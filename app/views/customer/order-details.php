<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, ps.description as service_description, 
                       u.first_name, u.last_name, u.email, u.phone 
                       FROM orders o 
                       JOIN provider_services ps ON o.service_id = ps.id 
                       JOIN service_providers sp ON o.provider_id = sp.id 
                       JOIN users u ON sp.user_id = u.id 
                       WHERE o.id = ? AND o.customer_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

$requirements = json_decode($order['customer_requirements'], true);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - ExpertHub</title>
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
            <div class="col-lg-8">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-file-alt me-2"></i>Order Details</h3>
                        <p class="mb-0">Order #<?php echo $order['order_number']; ?></p>
                    </div>
                    <div class="p-4">
                        <!-- Order Status -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Order Status</h5>
                                    <span class="badge bg-<?php 
                                        echo $order['status'] === 'completed' ? 'success' : 
                                            ($order['status'] === 'in_progress' ? 'warning' : 'primary'); 
                                    ?> fs-6">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Service Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-briefcase me-2"></i>Service Information</h6>
                            </div>
                            <div class="card-body">
                                <h5 class="text-primary"><?php echo htmlspecialchars($order['service_title']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($order['service_description']); ?></p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Provider:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Price:</strong> <span class="text-success">$<?php echo number_format($order['final_price'] ?? $order['quoted_price'] ?? 0, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Your Requirements -->
                        <?php if ($requirements): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-clipboard-list me-2"></i>Your Requirements</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Phone:</strong><br>
                                        <?php echo htmlspecialchars($requirements['phone'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Address:</strong><br>
                                        <?php echo htmlspecialchars($requirements['address'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="col-12">
                                        <strong>Description:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($requirements['description'] ?? 'N/A')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Order Timeline -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-clock me-2"></i>Order Timeline</h6>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <i class="fas fa-plus-circle text-primary"></i>
                                        <div>
                                            <strong>Order Placed</strong><br>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <?php if ($order['started_at']): ?>
                                    <div class="timeline-item">
                                        <i class="fas fa-play-circle text-warning"></i>
                                        <div>
                                            <strong>Work Started</strong><br>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['started_at'])); ?></small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($order['completed_at']): ?>
                                    <div class="timeline-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <div>
                                            <strong>Order Completed</strong><br>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['completed_at'])); ?></small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Provider Contact -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-user-tie me-2"></i>Provider Contact</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <a href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Orders
                            </a>
                            <?php if ($order['status'] === 'completed'): ?>
                                <button class="btn btn-warning" onclick="alert('Review feature coming soon!')">
                                    <i class="fas fa-star me-1"></i>Leave Review
                                </button>
                            <?php endif; ?>
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
            padding-bottom: 20px;
            display: flex;
            align-items-start;
        }
        .timeline-item i {
            position: absolute;
            left: -30px;
            top: 2px;
            font-size: 1.2em;
        }
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 20px;
            width: 2px;
            height: calc(100% - 10px);
            background-color: #dee2e6;
        }
    </style>
</body>
</html>