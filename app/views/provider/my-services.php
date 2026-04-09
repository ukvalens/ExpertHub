<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=my-services&lang=$lang");
    exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

if (!$provider) {
    $stmt = $conn->prepare("INSERT INTO service_providers (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $provider_id = $conn->insert_id;
} else {
    $provider_id = $provider['id'];
}

// Handle status toggle (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $service_id = (int)$_POST['service_id'];
    $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
    $stmt = $conn->prepare("UPDATE provider_services SET status = ? WHERE id = ? AND provider_id = ?");
    $stmt->bind_param("sii", $new_status, $service_id, $provider_id);
    $stmt->execute();
    echo json_encode(['ok' => true]); exit;
}

$svc_page  = max(1, (int)($_GET['spage'] ?? 1));
$per_page  = 6;
$offset    = ($svc_page - 1) * $per_page;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM provider_services WHERE provider_id = ?");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$total_services = $stmt->get_result()->fetch_assoc()['total'];
$total_pages    = ceil($total_services / $per_page);

$stmt = $conn->prepare("SELECT ps.*, sc.name as category_name,
    (SELECT COUNT(*) FROM orders WHERE service_id = ps.id AND status = 'completed') as completed_orders,
    (SELECT COUNT(*) FROM orders WHERE service_id = ps.id AND status IN ('accepted','in_progress')) as active_orders
    FROM provider_services ps
    LEFT JOIN service_categories sc ON ps.category_id = sc.id
    WHERE ps.provider_id = ?
    ORDER BY ps.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $provider_id, $per_page, $offset);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-briefcase me-2" style="color:var(--accent-color)"></i>My Services</span>
        <a href="#" class="btn btn-sm btn-success nav-link-ajax" data-page="create-service">
            <i class="fas fa-plus me-1"></i>Create New
        </a>
    </div>
    <div class="card-body">

        <?php if (empty($services)): ?>
            <div class="text-center py-5">
                <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No services yet</h6>
                <p class="text-muted small">Create your first service to start receiving orders.</p>
                <a href="#" class="btn btn-primary btn-sm nav-link-ajax" data-page="create-service">
                    <i class="fas fa-plus me-1"></i>Create Service
                </a>
            </div>
        <?php else: ?>

            <div class="row g-3">
                <?php foreach ($services as $service): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 service-item" data-id="<?php echo $service['id']; ?>">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <span class="badge bg-<?php echo $service['status'] === 'active' ? 'success' : 'secondary'; ?> status-badge">
                                <?php echo ucfirst($service['status']); ?>
                            </span>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary border-0 py-0" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item toggle-status-btn"
                                            data-id="<?php echo $service['id']; ?>"
                                            data-current="<?php echo $service['status']; ?>">
                                            <i class="fas fa-toggle-<?php echo $service['status'] === 'active' ? 'off' : 'on'; ?> me-2"></i>
                                            <?php echo $service['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($service['title']); ?></h6>
                            <?php if ($service['category_name']): ?>
                                <small class="text-muted d-block mb-2">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($service['category_name']); ?>
                                </small>
                            <?php endif; ?>
                            <p class="card-text text-muted small mb-2">
                                <?php echo htmlspecialchars(mb_strimwidth($service['description'] ?? '', 0, 90, '...')); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-success fw-bold">$<?php echo number_format($service['base_price'], 2); ?></span>
                                <small class="text-muted"><?php echo ucfirst($service['pricing_model']); ?></small>
                            </div>
                            <div class="d-flex gap-3 text-muted" style="font-size:.78rem">
                                <span><i class="fas fa-check-circle me-1 text-success"></i><?php echo $service['completed_orders']; ?> done</span>
                                <span><i class="fas fa-spinner me-1 text-warning"></i><?php echo $service['active_orders']; ?> active</span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent py-1">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($service['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="mt-3"><ul class="pagination justify-content-center">
                <?php if ($svc_page > 1): ?>
                    <li class="page-item"><a class="page-link" href="#" onclick="svcGoPage(<?php echo $svc_page-1; ?>);return false;">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $svc_page ? 'active' : ''; ?>">
                        <a class="page-link" href="#" onclick="svcGoPage(<?php echo $i; ?>);return false;"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($svc_page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="#" onclick="svcGoPage(<?php echo $svc_page+1; ?>);return false;">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <div class="text-center text-muted small">
                Showing <?php echo min($offset + 1, $total_services); ?>–<?php echo min($offset + $per_page, $total_services); ?> of <?php echo $total_services; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
window.svcGoPage = function(p) {
    const lang = '<?php echo $lang ?? "en"; ?>';
    const params = 'page=my-services&lang=' + lang + '&spage=' + p;
    history.pushState({page:'my-services',extra:{}}, '', 'index.php?' + params);
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
    fetch('index.php?' + params, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.text()).then(html=>{
            mc.innerHTML = html;
            mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
            if(typeof bindAjaxLinks==='function') bindAjaxLinks();
        });
};

document.querySelectorAll('.toggle-status-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id      = btn.dataset.id;
        const current = btn.dataset.current;
        const next    = current === 'active' ? 'inactive' : 'active';
        const data    = new FormData();
        data.append('toggle_status', '1');
        data.append('service_id', id);
        data.append('new_status', next);
        fetch('index.php?page=my-services&lang=<?php echo $_GET["lang"] ?? "en"; ?>', {
            method: 'POST', body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(() => {
            if (typeof loadPage === 'function') loadPage('my-services', false);
        });
    });
});
</script>
