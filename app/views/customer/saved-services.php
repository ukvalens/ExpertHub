<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=saved-services&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

// Ensure active cart exists
$stmt = $conn->prepare("SELECT id FROM shopping_carts WHERE customer_id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart = $stmt->get_result()->fetch_assoc();
if (!$cart) {
    $stmt = $conn->prepare("INSERT INTO shopping_carts (customer_id, status) VALUES (?, 'active')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_id = $conn->insert_id;
} else {
    $cart_id = $cart['id'];
}

// AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $service_id = (int)($_POST['service_id'] ?? 0);

    if ($_POST['action'] === 'save' && $service_id) {
        // Avoid duplicates
        $chk = $conn->prepare("SELECT id FROM cart_items WHERE cart_id = ? AND service_id = ?");
        $chk->bind_param("ii", $cart_id, $service_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, service_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $cart_id, $service_id);
            $stmt->execute();
        }
        echo json_encode(['ok' => true]); exit;
    }

    if ($_POST['action'] === 'remove' && $service_id) {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND service_id = ?");
        $stmt->bind_param("ii", $cart_id, $service_id);
        $stmt->execute();
        echo json_encode(['ok' => true]); exit;
    }
}

// Fetch saved services
$stmt = $conn->prepare("SELECT ci.id as item_id, ci.added_at, ci.service_id,
    ps.title, ps.description, ps.base_price, ps.pricing_model, ps.provider_id,
    sp.rating, sp.verification_status,
    u.first_name, u.last_name, u.profile_image,
    sc.name as category_name,
    (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status = 'completed') as total_orders
    FROM cart_items ci
    JOIN provider_services ps ON ci.service_id = ps.id
    JOIN service_providers sp ON ps.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN service_categories sc ON ps.category_id = sc.id
    WHERE ci.cart_id = ? AND ps.status = 'active'
    ORDER BY ci.added_at DESC");
$stmt->bind_param("i", $cart_id);
$stmt->execute();
$saved = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-bookmark me-2" style="color:var(--accent-color)"></i>Saved Services
            <span class="badge bg-secondary ms-1"><?php echo count($saved); ?></span>
        </span>
        <a href="#" class="btn btn-sm btn-outline-primary nav-link-ajax" data-page="browse-services">
            <i class="fas fa-search me-1"></i>Browse More
        </a>
    </div>
    <div class="card-body">

        <?php if (empty($saved)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bookmark fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No saved services yet</h6>
                <p class="text-muted small">Bookmark services you're interested in to find them easily later.</p>
                <a href="#" class="btn btn-primary btn-sm nav-link-ajax" data-page="browse-services">
                    <i class="fas fa-search me-1"></i>Browse Services
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($saved as $svc): ?>
                <div class="col-md-6 col-lg-4" id="saved-<?php echo $svc['service_id']; ?>">
                    <div class="card h-100">
                        <div class="card-body">
                            <?php if ($svc['category_name']): ?>
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($svc['category_name']); ?>
                                </small>
                            <?php endif; ?>
                            <h6 class="card-title text-primary mb-1"><?php echo htmlspecialchars($svc['title']); ?></h6>
                            <p class="card-text text-muted small mb-2">
                                <?php echo htmlspecialchars(mb_strimwidth($svc['description'] ?? '', 0, 90, '...')); ?>
                            </p>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if ($svc['profile_image']): ?>
                                    <img src="../../../<?php echo htmlspecialchars($svc['profile_image']); ?>"
                                         class="rounded-circle" style="width:22px;height:22px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                         style="width:22px;height:22px;font-size:.6rem;">
                                        <?php echo strtoupper(substr($svc['first_name'],0,1).substr($svc['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted"><?php echo htmlspecialchars($svc['first_name'].' '.$svc['last_name']); ?></small>
                                <?php if ($svc['verification_status'] === 'verified'): ?>
                                    <i class="fas fa-check-circle text-success" style="font-size:.72rem;" title="Verified"></i>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="text-warning" style="font-size:.75rem;">
                                    <?php $r = round($svc['rating'] ?? 0);
                                    for ($s = 1; $s <= 5; $s++) echo '<i class="fas fa-star'.($s > $r ? ' text-muted' : '').'"></i>'; ?>
                                </span>
                                <small class="text-muted">(<?php echo $svc['total_orders']; ?> completed)</small>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>Saved <?php echo date('M j, Y', strtotime($svc['added_at'])); ?>
                            </small>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                            <span class="text-success fw-bold">$<?php echo number_format($svc['base_price'], 2); ?></span>
                            <div class="d-flex gap-1">
                                <button class="btn btn-outline-danger btn-sm remove-btn"
                                        data-id="<?php echo $svc['service_id']; ?>" title="Remove">
                                    <i class="fas fa-bookmark"></i>
                                </button>
                                <a href="../customer/order.php?service_id=<?php echo $svc['service_id']; ?>&lang=<?php echo $lang; ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-shopping-cart me-1"></i>Order
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
(function(){
    const url  = 'index.php?page=saved-services&lang=<?php echo $lang; ?>';

    document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id   = btn.dataset.id;
            const data = new FormData();
            data.append('action', 'remove');
            data.append('service_id', id);
            fetch(url, { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'} })
                .then(() => {
                    const card = document.getElementById('saved-' + id);
                    if (card) card.remove();
                    // Update badge count
                    const badge = document.querySelector('.card-header .badge');
                    if (badge) badge.textContent = parseInt(badge.textContent) - 1;
                });
        });
    });
})();
</script>
