<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$payment_ref = $_GET['ref'] ?? null;
if (!$payment_ref) {
    header("Location: orders.php");
    exit();
}

// Get payment details
$stmt = $conn->prepare("SELECT p.*, o.order_number, ps.title as service_title 
                       FROM payments p 
                       JOIN orders o ON p.order_id = o.id 
                       JOIN provider_services ps ON o.service_id = ps.id 
                       WHERE p.transaction_id = ?");
$stmt->bind_param("s", $payment_ref);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    header("Location: orders.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - ExpertHub</title>
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
            <div class="col-lg-6">
                <div class="auth-card">
                    <div class="text-center p-4">
                        <div class="success-icon mb-4">
                            <i class="fas fa-check-circle fa-5x text-success"></i>
                        </div>
                        <h3 class="text-success mb-3">Payment Successful!</h3>
                        <p class="text-muted mb-4">Your order has been confirmed and the provider will be notified.</p>
                        
                        <div class="card mb-4">
                            <div class="card-body">
                                <h6 class="card-title">Payment Details</h6>
                                <div class="row text-start">
                                    <div class="col-6"><strong>Reference:</strong></div>
                                    <div class="col-6"><?php echo $payment['transaction_id']; ?></div>
                                    
                                    <div class="col-6"><strong>Order #:</strong></div>
                                    <div class="col-6"><?php echo $payment['order_number']; ?></div>
                                    
                                    <div class="col-6"><strong>Service:</strong></div>
                                    <div class="col-6"><?php echo htmlspecialchars($payment['service_title']); ?></div>
                                    
                                    <div class="col-6"><strong>Amount:</strong></div>
                                    <div class="col-6 text-success fw-bold">$<?php echo number_format($payment['amount'], 2); ?></div>
                                    
                                    <div class="col-6"><strong>Payment Method:</strong></div>
                                    <div class="col-6"><?php echo strtoupper(str_replace('_', ' ', $payment['payment_method'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            The service provider will contact you soon to schedule the service.
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary me-md-2">
                                <i class="fas fa-list-alt me-1"></i>View My Orders
                            </a>
                            <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-search me-1"></i>Browse More Services
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>