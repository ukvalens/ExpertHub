<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$per_page = 5;
$offset = ($page - 1) * $per_page;

// Get total count for customer tickets
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM support_tickets st 
                             JOIN users u ON st.user_id = u.id 
                             WHERE u.user_type = 'customer'");
$count_stmt->execute();
$total_customer_tickets = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_customer_tickets / $per_page);

// Handle ticket status updates and responses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $new_status = $_POST['new_status'];
    $response = $_POST['response'] ?? null;
    
    if ($response) {
        if ($new_status === 'resolved') {
            $stmt = $conn->prepare("UPDATE support_tickets SET status = ?, assigned_to = ?, provider_response = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sisi", $new_status, $user_id, $response, $ticket_id);
        } else {
            $stmt = $conn->prepare("UPDATE support_tickets SET status = ?, assigned_to = ?, provider_response = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sisi", $new_status, $user_id, $response, $ticket_id);
        }
    } else {
        if ($new_status === 'resolved') {
            $stmt = $conn->prepare("UPDATE support_tickets SET status = ?, assigned_to = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sii", $new_status, $user_id, $ticket_id);
        } else {
            $stmt = $conn->prepare("UPDATE support_tickets SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sii", $new_status, $user_id, $ticket_id);
        }
    }
    
    if ($stmt->execute()) {
        // Send email notification to customer
        $stmt_customer = $conn->prepare("SELECT u.email, u.first_name, u.last_name, st.ticket_number, st.subject 
                                        FROM support_tickets st 
                                        JOIN users u ON st.user_id = u.id 
                                        WHERE st.id = ?");
        $stmt_customer->bind_param("i", $ticket_id);
        $stmt_customer->execute();
        $customer_info = $stmt_customer->get_result()->fetch_assoc();
        
        if ($customer_info) {
            $to = $customer_info['email'];
            $subject = "Support Ticket Update - #{$customer_info['ticket_number']}";
            $status_text = $new_status === 'resolved' ? 'resolved' : 'updated';
            
            $message = "Dear {$customer_info['first_name']},\n\n";
            $message .= "Your support ticket #{$customer_info['ticket_number']} has been {$status_text}.\n\n";
            $message .= "Subject: {$customer_info['subject']}\n\n";
            
            if ($response) {
                $message .= "Support Response:\n{$response}\n\n";
            }
            
            $message .= "You can view your ticket details by logging into your ExpertHub account.\n\n";
            $message .= "Best regards,\nExpertHub Support Team";
            
            $headers = "From: support@experthub.com\r\n";
            $headers .= "Reply-To: support@experthub.com\r\n";
            
            mail($to, $subject, $message, $headers);
        }
        
        $success_message = "Ticket updated successfully!";
    } else {
        $error_message = "Failed to update ticket.";
    }
    
    header("Location: support.php?lang=" . ($_GET['lang'] ?? 'en'));
    exit();
}

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
    } else {
        $error_message = "Failed to create support ticket. Please try again.";
    }
}

// Get total count for provider's own tickets
$my_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM support_tickets WHERE user_id = ?");
$my_count_stmt->bind_param("i", $user_id);
$my_count_stmt->execute();
$total_my_tickets = $my_count_stmt->get_result()->fetch_assoc()['total'];
$my_total_pages = ceil($total_my_tickets / $per_page);

// Get user's support tickets (provider's own tickets) with pagination
$stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $per_page, $offset);
$stmt->execute();
$my_tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all customer support tickets for providers to view
$stmt = $conn->prepare("SELECT st.*, u.first_name, u.last_name, u.email,
                       CASE WHEN st.assigned_to = ? THEN 1 ELSE 0 END as is_assigned_to_me,
                       pu.first_name as provider_name
                       FROM support_tickets st 
                       JOIN users u ON st.user_id = u.id 
                       LEFT JOIN users pu ON st.assigned_to = pu.id
                       WHERE u.user_type = 'customer' 
                       ORDER BY st.status = 'open' DESC, st.priority = 'urgent' DESC, st.created_at DESC 
                       LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $per_page, $offset);
$stmt->execute();
$customer_tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - ExpertHub Provider</title>
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
                <a class="nav-link" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Dashboard</a>
                <a class="nav-link" href="my-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Orders</a>
                <a class="nav-link active" href="support.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Support</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-tie me-1"></i>Provider
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="profile.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">
                        <i class="fas fa-user me-2"></i>Profile</a></li>
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
                        <h3><i class="fas fa-headset me-2"></i>Provider Support</h3>
                        <p class="mb-0">Get help with your ExpertHub provider experience</p>
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
                                        <h5>Provider FAQ</h5>
                                        <p class="text-muted">Find answers to provider-specific questions</p>
                                        <button class="btn btn-outline-primary" onclick="showFAQ()">View FAQ</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <i class="fas fa-comments fa-3x text-success mb-3"></i>
                                        <h5>Live Chat</h5>
                                        <p class="text-muted">Chat with our provider support team</p>
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

                        <!-- Customer Support Tickets -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-users me-2"></i>Customer Support Tickets</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($customer_tickets)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                        <h6>No Customer Tickets</h6>
                                        <p class="text-muted">No customer support tickets at the moment.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Ticket #</th>
                                                    <th>Customer</th>
                                                    <th>Subject</th>
                                                    <th>Category</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($customer_tickets as $ticket): ?>
                                                    <tr class="<?php echo $ticket['is_assigned_to_me'] ? 'table-info' : ''; ?>">
                                                        <td><strong><?php echo $ticket['ticket_number']; ?></strong></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                                                            <?php if ($ticket['is_assigned_to_me']): ?>
                                                                <br><small class="text-info"><i class="fas fa-user-check"></i> Assigned to you</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($ticket['subject']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($ticket['message'], 0, 50)) . '...'; ?></small>
                                                        </td>
                                                        <td><span class="badge bg-secondary"><?php echo ucfirst($ticket['category']); ?></span></td>
                                                        <td><span class="badge bg-<?php echo $ticket['priority'] === 'urgent' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : 'info'); ?>"><?php echo ucfirst($ticket['priority']); ?></span></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $ticket['status'] === 'open' ? 'warning' : ($ticket['status'] === 'closed' ? 'success' : 'info'); ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                                        <td>
                                                            <div class="btn-group-vertical btn-group-sm">
                                                                <?php if ($ticket['status'] === 'open'): ?>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                        <input type="hidden" name="new_status" value="in_progress">
                                                                        <button type="submit" name="update_ticket" class="btn btn-warning btn-sm">
                                                                            <i class="fas fa-play"></i> Take
                                                                        </button>
                                                                    </form>
                                                                <?php elseif ($ticket['status'] === 'in_progress' && $ticket['is_assigned_to_me']): ?>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                        <input type="hidden" name="new_status" value="resolved">
                                                                        <button type="submit" name="update_ticket" class="btn btn-success btn-sm">
                                                                            <i class="fas fa-check"></i> Resolve
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#ticketModal<?php echo $ticket['id']; ?>">
                                                                    <i class="fas fa-eye"></i> View
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- My Tickets -->
                        <!-- My Tickets -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-history me-2"></i>My Support Tickets</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($my_tickets)): ?>
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($my_tickets as $ticket): ?>
                                                    <tr>
                                                        <td><strong><?php echo $ticket['ticket_number']; ?></strong></td>
                                                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                                        <td><span class="badge bg-secondary"><?php echo ucfirst($ticket['category']); ?></span></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $ticket['status'] === 'open' ? 'warning' : ($ticket['status'] === 'closed' ? 'success' : 'info'); ?>">
                                                                <?php echo ucfirst($ticket['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination for My Tickets -->
                                    <?php if ($my_total_pages > 1): ?>
                                        <nav aria-label="My tickets pagination" class="mt-3">
                                            <ul class="pagination justify-content-center pagination-sm">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="support.php?page=<?php echo $page-1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Previous</a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $my_total_pages; $i++): ?>
                                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="support.php?page=<?php echo $i; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $my_total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="support.php?page=<?php echo $page+1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Next</a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                        
                                        <div class="text-center text-muted small">
                                            Showing <?php echo min($offset + 1, $total_my_tickets); ?>-<?php echo min($offset + $per_page, $total_my_tickets); ?> of <?php echo $total_my_tickets; ?> my tickets
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
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
                                    <option value="billing">Payments & Earnings</option>
                                    <option value="account">Account & Profile</option>
                                    <option value="service">Service Management</option>
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
                    <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i>Provider FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I manage my services?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Go to "My Services" to create, edit, or deactivate your service offerings. Make sure to provide detailed descriptions and competitive pricing.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    When do I get paid?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Payments are released after successful order completion and customer approval. Check your earnings in the "Earnings" section.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    How do I improve my rating?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Provide excellent service, communicate clearly with customers, deliver on time, and maintain professional standards throughout the service process.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../../includes/footer.php'; ?>
    
    <!-- Customer Ticket Detail Modals -->
    <?php foreach ($customer_tickets as $ticket): ?>
        <div class="modal fade" id="ticketModal<?php echo $ticket['id']; ?>" tabindex="-1">
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
                                <strong>Customer:</strong> <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?><br>
                                <strong>Email:</strong> <?php echo htmlspecialchars($ticket['email']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Category:</strong> <span class="badge bg-secondary"><?php echo ucfirst($ticket['category']); ?></span><br>
                                <strong>Priority:</strong> <span class="badge bg-<?php echo $ticket['priority'] === 'urgent' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : 'info'); ?>"><?php echo ucfirst($ticket['priority']); ?></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong>Subject:</strong><br>
                            <?php echo htmlspecialchars($ticket['subject']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Message:</strong><br>
                            <div class="border p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                            </div>
                        </div>
                        <?php if ($ticket['provider_response']): ?>
                            <div class="mb-3">
                                <strong>Provider Response:</strong><br>
                                <div class="border p-3 bg-success bg-opacity-10 rounded">
                                    <?php echo nl2br(htmlspecialchars($ticket['provider_response'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?php echo $ticket['status'] === 'open' ? 'warning' : ($ticket['status'] === 'closed' ? 'success' : 'info'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <?php if ($ticket['status'] === 'open'): ?>
                            <form method="POST" class="w-100">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <input type="hidden" name="new_status" value="in_progress">
                                <div class="mb-3">
                                    <label class="form-label">Response to Customer:</label>
                                    <textarea class="form-control" name="response" rows="3" placeholder="Provide a response to the customer..." required></textarea>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="update_ticket" class="btn btn-warning">
                                        <i class="fas fa-play me-1"></i>Take & Respond
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($ticket['status'] === 'in_progress' && $ticket['is_assigned_to_me']): ?>
                            <form method="POST" class="w-100">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <input type="hidden" name="new_status" value="resolved">
                                <?php if (!$ticket['provider_response']): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Response to Customer:</label>
                                        <textarea class="form-control" name="response" rows="3" placeholder="Provide a response before resolving..." required></textarea>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <label class="form-label">Additional Response (Optional):</label>
                                        <textarea class="form-control" name="response" rows="3" placeholder="Add additional response if needed..."></textarea>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="update_ticket" class="btn btn-success">
                                        <i class="fas fa-check me-1"></i>Mark Resolved
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <?php endif; ?>
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