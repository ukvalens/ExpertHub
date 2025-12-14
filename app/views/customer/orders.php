<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 3;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_clause = "WHERE o.customer_id = ?";
$params = [$user_id];
$param_types = "i";

if ($status_filter !== 'all') {
    $where_clause .= " AND o.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders o 
                             JOIN provider_services ps ON o.service_id = ps.id 
                             JOIN service_providers sp ON o.provider_id = sp.id 
                             JOIN users u ON sp.user_id = u.id 
                             $where_clause");
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);

// Get customer orders with pagination
$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, u.first_name, u.last_name 
                       FROM orders o 
                       JOIN provider_services ps ON o.service_id = ps.id 
                       JOIN service_providers sp ON o.provider_id = sp.id 
                       JOIN users u ON sp.user_id = u.id 
                       $where_clause 
                       ORDER BY o.created_at DESC 
                       LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ExpertHub</title>
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
                <a class="nav-link active" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Orders</a>
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
                        <h3><i class="fas fa-list-alt me-2"></i>My Orders</h3>
                        <p class="mb-0">Track and manage your service orders</p>
                    </div>
                    <div class="p-4">
                        <!-- Filter Buttons -->
                        <div class="btn-group mb-4" role="group">
                            <a href="orders.php?status=all&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                            <a href="orders.php?status=requested&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'requested' ? 'btn-primary' : 'btn-outline-primary'; ?>">Active</a>
                            <a href="orders.php?status=completed&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'completed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Completed</a>
                        </div>

                        <!-- Orders List -->
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                <h5>No Orders Found</h5>
                                <p class="text-muted">You haven't placed any orders yet.</p>
                                <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Browse Services
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($orders as $order): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between">
                                                <small class="text-muted">#<?php echo $order['order_number']; ?></small>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] === 'completed' ? 'success' : 
                                                        ($order['status'] === 'in_progress' ? 'warning' : 'primary'); 
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($order['service_title']); ?></h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-tie me-1"></i>
                                                        Provider: <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                                    </small>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-success fw-bold">
                                                        $<?php echo number_format($order['final_price'] ?? $order['quoted_price'] ?? 0, 2); ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <a href="order-details.php?order_id=<?php echo $order['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                                <?php if ($order['status'] === 'completed'): ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="alert('Leave review - Coming Soon')">
                                                        <i class="fas fa-star me-1"></i>Review
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Orders pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="orders.php?status=<?php echo $status_filter; ?>&page=<?php echo $page-1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="orders.php?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="orders.php?status=<?php echo $status_filter; ?>&page=<?php echo $page+1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                
                                <div class="text-center text-muted">
                                    Showing <?php echo min($offset + 1, $total_orders); ?>-<?php echo min($offset + $per_page, $total_orders); ?> of <?php echo $total_orders; ?> orders
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>