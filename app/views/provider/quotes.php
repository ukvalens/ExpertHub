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
$status_filter = $_GET['status'] ?? 'pending';

// Handle send quote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];
    if ($_POST['action'] === 'send_quote' && !empty($_POST['quoted_price'])) {
        $price = (float)$_POST['quoted_price'];
        $note  = trim($_POST['quote_note'] ?? '');
        $stmt  = $conn->prepare("UPDATE orders SET status = 'quoted', quoted_price = ?, special_instructions = CONCAT(COALESCE(special_instructions,''), ?) WHERE id = ? AND provider_id = ?");
        $note_text = $note ? "\n[Quote note]: $note" : '';
        $stmt->bind_param("dsii", $price, $note_text, $order_id, $provider_id);
        $stmt->execute();
    } elseif ($_POST['action'] === 'revise_quote' && !empty($_POST['quoted_price'])) {
        $price = (float)$_POST['quoted_price'];
        $stmt  = $conn->prepare("UPDATE orders SET quoted_price = ? WHERE id = ? AND provider_id = ? AND status = 'quoted'");
        $stmt->bind_param("dii", $price, $order_id, $provider_id);
        $stmt->execute();
    } elseif ($_POST['action'] === 'cancel_quote') {
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancellation_reason = 'Quote withdrawn by provider' WHERE id = ? AND provider_id = ? AND status = 'quoted'");
        $stmt->bind_param("ii", $order_id, $provider_id);
        $stmt->execute();
    }
}

// Counts per status
$stmt = $conn->prepare("SELECT
    COUNT(CASE WHEN status = 'requested' THEN 1 END) as requested_count,
    COUNT(CASE WHEN status = 'quoted'    THEN 1 END) as quoted_count,
    COUNT(CASE WHEN status = 'accepted'  THEN 1 END) as accepted_count
    FROM orders WHERE provider_id = ?");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Map filter to DB status
$status_map = [
    'pending'  => 'requested',
    'sent'     => 'quoted',
    'accepted' => 'accepted',
];
$db_status = $status_map[$status_filter] ?? 'requested';

$page_num  = max(1, (int)($_GET['qpage'] ?? 1));
$per_page  = 10;
$offset    = ($page_num - 1) * $per_page;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE provider_id = ? AND status = ?");
$stmt->bind_param("is", $provider_id, $db_status);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $conn->prepare("SELECT o.id, o.order_number, o.status, o.created_at, o.updated_at,
    o.quoted_price, o.final_price, o.customer_requirements, o.special_instructions,
    o.order_type, o.priority, o.scheduled_date,
    ps.title as service_title, ps.base_price,
    u.first_name, u.last_name,
    cd.brand, cd.model, cd.device_type
    FROM orders o
    JOIN provider_services ps ON o.service_id = ps.id
    JOIN users u ON o.customer_id = u.id
    LEFT JOIN customer_devices cd ON o.device_id = cd.id
    WHERE o.provider_id = ? AND o.status = ?
    ORDER BY o.updated_at DESC
    LIMIT ? OFFSET ?");
$stmt->bind_param("isii", $provider_id, $db_status, $per_page, $offset);
$stmt->execute();
$quotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$tabs = [
    'pending'  => ['label' => 'Need Quote',  'count' => $counts['requested_count'], 'color' => 'primary'],
    'sent'     => ['label' => 'Sent',         'count' => $counts['quoted_count'],    'color' => 'warning'],
    'accepted' => ['label' => 'Accepted',     'count' => $counts['accepted_count'],  'color' => 'success'],
];
?>

<div class="content-card">
    <div class="card-header">
        <i class="fas fa-file-invoice-dollar me-2" style="color:var(--accent-color)"></i>Quotes
    </div>
    <div class="card-body">

        <!-- Tabs -->
        <div class="btn-group mb-3 flex-wrap" role="group">
            <?php foreach ($tabs as $key => $tab): ?>
                <a href="?page=quotes&status=<?php echo $key; ?>"
                   class="btn btn-sm <?php echo $status_filter === $key ? 'btn-'.$tab['color'] : 'btn-outline-'.$tab['color']; ?> nav-link-ajax"
                   data-page="quotes" data-status="<?php echo $key; ?>">
                    <?php echo $tab['label']; ?>
                    <span class="badge bg-white text-dark ms-1"><?php echo $tab['count']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($quotes)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No quotes <?php echo $status_filter === 'pending' ? 'needed' : $status_filter; ?></h6>
                <p class="text-muted small">
                    <?php if ($status_filter === 'pending'): ?>All requests have been quoted or handled.
                    <?php elseif ($status_filter === 'sent'): ?>No quotes awaiting customer response.
                    <?php else: ?>No accepted quotes yet.<?php endif; ?>
                </p>
            </div>
        <?php else: ?>

            <?php foreach ($quotes as $q):
                $requirements = json_decode($q['customer_requirements'], true);
                $age_hours = (time() - strtotime($q['created_at'])) / 3600;
            ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-start">

                        <!-- Info -->
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <strong class="text-muted small">#<?php echo htmlspecialchars($q['order_number']); ?></strong>
                                <?php if ($q['priority'] === 'emergency'): ?>
                                    <span class="badge bg-danger">🚨 Emergency</span>
                                <?php elseif ($q['priority'] === 'high'): ?>
                                    <span class="badge bg-warning text-dark">High Priority</span>
                                <?php endif; ?>
                                <span class="text-muted small ms-auto">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php
                                        if ($age_hours < 1) echo round($age_hours * 60) . 'm ago';
                                        elseif ($age_hours < 24) echo round($age_hours) . 'h ago';
                                        else echo date('M j, Y', strtotime($q['created_at']));
                                    ?>
                                </span>
                            </div>

                            <h6 class="text-primary mb-1"><?php echo htmlspecialchars($q['service_title']); ?></h6>

                            <p class="mb-1 small">
                                <i class="fas fa-user me-1 text-muted"></i>
                                <?php echo htmlspecialchars($q['first_name'] . ' ' . $q['last_name']); ?>
                            </p>

                            <?php if ($q['brand']): ?>
                            <p class="mb-1 small">
                                <i class="fas fa-laptop me-1 text-muted"></i>
                                <?php echo htmlspecialchars($q['brand'] . ' ' . $q['model']); ?>
                            </p>
                            <?php endif; ?>

                            <?php if (!empty($requirements['description'])): ?>
                            <p class="mb-1 small text-muted">
                                <i class="fas fa-clipboard me-1"></i>
                                <?php echo htmlspecialchars(mb_strimwidth($requirements['description'], 0, 120, '...')); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ($q['scheduled_date']): ?>
                            <p class="mb-0 small">
                                <i class="fas fa-calendar-alt me-1 text-info"></i>
                                Scheduled: <?php echo date('M j, Y', strtotime($q['scheduled_date'])); ?>
                            </p>
                            <?php endif; ?>

                            <p class="mb-0 small text-muted mt-1">
                                <i class="fas fa-tag me-1"></i>Base price: $<?php echo number_format($q['base_price'], 2); ?>
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="col-md-4 text-end mt-2 mt-md-0">

                            <?php if ($status_filter === 'pending'): ?>
                                <!-- Send quote form -->
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $q['id']; ?>">
                                    <input type="hidden" name="action" value="send_quote">
                                    <div class="input-group input-group-sm mb-2">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="quoted_price" class="form-control"
                                               placeholder="Your price" step="0.01" min="0"
                                               value="<?php echo $q['base_price']; ?>" required>
                                    </div>
                                    <input type="text" name="quote_note" class="form-control form-control-sm mb-2"
                                           placeholder="Note (optional)">
                                    <button class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-paper-plane me-1"></i>Send Quote
                                    </button>
                                </form>

                            <?php elseif ($status_filter === 'sent'): ?>
                                <div class="h5 text-warning mb-2">
                                    $<?php echo number_format($q['quoted_price'] ?? 0, 2); ?>
                                    <small class="text-muted d-block" style="font-size:.7rem">Awaiting response</small>
                                </div>
                                <!-- Revise -->
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="order_id" value="<?php echo $q['id']; ?>">
                                    <input type="hidden" name="action" value="revise_quote">
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="quoted_price" class="form-control"
                                               value="<?php echo $q['quoted_price']; ?>" step="0.01" min="0" required>
                                    </div>
                                    <button class="btn btn-outline-warning btn-sm w-100">
                                        <i class="fas fa-edit me-1"></i>Revise
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Withdraw this quote?')">
                                    <input type="hidden" name="order_id" value="<?php echo $q['id']; ?>">
                                    <input type="hidden" name="action" value="cancel_quote">
                                    <button class="btn btn-outline-danger btn-sm w-100">
                                        <i class="fas fa-times me-1"></i>Withdraw
                                    </button>
                                </form>

                            <?php elseif ($status_filter === 'accepted'): ?>
                                <div class="h5 text-success mb-1">
                                    $<?php echo number_format($q['final_price'] ?? $q['quoted_price'] ?? 0, 2); ?>
                                </div>
                                <span class="badge bg-success mb-2"><i class="fas fa-check me-1"></i>Accepted</span>
                                <div>
                                    <a href="?page=provider-orders" class="btn btn-outline-primary btn-sm w-100 nav-link-ajax" data-page="provider-orders">
                                        <i class="fas fa-list me-1"></i>View Order
                                    </a>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
            <nav><ul class="pagination justify-content-center mt-3">
                <?php if ($page_num > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="quotes" data-status="<?php echo $status_filter; ?>" href="?page=quotes&status=<?php echo $status_filter; ?>&qpage=<?php echo $page_num - 1; ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page_num ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="quotes" data-status="<?php echo $status_filter; ?>" href="?page=quotes&status=<?php echo $status_filter; ?>&qpage=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page_num < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="quotes" data-status="<?php echo $status_filter; ?>" href="?page=quotes&status=<?php echo $status_filter; ?>&qpage=<?php echo $page_num + 1; ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
