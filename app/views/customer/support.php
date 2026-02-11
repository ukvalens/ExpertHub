<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['ticket'])) {
    $success_message = "Support ticket #{$_GET['ticket']} created successfully!";
}

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$per_page = 5;
$offset = ($page - 1) * $per_page;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM support_tickets WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_tickets = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_tickets / $per_page);

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = $_POST['subject'];
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $message = $_POST['message'];
    
    $ticket_number = 'TKT' . date('Ymd') . rand(1000, 9999);
    
    $stmt = $conn->prepare("INSERT INTO support_tickets (ticket_number, user_id, subject, category, priority, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
    $stmt->bind_param("sissss", $ticket_number, $user_id, $subject, $category, $priority, $message);
    
    if ($stmt->execute()) {
        $success_message = "Support ticket #$ticket_number created successfully!";
        // Redirect to prevent duplicate submission on reload
        header("Location: support.php?success=1&ticket=$ticket_number&lang=" . ($_GET['lang'] ?? 'en'));
        exit();
    } else {
        $error_message = "Failed to create support ticket. Please try again.";
    }
}

// Get user's support tickets with provider responses
$stmt = $conn->prepare("SELECT st.*, pu.first_name as provider_name 
                       FROM support_tickets st 
                       LEFT JOIN users pu ON st.assigned_to = pu.id 
                       WHERE st.user_id = ? 
                       ORDER BY st.created_at DESC 
                       LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $per_page, $offset);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">
                <i class="fas fa-users-cog me-2"></i>ExpertHub
            </a>
            <div class="navbar-nav mx-auto">
                <a class="nav-link" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Home</a>
                <a class="nav-link" href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Browse Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Orders</a>
                <a class="nav-link active" href="support.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Support</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user me-1"></i>Customer
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-danger" href="../../../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-headset me-2"></i>Customer Support</h3>
                        <p class="mb-0">Get help with your ExpertHub experience</p>
                    </div>
                    <div class="p-4">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Help Section -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-question-circle fa-3x text-primary mb-3"></i>
                                        <h5>FAQ</h5>
                                        <p class="text-muted">Find answers to common questions</p>
                                        <button class="btn btn-outline-primary" onclick="showFAQ()">View FAQ</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-comments fa-3x text-success mb-3"></i>
                                        <h5>Live Chat</h5>
                                        <p class="text-muted">Chat with our support team</p>
                                        <button class="btn btn-outline-success" onclick="window.location.href='messages.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>'">Start Chat</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-ticket-alt fa-3x text-warning mb-3"></i>
                                        <h5>Support Ticket</h5>
                                        <p class="text-muted">Submit a detailed support request</p>
                                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#ticketModal">Create Ticket</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Tickets -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-history me-2"></i>Your Recent Tickets</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($tickets)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                        <h6>No Support Tickets</h6>
                                        <p class="text-muted">You haven't created any support tickets yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Ticket #</th>
                                                    <th>Subject</th>
                                                    <th>Category</th>
                                                    <th>Status</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tickets as $ticket): ?>
                                                    <tr>
                                                        <td><strong><?php echo $ticket['ticket_number']; ?></strong></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($ticket['subject']); ?>
                                                            <?php if ($ticket['provider_response']): ?>
                                                                <br><small class="text-success"><i class="fas fa-reply"></i> Response received</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><span class="badge bg-secondary"><?php echo ucfirst($ticket['category']); ?></span></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $ticket['status'] === 'resolved' ? 'success' : 
                                                                    ($ticket['status'] === 'in_progress' ? 'info' : 
                                                                    ($ticket['status'] === 'open' ? 'warning' : 'secondary')); 
                                                            ?>">
                                                                <?php 
                                                                    if ($ticket['status'] === 'open') echo 'Pending';
                                                                    elseif ($ticket['status'] === 'in_progress') echo 'In Progress';
                                                                    elseif ($ticket['status'] === 'resolved') echo 'Resolved';
                                                                    else echo ucfirst($ticket['status']);
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewTicket<?php echo $ticket['id']; ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Support tickets pagination" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="support.php?page=<?php echo $page-1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="support.php?page=<?php echo $i; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="support.php?page=<?php echo $page+1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            
                            <div class="text-center text-muted">
                                Showing <?php echo min($offset + 1, $total_tickets); ?>-<?php echo min($offset + $per_page, $total_tickets); ?> of <?php echo $total_tickets; ?> tickets
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Ticket Modal -->
    <div class="modal fade" id="ticketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ticket-alt me-2"></i>Create Support Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" name="subject" required placeholder="Brief description of your issue">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category *</label>
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
                                <label for="priority" class="form-label">Priority *</label>
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
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" name="message" rows="5" required placeholder="Please describe your issue in detail..."></textarea>
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

    <!-- FAQ Modal -->
    <div class="modal fade" id="faqModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I place an order?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Browse services, select a provider, fill out your requirements, and proceed to payment. The provider will be notified and can accept your request.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How do I communicate with providers?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Once your order is accepted, you can use the messaging system to chat with providers. You can also send voice notes and make video calls.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    What payment methods are accepted?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We accept Mobile Money (MTN MoMo, Airtel Money) for secure and convenient payments.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../../includes/footer.php'; ?>
    
    <!-- Ticket Detail Modals -->
    <?php foreach ($tickets as $ticket): ?>
        <div class="modal fade" id="viewTicket<?php echo $ticket['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-ticket-alt me-2"></i>Ticket #<?php echo $ticket['ticket_number']; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Subject:</strong><br>
                                <?php echo htmlspecialchars($ticket['subject']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?php 
                                    echo $ticket['status'] === 'resolved' ? 'success' : 
                                        ($ticket['status'] === 'in_progress' ? 'info' : 
                                        ($ticket['status'] === 'open' ? 'warning' : 'secondary')); 
                                ?>">
                                    <?php 
                                        if ($ticket['status'] === 'open') echo 'Pending';
                                        elseif ($ticket['status'] === 'in_progress') echo 'In Progress';
                                        elseif ($ticket['status'] === 'resolved') echo 'Resolved';
                                        else echo ucfirst($ticket['status']);
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong>Your Message:</strong><br>
                            <div class="border p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                            </div>
                        </div>
                        <?php if ($ticket['provider_response']): ?>
                            <div class="mb-3">
                                <strong>Support Response:</strong>
                                <?php if ($ticket['provider_name']): ?>
                                    <small class="text-muted">by <?php echo htmlspecialchars($ticket['provider_name']); ?></small>
                                <?php endif; ?>
                                <div class="border p-3 bg-success bg-opacity-10 rounded">
                                    <?php echo nl2br(htmlspecialchars($ticket['provider_response'])); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-clock me-2"></i>Waiting for support team response...
                            </div>
                        <?php endif; ?>
                        <div class="text-muted">
                            <small>Created: <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></small>
                            <?php if ($ticket['resolved_at']): ?>
                                <br><small class="text-success">Resolved: <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showFAQ() {
            new bootstrap.Modal(document.getElementById('faqModal')).show();
        }
    </script>
</body>
</html>