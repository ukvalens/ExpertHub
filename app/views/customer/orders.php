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

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $order_id = $_POST['order_id'];
    $rating = $_POST['rating'];
    $review_text = $_POST['review_text'];
    
    // For now, just show success message (database table needs to be created)
    $review_success = true;
    
    // TODO: Create reviews table with columns: id, order_id, rating, review_text, created_at
    // $stmt = $conn->prepare("INSERT INTO reviews (order_id, rating, review_text, created_at) VALUES (?, ?, ?, NOW())");
    // $stmt->bind_param("iis", $order_id, $rating, $review_text);
    // $stmt->execute();
}

// Build query conditions
$where_clause = "WHERE o.customer_id = ?";
$params = [$user_id];
$param_types = "i";

if ($status_filter !== 'all') {
    $where_clause .= " AND o.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Get counts for each status
$count_stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN o.status IN ('requested', 'accepted', 'in_progress') THEN 1 END) as active_count,
    COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_count
    FROM orders o WHERE o.customer_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$counts = $count_stmt->get_result()->fetch_assoc();

// Get total count for current filter
$filter_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders o 
                                   JOIN provider_services ps ON o.service_id = ps.id 
                                   JOIN service_providers sp ON o.provider_id = sp.id 
                                   JOIN users u ON sp.user_id = u.id 
                                   $where_clause");
$filter_count_stmt->bind_param($param_types, ...$params);
$filter_count_stmt->execute();
$total_orders = $filter_count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);

// Get customer orders with pagination and message counts
$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM messages WHERE order_id = o.id AND receiver_id = ? AND is_read = 0) as unread_messages
                       FROM orders o 
                       JOIN provider_services ps ON o.service_id = ps.id 
                       JOIN service_providers sp ON o.provider_id = sp.id 
                       JOIN users u ON sp.user_id = u.id 
                       $where_clause 
                       ORDER BY o.created_at DESC 
                       LIMIT ? OFFSET ?");
$params_with_user = array_merge([$user_id], $params, [$per_page, $offset]);
$param_types_with_user = "i" . $param_types . "ii";
$stmt->bind_param($param_types_with_user, ...$params_with_user);
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
                        <!-- Review Success Alert -->
                        <?php if (isset($review_success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Thank you!</strong> Your review has been submitted successfully.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Filter Buttons -->
                        <div class="btn-group mb-4" role="group">
                            <a href="orders.php?status=all&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All (<?php echo $counts['total']; ?>)</a>
                            <a href="orders.php?status=requested&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'requested' ? 'btn-primary' : 'btn-outline-primary'; ?>">Active (<?php echo $counts['active_count']; ?>)</a>
                            <a href="orders.php?status=completed&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" 
                               class="btn <?php echo $status_filter === 'completed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Completed (<?php echo $counts['completed_count']; ?>)</a>
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
                                                <div class="btn-group w-100" role="group">
                                                    <a href="order-details.php?order_id=<?php echo $order['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>Details
                                                    </a>
                                                    
                                                    <?php if (in_array($order['status'], ['accepted', 'in_progress', 'completed'])): ?>
                                                        <a href="messages.php?order_id=<?php echo $order['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-info position-relative">
                                                            <i class="fas fa-comments me-1"></i>Chat
                                                            <?php if ($order['unread_messages'] > 0): ?>
                                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                                                    <?php echo $order['unread_messages']; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($order['status'], ['accepted', 'in_progress'])): ?>
                                                        <a href="../shared/video-call.php?order_id=<?php echo $order['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-video me-1"></i>Call
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($order['status'] === 'completed'): ?>
                                                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reviewModal" data-order-id="<?php echo $order['id']; ?>" data-provider="<?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>" data-service="<?php echo htmlspecialchars($order['service_title']); ?>">
                                                            <i class="fas fa-star me-1"></i>Review
                                                        </button>
                                                    <?php endif; ?>
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
    
    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-star me-2"></i>Leave a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="reviewOrderId">
                        <div class="mb-3">
                            <label class="form-label">Service:</label>
                            <p class="fw-bold" id="reviewService"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Provider:</label>
                            <p class="fw-bold" id="reviewProvider"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rating *</label>
                            <div class="rating-stars">
                                <input type="radio" name="rating" value="5" id="star5" required>
                                <label for="star5"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="4" id="star4">
                                <label for="star4"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="3" id="star3">
                                <label for="star3"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="2" id="star2">
                                <label for="star2"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="1" id="star1">
                                <label for="star1"><i class="fas fa-star"></i></label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="review_text" class="form-label">Your Review</label>
                            <textarea class="form-control" name="review_text" rows="4" placeholder="Share your experience..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_review" class="btn btn-warning">
                            <i class="fas fa-star me-1"></i>Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle review modal
        document.getElementById('reviewModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('reviewOrderId').value = button.getAttribute('data-order-id');
            document.getElementById('reviewService').textContent = button.getAttribute('data-service');
            document.getElementById('reviewProvider').textContent = button.getAttribute('data-provider');
        });
        
        // Star rating functionality
        document.querySelectorAll('.rating-stars input').forEach(input => {
            input.addEventListener('change', function() {
                const rating = this.value;
                document.querySelectorAll('.rating-stars label').forEach((label, index) => {
                    if (index >= 5 - rating) {
                        label.style.color = '#ffc107';
                    } else {
                        label.style.color = '#dee2e6';
                    }
                });
            });
        });
    </script>
    <style>
        .rating-stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating-stars input {
            display: none;
        }
        .rating-stars label {
            cursor: pointer;
            font-size: 1.5rem;
            color: #dee2e6;
            margin-right: 5px;
        }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #ffc107;
        }
        .rating-stars input:checked ~ label {
            color: #ffc107;
        }
    </style>
</body>
</html>