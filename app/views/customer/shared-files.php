<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=shared-files&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

$order_filter = (int)($_GET['order_id'] ?? 0);
$sf_page      = max(1, (int)($_GET['sfpage'] ?? 1));
$per_page     = 12;
$offset       = ($sf_page - 1) * $per_page;

// Orders that have shared files
$ord_stmt = $conn->prepare("SELECT DISTINCT o.id, o.order_number, o.service_title
    FROM order_documents od
    JOIN orders o ON od.order_id = o.id
    WHERE o.customer_id = ? AND od.is_public = 1 AND od.uploaded_by != ?
    ORDER BY o.created_at DESC");
$ord_stmt->bind_param("ii", $user_id, $user_id);
$ord_stmt->execute();
$orders_with_files = $ord_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build query
$where  = "WHERE o.customer_id = ? AND od.is_public = 1 AND od.uploaded_by != ?";
$params = [$user_id, $user_id];
$types  = 'ii';

if ($order_filter > 0) {
    $where   .= " AND o.id = ?";
    $params[] = $order_filter;
    $types   .= 'i';
}

$cnt = $conn->prepare("SELECT COUNT(*) as total FROM order_documents od JOIN orders o ON od.order_id = o.id $where");
$cnt->bind_param($types, ...$params);
$cnt->execute();
$total_files = $cnt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_files / $per_page);

$p2 = array_merge($params, [$per_page, $offset]);
$t2 = $types . 'ii';
$stmt = $conn->prepare("SELECT od.id, od.file_name, od.file_path, od.file_size, od.mime_type,
    od.document_type, od.description, od.created_at,
    o.id as order_id, o.order_number, o.service_title,
    u.first_name, u.last_name
    FROM order_documents od
    JOIN orders o ON od.order_id = o.id
    JOIN users u ON od.uploaded_by = u.id
    $where ORDER BY od.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$doc_types = ['requirement'=>'Requirement','deliverable'=>'Deliverable','diagnostic'=>'Diagnostic','report'=>'Report','invoice'=>'Invoice','other'=>'Other'];

function sf_icon($mime) {
    $mime = $mime ?? '';
    if (str_contains($mime,'pdf'))   return ['fa-file-pdf','text-danger'];
    if (str_contains($mime,'word') || str_contains($mime,'doc')) return ['fa-file-word','text-primary'];
    if (str_contains($mime,'sheet') || str_contains($mime,'excel')) return ['fa-file-excel','text-success'];
    if (str_contains($mime,'image')) return ['fa-file-image','text-info'];
    if (str_contains($mime,'zip') || str_contains($mime,'compressed')) return ['fa-file-archive','text-warning'];
    return ['fa-file-alt','text-secondary'];
}

function sf_size($b) {
    if (!$b) return '—';
    if ($b < 1024) return $b.' B';
    if ($b < 1048576) return round($b/1024,1).' KB';
    return round($b/1048576,1).' MB';
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-share-alt me-2" style="color:var(--accent-color)"></i>Shared Files
            <span class="badge bg-secondary ms-1"><?php echo $total_files; ?></span>
        </span>
        <?php if ($order_filter): ?>
            <a href="?page=shared-files&lang=<?php echo $lang; ?>"
               class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="shared-files">
                <i class="fas fa-times me-1"></i>Clear Filter
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <!-- Order filter -->
        <?php if (!empty($orders_with_files)): ?>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <a href="?page=shared-files&lang=<?php echo $lang; ?>"
               class="btn btn-sm <?php echo !$order_filter ? 'btn-secondary' : 'btn-outline-secondary'; ?> nav-link-ajax" data-page="shared-files">
                All Orders
            </a>
            <?php foreach ($orders_with_files as $o): ?>
                <a href="?page=shared-files&order_id=<?php echo $o['id']; ?>&lang=<?php echo $lang; ?>"
                   class="btn btn-sm <?php echo $order_filter == $o['id'] ? 'btn-primary' : 'btn-outline-primary'; ?> nav-link-ajax" data-page="shared-files">
                    #<?php echo htmlspecialchars($o['order_number']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($files)): ?>
            <div class="text-center py-5">
                <i class="fas fa-share-alt fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No shared files yet</h6>
                <p class="text-muted small">Files shared by your providers on active orders will appear here.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($files as $f):
                    [$icon, $icon_color] = sf_icon($f['mime_type']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <i class="fas <?php echo $icon; ?> <?php echo $icon_color; ?> fa-2x flex-shrink-0 mt-1"></i>
                                <div style="min-width:0;">
                                    <div class="fw-semibold text-truncate" style="font-size:.85rem;"
                                         title="<?php echo htmlspecialchars($f['file_name']); ?>">
                                        <?php echo htmlspecialchars($f['file_name']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo sf_size($f['file_size']); ?></small>
                                </div>
                            </div>

                            <div class="mb-1">
                                <span class="badge bg-light text-dark border" style="font-size:.7rem;">
                                    <?php echo $doc_types[$f['document_type']] ?? ucfirst($f['document_type']); ?>
                                </span>
                            </div>

                            <?php if ($f['description']): ?>
                                <p class="text-muted small mb-1">
                                    <?php echo htmlspecialchars(mb_strimwidth($f['description'], 0, 70, '...')); ?>
                                </p>
                            <?php endif; ?>

                            <small class="text-muted d-block">
                                <i class="fas fa-user-tie me-1"></i>
                                <?php echo htmlspecialchars($f['first_name'].' '.$f['last_name']); ?>
                            </small>
                            <small class="text-muted d-block">
                                <i class="fas fa-briefcase me-1"></i>
                                <?php echo htmlspecialchars(mb_strimwidth($f['service_title'], 0, 40, '...')); ?>
                                <span class="text-muted">(#<?php echo htmlspecialchars($f['order_number']); ?>)</span>
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('M j, Y g:i A', strtotime($f['created_at'])); ?>
                            </small>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="/ExpertHUB/<?php echo htmlspecialchars($f['file_path']); ?>"
                               target="_blank" download
                               class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-download me-1"></i>Download
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="mt-3"><ul class="pagination justify-content-center">
                <?php if ($sf_page > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="shared-files"
                        href="?page=shared-files&order_id=<?php echo $order_filter; ?>&sfpage=<?php echo $sf_page-1; ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $sf_page ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="shared-files"
                           href="?page=shared-files&order_id=<?php echo $order_filter; ?>&sfpage=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($sf_page < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="shared-files"
                        href="?page=shared-files&order_id=<?php echo $order_filter; ?>&sfpage=<?php echo $sf_page+1; ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
