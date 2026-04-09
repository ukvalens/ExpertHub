<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=invoices&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$lang      = $_GET['lang'] ?? 'en';
$view_id   = (int)($_GET['invoice_id'] ?? 0); // order id used as invoice id

// Resolve provider_id
$sp_id = 0;
if ($user_type === 'provider') {
    $s = $conn->prepare("SELECT id FROM service_providers WHERE user_id=?");
    $s->bind_param("i", $user_id); $s->execute();
    $sp_id = (int)($s->get_result()->fetch_assoc()['id'] ?? 0);
}

// ── Single invoice view ──────────────────────────────────────────────────────
if ($view_id) {
    $stmt = $conn->prepare("SELECT o.*,
        uc.first_name as c_first, uc.last_name as c_last,
        uc.email as c_email, uc.phone as c_phone, uc.country as c_country,
        up.first_name as p_first, up.last_name as p_last,
        up.email as p_email, up.phone as p_phone,
        sp.professional_title, sp.hourly_rate,
        p.amount, p.provider_amount, p.platform_commission,
        p.payment_method, p.payment_status, p.transaction_id, p.processed_at
        FROM orders o
        JOIN users uc ON o.customer_id = uc.id
        JOIN service_providers sp ON o.provider_id = sp.id
        JOIN users up ON sp.user_id = up.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ?");
    $stmt->bind_param("i", $view_id); $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();

    // Access control
    $allowed = match($user_type) {
        'customer' => $inv && $inv['customer_id'] == $user_id,
        'provider' => $inv && $inv['provider_id'] == $sp_id,
        'admin'    => (bool)$inv,
        default    => false,
    };
    if (!$allowed) { echo '<div class="alert alert-danger">Invoice not found.</div>'; return; }

    $inv_number = 'INV-' . strtoupper(substr(md5($inv['order_number']), 0, 8));
    $subtotal   = (float)($inv['amount'] ?? $inv['final_price'] ?? $inv['quoted_price'] ?? 0);
    $commission = (float)($inv['platform_commission'] ?? $subtotal * 0.10);
    $provider_cut = (float)($inv['provider_amount'] ?? $subtotal - $commission);
    $method_label = ['mtn_momo'=>'MTN MoMo','airtel_money'=>'Airtel Money','credit_card'=>'Credit Card','bank_transfer'=>'Bank Transfer','wallet'=>'Wallet','mobile_money'=>'Mobile Money'];
    ?>
    <style>
    @media print {
        .no-print { display:none !important; }
        .content-card { box-shadow:none !important; border:none !important; }
        body { background:#fff !important; }
    }
    .inv-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:#888; }
    .inv-divider { border-top:2px solid var(--primary-color,#0d6efd); margin:1rem 0; }
    </style>

    <div class="content-card">
        <div class="card-header d-flex justify-content-between align-items-center no-print">
            <span><i class="fas fa-file-invoice me-2" style="color:var(--accent-color)"></i>Invoice <?php echo $inv_number; ?></span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="loadPage('invoices')">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </button>
                <button class="btn btn-sm btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print / PDF
                </button>
            </div>
        </div>
        <div class="card-body p-4">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h4 class="fw-bold mb-0" style="color:var(--primary-color)">
                        <i class="fas fa-users-cog me-2"></i>ExpertHub
                    </h4>
                    <small class="text-muted">ICT Support & Maintenance Marketplace</small>
                </div>
                <div class="text-end">
                    <h5 class="fw-bold mb-0">INVOICE</h5>
                    <div class="text-muted small"><?php echo $inv_number; ?></div>
                    <span class="badge bg-<?php echo $inv['payment_status']==='completed'?'success':($inv['payment_status']==='pending'?'warning':'secondary'); ?> mt-1">
                        <?php echo ucfirst($inv['payment_status'] ?? 'pending'); ?>
                    </span>
                </div>
            </div>
            <div class="inv-divider"></div>

            <!-- Parties -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="inv-label mb-1">Bill To (Customer)</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($inv['c_first'].' '.$inv['c_last']); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($inv['c_email']); ?></div>
                    <?php if ($inv['c_phone']): ?><div class="text-muted small"><?php echo htmlspecialchars($inv['c_phone']); ?></div><?php endif; ?>
                    <?php if ($inv['c_country']): ?><div class="text-muted small"><?php echo htmlspecialchars($inv['c_country']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div class="inv-label mb-1">Service Provider</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($inv['p_first'].' '.$inv['p_last']); ?></div>
                    <?php if ($inv['professional_title']): ?><div class="text-muted small"><?php echo htmlspecialchars($inv['professional_title']); ?></div><?php endif; ?>
                    <div class="text-muted small"><?php echo htmlspecialchars($inv['p_email']); ?></div>
                    <?php if ($inv['p_phone']): ?><div class="text-muted small"><?php echo htmlspecialchars($inv['p_phone']); ?></div><?php endif; ?>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="inv-label mb-1">Invoice Details</div>
                    <div class="small"><span class="text-muted">Order #:</span> <strong><?php echo htmlspecialchars($inv['order_number']); ?></strong></div>
                    <div class="small"><span class="text-muted">Issued:</span> <?php echo date('M j, Y', strtotime($inv['created_at'])); ?></div>
                    <?php if ($inv['processed_at']): ?>
                    <div class="small"><span class="text-muted">Paid:</span> <?php echo date('M j, Y', strtotime($inv['processed_at'])); ?></div>
                    <?php endif; ?>
                    <?php if ($inv['transaction_id']): ?>
                    <div class="small"><span class="text-muted">Ref:</span> <?php echo htmlspecialchars($inv['transaction_id']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Line items -->
            <table class="table table-bordered mb-4" style="font-size:.85rem;">
                <thead style="background:var(--light-bg)">
                    <tr>
                        <th>Description</th>
                        <th class="text-center" style="width:100px">Status</th>
                        <th class="text-end" style="width:120px">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($inv['service_title']); ?></div>
                            <?php if ($inv['service_description']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($inv['service_description'],0,120,'…')); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $inv['status']==='completed'?'success':($inv['status']==='in_progress'?'warning':'primary'); ?>">
                                <?php echo ucfirst(str_replace('_',' ',$inv['status'])); ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold">$<?php echo number_format($subtotal,2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end text-muted">Subtotal</td>
                        <td class="text-end">$<?php echo number_format($subtotal,2); ?></td>
                    </tr>
                    <?php if ($user_type === 'admin' || $user_type === 'provider'): ?>
                    <tr>
                        <td colspan="2" class="text-end text-muted">Platform Commission (10%)</td>
                        <td class="text-end text-danger">-$<?php echo number_format($commission,2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="text-end text-muted">Provider Payout</td>
                        <td class="text-end text-success">$<?php echo number_format($provider_cut,2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="background:var(--light-bg)">
                        <td colspan="2" class="text-end fw-bold">Total</td>
                        <td class="text-end fw-bold text-primary fs-6">$<?php echo number_format($subtotal,2); ?></td>
                    </tr>
                </tfoot>
            </table>

            <!-- Payment info -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background:var(--light-bg)">
                        <div class="inv-label mb-1">Payment Method</div>
                        <div class="fw-semibold">
                            <i class="fas fa-mobile-alt me-1 text-warning"></i>
                            <?php echo $method_label[$inv['payment_method']] ?? strtoupper(str_replace('_',' ',$inv['payment_method'] ?? 'N/A')); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background:var(--light-bg)">
                        <div class="inv-label mb-1">Notes</div>
                        <div class="text-muted small">Thank you for using ExpertHub. For support, contact support@experthub.com</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php
    return;
}

// ── Invoice list ─────────────────────────────────────────────────────────────
$filter_status = $_GET['inv_status'] ?? '';
$search        = trim($_GET['inv_search'] ?? '');
$pg            = max(1, (int)($_GET['inv_page'] ?? 1));
$per_page      = 10;
$offset        = ($pg - 1) * $per_page;

$where  = ["o.status IN ('completed','in_progress','accepted','requested')"];
$params = [];
$types  = '';

if ($user_type === 'customer') {
    $where[] = 'o.customer_id = ?'; $params[] = $user_id; $types .= 'i';
} elseif ($user_type === 'provider') {
    $where[] = 'o.provider_id = ?'; $params[] = $sp_id; $types .= 'i';
}
if ($filter_status) { $where[] = 'o.status = ?'; $params[] = $filter_status; $types .= 's'; }
if ($search) {
    $like = "%$search%";
    $where[] = '(o.order_number LIKE ? OR o.service_title LIKE ? OR uc.first_name LIKE ? OR uc.last_name LIKE ?)';
    $params  = array_merge($params, [$like,$like,$like,$like]); $types .= 'ssss';
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$base = "FROM orders o
    JOIN users uc ON o.customer_id=uc.id
    JOIN service_providers sp ON o.provider_id=sp.id
    JOIN users up ON sp.user_id=up.id
    LEFT JOIN payments p ON o.id=p.order_id
    $where_sql";

$cs = $conn->prepare("SELECT COUNT(DISTINCT o.id) $base");
if ($types) $cs->bind_param($types, ...$params);
$cs->execute();
$total    = (int)$cs->get_result()->fetch_row()[0];
$total_pg = max(1, ceil($total / $per_page));

$ds = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status,
    o.quoted_price, o.final_price, o.created_at, o.completed_at,
    uc.first_name as c_first, uc.last_name as c_last,
    up.first_name as p_first, up.last_name as p_last,
    p.amount, p.payment_status, p.transaction_id
    $base
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?");
$all_p = array_merge($params, [$per_page, $offset]);
$all_t = $types . 'ii';
$ds->bind_param($all_t, ...$all_p);
$ds->execute();
$invoices = $ds->get_result()->fetch_all(MYSQLI_ASSOC);

$status_color = ['completed'=>'success','in_progress'=>'warning','accepted'=>'primary','requested'=>'info','cancelled'=>'danger','disputed'=>'danger'];
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-invoice me-2" style="color:var(--accent-color)"></i>
            <?php echo $user_type === 'admin' ? 'All Invoices' : 'My Invoices'; ?>
        </span>
        <small class="text-muted"><?php echo number_format($total); ?> invoice<?php echo $total!==1?'s':''; ?></small>
    </div>
    <div class="card-body">

        <!-- Filters -->
        <form method="GET" action="index.php" class="mb-3">
            <input type="hidden" name="page" value="invoices">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <input type="text" class="form-control form-control-sm" name="inv_search"
                           placeholder="Order #, service, name…"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select form-select-sm" name="inv_status">
                        <option value="">All statuses</option>
                        <?php foreach (array_keys($status_color) as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $filter_status===$st?'selected':''; ?>>
                                <?php echo ucfirst(str_replace('_',' ',$st)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-1">
                    <button class="btn btn-sm btn-primary flex-grow-1" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search || $filter_status): ?>
                    <a href="index.php?page=invoices&lang=<?php echo $lang; ?>"
                       class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="invoices">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if (empty($invoices)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No invoices found</h6>
                <p class="text-muted small">Invoices are generated automatically for each order.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" style="font-size:.83rem;">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Order</th>
                        <th>Service</th>
                        <?php if ($user_type !== 'customer'): ?><th>Customer</th><?php endif; ?>
                        <?php if ($user_type !== 'provider'): ?><th>Provider</th><?php endif; ?>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv):
                        $inv_num = 'INV-' . strtoupper(substr(md5($inv['order_number']), 0, 8));
                        $amount  = $inv['amount'] ?? $inv['final_price'] ?? $inv['quoted_price'] ?? 0;
                    ?>
                    <tr>
                        <td class="fw-semibold text-primary" style="font-size:.78rem;"><?php echo $inv_num; ?></td>
                        <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($inv['order_number']); ?></span></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($inv['service_title'],0,28,'…')); ?></td>
                        <?php if ($user_type !== 'customer'): ?>
                        <td><?php echo htmlspecialchars($inv['c_first'].' '.$inv['c_last']); ?></td>
                        <?php endif; ?>
                        <?php if ($user_type !== 'provider'): ?>
                        <td><?php echo htmlspecialchars($inv['p_first'].' '.$inv['p_last']); ?></td>
                        <?php endif; ?>
                        <td class="fw-bold text-success">$<?php echo number_format($amount,2); ?></td>
                        <td class="text-muted"><?php echo date('M j, Y', strtotime($inv['created_at'])); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $status_color[$inv['status']] ?? 'secondary'; ?>">
                                <?php echo ucfirst(str_replace('_',' ',$inv['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="loadInvoice(<?php echo $inv['id']; ?>)">
                                <i class="fas fa-eye me-1"></i>View
                            </button>
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
                    <a class="page-link" href="#" onclick="invGoPage(<?php echo $pg-1; ?>);return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                <?php for ($i = max(1,$pg-2); $i <= min($total_pg,$pg+2); $i++): ?>
                <li class="page-item <?php echo $i===$pg?'active':''; ?>">
                    <a class="page-link" href="#" onclick="invGoPage(<?php echo $i; ?>);return false;"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pg < $total_pg): ?>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="invGoPage(<?php echo $pg+1; ?>);return false;">
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
(function(){
    const lang = '<?php echo $lang; ?>';

    window.loadInvoice = function(id) {
        const params = 'page=invoices&lang=' + lang + '&invoice_id=' + id;
        history.pushState({page:'invoices',extra:{invoice_id:id}}, '', 'index.php?' + params);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + params, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    };

    window.invGoPage = function(p) {
        const q = new URLSearchParams(location.search);
        q.set('page','invoices'); q.set('lang',lang); q.set('inv_page',p);
        history.pushState({page:'invoices',extra:{}}, '', 'index.php?' + q);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + q, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    };
})();
</script>
