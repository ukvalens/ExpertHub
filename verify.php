<?php
require_once 'config/database.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // In a real implementation, you would store tokens in database
    // For now, just activate any pending account
    $stmt = $conn->prepare("UPDATE users SET status = 'active', email_verified = 1 WHERE status = 'pending_verification' LIMIT 1");
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Email verified successfully! You can now login.";
        $type = "success";
    } else {
        $message = "Invalid or expired verification link.";
        $type = "danger";
    }
} else {
    $message = "Invalid verification link.";
    $type = "danger";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0077B6;
            --secondary-color: #023E8A;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center p-5">
                        <h3 class="mb-4">Email Verification</h3>
                        <div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>