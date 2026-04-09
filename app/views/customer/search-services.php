<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=search-services&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$lang         = $_GET['lang'] ?? 'en';
$search       = trim($_GET['search'] ?? '');
$category_id  = (int)($_GET['category'] ?? 0);
$min_price    = (float)($_GET['min_price'] ?? 0);
$max_price    = (float)($_GET['max_price'] ?? 0);
$pricing_model= $_GET['pricing_model'] ?? '';
$min_rating   = (int)($_GET['min_rating'] ?? 0);
$sort         = $_GET['sort'] ?? 'relevance';
$ss_page      = max(1, (int)($_GET['sspage'] ?? 1));
$per_page     = 9;
$offset       = ($ss_page - 1) * $per_page;

// Categories for filter dropdown
$cat_stmt = $conn->prepare("SELECT id, name, parent_id FROM service_categories WHERE status = 'active' ORDER BY parent_id, sort_order, name");
$cat_stmt->execute();
$all_cats = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build query
$where  = "WHERE ps.status = 'active'";
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= " AND (ps.title LIKE ? OR ps.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
    $types   .= 'ssss';
}
if ($category_id > 0) {
    $where   .= " AND (ps.category_id = ? OR sc.parent_id = ?)";
    $params[] = $category_id; $params[] = $category_id;
    $types   .= 'ii';
}
if ($min_price > 0) {
    $where   .= " AND ps.base_price >= ?";
    $params[] = $min_price; $types .= 'd';
}
if ($max_price > 0) {
    $where   .= " AND ps.base_price <= ?";
    $params[] = $max_price; $types .= 'd';
}
if ($pricing_model !== '') {
    $where   .= " AND ps.pricing_model = ?";
    $params[] = $pricing_model; $types .= 's';
}
if ($min_rating > 0) {
    $where   .= " AND sp.rating >= ?";
    $params[] = $min_rating; $types .= 'i';
}

$order = match($sort) {
    'price_asc'  => 'ps.base_price ASC',
    'price_desc' => 'ps.base_price DESC',
    'rating'     => 'sp.rating DESC',
    'newest'     => 'ps.created_at DESC',
    'popular'    => 'total_orders DESC',
    default      => 'sp.rating DESC, ps.created_at DESC',
};

$base_sql = "FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN service_categories sc ON ps.category_id = sc.id
    $where";

// Count
$cnt = $conn->prepare("SELECT COUNT(*) as total $base_sql");
if ($params) $cnt->bind_param($types, ...$params);
$cnt->execute();
$total      = $cnt->get_result()->fetch_assoc()['total'];
$total_pages= ceil($total / $per_page);

// Results
$p2 = array_merge($params, [$per_page, $offset]);
$t2 = $types . 'ii';
$stmt = $conn->prepare("SELECT ps.id, ps.title, ps.description, ps.base_price, ps.pricing_model,
    ps.provider_id, sp.rating, sp.verification_status,
    u.first_name, u.last_name, u.profile_image,
    sc.name as category_name,
    (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status = 'completed') as total_orders
    $base_sql ORDER BY $order LIMIT ? OFFSET ?");
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$has_filters = $search !== '' || $category_id > 0 || $min_price > 0 || $max_price > 0 || $pricing_model !== '' || $min_rating > 0;
?>

<div class="content-card">
    <div class="card-header"><i class="fas fa-filter me-2" style="color:var(--accent-color)"></i>Search Services</div>
    <div class="card-body">

        <!-- Filter Form -->
        <form method="GET" action="index.php" class="mb-3">
            <input type="hidden" name="page" value="search-services">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">

            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Keywords, service name, provider..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($all_cats as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo ($cat['parent_id'] ? '&nbsp;&nbsp;↳ ' : '') . htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="sort">
                        <?php foreach (['relevance' => 'Sort: Relevance', 'rating' => 'Sort: Top Rated', 'popular' => 'Sort: Most Popular', 'price_asc' => 'Sort: Price ↑', 'price_desc' => 'Sort: Price ↓', 'newest' => 'Sort: Newest'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $sort === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-0 text-muted" style="font-size:.75rem">Min Price ($)</label>
                    <input type="number" class="form-control form-control-sm" name="min_price"
                           value="<?php echo $min_price ?: ''; ?>" min="0" step="0.01" placeholder="0">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-0 text-muted" style="font-size:.75rem">Max Price ($)</label>
                    <input type="number" class="form-control form-control-sm" name="max_price"
                           value="<?php echo $max_price ?: ''; ?>" min="0" step="0.01" placeholder="Any">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-0 text-muted" style="font-size:.75rem">Pricing Model</label>
                    <select class="form-select form-select-sm" name="pricing_model">
                        <option value="">Any Model</option>
                        <?php foreach (['fixed' => 'Fixed Price', 'hourly' => 'Hourly', 'emergency' => 'Emergency'] as $v => $l): ?>
                            <option value="<?php echo $v; ?>" <?php echo $pricing_model === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-0 text-muted" style="font-size:.75rem">Min Rating</label>
                    <select class="form-select form-select-sm" name="min_rating">
                        <option value="0">Any Rating</option>
                        <?php for ($r = 1; $r <= 5; $r++): ?>
                            <option value="<?php echo $r; ?>" <?php echo $min_rating === $r ? 'selected' : ''; ?>>
                                <?php echo str_repeat('★', $r) . str_repeat('☆', 5 - $r); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <?php if ($has_filters): ?>
                        <a href="index.php?page=search-services&lang=<?php echo $lang; ?>"
                           class="btn btn-outline-secondary btn-sm nav-link-ajax" data-page="search-services">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Results summary -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <small class="text-muted">
                <?php if ($has_filters): ?>
                    <strong><?php echo $total; ?></strong> result<?php echo $total != 1 ? 's' : ''; ?> found
                    <?php if ($search): ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
                <?php else: ?>
                    Use filters above to search services
                <?php endif; ?>
            </small>
        </div>

        <?php if (!$has_filters && empty($services)): ?>
            <div class="text-center py-5">
                <i class="fas fa-filter fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">Start your search</h6>
                <p class="text-muted small">Enter keywords or apply filters to find services.</p>
            </div>
        <?php elseif (empty($services)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No services match your filters</h6>
                <p class="text-muted small">Try broadening your search criteria.</p>
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
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if ($svc['profile_image']): ?>
                                    <img src="../../../<?php echo htmlspecialchars($svc['profile_image']); ?>"
                                         class="rounded-circle" style="width:22px;height:22px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                                         style="width:22px;height:22px;font-size:.6rem;">
                                        <?php echo strtoupper(substr($svc['first_name'],0,1).substr($svc['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted"><?php echo htmlspecialchars($svc['first_name'].' '.$svc['last_name']); ?></small>
                                <?php if ($svc['verification_status'] === 'verified'): ?>
                                    <i class="fas fa-check-circle text-success" style="font-size:.72rem;" title="Verified"></i>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-warning" style="font-size:.75rem;">
                                    <?php $r = round($svc['rating'] ?? 0);
                                    for ($s = 1; $s <= 5; $s++) echo '<i class="fas fa-star'.($s > $r ? ' text-muted' : '').'"></i>'; ?>
                                </span>
                                <small class="text-muted">(<?php echo $svc['total_orders']; ?>)</small>
                                <span class="badge bg-light text-muted border ms-auto" style="font-size:.68rem;">
                                    <?php echo ucfirst($svc['pricing_model']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                            <span class="text-success fw-bold">$<?php echo number_format($svc['base_price'], 2); ?></span>
                            <a href="../customer/order.php?service_id=<?php echo $svc['id']; ?>&lang=<?php echo $lang; ?>"
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-shopping-cart me-1"></i>Order
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1):
                $q = http_build_query(['page' => 'search-services', 'lang' => $lang, 'search' => $search,
                    'category' => $category_id, 'min_price' => $min_price, 'max_price' => $max_price,
                    'pricing_model' => $pricing_model, 'min_rating' => $min_rating, 'sort' => $sort]);
            ?>
            <nav class="mt-3"><ul class="pagination justify-content-center">
                <?php if ($ss_page > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="search-services"
                        href="?<?php echo $q; ?>&sspage=<?php echo $ss_page - 1; ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $ss_page ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="search-services"
                           href="?<?php echo $q; ?>&sspage=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($ss_page < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="search-services"
                        href="?<?php echo $q; ?>&sspage=<?php echo $ss_page + 1; ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
