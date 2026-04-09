<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=admin-tickets&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$admin_id = $_SESSION['user_id'];
$lang     = $_GET['lang'] ?? 'en';

// --- AJAX actions ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $tid    = (int)($_POST['ticket_id'] ?? 0);

    if ($action === 'respond' && $tid) {
        $response   = trim($_POST['response'] ?? '');
        $new_status = $_POST['status'] ?? 'in_progress';
        if (!in_array($new_status, ['in_progress','resolved','closed'])) $new_status = 'in_progress';

        if ($new_status === 'resolved') {
            $s = $conn->prepare("UPDATE support_tickets SET status=?, assigned_to=?, provider_response=?, resolved_at=NOW(), updated_at=NOW() WHERE id=?");
            $s->bind_param("sisi", $new_status, $admin_id, $response, $tid);
        } else {
            $s = $conn->prepare("UPDATE support_tickets SET status=?, assigned_to=?, provider_response=?, updated_at=NOW() WHERE id=?");
            $s->bind_param("sisi", $new_status, $admin_id, $response, $tid);
        }
        echo json_encode(['ok' => $s->execute()]);

    } elseif ($action === 'update_status' && $tid) {
        $new_status = $_POST['status'];
        if (!in_array($new_status, ['open','in_progress','resolved','closed'])) { echo json_encode(['ok'=>false]); exit; }
        $extra = $new_status === 'resolved' ? ', resolved_at=NOW()' : '';
        $s = $conn->prepare("UPDATE support_tickets SET status=?, updated_at=NOW()$extra WHERE id=?");
        $s->bind_param("si", $new_status, $tid);
        echo json_encode(['ok' => $s->execute()]);

    } elseif ($action === 'delete' && $tid) {
        $s = $conn->prepare("DELETE FROM support_tickets WHERE id=?");
        $s->bind_param("i", $tid);
        echo json_encode(['ok' => $s->execute()]);
    }
    exit;
}

// --- Filters ---
$search        = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$page          = max(1, (int)($_GET['p'] ?? 1));
$per_page      = 12;
$offset        = ($page - 1) * $per_page;

// Counts
$sc = $conn->query("SELECT status, COUNT(*) as cnt FROM support_tickets GROUP BY status");
$counts = ['all' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($sc->fetch_all(MYSQLI_ASSOC) as $r) {
    $counts[$r['status']] = (int)$r['cnt'];
    $counts['all'] += (int)$r['cnt'];
}

// Build WHERE
$where  = "WHERE 1=1";
$params = [];
$types  = '';
if ($status_filter !== 'all') {
    $where   .= " AND st.status = ?";
    $params[] = $status_filter; $types .= 's';
}
if ($priority_filter !== 'all') {
    $where   .= " AND st.priority = ?";
    $params[] = $priority_filter; $types .= 's';
}
if ($search !== '') {
    $like     = "%$search%";
    $where   .= " AND (st.ticket_number LIKE ? OR st.subject LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params   = array_merge($params, [$like,$like,$like,$like,$like]);
    $types   .= 'sssss';
}

// Total
$cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM support_tickets st JOIN users u ON st.user_id=u.id $where");
if ($types) $cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total       = (int)$cnt_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$fp = array_merge($params, [$per_page, $offset]);
$ft = $types . 'ii';
$stmt = $conn->prepare("
    SELECT st.*,
           u.first_name, u.last_name, u.email, u.profile_image,
           a.first_name AS admin_first, a.last_name AS admin_last
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    LEFT JOIN users a ON st.assigned_to = a.id
    $where
    ORDER BY FIELD(st.status,'open','in_progress','resolved','closed'),
             FIELD(st.priority,'urgent','high','medium','low'),
             st.created_at DESC
    LIMIT ? OFFSET ?
");
if ($ft) $stmt->bind_param($ft, ...$fp);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_badge  = ['open'=>'warning','in_progress'=>'info','resolved'=>'success','closed'=>'secondary'];
$status_label  = ['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'];
$priority_badge = ['low'=>'secondary','medium'=>'primary','high'=>'warning','urgent'=>'danger'];
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-ticket-alt me-2" style="color:var(--accent-color)"></i>Support Tickets</span>
        <span class="badge bg-primary"><?php echo number_format($counts['all']); ?> total</span>
    </div>
    <div class="card-body">

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <?php foreach ([
                ['label'=>'Total',       'key'=>'all',         'icon'=>'fa-ticket-alt',  'color'=>'primary'],
                ['label'=>'Open',        'key'=>'open',        'icon'=>'fa-clock',       'color'=>'warning'],
                ['label'=>'In Progress', 'key'=>'in_progress', 'icon'=>'fa-spinner',     'color'=>'info'],
                ['label'=>'Resolved',    'key'=>'resolved',    'icon'=>'fa-check-circle','color'=>'success'],
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

        <!-- Search + filters -->
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <div class="input-group input-group-sm" style="max-width:280px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="ticketSearch" placeholder="Search ticket, subject, user…"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="btn-group btn-group-sm flex-wrap">
                <?php foreach (['all'=>['All','secondary'],'open'=>['Open','warning'],'in_progress'=>['In Progress','info'],'resolved'=>['Resolved','success'],'closed'=>['Closed','secondary']] as $k=>[$l,$c]):
                    $act = $status_filter === $k; ?>
                <a href="?page=admin-tickets&status=<?php echo $k; ?>&priority=<?php echo $priority_filter; ?>&q=<?php echo urlencode($search); ?>&lang=<?php echo $lang; ?>"
                   class="btn btn-sm nav-link-ajax <?php echo $act?"btn-$c":"btn-outline-$c"; ?>"
                   data-page="admin-tickets" data-status="<?php echo $k; ?>">
                    <?php echo $l; ?> <span class="badge bg-white text-dark ms-1"><?php echo $counts[$k]??0; ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="btn-group btn-group-sm">
                <?php foreach (['all'=>'All Priority','urgent'=>'Urgent','high'=>'High','medium'=>'Medium','low'=>'Low'] as $k=>$l):
                    $act = $priority_filter === $k; ?>
                <a href="?page=admin-tickets&status=<?php echo $status_filter; ?>&priority=<?php echo $k; ?>&q=<?php echo urlencode($search); ?>&lang=<?php echo $lang; ?>"
                   class="btn btn-sm nav-link-ajax <?php echo $act?'btn-dark':'btn-outline-secondary'; ?>"
                   data-page="admin-tickets">
                    <?php echo $l; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Table -->
        <?php if (empty($tickets)): ?>
            <div class="text-center py-5">
                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No tickets found</h6>
                <p class="text-muted small"><?php echo $search ? "No results for \"$search\"." : "No tickets in this category."; ?></p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle" id="ticketsTable">
                <thead class="table-light">
                    <tr>
                        <th>Ticket</th>
                        <th>User</th>
                        <th>Subject</th>
                        <th class="text-center">Priority</th>
                        <th class="text-center">Status</th>
                        <th>Created</th>
                        <th>Assigned</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t):
                        $sbadge = $status_badge[$t['status']]    ?? 'secondary';
                        $slabel = $status_label[$t['status']]    ?? ucfirst($t['status']);
                        $pbadge = $priority_badge[$t['priority']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold small"><?php echo htmlspecialchars($t['ticket_number']); ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?php echo ucfirst($t['category']); ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($t['profile_image']): ?>
                                    <img src="../../../<?php echo htmlspecialchars($t['profile_image']); ?>"
                                         class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:28px;height:28px;font-size:.65rem;font-weight:700;">
                                        <?php echo strtoupper(substr($t['first_name'],0,1).substr($t['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="small fw-semibold"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></div>
                                    <div class="text-muted" style="font-size:.68rem;"><?php echo htmlspecialchars($t['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="max-width:200px;">
                            <div class="small fw-semibold text-truncate"><?php echo htmlspecialchars($t['subject']); ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?php echo htmlspecialchars(mb_strimwidth($t['message'],0,60,'…')); ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $pbadge; ?>"><?php echo ucfirst($t['priority']); ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $sbadge; ?>"><?php echo $slabel; ?></span>
                            <?php if ($t['provider_response']): ?>
                                <div style="font-size:.65rem;" class="text-success mt-1"><i class="fas fa-reply me-1"></i>Replied</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="small text-muted"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></div>
                        </td>
                        <td>
                            <?php if ($t['admin_first']): ?>
                                <div class="small text-muted"><?php echo htmlspecialchars($t['admin_first'].' '.$t['admin_last']); ?></div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="viewTicket(<?php echo htmlspecialchars(json_encode($t), ENT_QUOTES); ?>)"
                                        title="View & Respond">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($t['status'] !== 'in_progress'): ?>
                                        <li><a class="dropdown-item text-info" href="#"
                                               onclick="quickStatus(<?php echo $t['id']; ?>,'in_progress');return false;">
                                            <i class="fas fa-play me-2"></i>Mark In Progress</a></li>
                                        <?php endif; ?>
                                        <?php if ($t['status'] !== 'resolved'): ?>
                                        <li><a class="dropdown-item text-success" href="#"
                                               onclick="quickStatus(<?php echo $t['id']; ?>,'resolved');return false;">
                                            <i class="fas fa-check me-2"></i>Mark Resolved</a></li>
                                        <?php endif; ?>
                                        <?php if ($t['status'] !== 'closed'): ?>
                                        <li><a class="dropdown-item text-secondary" href="#"
                                               onclick="quickStatus(<?php echo $t['id']; ?>,'closed');return false;">
                                            <i class="fas fa-times me-2"></i>Close Ticket</a></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#"
                                               onclick="deleteTicket(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['ticket_number'], ENT_QUOTES); ?>');return false;">
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
                    <a class="page-link nav-link-ajax" data-page="admin-tickets" data-status="<?php echo $status_filter; ?>"
                       href="?page=admin-tickets&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&q=<?php echo urlencode($search); ?>&p=<?php echo $page-1; ?>&lang=<?php echo $lang; ?>">Prev</a>
                </li>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link nav-link-ajax" data-page="admin-tickets" data-status="<?php echo $status_filter; ?>"
                       href="?page=admin-tickets&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&q=<?php echo urlencode($search); ?>&p=<?php echo $i; ?>&lang=<?php echo $lang; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link nav-link-ajax" data-page="admin-tickets" data-status="<?php echo $status_filter; ?>"
                       href="?page=admin-tickets&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&q=<?php echo urlencode($search); ?>&p=<?php echo $page+1; ?>&lang=<?php echo $lang; ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul></nav>
        <div class="text-center text-muted small">
            Showing <?php echo min($offset+1,$total); ?>–<?php echo min($offset+$per_page,$total); ?> of <?php echo $total; ?> tickets
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<!-- View & Respond Modal -->
<div class="modal fade" id="viewTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ticket-alt me-2"></i><span id="vtNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3" id="vtMeta"></div>
                <div class="mb-3">
                    <strong class="small">User Message:</strong>
                    <div class="border rounded p-3 bg-light mt-1 small" id="vtMessage"></div>
                </div>
                <div id="vtResponseBlock" class="mb-3"></div>

                <!-- Reply form -->
                <div id="vtReplyForm">
                    <label class="form-label small fw-semibold">Reply to User</label>
                    <textarea class="form-control form-control-sm mb-2" id="vtReply" rows="4"
                              placeholder="Type your response…"></textarea>
                    <div class="d-flex gap-2 flex-wrap">
                        <select class="form-select form-select-sm" id="vtNewStatus" style="max-width:160px;">
                            <option value="in_progress">Mark In Progress</option>
                            <option value="resolved">Mark Resolved</option>
                            <option value="closed">Close Ticket</option>
                        </select>
                        <button class="btn btn-primary btn-sm" onclick="submitReply()">
                            <i class="fas fa-paper-plane me-1"></i>Send Response
                        </button>
                    </div>
                    <div id="vtAlert" class="mt-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const base = 'index.php?page=admin-tickets&lang=<?php echo $lang; ?>';
    let currentTicketId = null;

    function post(data) {
        return fetch(base, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        }).then(r => r.json());
    }

    function reload() { if (typeof loadPage === 'function') loadPage('admin-tickets', false); }

    window.viewTicket = function(t) {
        currentTicketId = t.id;
        const sbadge  = {open:'warning',in_progress:'info',resolved:'success',closed:'secondary'};
        const slabel  = {open:'Open',in_progress:'In Progress',resolved:'Resolved',closed:'Closed'};
        const pbadge  = {low:'secondary',medium:'primary',high:'warning',urgent:'danger'};

        document.getElementById('vtNumber').textContent = t.ticket_number;
        document.getElementById('vtMeta').innerHTML = `
            <div class="col-sm-6 small"><strong>User:</strong> ${t.first_name} ${t.last_name}<br>
                <span class="text-muted">${t.email}</span></div>
            <div class="col-sm-3 small"><strong>Status:</strong><br>
                <span class="badge bg-${sbadge[t.status]||'secondary'}">${slabel[t.status]||t.status}</span></div>
            <div class="col-sm-3 small"><strong>Priority:</strong><br>
                <span class="badge bg-${pbadge[t.priority]||'secondary'}">${t.priority}</span></div>
            <div class="col-sm-6 small"><strong>Category:</strong> ${t.category}</div>
            <div class="col-sm-6 small"><strong>Created:</strong> ${new Date(t.created_at).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'})}</div>`;

        document.getElementById('vtMessage').textContent = t.message;

        document.getElementById('vtResponseBlock').innerHTML = t.provider_response
            ? `<strong class="small">Previous Response:</strong>
               <div class="border rounded p-3 bg-success bg-opacity-10 mt-1 small">${t.provider_response}</div>`
            : '';

        document.getElementById('vtReply').value = '';
        document.getElementById('vtAlert').innerHTML = '';
        document.getElementById('vtNewStatus').value = t.status === 'open' ? 'in_progress' : 'resolved';

        // Hide reply form for closed/resolved tickets
        document.getElementById('vtReplyForm').style.display =
            ['closed','resolved'].includes(t.status) ? 'none' : '';

        new bootstrap.Modal(document.getElementById('viewTicketModal')).show();
    };

    window.submitReply = function() {
        const reply  = document.getElementById('vtReply').value.trim();
        const status = document.getElementById('vtNewStatus').value;
        if (!reply) {
            document.getElementById('vtAlert').innerHTML =
                '<div class="alert alert-danger py-1 small">Please enter a response.</div>';
            return;
        }
        post({ action:'respond', ticket_id: currentTicketId, response: reply, status })
            .then(d => {
                if (d.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('viewTicketModal'))?.hide();
                    reload();
                } else {
                    document.getElementById('vtAlert').innerHTML =
                        '<div class="alert alert-danger py-1 small">Failed to send response.</div>';
                }
            });
    };

    window.quickStatus = function(id, status) {
        const labels = { in_progress:'mark as In Progress', resolved:'mark as Resolved', closed:'close' };
        if (!confirm(`Are you sure you want to ${labels[status]} this ticket?`)) return;
        post({ action:'update_status', ticket_id: id, status }).then(d => { if (d.ok) reload(); });
    };

    window.deleteTicket = function(id, num) {
        if (!confirm(`Permanently delete ticket ${num}? This cannot be undone.`)) return;
        post({ action:'delete', ticket_id: id }).then(d => { if (d.ok) reload(); });
    };

    // Client-side search filter
    document.getElementById('ticketSearch')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#ticketsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
})();
</script>
