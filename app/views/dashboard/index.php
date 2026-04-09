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

if ($user_type === 'customer') {
    $stmt = $conn->prepare("SELECT u.* FROM users u WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT 
        COUNT(CASE WHEN status IN ('accepted', 'in_progress') THEN 1 END) as active_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN final_price END), 0) as total_spent
        FROM orders WHERE customer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT COUNT(*) as saved_count FROM cart_items ci 
                           JOIN shopping_carts sc ON ci.cart_id = sc.id 
                           WHERE sc.customer_id = ? AND sc.status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['saved_items'] = $stmt->get_result()->fetch_assoc()['saved_count'];

} elseif ($user_type === 'provider') {
    $stmt = $conn->prepare("SELECT u.*, sp.* FROM users u LEFT JOIN service_providers sp ON u.id = sp.user_id WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

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

    $avg_minutes = $stats['avg_response_minutes'] ?? 0;
    $stats['avg_response_time'] = $avg_minutes < 60 ? round($avg_minutes) . 'm' : round($avg_minutes / 60, 1) . 'h';

} elseif ($user_type === 'admin') {
    $stmt = $conn->prepare("SELECT u.* FROM users u WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

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
            --primary: #0077B6;
            --secondary: #023E8A;
            --accent: #00BFA6;
            --bg: #F8F9FA;
            --sidebar-w: 240px;
            --primary-color: #0077B6;
            --secondary-color: #023E8A;
            --accent-color: #00BFA6;
            --background-color: #F8F9FA;
            --light-bg: #E6F2F1;
            --text-color: #2D2D2D;
            --text-muted: #6C757D;
            --success-color: #2A9D8F;
            --error-color: #E63946;
            --white: #FFFFFF;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: grid;
            grid-template-rows: 60px 1fr auto;
            grid-template-columns: var(--sidebar-w) 1fr;
            grid-template-areas:
                "topbar topbar"
                "sidebar main"
                "footer footer";
        }

        /* TOPBAR */
        .topbar {
            grid-area: topbar;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
        }

        .topbar .brand {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            text-decoration: none;
        }

        .topbar .description {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
        }

        .topbar .profile-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar .profile-area img,
        .topbar .profile-area .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.5);
        }

        .topbar .profile-area .avatar {
            background: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .topbar .profile-area .name {
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* SIDEBAR */
        .sidebar {
            grid-area: sidebar;
            background: linear-gradient(180deg, var(--secondary), var(--primary));
            overflow-y: auto;
            padding: 15px 0;
            position: sticky;
            top: 60px;
            height: calc(100vh - 60px);
            align-self: start;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 18px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.82rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left: 3px solid var(--accent);
            padding-left: 15px;
        }

        .sidebar a i {
            width: 16px;
            text-align: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .sidebar .section-label {
            color: rgba(255,255,255,0.45);
            font-size: 0.65rem;
            text-transform: uppercase;
            padding: 12px 18px 3px;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* MAIN CONTENT */
        .main-wrapper {
            grid-area: main;
        }

        .main-content {
            padding: 20px;
        }

        /* STAT CARDS */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0,119,182,0.08);
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover { transform: translateY(-4px); }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #E6F2F1, #F0F4F8);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin: 0 auto 8px;
        }

        .stat-card h5 {
            color: var(--primary);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .stat-card p {
            color: #6C757D;
            font-size: 0.78rem;
            margin: 0;
        }

        /* CONTENT CARDS */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,119,182,0.08);
            overflow: hidden;
        }

        .content-card .card-header {
            background: white;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 15px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .content-card .card-body { padding: 15px; }

        /* FOOTER */
        footer {
            grid-area: footer;
            padding-left: var(--sidebar-w);
        }

        @media (max-width: 768px) {
            footer { padding-left: 0; }
        }

        /* HAMBURGER */
        .hamburger { display: none; background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; padding: 5px; }

        /* SIDEBAR OVERLAY */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 998;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            body {
                grid-template-columns: 0 1fr;
            }
            .hamburger { display: block; }
            .topbar .description { display: none; }
            .sidebar {
                position: fixed;
                top: 60px;
                left: -220px;
                width: 220px;
                height: calc(100vh - 60px);
                z-index: 999;
                transition: left 0.3s ease;
            }
            .sidebar.open { left: 0; }
            .sidebar-overlay.open { display: block; }
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="hamburger" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <a href="../../../index.php" class="brand"><i class="fas fa-users-cog me-2"></i>ExpertHub</a>
        </div>
        <span class="description">
            Welcome back, <?php echo $user['first_name']; ?>! 👋
        </span>
        <div class="profile-area dropdown">
            <button class="btn p-0 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="../../../<?php echo $user['profile_image']; ?>" alt="Profile">
                <?php else: ?>
                    <div class="avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                <?php endif; ?>
                <span class="name"><?php echo $user['first_name']; ?></span>
                <i class="fas fa-chevron-down text-white" style="font-size:0.7rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($user_type === 'customer'): ?>
                    <li><a class="dropdown-item" href="../customer/profile.php?lang=<?php echo $lang; ?>"><i class="fas fa-user me-2"></i>My Profile</a></li>
                <?php elseif ($user_type === 'provider'): ?>
                    <li><a class="dropdown-item" href="../provider/profile.php?lang=<?php echo $lang; ?>"><i class="fas fa-user-tie me-2"></i>My Profile</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="../../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">

        <?php if ($user_type === 'customer'): ?>

            <div class="section-label">🏠 Dashboard</div>
            <a href="index.php?lang=<?php echo $lang; ?>" class="active"><i class="fas fa-home"></i>Dashboard</a>

            <div class="section-label">🛒 Services</div>
            <a href="../customer/browse-services.php?lang=<?php echo $lang; ?>"><i class="fas fa-search"></i>Browse Services</a>
            <a href="../customer/categories.php?lang=<?php echo $lang; ?>"><i class="fas fa-th-large"></i>Categories</a>
            <a href="../customer/search-services.php?lang=<?php echo $lang; ?>"><i class="fas fa-filter"></i>Search Services</a>
            <a href="../customer/compare-services.php?lang=<?php echo $lang; ?>"><i class="fas fa-balance-scale"></i>Compare Services</a>
            <a href="../customer/saved-services.php?lang=<?php echo $lang; ?>"><i class="fas fa-bookmark"></i>Saved Services</a>

            <div class="section-label">🛍 Orders</div>
            <a href="../customer/orders.php?lang=<?php echo $lang; ?>"><i class="fas fa-list-alt"></i>My Orders</a>
            <a href="../customer/orders.php?status=active&lang=<?php echo $lang; ?>"><i class="fas fa-spinner"></i>Active Orders</a>
            <a href="../customer/orders.php?status=review&lang=<?php echo $lang; ?>"><i class="fas fa-star-half-alt"></i>Pending Review</a>
            <a href="../customer/orders.php?status=completed&lang=<?php echo $lang; ?>"><i class="fas fa-check-circle"></i>Completed Orders</a>
            <a href="../customer/orders.php?status=cancelled&lang=<?php echo $lang; ?>"><i class="fas fa-times-circle"></i>Cancelled Orders</a>

            <div class="section-label">💬 Communication</div>
            <a href="../customer/messages.php?lang=<?php echo $lang; ?>"><i class="fas fa-comments"></i>Messages</a>
            <a href="../customer/notifications.php?lang=<?php echo $lang; ?>"><i class="fas fa-bell"></i>Notifications</a>

            <div class="section-label">📁 Documents &amp; Devices</div>
            <a href="../customer/documents.php?lang=<?php echo $lang; ?>"><i class="fas fa-file-alt"></i>My Documents</a>
            <a href="../customer/shared-files.php?lang=<?php echo $lang; ?>"><i class="fas fa-share-alt"></i>Shared Files</a>
            <a href="../customer/templates.php?lang=<?php echo $lang; ?>"><i class="fas fa-copy"></i>Templates</a>
            <a href="../customer/devices.php?lang=<?php echo $lang; ?>"><i class="fas fa-laptop"></i>My Devices</a>

            <div class="section-label">💰 Payments</div>
            <a href="../customer/wallet.php?lang=<?php echo $lang; ?>"><i class="fas fa-wallet"></i>My Wallet</a>
            <a href="../customer/payment-methods.php?lang=<?php echo $lang; ?>"><i class="fas fa-credit-card"></i>Payment Methods</a>
            <a href="../customer/transactions.php?lang=<?php echo $lang; ?>"><i class="fas fa-exchange-alt"></i>Transactions</a>
            <a href="../customer/invoices.php?lang=<?php echo $lang; ?>"><i class="fas fa-file-invoice"></i>Invoices</a>
            <a href="../customer/refunds.php?lang=<?php echo $lang; ?>"><i class="fas fa-undo"></i>Refunds</a>

        

           
            <div class="section-label">🆘 Support</div>
            <a href="../customer/help.php?lang=<?php echo $lang; ?>"><i class="fas fa-question-circle"></i>Help Center</a>
            <a href="../customer/tickets.php?lang=<?php echo $lang; ?>"><i class="fas fa-ticket-alt"></i>Support Tickets</a>
            <a href="../customer/contact.php?lang=<?php echo $lang; ?>"><i class="fas fa-headset"></i>Contact Support</a>

        <?php elseif ($user_type === 'provider'): ?>

            <div class="section-label">🏠 Dashboard</div>
            <a href="index.php?lang=<?php echo $lang; ?>" class="active"><i class="fas fa-home"></i>Dashboard</a>
            <a href="../provider/performance.php?lang=<?php echo $lang; ?>"><i class="fas fa-chart-line"></i>Performance Overview</a>

            <div class="section-label">📦 Orders</div>
            <a href="../provider/orders.php?lang=<?php echo $lang; ?>"><i class="fas fa-shopping-bag"></i>All Orders</a>
            <a href="../provider/orders.php?status=active&lang=<?php echo $lang; ?>"><i class="fas fa-spinner"></i>Active Orders</a>
            <a href="../provider/orders.php?status=completed&lang=<?php echo $lang; ?>"><i class="fas fa-check-circle"></i>Completed Orders</a>
            <a href="../provider/order-board.php?lang=<?php echo $lang; ?>"><i class="fas fa-columns"></i>Order Board</a>

            <div class="section-label">📥 Requests</div>
            <a href="../provider/requests.php?lang=<?php echo $lang; ?>"><i class="fas fa-inbox"></i>New Requests</a>
            <a href="../provider/quotes.php?status=pending&lang=<?php echo $lang; ?>"><i class="fas fa-file-signature"></i>Pending Quotes</a>
            <a href="../provider/negotiations.php?lang=<?php echo $lang; ?>"><i class="fas fa-handshake"></i>Negotiations</a>

            <div class="section-label">🛠 Services</div>
            <a href="../provider/my-services.php?lang=<?php echo $lang; ?>"><i class="fas fa-briefcase"></i>My Services</a>
            <a href="../provider/create-service.php?lang=<?php echo $lang; ?>"><i class="fas fa-plus"></i>Create Service</a>
            <a href="../provider/templates.php?lang=<?php echo $lang; ?>"><i class="fas fa-copy"></i>Service Templates</a>
            <a href="../provider/portfolio.php?lang=<?php echo $lang; ?>"><i class="fas fa-images"></i>Portfolio</a>

            <div class="section-label">📅 Schedule</div>
            <a href="../provider/calendar.php?lang=<?php echo $lang; ?>"><i class="fas fa-calendar-alt"></i>Calendar</a>
            <a href="../provider/availability.php?lang=<?php echo $lang; ?>"><i class="fas fa-clock"></i>Availability</a>
            <a href="../provider/appointments.php?lang=<?php echo $lang; ?>"><i class="fas fa-calendar-check"></i>Appointments</a>

            <div class="section-label">💬 Communication</div>
            <a href="../provider/messages.php?lang=<?php echo $lang; ?>"><i class="fas fa-comments"></i>Messages</a>
            <a href="../provider/notifications.php?lang=<?php echo $lang; ?>"><i class="fas fa-bell"></i>Notifications</a>

            <div class="section-label">📁 Work &amp; Files</div>
            <a href="../provider/deliverables.php?lang=<?php echo $lang; ?>"><i class="fas fa-paper-plane"></i>Deliverables</a>
            <a href="../provider/documents.php?lang=<?php echo $lang; ?>"><i class="fas fa-file-alt"></i>Documents</a>
            <a href="../provider/file-manager.php?lang=<?php echo $lang; ?>"><i class="fas fa-folder-open"></i>File Manager</a>

            <div class="section-label">💰 Earnings</div>
            <a href="../provider/earnings.php?lang=<?php echo $lang; ?>"><i class="fas fa-dollar-sign"></i>Earnings Dashboard</a>
            <a href="../provider/transactions.php?lang=<?php echo $lang; ?>"><i class="fas fa-exchange-alt"></i>Transactions</a>
            <a href="../provider/withdraw.php?lang=<?php echo $lang; ?>"><i class="fas fa-money-bill-wave"></i>Withdraw Funds</a>
            <a href="../provider/invoices.php?lang=<?php echo $lang; ?>"><i class="fas fa-file-invoice"></i>Invoices</a>
            <a href="../provider/quotes.php?lang=<?php echo $lang; ?>"><i class="fas fa-file-invoice-dollar"></i>Quotes</a>

            <div class="section-label">👥 Clients</div>
            <a href="../provider/clients.php?lang=<?php echo $lang; ?>"><i class="fas fa-users"></i>My Clients</a>
            <a href="../provider/client-management.php?lang=<?php echo $lang; ?>"><i class="fas fa-user-cog"></i>Client Management</a>

            <div class="section-label">⭐ Reviews</div>
            <a href="../provider/reviews.php?lang=<?php echo $lang; ?>"><i class="fas fa-star"></i>Reviews</a>
            <a href="../provider/ratings.php?lang=<?php echo $lang; ?>"><i class="fas fa-chart-bar"></i>Ratings</a>
            <a href="../provider/performance-metrics.php?lang=<?php echo $lang; ?>"><i class="fas fa-tachometer-alt"></i>Performance Metrics</a>

            <div class="section-label">📢 Growth</div>
            <a href="../provider/promotions.php?lang=<?php echo $lang; ?>"><i class="fas fa-bullhorn"></i>Promotions</a>
            <a href="../provider/offers.php?lang=<?php echo $lang; ?>"><i class="fas fa-tags"></i>Offers</a>
            <a href="../provider/profile-optimization.php?lang=<?php echo $lang; ?>"><i class="fas fa-rocket"></i>Profile Optimization</a>

            <div class="section-label">⚖️ Disputes</div>
            <a href="../provider/disputes.php?lang=<?php echo $lang; ?>"><i class="fas fa-gavel"></i>Dispute Center</a>
            <a href="../provider/compliance.php?lang=<?php echo $lang; ?>"><i class="fas fa-clipboard-check"></i>Compliance</a>

            <div class="section-label">⚙️ Settings</div>
            <a href="../provider/profile.php?lang=<?php echo $lang; ?>"><i class="fas fa-user-tie"></i>Profile Settings</a>
            <a href="../provider/account-settings.php?lang=<?php echo $lang; ?>"><i class="fas fa-cog"></i>Account Settings</a>
            <a href="../provider/team.php?lang=<?php echo $lang; ?>"><i class="fas fa-users-cog"></i>Team Management</a>

            <div class="section-label">🆘 Support</div>
            <a href="../provider/support.php?lang=<?php echo $lang; ?>"><i class="fas fa-headset"></i>Provider Support</a>
            <a href="../provider/resources.php?lang=<?php echo $lang; ?>"><i class="fas fa-book"></i>Resource Center</a>

        <?php elseif ($user_type === 'admin'): ?>

            <div class="section-label">🏠 Dashboard</div>
            <a href="index.php" class="active"><i class="fas fa-home"></i>Dashboard</a>
            <a href="../admin/analytics.php"><i class="fas fa-chart-pie"></i>Analytics Overview</a>

            <div class="section-label">👤 Users</div>
            <a href="../admin/customers.php"><i class="fas fa-user"></i>Customers</a>
            <a href="../admin/providers.php"><i class="fas fa-user-tie"></i>Providers</a>
            <a href="../admin/admins.php"><i class="fas fa-user-shield"></i>Admins</a>
            <a href="../admin/verification.php"><i class="fas fa-id-badge"></i>Verification</a>

            <div class="section-label">🛒 Services</div>
            <a href="../../../browse-services.php"><i class="fas fa-briefcase"></i>Service Listings</a>
            <a href="../admin/categories.php"><i class="fas fa-th-large"></i>Categories</a>
            <a href="../admin/approvals.php"><i class="fas fa-check-double"></i>Approvals</a>

            <div class="section-label">📦 Orders</div>
            <a href="../admin/orders.php"><i class="fas fa-shopping-bag"></i>All Orders</a>
            <a href="../admin/orders.php?status=active"><i class="fas fa-spinner"></i>Active Orders</a>
            <a href="../admin/disputes.php"><i class="fas fa-gavel"></i>Disputes</a>
            <a href="../admin/refunds.php"><i class="fas fa-undo"></i>Refund Requests</a>

            <div class="section-label">💰 Finance</div>
            <a href="../admin/transactions.php"><i class="fas fa-exchange-alt"></i>Transactions</a>
            <a href="../admin/commissions.php"><i class="fas fa-percentage"></i>Commissions</a>
            <a href="../admin/payouts.php"><i class="fas fa-money-bill-wave"></i>Payouts</a>
            <a href="../admin/revenue.php"><i class="fas fa-chart-line"></i>Revenue Reports</a>

            <div class="section-label">⭐ Reviews</div>
            <a href="../admin/reviews.php"><i class="fas fa-star"></i>Reviews Monitoring</a>
            <a href="../admin/ratings.php"><i class="fas fa-chart-bar"></i>Ratings Analysis</a>

            <div class="section-label">⚖️ Disputes</div>
            <a href="../admin/dispute-cases.php"><i class="fas fa-balance-scale"></i>Dispute Cases</a>
            <a href="../admin/resolution.php"><i class="fas fa-handshake"></i>Resolution Center</a>

            <div class="section-label">📊 Analytics</div>
            <a href="../admin/user-analytics.php"><i class="fas fa-users"></i>User Analytics</a>
            <a href="../admin/revenue-analytics.php"><i class="fas fa-dollar-sign"></i>Revenue Analytics</a>
            <a href="../admin/service-trends.php"><i class="fas fa-trending-up"></i>Service Trends</a>

            <div class="section-label">📢 Communication</div>
            <a href="../admin/notifications.php"><i class="fas fa-bell"></i>Notifications</a>
            <a href="../admin/email-templates.php"><i class="fas fa-envelope"></i>Email/SMS Templates</a>
            <a href="../admin/announcements.php"><i class="fas fa-bullhorn"></i>Announcements</a>

            <div class="section-label">📚 Content</div>
            <a href="../admin/knowledge-base.php"><i class="fas fa-book"></i>Knowledge Base</a>
            <a href="../admin/faqs.php"><i class="fas fa-question-circle"></i>FAQs</a>
            <a href="../admin/manage_about.php"><i class="fas fa-images"></i>Manage Photos</a>

            <div class="section-label">⚙️ System</div>
            <a href="../admin/platform-settings.php"><i class="fas fa-cogs"></i>Platform Settings</a>
            <a href="../admin/api-settings.php"><i class="fas fa-plug"></i>API Settings</a>
            <a href="../admin/security-settings.php"><i class="fas fa-shield-alt"></i>Security Settings</a>
            <a href="../admin/tax-compliance.php"><i class="fas fa-file-contract"></i>Tax &amp; Compliance</a>

            <div class="section-label">🔗 Integrations</div>
            <a href="../admin/payment-gateways.php"><i class="fas fa-credit-card"></i>Payment Gateways</a>
            <a href="../admin/third-party-apis.php"><i class="fas fa-code"></i>Third-party APIs</a>

            <div class="section-label">🛡 Security</div>
            <a href="../admin/activity-logs.php"><i class="fas fa-history"></i>Activity Logs</a>
            <a href="../admin/system-monitoring.php"><i class="fas fa-desktop"></i>System Monitoring</a>

            <div class="section-label">🆘 Support</div>
            <a href="../admin/tickets.php"><i class="fas fa-ticket-alt"></i>Support Tickets</a>
            <a href="../admin/bug-reports.php"><i class="fas fa-bug"></i>Bug Reports</a>

        <?php endif; ?>

        <div class="section-label">─────────────</div>
        <a href="../../../logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        <div class="main-content">

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <?php if ($user_type === 'customer'): ?>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><h5><?php echo $stats['active_orders']; ?></h5><p>Active Orders</p></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><h5><?php echo $stats['completed_orders']; ?></h5><p>Completed</p></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div><h5>$<?php echo number_format($stats['total_spent'], 0); ?></h5><p>Total Spent</p></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-bookmark"></i></div><h5><?php echo $stats['saved_items']; ?></h5><p>Saved Items</p></div></div>

                <?php elseif ($user_type === 'provider'): ?>
                    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div><h5>$<?php echo number_format($stats['total_earnings'] ?? 0, 0); ?></h5><p>Earnings</p></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-icon"><i class="fas fa-briefcase"></i></div><h5><?php echo $stats['total_services'] ?? 0; ?></h5><p>Services</p></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-icon"><i class="fas fa-shopping-bag"></i></div><h5><?php echo $stats['active_orders'] ?? 0; ?></h5><p>Active Orders</p></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><h5><?php echo $stats['completed_orders'] ?? 0; ?></h5><p>Completed</p></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-icon"><i class="fas fa-star"></i></div><h5><?php echo number_format($user['rating'] ?? 0, 1); ?></h5><p>Rating</p></div></div>
                    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><h5><?php echo $stats['avg_response_time'] ?? '0m'; ?></h5><p>Avg Response</p></div></div>

                <?php elseif ($user_type === 'admin'): ?>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><h5><?php echo $stats['total_users']; ?></h5><p>Total Users</p></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-briefcase"></i></div><h5><?php echo $stats['total_services']; ?></h5><p>Services</p></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-shopping-bag"></i></div><h5><?php echo $stats['active_orders']; ?></h5><p>Active Orders</p></div></div>
                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div><h5>$<?php echo number_format($stats['total_revenue'], 0); ?></h5><p>Revenue</p></div></div>
                <?php endif; ?>
            </div>

            <!-- Content Row -->
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="content-card">
                        <div class="card-header"><i class="fas fa-history me-2" style="color:var(--accent)"></i>Recent Activity</div>
                        <div class="card-body text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted small mb-0">No recent activity to display</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="content-card">
                        <div class="card-header"><i class="fas fa-bolt me-2" style="color:var(--accent)"></i>Quick Actions</div>
                        <div class="card-body d-grid gap-2">
                            <?php if ($user_type === 'customer'): ?>
                                <a href="../customer/browse-services.php?lang=<?php echo $lang; ?>" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Browse Services</a>
                                <a href="../customer/orders.php?lang=<?php echo $lang; ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-list me-1"></i>My Orders</a>
                                <a href="../customer/messages.php?lang=<?php echo $lang; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-comments me-1"></i>Messages</a>
                            <?php elseif ($user_type === 'provider'): ?>
                                <a href="../provider/create-service.php?lang=<?php echo $lang; ?>" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Create Service</a>
                                <a href="../provider/orders.php?lang=<?php echo $lang; ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-shopping-bag me-1"></i>View Orders</a>
                                <a href="../provider/messages.php?lang=<?php echo $lang; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-comments me-1"></i>Messages</a>
                            <?php elseif ($user_type === 'admin'): ?>
                                <a href="../../../browse-services.php" class="btn btn-primary btn-sm"><i class="fas fa-briefcase me-1"></i>View Services</a>
                                <a href="../admin/manage_about.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-images me-1"></i>Manage Photos</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <?php include '../../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    </script>
</body>
</html>
