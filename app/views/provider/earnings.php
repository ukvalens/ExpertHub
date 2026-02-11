<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get provider ID
$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

if (!$provider) {
    header("Location: ../../../login.php");
    exit();
}

$provider_id = $provider['id'];

// Handle add earnings submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_earnings') {
    $order_id = $_POST['order_id'];
    $additional_amount = $_POST['additional_amount'];
    $description = $_POST['description'];
    
    // Insert additional earnings record
    $stmt = $conn->prepare("INSERT INTO additional_earnings (order_id, provider_id, amount, description, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iids", $order_id, $provider_id, $additional_amount, $description);
    
    if ($stmt->execute()) {
        // Update order final_price
        $stmt = $conn->prepare("UPDATE orders SET final_price = COALESCE(final_price, quoted_price) + ? WHERE id = ?");
        $stmt->bind_param("di", $additional_amount, $order_id);
        $stmt->execute();
        
        $success_message = "Earnings adjusted successfully!";
    } else {
        $error_message = "Failed to adjust earnings. Please try again.";
    }
}

// Get earnings and transaction stats
$stmt = $conn->prepare("SELECT 
    COALESCE(SUM(CASE WHEN o.status IN ('completed', 'in_progress', 'accepted') THEN o.final_price END), 0) as total_earnings,
    COUNT(CASE WHEN p.payment_status = 'completed' OR o.status IN ('completed', 'in_progress', 'accepted') THEN 1 END) as completed_transactions,
    COUNT(CASE WHEN p.payment_status = 'pending' OR (o.status = 'pending' AND p.id IS NULL) THEN 1 END) as pending_transactions
    FROM orders o 
    LEFT JOIN payments p ON o.id = p.order_id 
    WHERE o.provider_id = ?");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$wallet_stats = $stmt->get_result()->fetch_assoc();

// Get recent transactions
$stmt = $conn->prepare("SELECT o.*, o.service_title, u.first_name, u.last_name, p.payment_method, p.payment_status, p.created_at as payment_date
                       FROM orders o 
                       JOIN users u ON o.customer_id = u.id
                       LEFT JOIN payments p ON o.id = p.order_id 
                       WHERE o.provider_id = ? AND o.status IN ('completed', 'in_progress', 'accepted')
                       ORDER BY COALESCE(p.created_at, o.created_at) DESC LIMIT 10");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get active orders for price adjustments
$stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.quoted_price, o.final_price 
                       FROM orders o 
                       WHERE o.provider_id = ? AND o.status IN ('accepted', 'in_progress')
                       ORDER BY o.created_at DESC");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$active_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings - ExpertHub Provider</title>
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
                <a class="nav-link" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Dashboard</a>
                <a class="nav-link" href="my-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Orders</a>
                <a class="nav-link" href="messages.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Messages</a>
                <a class="nav-link active" href="earnings.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Earnings</a>
                <a class="nav-link" href="support.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Support</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-tie me-1"></i>Provider
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="profile.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">
                        <i class="fas fa-user me-2"></i>Profile</a></li>
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
                        <h3><i class="fas fa-chart-line me-2"></i>Earnings Dashboard</h3>
                        <p class="mb-0">Track your income and payment history</p>
                    </div>
                    <div class="p-4">
                        <!-- Earnings Summary -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                                        <h4 class="text-success">$<?php echo number_format($wallet_stats['total_earnings'], 2); ?></h4>
                                        <p class="text-muted mb-0">Total Earnings</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                                        <h4 class="text-primary"><?php echo $wallet_stats['completed_transactions']; ?></h4>
                                        <p class="text-muted mb-0">Completed Jobs</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                        <h4 class="text-info"><?php echo $wallet_stats['pending_transactions']; ?></h4>
                                        <p class="text-muted mb-0">Pending Payments</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-calculator fa-2x text-warning mb-2"></i>
                                        <h4 class="text-warning">$<?php echo number_format($wallet_stats['total_earnings'], 2); ?></h4>
                                        <p class="text-muted mb-0">Total</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                            <i class="fas fa-money-bill-wave me-2"></i>Request Withdrawal
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="my-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary w-100">
                                            <i class="fas fa-plus me-2"></i>Add New Service
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-info w-100">
                                            <i class="fas fa-clipboard-list me-2"></i>View Orders
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Adjustments -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-calculator me-2"></i>Add Earnings</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Record additional money earned through discussions or extra work</p>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addEarningsModal">
                                            <i class="fas fa-plus-circle me-2"></i>Add Earnings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction History -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-history me-2"></i>Payment History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($transactions)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                        <h6>No Transactions Yet</h6>
                                        <p class="text-muted">Your payment history will appear here once you complete services.</p>
                                        <a href="my-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Create Your First Service
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Order</th>
                                                    <th>Customer</th>
                                                    <th>Service</th>
                                                    <th>Payment Method</th>
                                                    <th>Total Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transactions as $transaction): ?>
                                                    <tr>
                                                        <td><?php echo date('M j, Y', strtotime($transaction['payment_date'] ?? $transaction['created_at'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-light text-dark">#<?php echo $transaction['order_number']; ?></span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($transaction['service_title']); ?></td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php 
                                                                    $method = $transaction['payment_method'] ?? 'mtn_momo';
                                                                    if ($method === 'mtn_momo') {
                                                                        echo 'MTN MoMo';
                                                                    } elseif ($method === 'airtel_money') {
                                                                        echo 'Airtel Money';
                                                                    } else {
                                                                        echo ucfirst(str_replace('_', ' ', $method));
                                                                    }
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="text-success fw-bold">
                                                                $<?php echo number_format($transaction['final_price'] ?? $transaction['quoted_price'], 2); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo ($transaction['payment_status'] ?? 'completed') === 'completed' ? 'success' : 
                                                                    (($transaction['payment_status'] ?? 'completed') === 'pending' ? 'warning' : 'danger'); 
                                                            ?>">
                                                                <?php echo ucfirst($transaction['payment_status'] ?? 'Completed'); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Withdrawal Modal -->
    <div class="modal fade" id="withdrawModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Request Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i class="fas fa-construction fa-3x text-warning mb-3"></i>
                        <h5>Feature Coming Soon!</h5>
                        <p class="text-muted">We're working on adding withdrawal functionality. Currently, payments are processed automatically upon order completion.</p>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Current Process:</strong><br>
                            Earnings are automatically transferred to your registered Mobile Money account within 24-48 hours after order completion.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="support.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                        <i class="fas fa-headset me-1"></i>Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Earnings Modal -->
    <div class="modal fade" id="addEarningsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Earnings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addEarningsForm">
                        <input type="hidden" name="action" value="add_earnings">
                        <div class="mb-3">
                            <label class="form-label">Select Order</label>
                            <select class="form-select" name="order_id" required>
                                <option value="">Choose an order...</option>
                                <?php foreach ($active_orders as $order): ?>
                                    <option value="<?php echo $order['id']; ?>">
                                        #<?php echo $order['order_number']; ?> - <?php echo htmlspecialchars($order['service_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (empty($active_orders)): ?>
                                    <option value="" disabled>No orders available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount ($)</label>
                            <input type="number" class="form-control" name="additional_amount" step="0.01" required placeholder="Enter amount (positive to add, negative to remove)">
                            <small class="text-muted">Use positive numbers to add earnings, negative numbers to remove earnings</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required placeholder="Describe the reason for this adjustment (e.g., extra work done, refund given, etc.)"></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will adjust your total earnings for this order. Use positive amounts to add earnings, negative amounts to remove earnings.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addEarningsForm" class="btn btn-success">Adjust Earnings</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>