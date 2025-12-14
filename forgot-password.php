<?php
session_start();
require_once 'config/database.php';
require_once 'config/email.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $reset_token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token (in production, create password_resets table)
        // For now, we'll use a simple approach
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $temp_password = 'reset_' . $reset_token;
        $update_stmt->bind_param("ss", $temp_password, $email);
        $update_stmt->execute();
        
        // Skip email for development - show reset link directly
        $reset_link = "http://localhost/ExpertHUB/reset-password.php?token=" . $reset_token . "&email=" . urlencode($email);
        
        // In development, show the reset link directly
        $_SESSION['reset_link'] = $reset_link;
        $_SESSION['reset_email'] = $email;
        
        $success = "Password reset link generated. <br><a href='" . $reset_link . "' class='btn btn-sm btn-primary mt-2'>Click here to reset password</a>";
    } else {
        $error = "Email address not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    </style>
</head>
<body class="auth-body">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6 d-none d-lg-block">
                <div class="pe-5">
                    <h2 class="fw-bold mb-4 text-primary">Forgot Your Password?</h2>
                    <p class="lead mb-5">Don't worry! It happens to the best of us. We'll help you get back into your ExpertHub account quickly and securely.</p>
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Secure Process</h6>
                                    <p class="text-muted mb-0">Your password reset is protected with enterprise-grade security measures.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Quick Recovery</h6>
                                    <p class="text-muted mb-0">Get back to your projects and services within minutes.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Email Verification</h6>
                                    <p class="text-muted mb-0">We'll send a secure reset link directly to your registered email address.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="auth-card mx-auto" style="max-width: 450px;">
                    <div class="auth-header">
                        <h3><i class="fas fa-users-cog me-2"></i>ExpertHub</h3>
                        <p class="mb-0">Reset Your Password</p>
                        <small class="d-block mt-2 opacity-75">Enter your email to receive a password reset link</small>
                    </div>
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php else: ?>
                            <div class="mb-3">
                                <p class="text-muted mb-0">We'll send you a secure link to reset your password and get back to your ExpertHub account.</p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" placeholder="Enter your registered email" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-2">Remember your password? <a href="login.php" class="text-decoration-none">Sign In</a></p>
                            <p class="mb-2">Don't have an account? <a href="signup.php" class="text-decoration-none">Sign Up</a></p>
                            <p class="mb-0"><a href="index.php" class="text-decoration-none">← Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>