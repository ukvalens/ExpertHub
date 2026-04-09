<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=customers&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$lang = $_GET['lang'] ?? 'en';

// --- AJAX actions ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cid    = (int)($_POST['customer_id'] ?? 0);
    header('Content-Type: application/json');

    if ($action === 'update_status' && $cid) {
        $status = $_POST['status'];
        if (!in_array($status, ['active','inactive','suspended'])) { echo json_encode(['ok'=>false]); exit; }
        $s = $conn->prepare("UPDATE users SET status=? WHERE id=? AND user_type='customer'");
        $s->bind_param("si", $status, $cid);
        echo json_encode(['ok' => $s->execute()]);
    } elseif ($action === 'delete' && $cid) {
        $s = $conn->prepare("DELETE FROM users WHERE id=? AND user_type='customer'");
        $s->bind_param("i", $cid);
        echo json_encode(['ok' => $s->execute()]);
    } elseif ($action === 'reset_password' && $cid) {
        $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE users SET password=? WHERE id=? AND user_type='customer'");
        $s->bind_param("si", $new_pass, $cid);
        echo json_encode(['ok' => $s->execute()]);
    }
    exit;
}

// --- Filters ---
$search        = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$page          = max(1, (int)($_GET['p'] ?? 1));
$per_page      = 12;
$offset        = ($page - 1) * $per_page;

// Status counts
$sc = $conn->query("SELECT status, COUNT(*) as cnt FROM users WHERE user_type='customer' GROUP BY status");
$counts = ['all' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0, 'pending_verification' => 0];
foreach ($sc->fetch_all(MYSQLI_ASSOC) as $r) {
    $counts[$r['status']] = (int)$r['cnt'];
    $counts['all'] += (int)$r['cnt'];
}

// Build WHERE
$where  = "WHERE u.user_type = 'customer'";
$params = [];
$types  = '';
if ($status_filter !== 'all') {
    $where   .= " AND u.status = ?";
    $params[] = $status_filter;
    $types   .= 's';
}
if ($search !== '') {
    $like     = "%$search%";
    $where   .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params   = array_merge($params, [$like, $like, $like, $like]);
    $types   .= 'ssss';
}

// Total
$cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM users u $where");
if ($types) $cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total       = (int)$cnt_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total / $per_page));

// Fetch customers with order stats
$fp = array_merge($params, [$per_page, $offset]);
$ft = $types . 'ii';
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.country,
           u.status, u.created_at, u.profile_image,
           COUNT(DISTINCT o.id)                                          AS total_orders,
           COUNT(DISTINCT CASE WHEN o.status='completed' THEN o.id END) AS completed_orders,
           COALESCE(SUM(CASE WHEN o.status='completed' THEN o.final_price END), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.customer_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
if ($ft) $stmt->bind_param($ft, ...$fp);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_badge = [
    'active'               => 'success',
    'inactive'             => 'secondary',
    'suspended'            => 'danger',
    'pending_verification' => 'warning',
];
$status_label = [
    'active'               => 'Active',
    'inactive'             => 'Inactive',
    'suspended'            => 'Suspended',
    'pending_verification' => 'Pending',
];
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-users me-2" style="color:var(--accent-color)"></i>Customer Management</span>
        <span class="badge bg-primary"><?php echo number_format($counts['all']); ?> total</span>
    </div>
    <div class="card-body">

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <?php foreach ([
                ['label'=>'Total',     'key'=>'all',                  'icon'=>'fa-users',        'color'=>'primary'],
                ['label'=>'Active',    'key'=>'active',               'icon'=>'fa-user-check',   'color'=>'success'],
                ['label'=>'Suspended', 'key'=>'suspended',            'icon'=>'fa-user-slash',   'color'=>'danger'],
                ['label'=>'Pending',   'key'=>'pending_verification', 'icon'=>'fa-user-clock',   'color'=>'warning'],
            ] as $s): ?>
            <div class="col-6 col-md-3">
                <div class="card text-center border-<?php echo $s['color']; ?>">
                    <div class="card-body py-3">
                        <i class="fas <?php echo $s['icon']; ?> fa-lg text-<?php echo $s['color']; ?> mb-1"></i>
                        <div class="h4 mb-0 text-<?php echo $s['color']; ?>"><?php echo $counts[$s['key']]; ?></div>
                        <div class="small text-muted"><?php echo $s['label']; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Search + filter -->
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <form class="d-flex gap-2 flex-grow-1" method="GET" id="filterForm">
                <input type="hidden" name="page" value="customers">
                <input type="hidden" name="lang" value="<?php echo $lang; ?>">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>" id="statusInput">
                <div class="input-group input-group-sm" style="max-width:320px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="q" placeholder="Search name, email, phone…"
                           value="<?php echo htmlspecialchars($search); ?>">
                    <?php if ($search): ?>
                        <a href="?page=customers&status=<?php echo $status_filter; ?>&lang=<?php echo $lang; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="btn-group btn-group-sm flex-wrap" role="group">
                <?php foreach ([
                    'all'       => ['All',       'secondary'],
                    'active'    => ['Active',    'success'],
                    'suspended' => ['Suspended', 'danger'],
                    'inactive'  => ['Inactive',  'secondary'],
                ] as $key => [$label, $color]):
                    $active = $status_filter === $key;
                ?>
                <a href="?page=customers&status=<?php echo $key; ?>&q=<?php echo urlencode($search); ?>&lang=<?php echo $lang; ?>"
                   class="btn btn-sm nav-link-ajax <?php echo $active ? "btn-$color" : "btn-outline-$color"; ?>"
                   data-page="customers" data-status="<?php echo $key; ?>">
                    <?php echo $label; ?>
                    <span class="badge bg-white text-dark ms-1"><?php echo $counts[$key] ?? 0; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Table -->
        <?php if (empty($customers)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No customers found</h6>
                <p class="text-muted small"><?php echo $search ? "No results for \"$search\"." : "No customers in this category."; ?></p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th class="text-center">Orders</th>
                        <th class="text-center">Spent</th>
                        <th class="text-center">Status</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c):
                        $badge = $status_badge[$c['status']] ?? 'secondary';
                        $slabel = $status_label[$c['status']] ?? ucfirst($c['status']);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($c['profile_image']): ?>
                                    <img src="../../../<?php echo htmlspecialchars($c['profile_image']); ?>"
                                         class="rounded-circle" style="width:34px;height:34px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:34px;height:34px;font-size:.75rem;font-weight:700;">
                                        <?php echo strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></div>
                                    <div class="text-muted" style="font-size:.72rem;">#<?php echo $c['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small"><?php echo htmlspecialchars($c['email']); ?></div>
                            <?php if ($c['phone']): ?>
                                <div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($c['phone']); ?></div>
                            <?php endif; ?>
                            <?php if ($c['country']): ?>
                                <div class="text-muted" style="font-size:.72rem;"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($c['country']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="fw-semibold"><?php echo $c['total_orders']; ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?php echo $c['completed_orders']; ?> done</div>
                        </td>
                        <td class="text-center">
                            <div class="fw-semibold text-success small">$<?php echo number_format($c['total_spent'], 2); ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $badge; ?>"><?php echo $slabel; ?></span>
                        </td>
                        <td>
                            <div class="small text-muted"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></div>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="viewCustomer(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES); ?>)"
                                        title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Actions">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($c['status'] !== 'active'): ?>
                                        <li><a class="dropdown-item text-success" href="#"
                                               onclick="updateStatus(<?php echo $c['id']; ?>,'active');return false;">
                                            <i class="fas fa-user-check me-2"></i>Activate</a></li>
                                        <?php endif; ?>
                                        <?php if ($c['status'] !== 'suspended'): ?>
                                        <li><a class="dropdown-item text-warning" href="#"
                                               onclick="updateStatus(<?php echo $c['id']; ?>,'suspended');return false;">
                                            <i class="fas fa-user-slash me-2"></i>Suspend</a></li>
                                        <?php endif; ?>
                                        <?php if ($c['status'] !== 'inactive'): ?>
                                        <li><a class="dropdown-item text-secondary" href="#"
                                               onclick="updateStatus(<?php echo $c['id']; ?>,'inactive');return false;">
                                            <i class="fas fa-user-minus me-2"></i>Deactivate</a></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#"
                                               onclick="openResetPassword(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['first_name'].' '.$c['last_name'], ENT_QUOTES); ?>');return false;">
                                            <i class="fas fa-key me-2"></i>Reset Password</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#"
                                               onclick="deleteCustomer(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['first_name'].' '.$c['last_name'], ENT_QUOTES); ?>');return false;">
                                            <i class="fas fa-trash me-2"></i>Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link nav-link-ajax" data-page="customers" data-status="<?php echo $status_filter; ?>"
                       href="?page=customers&status=<?php echo $status_filter; ?>&q=<?php echo urlencode($search); ?>&p=<?php echo $page-1; ?>&lang=<?php echo $lang; ?>">Prev</a>
                </li>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link nav-link-ajax" data-page="customers" data-status="<?php echo $status_filter; ?>"
                       href="?page=customers&status=<?php echo $status_filter; ?>&q=<?php echo urlencode($search); ?>&p=<?php echo $i; ?>&lang=<?php echo $lang; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link nav-link-ajax" data-page="customers" data-status="<?php echo $status_filter; ?>"
                       href="?page=customers&status=<?php echo $status_filter; ?>&q=<?php echo urlencode($search); ?>&p=<?php echo $page+1; ?>&lang=<?php echo $lang; ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul></nav>
        <div class="text-center text-muted small">
            Showing <?php echo min($offset+1,$total); ?>–<?php echo min($offset+$per_page,$total); ?> of <?php echo $total; ?> customers
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<!-- View Customer Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user me-2"></i><span id="vcName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="vcBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Setting new password for: <strong id="rpName"></strong></p>
                <input type="hidden" id="rpId">
                <div class="mb-3">
                    <label class="form-label small">New Password *</label>
                    <input type="password" class="form-control form-control-sm" id="rpPass" placeholder="Min. 6 characters" minlength="6">
                </div>
                <div id="rpAlert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitResetPassword()">
                    <i class="fas fa-save me-1"></i>Save Password
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const base = 'index.php?page=customers&lang=<?php echo $lang; ?>';

    function post(data) {
        return fetch(base, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        }).then(r => r.json());
    }

    window.updateStatus = function(id, status) {
        const labels = { active: 'activate', suspended: 'suspend', inactive: 'deactivate' };
        if (!confirm(`Are you sure you want to ${labels[status]} this customer?`)) return;
        post({ action: 'update_status', customer_id: id, status })
            .then(d => { if (d.ok && typeof loadPage === 'function') loadPage('customers', false); });
    };

    window.deleteCustomer = function(id, name) {
        if (!confirm(`Permanently delete "${name}"? This cannot be undone.`)) return;
        post({ action: 'delete', customer_id: id })
            .then(d => { if (d.ok && typeof loadPage === 'function') loadPage('customers', false); });
    };

    window.openResetPassword = function(id, name) {
        document.getElementById('rpId').value   = id;
        document.getElementById('rpName').textContent = name;
        document.getElementById('rpPass').value  = '';
        document.getElementById('rpAlert').innerHTML = '';
        new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
    };

    window.submitResetPassword = function() {
        const pass = document.getElementById('rpPass').value.trim();
        if (pass.length < 6) {
            document.getElementById('rpAlert').innerHTML = '<div class="alert alert-danger py-1 small">Password must be at least 6 characters.</div>';
            return;
        }
        post({ action: 'reset_password', customer_id: document.getElementById('rpId').value, password: pass })
            .then(d => {
                if (d.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal'))?.hide();
                } else {
                    document.getElementById('rpAlert').innerHTML = '<div class="alert alert-danger py-1 small">Failed to reset password.</div>';
                }
            });
    };

    window.viewCustomer = function(c) {
        document.getElementById('vcName').textContent = c.first_name + ' ' + c.last_name;
        const statusColors = { active:'success', inactive:'secondary', suspended:'danger', pending_verification:'warning' };
        const statusLabels = { active:'Active', inactive:'Inactive', suspended:'Suspended', pending_verification:'Pending' };
        const badge = statusColors[c.status] || 'secondary';
        const slabel = statusLabels[c.status] || c.status;
        document.getElementById('vcBody').innerHTML = `
            <div class="row g-3">
                <div class="col-md-4 text-center">
                    ${c.profile_image
                        ? `<img src="../../../${c.profile_image}" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">`
                        : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-2" style="width:80px;height:80px;font-size:1.5rem;font-weight:700;">${c.first_name[0].toUpperCase()}${c.last_name[0].toUpperCase()}</div>`
                    }
                    <div class="fw-bold">${c.first_name} ${c.last_name}</div>
                    <span class="badge bg-${badge} mt-1">${slabel}</span>
                </div>
                <div class="col-md-8">
                    <table class="table table-sm table-borderless small">
                        <tr><td class="text-muted fw-semibold" style="width:35%">ID</td><td>#${c.id}</td></tr>
                        <tr><td class="text-muted fw-semibold">Email</td><td>${c.email}</td></tr>
                        <tr><td class="text-muted fw-semibold">Phone</td><td>${c.phone || '—'}</td></tr>
                        <tr><td class="text-muted fw-semibold">Country</td><td>${c.country || '—'}</td></tr>
                        <tr><td class="text-muted fw-semibold">Joined</td><td>${new Date(c.created_at).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'})}</td></tr>
                        <tr><td class="text-muted fw-semibold">Total Orders</td><td>${c.total_orders}</td></tr>
                        <tr><td class="text-muted fw-semibold">Completed</td><td>${c.completed_orders}</td></tr>
                        <tr><td class="text-muted fw-semibold">Total Spent</td><td class="text-success fw-semibold">$${parseFloat(c.total_spent).toFixed(2)}</td></tr>
                    </table>
                </div>
            </div>`;
        new bootstrap.Modal(document.getElementById('viewCustomerModal')).show();
    };
})();
</script>
