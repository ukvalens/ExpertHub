<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=wallet&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$lang      = $_GET['lang'] ?? 'en';

if ($user_type === 'customer') {
    $stmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN p.payment_status='completed' THEN p.amount END), 0) as total_spent,
        COUNT(CASE WHEN p.payment_status='completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN p.payment_status='pending'   THEN 1 END) as pending_count
        FROM orders o LEFT JOIN payments p ON o.id=p.order_id
        WHERE o.customer_id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT p.*, o.order_number, o.service_title
        FROM payments p JOIN orders o ON p.order_id=o.id
        WHERE o.customer_id=? ORDER BY p.created_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $summary = [
        ['icon'=>'fa-money-bill-wave','color'=>'primary',  'label'=>'Total Spent',          'value'=>'$'.number_format($stats['total_spent'],2)],
        ['icon'=>'fa-check-circle',   'color'=>'success',  'label'=>'Completed Payments',   'value'=>(int)$stats['completed_count']],
        ['icon'=>'fa-clock',          'color'=>'warning',  'label'=>'Pending Payments',     'value'=>(int)$stats['pending_count']],
    ];

} elseif ($user_type === 'provider') {
    $stmt = $conn->prepare("SELECT sp.id FROM service_providers sp WHERE sp.user_id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $sp_id = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);

    $stmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN p.payment_status='completed' THEN p.provider_amount END), 0) as total_earned,
        COALESCE(SUM(CASE WHEN p.payment_status='pending'   THEN p.provider_amount END), 0) as pending_amount,
        COUNT(CASE WHEN p.payment_status='completed' THEN 1 END) as completed_count,
        COALESCE(SUM(CASE WHEN p.payment_status='completed' AND p.payout_status='pending' THEN p.provider_amount END), 0) as available_payout
        FROM orders o LEFT JOIN payments p ON o.id=p.order_id
        WHERE o.provider_id=?");
    $stmt->bind_param("i", $sp_id); $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT p.*, o.order_number, o.service_title,
        u.first_name, u.last_name
        FROM payments p JOIN orders o ON p.order_id=o.id
        JOIN users u ON o.customer_id=u.id
        WHERE o.provider_id=? ORDER BY p.created_at DESC LIMIT 20");
    $stmt->bind_param("i", $sp_id); $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $summary = [
        ['icon'=>'fa-dollar-sign',    'color'=>'success',  'label'=>'Total Earned',         'value'=>'$'.number_format($stats['total_earned'],2)],
        ['icon'=>'fa-wallet',         'color'=>'primary',  'label'=>'Available for Payout', 'value'=>'$'.number_format($stats['available_payout'],2)],
        ['icon'=>'fa-clock',          'color'=>'warning',  'label'=>'Pending Clearance',    'value'=>'$'.number_format($stats['pending_amount'],2)],
    ];

} else {
    // admin
    $stmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN payment_status='completed' THEN amount END), 0)          as total_volume,
        COALESCE(SUM(CASE WHEN payment_status='completed' THEN platform_fee END), 0)    as total_fees,
        COUNT(CASE WHEN payment_status='pending' THEN 1 END)                            as pending_count
        FROM payments");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT p.*, o.order_number, o.service_title,
        uc.first_name as c_first, uc.last_name as c_last,
        up.first_name as p_first, up.last_name as p_last
        FROM payments p JOIN orders o ON p.order_id=o.id
        JOIN users uc ON o.customer_id=uc.id
        JOIN service_providers sp ON o.provider_id=sp.id
        JOIN users up ON sp.user_id=up.id
        ORDER BY p.created_at DESC LIMIT 20");
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $summary = [
        ['icon'=>'fa-exchange-alt',   'color'=>'primary',  'label'=>'Total Volume',         'value'=>'$'.number_format($stats['total_volume'],2)],
        ['icon'=>'fa-percentage',     'color'=>'success',  'label'=>'Platform Fees',        'value'=>'$'.number_format($stats['total_fees'],2)],
        ['icon'=>'fa-clock',          'color'=>'warning',  'label'=>'Pending Payments',     'value'=>(int)$stats['pending_count']],
    ];
}

$status_color = ['completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'info'];
?>

<div class="content-card">
    <div class="card-header">
        <i class="fas fa-wallet me-2" style="color:var(--accent-color)"></i>
        <?php echo $user_type === 'provider' ? 'Earnings & Wallet' : ($user_type === 'admin' ? 'Payment Overview' : 'My Wallet'); ?>
    </div>
    <div class="card-body">

        <!-- Summary cards -->
        <div class="row g-3 mb-4">
            <?php foreach ($summary as $s): ?>
            <div class="col-md-4">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <i class="fas <?php echo $s['icon']; ?> fa-2x text-<?php echo $s['color']; ?> mb-2"></i>
                    <h4 class="mb-0 text-<?php echo $s['color']; ?>"><?php echo $s['value']; ?></h4>
                    <small class="text-muted"><?php echo $s['label']; ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick actions -->
        <div class="d-flex gap-2 flex-wrap mb-4">
            <?php if ($user_type === 'customer'): ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#walletActionModal">
                    <i class="fas fa-plus me-1"></i>Add Funds
                </button>
                <button class="btn btn-sm btn-outline-primary nav-link-ajax" data-page="browse-services"
                        onclick="loadPage('browse-services')">
                    <i class="fas fa-search me-1"></i>Browse Services
                </button>
            <?php elseif ($user_type === 'provider'): ?>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#walletActionModal">
                    <i class="fas fa-money-bill-wave me-1"></i>Request Payout
                </button>
                <button class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="earnings"
                        onclick="loadPage('earnings')">
                    <i class="fas fa-chart-line me-1"></i>Earnings Dashboard
                </button>
            <?php endif; ?>
        </div>

        <!-- Payment methods (customer/provider only) -->
        <?php if ($user_type !== 'admin'): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="p-3 rounded border d-flex align-items-center gap-3">
                    <i class="fas fa-mobile-alt fa-2x text-warning"></i>
                    <div>
                        <h6 class="mb-0">Mobile Money</h6>
                        <small class="text-muted">MTN MoMo · Airtel Money</small>
                    </div>
                    <span class="badge bg-success ms-auto">Active</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded border d-flex align-items-center gap-3">
                    <i class="fas fa-credit-card fa-2x text-info"></i>
                    <div>
                        <h6 class="mb-0">Credit / Debit Card</h6>
                        <small class="text-muted">Visa · Mastercard</small>
                    </div>
                    <span class="badge bg-secondary ms-auto">Coming Soon</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Transaction history -->
        <h6 class="fw-semibold mb-3">
            <i class="fas fa-history me-2 text-muted"></i>Recent Transactions
        </h6>

        <?php if (empty($transactions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No transactions yet</h6>
                <p class="text-muted small">Your payment history will appear here.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" style="font-size:.83rem;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order</th>
                        <th>Service</th>
                        <?php if ($user_type === 'admin'): ?>
                            <th>Customer</th><th>Provider</th>
                        <?php elseif ($user_type === 'provider'): ?>
                            <th>Customer</th>
                        <?php endif; ?>
                        <th>Amount</th>
                        <?php if ($user_type === 'provider'): ?><th>Your Cut</th><?php endif; ?>
                        <th>Status</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="text-muted"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></td>
                        <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($t['order_number']); ?></span></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($t['service_title'],0,30,'…')); ?></td>
                        <?php if ($user_type === 'admin'): ?>
                            <td><?php echo htmlspecialchars($t['c_first'].' '.$t['c_last']); ?></td>
                            <td><?php echo htmlspecialchars($t['p_first'].' '.$t['p_last']); ?></td>
                        <?php elseif ($user_type === 'provider'): ?>
                            <td><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></td>
                        <?php endif; ?>
                        <td class="fw-bold text-primary">$<?php echo number_format($t['amount'],2); ?></td>
                        <?php if ($user_type === 'provider'): ?>
                            <td class="fw-bold text-success">$<?php echo number_format($t['provider_amount'] ?? 0,2); ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge bg-<?php echo $status_color[$t['payment_status']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($t['payment_status']); ?>
                            </span>
                        </td>
                        <td class="text-muted">
                            <?php echo strtoupper(str_replace('_',' ',$t['payment_method'] ?? 'MOMO')); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Action modal (Add Funds / Request Payout) -->
<div class="modal fade" id="walletActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-<?php echo $user_type === 'provider' ? 'money-bill-wave' : 'plus'; ?> me-2"></i>
                    <?php echo $user_type === 'provider' ? 'Request Payout' : 'Add Funds'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                <h5>Coming Soon</h5>
                <p class="text-muted small">
                    <?php if ($user_type === 'provider'): ?>
                        Payout requests will be available shortly. Earnings are currently settled manually by the admin.
                    <?php else: ?>
                        Wallet top-up is coming soon. Payments are currently processed directly at checkout via Mobile Money.
                    <?php endif; ?>
                </p>
                <div class="alert alert-info text-start mt-3 mb-0" style="font-size:.82rem;">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Current process:</strong>
                    <?php if ($user_type === 'provider'): ?>
                        Earnings are credited after order completion and paid out on a weekly cycle.
                    <?php else: ?>
                        Pay via MTN MoMo or Airtel Money when placing an order.
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
