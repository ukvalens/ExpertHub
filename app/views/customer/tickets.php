<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=tickets&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $ticket_number = 'TKT' . date('Ymd') . rand(1000, 9999);
    $subject  = trim($_POST['subject']);
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $message  = trim($_POST['message']);

    $stmt = $conn->prepare("INSERT INTO support_tickets (ticket_number, user_id, subject, category, priority, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
    $stmt->bind_param("sissss", $ticket_number, $user_id, $subject, $category, $priority, $message);
    if ($stmt->execute()) {
        $post_success = "Ticket #$ticket_number submitted successfully!";
    } else {
        $post_error = "Failed to submit ticket. Please try again.";
    }
}

// Handle close ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_ticket'])) {
    $tid = (int)$_POST['ticket_id'];
    $stmt = $conn->prepare("UPDATE support_tickets SET status='closed' WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $tid, $user_id);
    $stmt->execute();
}

// Filters
$status_filter = $_GET['status'] ?? 'all';
$page     = max(1, (int)($_GET['p'] ?? 1));
$per_page = 8;
$offset   = ($page - 1) * $per_page;

$where  = "WHERE st.user_id = ?";
$params = [$user_id];
$types  = 'i';
if ($status_filter !== 'all') {
    $where  .= " AND st.status = ?";
    $params[] = $status_filter;
    $types   .= 's';
}

// Counts per status
$cs = $conn->prepare("SELECT status, COUNT(*) as cnt FROM support_tickets WHERE user_id=? GROUP BY status"); // no join here, no ambiguity
$cs->bind_param("i", $user_id);
$cs->execute();
$counts = ['all' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($cs->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

// Total for pagination
$cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM support_tickets st LEFT JOIN users u ON st.assigned_to = u.id $where");
$cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total       = (int)$cnt_stmt->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total / $per_page));

// Fetch tickets
$fp = array_merge($params, [$per_page, $offset]);
$ft = $types . 'ii';
$stmt = $conn->prepare("SELECT st.*, u.first_name as responder_name
    FROM support_tickets st
    LEFT JOIN users u ON st.assigned_to = u.id
    $where ORDER BY st.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($ft, ...$fp);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_badge = ['open' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
$status_label = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$priority_badge = ['low' => 'secondary', 'medium' => 'primary', 'high' => 'warning', 'urgent' => 'danger'];
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-ticket-alt me-2" style="color:var(--accent-color)"></i>Support Tickets</span>
        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#newTicketModal">
            <i class="fas fa-plus me-1"></i>New Ticket
        </button>
    </div>
    <div class="card-body">

        <?php if (!empty($post_success)): ?>
            <div class="alert alert-success alert-dismissible fade show py-2">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($post_success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <?php foreach ([
                ['label' => 'Total',       'key' => 'all',         'icon' => 'fa-ticket-alt',   'color' => 'primary'],
                ['label' => 'Open',        'key' => 'open',        'icon' => 'fa-clock',        'color' => 'warning'],
                ['label' => 'In Progress', 'key' => 'in_progress', 'icon' => 'fa-spinner',      'color' => 'info'],
                ['label' => 'Resolved',    'key' => 'resolved',    'icon' => 'fa-check-circle', 'color' => 'success'],
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

        <!-- Filter tabs -->
        <div class="btn-group mb-3 flex-wrap" role="group">
            <?php foreach ([
                'all'         => ['label' => 'All',         'color' => 'secondary'],
                'open'        => ['label' => 'Open',        'color' => 'warning'],
                'in_progress' => ['label' => 'In Progress', 'color' => 'info'],
                'resolved'    => ['label' => 'Resolved',    'color' => 'success'],
                'closed'      => ['label' => 'Closed',      'color' => 'secondary'],
            ] as $key => $tab):
                $active = $status_filter === $key;
            ?>
            <a href="#" class="btn btn-sm <?php echo $active ? 'btn-'.$tab['color'] : 'btn-outline-'.$tab['color']; ?> nav-link-ajax"
               data-page="tickets" data-status="<?php echo $key; ?>">
                <?php echo $tab['label']; ?>
                <span class="badge bg-white text-dark ms-1"><?php echo $counts[$key] ?? 0; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Ticket list -->
        <?php if (empty($tickets)): ?>
            <div class="text-center py-5">
                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No tickets found</h6>
                <p class="text-muted small">
                    <?php echo $status_filter === 'all' ? "You haven't submitted any support tickets yet." : "No $status_filter tickets."; ?>
                </p>
                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                    <i class="fas fa-plus me-1"></i>Create Your First Ticket
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $ticket):
                $badge  = $status_badge[$ticket['status']]     ?? 'secondary';
                $slabel = $status_label[$ticket['status']]     ?? ucfirst($ticket['status']);
                $pbadge = $priority_badge[$ticket['priority']] ?? 'secondary';
            ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-start">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <strong class="text-muted small"><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                                <span class="badge bg-<?php echo $badge; ?>"><?php echo $slabel; ?></span>
                                <span class="badge bg-<?php echo $pbadge; ?>"><?php echo ucfirst($ticket['priority']); ?></span>
                                <span class="badge bg-light text-dark"><?php echo ucfirst($ticket['category']); ?></span>
                            </div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($ticket['subject']); ?></h6>
                            <p class="text-muted small mb-1">
                                <?php echo htmlspecialchars(mb_strimwidth($ticket['message'], 0, 120, '...')); ?>
                            </p>
                            <?php if ($ticket['provider_response']): ?>
                                <small class="text-success"><i class="fas fa-reply me-1"></i>Response received</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end mt-2 mt-md-0">
                            <div class="text-muted small mb-2">
                                <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                            </div>
                            <div class="d-flex gap-1 justify-content-md-end flex-wrap">
                                <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#viewTicket<?php echo $ticket['id']; ?>">
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
                                <?php if (in_array($ticket['status'], ['open', 'in_progress'])): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Close this ticket?')">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <button type="submit" name="close_ticket" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Close
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
            <nav><ul class="pagination justify-content-center mt-2">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="tickets" data-status="<?php echo $status_filter; ?>"
                        href="#">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="tickets" data-status="<?php echo $status_filter; ?>"
                           href="#"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="tickets" data-status="<?php echo $status_filter; ?>"
                        href="#">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <div class="text-center text-muted small">
                Showing <?php echo min($offset + 1, $total); ?>–<?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> tickets
            </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<!-- New Ticket Modal -->
<div class="modal fade" id="newTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ticket-alt me-2"></i>Create Support Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" class="form-control" name="subject" required placeholder="Brief description of your issue">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="technical">Technical Issue</option>
                                <option value="billing">Billing & Payment</option>
                                <option value="account">Account & Profile</option>
                                <option value="service">Service Related</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority *</label>
                            <select class="form-select" name="priority" required>
                                <option value="">Select Priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message *</label>
                        <textarea class="form-control" name="message" rows="5" required placeholder="Describe your issue in detail..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_ticket" class="btn btn-warning">
                        <i class="fas fa-paper-plane me-1"></i>Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Ticket Modals -->
<?php foreach ($tickets as $ticket):
    $badge  = $status_badge[$ticket['status']]     ?? 'secondary';
    $slabel = $status_label[$ticket['status']]     ?? ucfirst($ticket['status']);
    $pbadge = $priority_badge[$ticket['priority']] ?? 'secondary';
?>
<div class="modal fade" id="viewTicket<?php echo $ticket['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ticket-alt me-2"></i><?php echo htmlspecialchars($ticket['ticket_number']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-sm-6 mb-2">
                        <strong>Subject:</strong><br><?php echo htmlspecialchars($ticket['subject']); ?>
                    </div>
                    <div class="col-sm-6 mb-2">
                        <strong>Status:</strong>
                        <span class="badge bg-<?php echo $badge; ?> ms-1"><?php echo $slabel; ?></span>
                        <span class="badge bg-<?php echo $pbadge; ?> ms-1"><?php echo ucfirst($ticket['priority']); ?></span>
                    </div>
                    <div class="col-sm-6 mb-2"><strong>Category:</strong> <?php echo ucfirst($ticket['category']); ?></div>
                    <div class="col-sm-6 mb-2"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></div>
                </div>
                <div class="mb-3">
                    <strong>Your Message:</strong>
                    <div class="border rounded p-3 bg-light mt-1"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></div>
                </div>
                <?php if ($ticket['provider_response']): ?>
                    <div class="mb-3">
                        <strong>Support Response:</strong>
                        <?php if ($ticket['responder_name']): ?>
                            <small class="text-muted ms-1">by <?php echo htmlspecialchars($ticket['responder_name']); ?></small>
                        <?php endif; ?>
                        <div class="border rounded p-3 bg-success bg-opacity-10 mt-1">
                            <?php echo nl2br(htmlspecialchars($ticket['provider_response'])); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info py-2"><i class="fas fa-clock me-2"></i>Awaiting support team response...</div>
                <?php endif; ?>
                <?php if ($ticket['resolved_at']): ?>
                    <small class="text-success"><i class="fas fa-check me-1"></i>Resolved: <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <?php if (in_array($ticket['status'], ['open', 'in_progress'])): ?>
                <form method="POST" onsubmit="return confirm('Close this ticket?')">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    <button type="submit" name="close_ticket" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Close Ticket
                    </button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
