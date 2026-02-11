<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login.php");
    exit();
}

$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];
$lang = $_GET['lang'] ?? 'en';

// Get user info based on user type
if ($user_type === 'customer') {
    $stmt = $conn->prepare("SELECT u.* FROM users u WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Get customer stats
    $stmt = $conn->prepare("SELECT 
        COUNT(CASE WHEN status IN ('accepted', 'in_progress') THEN 1 END) as active_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN final_price END), 0) as total_spent
        FROM orders WHERE customer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get saved items count (assuming a saved_services table exists)
    $stmt = $conn->prepare("SELECT COUNT(*) as saved_count FROM cart_items ci 
                           JOIN shopping_carts sc ON ci.cart_id = sc.id 
                           WHERE sc.customer_id = ? AND sc.status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $saved_result = $stmt->get_result()->fetch_assoc();
    $stats['saved_items'] = $saved_result['saved_count'];
    
} elseif ($user_type === 'provider') {
    $stmt = $conn->prepare("SELECT u.*, sp.* FROM users u LEFT JOIN service_providers sp ON u.id = sp.user_id WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Get provider stats
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status IN ('accepted', 'in_progress')) as active_orders,
        (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status = 'completed') as completed_orders,
        (SELECT COUNT(*) FROM provider_services WHERE provider_id = sp.id AND status = 'active') as total_services,
        (SELECT COALESCE(SUM(final_price), 0) FROM orders WHERE provider_id = sp.id AND status = 'completed') as total_earnings,
        (SELECT AVG(TIMESTAMPDIFF(MINUTE, o.created_at, o.updated_at)) FROM orders o WHERE o.provider_id = sp.id AND o.status = 'accepted') as avg_response_minutes
        FROM service_providers sp WHERE sp.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Format average response time
    $avg_minutes = $stats['avg_response_minutes'] ?? 0;
    if ($avg_minutes < 60) {
        $stats['avg_response_time'] = round($avg_minutes) . 'm';
    } else {
        $stats['avg_response_time'] = round($avg_minutes / 60, 1) . 'h';
    }
    
} elseif ($user_type === 'admin') {
    $stmt = $conn->prepare("SELECT u.* FROM users u WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Get admin stats
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
        (SELECT COUNT(*) FROM provider_services WHERE status = 'active') as total_services,
        (SELECT COUNT(*) FROM orders WHERE status IN ('accepted', 'in_progress')) as active_orders,
        (SELECT COALESCE(SUM(final_price), 0) FROM orders WHERE status = 'completed') as total_revenue");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($user_type); ?> Dashboard - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0077B6;
            --secondary-color: #023E8A;
            --accent-color: #00BFA6;
            --background-color: #E6F2F1;
            --text-primary: #2D2D2D;
            --text-secondary: #6C757D;
            --error-color: #E63946;
            --success-color: #2A9D8F;
            --info-color: #0077B6;
        }
        
        body {
            background: linear-gradient(135deg, var(--background-color) 0%, #E6F2F1 50%, var(--background-color) 100%);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 76px;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            box-shadow: 0 2px 10px rgba(0, 119, 182, 0.2);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 119, 182, 0.1);
            background: white;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 119, 182, 0.15);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #1f7a6b;
            border-color: #1f7a6b;
        }
        
        .btn-accent {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        
        .btn-accent:hover {
            background-color: #00a693;
            border-color: #00a693;
            color: white;
        }
        
        .text-accent { color: var(--accent-color) !important; }
        .text-secondary-custom { color: var(--text-secondary) !important; }
        
        .service-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 119, 182, 0.1);
            transition: all 0.3s ease;
            background: white;
            cursor: pointer;
        }
        
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 119, 182, 0.15);
        }
        
        .service-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #E6F2F1, var(--background-color));
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 1rem;
        }
        
        .notification-badge {
            background-color: var(--error-color);
        }
        
        .search-bar {
            border-radius: 25px;
            border: 2px solid var(--accent-color);
        }
        
        .search-bar:focus {
            box-shadow: 0 0 0 0.2rem rgba(0, 191, 166, 0.25);
        }
        
        .provider-stats {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .admin-stats {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../../index.php">
                <i class="fas fa-users-cog me-2"></i>ExpertHub
            </a>
            
            <div class="navbar-nav mx-auto">
                <a class="nav-link active" href="index.php?lang=<?php echo $lang; ?>">
                    <i class="fas fa-home me-1"></i>Home
                </a>
                
                <?php if ($user_type === 'customer'): ?>
                    <a class="nav-link" href="../customer/browse-services.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-search me-1"></i>Browse Services
                    </a>
                    <a class="nav-link" href="../customer/orders.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-list-alt me-1"></i>My Orders
                    </a>
                    <a class="nav-link" href="../customer/messages.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-comments me-1"></i>Messages
                    </a>
                    <a class="nav-link" href="../customer/devices.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-laptop me-1"></i>My Devices
                    </a>
                    <a class="nav-link" href="../customer/wallet.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-wallet me-1"></i>Wallet
                    </a>
                    <a class="nav-link" href="../customer/support.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-headset me-1"></i>Support
                    </a>
                    
                <?php elseif ($user_type === 'provider'): ?>
                    <a class="nav-link" href="../provider/orders.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-shopping-bag me-1"></i>Orders
                    </a>
                    <a class="nav-link" href="../provider/my-services.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-briefcase me-1"></i>My Services
                    </a>
                    <a class="nav-link" href="../provider/create-service.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-plus me-1"></i>Create Service
                    </a>
                    <a class="nav-link" href="../provider/messages.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-comments me-1"></i>Messages
                    </a>
                    <a class="nav-link" href="#" onclick="alert('Calendar - Coming Soon')">
                        <i class="fas fa-calendar me-1"></i>Calendar
                    </a>
                    <a class="nav-link" href="../provider/earnings.php?lang=<?php echo $lang; ?>">
                        <i class="fas fa-dollar-sign me-1"></i>Earnings
                    </a>
                    
                <?php elseif ($user_type === 'admin'): ?>
                    <a class="nav-link" href="../../../browse-services.php">
                        <i class="fas fa-briefcase me-1"></i>Services
                    </a>
                    <a class="nav-link" href="#" onclick="alert('Users - Coming Soon')">
                        <i class="fas fa-users me-1"></i>Users
                    </a>
                    <a class="nav-link" href="#" onclick="alert('Analytics - Coming Soon')">
                        <i class="fas fa-chart-bar me-1"></i>Analytics
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <?php if ($user['profile_image']): ?>
                        <img src="../../../<?php echo $user['profile_image']; ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <?php echo $user['first_name']; ?>
                </button>
                <ul class="dropdown-menu">
                    <?php if ($user_type === 'customer'): ?>
                        <li><a class="dropdown-item" href="../customer/profile.php?lang=<?php echo $lang; ?>">
                            <i class="fas fa-user me-2"></i>My Profile</a></li>
                    <?php elseif ($user_type === 'provider'): ?>
                        <li><a class="dropdown-item" href="../provider/profile.php?lang=<?php echo $lang; ?>">
                            <i class="fas fa-user-tie me-2"></i>My Profile</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../../../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <?php if ($user_type === 'customer'): ?>
            <!-- CUSTOMER DASHBOARD -->
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2 class="mb-1">Welcome back, <?php echo $user['first_name']; ?>! 👋</h2>
                                    <p class="text-secondary-custom mb-0">Customer Dashboard - Manage your services and orders</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-accent btn-lg" data-bs-toggle="modal" data-bs-target="#getServiceModal">
                                        <i class="fas fa-plus me-2"></i>Get Service Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['active_orders']; ?></h4>
                            <p class="text-muted mb-0">Active Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['completed_orders']; ?></h4>
                            <p class="text-muted mb-0">Completed Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h4 class="text-primary">$<?php echo number_format($stats['total_spent'], 2); ?></h4>
                            <p class="text-muted mb-0">Total Spent</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['saved_items']; ?></h4>
                            <p class="text-muted mb-0">Saved Items</p>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($user_type === 'provider'): ?>
            <!-- PROVIDER DASHBOARD -->
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card provider-stats">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2 class="mb-1 text-white">Welcome back, <?php echo $user['first_name']; ?>! 👋</h2>
                                    <p class="text-white-50 mb-0">Service Provider Dashboard - Manage your services and grow your business</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <a href="../provider/create-service.php?lang=<?php echo $lang; ?>" class="btn btn-light btn-lg">
                                        <i class="fas fa-plus me-2"></i>Create New Service
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Summary -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h4 class="text-primary">$<?php echo number_format($stats['total_earnings'] ?? 0, 0); ?></h4>
                            <p class="text-muted mb-0">Total Earnings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['total_services'] ?? 0; ?></h4>
                            <p class="text-muted mb-0">Active Services</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['active_orders'] ?? 0; ?></h4>
                            <p class="text-muted mb-0">Active Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <h4 class="text-primary"><?php echo number_format($user['rating'] ?? 0, 1); ?></h4>
                            <p class="text-muted mb-0">Rating</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['avg_response_time'] ?? '0m'; ?></h4>
                            <p class="text-muted mb-0">Avg Response</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $user['completion_rate'] ?? '98'; ?>%</h4>
                            <p class="text-muted mb-0">Completion Rate</p>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($user_type === 'admin'): ?>
            <!-- ADMIN DASHBOARD -->
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card admin-stats">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2 class="mb-1 text-white">Welcome back, <?php echo $user['first_name']; ?>! 👋</h2>
                                    <p class="text-white-50 mb-0">Admin Dashboard - Platform Management & Analytics</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-light btn-lg">
                                        <i class="fas fa-cog me-2"></i>System Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Admin Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['total_users']; ?></h4>
                            <p class="text-muted mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['total_services']; ?></h4>
                            <p class="text-muted mb-0">Total Services</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h4 class="text-primary"><?php echo $stats['active_orders']; ?></h4>
                            <p class="text-muted mb-0">Active Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card service-card text-center">
                        <div class="card-body p-4">
                            <div class="service-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h4 class="text-primary">$<?php echo number_format($stats['total_revenue'], 2); ?></h4>
                            <p class="text-muted mb-0">Platform Revenue</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Common Activity Section -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2 text-accent"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-secondary-custom mb-3"></i>
                            <h6>No recent activity to display</h6>
                            <p class="text-secondary-custom">Recent updates and activities will appear here.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-bolt me-2 text-accent"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($user_type === 'customer'): ?>
                                <a href="../customer/browse-services.php?lang=<?php echo $lang; ?>" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Browse Services
                                </a>
                                <a href="../customer/orders.php?lang=<?php echo $lang; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>My Orders
                                </a>
                            <?php elseif ($user_type === 'provider'): ?>
                                <a href="../provider/create-service.php?lang=<?php echo $lang; ?>" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Create Service
                                </a>
                            <?php elseif ($user_type === 'admin'): ?>
                                <a href="../customer/browse-services.php?lang=<?php echo $lang; ?>" class="btn btn-danger">
                                    <i class="fas fa-briefcase me-2"></i>View Services
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Get Service Modal (Customer Only) -->
    <?php if ($user_type === 'customer'): ?>
    <div class="modal fade" id="getServiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-search me-2 text-accent"></i>Get Service Now</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card quick-action-card h-100" onclick="redirectToService('web-development')">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-code fa-3x text-primary mb-3"></i>
                                    <h6>Web Development</h6>
                                    <small class="text-secondary-custom">Websites, apps, e-commerce</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card quick-action-card h-100" onclick="redirectToService('graphic-design')">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-palette fa-3x text-accent mb-3"></i>
                                    <h6>Graphic Design</h6>
                                    <small class="text-secondary-custom">Logos, branding, UI/UX</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <button class="btn btn-primary" onclick="redirectToService('all')">
                            <i class="fas fa-th-large me-2"></i>Browse All Services
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php include '../../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function redirectToService(category) {
            if (category === 'all') {
                window.location.href = '../../../browse-services.php';
            } else {
                window.location.href = `../../../browse-services.php?category=${category}`;
            }
        }
    </script>
</body>
</html>