<?php
if (!isset($conn)) { require_once '../../../config/database.php'; }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

if (!$provider) { echo '<div class="alert alert-danger">Provider not found.</div>'; return; }

$provider_id = $provider['id'];
$status_filter = $_GET['status'] ?? 'all';
$orders_page = max(1, (int)($_GET['orders_page'] ?? 1));
$per_page = 10;
$offset = ($orders_page - 1) * $per_page;

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];
    switch ($action) {
        case 'accept':
            $stmt = $conn->prepare("UPDATE orders SET status = 'accepted' WHERE id = ? AND provider_id = ?"); break;
        case 'start':
            $stmt = $conn->prepare("UPDATE orders SET status = 'in_progress', started_at = NOW() WHERE id = ? AND provider_id = ?"); break;
        case 'complete':
            $stmt = $conn->prepare("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ? AND provider_id = ?"); break;
    }
    if (isset($stmt)) {
        $stmt->bind_param("ii", $order_id, $provider_id);
        $stmt->execute();
    }
}

// Counts
$count_stmt = $conn->prepare("SELECT COUNT(*) as total,
    COUNT(CASE WHEN status = 'requested' THEN 1 END) as requested_count,
    COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_count,
    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
    FROM orders WHERE provider_id = ?");
$count_stmt->bind_param("i", $provider_id);
$count_stmt->execute();
$counts = $count_stmt->get_result()->fetch_assoc();

// Filter
$where_clause = "WHERE o.provider_id = ?";
$params = [$provider_id];
$param_types = "i";
if ($status_filter !== 'all') {
    $where_clause .= " AND o.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

$fc = $conn->prepare("SELECT COUNT(*) as total FROM orders o $where_clause");
$fc->bind_param($param_types, ...$params);
$fc->execute();
$total_orders = $fc->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);

// Orders
$stmt = $conn->prepare("SELECT o.*, ps.title as service_title, u.first_name, u.last_name,
    cd.device_type, cd.brand, cd.model,
    (SELECT COUNT(*) FROM messages WHERE order_id = o.id AND receiver_id = ? AND is_read = 0) as unread_messages
    FROM orders o
    JOIN provider_services ps ON o.service_id = ps.id
    JOIN users u ON o.customer_id = u.id
    LEFT JOIN customer_devices cd ON o.device_id = cd.id
    $where_clause ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
$params_full = array_merge([$_SESSION['user_id']], $params, [$per_page, $offset]);
$stmt->bind_param("i" . $param_types . "ii", ...$params_full);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content-card">
    <div class="card-header"><i class="fas fa-clipboard-list me-2" style="color:var(--accent-color)"></i>Order Management</div>
    <div class="card-body">

        <?php if ($counts['requested_count'] > 0 && $status_filter !== 'requested'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-bell me-2"></i><strong>New Order Alert!</strong> You have <?php echo $counts['requested_count']; ?> new request(s).
                <a href="?page=provider-orders&status=requested" class="btn btn-sm btn-success ms-2 nav-link-ajax" data-page="provider-orders"><i class="fas fa-eye me-1"></i>View Now</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="btn-group mb-3 flex-wrap" role="group">
            <?php foreach (['all' => 'All ('.$counts['total'].')', 'requested' => 'New ('.$counts['requested_count'].')', 'accepted' => 'Accepted ('.$counts['accepted_count'].')', 'in_progress' => 'Active ('.$counts['in_progress_count'].')', 'completed' => 'Done ('.$counts['completed_count'].')'] as $s => $label): ?>
                <a href="?page=provider-orders&status=<?php echo $s; ?>" class="btn btn-sm <?php echo $status_filter === $s ? 'btn-primary' : 'btn-outline-primary'; ?> nav-link-ajax" data-page="provider-orders"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
            <div class="text-center py-4">
                <i class="fas fa-clipboard-list fa-2x text-muted mb-2"></i>
                <p class="text-muted">No orders found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order):
                $requirements = json_decode($order['customer_requirements'], true); ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#<?php echo $order['order_number']; ?></strong>
                            <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'in_progress' ? 'warning' : ($order['status'] === 'accepted' ? 'info' : 'primary')); ?> ms-2">
                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                        </div>
                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="text-primary"><?php echo htmlspecialchars($order['service_title']); ?></h6>
                                <p class="mb-1"><i class="fas fa-user me-1"></i><strong>Customer:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                                <?php if ($order['device_type']): ?>
                                    <p class="mb-1"><i class="fas fa-laptop me-1"></i><strong>Device:</strong> <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></p>
                                <?php endif; ?>
                                <?php if ($requirements): ?>
                                    <p class="mb-1"><i class="fas fa-clipboard me-1"></i><strong>Notes:</strong> <?php echo htmlspecialchars($requirements['description'] ?? 'N/A'); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="h5 text-success mb-3">$<?php echo number_format($order['final_price'] ?? $order['quoted_price'] ?? 0, 2); ?></div>
                                <div class="d-grid gap-1">
                                    <?php if ($order['status'] === 'requested'): ?>
                                        <form method="POST"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="action" value="accept"><button class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Accept</button></form>
                                    <?php elseif ($order['status'] === 'accepted'): ?>
                                        <form method="POST"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="action" value="start"><button class="btn btn-warning btn-sm"><i class="fas fa-play me-1"></i>Start</button></form>
                                    <?php elseif ($order['status'] === 'in_progress'): ?>
                                        <form method="POST"><input type="hidden" name="order_id" value="<?php echo $order['id']; ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-primary btn-sm"><i class="fas fa-check-circle me-1"></i>Complete</button></form>
                                    <?php endif; ?>
                                    <a href="../provider/messages.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline-info btn-sm position-relative">
                                        <i class="fas fa-comments me-1"></i>Chat
                                        <?php if ($order['unread_messages'] > 0): ?><span class="badge bg-danger rounded-pill"><?php echo $order['unread_messages']; ?></span><?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
                <nav><ul class="pagination justify-content-center">
                    <?php if ($orders_page > 1): ?><li class="page-item"><a class="page-link nav-link-ajax" data-page="provider-orders" href="?page=provider-orders&status=<?php echo $status_filter; ?>&orders_page=<?php echo $orders_page-1; ?>">Prev</a></li><?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?><li class="page-item <?php echo $i === $orders_page ? 'active' : ''; ?>"><a class="page-link nav-link-ajax" data-page="provider-orders" href="?page=provider-orders&status=<?php echo $status_filter; ?>&orders_page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
                    <?php if ($orders_page < $total_pages): ?><li class="page-item"><a class="page-link nav-link-ajax" data-page="provider-orders" href="?page=provider-orders&status=<?php echo $status_filter; ?>&orders_page=<?php echo $orders_page+1; ?>">Next</a></li><?php endif; ?>
                </ul></nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
