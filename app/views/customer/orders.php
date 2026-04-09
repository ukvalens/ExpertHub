<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    $s    = isset($_GET['status']) ? '&status='.$_GET['status'] : '';
    header("Location: ../dashboard/index.php?page=orders&lang=$lang$s"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

// AJAX: submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $order_id   = (int)$_POST['order_id'];
    $rating     = max(1, min(5, (int)$_POST['rating']));
    $review_text= trim($_POST['review_text'] ?? '');

    // Get provider_id for this order
    $stmt = $conn->prepare("SELECT provider_id FROM orders WHERE id = ? AND customer_id = ? AND status = 'completed'");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $ord = $stmt->get_result()->fetch_assoc();

    if ($ord) {
        // Check not already reviewed
        $chk = $conn->prepare("SELECT id FROM reviews WHERE order_id = ?");
        $chk->bind_param("i", $order_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO reviews (order_id, reviewer_id, provider_id, overall_rating, review_text) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $order_id, $user_id, $ord['provider_id'], $rating, $review_text);
            $stmt->execute();
            // Update provider avg rating
            $conn->query("UPDATE service_providers SET rating = (SELECT AVG(overall_rating) FROM reviews WHERE provider_id = {$ord['provider_id']} AND status = 'active'), total_reviews = (SELECT COUNT(*) FROM reviews WHERE provider_id = {$ord['provider_id']}) WHERE id = {$ord['provider_id']}");
        }
    }
    echo json_encode(['ok' => true]); exit;
}

$status_filter = $_GET['status'] ?? 'all';
$ord_page      = max(1, (int)($_GET['opage'] ?? 1));
$per_page      = 10;
$offset        = ($ord_page - 1) * $per_page;

// Counts
$stmt = $conn->prepare("SELECT
    COUNT(*) as total,
    COUNT(CASE WHEN status IN ('requested','quoted','accepted','in_progress') THEN 1 END) as active_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
    FROM orders WHERE customer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Filter map
$status_map = [
    'all'       => null,
    'active'    => ['requested','quoted','accepted','in_progress'],
    'completed' => ['completed'],
    'review'    => ['awaiting_review'],
    'cancelled' => ['cancelled'],
];

$where  = "WHERE o.customer_id = ?";
$params = [$user_id];
$types  = 'i';

if ($status_filter !== 'all' && isset($status_map[$status_filter])) {
    $statuses = $status_map[$status_filter];
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $where  .= " AND o.status IN ($ph)";
    $params  = array_merge($params, $statuses);
    $types  .= str_repeat('s', count($statuses));
}

// Count filtered
$cnt = $conn->prepare("SELECT COUNT(*) as total FROM orders o $where");
$cnt->bind_param($types, ...$params);
$cnt->execute();
$total_orders = $cnt->get_result()->fetch_assoc()['total'];
$total_pages  = ceil($total_orders / $per_page);

// Orders
$p2 = array_merge([$user_id], $params, [$per_page, $offset]);
$t2 = 'i' . $types . 'ii';
$stmt = $conn->prepare("SELECT o.id, o.order_number, o.status, o.created_at, o.quoted_price, o.final_price,
    o.service_title, o.started_at, o.completed_at,
    u.first_name, u.last_name, u.profile_image,
    (SELECT COUNT(*) FROM messages WHERE order_id=o.id AND receiver_id=? AND is_read=0) as unread,
    (SELECT id FROM reviews WHERE order_id=o.id LIMIT 1) as review_id
    FROM orders o
    JOIN service_providers sp ON o.provider_id=sp.id
    JOIN users u ON sp.user_id=u.id
    $where ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_colors = [
    'requested'      => 'primary',
    'quoted'         => 'info',
    'accepted'       => 'info',
    'in_progress'    => 'warning',
    'awaiting_review'=> 'secondary',
    'completed'      => 'success',
    'cancelled'      => 'danger',
    'disputed'       => 'danger',
];

$tabs = [
    'all'       => ['label' => 'All',       'count' => $counts['total'],          'color' => 'secondary'],
    'active'    => ['label' => 'Active',    'count' => $counts['active_count'],   'color' => 'primary'],
    'completed' => ['label' => 'Completed', 'count' => $counts['completed_count'],'color' => 'success'],
    'cancelled' => ['label' => 'Cancelled', 'count' => $counts['cancelled_count'],'color' => 'danger'],
];
?>

<div class="content-card">
    <div class="card-header"><i class="fas fa-list-alt me-2" style="color:var(--accent-color)"></i>My Orders</div>
    <div class="card-body">

        <!-- Tabs -->
        <div class="btn-group mb-3 flex-wrap" role="group">
            <?php foreach ($tabs as $key => $tab): ?>
                <a href="?page=orders&status=<?php echo $key; ?>&lang=<?php echo $lang; ?>"
                   class="btn btn-sm <?php echo $status_filter === $key ? 'btn-'.$tab['color'] : 'btn-outline-'.$tab['color']; ?> nav-link-ajax"
                   data-page="orders" data-status="<?php echo $key; ?>">
                    <?php echo $tab['label']; ?>
                    <span class="badge bg-white text-dark ms-1"><?php echo $tab['count']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No orders found</h6>
                <p class="text-muted small">
                    <?php echo $status_filter === 'all' ? "You haven't placed any orders yet." : "No $status_filter orders."; ?>
                </p>
                <a href="#" class="btn btn-primary btn-sm nav-link-ajax" data-page="browse-services">
                    <i class="fas fa-search me-1"></i>Browse Services
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order):
                $color = $status_colors[$order['status']] ?? 'secondary';
            ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-start">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <strong class="text-muted small">#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                                <?php if ($order['unread'] > 0): ?>
                                    <span class="badge bg-danger"><i class="fas fa-envelope me-1"></i><?php echo $order['unread']; ?> new</span>
                                <?php endif; ?>
                                <small class="text-muted ms-auto"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                            </div>

                            <h6 class="text-primary mb-1"><?php echo htmlspecialchars($order['service_title']); ?></h6>

                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if ($order['profile_image']): ?>
                                    <img src="../../../<?php echo htmlspecialchars($order['profile_image']); ?>"
                                         class="rounded-circle" style="width:20px;height:20px;object-fit:cover;">
                                <?php endif; ?>
                                <small class="text-muted">
                                    <i class="fas fa-user-tie me-1"></i>
                                    <?php echo htmlspecialchars($order['first_name'].' '.$order['last_name']); ?>
                                </small>
                            </div>

                            <?php if ($order['started_at']): ?>
                                <small class="text-muted d-block">
                                    <i class="fas fa-play me-1 text-warning"></i>Started <?php echo date('M j, g:i A', strtotime($order['started_at'])); ?>
                                </small>
                            <?php endif; ?>
                            <?php if ($order['completed_at']): ?>
                                <small class="text-muted d-block">
                                    <i class="fas fa-check me-1 text-success"></i>Completed <?php echo date('M j, g:i A', strtotime($order['completed_at'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 text-end mt-2 mt-md-0">
                            <div class="h5 text-success mb-2">
                                $<?php echo number_format($order['final_price'] ?? $order['quoted_price'] ?? 0, 2); ?>
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <?php if (in_array($order['status'], ['accepted','in_progress','completed'])): ?>
                                    <a href="#" class="btn btn-outline-info btn-sm nav-link-ajax position-relative"
                                       data-page="messages" onclick="event.preventDefault(); selectConvCustomer(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-comments me-1"></i>Chat
                                        <?php if ($order['unread'] > 0): ?>
                                            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" style="font-size:.6rem;"><?php echo $order['unread']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (in_array($order['status'], ['accepted','in_progress'])): ?>
                                    <a href="../shared/video-call.php?order_id=<?php echo $order['id']; ?>&lang=<?php echo $lang; ?>"
                                       class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-video me-1"></i>Video Call
                                    </a>
                                <?php endif; ?>
                                <?php if ($order['status'] === 'completed' && !$order['review_id']): ?>
                                    <button class="btn btn-outline-warning btn-sm review-btn"
                                            data-id="<?php echo $order['id']; ?>"
                                            data-service="<?php echo htmlspecialchars($order['service_title'], ENT_QUOTES); ?>"
                                            data-provider="<?php echo htmlspecialchars($order['first_name'].' '.$order['last_name'], ENT_QUOTES); ?>">
                                        <i class="fas fa-star me-1"></i>Review
                                    </button>
                                <?php elseif ($order['review_id']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Reviewed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
            <nav><ul class="pagination justify-content-center mt-2">
                <?php if ($ord_page > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="orders" data-status="<?php echo $status_filter; ?>"
                        href="?page=orders&status=<?php echo $status_filter; ?>&opage=<?php echo $ord_page-1; ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $ord_page ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="orders" data-status="<?php echo $status_filter; ?>"
                           href="?page=orders&status=<?php echo $status_filter; ?>&opage=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($ord_page < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="orders" data-status="<?php echo $status_filter; ?>"
                        href="?page=orders&status=<?php echo $status_filter; ?>&opage=<?php echo $ord_page+1; ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-star me-2 text-warning"></i>Leave a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reviewAlert"></div>
                <p class="mb-1 small text-muted">Service: <strong id="rService"></strong></p>
                <p class="mb-3 small text-muted">Provider: <strong id="rProvider"></strong></p>
                <div class="mb-3 text-center">
                    <div class="d-flex justify-content-center gap-2" id="starRow">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star fa-2x text-muted star-pick" data-val="<?php echo $i; ?>"
                               style="cursor:pointer;transition:color .1s;"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="rRating" value="0">
                </div>
                <textarea class="form-control" id="rText" rows="4" placeholder="Share your experience..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="submitReviewBtn"><i class="fas fa-star me-1"></i>Submit</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const lang = '<?php echo $lang; ?>';
    const url  = 'index.php?page=orders&lang=' + lang;

    // Chat shortcut
    window.selectConvCustomer = function(orderId) {
        history.pushState({page:'messages',extra:{}}, '', 'index.php?page=messages&lang='+lang+'&order_id='+orderId);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?page=messages&lang='+lang+'&order_id='+orderId, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    };

    // Star rating
    let selectedRating = 0;
    document.querySelectorAll('.star-pick').forEach(star => {
        star.addEventListener('mouseenter', () => {
            document.querySelectorAll('.star-pick').forEach((s,i) => s.classList.toggle('text-warning', i < star.dataset.val));
        });
        star.addEventListener('mouseleave', () => {
            document.querySelectorAll('.star-pick').forEach((s,i) => s.classList.toggle('text-warning', i < selectedRating));
        });
        star.addEventListener('click', () => {
            selectedRating = parseInt(star.dataset.val);
            document.getElementById('rRating').value = selectedRating;
            document.querySelectorAll('.star-pick').forEach((s,i) => {
                s.classList.toggle('text-warning', i < selectedRating);
                s.classList.toggle('text-muted', i >= selectedRating);
            });
        });
    });

    // Open review modal
    let currentOrderId = null;
    document.querySelectorAll('.review-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            currentOrderId = btn.dataset.id;
            document.getElementById('rService').textContent  = btn.dataset.service;
            document.getElementById('rProvider').textContent = btn.dataset.provider;
            document.getElementById('rRating').value = 0;
            selectedRating = 0;
            document.querySelectorAll('.star-pick').forEach(s => { s.classList.remove('text-warning'); s.classList.add('text-muted'); });
            document.getElementById('rText').value = '';
            document.getElementById('reviewAlert').innerHTML = '';
            new bootstrap.Modal(document.getElementById('reviewModal')).show();
        });
    });

    // Submit review
    document.getElementById('submitReviewBtn')?.addEventListener('click', () => {
        if (!selectedRating) {
            document.getElementById('reviewAlert').innerHTML = '<div class="alert alert-danger py-1">Please select a rating.</div>';
            return;
        }
        const data = new FormData();
        data.append('action', 'submit_review');
        data.append('order_id', currentOrderId);
        data.append('rating', selectedRating);
        data.append('review_text', document.getElementById('rText').value);
        fetch(url + '&status=<?php echo $status_filter; ?>', {method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(() => {
                bootstrap.Modal.getInstance(document.getElementById('reviewModal'))?.hide();
                if (typeof loadPage === 'function') loadPage('orders', false, {status:'<?php echo $status_filter; ?>'});
            });
    });
})();
</script>
