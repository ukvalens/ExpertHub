<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get provider ID
$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

if (!$provider) {
    // Create provider record if doesn't exist
    $stmt = $conn->prepare("INSERT INTO service_providers (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $provider_id = $conn->insert_id;
} else {
    $provider_id = $provider['id'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    // $category = $_POST['category']; // Removed since column doesn't exist
    $base_price = $_POST['base_price'];

    
    // Get first available category
    $cat_stmt = $conn->prepare("SELECT id FROM service_categories LIMIT 1");
    $cat_stmt->execute();
    $category = $cat_stmt->get_result()->fetch_assoc();
    $category_id = $category ? $category['id'] : null;
    
    if (!$category_id) {
        $error = "No service categories available. Please contact admin.";
    } else {
        $service_type = 'online_service';
        $status = 'active';
        
        $stmt = $conn->prepare("INSERT INTO provider_services (provider_id, category_id, title, description, service_type, base_price, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssds", $provider_id, $category_id, $title, $description, $service_type, $base_price, $status);
        
        if ($stmt->execute()) {
            $success = "Service created successfully!";
        } else {
            $error = "Failed to create service.";
        }
    }

}
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Service - ExpertHub</title>
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
                <a class="nav-link active" href="create-service.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Create Service</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <?php 
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $nav_user = $stmt->get_result()->fetch_assoc();
                    ?>
                    <?php if ($nav_user['profile_image']): ?>
                        <img src="../../../<?php echo $nav_user['profile_image']; ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <?php echo strtoupper(substr($nav_user['first_name'], 0, 1) . substr($nav_user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    Provider
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
            <div class="col-md-8 mx-auto">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-plus me-2"></i>Create New Service</h3>
                        <p class="mb-0">Add a new service to your portfolio</p>
                    </div>
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Service Title</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            

                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="5" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Base Price ($)</label>
                                    <input type="number" class="form-control" name="base_price" step="0.01" required>
                                </div>

                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Service
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>