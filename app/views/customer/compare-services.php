<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=compare-services&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$lang = $_GET['lang'] ?? 'en';

// IDs to compare (up to 3)
$ids = array_filter(array_map('intval', (array)($_GET['ids'] ?? [])));
$ids = array_slice(array_values($ids), 0, 3);

// Fetch services for comparison
$compared = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("SELECT ps.id, ps.title, ps.description, ps.base_price, ps.pricing_model,
        ps.estimated_duration, ps.deliverables, ps.service_scope,
        ps.provider_id, sp.rating, sp.total_reviews, sp.verification_status,
        sp.experience_years, sp.completion_rate,
        u.first_name, u.last_name, u.profile_image,
        sc.name as category_name,
        (SELECT COUNT(*) FROM orders WHERE provider_id = sp.id AND status = 'completed') as total_orders,
        (SELECT COUNT(*) FROM reviews WHERE provider_id = sp.id AND status = 'active') as review_count
        FROM provider_services ps
        JOIN service_providers sp ON ps.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN service_categories sc ON ps.category_id = sc.id
        WHERE ps.id IN ($placeholders) AND ps.status = 'active'");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $compared = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Search for services to add
$search  = trim($_GET['search'] ?? '');
$results = [];
if ($search !== '') {
    $stmt = $conn->prepare("SELECT ps.id, ps.title, ps.base_price, u.first_name, u.last_name, sp.rating
        FROM provider_services ps
        JOIN service_providers sp ON ps.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE ps.status = 'active' AND (ps.title LIKE ? OR ps.description LIKE ?)
        ORDER BY sp.rating DESC LIMIT 10");
    $like = "%$search%";
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$ids_str = implode(',', $ids);
?>

<style>
.compare-table th { background: var(--light-bg); font-size: .82rem; white-space: nowrap; }
.compare-table td { font-size: .82rem; vertical-align: top; }
.compare-table .svc-header { text-align: center; padding: .75rem .5rem; }
.compare-table .svc-header h6 { font-size: .85rem; margin-bottom: .2rem; }
.compare-winner { background: rgba(0,191,166,.08); }
.star-row i { font-size: .72rem; }
</style>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-balance-scale me-2" style="color:var(--accent-color)"></i>Compare Services</span>
        <?php if (!empty($compared)): ?>
            <a href="index.php?page=compare-services&lang=<?php echo $lang; ?>"
               class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="compare-services">
                <i class="fas fa-times me-1"></i>Clear
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <!-- Search to add services -->
        <form method="GET" action="index.php" class="mb-3">
            <input type="hidden" name="page" value="compare-services">
            <input type="hidden" name="lang" value="<?php echo $lang; ?>">
            <?php foreach ($ids as $id): ?>
                <input type="hidden" name="ids[]" value="<?php echo $id; ?>">
            <?php endforeach; ?>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" name="search"
                       placeholder="Search a service to add to comparison..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>

        <!-- Search results -->
        <?php if (!empty($results)): ?>
        <div class="mb-3">
            <small class="text-muted d-block mb-2">Click to add to comparison (max 3):</small>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($results as $r):
                    $already = in_array($r['id'], $ids);
                    $full    = count($ids) >= 3 && !$already;
                    $new_ids = $already ? array_diff($ids, [$r['id']]) : array_merge($ids, [$r['id']]);
                    $new_ids = array_slice(array_values($new_ids), 0, 3);
                    $q = http_build_query(['page' => 'compare-services', 'lang' => $lang, 'ids' => $new_ids]);
                ?>
                <a href="index.php?<?php echo $q; ?>"
                   class="btn btn-sm <?php echo $already ? 'btn-success' : ($full ? 'btn-outline-secondary disabled' : 'btn-outline-primary'); ?> nav-link-ajax"
                   data-page="compare-services">
                    <?php if ($already): ?><i class="fas fa-check me-1"></i><?php endif; ?>
                    <?php echo htmlspecialchars($r['title']); ?>
                    <span class="text-muted ms-1">$<?php echo number_format($r['base_price'], 2); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($compared)): ?>
            <div class="text-center py-5">
                <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No services selected</h6>
                <p class="text-muted small">Search for services above to compare up to 3 side by side.</p>
                <a href="index.php?page=browse-services&lang=<?php echo $lang; ?>"
                   class="btn btn-primary btn-sm nav-link-ajax" data-page="browse-services">
                    <i class="fas fa-search me-1"></i>Browse Services
                </a>
            </div>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered compare-table">
                <thead>
                    <tr>
                        <th style="width:160px">Feature</th>
                        <?php foreach ($compared as $svc): ?>
                        <th class="svc-header">
                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                <?php if ($svc['profile_image']): ?>
                                    <img src="../../../<?php echo htmlspecialchars($svc['profile_image']); ?>"
                                         class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                                         style="width:28px;height:28px;font-size:.65rem;">
                                        <?php echo strtoupper(substr($svc['first_name'],0,1).substr($svc['last_name'],0,1)); ?>
                                    </div>
                                <?php endif; ?>
                                <small><?php echo htmlspecialchars($svc['first_name'].' '.$svc['last_name']); ?></small>
                                <?php if ($svc['verification_status'] === 'verified'): ?>
                                    <i class="fas fa-check-circle text-success" style="font-size:.7rem;"></i>
                                <?php endif; ?>
                            </div>
                            <h6><?php echo htmlspecialchars($svc['title']); ?></h6>
                            <?php if ($svc['category_name']): ?>
                                <span class="badge bg-light text-muted border" style="font-size:.68rem;">
                                    <?php echo htmlspecialchars($svc['category_name']); ?>
                                </span>
                            <?php endif; ?>
                            <?php
                            // Remove button
                            $rem = array_diff($ids, [$svc['id']]);
                            $q   = http_build_query(['page' => 'compare-services', 'lang' => $lang, 'ids' => array_values($rem)]);
                            ?>
                            <div class="mt-1">
                                <a href="index.php?<?php echo $q; ?>"
                                   class="btn btn-outline-danger btn-sm py-0 nav-link-ajax" data-page="compare-services"
                                   style="font-size:.7rem;">
                                    <i class="fas fa-times me-1"></i>Remove
                                </a>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Helper: highlight best value cell
                    $prices   = array_column($compared, 'base_price');
                    $ratings  = array_column($compared, 'rating');
                    $orders   = array_column($compared, 'total_orders');
                    $min_price_val = min($prices);
                    $max_rating    = max($ratings);
                    $max_orders    = max($orders);
                    ?>

                    <!-- Price -->
                    <tr>
                        <th>Price</th>
                        <?php foreach ($compared as $svc): ?>
                        <td class="text-center <?php echo $svc['base_price'] == $min_price_val ? 'compare-winner' : ''; ?>">
                            <strong class="text-success">$<?php echo number_format($svc['base_price'], 2); ?></strong>
                            <small class="text-muted d-block"><?php echo ucfirst($svc['pricing_model']); ?></small>
                            <?php if ($svc['base_price'] == $min_price_val && count($compared) > 1): ?>
                                <span class="badge bg-success" style="font-size:.65rem;">Best Price</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Rating -->
                    <tr>
                        <th>Rating</th>
                        <?php foreach ($compared as $svc): ?>
                        <td class="text-center <?php echo $svc['rating'] == $max_rating && $max_rating > 0 ? 'compare-winner' : ''; ?>">
                            <div class="star-row">
                                <?php $r = round($svc['rating'] ?? 0);
                                for ($s = 1; $s <= 5; $s++) echo '<i class="fas fa-star'.($s > $r ? ' text-muted' : ' text-warning').'"></i>'; ?>
                            </div>
                            <small class="text-muted"><?php echo number_format($svc['rating'], 1); ?> (<?php echo $svc['review_count']; ?> reviews)</small>
                            <?php if ($svc['rating'] == $max_rating && $max_rating > 0 && count($compared) > 1): ?>
                                <span class="badge bg-warning text-dark" style="font-size:.65rem;">Top Rated</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Completed Orders -->
                    <tr>
                        <th>Completed Orders</th>
                        <?php foreach ($compared as $svc): ?>
                        <td class="text-center <?php echo $svc['total_orders'] == $max_orders && $max_orders > 0 ? 'compare-winner' : ''; ?>">
                            <?php echo $svc['total_orders']; ?>
                            <?php if ($svc['total_orders'] == $max_orders && $max_orders > 0 && count($compared) > 1): ?>
                                <span class="badge bg-info" style="font-size:.65rem;">Most Popular</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Experience -->
                    <tr>
                        <th>Experience</th>
                        <?php foreach ($compared as $svc): ?>
                        <td class="text-center">
                            <?php echo $svc['experience_years'] ? $svc['experience_years'].' yrs' : '—'; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Completion Rate -->
                    <tr>
                        <th>Completion Rate</th>
                        <?php foreach ($compared as $svc): ?>
                        <td class="text-center">
                            <?php echo $svc['completion_rate'] ? number_format($svc['completion_rate'], 0).'%' : '—'; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Est. Duration -->
                    <tr>
                        <th>Est. Duration</th>
                        <?php foreach ($compared as $svc): ?>
                        <td class="text-center">
                            <?php if ($svc['estimated_duration']): ?>
                                <?php $d = $svc['estimated_duration'];
                                echo $d < 60 ? $d.'m' : round($d/60, 1).'h'; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Description -->
                    <tr>
                        <th>Description</th>
                        <?php foreach ($compared as $svc): ?>
                        <td><?php echo htmlspecialchars(mb_strimwidth($svc['description'] ?? '', 0, 120, '...')); ?></td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Deliverables -->
                    <tr>
                        <th>Deliverables</th>
                        <?php foreach ($compared as $svc): ?>
                        <td><?php echo $svc['deliverables'] ? htmlspecialchars(mb_strimwidth($svc['deliverables'], 0, 100, '...')) : '<span class="text-muted">—</span>'; ?></td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Action -->
                    <tr>
                        <th>Action</th>
                        <?php foreach ($compared as $svc): ?>
                        <td class="text-center">
                            <a href="../customer/order.php?service_id=<?php echo $svc['id']; ?>&lang=<?php echo $lang; ?>"
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-shopping-cart me-1"></i>Order
                            </a>
                        </td>
                        <?php endforeach; ?>
                    </tr>

                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div>
</div>
