<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=devices&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

// Add device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_device'])) {
    $device_type   = $_POST['device_type'];
    $brand         = trim($_POST['brand']);
    $model         = trim($_POST['model']);
    $serial_number = trim($_POST['serial_number']);
    $purchase_date = $_POST['purchase_date'] ?: null;
    $notes         = trim($_POST['notes']);
    $stmt = $conn->prepare("INSERT INTO customer_devices (customer_id, device_type, brand, model, serial_number, purchase_date, notes, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $stmt->bind_param("issssss", $user_id, $device_type, $brand, $model, $serial_number, $purchase_date, $notes);
    $stmt->execute();
}

// Delete device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_device'])) {
    $did = (int)$_POST['device_id'];
    $stmt = $conn->prepare("DELETE FROM customer_devices WHERE id=? AND customer_id=?");
    $stmt->bind_param("ii", $did, $user_id);
    $stmt->execute();
}

$stmt = $conn->prepare("SELECT * FROM customer_devices WHERE customer_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$icon_map = [
    'laptop'         => 'laptop',
    'desktop'        => 'desktop',
    'printer'        => 'print',
    'mobile'         => 'mobile-alt',
    'server'         => 'server',
    'network_device' => 'network-wired',
    'other'          => 'microchip',
];
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-laptop me-2" style="color:var(--accent-color)"></i>My Devices</span>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
            <i class="fas fa-plus me-1"></i>Add Device
        </button>
    </div>
    <div class="card-body">

        <?php if (empty($devices)): ?>
            <div class="text-center py-5">
                <i class="fas fa-laptop fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No devices added yet</h6>
                <p class="text-muted small">Add your devices to track service history and get better support.</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                    <i class="fas fa-plus me-1"></i>Add Your First Device
                </button>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($devices as $d):
                    $ico = $icon_map[$d['device_type']] ?? 'microchip';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <i class="fas fa-<?php echo $ico; ?> text-primary"></i>
                            <span class="badge bg-<?php echo $d['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($d['status']); ?>
                            </span>
                        </div>
                        <div class="card-body py-2">
                            <h6 class="mb-1"><?php echo htmlspecialchars($d['brand'].' '.$d['model']); ?></h6>
                            <small class="text-muted">
                                <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $d['device_type'])); ?><br>
                                <?php if ($d['serial_number']): ?>
                                    <strong>Serial:</strong> <?php echo htmlspecialchars($d['serial_number']); ?><br>
                                <?php endif; ?>
                                <?php if ($d['purchase_date']): ?>
                                    <strong>Purchased:</strong> <?php echo date('M j, Y', strtotime($d['purchase_date'])); ?><br>
                                <?php endif; ?>
                                <strong>Added:</strong> <?php echo date('M j, Y', strtotime($d['created_at'])); ?>
                            </small>
                            <?php if ($d['notes']): ?>
                                <p class="text-muted small mt-1 mb-0"><?php echo htmlspecialchars($d['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2 py-2">
                            <button class="btn btn-sm btn-outline-primary nav-link-ajax"
                                    data-page="device-history"
                                    onclick="loadDeviceHistory(<?php echo $d['id']; ?>)">
                                <i class="fas fa-history me-1"></i>History
                            </button>
                            <button class="btn btn-sm btn-outline-success nav-link-ajax"
                                    data-page="request-service"
                                    onclick="loadPage('request-service', true, {device_id: <?php echo $d['id']; ?>})">
                                <i class="fas fa-wrench me-1"></i>Request
                            </button>
                            <form method="POST" class="ms-auto"
                                  onsubmit="return confirm('Remove this device?')">
                                <input type="hidden" name="device_id" value="<?php echo $d['id']; ?>">
                                <button type="submit" name="delete_device"
                                        class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Device Type</label>
                            <select class="form-select form-select-sm" name="device_type" required>
                                <option value="">Select type</option>
                                <option value="laptop">Laptop</option>
                                <option value="desktop">Desktop</option>
                                <option value="printer">Printer</option>
                                <option value="server">Server</option>
                                <option value="network_device">Network Device</option>
                                <option value="mobile">Mobile Device</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control form-control-sm" name="brand" required placeholder="e.g., Dell, HP, Apple">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control form-control-sm" name="model" required placeholder="e.g., Inspiron 15">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Serial Number <small class="text-muted">(optional)</small></label>
                            <input type="text" class="form-control form-control-sm" name="serial_number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purchase Date <small class="text-muted">(optional)</small></label>
                            <input type="date" class="form-control form-control-sm" name="purchase_date">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes <small class="text-muted">(optional)</small></label>
                            <textarea class="form-control form-control-sm" name="notes" rows="2"
                                      placeholder="Any additional information..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_device" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i>Add Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadDeviceHistory(deviceId) {
    const lang = '<?php echo $lang; ?>';
    const params = 'page=device-history&lang=' + lang + '&device_id=' + deviceId;
    history.pushState({page:'device-history',extra:{device_id:deviceId}}, '', 'index.php?' + params);
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
    fetch('index.php?' + params, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.text()).then(html => {
            mc.innerHTML = html;
            mc.querySelectorAll('script').forEach(s => {
                const ns = document.createElement('script'); ns.textContent = s.textContent; document.body.appendChild(ns);
            });
            if (typeof bindAjaxLinks === 'function') bindAjaxLinks();
        });
}
</script>
