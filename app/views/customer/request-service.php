<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$device_id = $_GET['device_id'] ?? null;
if (!$device_id) {
    header("Location: devices.php");
    exit();
}

// Get device details
$stmt = $conn->prepare("SELECT * FROM customer_devices WHERE id = ? AND customer_id = ?");
$stmt->bind_param("ii", $device_id, $_SESSION['user_id']);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();

if (!$device) {
    header("Location: devices.php");
    exit();
}

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$per_page = 4;
$offset = ($page - 1) * $per_page;

// Get relevant services based on device type
$device_keywords = [
    'laptop' => ['laptop', 'computer', 'repair', 'maintenance'],
    'desktop' => ['desktop', 'computer', 'repair', 'maintenance'],
    'printer' => ['printer', 'printing', 'repair'],
    'server' => ['server', 'network', 'maintenance'],
    'network_device' => ['network', 'router', 'wifi'],
    'mobile' => ['mobile', 'phone', 'repair'],
    'other' => ['repair', 'maintenance', 'support']
];

$keywords = $device_keywords[$device['device_type']] ?? $device_keywords['other'];
$search_pattern = implode('|', $keywords);

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM provider_services ps 
                             JOIN service_providers sp ON ps.provider_id = sp.id 
                             WHERE ps.status = 'active' 
                             AND (ps.title REGEXP ? OR ps.description REGEXP ?)");
$count_stmt->bind_param("ss", $search_pattern, $search_pattern);
$count_stmt->execute();
$total_services = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_services / $per_page);

// Get services with pagination
$stmt = $conn->prepare("SELECT ps.*, u.first_name, u.last_name, sp.rating, 
                       (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status = 'completed') as total_orders
                       FROM provider_services ps 
                       JOIN service_providers sp ON ps.provider_id = sp.id 
                       JOIN users u ON sp.user_id = u.id 
                       WHERE ps.status = 'active' 
                       AND (ps.title REGEXP ? OR ps.description REGEXP ?)
                       ORDER BY sp.rating DESC, total_orders DESC 
                       LIMIT ? OFFSET ?");
$stmt->bind_param("ssii", $search_pattern, $search_pattern, $per_page, $offset);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Service - ExpertHub</title>
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
                <a class="nav-link" href="devices.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Devices</a>
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
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-wrench me-2"></i>Request Service</h3>
                        <p class="mb-0">Find services for your <?php echo htmlspecialchars($device['brand'] . ' ' . $device['model']); ?></p>
                    </div>
                    <div class="p-4">
                        <!-- Device Info -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2 text-center">
                                        <i class="fas fa-<?php 
                                            echo $device['device_type'] === 'laptop' ? 'laptop' : 
                                                ($device['device_type'] === 'desktop' ? 'desktop' : 
                                                ($device['device_type'] === 'printer' ? 'print' : 
                                                ($device['device_type'] === 'mobile' ? 'mobile-alt' : 'microchip'))); 
                                        ?> fa-3x text-primary"></i>
                                    </div>
                                    <div class="col-md-10">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($device['brand'] . ' ' . $device['model']); ?></h5>
                                        <p class="text-muted mb-0">
                                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $device['device_type'])); ?>
                                            <?php if ($device['serial_number']): ?>
                                                | <strong>Serial:</strong> <?php echo htmlspecialchars($device['serial_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recommended Services -->
                        <h5 class="mb-3">Recommended Services for Your Device</h5>
                        
                        <?php if (empty($services)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h6>No Specific Services Found</h6>
                                <p class="text-muted">Browse all available services to find what you need.</p>
                                <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Browse All Services
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($services as $service): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title text-primary"><?php echo htmlspecialchars($service['title']); ?></h6>
                                                <p class="card-text text-muted mb-3"><?php echo htmlspecialchars(substr($service['description'], 0, 100)) . '...'; ?></p>
                                                
                                                <div class="mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-tie me-1"></i>
                                                        Provider: <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                                                    </small><br>
                                                    <small class="text-warning">
                                                        <i class="fas fa-star"></i> <?php echo number_format($service['rating'] ?? 0, 1); ?>
                                                        (<?php echo $service['total_orders']; ?> orders)
                                                    </small>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="h6 text-success mb-0">$<?php echo number_format($service['base_price'], 2); ?></span>
                                                        <small class="text-muted d-block">starting at</small>
                                                    </div>
                                                    <div>
                                                        <a href="order.php?service_id=<?php echo $service['id']; ?>&device_id=<?php echo $device['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-shopping-cart me-1"></i>Order Now
                                                        </a>
                                                    </div>
                                                </div>
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
                                                <a class="page-link" href="request-service.php?device_id=<?php echo $device['id']; ?>&page=<?php echo $page-1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="request-service.php?device_id=<?php echo $device['id']; ?>&page=<?php echo $i; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="request-service.php?device_id=<?php echo $device['id']; ?>&page=<?php echo $page+1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                
                                <div class="text-center text-muted mb-3">
                                    Showing <?php echo min($offset + 1, $total_services); ?>-<?php echo min($offset + $per_page, $total_services); ?> of <?php echo $total_services; ?> services
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center">
                                <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-search me-2"></i>Browse More Services
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="devices.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-arrow-left me-1"></i>Back to Devices
                            </a>
                            <a href="device-history.php?device_id=<?php echo $device['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-info">
                                <i class="fas fa-history me-1"></i>Service History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>