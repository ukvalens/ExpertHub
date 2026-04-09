<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=transactions&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$lang      = $_GET['lang'] ?? 'en';

// Filters
$filter_status = $_GET['txn_status'] ?? '';
$filter_method = $_GET['txn_method'] ?? '';
$filter_from   = $_GET['txn_from']   ?? '';
$filter_to     = $_GET['txn_to']     ?? '';
$search        = trim($_GET['txn_search'] ?? '');
$pg            = max(1, (int)($_GET['txn_page'] ?? 1));
$per_page      = 5;
$offset        = ($pg - 1) * $per_page;

// Build WHERE clauses and params per role
$where  = [];
$params = [];
$types  = '';

if ($user_type === 'customer') {
    $where[]  = 'o.customer_id = ?';
    $params[] = $user_id; $types .= 'i';

} elseif ($user_type === 'provider') {
    $stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $sp_id = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    $where[]  = 'o.provider_id = ?';
    $params[] = $sp_id; $types .= 'i';
}
// admin: no role filter

if ($filter_status) { $where[] = 'p.payment_status = ?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_method) { $where[] = 'p.payment_method = ?'; $params[] = $filter_method;  $types .= 's'; }
if ($filter_from)   { $where[] = 'DATE(p.created_at) >= ?'; $params[] = $filter_from; $types .= 's'; }
if ($filter_to)     { $where[] = 'DATE(p.created_at) <= ?'; $params[] = $filter_to;   $types .= 's'; }
if ($search) {
    $like = "%$search%";
    $where[]  = '(o.order_number LIKE ? OR o.service_title LIKE ? OR uc.first_name LIKE ? OR uc.last_name LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like, $like]);
    $types   .= 'ssss';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$base_query = "FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN users uc ON o.customer_id = uc.id
    JOIN service_providers sp ON o.provider_id = sp.id
    JOIN users up ON sp.user_id = up.id
    $where_sql";

// Total count
$count_stmt = $conn->prepare("SELECT COUNT(*) $base_query");
if ($types) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total    = (int)$count_stmt->get_result()->fetch_row()[0];
$total_pg = max(1, ceil($total / $per_page));

// Fetch rows
$data_stmt = $conn->prepare("SELECT p.id, p.transaction_id, p.amount, p.provider_amount,
    p.platform_commission, p.payment_method, p.payment_status, p.payment_type,
    p.created_at, p.processed_at,
    o.order_number, o.service_title, o.status as order_status,
    uc.first_name as c_first, uc.last_name as c_last,
    up.first_name as p_first, up.last_name as p_last
    $base_query
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?");
$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . 'ii';
$data_stmt->bind_param($all_types, ...$all_params);
$data_stmt->execute();
$rows = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary stats
if ($user_type === 'customer') {
    $s = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN p.payment_status='completed' THEN p.amount END),0) as total,
        COUNT(CASE WHEN p.payment_status='completed' THEN 1 END) as count_done,
        COUNT(CASE WHEN p.payment_status='pending'   THEN 1 END) as count_pend
        FROM payments p JOIN orders o ON p.order_id=o.id WHERE o.customer_id=?");
    $s->bind_param("i", $user_id);
} elseif ($user_type === 'provider') {
    $s = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN p.payment_status='completed' THEN p.provider_amount END),0) as total,
        COUNT(CASE WHEN p.payment_status='completed' THEN 1 END) as count_done,
        COUNT(CASE WHEN p.payment_status='pending'   THEN 1 END) as count_pend
        FROM payments p JOIN orders o ON p.order_id=o.id
        JOIN service_providers sp ON o.provider_id=sp.id WHERE sp.user_id=?");
    $s->bind_param("i", $user_id);
} else {
    $s = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN payment_status='completed' THEN amount END),0) as total,
        COUNT(CASE WHEN payment_status='completed' THEN 1 END) as count_done,
        COUNT(CASE WHEN payment_status='pending'   THEN 1 END) as count_pend
        FROM payments");
    $s->execute();
}
if ($user_type !== 'admin') $s->execute();
$summary = $s->get_result()->fetch_assoc();

$status_color = ['completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'info','processing'=>'primary','disputed'=>'danger'];
$method_label = ['mtn_momo'=>'MTN MoMo','airtel_money'=>'Airtel Money','credit_card'=>'Credit Card','bank_transfer'=>'Bank Transfer','wallet'=>'Wallet','mobile_money'=>'Mobile Money'];

function txn_qs($extra = []) {
    $base = ['page'=>'transactions','lang'=>$_GET['lang']??'en'];
    foreach (['txn_status','txn_method','txn_from','txn_to','txn_search','txn_page'] as $k)
        if (!empty($_GET[$k])) $base[$k] = $_GET[$k];
    return http_build_query(array_merge($base, $extra));
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-exchange-alt me-2" style="color:var(--accent-color)"></i>
            <?php echo $user_type === 'admin' ? 'All Transactions' : 'My Transactions'; ?>
        </span>
        <small class="text-muted"><?php echo number_format($total); ?> record<?php echo $total !== 1 ? 's' : ''; ?></small>
    </div>
    <div class="card-body">

        <!-- Summary -->
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <div class="fw-bold text-success fs-6">$<?php echo number_format($summary['total'],2); ?></div>
                    <small class="text-muted"><?php echo $user_type === 'provider' ? 'Total Received' : ($user_type === 'admin' ? 'Total Volume' : 'Total Spent'); ?></small>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <div class="fw-bold text-primary fs-6"><?php echo (int)$summary['count_done']; ?></div>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <div class="fw-bold text-warning fs-6"><?php echo (int)$summary['count_pend']; ?></div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="index.php" class="mb-3">
            <input type="hidden" name="page" value="transactions">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <input type="text" class="form-control form-control-sm" name="txn_search"
                           placeholder="Order #, service, name…"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" name="txn_status">
                        <option value="">All statuses</option>
                        <?php foreach (array_keys($status_color) as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $filter_status===$st?'selected':''; ?>>
                                <?php echo ucfirst($st); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" name="txn_method">
                        <option value="">All methods</option>
                        <?php foreach ($method_label as $val => $lbl): ?>
                            <option value="<?php echo $val; ?>" <?php echo $filter_method===$val?'selected':''; ?>>
                                <?php echo $lbl; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control form-control-sm" name="txn_from"
                           value="<?php echo htmlspecialchars($filter_from); ?>" placeholder="From">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" class="form-control form-control-sm" name="txn_to"
                           value="<?php echo htmlspecialchars($filter_to); ?>" placeholder="To">
                </div>
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button class="btn btn-sm btn-primary flex-grow-1" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search || $filter_status || $filter_method || $filter_from || $filter_to): ?>
                    <a href="index.php?page=transactions&lang=<?php echo $lang; ?>"
                       class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="transactions">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Table -->
        <?php if (empty($rows)): ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No transactions found</h6>
                <p class="text-muted small">Try adjusting your filters.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" style="font-size:.82rem;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ref</th>
                        <th>Order</th>
                        <th>Service</th>
                        <?php if ($user_type !== 'customer'): ?><th>Customer</th><?php endif; ?>
                        <?php if ($user_type !== 'provider'): ?><th>Provider</th><?php endif; ?>
                        <th>Amount</th>
                        <?php if ($user_type === 'provider'): ?><th>Your Cut</th><?php endif; ?>
                        <?php if ($user_type === 'admin'): ?><th>Fee</th><?php endif; ?>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-muted text-nowrap">
                            <?php echo date('M j, Y', strtotime($r['created_at'])); ?><br>
                            <span style="font-size:.72rem;"><?php echo date('g:i A', strtotime($r['created_at'])); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border" style="font-size:.7rem;">
                                <?php echo htmlspecialchars($r['transaction_id'] ?? '—'); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                #<?php echo htmlspecialchars($r['order_number']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($r['service_title'],0,28,'…')); ?></td>
                        <?php if ($user_type !== 'customer'): ?>
                        <td><?php echo htmlspecialchars($r['c_first'].' '.$r['c_last']); ?></td>
                        <?php endif; ?>
                        <?php if ($user_type !== 'provider'): ?>
                        <td><?php echo htmlspecialchars($r['p_first'].' '.$r['p_last']); ?></td>
                        <?php endif; ?>
                        <td class="fw-bold text-primary">$<?php echo number_format($r['amount'],2); ?></td>
                        <?php if ($user_type === 'provider'): ?>
                        <td class="fw-bold text-success">$<?php echo number_format($r['provider_amount']??0,2); ?></td>
                        <?php endif; ?>
                        <?php if ($user_type === 'admin'): ?>
                        <td class="text-muted">$<?php echo number_format($r['platform_commission']??0,2); ?></td>
                        <?php endif; ?>
                        <td class="text-muted">
                            <?php echo $method_label[$r['payment_method']] ?? strtoupper(str_replace('_',' ',$r['payment_method']??'')); ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $status_color[$r['payment_status']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($r['payment_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pg > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-1">
                <?php if ($pg > 1): ?>
                    <li class="page-item">
                        <a class="page-link nav-link-ajax" data-page="transactions"
                           href="index.php?<?php echo txn_qs(['txn_page'=>$pg-1]); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                <?php
                $start = max(1, $pg - 2);
                $end   = min($total_pg, $pg + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <li class="page-item <?php echo $i===$pg?'active':''; ?>">
                        <a class="page-link nav-link-ajax" data-page="transactions"
                           href="index.php?<?php echo txn_qs(['txn_page'=>$i]); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <?php if ($pg < $total_pg): ?>
                    <li class="page-item">
                        <a class="page-link nav-link-ajax" data-page="transactions"
                           href="index.php?<?php echo txn_qs(['txn_page'=>$pg+1]); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <p class="text-center text-muted small">
                Showing <?php echo min($offset+1,$total); ?>–<?php echo min($offset+$per_page,$total); ?> of <?php echo number_format($total); ?>
            </p>
        </nav>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<script>
// Make pagination links load via AJAX preserving all filter params
(function(){
    document.querySelectorAll('a.nav-link-ajax[data-page="transactions"]').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const url  = new URL(a.href, location.href);
            const params = url.search.slice(1);
            history.pushState({page:'transactions',extra:{}}, '', 'index.php?' + params);
            const mc = document.getElementById('mainContent');
            mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
            fetch('index.php?' + params, {headers:{'X-Requested-With':'XMLHttpRequest'}})
                .then(r=>r.text()).then(html=>{
                    mc.innerHTML = html;
                    mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                    if(typeof bindAjaxLinks==='function') bindAjaxLinks();
                });
        });
    });
})();
</script>
