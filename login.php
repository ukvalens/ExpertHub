<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, password, user_type, status, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            if ($user['status'] == 'pending_verification') {
                $error = "Your account is pending verification. Please contact support.";
            } elseif ($user['status'] != 'active') {
                $error = "Your account is not active. Please contact support.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect to unified dashboard
                header("Location: app/views/dashboard/index.php?lang=en");
                exit();
            }
        } else {
            $error = "Invalid email or password!";
        }
    } else {
        $error = "Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=17" rel="stylesheet">

    </style>
</head>
<body class="auth-body">
    <div class="container">
        <div class="row align-items-center py-4">
            <div class="col-lg-6 d-none d-lg-block">
                <div class="pe-5">
                    <h2 class="fw-bold mb-4 text-primary">Welcome Back to ExpertHub</h2>
                    <p class="lead mb-5">Access your dashboard and continue managing your services with ease.</p>
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Personal Dashboard</h6>
                                    <p class="text-muted mb-0">Access your personalized control panel with real-time updates and insights.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Order Management</h6>
                                    <p class="text-muted mb-0">Track progress, communicate with providers, and manage all your service orders.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-start">
                                <div class="service-icon me-3 flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div>
                                    <h6 class="mb-2">Direct Communication</h6>
                                    <p class="text-muted mb-0">Chat directly with service providers and collaborate in real-time.</p>
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
                        <p class="mb-0">Welcome Back</p>
                        <small class="d-block mt-2 opacity-75">Sign in to access your dashboard and manage your services</small>
                    </div>
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php else: ?>
                            <div class="mb-3">
                                <p class="text-muted mb-0">Enter your credentials to access your ExpertHub account.</p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-2"><a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a></p>
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
