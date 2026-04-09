<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=clients&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$lang      = $_GET['lang'] ?? 'en';

// Resolve provider_id
$sp_id = 0;
if ($user_type === 'provider') {
    $s = $conn->prepare("SELECT id FROM service_providers WHERE user_id=?");
    $s->bind_param("i", $user_id); $s->execute();
    $sp_id = (int)($s->get_result()->fetch_assoc()['id'] ?? 0);
}

$search   = trim($_GET['cl_search'] ?? '');
$pg       = max(1, (int)($_GET['cl_page'] ?? 1));
$per_page = 10;
$offset   = ($pg - 1) * $per_page;
$like     = "%$search%";

// ── View single client profile ───────────────────────────────────────────────
$view_uid = (int)($_GET['client_id'] ?? 0);
if ($view_uid) {
    // Fetch user
    $stmt = $conn->prepare("SELECT u.*, sp.professional_title, sp.rating, sp.total_reviews,
        sp.verification_status, sp.experience_years, sp.bio, sp.hourly_rate, sp.completion_rate
        FROM users u LEFT JOIN service_providers sp ON u.id=sp.user_id
        WHERE u.id=?");
    $stmt->bind_param("i", $view_uid); $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    if (!$client) { echo '<div class="alert alert-danger">User not found.</div>'; return; }

    // Shared orders
    if ($user_type === 'provider') {
        $stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status,
            COALESCE(o.final_price,o.quoted_price) as amount, o.created_at, o.completed_at
            FROM orders o WHERE o.provider_id=? AND o.customer_id=?
            ORDER BY o.created_at DESC LIMIT 10");
        $stmt->bind_param("ii", $sp_id, $view_uid);
    } elseif ($user_type === 'customer') {
        $stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status,
            COALESCE(o.final_price,o.quoted_price) as amount, o.created_at, o.completed_at
            FROM orders o
            JOIN service_providers sp ON o.provider_id=sp.id
            WHERE o.customer_id=? AND sp.user_id=?
            ORDER BY o.created_at DESC LIMIT 10");
        $stmt->bind_param("ii", $user_id, $view_uid);
    } else {
        $stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status,
            COALESCE(o.final_price,o.quoted_price) as amount, o.created_at, o.completed_at
            FROM orders o
            JOIN service_providers sp ON o.provider_id=sp.id
            WHERE o.customer_id=? OR sp.user_id=?
            ORDER BY o.created_at DESC LIMIT 10");
        $stmt->bind_param("ii", $view_uid, $view_uid);
    }
    $stmt->execute();
    $shared_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $sc = ['completed'=>'success','in_progress'=>'warning','accepted'=>'primary','requested'=>'info','cancelled'=>'danger'];
    ?>
    <div class="content-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user me-2" style="color:var(--accent-color)"></i>
                <?php echo htmlspecialchars($client['first_name'].' '.$client['last_name']); ?>
            </span>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadPage('clients')">
                <i class="fas fa-arrow-left me-1"></i>Back
            </button>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Profile card -->
                <div class="col-md-4">
                    <div class="text-center p-3 rounded" style="background:var(--light-bg)">
                        <?php if (!empty($client['profile_image'])): ?>
                            <img src="../../../<?php echo htmlspecialchars($client['profile_image']); ?>"
                                 class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-2"
                                 style="width:80px;height:80px;font-size:1.6rem;">
                                <?php echo strtoupper(substr($client['first_name'],0,1).substr($client['last_name'],0,1)); ?>
                            </div>
                        <?php endif; ?>
                        <h6 class="mb-0"><?php echo htmlspecialchars($client['first_name'].' '.$client['last_name']); ?></h6>
                        <?php if ($client['professional_title']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($client['professional_title']); ?></small><br>
                        <?php endif; ?>
                        <span class="badge bg-<?php echo $client['user_type']==='provider'?'primary':($client['user_type']==='admin'?'danger':'success'); ?> mt-1">
                            <?php echo ucfirst($client['user_type']); ?>
                        </span>
                        <?php if ($client['verification_status']==='verified'): ?>
                            <span class="badge bg-success ms-1"><i class="fas fa-check-circle me-1"></i>Verified</span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3" style="font-size:.84rem;">
                        <?php if ($client['email']): ?>
                        <div class="d-flex gap-2 mb-2">
                            <i class="fas fa-envelope text-muted mt-1"></i>
                            <span><?php echo htmlspecialchars($client['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($client['phone']): ?>
                        <div class="d-flex gap-2 mb-2">
                            <i class="fas fa-phone text-muted mt-1"></i>
                            <span><?php echo htmlspecialchars($client['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($client['country']): ?>
                        <div class="d-flex gap-2 mb-2">
                            <i class="fas fa-map-marker-alt text-muted mt-1"></i>
                            <span><?php echo htmlspecialchars($client['country']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mb-2">
                            <i class="fas fa-calendar text-muted mt-1"></i>
                            <span>Joined <?php echo date('M Y', strtotime($client['created_at'])); ?></span>
                        </div>
                    </div>

                    <?php if ($client['user_type'] === 'provider'): ?>
                    <div class="row g-2 mt-1 text-center" style="font-size:.8rem;">
                        <div class="col-6">
                            <div class="p-2 rounded" style="background:var(--light-bg)">
                                <div class="fw-bold text-warning"><?php echo number_format($client['rating']??0,1); ?> ★</div>
                                <div class="text-muted">Rating</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded" style="background:var(--light-bg)">
                                <div class="fw-bold text-primary"><?php echo (int)$client['experience_years']; ?>y</div>
                                <div class="text-muted">Experience</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user_type === 'provider' && !empty($shared_orders)): ?>
                    <div class="mt-3 d-grid gap-2">
                        <button class="btn btn-sm btn-outline-primary"
                                onclick="loadPage('provider-messages')">
                            <i class="fas fa-comments me-1"></i>Send Message
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Orders history -->
                <div class="col-md-8">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-history me-2 text-muted"></i>
                        <?php echo $user_type==='customer' ? 'Orders with this Provider' : 'Order History'; ?>
                    </h6>
                    <?php if (empty($shared_orders)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">No shared orders yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" style="font-size:.82rem;">
                                <thead>
                                    <tr><th>Order</th><th>Service</th><th>Amount</th><th>Date</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shared_orders as $o): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($o['order_number']); ?></span></td>
                                        <td><?php echo htmlspecialchars(mb_strimwidth($o['service_title'],0,28,'…')); ?></td>
                                        <td class="text-success fw-bold">$<?php echo number_format($o['amount']??0,2); ?></td>
                                        <td class="text-muted"><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
                                        <td><span class="badge bg-<?php echo $sc[$o['status']]??'secondary'; ?>"><?php echo ucfirst(str_replace('_',' ',$o['status'])); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($client['bio']): ?>
                    <div class="mt-3 p-3 rounded" style="background:var(--light-bg)">
                        <div class="text-muted small fw-semibold mb-1">Bio</div>
                        <p class="small mb-0"><?php echo htmlspecialchars($client['bio']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// ── Client list ──────────────────────────────────────────────────────────────
if ($user_type === 'provider') {
    // Unique customers who placed orders with this provider
    $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT o.customer_id)
        FROM orders o JOIN users u ON o.customer_id=u.id
        WHERE o.provider_id=? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)");
    $count_stmt->bind_param("isss", $sp_id, $like, $like, $like); $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
        u.profile_image, u.country, u.created_at,
        COUNT(o.id) as total_orders,
        SUM(CASE WHEN o.status='completed' THEN 1 END) as completed_orders,
        COALESCE(SUM(CASE WHEN o.status='completed' THEN COALESCE(o.final_price,o.quoted_price) END),0) as total_spent,
        MAX(o.created_at) as last_order
        FROM orders o JOIN users u ON o.customer_id=u.id
        WHERE o.provider_id=? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
        GROUP BY u.id ORDER BY last_order DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("isssii", $sp_id, $like, $like, $like, $per_page, $offset);
    $title = 'My Clients';
    $empty_msg = 'No clients yet. Complete orders to build your client list.';

} elseif ($user_type === 'customer') {
    // Unique providers the customer has ordered from
    $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT sp.user_id)
        FROM orders o JOIN service_providers sp ON o.provider_id=sp.id JOIN users u ON sp.user_id=u.id
        WHERE o.customer_id=? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)");
    $count_stmt->bind_param("isss", $user_id, $like, $like, $like); $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
        u.profile_image, u.country, u.created_at,
        sp.professional_title, sp.rating, sp.verification_status,
        COUNT(o.id) as total_orders,
        SUM(CASE WHEN o.status='completed' THEN 1 END) as completed_orders,
        COALESCE(SUM(CASE WHEN o.status='completed' THEN COALESCE(o.final_price,o.quoted_price) END),0) as total_spent,
        MAX(o.created_at) as last_order
        FROM orders o JOIN service_providers sp ON o.provider_id=sp.id JOIN users u ON sp.user_id=u.id
        WHERE o.customer_id=? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
        GROUP BY u.id ORDER BY last_order DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("isssii", $user_id, $like, $like, $like, $per_page, $offset);
    $title = 'My Providers';
    $empty_msg = 'No providers yet. Order a service to see your providers here.';

} else {
    // Admin: all users
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type != 'admin' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)");
    $count_stmt->bind_param("sss", $like, $like, $like); $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
        u.profile_image, u.country, u.created_at, u.user_type, u.status,
        sp.professional_title, sp.rating, sp.verification_status,
        (SELECT COUNT(*) FROM orders WHERE customer_id=u.id OR provider_id IN (SELECT id FROM service_providers WHERE user_id=u.id)) as total_orders
        FROM users u LEFT JOIN service_providers sp ON u.id=sp.user_id
        WHERE u.user_type != 'admin' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
        ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $like, $like, $like, $per_page, $offset);
    $title = 'All Users';
    $empty_msg = 'No users found.';
}

$stmt->execute();
$clients   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_pg  = max(1, ceil($total / $per_page));
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users me-2" style="color:var(--accent-color)"></i><?php echo $title; ?></span>
        <small class="text-muted"><?php echo number_format($total); ?> record<?php echo $total!==1?'s':''; ?></small>
    </div>
    <div class="card-body">

        <!-- Search -->
        <form method="GET" action="index.php" class="mb-3">
            <input type="hidden" name="page" value="clients">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" name="cl_search"
                       placeholder="Search by name or email…"
                       value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                <a href="index.php?page=clients&lang=<?php echo $lang; ?>"
                   class="btn btn-outline-secondary nav-link-ajax" data-page="clients">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($clients)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h6 class="text-muted"><?php echo $empty_msg; ?></h6>
            </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($clients as $c): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm" style="cursor:pointer"
                     onclick="loadClientProfile(<?php echo $c['id']; ?>)">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <!-- Avatar -->
                        <?php if (!empty($c['profile_image'])): ?>
                            <img src="../../../<?php echo htmlspecialchars($c['profile_image']); ?>"
                                 class="rounded-circle flex-shrink-0"
                                 style="width:48px;height:48px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:48px;height:48px;font-size:.9rem;">
                                <?php echo strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1)); ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-0" style="font-size:.88rem;">
                                        <?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?>
                                    </h6>
                                    <?php if (!empty($c['professional_title'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($c['professional_title'],0,30,'…')); ?></small>
                                    <?php elseif (!empty($c['user_type'])): ?>
                                        <small class="text-muted"><?php echo ucfirst($c['user_type']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($c['verification_status']) && $c['verification_status']==='verified'): ?>
                                    <i class="fas fa-check-circle text-success" style="font-size:.8rem;"></i>
                                <?php endif; ?>
                            </div>

                            <div class="mt-1" style="font-size:.78rem;">
                                <?php if ($c['email']): ?>
                                <div class="text-muted text-truncate"><?php echo htmlspecialchars($c['email']); ?></div>
                                <?php endif; ?>
                                <?php if ($c['country']): ?>
                                <div class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($c['country']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-3 mt-2" style="font-size:.78rem;">
                                <span class="text-muted">
                                    <i class="fas fa-shopping-bag me-1"></i><?php echo (int)($c['total_orders']??0); ?> orders
                                </span>
                                <?php if (isset($c['total_spent']) && $c['total_spent'] > 0): ?>
                                <span class="text-success fw-bold">
                                    $<?php echo number_format($c['total_spent'],2); ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($c['rating'])): ?>
                                <span class="text-warning">
                                    <i class="fas fa-star"></i> <?php echo number_format($c['rating'],1); ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($c['last_order'])): ?>
                            <div class="text-muted mt-1" style="font-size:.72rem;">
                                Last order: <?php echo date('M j, Y', strtotime($c['last_order'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pg > 1): ?>
        <nav class="mt-4">
            <ul class="pagination pagination-sm justify-content-center mb-1">
                <?php if ($pg > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="clGoPage(<?php echo $pg-1; ?>);return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                <?php for ($i = max(1,$pg-2); $i <= min($total_pg,$pg+2); $i++): ?>
                <li class="page-item <?php echo $i===$pg?'active':''; ?>">
                    <a class="page-link" href="#" onclick="clGoPage(<?php echo $i; ?>);return false;"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pg < $total_pg): ?>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="clGoPage(<?php echo $pg+1; ?>);return false;">
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

    window.loadClientProfile = function(id) {
        const params = 'page=clients&lang=' + lang + '&client_id=' + id;
        history.pushState({page:'clients',extra:{client_id:id}}, '', 'index.php?' + params);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + params, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    };

    window.clGoPage = function(p) {
        const q = new URLSearchParams(location.search);
        q.set('page','clients'); q.set('lang',lang); q.set('cl_page',p);
        if ('<?php echo htmlspecialchars($search); ?>') q.set('cl_search','<?php echo htmlspecialchars($search); ?>');
        history.pushState({page:'clients',extra:{}}, '', 'index.php?' + q);
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
