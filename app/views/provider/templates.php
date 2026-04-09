<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=templates&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
if (!$provider) { echo '<div class="alert alert-danger">Provider not found.</div>'; return; }
$provider_id = $provider['id'];

$success = $error = '';

// Handle actions via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save_template') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price  = (float)($_POST['base_price'] ?? 0);

        $cat_stmt = $conn->prepare("SELECT id FROM service_categories LIMIT 1");
        $cat_stmt->execute();
        $cat = $cat_stmt->get_result()->fetch_assoc();
        $category_id = $cat['id'] ?? null;

        if ($title && $category_id) {
            $stmt = $conn->prepare("INSERT INTO provider_services (provider_id, category_id, title, description, service_type, base_price, status) VALUES (?, ?, ?, ?, 'online_service', ?, 'draft')");
            $stmt->bind_param("iissd", $provider_id, $category_id, $title, $description, $base_price);
            $stmt->execute() ? $success = "Template saved." : $error = "Failed to save template.";
        } else {
            $error = "Title is required.";
        }
        echo json_encode(['ok' => !$error, 'msg' => $error ?: $success]); exit;

    } elseif ($action === 'use_template') {
        $template_id = (int)$_POST['template_id'];
        $stmt = $conn->prepare("SELECT * FROM provider_services WHERE id = ? AND provider_id = ? AND status = 'draft'");
        $stmt->bind_param("ii", $template_id, $provider_id);
        $stmt->execute();
        $tpl = $stmt->get_result()->fetch_assoc();
        if ($tpl) {
            $stmt = $conn->prepare("INSERT INTO provider_services (provider_id, category_id, title, description, service_type, base_price, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("iisssd", $provider_id, $tpl['category_id'], $tpl['title'], $tpl['description'], $tpl['service_type'], $tpl['base_price']);
            $stmt->execute();
        }
        echo json_encode(['ok' => true]); exit;

    } elseif ($action === 'delete_template') {
        $template_id = (int)$_POST['template_id'];
        $stmt = $conn->prepare("DELETE FROM provider_services WHERE id = ? AND provider_id = ? AND status = 'draft'");
        $stmt->bind_param("ii", $template_id, $provider_id);
        $stmt->execute();
        echo json_encode(['ok' => true]); exit;
    }
}

// Fetch draft services (templates)
$stmt = $conn->prepare("SELECT ps.*, sc.name as category_name
    FROM provider_services ps
    LEFT JOIN service_categories sc ON ps.category_id = sc.id
    WHERE ps.provider_id = ? AND ps.status = 'draft'
    ORDER BY ps.created_at DESC");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-copy me-2" style="color:var(--accent-color)"></i>Service Templates</span>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newTemplateModal">
            <i class="fas fa-plus me-1"></i>New Template
        </button>
    </div>
    <div class="card-body">

        <?php if (empty($templates)): ?>
            <div class="text-center py-5">
                <i class="fas fa-copy fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No templates yet</h6>
                <p class="text-muted small">Save service blueprints here to quickly create new services.</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTemplateModal">
                    <i class="fas fa-plus me-1"></i>Create Template
                </button>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($templates as $tpl): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($tpl['title']); ?></h6>
                                <span class="badge bg-secondary ms-1">Draft</span>
                            </div>
                            <?php if ($tpl['category_name']): ?>
                                <small class="text-muted d-block mb-2">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($tpl['category_name']); ?>
                                </small>
                            <?php endif; ?>
                            <p class="card-text text-muted small mb-2">
                                <?php echo htmlspecialchars(mb_strimwidth($tpl['description'] ?? '', 0, 90, '...')); ?>
                            </p>
                            <div class="text-success fw-bold mb-1">$<?php echo number_format($tpl['base_price'], 2); ?></div>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($tpl['created_at'])); ?>
                            </small>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2">
                            <button class="btn btn-success btn-sm flex-fill use-tpl-btn" data-id="<?php echo $tpl['id']; ?>">
                                <i class="fas fa-rocket me-1"></i>Use
                            </button>
                            <button class="btn btn-outline-danger btn-sm del-tpl-btn" data-id="<?php echo $tpl['id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- New Template Modal -->
<div class="modal fade" id="newTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-copy me-2"></i>New Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tplAlert"></div>
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" id="tplTitle" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="tplDesc" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Base Price ($)</label>
                    <input type="number" class="form-control" id="tplPrice" step="0.01" min="0" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveTplBtn"><i class="fas fa-save me-1"></i>Save Template</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const lang = '<?php echo $_GET["lang"] ?? "en"; ?>';
    const url  = 'index.php?page=templates&lang=' + lang;

    function post(data) {
        return fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    }

    // Save template
    document.getElementById('saveTplBtn')?.addEventListener('click', () => {
        const title = document.getElementById('tplTitle').value.trim();
        if (!title) { document.getElementById('tplAlert').innerHTML = '<div class="alert alert-danger py-1">Title is required.</div>'; return; }
        const data = new FormData();
        data.append('action', 'save_template');
        data.append('title', title);
        data.append('description', document.getElementById('tplDesc').value);
        data.append('base_price', document.getElementById('tplPrice').value);
        post(data).then(() => {
            bootstrap.Modal.getInstance(document.getElementById('newTemplateModal'))?.hide();
            if (typeof loadPage === 'function') loadPage('templates', false);
        });
    });

    // Use template
    document.querySelectorAll('.use-tpl-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = new FormData();
            data.append('action', 'use_template');
            data.append('template_id', btn.dataset.id);
            post(data).then(() => {
                if (typeof loadPage === 'function') loadPage('my-services', true);
            });
        });
    });

    // Delete template
    document.querySelectorAll('.del-tpl-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Delete this template?')) return;
            const data = new FormData();
            data.append('action', 'delete_template');
            data.append('template_id', btn.dataset.id);
            post(data).then(() => {
                if (typeof loadPage === 'function') loadPage('templates', false);
            });
        });
    });
})();
</script>
