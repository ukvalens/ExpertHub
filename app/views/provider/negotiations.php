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
$user_id     = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];

    // Verify order belongs to this provider and is in quoted status
    $chk = $conn->prepare("SELECT id, customer_id FROM orders WHERE id = ? AND provider_id = ? AND status = 'quoted'");
    $chk->bind_param("ii", $order_id, $provider_id);
    $chk->execute();
    $chk_order = $chk->get_result()->fetch_assoc();

    if ($chk_order) {
        $customer_id = $chk_order['customer_id'];

        if ($_POST['action'] === 'counter' && !empty($_POST['counter_price'])) {
            $price   = (float)$_POST['counter_price'];
            $message = trim($_POST['counter_message'] ?? '');
            // Update quoted price
            $stmt = $conn->prepare("UPDATE orders SET quoted_price = ? WHERE id = ?");
            $stmt->bind_param("di", $price, $order_id);
            $stmt->execute();
            // Log as message
            $content = "💬 Counter-offer: $" . number_format($price, 2) . ($message ? " — $message" : '');
            $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_type, message_content) VALUES (?, ?, ?, 'text', ?)");
            $stmt->bind_param("iiis", $order_id, $user_id, $customer_id, $content);
            $stmt->execute();

        } elseif ($_POST['action'] === 'accept_counter' && !empty($_POST['counter_price'])) {
            $price = (float)$_POST['counter_price'];
            $stmt  = $conn->prepare("UPDATE orders SET status = 'accepted', quoted_price = ?, final_price = ? WHERE id = ?");
            $stmt->bind_param("ddi", $price, $price, $order_id);
            $stmt->execute();
            $content = "✅ Provider accepted the counter-offer of $" . number_format($price, 2);
            $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_type, message_content) VALUES (?, ?, ?, 'text', ?)");
            $stmt->bind_param("iiis", $order_id, $user_id, $customer_id, $content);
            $stmt->execute();

        } elseif ($_POST['action'] === 'decline') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancellation_reason = 'Negotiation ended by provider' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $content = "❌ Provider ended the negotiation.";
            $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_type, message_content) VALUES (?, ?, ?, 'text', ?)");
            $stmt->bind_param("iiis", $order_id, $user_id, $customer_id, $content);
            $stmt->execute();
        }
    }
}

// Fetch quoted orders (active negotiations)
$page_num  = max(1, (int)($_GET['npage'] ?? 1));
$per_page  = 8;
$offset    = ($page_num - 1) * $per_page;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE provider_id = ? AND status = 'quoted'");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$total       = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $conn->prepare("SELECT o.id, o.order_number, o.created_at, o.updated_at,
    o.quoted_price, o.final_price, o.customer_requirements, o.priority, o.order_type,
    ps.title as service_title, ps.base_price,
    u.id as customer_user_id, u.first_name, u.last_name,
    cd.brand, cd.model
    FROM orders o
    JOIN provider_services ps ON o.service_id = ps.id
    JOIN users u ON o.customer_id = u.id
    LEFT JOIN customer_devices cd ON o.device_id = cd.id
    WHERE o.provider_id = ? AND o.status = 'quoted'
    ORDER BY o.updated_at DESC
    LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $provider_id, $per_page, $offset);
$stmt->execute();
$negotiations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch last 4 messages per order for negotiation history
$histories = [];
foreach ($negotiations as $n) {
    $stmt = $conn->prepare("SELECT m.message_content, m.created_at, u.first_name, u.user_type
        FROM messages m JOIN users u ON m.sender_id = u.id
        WHERE m.order_id = ? AND m.message_type = 'text'
        ORDER BY m.created_at DESC LIMIT 4");
    $stmt->bind_param("i", $n['id']);
    $stmt->execute();
    $histories[$n['id']] = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-handshake me-2" style="color:var(--accent-color)"></i>Negotiations</span>
        <span class="badge bg-warning text-dark"><?php echo $total; ?> active</span>
    </div>
    <div class="card-body">

        <?php if (empty($negotiations)): ?>
            <div class="text-center py-5">
                <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No active negotiations</h6>
                <p class="text-muted small">Negotiations appear here when customers respond to your quotes.</p>
            </div>
        <?php else: ?>

            <?php foreach ($negotiations as $neg):
                $requirements = json_decode($neg['customer_requirements'], true);
                $age_hours    = (time() - strtotime($neg['updated_at'])) / 3600;
                $history      = $histories[$neg['id']] ?? [];
            ?>
            <div class="card mb-3 border-start border-4 border-warning">
                <div class="card-body">

                    <!-- Header row -->
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <strong class="text-muted small">#<?php echo htmlspecialchars($neg['order_number']); ?></strong>
                            <?php if ($neg['priority'] === 'emergency'): ?>
                                <span class="badge bg-danger ms-1">🚨 Emergency</span>
                            <?php elseif ($neg['priority'] === 'high'): ?>
                                <span class="badge bg-warning text-dark ms-1">High</span>
                            <?php endif; ?>
                            <h6 class="text-primary mb-0 mt-1"><?php echo htmlspecialchars($neg['service_title']); ?></h6>
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($neg['first_name'] . ' ' . $neg['last_name']); ?>
                                <?php if ($neg['brand']): ?>
                                    &nbsp;·&nbsp;<i class="fas fa-laptop me-1"></i><?php echo htmlspecialchars($neg['brand'] . ' ' . $neg['model']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?php
                                    if ($age_hours < 1) echo round($age_hours * 60) . 'm ago';
                                    elseif ($age_hours < 24) echo round($age_hours) . 'h ago';
                                    else echo date('M j, Y', strtotime($neg['updated_at']));
                                ?>
                            </div>
                            <div class="small text-muted">Base: $<?php echo number_format($neg['base_price'], 2); ?></div>
                            <div class="fw-bold text-warning">Current: $<?php echo number_format($neg['quoted_price'] ?? 0, 2); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($requirements['description'])): ?>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-clipboard me-1"></i>
                        <?php echo htmlspecialchars(mb_strimwidth($requirements['description'], 0, 100, '...')); ?>
                    </p>
                    <?php endif; ?>

                    <!-- Negotiation history -->
                    <?php if (!empty($history)): ?>
                    <div class="bg-light rounded p-2 mb-3" style="font-size:.8rem; max-height:120px; overflow-y:auto;">
                        <?php foreach ($history as $msg): ?>
                        <div class="mb-1">
                            <span class="fw-semibold <?php echo $msg['user_type'] === 'provider' ? 'text-primary' : 'text-success'; ?>">
                                <?php echo $msg['user_type'] === 'provider' ? 'You' : htmlspecialchars($msg['first_name']); ?>:
                            </span>
                            <?php echo htmlspecialchars($msg['message_content']); ?>
                            <span class="text-muted ms-1" style="font-size:.7rem"><?php echo date('M j g:i A', strtotime($msg['created_at'])); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="row g-2">
                        <!-- Counter offer -->
                        <div class="col-md-6">
                            <form class="neg-form">
                                <input type="hidden" name="order_id" value="<?php echo $neg['id']; ?>">
                                <input type="hidden" name="action" value="counter">
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="counter_price" class="form-control"
                                           placeholder="Counter price" step="0.01" min="0"
                                           value="<?php echo $neg['quoted_price']; ?>" required>
                                </div>
                                <input type="text" name="counter_message" class="form-control form-control-sm mb-1"
                                       placeholder="Message (optional)">
                                <button class="btn btn-warning btn-sm w-100">
                                    <i class="fas fa-reply me-1"></i>Send Counter-offer
                                </button>
                            </form>
                        </div>
                        <!-- Accept / Decline -->
                        <div class="col-md-6 d-flex flex-column gap-2">
                            <form class="neg-form">
                                <input type="hidden" name="order_id" value="<?php echo $neg['id']; ?>">
                                <input type="hidden" name="action" value="accept_counter">
                                <input type="hidden" name="counter_price" value="<?php echo $neg['quoted_price']; ?>">
                                <button class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-check me-1"></i>Accept $<?php echo number_format($neg['quoted_price'] ?? 0, 2); ?>
                                </button>
                            </form>
                            <form class="neg-form neg-decline">
                                <input type="hidden" name="order_id" value="<?php echo $neg['id']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button class="btn btn-outline-danger btn-sm w-100">
                                    <i class="fas fa-times me-1"></i>End Negotiation
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
            <nav><ul class="pagination justify-content-center mt-3">
                <?php if ($page_num > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="negotiations" href="?page=negotiations&npage=<?php echo $page_num - 1; ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page_num ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="negotiations" href="?page=negotiations&npage=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page_num < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="negotiations" href="?page=negotiations&npage=<?php echo $page_num + 1; ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.neg-form').forEach(form => {
    form.addEventListener('submit', e => {
        e.preventDefault();
        if (form.classList.contains('neg-decline') && !confirm('End this negotiation?')) return;
        const data = new FormData(form);
        fetch('index.php?page=negotiations&lang=<?php echo $_GET["lang"] ?? "en"; ?>', {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(() => {
            if (typeof loadPage === 'function') loadPage('negotiations', false);
        });
    });
});
</script>
