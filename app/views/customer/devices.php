<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle device addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_device'])) {
    $device_type = $_POST['device_type'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $serial_number = $_POST['serial_number'];
    $purchase_date = $_POST['purchase_date'];
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("INSERT INTO customer_devices (customer_id, device_type, brand, model, serial_number, purchase_date, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issssss", $user_id, $device_type, $brand, $model, $serial_number, $purchase_date, $notes);
    $stmt->execute();
    
    header("Location: devices.php?lang=" . ($_GET['lang'] ?? 'en'));
    exit();
}

// Get customer devices
$stmt = $conn->prepare("SELECT * FROM customer_devices WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Devices - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">
                <i class="fas fa-users-cog me-2"></i>ExpertHub
            </a>
            <div class="navbar-nav mx-auto">
                <a class="nav-link" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Home</a>
                <a class="nav-link" href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Browse Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Orders</a>
                <a class="nav-link active" href="devices.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Devices</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user me-1"></i>Customer
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-danger" href="../../../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="auth-card">
                    <div class="auth-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><i class="fas fa-laptop me-2"></i>My Devices</h3>
                                <p class="mb-0">Manage your devices and track service history</p>
                            </div>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                                <i class="fas fa-plus me-2"></i>Add Device
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <?php if (empty($devices)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-laptop fa-3x text-muted mb-3"></i>
                                <h5>No Devices Added</h5>
                                <p class="text-muted">Add your devices to track their service history and get better support.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                                    <i class="fas fa-plus me-2"></i>Add Your First Device
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($devices as $device): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <div class="device-icon">
                                                    <i class="fas fa-<?php 
                                                        echo $device['device_type'] === 'laptop' ? 'laptop' : 
                                                            ($device['device_type'] === 'desktop' ? 'desktop' : 
                                                            ($device['device_type'] === 'printer' ? 'print' : 
                                                            ($device['device_type'] === 'mobile' ? 'mobile-alt' : 'microchip'))); 
                                                    ?> text-primary"></i>
                                                </div>
                                                <span class="badge bg-<?php echo $device['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($device['status']); ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($device['brand'] . ' ' . $device['model']); ?></h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $device['device_type'])); ?><br>
                                                        <?php if ($device['serial_number']): ?>
                                                            <strong>Serial:</strong> <?php echo htmlspecialchars($device['serial_number']); ?><br>
                                                        <?php endif; ?>
                                                        <strong>Added:</strong> <?php echo date('M j, Y', strtotime($device['created_at'])); ?>
                                                    </small>
                                                </p>
                                                <?php if ($device['notes']): ?>
                                                    <p class="card-text">
                                                        <small><?php echo htmlspecialchars($device['notes']); ?></small>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <a href="device-history.php?device_id=<?php echo $device['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-history me-1"></i>Service History
                                                </a>
                                                <a href="request-service.php?device_id=<?php echo $device['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-wrench me-1"></i>Request Service
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="device_type" class="form-label">Device Type</label>
                                <select class="form-select" id="device_type" name="device_type" required>
                                    <option value="">Select device type</option>
                                    <option value="laptop">Laptop</option>
                                    <option value="desktop">Desktop</option>
                                    <option value="printer">Printer</option>
                                    <option value="server">Server</option>
                                    <option value="network_device">Network Device</option>
                                    <option value="mobile">Mobile Device</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="brand" class="form-label">Brand</label>
                                <input type="text" class="form-control" id="brand" name="brand" required placeholder="e.g., Dell, HP, Apple">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="model" name="model" required placeholder="e.g., Inspiron 15, MacBook Pro">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number" placeholder="Optional">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="purchase_date" class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional information about this device..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_device" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>Add Device
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>