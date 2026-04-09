<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_type = $_POST['user_type'];
    $phone = $_POST['phone'];
    $country = $_POST['country'];
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    
    // Check if email exists
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    
    if ($check_email->get_result()->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, user_type, phone, country, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')");
        $stmt->bind_param("sssssss", $first_name, $last_name, $email, $password, $user_type, $phone, $country);
        
        if ($stmt->execute()) {
            // Skip email verification for development
            // In production, implement proper email service
            // For now, auto-verify the account
            $update_stmt = $conn->prepare("UPDATE users SET status = 'active', email_verified = 1 WHERE email = ?");
            $update_stmt->bind_param("s", $email);
            $update_stmt->execute();
            
            $success = "Registration successful! Your account is now active. You can login now.";
        } else {
            $error = "Registration failed!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=15" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="container">
        <div class="row align-items-center py-4">
            <div class="col-lg-6 d-none d-lg-block">
                <div class="pe-5">
                    <h2 class="fw-bold mb-4 text-primary">Join ExpertHub Today</h2>
                    <p class="lead mb-5">Connect with thousands of verified professionals and get your projects done efficiently.</p>
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Secure & Trusted Platform</h6>
                                    <p class="text-muted mb-0">Your data and payments are protected with enterprise-grade security and encryption.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Verified Expert Network</h6>
                                    <p class="text-muted mb-0">Access to pre-screened professionals with proven track records and verified skills.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">24/7 Customer Support</h6>
                                    <p class="text-muted mb-0">Get assistance whenever you need it with our dedicated support team.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="auth-card mx-auto" style="max-width: 500px;">
                    <div class="auth-header py-2">
                        <h5><i class="fas fa-users-cog me-2"></i>ExpertHub</h5>
                        <p class="mb-0 small">Create Your Account</p>
                    </div>
                    <div class="p-3">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php else: ?>
                            <div class="mb-3">
                                <p class="text-muted mb-0">Choose your account type and start your journey with ExpertHub today.</p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">First Name</label>
                                    <input type="text" class="form-control form-control-sm" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Last Name</label>
                                    <input type="text" class="form-control form-control-sm" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small mb-1">Email Address</label>
                                <input type="email" class="form-control form-control-sm" name="email" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small mb-1">Password</label>
                                <input type="password" class="form-control form-control-sm" name="password" required minlength="6">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Phone Number</label>
                                    <input type="tel" class="form-control form-control-sm" name="phone">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Country</label>
                                    <input type="text" class="form-control form-control-sm" name="country">
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small mb-1">I want to:</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="user_type" value="customer" id="customer" checked>
                                        <label class="form-check-label small" for="customer">
                                            <i class="fas fa-shopping-cart me-1"></i>Buy Services
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="user_type" value="provider" id="provider">
                                        <label class="form-check-label small" for="provider">
                                            <i class="fas fa-briefcase me-1"></i>Sell Services
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-2">Already have an account? <a href="login.php" class="text-decoration-none">Sign In</a></p>
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