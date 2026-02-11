<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get wallet balance and transactions
$stmt = $conn->prepare("SELECT 
    COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount END), 0) as total_spent,
    COUNT(CASE WHEN p.payment_status = 'completed' THEN 1 END) as total_transactions,
    COUNT(CASE WHEN p.payment_status = 'pending' THEN 1 END) as pending_transactions
    FROM orders o 
    LEFT JOIN payments p ON o.id = p.order_id 
    WHERE o.customer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wallet_stats = $stmt->get_result()->fetch_assoc();

// Get recent transactions
$stmt = $conn->prepare("SELECT p.*, o.order_number, o.service_title 
                       FROM payments p 
                       JOIN orders o ON p.order_id = o.id 
                       WHERE o.customer_id = ? 
                       ORDER BY p.created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wallet - ExpertHub</title>
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
                <a class="nav-link active" href="wallet.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Wallet</a>
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
                        <h3><i class="fas fa-wallet me-2"></i>My Wallet</h3>
                        <p class="mb-0">Manage your payments and transaction history</p>
                    </div>
                    <div class="p-4">
                        <!-- Wallet Summary -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                                        <h4 class="text-primary">$<?php echo number_format($wallet_stats['total_spent'], 2); ?></h4>
                                        <p class="text-muted mb-0">Total Spent</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <h4 class="text-success"><?php echo $wallet_stats['total_transactions']; ?></h4>
                                        <p class="text-muted mb-0">Completed Payments</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                        <h4 class="text-warning"><?php echo $wallet_stats['pending_transactions']; ?></h4>
                                        <p class="text-muted mb-0">Pending Payments</p>
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
                                    <div class="col-md-6">
                                        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addFundsModal">
                                            <i class="fas fa-plus me-2"></i>Add Funds
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-success w-100">
                                            <i class="fas fa-shopping-cart me-2"></i>Browse Services
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction History -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-history me-2"></i>Transaction History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($transactions)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                        <h6>No Transactions Yet</h6>
                                        <p class="text-muted">Your payment history will appear here once you start ordering services.</p>
                                        <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Browse Services
                                        </a>
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
                                                    <th>Method</th>
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
                                                            <span class="text-primary fw-bold">$<?php echo number_format($transaction['amount'], 2); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $transaction['payment_status'] === 'completed' ? 'success' : 
                                                                    ($transaction['payment_status'] === 'pending' ? 'warning' : 'danger'); 
                                                            ?>">
                                                                <?php echo ucfirst($transaction['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php 
                                                                    $method = $transaction['payment_method'] ?? 'N/A';
                                                                    if ($method && $method !== 'N/A') {
                                                                        echo strtoupper(str_replace('_', ' ', $method));
                                                                    } else {
                                                                        echo '<span class="text-muted">MOBILE MONEY</span>';
                                                                    }
                                                                ?>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="fas fa-credit-card me-2"></i>Payment Methods</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card border">
                                            <div class="card-body text-center">
                                                <i class="fas fa-mobile-alt fa-2x text-warning mb-2"></i>
                                                <h6>Mobile Money</h6>
                                                <small class="text-muted">MTN MoMo, Airtel Money</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border">
                                            <div class="card-body text-center">
                                                <i class="fas fa-credit-card fa-2x text-info mb-2"></i>
                                                <h6>Credit/Debit Card</h6>
                                                <small class="text-muted">Coming Soon</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    
    <!-- Add Funds Modal -->
    <div class="modal fade" id="addFundsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Funds</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i class="fas fa-construction fa-3x text-warning mb-3"></i>
                        <h5>Feature Coming Soon!</h5>
                        <p class="text-muted">We're working on adding wallet funding functionality. For now, payments are processed directly when you place orders.</p>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Current Payment Process:</strong><br>
                            When you place an order, you'll be prompted to pay using Mobile Money (MTN MoMo, Airtel Money) or other available payment methods.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                        <i class="fas fa-shopping-cart me-1"></i>Browse Services
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>