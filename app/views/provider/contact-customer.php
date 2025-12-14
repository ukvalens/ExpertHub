<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../config/email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $email_subject = $_POST['email_subject'];
    $email_message = $_POST['email_message'];
    $order_id = $_POST['order_id'];
    
    // Get order details for email
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_data = $stmt->get_result()->fetch_assoc();
    
    if ($order_data && sendCustomEmail($order_data['email'], $order_data['first_name'], $email_subject, $email_message)) {
        $success_message = "Email sent successfully to " . $order_data['first_name'] . "!";
    } else {
        $error_message = "Failed to send email. Please check your email configuration.";
    }
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Get provider ID
$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

// Get order and customer details
$stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
                       FROM orders o 
                       JOIN users u ON o.customer_id = u.id 
                       WHERE o.id = ? AND o.provider_id = ?");
$stmt->bind_param("ii", $order_id, $provider['id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

$requirements = json_decode($order['customer_requirements'], true);

// Get provider name
$stmt = $conn->prepare("SELECT u.first_name, u.last_name FROM users u JOIN service_providers sp ON u.id = sp.user_id WHERE sp.id = ?");
$stmt->bind_param("i", $provider['id']);
$stmt->execute();
$provider_info = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Customer - ExpertHub</title>
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
                <a class="nav-link" href="services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Orders</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-tie me-1"></i>Provider
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-danger" href="../../../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-comments me-2"></i>Contact Customer</h3>
                        <p class="mb-0">Order #<?php echo $order['order_number']; ?></p>
                    </div>
                    <div class="p-4">
                        <!-- Success/Error Messages -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Customer Info -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h6 class="card-title">Customer Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><i class="fas fa-user me-2"></i><strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong></p>
                                        <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($order['email']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($requirements && isset($requirements['phone'])): ?>
                                            <p><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($requirements['phone']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($requirements && isset($requirements['address'])): ?>
                                            <p><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($requirements['address']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Options -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-phone fa-3x text-primary mb-3"></i>
                                        <h5>Call Customer</h5>
                                        <p class="text-muted">Direct phone call for immediate discussion</p>
                                        <?php if ($requirements && isset($requirements['phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($requirements['phone']); ?>" class="btn btn-primary">
                                                <i class="fas fa-phone me-1"></i>Call Now
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" disabled>No Phone Available</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-envelope fa-3x text-success mb-3"></i>
                                        <h5>Send Email</h5>
                                        <p class="text-muted">Send detailed message via email</p>
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#emailModal">
                                            <i class="fas fa-envelope me-1"></i>Send Email
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Message Templates -->
                        <div class="card mt-4">
                            <div class="card-body">
                                <h6 class="card-title">Quick Message Templates</h6>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-primary btn-sm w-100" onclick="copyTemplate('start')">
                                            <i class="fas fa-play me-1"></i>Starting Work
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-warning btn-sm w-100" onclick="copyTemplate('clarification')">
                                            <i class="fas fa-question me-1"></i>Need Clarification
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-success btn-sm w-100" onclick="copyTemplate('complete')">
                                            <i class="fas fa-check me-1"></i>Work Complete
                                        </button>
                                    </div>
                                </div>
                                
                                <textarea id="messageTemplate" class="form-control mt-3" rows="4" readonly placeholder="Click a template button above to see the message"></textarea>
                                <button id="copyBtn" class="btn btn-secondary btn-sm mt-2" onclick="copyMessage()" style="display:none;">
                                    <i class="fas fa-copy me-1"></i>Copy Message
                                </button>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <a href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Email Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Send Email to <?php echo htmlspecialchars($order['first_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="email_subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject" 
                                   value="Regarding Order #<?php echo $order['order_number']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_message" class="form-label">Message</label>
                            <textarea class="form-control" id="email_message" name="email_message" rows="6" required 
                                      placeholder="Type your message here..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This email will be sent to: <strong><?php echo htmlspecialchars($order['email']); ?></strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_email" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i>Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const templates = {
            start: `Hi <?php echo $order['first_name']; ?>,\n\nI'm starting work on your order #<?php echo $order['order_number']; ?>. I'll keep you updated on the progress.\n\nBest regards,\n<?php echo htmlspecialchars($provider_info['first_name'] . ' ' . $provider_info['last_name']); ?>`,
            clarification: `Hi <?php echo $order['first_name']; ?>,\n\nI need some clarification regarding your order #<?php echo $order['order_number']; ?>. Could you please provide more details about your requirements?\n\nBest regards,\n<?php echo htmlspecialchars($provider_info['first_name'] . ' ' . $provider_info['last_name']); ?>`,
            complete: `Hi <?php echo $order['first_name']; ?>,\n\nI've completed the work for your order #<?php echo $order['order_number']; ?>. Please review and let me know if you need any adjustments.\n\nBest regards,\n<?php echo htmlspecialchars($provider_info['first_name'] . ' ' . $provider_info['last_name']); ?>`
        };

        function copyTemplate(type) {
            const textarea = document.getElementById('messageTemplate');
            const emailTextarea = document.getElementById('email_message');
            const copyBtn = document.getElementById('copyBtn');
            textarea.value = templates[type];
            if (emailTextarea) emailTextarea.value = templates[type];
            copyBtn.style.display = 'inline-block';
        }

        function copyMessage() {
            const textarea = document.getElementById('messageTemplate');
            textarea.select();
            document.execCommand('copy');
            
            const btn = document.getElementById('copyBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-secondary');
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.add('btn-secondary');
                btn.classList.remove('btn-success');
            }, 2000);
        }
    </script>
</body>
</html>