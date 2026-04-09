<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=documents&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

// AJAX: upload document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    $doc_type = $_POST['doc_type'] ?? 'other';

    // Verify order belongs to customer
    $chk = $conn->prepare("SELECT id FROM orders WHERE id = ? AND customer_id = ?");
    $chk->bind_param("ii", $order_id, $user_id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid order.']); exit;
    }

    if (!empty($_FILES['file']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','txt','jpg','jpeg','png','zip','xls','xlsx'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['ok' => false, 'msg' => 'File type not allowed.']); exit;
        }
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'msg' => 'File too large (max 10MB).']); exit;
        }
        $dir  = '../../../uploads/documents/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $fname = $user_id . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $fname)) {
            $path = 'uploads/documents/' . $fname;
            $name = $_FILES['file']['name'];
            $size = $_FILES['file']['size'];
            $mime = $_FILES['file']['type'];
            $stmt = $conn->prepare("INSERT INTO order_documents (order_id, uploaded_by, document_type, file_name, file_path, file_size, mime_type, description, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("iisssiss", $order_id, $user_id, $doc_type, $name, $path, $size, $mime, $desc);
            $stmt->execute();
            echo json_encode(['ok' => true]); exit;
        }
    }
    echo json_encode(['ok' => false, 'msg' => 'Upload failed.']); exit;
}

// AJAX: delete document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $doc_id = (int)$_POST['doc_id'];
    $stmt   = $conn->prepare("SELECT od.file_path FROM order_documents od JOIN orders o ON od.order_id = o.id WHERE od.id = ? AND od.uploaded_by = ? AND o.customer_id = ?");
    $stmt->bind_param("iii", $doc_id, $user_id, $user_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    if ($doc) {
        $file = '../../../' . $doc['file_path'];
        if (file_exists($file)) unlink($file);
        $conn->prepare("DELETE FROM order_documents WHERE id = ?")->bind_param("i", $doc_id) && $conn->prepare("DELETE FROM order_documents WHERE id = ?")->execute();
        $del = $conn->prepare("DELETE FROM order_documents WHERE id = ?");
        $del->bind_param("i", $doc_id);
        $del->execute();
    }
    echo json_encode(['ok' => true]); exit;
}

// Filter
$type_filter  = $_GET['type'] ?? 'all';
$doc_page     = max(1, (int)($_GET['dpage'] ?? 1));
$per_page     = 12;
$offset       = ($doc_page - 1) * $per_page;

$where  = "WHERE o.customer_id = ?";
$params = [$user_id];
$types  = 'i';
if ($type_filter !== 'all') {
    $where   .= " AND od.document_type = ?";
    $params[] = $type_filter;
    $types   .= 's';
}

$cnt = $conn->prepare("SELECT COUNT(*) as total FROM order_documents od JOIN orders o ON od.order_id = o.id $where");
$cnt->bind_param($types, ...$params);
$cnt->execute();
$total_docs  = $cnt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_docs / $per_page);

$p2 = array_merge($params, [$per_page, $offset]);
$t2 = $types . 'ii';
$stmt = $conn->prepare("SELECT od.id, od.file_name, od.file_path, od.file_size, od.mime_type,
    od.document_type, od.description, od.created_at, od.uploaded_by,
    o.order_number, o.service_title
    FROM order_documents od
    JOIN orders o ON od.order_id = o.id
    $where ORDER BY od.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($t2, ...$p2);
$stmt->execute();
$docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Customer orders for upload modal
$ord_stmt = $conn->prepare("SELECT id, order_number, service_title FROM orders WHERE customer_id = ? AND status IN ('accepted','in_progress','completed') ORDER BY created_at DESC");
$ord_stmt->bind_param("i", $user_id);
$ord_stmt->execute();
$my_orders = $ord_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Type counts
$tc_stmt = $conn->prepare("SELECT od.document_type, COUNT(*) as cnt FROM order_documents od JOIN orders o ON od.order_id = o.id WHERE o.customer_id = ? GROUP BY od.document_type");
$tc_stmt->bind_param("i", $user_id);
$tc_stmt->execute();
$type_counts = [];
foreach ($tc_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $type_counts[$r['document_type']] = $r['cnt'];
}

$doc_types = ['requirement' => 'Requirement', 'deliverable' => 'Deliverable', 'diagnostic' => 'Diagnostic', 'report' => 'Report', 'invoice' => 'Invoice', 'other' => 'Other'];

function file_icon($mime) {
    if (str_contains($mime, 'pdf'))   return 'fa-file-pdf text-danger';
    if (str_contains($mime, 'word') || str_contains($mime, 'doc')) return 'fa-file-word text-primary';
    if (str_contains($mime, 'sheet') || str_contains($mime, 'excel')) return 'fa-file-excel text-success';
    if (str_contains($mime, 'image')) return 'fa-file-image text-info';
    if (str_contains($mime, 'zip') || str_contains($mime, 'compressed')) return 'fa-file-archive text-warning';
    return 'fa-file-alt text-secondary';
}

function fmt_size($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024, 1) . ' KB';
    return round($bytes/1048576, 1) . ' MB';
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-alt me-2" style="color:var(--accent-color)"></i>My Documents
            <span class="badge bg-secondary ms-1"><?php echo $total_docs; ?></span>
        </span>
        <?php if (!empty($my_orders)): ?>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
            <i class="fas fa-upload me-1"></i>Upload
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <!-- Type filter tabs -->
        <div class="d-flex flex-wrap gap-1 mb-3">
            <a href="?page=documents&lang=<?php echo $lang; ?>"
               class="btn btn-sm <?php echo $type_filter === 'all' ? 'btn-secondary' : 'btn-outline-secondary'; ?> nav-link-ajax" data-page="documents">
                All <span class="badge bg-white text-dark ms-1"><?php echo $total_docs; ?></span>
            </a>
            <?php foreach ($doc_types as $key => $label): ?>
                <?php $cnt_val = $type_counts[$key] ?? 0; if (!$cnt_val && $type_filter !== $key) continue; ?>
                <a href="?page=documents&type=<?php echo $key; ?>&lang=<?php echo $lang; ?>"
                   class="btn btn-sm <?php echo $type_filter === $key ? 'btn-primary' : 'btn-outline-primary'; ?> nav-link-ajax" data-page="documents">
                    <?php echo $label; ?> <span class="badge bg-white text-dark ms-1"><?php echo $cnt_val; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($docs)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No documents found</h6>
                <p class="text-muted small">Documents shared on your orders will appear here.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($docs as $doc): ?>
                <div class="col-md-6 col-lg-4" id="doc-<?php echo $doc['id']; ?>">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <i class="fas <?php echo file_icon($doc['mime_type'] ?? ''); ?> fa-2x flex-shrink-0 mt-1"></i>
                                <div class="min-width-0">
                                    <div class="fw-semibold text-truncate" style="font-size:.85rem;" title="<?php echo htmlspecialchars($doc['file_name']); ?>">
                                        <?php echo htmlspecialchars($doc['file_name']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo fmt_size($doc['file_size'] ?? 0); ?></small>
                                </div>
                            </div>
                            <div class="mb-1">
                                <span class="badge bg-light text-dark border" style="font-size:.7rem;">
                                    <?php echo $doc_types[$doc['document_type']] ?? ucfirst($doc['document_type']); ?>
                                </span>
                            </div>
                            <?php if ($doc['description']): ?>
                                <p class="text-muted small mb-1"><?php echo htmlspecialchars(mb_strimwidth($doc['description'], 0, 60, '...')); ?></p>
                            <?php endif; ?>
                            <small class="text-muted d-block">
                                <i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($doc['service_title']); ?>
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($doc['created_at'])); ?>
                            </small>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2">
                            <a href="/ExpertHUB/<?php echo htmlspecialchars($doc['file_path']); ?>"
                               target="_blank" class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="fas fa-download me-1"></i>Download
                            </a>
                            <?php if ($doc['uploaded_by'] == $user_id): ?>
                            <button class="btn btn-outline-danger btn-sm del-doc-btn" data-id="<?php echo $doc['id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="mt-3"><ul class="pagination justify-content-center">
                <?php if ($doc_page > 1): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="documents"
                        href="?page=documents&type=<?php echo $type_filter; ?>&dpage=<?php echo $doc_page-1; ?>">Prev</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $doc_page ? 'active' : ''; ?>">
                        <a class="page-link nav-link-ajax" data-page="documents"
                           href="?page=documents&type=<?php echo $type_filter; ?>&dpage=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($doc_page < $total_pages): ?>
                    <li class="page-item"><a class="page-link nav-link-ajax" data-page="documents"
                        href="?page=documents&type=<?php echo $type_filter; ?>&dpage=<?php echo $doc_page+1; ?>">Next</a></li>
                <?php endif; ?>
            </ul></nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="uploadAlert"></div>
                <div class="mb-3">
                    <label class="form-label">Order <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="uploadOrderId" required>
                        <option value="">Select order...</option>
                        <?php foreach ($my_orders as $o): ?>
                            <option value="<?php echo $o['id']; ?>">#<?php echo htmlspecialchars($o['order_number']); ?> — <?php echo htmlspecialchars($o['service_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Document Type</label>
                    <select class="form-select form-select-sm" id="uploadDocType">
                        <?php foreach ($doc_types as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">File <span class="text-danger">*</span></label>
                    <input type="file" class="form-control form-control-sm" id="uploadFile"
                           accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.xls,.xlsx">
                    <small class="text-muted">Max 10MB · PDF, DOC, DOCX, TXT, JPG, PNG, ZIP, XLS</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control form-control-sm" id="uploadDesc" placeholder="Optional note...">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="uploadSubmitBtn">
                    <i class="fas fa-upload me-1"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const lang = '<?php echo $lang; ?>';
    const url  = 'index.php?page=documents&lang=' + lang;

    // Upload
    document.getElementById('uploadSubmitBtn')?.addEventListener('click', () => {
        const orderId = document.getElementById('uploadOrderId').value;
        const file    = document.getElementById('uploadFile').files[0];
        if (!orderId || !file) {
            document.getElementById('uploadAlert').innerHTML = '<div class="alert alert-danger py-1">Order and file are required.</div>';
            return;
        }
        const data = new FormData();
        data.append('action',      'upload');
        data.append('order_id',    orderId);
        data.append('doc_type',    document.getElementById('uploadDocType').value);
        data.append('description', document.getElementById('uploadDesc').value);
        data.append('file',        file);

        document.getElementById('uploadSubmitBtn').disabled = true;
        fetch(url, { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(res => {
                if (res.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('uploadDocModal'))?.hide();
                    if (typeof loadPage === 'function') loadPage('documents', false);
                } else {
                    document.getElementById('uploadAlert').innerHTML = `<div class="alert alert-danger py-1">${res.msg}</div>`;
                    document.getElementById('uploadSubmitBtn').disabled = false;
                }
            });
    });

    // Delete
    document.querySelectorAll('.del-doc-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Delete this document?')) return;
            const data = new FormData();
            data.append('action', 'delete');
            data.append('doc_id', btn.dataset.id);
            fetch(url, { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'} })
                .then(() => {
                    const card = document.getElementById('doc-' + btn.dataset.id);
                    if (card) card.remove();
                });
        });
    });
})();
</script>
