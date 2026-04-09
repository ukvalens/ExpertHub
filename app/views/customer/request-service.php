<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    $did  = isset($_GET['device_id']) ? '&device_id='.(int)$_GET['device_id'] : '';
    header("Location: ../dashboard/index.php?page=request-service&lang=$lang$did"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id   = $_SESSION['user_id'];
$lang      = $_GET['lang'] ?? 'en';
$device_id = (int)($_GET['device_id'] ?? 0);
$pg        = max(1, (int)($_GET['svc_page'] ?? 1));
$per_page  = 6;
$offset    = ($pg - 1) * $per_page;

// Optional device context
$device = null;
if ($device_id) {
    $stmt = $conn->prepare("SELECT * FROM customer_devices WHERE id=? AND customer_id=?");
    $stmt->bind_param("ii", $device_id, $user_id);
    $stmt->execute();
    $device = $stmt->get_result()->fetch_assoc();
}

// Build keyword pattern from device type
$keyword_map = [
    'laptop'         => 'laptop|computer|repair|maintenance',
    'desktop'        => 'desktop|computer|repair|maintenance',
    'printer'        => 'printer|printing|repair',
    'server'         => 'server|network|maintenance',
    'network_device' => 'network|router|wifi',
    'mobile'         => 'mobile|phone|repair',
    'other'          => 'repair|maintenance|support',
];
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like = "%$search%";
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM provider_services ps
        JOIN service_providers sp ON ps.provider_id=sp.id
        WHERE ps.status='active' AND (ps.title LIKE ? OR ps.description LIKE ?)");
    $count_stmt->bind_param("ss", $like, $like);
    $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT ps.*, u.first_name, u.last_name, sp.rating, sp.verification_status,
        u.profile_image,
        (SELECT COUNT(*) FROM orders WHERE provider_id=sp.id AND status='completed') as total_orders
        FROM provider_services ps
        JOIN service_providers sp ON ps.provider_id=sp.id
        JOIN users u ON sp.user_id=u.id
        WHERE ps.status='active' AND (ps.title LIKE ? OR ps.description LIKE ?)
        ORDER BY sp.rating DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $like, $like, $per_page, $offset);
} elseif ($device) {
    $pattern = $keyword_map[$device['device_type']] ?? $keyword_map['other'];
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM provider_services ps
        JOIN service_providers sp ON ps.provider_id=sp.id
        WHERE ps.status='active' AND (ps.title REGEXP ? OR ps.description REGEXP ?)");
    $count_stmt->bind_param("ss", $pattern, $pattern);
    $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT ps.*, u.first_name, u.last_name, sp.rating, sp.verification_status,
        u.profile_image,
        (SELECT COUNT(*) FROM orders WHERE provider_id=sp.id AND status='completed') as total_orders
        FROM provider_services ps
        JOIN service_providers sp ON ps.provider_id=sp.id
        JOIN users u ON sp.user_id=u.id
        WHERE ps.status='active' AND (ps.title REGEXP ? OR ps.description REGEXP ?)
        ORDER BY sp.rating DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $pattern, $pattern, $per_page, $offset);
} else {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM provider_services WHERE status='active'");
    $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_row()[0];

    $stmt = $conn->prepare("SELECT ps.*, u.first_name, u.last_name, sp.rating, sp.verification_status,
        u.profile_image,
        (SELECT COUNT(*) FROM orders WHERE provider_id=sp.id AND status='completed') as total_orders
        FROM provider_services ps
        JOIN service_providers sp ON ps.provider_id=sp.id
        JOIN users u ON sp.user_id=u.id
        WHERE ps.status='active'
        ORDER BY sp.rating DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$services    = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_pages = max(1, ceil($total / $per_page));

$icon_map = ['laptop'=>'laptop','desktop'=>'desktop','printer'=>'print','mobile'=>'mobile-alt','server'=>'server','network_device'=>'network-wired','other'=>'microchip'];
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-wrench me-2" style="color:var(--accent-color)"></i>Request Service</span>
        <?php if ($device): ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadPage('devices')">
                <i class="fas fa-arrow-left me-1"></i>Back to Devices
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <?php if ($device): ?>
        <!-- Device context banner -->
        <div class="d-flex align-items-center gap-3 p-3 rounded mb-4" style="background:var(--light-bg)">
            <i class="fas fa-<?php echo $icon_map[$device['device_type']] ?? 'microchip'; ?> fa-2x text-primary"></i>
            <div>
                <h6 class="mb-0"><?php echo htmlspecialchars($device['brand'].' '.$device['model']); ?></h6>
                <small class="text-muted">
                    <?php echo ucfirst(str_replace('_',' ',$device['device_type'])); ?>
                    <?php if ($device['serial_number']): ?> · S/N: <?php echo htmlspecialchars($device['serial_number']); ?><?php endif; ?>
                </small>
            </div>
            <span class="ms-auto badge bg-info text-dark">Showing services for this device</span>
        </div>
        <?php endif; ?>

        <!-- Search bar -->
        <form id="rsSearchForm" class="mb-4">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="rsSearchInput"
                       placeholder="Search services by name or description..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <button class="btn btn-outline-secondary" type="button" id="rsClearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($services)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No services found</h6>
                <p class="text-muted small">Try a different search or browse all services.</p>
                <button class="btn btn-sm btn-primary nav-link-ajax" data-page="browse-services"
                        onclick="loadPage('browse-services')">
                    <i class="fas fa-search me-1"></i>Browse All Services
                </button>
            </div>
        <?php else: ?>

            <div class="row g-3" id="rsServiceGrid">
                <?php foreach ($services as $svc): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h6 class="text-primary mb-1" style="font-size:.88rem;">
                                <?php echo htmlspecialchars($svc['title']); ?>
                            </h6>
                            <p class="text-muted small flex-grow-1 mb-2">
                                <?php echo htmlspecialchars(mb_strimwidth($svc['description'] ?? '', 0, 90, '...')); ?>
                            </p>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if (!empty($svc['profile_image'])): ?>
                                    <img src="../../../<?php echo htmlspecialchars($svc['profile_image']); ?>"
                                         class="rounded-circle" style="width:24px;height:24px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                                         style="width:24px;height:24px;font-size:.6rem;">
                                        <?php echo strtoupper(substr($svc['first_name'],0,1).substr($svc['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted"><?php echo htmlspecialchars($svc['first_name'].' '.$svc['last_name']); ?></small>
                                <?php if ($svc['verification_status'] === 'verified'): ?>
                                    <i class="fas fa-check-circle text-success" style="font-size:.7rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-warning" style="font-size:.75rem;">
                                        <?php for($i=1;$i<=5;$i++) echo '<i class="fas fa-star'.($i<=round($svc['rating']??0)?'':'-o').'"></i>'; ?>
                                    </span>
                                    <small class="text-muted ms-1">(<?php echo (int)$svc['total_orders']; ?>)</small>
                                </div>
                                <span class="fw-bold text-success">$<?php echo number_format($svc['base_price'],2); ?></span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent py-2">
                            <button class="btn btn-sm btn-primary w-100"
                                    onclick="loadOrderPage(<?php echo $svc['id']; ?>, <?php echo $device_id ?: 0; ?>)">
                                <i class="fas fa-shopping-cart me-1"></i>Order Now
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination pagination-sm justify-content-center mb-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $pg ? 'active' : ''; ?>">
                        <button class="page-link" onclick="rsGoPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                    </li>
                    <?php endfor; ?>
                </ul>
                <p class="text-center text-muted small">
                    Showing <?php echo min($offset+1,$total); ?>–<?php echo min($offset+$per_page,$total); ?> of <?php echo $total; ?>
                </p>
            </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    const lang     = '<?php echo $lang; ?>';
    const deviceId = <?php echo $device_id ?: 0; ?>;

    window.loadOrderPage = function(serviceId, devId) {
        const extra = {service_id: serviceId};
        if (devId) extra.device_id = devId;
        loadPage('order', true, extra);
    };

    window.rsGoPage = function(p) {
        const q = new URLSearchParams({
            page: 'request-service', lang,
            svc_page: p,
            search: document.getElementById('rsSearchInput')?.value || ''
        });
        if (deviceId) q.set('device_id', deviceId);
        history.pushState({page:'request-service',extra:{}}, '', 'index.php?' + q);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + q, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    };

    document.getElementById('rsSearchForm')?.addEventListener('submit', e => {
        e.preventDefault();
        rsGoPage(1);
    });

    document.getElementById('rsClearSearch')?.addEventListener('click', () => {
        document.getElementById('rsSearchInput').value = '';
        rsGoPage(1);
    });
})();
</script>
