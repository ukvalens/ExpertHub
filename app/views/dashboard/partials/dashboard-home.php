<?php
// This partial is included by dashboard/index.php — $user, $user_type, $stats are already available
?>
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
            <div class="card-header"><i class="fas fa-history me-2" style="color:var(--accent-color)"></i>Recent Activity</div>
            <div class="card-body text-center py-4">
                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                <p class="text-muted small mb-0">No recent activity to display</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="content-card">
            <div class="card-header"><i class="fas fa-bolt me-2" style="color:var(--accent-color)"></i>Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <?php if ($user_type === 'customer'): ?>
                    <a href="#" class="btn btn-primary btn-sm nav-link-ajax" data-page="browse-services"><i class="fas fa-search me-1"></i>Browse Services</a>
                    <a href="#" class="btn btn-outline-primary btn-sm nav-link-ajax" data-page="orders"><i class="fas fa-list me-1"></i>My Orders</a>
                    <a href="#" class="btn btn-outline-secondary btn-sm nav-link-ajax" data-page="messages"><i class="fas fa-comments me-1"></i>Messages</a>
                <?php elseif ($user_type === 'provider'): ?>
                    <a href="#" class="btn btn-success btn-sm nav-link-ajax" data-page="create-service"><i class="fas fa-plus me-1"></i>Create Service</a>
                    <a href="#" class="btn btn-outline-primary btn-sm nav-link-ajax" data-page="provider-orders"><i class="fas fa-shopping-bag me-1"></i>View Orders</a>
                    <a href="#" class="btn btn-outline-secondary btn-sm nav-link-ajax" data-page="provider-messages"><i class="fas fa-comments me-1"></i>Messages</a>
                <?php elseif ($user_type === 'admin'): ?>
                    <a href="#" class="btn btn-primary btn-sm nav-link-ajax" data-page="browse-services"><i class="fas fa-briefcase me-1"></i>View Services</a>
                    <a href="#" class="btn btn-outline-primary btn-sm nav-link-ajax" data-page="manage-photos"><i class="fas fa-images me-1"></i>Manage Photos</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
