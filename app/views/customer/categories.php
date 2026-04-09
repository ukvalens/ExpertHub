<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=categories&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$lang = $_GET['lang'] ?? 'en';

// Fetch parent categories with service + sub counts
$stmt = $conn->prepare("SELECT sc.id, sc.name, sc.description, sc.icon,
    (SELECT COUNT(*) FROM service_categories sub WHERE sub.parent_id = sc.id AND sub.status = 'active') as sub_count,
    (SELECT COUNT(*) FROM provider_services ps
        JOIN service_categories sub ON ps.category_id = sub.id
        WHERE (sub.id = sc.id OR sub.parent_id = sc.id) AND ps.status = 'active') as service_count
    FROM service_categories sc
    WHERE sc.parent_id IS NULL AND sc.status = 'active'
    ORDER BY sc.sort_order, sc.name");
$stmt->execute();
$parent_cats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch subcategories grouped by parent
$stmt = $conn->prepare("SELECT sc.id, sc.parent_id, sc.name,
    (SELECT COUNT(*) FROM provider_services ps WHERE ps.category_id = sc.id AND ps.status = 'active') as service_count
    FROM service_categories sc
    WHERE sc.parent_id IS NOT NULL AND sc.status = 'active'
    ORDER BY sc.sort_order, sc.name");
$stmt->execute();
$all_subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$subs_by_parent = [];
foreach ($all_subs as $sub) {
    $subs_by_parent[$sub['parent_id']][] = $sub;
}

$icons = [
    'Technology Services' => 'fa-laptop-code',
    'Design & Creative'   => 'fa-paint-brush',
    'Marketing & Business'=> 'fa-chart-bar',
    'Writing & Translation'=> 'fa-pen-nib',
    'Education & Training'=> 'fa-graduation-cap',
    'Administrative'      => 'fa-tasks',
    'Government Services' => 'fa-landmark',
    'Professional Services'=> 'fa-briefcase',
];
$colors = ['primary','success','warning','info','danger','secondary','dark'];
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-th-large me-2" style="color:var(--accent-color)"></i>Service Categories</span>
        <a href="#" class="btn btn-sm btn-primary nav-link-ajax" data-page="browse-services">
            <i class="fas fa-search me-1"></i>Browse All
        </a>
    </div>
    <div class="card-body">

        <?php if (empty($parent_cats)): ?>
            <div class="text-center py-5">
                <i class="fas fa-th-large fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No categories available</h6>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($parent_cats as $i => $cat):
                    $color   = $colors[$i % count($colors)];
                    $fa_icon = $icons[$cat['name']] ?? 'fa-cogs';
                    $subs    = $subs_by_parent[$cat['id']] ?? [];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 category-card" style="cursor:pointer;transition:transform .15s;"
                         onclick="filterByCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="rounded-circle bg-<?php echo $color; ?> bg-opacity-10 d-flex align-items-center justify-content-center"
                                     style="width:44px;height:44px;flex-shrink:0;">
                                    <i class="fas <?php echo $fa_icon; ?> text-<?php echo $color; ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($cat['name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo $cat['service_count']; ?> service<?php echo $cat['service_count'] != 1 ? 's' : ''; ?>
                                        <?php if ($cat['sub_count'] > 0): ?>
                                            · <?php echo $cat['sub_count']; ?> subcategories
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>

                            <?php if ($cat['description']): ?>
                                <p class="text-muted small mb-2">
                                    <?php echo htmlspecialchars(mb_strimwidth($cat['description'], 0, 80, '...')); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($subs)): ?>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <?php foreach (array_slice($subs, 0, 4) as $sub): ?>
                                        <span class="badge bg-light text-dark border"
                                              style="font-size:.7rem;cursor:pointer;"
                                              onclick="event.stopPropagation(); filterByCategory(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['name'], ENT_QUOTES); ?>')">
                                            <?php echo htmlspecialchars($sub['name']); ?>
                                            <span class="text-muted">(<?php echo $sub['service_count']; ?>)</span>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($subs) > 4): ?>
                                        <span class="badge bg-light text-muted border" style="font-size:.7rem;">
                                            +<?php echo count($subs) - 4; ?> more
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent py-1 d-flex justify-content-end">
                            <small class="text-<?php echo $color; ?>">
                                Browse <i class="fas fa-arrow-right ms-1"></i>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function filterByCategory(catId, catName) {
    if (typeof loadPage === 'function') {
        // Navigate to browse-services with category filter via pushState
        const params = 'page=browse-services&lang=<?php echo $lang; ?>&category=' + catId;
        history.pushState({ page: 'browse-services', extra: {} }, '', 'index.php?' + params);
        const mainContent = document.getElementById('mainContent');
        mainContent.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        fetch('index.php?' + params, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                mainContent.innerHTML = html;
                mainContent.querySelectorAll('script').forEach(s => {
                    const ns = document.createElement('script');
                    ns.textContent = s.textContent;
                    document.body.appendChild(ns);
                });
                if (typeof bindAjaxLinks === 'function') bindAjaxLinks();
            });
    }
}

document.querySelectorAll('.category-card').forEach(c => {
    c.addEventListener('mouseenter', () => c.style.transform = 'translateY(-3px)');
    c.addEventListener('mouseleave', () => c.style.transform = '');
});
</script>
