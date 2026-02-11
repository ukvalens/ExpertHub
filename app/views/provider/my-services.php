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
    $stmt = $conn->prepare("INSERT INTO service_providers (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $provider_id = $conn->insert_id;
} else {
    $provider_id = $provider['id'];
}

// Handle service status toggle
if (isset($_POST['toggle_status'])) {
    $service_id = $_POST['service_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE provider_services SET status = ? WHERE id = ? AND provider_id = ?");
    $stmt->bind_param("sii", $new_status, $service_id, $provider_id);
    $stmt->execute();
}

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$per_page = 3;
$offset = ($page - 1) * $per_page;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM provider_services WHERE provider_id = ?");
$count_stmt->bind_param("i", $provider_id);
$count_stmt->execute();
$total_services = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_services / $per_page);

// Get provider services with pagination
$stmt = $conn->prepare("SELECT ps.*, sc.name as category_name FROM provider_services ps 
                       LEFT JOIN service_categories sc ON ps.category_id = sc.id 
                       WHERE ps.provider_id = ? ORDER BY ps.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $provider_id, $per_page, $offset);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Services - ExpertHub</title>
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
                <a class="nav-link active" href="my-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Orders</a>
                <a class="nav-link" href="support.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Support</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user me-1"></i>Provider
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
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><i class="fas fa-briefcase me-2"></i>My Services</h3>
                                <p class="mb-0">Manage your service offerings</p>
                            </div>
                            <a href="create-service.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Create New Service
                            </a>
                        </div>
                    </div>
                    <div class="p-4">
                        <?php if (empty($services)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                <h5>No Services Yet</h5>
                                <p class="text-muted">Create your first service to start receiving orders from customers.</p>
                                <a href="create-service.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Your First Service
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($services as $service): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <span class="badge bg-<?php echo $service['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($service['status']); ?>
                                                </span>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                                <input type="hidden" name="new_status" value="<?php echo $service['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                                                <button type="submit" name="toggle_status" class="dropdown-item">
                                                                    <i class="fas fa-toggle-<?php echo $service['status'] == 'active' ? 'off' : 'on'; ?> me-2"></i>
                                                                    <?php echo $service['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($service['title']); ?></h6>
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars(substr($service['description'], 0, 100)) . '...'; ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-success fw-bold">$<?php echo number_format($service['base_price'], 2); ?></span>
                                                    <small class="text-muted"><?php echo ucfirst($service['service_type']); ?></small>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <small class="text-muted">
                                                    Created: <?php echo date('M j, Y', strtotime($service['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Services pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="my-services.php?page=<?php echo $page-1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="my-services.php?page=<?php echo $i; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="my-services.php?page=<?php echo $page+1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                
                                <div class="text-center text-muted">
                                    Showing <?php echo min($offset + 1, $total_services); ?>-<?php echo min($offset + $per_page, $total_services); ?> of <?php echo $total_services; ?> services
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>