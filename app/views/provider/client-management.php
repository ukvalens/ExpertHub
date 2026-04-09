<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=client-management&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

$s = $conn->prepare("SELECT id FROM service_providers WHERE user_id=?");
$s->bind_param("i", $user_id); $s->execute();
$sp_id = (int)($s->get_result()->fetch_assoc()['id'] ?? 0);
if (!$sp_id) { echo '<div class="alert alert-danger">Provider profile not found.</div>'; return; }

// ── Ensure client_notes column exists (graceful) ─────────────────────────────
// We store notes + tags + status in a JSON column on orders or a lightweight
// in-memory approach using a session-less PHP array stored in a simple flat
// structure. Since no client_management table exists, we use order notes
// stored in orders.special_instructions per customer, keyed by customer_id.

$success = $error = null;

// Save note / tag / status for a customer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $customer_id = (int)($_POST['customer_id'] ?? 0);

    if ($action === 'save_note' && $customer_id) {
        $note = trim($_POST['note'] ?? '');
        $tag  = trim($_POST['tag']  ?? '');
        // Store as JSON in the most recent order's special_instructions for this pair
        $meta = json_encode(['note' => $note, 'tag' => $tag, 'updated' => date('Y-m-d H:i:s')]);
        $stmt = $conn->prepare("UPDATE orders SET special_instructions=?
            WHERE provider_id=? AND customer_id=?
            ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("sii", $meta, $sp_id, $customer_id);
        $stmt->execute() ? $success = 'Notes saved.' : $error = 'Failed to save notes.';
    }

    if ($action === 'block' && $customer_id) {
        $meta = json_encode(['blocked' => true, 'updated' => date('Y-m-d H:i:s')]);
        $stmt = $conn->prepare("UPDATE orders SET special_instructions=?
            WHERE provider_id=? AND customer_id=?
            ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("sii", $meta, $sp_id, $customer_id);
        $stmt->execute() ? $success = 'Client blocked.' : $error = 'Failed.';
    }

    if ($action === 'unblock' && $customer_id) {
        $stmt = $conn->prepare("UPDATE orders SET special_instructions=NULL
            WHERE provider_id=? AND customer_id=?");
        $stmt->bind_param("ii", $sp_id, $customer_id);
        $stmt->execute() ? $success = 'Client unblocked.' : $error = 'Failed.';
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$search   = trim($_GET['cm_search'] ?? '');
$filter   = $_GET['cm_filter'] ?? 'all'; // all | repeat | new | blocked
$pg       = max(1, (int)($_GET['cm_page'] ?? 1));
$per_page = 10;
$offset   = ($pg - 1) * $per_page;
$like     = "%$search%";

// Fetch unique clients with aggregated data
$having = '';
if ($filter === 'repeat')  $having = 'HAVING total_orders > 1';
if ($filter === 'new')     $having = 'HAVING total_orders = 1';
if ($filter === 'blocked') $having = 'HAVING is_blocked = 1';

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM (
    SELECT o.customer_id
    FROM orders o JOIN users u ON o.customer_id=u.id
    WHERE o.provider_id=? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
    GROUP BY o.customer_id
    $having
) sub");
$count_stmt->bind_param("isss", $sp_id, $like, $like, $like);
$count_stmt->execute();
$total    = (int)$count_stmt->get_result()->fetch_row()[0];
$total_pg = max(1, ceil($total / $per_page));

$stmt = $conn->prepare("SELECT
    u.id, u.first_name, u.last_name, u.email, u.phone, u.profile_image, u.country,
    COUNT(o.id)                                                              as total_orders,
    SUM(CASE WHEN o.status='completed' THEN 1 ELSE 0 END)                   as completed_orders,
    COALESCE(SUM(CASE WHEN o.status='completed' THEN COALESCE(o.final_price,o.quoted_price) END),0) as total_value,
    MAX(o.created_at)                                                        as last_order,
    (SELECT special_instructions FROM orders
     WHERE provider_id=? AND customer_id=u.id
     ORDER BY created_at DESC LIMIT 1)                                       as meta_json,
    (SELECT COUNT(*) FROM messages
     WHERE receiver_id=? AND sender_id=u.id AND is_read=0)                  as unread_msgs,
    JSON_UNQUOTE(JSON_EXTRACT(
        (SELECT special_instructions FROM orders
         WHERE provider_id=? AND customer_id=u.id ORDER BY created_at DESC LIMIT 1),
        '$.blocked')) = 'true'                                               as is_blocked
    FROM orders o
    JOIN users u ON o.customer_id=u.id
    WHERE o.provider_id=? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
    GROUP BY u.id
    $having
    ORDER BY last_order DESC
    LIMIT ? OFFSET ?");
$stmt->bind_param("iiiisssii", $sp_id, $user_id, $sp_id, $sp_id, $like, $like, $like, $per_page, $offset);
$stmt->execute();
$clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary counts
$sc_stmt = $conn->prepare("SELECT
    COUNT(DISTINCT customer_id)                                              as total_clients,
    COUNT(DISTINCT CASE WHEN status='completed' THEN customer_id END)       as active_clients
    FROM orders WHERE provider_id=?");
$sc_stmt->bind_param("i", $sp_id); $sc_stmt->execute();
$sc = $sc_stmt->get_result()->fetch_assoc();

$tag_colors = ['vip'=>'warning','regular'=>'primary','new'=>'success','difficult'=>'danger','blocked'=>'secondary'];
?>

<style>
.cm-card { transition: box-shadow .15s; }
.cm-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
</style>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-cog me-2" style="color:var(--accent-color)"></i>Client Management</span>
        <small class="text-muted"><?php echo number_format($total); ?> client<?php echo $total!==1?'s':''; ?></small>
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

        <!-- Summary -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <div class="fw-bold fs-5 text-primary"><?php echo (int)$sc['total_clients']; ?></div>
                    <small class="text-muted">Total Clients</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <div class="fw-bold fs-5 text-success"><?php echo (int)$sc['active_clients']; ?></div>
                    <small class="text-muted">With Completed Orders</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <div class="fw-bold fs-5 text-warning" id="repeatCount">—</div>
                    <small class="text-muted">Repeat Clients</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <div class="fw-bold fs-5 text-info" id="newCount">—</div>
                    <small class="text-muted">New Clients</small>
                </div>
            </div>
        </div>

        <!-- Filters + Search -->
        <form method="GET" action="index.php" class="mb-3">
            <input type="hidden" name="page" value="client-management">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" name="cm_search"
                               placeholder="Search by name or email…"
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <?php if ($search): ?>
                        <a href="index.php?page=client-management&lang=<?php echo $lang; ?>"
                           class="btn btn-outline-secondary nav-link-ajax" data-page="client-management">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-md-7">
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <?php foreach (['all'=>'All','repeat'=>'Repeat','new'=>'New','blocked'=>'Blocked'] as $f=>$lbl): ?>
                        <a href="index.php?page=client-management&lang=<?php echo $lang; ?>&cm_filter=<?php echo $f; ?><?php echo $search?"&cm_search=".urlencode($search):''; ?>"
                           class="btn <?php echo $filter===$f?'btn-primary':'btn-outline-primary'; ?> nav-link-ajax"
                           data-page="client-management">
                            <?php echo $lbl; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>

        <?php if (empty($clients)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No clients found</h6>
                <p class="text-muted small">Complete orders to build your client list.</p>
            </div>
        <?php else: ?>

        <div class="row g-3">
            <?php foreach ($clients as $c):
                $meta     = json_decode($c['meta_json'] ?? '{}', true) ?? [];
                $note     = $meta['note'] ?? '';
                $tag      = $meta['tag']  ?? '';
                $blocked  = !empty($c['is_blocked']);
                $tag_col  = $tag_colors[$tag] ?? 'secondary';
            ?>
            <div class="col-12">
                <div class="card cm-card <?php echo $blocked ? 'border-danger' : ''; ?>">
                    <div class="card-body py-2 px-3">
                        <div class="row align-items-center g-2">

                            <!-- Avatar + name -->
                            <div class="col-md-3 d-flex align-items-center gap-2">
                                <?php if (!empty($c['profile_image'])): ?>
                                    <img src="../../../<?php echo htmlspecialchars($c['profile_image']); ?>"
                                         class="rounded-circle flex-shrink-0"
                                         style="width:40px;height:40px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:40px;height:40px;font-size:.85rem;">
                                        <?php echo strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-width-0">
                                    <div class="fw-semibold" style="font-size:.88rem;">
                                        <?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?>
                                        <?php if ($blocked): ?>
                                            <span class="badge bg-danger ms-1" style="font-size:.65rem;">Blocked</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted text-truncate" style="font-size:.75rem;"><?php echo htmlspecialchars($c['email']); ?></div>
                                    <?php if ($tag): ?>
                                        <span class="badge bg-<?php echo $tag_col; ?>" style="font-size:.65rem;"><?php echo ucfirst($tag); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-3">
                                <div class="d-flex gap-3" style="font-size:.78rem;">
                                    <div>
                                        <div class="fw-bold text-primary"><?php echo (int)$c['total_orders']; ?></div>
                                        <div class="text-muted">Orders</div>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-success"><?php echo (int)$c['completed_orders']; ?></div>
                                        <div class="text-muted">Done</div>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-success">$<?php echo number_format($c['total_value'],0); ?></div>
                                        <div class="text-muted">Value</div>
                                    </div>
                                </div>
                                <div class="text-muted mt-1" style="font-size:.72rem;">
                                    Last: <?php echo date('M j, Y', strtotime($c['last_order'])); ?>
                                </div>
                            </div>

                            <!-- Note preview -->
                            <div class="col-md-3">
                                <?php if ($note): ?>
                                    <div class="text-muted small fst-italic text-truncate">
                                        <i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($note); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">No notes yet</span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="col-md-3 d-flex gap-1 justify-content-md-end flex-wrap">
                                <?php if ($c['unread_msgs'] > 0): ?>
                                <button class="btn btn-sm btn-danger position-relative"
                                        onclick="loadPage('provider-messages')" title="Messages">
                                    <i class="fas fa-comments"></i>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"
                                          style="font-size:.6rem;"><?php echo $c['unread_msgs']; ?></span>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="loadPage('provider-messages')" title="Message">
                                    <i class="fas fa-comments"></i>
                                </button>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-outline-secondary"
                                        onclick="openNoteModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['first_name'].' '.$c['last_name'])); ?>', '<?php echo htmlspecialchars(addslashes($note)); ?>', '<?php echo htmlspecialchars($tag); ?>')"
                                        title="Add Note">
                                    <i class="fas fa-sticky-note"></i>
                                </button>

                                <button class="btn btn-sm btn-outline-info"
                                        onclick="loadClientProfile(<?php echo $c['id']; ?>)"
                                        title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <?php if ($blocked): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="unblock">
                                    <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Unblock">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Block this client?')">
                                    <input type="hidden" name="action" value="block">
                                    <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Block">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pg > 1): ?>
        <nav class="mt-4">
            <ul class="pagination pagination-sm justify-content-center mb-1">
                <?php if ($pg > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="cmGoPage(<?php echo $pg-1; ?>);return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                <?php for ($i = max(1,$pg-2); $i <= min($total_pg,$pg+2); $i++): ?>
                <li class="page-item <?php echo $i===$pg?'active':''; ?>">
                    <a class="page-link" href="#" onclick="cmGoPage(<?php echo $i; ?>);return false;"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pg < $total_pg): ?>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="cmGoPage(<?php echo $pg+1; ?>);return false;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <p class="text-center text-muted small">
                Showing <?php echo min($offset+1,$total); ?>–<?php echo min($offset+$per_page,$total); ?> of <?php echo number_format($total); ?>
            </p>
        </nav>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<!-- Note / Tag Modal -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sticky-note me-2"></i>Client Notes — <span id="noteClientName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save_note">
                <input type="hidden" name="customer_id" id="noteCustomerId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tag</label>
                        <select class="form-select form-select-sm" name="tag" id="noteTag">
                            <option value="">— No tag —</option>
                            <option value="vip">⭐ VIP</option>
                            <option value="regular">🔵 Regular</option>
                            <option value="new">🟢 New</option>
                            <option value="difficult">🔴 Difficult</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Private Note</label>
                        <textarea class="form-control form-control-sm" name="note" id="noteText"
                                  rows="4" placeholder="Add a private note about this client…"></textarea>
                        <small class="text-muted">Only visible to you.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    const lang = '<?php echo $lang; ?>';

    window.openNoteModal = function(id, name, note, tag) {
        document.getElementById('noteCustomerId').value = id;
        document.getElementById('noteClientName').textContent = name;
        document.getElementById('noteText').value = note;
        document.getElementById('noteTag').value = tag;
        new bootstrap.Modal(document.getElementById('noteModal')).show();
    };

    window.loadClientProfile = function(id) {
        const params = 'page=clients&lang=' + lang + '&client_id=' + id;
        history.pushState({page:'clients',extra:{client_id:id}}, '', 'index.php?' + params);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + params, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    };

    window.cmGoPage = function(p) {
        const q = new URLSearchParams(location.search);
        q.set('page','client-management'); q.set('lang',lang); q.set('cm_page',p);
        history.pushState({}, '', 'index.php?' + q);
        const mc = document.getElementById('mainContent');
        mc.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + q, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.text()).then(html=>{
                mc.innerHTML = html;
                mc.querySelectorAll('script').forEach(s=>{const ns=document.createElement('script');ns.textContent=s.textContent;document.body.appendChild(ns);});
                if(typeof bindAjaxLinks==='function') bindAjaxLinks();
            });
    };

    // Fill repeat/new counts from current data
    const rows = document.querySelectorAll('.cm-card');
    let repeat = 0, newC = 0;
    rows.forEach(r => {
        const orders = parseInt(r.querySelector('.text-primary')?.textContent) || 0;
        if (orders > 1) repeat++;
        if (orders === 1) newC++;
    });
    const rc = document.getElementById('repeatCount');
    const nc = document.getElementById('newCount');
    if (rc) rc.textContent = repeat;
    if (nc) nc.textContent = newC;
})();
</script>
