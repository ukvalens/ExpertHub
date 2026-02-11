<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}b

$device_id = $_GET['device_id'] ?? null;
if (!$device_id) {
    header("Location: devices.php");
    exit();
}

// Get device details
$stmt = $conn->prepare("SELECT * FROM customer_devices WHERE id = ? AND customer_id = ?");
$stmt->bind_param("ii", $device_id, $_SESSION['user_id']);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();

if (!$device) {
    header("Location: devices.php");
    exit();
}

// Get service history for this device
$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, u.first_name, u.last_name 
                       FROM orders o 
                       JOIN provider_services ps ON o.service_id = ps.id 
                       JOIN service_providers sp ON o.provider_id = sp.id 
                       JOIN users u ON sp.user_id = u.id 
                       WHERE o.device_id = ? AND o.customer_id = ? 
                       ORDER BY o.created_at DESC");
$stmt->bind_param("ii", $device_id, $_SESSION['user_id']);
$stmt->execute();
$service_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Service History - ExpertHub</title>
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
                <a class="nav-link" href="devices.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Devices</a>
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
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-history me-2"></i>Service History</h3>
                        <p class="mb-0"><?php echo htmlspecialchars($device['brand'] . ' ' . $device['model']); ?></p>
                    </div>
                    <div class="p-4">
                        <!-- Device Info -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($device['brand'] . ' ' . $device['model']); ?></h5>
                                        <p class="text-muted mb-0">
                                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $device['device_type'])); ?>
                                            <?php if ($device['serial_number']): ?>
                                                | <strong>Serial:</strong> <?php echo htmlspecialchars($device['serial_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <a href="request-service.php?device_id=<?php echo $device['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-success">
                                            <i class="fas fa-wrench me-1"></i>Request Service
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Service History -->
                        <?php if (empty($service_history)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                <h5>No Service History</h5>
                                <p class="text-muted">This device hasn't been serviced yet through ExpertHub.</p>
                                <a href="request-service.php?device_id=<?php echo $device['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                                    <i class="fas fa-wrench me-2"></i>Request First Service
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($service_history as $service): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-<?php 
                                            echo $service['status'] === 'completed' ? 'success' : 
                                                ($service['status'] === 'in_progress' ? 'warning' : 'primary'); 
                                        ?>"></div>
                                        <div class="timeline-content">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="card-title"><?php echo htmlspecialchars($service['service_title']); ?></h6>
                                                            <p class="text-muted mb-2">
                                                                <i class="fas fa-user-tie me-1"></i>
                                                                Provider: <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                                                            </p>
                                                            <small class="text-muted">
                                                                <?php echo date('M j, Y g:i A', strtotime($service['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-<?php 
                                                                echo $service['status'] === 'completed' ? 'success' : 
                                                                    ($service['status'] === 'in_progress' ? 'warning' : 'primary'); 
                                                            ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $service['status'])); ?>
                                                            </span>
                                                            <div class="mt-2">
                                                                <span class="text-success fw-bold">
                                                                    $<?php echo number_format($service['final_price'] ?? $service['quoted_price'] ?? 0, 2); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2">
                                                        <a href="order-details.php?order_id=<?php echo $service['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="devices.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Devices
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        .timeline-marker {
            position: absolute;
            left: -35px;
            top: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 22px;
            width: 2px;
            height: calc(100% + 10px);
            background-color: #dee2e6;
        }
    </style>
</body>
</html>