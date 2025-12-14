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
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 3;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_clause = "WHERE o.provider_id = ?";
$params = [$provider_id];
$param_types = "i";

if ($status_filter !== 'all') {
    $where_clause .= " AND o.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Get counts for each status
$count_stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN o.status = 'requested' THEN 1 END) as requested_count,
    COUNT(CASE WHEN o.status = 'in_progress' THEN 1 END) as in_progress_count,
    COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_count
    FROM orders o WHERE o.provider_id = ?");
$count_stmt->bind_param("i", $provider_id);
$count_stmt->execute();
$counts = $count_stmt->get_result()->fetch_assoc();

// Get total count for current filter
$filter_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders o $where_clause");
$filter_count_stmt->bind_param($param_types, ...$params);
$filter_count_stmt->execute();
$total_orders = $filter_count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);

// Get orders with pagination
$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, u.first_name, u.last_name 
                       FROM orders o 
                       JOIN provider_services ps ON o.service_id = ps.id 
                       JOIN users u ON o.customer_id = u.id 
                       $where_clause 
                       ORDER BY o.created_at DESC 
                       LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'accept':
            $stmt = $conn->prepare("UPDATE orders SET status = 'accepted' WHERE id = ? AND provider_id = ?");
            break;
        case 'start':
            $stmt = $conn->prepare("UPDATE orders SET status = 'in_progress', started_at = NOW() WHERE id = ? AND provider_id = ?");
            break;
        case 'complete':
            $stmt = $conn->prepare("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ? AND provider_id = ?");
            break;
    }
    
    if (isset($stmt)) {
        $stmt->bind_param("ii", $order_id, $provider_id);
        $stmt->execute();
        header("Location: orders.php?status=$status_filter&page=$page&lang=" . ($_GET['lang'] ?? 'en'));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ExpertHub Provider</title>
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
                <a class="nav-link" href="services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Services</a>
                <a class="nav-link active" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Orders</a>
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
                        <h3><i class="fas fa-clipboard-list me-2"></i>Order Management</h3>
                        <p class="mb-0">Manage your service orders and customer requests</p>
                    </div>
                    <div class="p-4">
                        <!-- Filter Buttons -->
                        <div class="btn-group mb-4" role="group">
                            <a href="orders.php?status=all&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All (<?php echo $counts['total']; ?>)</a>
                            <a href="orders.php?status=requested&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'requested' ? 'btn-primary' : 'btn-outline-primary'; ?>">New Requests (<?php echo $counts['requested_count']; ?>)</a>
                            <a href="orders.php?status=in_progress&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'in_progress' ? 'btn-primary' : 'btn-outline-primary'; ?>">In Progress (<?php echo $counts['in_progress_count']; ?>)</a>
                            <a href="orders.php?status=completed&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'completed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Completed (<?php echo $counts['completed_count']; ?>)</a>
                        </div>

                        <!-- Orders List -->
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5>No Orders Found</h5>
                                <p class="text-muted">You don't have any orders matching the current filter.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($orders as $order): 
                                    $requirements = json_decode($order['customer_requirements'], true);
                                ?>
                                    <div class="col-12 mb-4">
                                        <div class="card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>#<?php echo $order['order_number']; ?></strong>
                                                    <span class="badge bg-<?php 
                                                        echo $order['status'] === 'completed' ? 'success' : 
                                                            ($order['status'] === 'in_progress' ? 'warning' : 
                                                            ($order['status'] === 'accepted' ? 'info' : 'primary')); 
                                                    ?> ms-2">
                                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h6 class="card-title text-primary"><?php echo htmlspecialchars($order['service_title']); ?></h6>
                                                        <p class="mb-2">
                                                            <i class="fas fa-user me-1"></i>
                                                            <strong>Customer:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                                        </p>
                                                        <?php if ($requirements): ?>
                                                            <p class="mb-2">
                                                                <i class="fas fa-phone me-1"></i>
                                                                <strong>Phone:</strong> <?php echo htmlspecialchars($requirements['phone'] ?? 'N/A'); ?>
                                                            </p>
                                                            <p class="mb-2">
                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                <strong>Address:</strong> <?php echo htmlspecialchars($requirements['address'] ?? 'N/A'); ?>
                                                            </p>
                                                            <p class="mb-2">
                                                                <i class="fas fa-clipboard me-1"></i>
                                                                <strong>Requirements:</strong> <?php echo htmlspecialchars($requirements['description'] ?? 'N/A'); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <div class="mb-3">
                                                            <span class="h5 text-success">$<?php echo number_format($order['final_price'] ?? $order['quoted_price'] ?? 0, 2); ?></span>
                                                        </div>
                                                        
                                                        <!-- Action Buttons -->
                                                        <div class="btn-group-vertical d-grid gap-2">
                                                            <?php if ($order['status'] === 'requested'): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="action" value="accept">
                                                                    <button type="submit" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-check me-1"></i>Accept Order
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($order['status'] === 'accepted'): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="action" value="start">
                                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                                        <i class="fas fa-play me-1"></i>Start Work
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($order['status'] === 'in_progress'): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="action" value="complete">
                                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                                        <i class="fas fa-flag-checkered me-1"></i>Mark Complete
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <a href="contact-customer.php?order_id=<?php echo $order['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-secondary btn-sm">
                                                                <i class="fas fa-comments me-1"></i>Contact
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
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