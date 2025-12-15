<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php");
    exit();
}

// Get provider ID
$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

if (!$provider) {
    header("Location: ../../../login.php");
    exit();
}

$provider_id = $provider['id'];

// Get earnings data
$stmt = $conn->prepare("SELECT 
    COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.provider_amount END), 0) as total_earnings,
    COALESCE(SUM(CASE WHEN p.payment_status = 'completed' AND MONTH(p.created_at) = MONTH(CURRENT_DATE()) THEN p.provider_amount END), 0) as monthly_earnings,
    COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN p.payment_status = 'pending' THEN 1 END) as pending_payments
    FROM orders o 
    LEFT JOIN payments p ON o.id = p.order_id 
    WHERE o.provider_id = ?");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$earnings = $stmt->get_result()->fetch_assoc();

// Get recent transactions
$stmt = $conn->prepare("SELECT p.*, o.order_number, o.service_title 
                       FROM payments p 
                       JOIN orders o ON p.order_id = o.id 
                       WHERE o.provider_id = ? AND p.payment_status = 'completed'
                       ORDER BY p.created_at DESC LIMIT 10");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                <a class="nav-link active" href="earnings.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Earnings</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-tie me-1"></i>Provider
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
                        <h3><i class="fas fa-dollar-sign me-2"></i>Earnings Dashboard</h3>
                        <p class="mb-0">Track your income and payment history</p>
                    </div>
                    <div class="p-4">
                        <!-- Earnings Summary -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-wallet fa-2x text-success mb-2"></i>
                                        <h4 class="text-success">$<?php echo number_format($earnings['total_earnings'], 2); ?></h4>
                                        <p class="text-muted mb-0">Total Earnings</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-calendar-month fa-2x text-primary mb-2"></i>
                                        <h4 class="text-primary">$<?php echo number_format($earnings['monthly_earnings'], 2); ?></h4>
                                        <p class="text-muted mb-0">This Month</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-check-circle fa-2x text-info mb-2"></i>
                                        <h4 class="text-info"><?php echo $earnings['completed_orders']; ?></h4>
                                        <p class="text-muted mb-0">Completed Orders</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                        <h4 class="text-warning"><?php echo $earnings['pending_payments']; ?></h4>
                                        <p class="text-muted mb-0">Pending Payments</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Transactions -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($transactions)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                        <h6>No Transactions Yet</h6>
                                        <p class="text-muted">Complete orders to start earning money.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Order</th>
                                                    <th>Service</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transactions as $transaction): ?>
                                                    <tr>
                                                        <td><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-light text-dark">#<?php echo $transaction['order_number']; ?></span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($transaction['service_title']); ?></td>
                                                        <td>
                                                            <span class="text-success fw-bold">$<?php echo number_format($transaction['provider_amount'], 2); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success">Paid</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Withdrawal Section -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="fas fa-money-bill-wave me-2"></i>Withdrawal</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Available Balance:</strong> $<?php echo number_format($earnings['total_earnings'], 2); ?>
                                </div>
                                <button class="btn btn-success" onclick="alert('Withdrawal feature coming soon!')">
                                    <i class="fas fa-download me-2"></i>Request Withdrawal
                                </button>
                                <small class="text-muted d-block mt-2">
                                    Minimum withdrawal amount: $50. Processing time: 3-5 business days.
                                </small>
                            </div>
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