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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];
    $sql = match($action) {
        'accept'   => "UPDATE orders SET status = 'accepted' WHERE id = ? AND provider_id = ?",
        'start'    => "UPDATE orders SET status = 'in_progress', started_at = NOW() WHERE id = ? AND provider_id = ?",
        'complete' => "UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ? AND provider_id = ?",
        default    => null
    };
    if ($sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $order_id, $provider_id);
        $stmt->execute();
    }
}

// Fetch all active orders grouped by status
$stmt = $conn->prepare("SELECT o.id, o.order_number, o.status, o.final_price, o.quoted_price, o.created_at,
    ps.title as service_title, u.first_name, u.last_name,
    cd.brand, cd.model
    FROM orders o
    JOIN provider_services ps ON o.service_id = ps.id
    JOIN users u ON o.customer_id = u.id
    LEFT JOIN customer_devices cd ON o.device_id = cd.id
    WHERE o.provider_id = ? AND o.status IN ('requested','accepted','in_progress','completed')
    ORDER BY o.created_at DESC");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$all_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$columns = [
    'requested'   => ['label' => 'New Requests', 'color' => 'primary',  'icon' => 'inbox'],
    'accepted'    => ['label' => 'Accepted',      'color' => 'info',     'icon' => 'check'],
    'in_progress' => ['label' => 'In Progress',   'color' => 'warning',  'icon' => 'spinner'],
    'completed'   => ['label' => 'Completed',     'color' => 'success',  'icon' => 'check-circle'],
];

$grouped = array_fill_keys(array_keys($columns), []);
foreach ($all_orders as $o) {
    if (isset($grouped[$o['status']])) $grouped[$o['status']][] = $o;
}
?>

<style>
.board-wrap { display: flex; gap: 1rem; overflow-x: auto; overflow-y: hidden; padding-bottom: .5rem; align-items: flex-start; }
.board-col { min-width: 240px; flex: 1; background: #f8f9fa; border-radius: 10px; padding: .75rem; max-height: calc(100vh - 180px); overflow-y: auto; }
.board-col-header { font-weight: 600; font-size: .9rem; margin-bottom: .75rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #f8f9fa; z-index: 1; padding-bottom: .25rem; }
.board-card { background: #fff; border-radius: 8px; padding: .75rem; margin-bottom: .6rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); font-size: .85rem; }
.board-card .order-num { font-weight: 700; color: #555; }
.board-card .service-name { font-weight: 600; color: #333; margin: .25rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.board-card .meta { color: #888; font-size: .78rem; }
.board-card .price { font-weight: 700; color: #198754; }
.board-card .actions { margin-top: .5rem; display: flex; gap: .4rem; flex-wrap: wrap; }
</style>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-columns me-2" style="color:var(--accent-color)"></i>Order Board</span>
        <a href="?page=provider-orders" class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="provider-orders">
            <i class="fas fa-list me-1"></i>List View
        </a>
    </div>
    <div class="card-body">
        <div class="board-wrap">
            <?php foreach ($columns as $status => $col): ?>
            <div class="board-col">
                <div class="board-col-header">
                    <span><i class="fas fa-<?php echo $col['icon']; ?> me-1 text-<?php echo $col['color']; ?>"></i><?php echo $col['label']; ?></span>
                    <span class="badge bg-<?php echo $col['color']; ?>"><?php echo count($grouped[$status]); ?></span>
                </div>

                <?php if (empty($grouped[$status])): ?>
                    <div class="text-center text-muted py-3" style="font-size:.8rem;">No orders</div>
                <?php endif; ?>

                <?php foreach ($grouped[$status] as $order): ?>
                <div class="board-card">
                    <div class="order-num">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                    <div class="service-name" title="<?php echo htmlspecialchars($order['service_title']); ?>">
                        <?php echo htmlspecialchars($order['service_title']); ?>
                    </div>
                    <div class="meta">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                    </div>
                    <?php if ($order['brand']): ?>
                    <div class="meta"><i class="fas fa-laptop me-1"></i><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></div>
                    <?php endif; ?>
                    <div class="meta"><i class="fas fa-clock me-1"></i><?php echo date('M j, g:i A', strtotime($order['created_at'])); ?></div>
                    <div class="price mt-1">$<?php echo number_format($order['final_price'] ?? $order['quoted_price'] ?? 0, 2); ?></div>
                    <div class="actions">
                        <?php if ($status === 'requested'): ?>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Accept</button>
                            </form>
                        <?php elseif ($status === 'accepted'): ?>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="start">
                                <button class="btn btn-warning btn-sm"><i class="fas fa-play me-1"></i>Start</button>
                            </form>
                        <?php elseif ($status === 'in_progress'): ?>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button class="btn btn-primary btn-sm"><i class="fas fa-check-circle me-1"></i>Complete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
