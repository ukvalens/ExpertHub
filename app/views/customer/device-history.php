<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    $did  = isset($_GET['device_id']) ? '&device_id='.(int)$_GET['device_id'] : '';
    header("Location: ../dashboard/index.php?page=device-history&lang=$lang$did"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id   = $_SESSION['user_id'];
$lang      = $_GET['lang'] ?? 'en';
$device_id = (int)($_GET['device_id'] ?? 0);

if (!$device_id) {
    echo '<div class="alert alert-warning">No device selected. <button class="btn btn-sm btn-outline-secondary nav-link-ajax ms-2" data-page="devices" onclick="loadPage(\'devices\')">Back to Devices</button></div>';
    return;
}

$stmt = $conn->prepare("SELECT * FROM customer_devices WHERE id=? AND customer_id=?");
$stmt->bind_param("ii", $device_id, $user_id);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();

if (!$device) {
    echo '<div class="alert alert-danger">Device not found.</div>'; return;
}

$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, u.first_name, u.last_name
    FROM orders o
    JOIN provider_services ps ON o.service_id = ps.id
    JOIN service_providers sp ON o.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE o.device_id=? AND o.customer_id=?
    ORDER BY o.created_at DESC");
$stmt->bind_param("ii", $device_id, $user_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_color = ['completed' => 'success', 'in_progress' => 'warning', 'cancelled' => 'danger'];
?>

<style>
.timeline { position:relative; padding-left:28px; }
.timeline-item { position:relative; margin-bottom:24px; }
.timeline-dot { position:absolute; left:-34px; top:10px; width:12px; height:12px; border-radius:50%; }
.timeline-item:not(:last-child)::before { content:''; position:absolute; left:-29px; top:22px; width:2px; height:calc(100% + 4px); background:#dee2e6; }
</style>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-2" style="color:var(--accent-color)"></i>Service History</span>
        <button class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="devices"
                onclick="loadPage('devices')">
            <i class="fas fa-arrow-left me-1"></i>Back to Devices
        </button>
    </div>
    <div class="card-body">

        <!-- Device summary -->
        <div class="d-flex align-items-center justify-content-between p-3 rounded mb-4" style="background:var(--light-bg)">
            <div>
                <h6 class="mb-0"><?php echo htmlspecialchars($device['brand'].' '.$device['model']); ?></h6>
                <small class="text-muted">
                    <?php echo ucfirst(str_replace('_', ' ', $device['device_type'])); ?>
                    <?php if ($device['serial_number']): ?> · S/N: <?php echo htmlspecialchars($device['serial_number']); ?><?php endif; ?>
                </small>
            </div>
            <button class="btn btn-sm btn-success nav-link-ajax" data-page="request-service"
                    onclick="loadPage('request-service', true, {device_id: <?php echo $device['id']; ?>})">
                <i class="fas fa-wrench me-1"></i>Request Service
            </button>
        </div>

        <?php if (empty($history)): ?>
            <div class="text-center py-5">
                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No service history yet</h6>
                <p class="text-muted small">This device hasn't been serviced through ExpertHub.</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($history as $s):
                    $sc = $status_color[$s['status']] ?? 'primary';
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot bg-<?php echo $sc; ?>"></div>
                    <div class="card shadow-sm">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1" style="font-size:.88rem;"><?php echo htmlspecialchars($s['service_title']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?>
                                        &nbsp;·&nbsp; <?php echo date('M j, Y', strtotime($s['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $sc; ?>"><?php echo ucfirst(str_replace('_', ' ', $s['status'])); ?></span>
                                    <div class="text-success fw-bold mt-1" style="font-size:.85rem;">
                                        $<?php echo number_format($s['final_price'] ?? $s['quoted_price'] ?? 0, 2); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-primary nav-link-ajax"
                                        data-page="order-details"
                                        onclick="loadPage('order-details', true, {order_id: <?php echo $s['id']; ?>})">
                                    <i class="fas fa-eye me-1"></i>View Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
