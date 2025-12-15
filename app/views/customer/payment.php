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
$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, u.first_name, u.last_name 
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

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $momo_number = $_POST['momo_number'];
    $payment_method = $_POST['payment_method'];
    
    // Generate payment reference
    $payment_ref = 'PAY' . date('Ymd') . rand(10000, 99999);
    
    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount, provider_amount, transaction_id, payment_status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $provider_amount = $order['quoted_price'] * 0.9; // 90% to provider, 10% commission
    $stmt->bind_param("isdds", $order_id, $payment_method, $order['quoted_price'], $provider_amount, $payment_ref);
    
    if ($stmt->execute()) {
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = 'payment_pending' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        
        // Simulate MoMo payment processing
        sleep(2);
        
        // Update payment status to completed (in real implementation, this would be done by MoMo callback)
        $stmt = $conn->prepare("UPDATE payments SET payment_status = 'completed', processed_at = NOW() WHERE transaction_id = ?");
        $stmt->bind_param("s", $payment_ref);
        $stmt->execute();
        
        // Update order status to requested (ready for provider acceptance)
        $stmt = $conn->prepare("UPDATE orders SET status = 'requested' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        
        header("Location: payment-success.php?ref=$payment_ref&lang=" . ($_GET['lang'] ?? 'en'));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - ExpertHub</title>
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
                    <div class="auth-header">
                        <h3><i class="fas fa-credit-card me-2"></i>Payment</h3>
                        <p class="mb-0">Complete your payment via Mobile Money</p>
                    </div>
                    <div class="p-4">
                        <!-- Order Summary -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h6 class="card-title">Order Details</h6>
                                <p class="mb-1"><strong>Order #:</strong> <?php echo $order['order_number']; ?></p>
                                <p class="mb-1"><strong>Service:</strong> <?php echo htmlspecialchars($order['service_title']); ?></p>
                                <p class="mb-1"><strong>Provider:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold">Total Amount:</span>
                                    <span class="fw-bold text-success h5">$<?php echo number_format($order['quoted_price'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Form -->
                        <form method="POST" id="paymentForm">
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="card payment-method active" data-method="mtn_momo">
                                            <div class="card-body text-center">
                                                <i class="fas fa-mobile-alt fa-2x text-warning mb-2"></i>
                                                <h6>MTN MoMo</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card payment-method" data-method="airtel_money">
                                            <div class="card-body text-center">
                                                <i class="fas fa-mobile-alt fa-2x text-danger mb-2"></i>
                                                <h6>Airtel Money</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="payment_method" id="payment_method" value="mtn_momo">
                            </div>

                            <div class="mb-3">
                                <label for="momo_number" class="form-label">Mobile Money Number</label>
                                <input type="tel" class="form-control" id="momo_number" name="momo_number" required placeholder="Enter your MoMo number">
                                <small class="text-muted">Enter the number you want to pay from</small>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                You will receive a payment prompt on your phone. Please approve the transaction to complete your order.
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="order.php?service_id=<?php echo $order['service_id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-1"></i>Back
                                </a>
                                <button type="submit" class="btn btn-success" id="payBtn">
                                    <i class="fas fa-mobile-alt me-1"></i>Pay Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('payment_method').value = this.dataset.method;
            });
        });

        // Payment form submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const payBtn = document.getElementById('payBtn');
            payBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            payBtn.disabled = true;
        });
    </script>
    <style>
        .payment-method {
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .payment-method.active {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
    </style>
</body>
</html>