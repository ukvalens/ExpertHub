<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=notifications&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$lang      = $_GET['lang'] ?? 'en';

// AJAX: mark all read (messages)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['ok' => true]); exit;
}

// Build notifications from two sources:
// 1. Unread messages grouped by order
// 2. Recent order status changes (last 30 days)

$notifications = [];

// --- Unread messages ---
$stmt = $conn->prepare("SELECT m.order_id, COUNT(*) as msg_count,
    MAX(m.created_at) as latest,
    o.order_number, o.service_title,
    u.first_name, u.last_name
    FROM messages m
    JOIN orders o ON m.order_id = o.id
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ? AND m.is_read = 0
    GROUP BY m.order_id
    ORDER BY latest DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($unread_msgs as $msg) {
    $notifications[] = [
        'type'    => 'message',
        'icon'    => 'fa-comments',
        'color'   => 'primary',
        'title'   => $msg['msg_count'] . ' new message' . ($msg['msg_count'] > 1 ? 's' : '') . ' from ' . htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']),
        'body'    => 'Order #' . htmlspecialchars($msg['order_number']) . ' — ' . htmlspecialchars($msg['service_title']),
        'time'    => $msg['latest'],
        'link_page' => $user_type === 'provider' ? 'provider-messages' : 'messages',
        'order_id'  => $msg['order_id'],
        'unread'  => true,
    ];
}

// --- Recent order status changes ---
if ($user_type === 'customer') {
    $stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status, o.updated_at,
        u.first_name, u.last_name
        FROM orders o
        JOIN service_providers sp ON o.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE o.customer_id = ? AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY o.updated_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status, o.updated_at,
        u.first_name, u.last_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        JOIN service_providers sp ON o.provider_id = sp.id
        WHERE sp.user_id = ? AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY o.updated_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_notifs = [
    'requested'   => ['icon' => 'fa-inbox',        'color' => 'primary',   'msg' => 'New order request'],
    'accepted'    => ['icon' => 'fa-check-circle',  'color' => 'success',   'msg' => 'Order accepted'],
    'in_progress' => ['icon' => 'fa-spinner',       'color' => 'warning',   'msg' => 'Order started'],
    'completed'   => ['icon' => 'fa-check-double',  'color' => 'success',   'msg' => 'Order completed'],
    'cancelled'   => ['icon' => 'fa-times-circle',  'color' => 'danger',    'msg' => 'Order cancelled'],
    'quoted'      => ['icon' => 'fa-file-invoice',  'color' => 'info',      'msg' => 'Quote received'],
    'disputed'    => ['icon' => 'fa-gavel',         'color' => 'danger',    'msg' => 'Order disputed'],
];

foreach ($recent_orders as $ord) {
    $sn = $status_notifs[$ord['status']] ?? ['icon' => 'fa-bell', 'color' => 'secondary', 'msg' => 'Order updated'];
    $notifications[] = [
        'type'      => 'order',
        'icon'      => $sn['icon'],
        'color'     => $sn['color'],
        'title'     => $sn['msg'] . ' — ' . htmlspecialchars($ord['service_title']),
        'body'      => 'Order #' . htmlspecialchars($ord['order_number']) . ' · ' . htmlspecialchars($ord['first_name'] . ' ' . $ord['last_name']),
        'time'      => $ord['updated_at'],
        'link_page' => $user_type === 'provider' ? 'provider-orders' : 'orders',
        'order_id'  => $ord['id'],
        'unread'    => false,
    ];
}

// Sort all by time desc
usort($notifications, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));

$unread_count = count(array_filter($notifications, fn($n) => $n['unread']));

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="fas fa-bell me-2" style="color:var(--accent-color)"></i>Notifications
            <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </span>
        <?php if ($unread_count > 0): ?>
            <button class="btn btn-sm btn-outline-secondary" id="markAllReadBtn">
                <i class="fas fa-check-double me-1"></i>Mark all read
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">

        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No notifications</h6>
                <p class="text-muted small">You're all caught up!</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                <div class="list-group-item list-group-item-action px-3 py-2 notif-item <?php echo $n['unread'] ? 'bg-light' : ''; ?>"
                     style="cursor:pointer; border-left: 3px solid var(--bs-<?php echo $n['color']; ?>, #ccc);"
                     onclick="goToNotif('<?php echo $n['link_page']; ?>', <?php echo $n['order_id']; ?>)">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-<?php echo $n['color']; ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:36px;height:36px;margin-top:2px;">
                            <i class="fas <?php echo $n['icon']; ?> text-<?php echo $n['color']; ?>" style="font-size:.8rem;"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-<?php echo $n['unread'] ? '600' : 'normal'; ?>" style="font-size:.85rem;">
                                    <?php echo $n['title']; ?>
                                </span>
                                <small class="text-muted ms-2 flex-shrink-0" style="font-size:.72rem;">
                                    <?php echo time_ago($n['time']); ?>
                                </small>
                            </div>
                            <div class="text-muted" style="font-size:.78rem;"><?php echo $n['body']; ?></div>
                        </div>
                        <?php if ($n['unread']): ?>
                            <div class="rounded-circle bg-primary flex-shrink-0" style="width:8px;height:8px;margin-top:6px;"></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
(function(){
    const lang = '<?php echo $lang; ?>';

    window.goToNotif = function(page, orderId) {
        const params = 'page=' + page + '&lang=' + lang + (orderId ? '&order_id=' + orderId : '');
        history.pushState({page, extra:{}}, '', 'index.php?' + params);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + params, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r => r.text()).then(html => {
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s => {
                    const ns = document.createElement('script');
                    ns.textContent = s.textContent;
                    document.body.appendChild(ns);
                });
                if (typeof bindAjaxLinks === 'function') bindAjaxLinks();
            });
    };

    document.getElementById('markAllReadBtn')?.addEventListener('click', () => {
        const data = new FormData();
        data.append('action', 'mark_read');
        fetch('index.php?page=notifications&lang=' + lang, {
            method: 'POST', body: data, headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(() => {
            if (typeof loadPage === 'function') loadPage('notifications', false);
        });
    });
})();
</script>
