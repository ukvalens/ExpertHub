<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'];
    $email = $_POST['email'];
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Verify token and update password
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND password LIKE 'reset_%'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update password
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $new_password, $email);
        
        if ($update_stmt->execute()) {
            $success = "Password reset successfully! You can now login with your new password.";
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    } else {
        $error = "Invalid or expired reset link.";
    }
}

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    header("Location: forgot-password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>

    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-users-cog me-2"></i>ExpertHub</h3>
                        <p class="mb-0">Set New Password</p>
                        <small class="d-block mt-2 opacity-75">Choose a strong password to secure your account</small>
                    </div>
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">Go to Login</a>
                            </div>
                        <?php else: ?>
                        
                        <form method="POST">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-key me-2"></i>Reset Password
                            </button>
                        </form>
                        
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <p class="mb-0"><a href="login.php" class="text-decoration-none">← Back to Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>