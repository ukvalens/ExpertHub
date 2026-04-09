<?php
session_start();
require_once '../../../config/database.php';

// Serve portfolio for a provider (AJAX)
if (isset($_GET['portfolio_provider_id'])) {
    $view_provider_id = (int)$_GET['portfolio_provider_id'];
    include '../shared/portfolio-viewer.php';
    exit;
}

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 3;
$offset = ($page - 1) * $per_page;

// Build where clause
$where_clause = "WHERE ps.status = 'active'";
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_clause .= " AND (ps.title LIKE ? OR ps.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= "ss";
}

if (!empty($category)) {
    $where_clause .= " AND ps.category = ?";
    $params[] = $category;
    $param_types .= "s";
}

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM provider_services ps 
                             JOIN service_providers sp ON ps.provider_id = sp.id 
                             JOIN users u ON sp.user_id = u.id 
                             $where_clause");
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_services = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_services / $per_page);

// Get services with pagination
$stmt = $conn->prepare("SELECT ps.*, u.first_name, u.last_name, sp.rating, 
                       (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status = 'completed') as total_orders
                       FROM provider_services ps 
                       JOIN service_providers sp ON ps.provider_id = sp.id 
                       JOIN users u ON sp.user_id = u.id 
                       $where_clause 
                       ORDER BY ps.created_at DESC 
                       LIMIT ? OFFSET ?");

$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services - ExpertHub</title>
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
                <a class="nav-link active" href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Browse Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Orders</a>
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
        <div class="row mb-4">
            <div class="col-12">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-search me-2"></i>Browse Services</h3>
                        <p class="mb-0">Find and order services from verified professionals</p>
                    </div>
                    <div class="p-4">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <input type="hidden" name="lang" value="<?php echo $_GET['lang'] ?? 'en'; ?>">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" placeholder="Search services..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Search
                                </button>
                            </div>
                        </form>
                        
                        <!-- Services Grid -->
                        <div class="row">
                            <?php if (empty($services)): ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <div class="service-icon mx-auto mb-3">
                                            <i class="fas fa-search"></i>
                                        </div>
                                        <h5>No services found</h5>
                                        <p class="text-muted">Try adjusting your search criteria or browse all categories.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($services as $service): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="service-card h-100">
                                            <div class="card-body p-4">
                                                <h5 class="card-title text-primary"><?php echo htmlspecialchars($service['title']); ?></h5>
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
                                                        <span class="h5 text-success mb-0">$<?php echo number_format($service['base_price'], 2); ?></span>
                                                        <small class="text-muted d-block">starting at</small>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-outline-secondary btn-sm view-portfolio-btn"
                                                            data-provider-id="<?php echo $service['provider_id']; ?>"
                                                            data-provider-name="<?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#portfolioModal">
                                                            <i class="fas fa-images"></i>
                                                        </button>
                                                        <a href="order.php?service_id=<?php echo $service['id']; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-shopping-cart me-1"></i>Order
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Services pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="browse-services.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&page=<?php echo $page-1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="browse-services.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&page=<?php echo $i; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="browse-services.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&page=<?php echo $page+1; ?>&lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            
                            <div class="text-center text-muted">
                                Showing <?php echo min($offset + 1, $total_services); ?>-<?php echo min($offset + $per_page, $total_services); ?> of <?php echo $total_services; ?> services
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Portfolio Modal -->
    <div class="modal fade" id="portfolioModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i><span id="portfolioModalName"></span>'s Portfolio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="portfolioModalBody">
                    <div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.querySelectorAll('.view-portfolio-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('portfolioModalName').textContent = btn.dataset.providerName;
            document.getElementById('portfolioModalBody').innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></div>';
            fetch('browse-services.php?portfolio_provider_id=' + btn.dataset.providerId + '&lang=<?php echo $_GET["lang"] ?? "en"; ?>')
                .then(r => r.text()).then(html => {
                    document.getElementById('portfolioModalBody').innerHTML = html;
                });
        });
    });
    </script>
</body>
</html>