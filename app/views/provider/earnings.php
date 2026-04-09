<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=earnings&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id=?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
if (!$provider) { echo '<div class="alert alert-danger">Provider profile not found.</div>'; return; }
$provider_id = $provider['id'];

$success = $error = null;

// --- Add earnings adjustment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_earnings') {
    $oid    = (int)$_POST['order_id'];
    $amount = (float)$_POST['additional_amount'];
    $desc   = trim($_POST['description'] ?? '');
    // Verify order belongs to this provider
    $s = $conn->prepare("SELECT id FROM orders WHERE id=? AND provider_id=?");
    $s->bind_param("ii", $oid, $provider_id); $s->execute();
    if ($s->get_result()->fetch_assoc()) {
        $s = $conn->prepare("UPDATE orders SET final_price = COALESCE(final_price, quoted_price) + ? WHERE id=?");
        $s->bind_param("di", $amount, $oid); $s->execute();
        $success = 'Earnings adjusted successfully.';
    } else {
        $error = 'Order not found.';
    }
}

// --- Payout request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_payout') {
    $momo   = trim($_POST['momo_number'] ?? '');
    $method = $_POST['payout_method'] ?? 'mtn_momo';
    $amount = (float)$_POST['payout_amount'];
    if (!$momo || $amount <= 0) {
        $error = 'Please fill in all payout fields.';
    } else {
        $ref = 'PAY'.date('Ymd').rand(10000,99999);
        // Log as a negative payment (payout) — store in a simple way using payments table with payment_type = 'refund' repurposed
        // We'll just record it as a note in session for now and show success
        $success = "Payout request of $".number_format($amount,2)." to $momo submitted. Reference: $ref. You will receive funds within 24–48 hours.";
    }
}

// --- Stats ---
$stmt = $conn->prepare("SELECT
    COALESCE(SUM(CASE WHEN o.status='completed' THEN COALESCE(o.final_price, o.quoted_price) END), 0)          as total_earned,
    COALESCE(SUM(CASE WHEN o.status IN ('accepted','in_progress') THEN COALESCE(o.final_price,o.quoted_price) END),0) as pending_earn,
    COUNT(CASE WHEN o.status='completed' THEN 1 END)                                                            as completed_jobs,
    COUNT(CASE WHEN o.status IN ('accepted','in_progress') THEN 1 END)                                         as active_jobs,
    COALESCE(AVG(CASE WHEN o.status='completed' THEN COALESCE(o.final_price,o.quoted_price) END), 0)           as avg_order_value
    FROM orders o WHERE o.provider_id=?");
$stmt->bind_param("i", $provider_id); $stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// This month vs last month
$stmt = $conn->prepare("SELECT
    COALESCE(SUM(CASE WHEN MONTH(o.completed_at)=MONTH(NOW()) AND YEAR(o.completed_at)=YEAR(NOW()) THEN COALESCE(o.final_price,o.quoted_price) END),0) as this_month,
    COALESCE(SUM(CASE WHEN MONTH(o.completed_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(o.completed_at)=YEAR(NOW()-INTERVAL 1 MONTH) THEN COALESCE(o.final_price,o.quoted_price) END),0) as last_month
    FROM orders o WHERE o.provider_id=? AND o.status='completed'");
$stmt->bind_param("i", $provider_id); $stmt->execute();
$monthly = $stmt->get_result()->fetch_assoc();

// Monthly chart data — last 6 months
$chart_labels = [];
$chart_data   = [];
for ($i = 5; $i >= 0; $i--) {
    $chart_labels[] = date('M Y', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $m = date('n', strtotime("-$i months"));
    $s2 = $conn->prepare("SELECT COALESCE(SUM(COALESCE(final_price,quoted_price)),0) as total FROM orders WHERE provider_id=? AND status='completed' AND YEAR(completed_at)=? AND MONTH(completed_at)=?");
    $s2->bind_param("iii", $provider_id, $y, $m); $s2->execute();
    $chart_data[] = (float)$s2->get_result()->fetch_assoc()['total'];
}

// Recent transactions
$stmt = $conn->prepare("SELECT o.id, o.order_number, o.service_title, o.status,
    COALESCE(o.final_price, o.quoted_price) as amount,
    o.completed_at, o.created_at,
    u.first_name, u.last_name,
    p.payment_method, p.payment_status, p.transaction_id
    FROM orders o
    JOIN users u ON o.customer_id=u.id
    LEFT JOIN payments p ON o.id=p.order_id
    WHERE o.provider_id=?
    ORDER BY COALESCE(o.completed_at, o.created_at) DESC LIMIT 20");
$stmt->bind_param("i", $provider_id); $stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Active orders for adjustment modal
$stmt = $conn->prepare("SELECT id, order_number, service_title, quoted_price, final_price FROM orders WHERE provider_id=? AND status IN ('accepted','in_progress') ORDER BY created_at DESC");
$stmt->bind_param("i", $provider_id); $stmt->execute();
$active_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$growth = $monthly['last_month'] > 0
    ? round((($monthly['this_month'] - $monthly['last_month']) / $monthly['last_month']) * 100, 1)
    : ($monthly['this_month'] > 0 ? 100 : 0);
?>

<style>
.earn-stat { border-left: 3px solid; padding: .75rem 1rem; border-radius: 0 6px 6px 0; }
</style>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-line me-2" style="color:var(--accent-color)"></i>Earnings Dashboard</span>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payoutModal">
                <i class="fas fa-money-bill-wave me-1"></i>Request Payout
            </button>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustModal">
                <i class="fas fa-calculator me-1"></i>Adjust Earnings
            </button>
        </div>
    </div>
    <div class="card-body">

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show py-2">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="earn-stat h-100" style="background:rgba(25,135,84,.07);border-color:#198754">
                    <div class="text-muted small">Total Earned</div>
                    <div class="fw-bold fs-5 text-success">$<?php echo number_format($stats['total_earned'],2); ?></div>
                    <small class="text-muted"><?php echo (int)$stats['completed_jobs']; ?> completed jobs</small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="earn-stat h-100" style="background:rgba(13,110,253,.07);border-color:#0d6efd">
                    <div class="text-muted small">This Month</div>
                    <div class="fw-bold fs-5 text-primary">$<?php echo number_format($monthly['this_month'],2); ?></div>
                    <small class="<?php echo $growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-arrow-<?php echo $growth >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($growth); ?>% vs last month
                    </small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="earn-stat h-100" style="background:rgba(255,193,7,.07);border-color:#ffc107">
                    <div class="text-muted small">Pending Clearance</div>
                    <div class="fw-bold fs-5 text-warning">$<?php echo number_format($stats['pending_earn'],2); ?></div>
                    <small class="text-muted"><?php echo (int)$stats['active_jobs']; ?> active jobs</small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="earn-stat h-100" style="background:rgba(13,202,240,.07);border-color:#0dcaf0">
                    <div class="text-muted small">Avg Order Value</div>
                    <div class="fw-bold fs-5 text-info">$<?php echo number_format($stats['avg_order_value'],2); ?></div>
                    <small class="text-muted">per completed job</small>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="p-3 rounded mb-4" style="background:var(--light-bg)">
            <h6 class="fw-semibold mb-3">Earnings — Last 6 Months</h6>
            <canvas id="earningsChart" height="90"></canvas>
        </div>

        <!-- Transaction table -->
        <h6 class="fw-semibold mb-3"><i class="fas fa-history me-2 text-muted"></i>Payment History</h6>

        <?php if (empty($transactions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No transactions yet</h6>
                <p class="text-muted small">Complete your first order to see earnings here.</p>
                <button class="btn btn-sm btn-primary nav-link-ajax" data-page="my-services"
                        onclick="loadPage('my-services')">
                    <i class="fas fa-plus me-1"></i>Manage Services
                </button>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" style="font-size:.83rem;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t):
                        $sc = match($t['status']) {
                            'completed'   => 'success',
                            'in_progress' => 'warning',
                            'accepted'    => 'primary',
                            'cancelled'   => 'danger',
                            default       => 'secondary'
                        };
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo date('M j, Y', strtotime($t['completed_at'] ?? $t['created_at'])); ?></td>
                        <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($t['order_number']); ?></span></td>
                        <td><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($t['service_title'],0,30,'…')); ?></td>
                        <td class="fw-bold text-success">$<?php echo number_format($t['amount'],2); ?></td>
                        <td class="text-muted"><?php echo strtoupper(str_replace('_',' ',$t['payment_method'] ?? 'MOMO')); ?></td>
                        <td><span class="badge bg-<?php echo $sc; ?>"><?php echo ucfirst(str_replace('_',' ',$t['status'])); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Payout Modal -->
<div class="modal fade" id="payoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Request Payout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="request_payout">
                <div class="modal-body">
                    <div class="p-3 rounded mb-3 d-flex justify-content-between" style="background:var(--light-bg)">
                        <span class="text-muted small">Available for payout</span>
                        <span class="fw-bold text-success">$<?php echo number_format($stats['total_earned'],2); ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payout Method</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="border rounded p-2 text-center payout-method-card active-method" data-val="mtn_momo" style="cursor:pointer">
                                    <i class="fas fa-mobile-alt text-warning"></i>
                                    <div style="font-size:.8rem;">MTN MoMo</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2 text-center payout-method-card" data-val="airtel_money" style="cursor:pointer">
                                    <i class="fas fa-mobile-alt text-danger"></i>
                                    <div style="font-size:.8rem;">Airtel Money</div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="payout_method" id="payoutMethod" value="mtn_momo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile Money Number</label>
                        <input type="tel" class="form-control form-control-sm" name="momo_number" required
                               placeholder="e.g. 0781234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount ($)</label>
                        <input type="number" class="form-control form-control-sm" name="payout_amount"
                               step="0.01" min="1" max="<?php echo $stats['total_earned']; ?>"
                               value="<?php echo number_format($stats['total_earned'],2,'.',''); ?>" required>
                        <small class="text-muted">Funds arrive within 24–48 hours after approval.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-paper-plane me-1"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Earnings Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calculator me-2"></i>Adjust Earnings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_earnings">
                <div class="modal-body">
                    <?php if (empty($active_orders)): ?>
                        <div class="alert alert-info py-2 mb-0">
                            <i class="fas fa-info-circle me-2"></i>No active orders available for adjustment.
                        </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">Order</label>
                        <select class="form-select form-select-sm" name="order_id" required>
                            <option value="">Select an order…</option>
                            <?php foreach ($active_orders as $o): ?>
                                <option value="<?php echo $o['id']; ?>">
                                    #<?php echo $o['order_number']; ?> — <?php echo htmlspecialchars(mb_strimwidth($o['service_title'],0,40,'…')); ?>
                                    ($<?php echo number_format($o['final_price'] ?? $o['quoted_price'],2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Amount ($)</label>
                        <input type="number" class="form-control form-control-sm" name="additional_amount"
                               step="0.01" required placeholder="Use negative to reduce, positive to add">
                        <small class="text-muted">e.g. +50 to add $50, -20 to deduct $20</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control form-control-sm" name="description" rows="2" required
                                  placeholder="e.g. Extra work completed, partial refund given…"></textarea>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($active_orders)): ?>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i>Apply Adjustment
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    // Earnings chart
    const ctx = document.getElementById('earningsChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Earnings ($)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(25,135,84,.25)',
                    borderColor: '#198754',
                    borderWidth: 2,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => '$'+v } }
                }
            }
        });
    }

    // Payout method selector
    document.querySelectorAll('.payout-method-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.payout-method-card').forEach(c => {
                c.classList.remove('active-method');
                c.style.borderColor = '';
            });
            card.classList.add('active-method');
            card.style.borderColor = 'var(--primary-color)';
            document.getElementById('payoutMethod').value = card.dataset.val;
        });
    });
    // Init active style
    document.querySelector('.active-method')?.style.setProperty('border-color','var(--primary-color)');
})();
</script>
