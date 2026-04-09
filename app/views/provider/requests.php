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

// Handle accept / decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    if ($_POST['action'] === 'accept') {
        $stmt = $conn->prepare("UPDATE orders SET status = 'accepted' WHERE id = ? AND provider_id = ? AND status = 'requested'");
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancellation_reason = 'Declined by provider' WHERE id = ? AND provider_id = ? AND status = 'requested'");
    }
    $stmt->bind_param("ii", $order_id, $provider_id);
    $stmt->execute();
}

// Counts
$stmt = $conn->prepare("SELECT COUNT(*) as total,
    COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN 1 END) as new_today
    FROM orders WHERE provider_id = ? AND status = 'requested'");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Fetch requests
$page_num = max(1, (int)($_GET['rpage'] ?? 1));
$per_page = 10;
$offset   = ($page_num - 1) * $per_page;

$total_pages = ceil($counts['total'] / $per_page);

$stmt = $conn->prepare("SELECT o.id, o.order_number, o.created_at, o.quoted_price, o.final_price,
    o.customer_requirements, o.special_instructions, o.order_type, o.priority,
    o.scheduled_date, o.scheduled_time,
    ps.title as service_title,
    u.first_name, u.last_name, u.profile_image,
    cd.device_type, cd.brand, cd.model
    FROM orders o
    JOIN provider_services ps ON o.service_id = ps.id
    JOIN users u ON o.customer_id = u.id
    LEFT JOIN customer_devices cd ON o.device_id = cd.id
    WHERE o.provider_id = ? AND o.status = 'requested'
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $provider_id, $per_page, $offset);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-inbox me-2" style="color:var(--accent-color)"></i>New Requests</span>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($counts['new_today'] > 0): ?>
                <span class="badge bg-success"><?php echo $counts['new_today']; ?> new today</span>
            <?php endif; ?>
            <span class="badge bg-primary"><?php echo $counts['total']; ?> pending</span>
        </div>
    </div>
    <div class="card-body">

        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No pending requests</h6>
                <p class="text-muted small">New customer requests will appear here.</p>
            </div>
        <?php else: ?>

            <?php foreach ($requests as $req):
                $requirements = json_decode($req['customer_requirements'], true);
                $age_hours = (time() - strtotime($req['created_at'])) / 3600;
            ?>
            <div class="card mb-3 border-start border-4 border-<?php echo $age_hours < 2 ? 'success' : ($age_hours < 24 ? 'warning' : 'secondary'); ?>">
                <div class="card-body">
                    <div class="row align-items-start">

                        <!-- Left: info -->
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <strong class="text-muted small">#<?php echo htmlspecialchars($req['order_number']); ?></strong>
                                <?php if ($req['priority'] === 'emergency'): ?>
                                    <span class="badge bg-danger">🚨 Emergency</span>
                                <?php elseif ($req['priority'] === 'high'): ?>
                                    <span class="badge bg-warning text-dark">High Priority</span>
                                <?php endif; ?>
                                <?php if ($req['order_type'] === 'scheduled'): ?>
                                    <span class="badge bg-info">Scheduled</span>
                                <?php endif; ?>
                                <span class="text-muted small ms-auto">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php
                                        if ($age_hours < 1) echo round($age_hours * 60) . 'm ago';
                                        elseif ($age_hours < 24) echo round($age_hours) . 'h ago';
                                        else echo date('M j, Y', strtotime($req['created_at']));
                                    ?>
                                </span>
                            </div>

                            <h6 class="text-primary mb-1"><?php echo htmlspecialchars($req['service_title']); ?></h6>

                            <p class="mb-1 small">
                                <i class="fas fa-user me-1 text-muted"></i>
                                <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?>
                            </p>

                            <?php if ($req['brand']): ?>
                            <p class="mb-1 small">
                                <i class="fas fa-laptop me-1 text-muted"></i>
                                <?php echo htmlspecialchars($req['brand'] . ' ' . $req['model']); ?>
                                <span class="text-muted">(<?php echo ucfirst($req['device_type']); ?>)</span>
                            </p>
                            <?php endif; ?>

                            <?php if (!empty($requirements['description'])): ?>
                            <p class="mb-1 small text-muted">
                                <i class="fas fa-clipboard me-1"></i>
                                <?php echo htmlspecialchars(mb_strimwidth($requirements['description'], 0, 120, '...')); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ($req['special_instructions']): ?>
                            <p class="mb-0 small text-muted fst-italic">
                                <i class="fas fa-sticky-note me-1"></i>
                                <?php echo htmlspecialchars(mb_strimwidth($req['special_instructions'], 0, 100, '...')); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ($req['scheduled_date']): ?>
                            <p class="mb-0 small mt-1">
                                <i class="fas fa-calendar-alt me-1 text-info"></i>
                                Scheduled: <?php echo date('M j, Y', strtotime($req['scheduled_date'])); ?>
                                <?php if ($req['scheduled_time']): ?>at <?php echo date('g:i A', strtotime($req['scheduled_time'])); ?><?php endif; ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Right: price + actions -->
                        <div class="col-md-4 text-end mt-2 mt-md-0">
                            <div class="h5 text-success mb-3">
                                $<?php echo number_format($req['final_price'] ?? $req['quoted_price'] ?? 0, 2); ?>
                            </div>
                            <div class="d-grid gap-2">
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button class="btn btn-success btn-sm w-100">
                                        <i class="fas fa-check me-1"></i>Accept
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Decline this request?')">
                                    <input type="hidden" name="order_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="decline">
                                    <button class="btn btn-outline-danger btn-sm w-100">
                                        <i class="fas fa-times me-1"></i>Decline
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
            <nav><ul class="pagination justify-content-center mt-3">
                <?php if ($page_num > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="requests" href="?page=requests&rpage=<?php echo $page_num - 1; ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page_num ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="requests" href="?page=requests&rpage=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page_num < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="requests" href="?page=requests&rpage=<?php echo $page_num + 1; ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
