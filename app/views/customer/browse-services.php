<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=browse-services&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Serve portfolio AJAX fragment
if (isset($_GET['portfolio_provider_id'])) {
    $view_provider_id = (int)$_GET['portfolio_provider_id'];
    include '../shared/portfolio-viewer.php';
    exit;
}

$lang     = $_GET['lang'] ?? 'en';
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$bs_page  = max(1, (int)($_GET['bpage'] ?? 1));
$per_page = 9;
$offset   = ($bs_page - 1) * $per_page;

// Fetch categories for filter
$cat_stmt = $conn->prepare("SELECT id, name FROM service_categories WHERE status = 'active' AND parent_id IS NULL ORDER BY sort_order");
$cat_stmt->execute();
$categories = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build where clause
$where = "WHERE ps.status = 'active'";
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= " AND (ps.title LIKE ? OR ps.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= 'ss';
}
if ($category !== '') {
    $where   .= " AND ps.category_id = ?";
    $params[] = (int)$category;
    $types   .= 'i';
}

// Count
$cnt = $conn->prepare("SELECT COUNT(*) as total FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id $where");
if ($params) $cnt->bind_param($types, ...$params);
$cnt->execute();
$total_services = $cnt->get_result()->fetch_assoc()['total'];
$total_pages    = ceil($total_services / $per_page);

// Services
$p2 = array_merge($params, [$per_page, $offset]);
$t2 = $types . 'ii';
$stmt = $conn->prepare("SELECT ps.id, ps.title, ps.description, ps.base_price, ps.pricing_model,
    ps.provider_id, sp.rating, sp.verification_status,
    u.first_name, u.last_name, u.profile_image,
    sc.name as category_name,
    (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status = 'completed') as total_orders
    FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN service_categories sc ON ps.category_id = sc.id
    $where ORDER BY sp.rating DESC, ps.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content-card">
    <div class="card-header"><i class="fas fa-search me-2" style="color:var(--accent-color)"></i>Browse Services</div>
    <div class="card-body">

        <!-- Search & Filter -->
        <form method="GET" action="index.php" class="row g-2 mb-3">
            <input type="hidden" name="page" value="browse-services">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" name="search"
                       placeholder="Search services..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-search me-1"></i>Search
                </button>
            </div>
        </form>

        <div class="text-muted small mb-3"><?php echo $total_services; ?> service<?php echo $total_services !== 1 ? 's' : ''; ?> found</div>

        <?php if (empty($services)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No services found</h6>
                <p class="text-muted small">Try different keywords or browse all categories.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($services as $svc): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <?php if ($svc['category_name']): ?>
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($svc['category_name']); ?>
                                </small>
                            <?php endif; ?>
                            <h6 class="card-title text-primary mb-1"><?php echo htmlspecialchars($svc['title']); ?></h6>
                            <p class="card-text text-muted small mb-2">
                                <?php echo htmlspecialchars(mb_strimwidth($svc['description'] ?? '', 0, 90, '...')); ?>
                            </p>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ($svc['profile_image']): ?>
                                    <img src="../../../<?php echo htmlspecialchars($svc['profile_image']); ?>"
                                         class="rounded-circle" style="width:24px;height:24px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                         style="width:24px;height:24px;font-size:.65rem;">
                                        <?php echo strtoupper(substr($svc['first_name'],0,1).substr($svc['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted"><?php echo htmlspecialchars($svc['first_name'].' '.$svc['last_name']); ?></small>
                                <?php if ($svc['verification_status'] === 'verified'): ?>
                                    <i class="fas fa-check-circle text-success" title="Verified" style="font-size:.75rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="text-warning small">
                                    <?php
                                    $r = round($svc['rating'] ?? 0);
                                    for ($s = 1; $s <= 5; $s++) echo '<i class="fas fa-star'.($s > $r ? ' text-muted' : '').'"></i>';
                                    ?>
                                </span>
                                <small class="text-muted">(<?php echo $svc['total_orders']; ?> completed)</small>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                            <span class="text-success fw-bold">$<?php echo number_format($svc['base_price'], 2); ?></span>
                            <div class="d-flex gap-1">
                                <button class="btn btn-outline-secondary btn-sm view-portfolio-btn"
                                    data-provider-id="<?php echo $svc['provider_id']; ?>"
                                    data-provider-name="<?php echo htmlspecialchars($svc['first_name'].' '.$svc['last_name']); ?>"
                                    title="View Portfolio">
                                    <i class="fas fa-images"></i>
                                </button>
                                <a href="../customer/order.php?service_id=<?php echo $svc['id']; ?>&lang=<?php echo $lang; ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-shopping-cart me-1"></i>Order
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="mt-3"><ul class="pagination justify-content-center">
                <?php if ($bs_page > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="browse-services"
                        href="?page=browse-services&bpage=<?php echo $bs_page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $bs_page ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="browse-services"
                           href="?page=browse-services&bpage=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($bs_page < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="browse-services"
                        href="?page=browse-services&bpage=<?php echo $bs_page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

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
        const modal = new bootstrap.Modal(document.getElementById('portfolioModal'));
        modal.show();
        fetch('index.php?page=browse-services&portfolio_provider_id=' + btn.dataset.providerId + '&lang=<?php echo $lang; ?>', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(r => r.text()).then(html => {
            document.getElementById('portfolioModalBody').innerHTML = html;
        });
    });
});
</script>
