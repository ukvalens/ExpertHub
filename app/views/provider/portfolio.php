<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=portfolio&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, portfolio FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
if (!$provider) { echo '<div class="alert alert-danger">Provider not found.</div>'; return; }

$provider_id = $provider['id'];
$portfolio   = json_decode($provider['portfolio'] ?? '[]', true) ?: [];

// Handle AJAX POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_item') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tags        = trim($_POST['tags'] ?? '');
        $image_path  = '';

        if (!empty($_FILES['image']['tmp_name'])) {
            $ext       = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed   = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
                $filename   = $user_id . '_' . uniqid() . '.' . $ext;
                $upload_dir = '../../../uploads/portfolio/';
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = 'uploads/portfolio/' . $filename;
                }
            }
        }

        if ($title) {
            $portfolio[] = [
                'id'          => uniqid(),
                'title'       => $title,
                'description' => $description,
                'tags'        => $tags,
                'image'       => $image_path,
                'created_at'  => date('Y-m-d H:i:s'),
            ];
            $json = json_encode($portfolio);
            $stmt = $conn->prepare("UPDATE service_providers SET portfolio = ? WHERE id = ?");
            $stmt->bind_param("si", $json, $provider_id);
            $stmt->execute();
            echo json_encode(['ok' => true]); exit;
        }
        echo json_encode(['ok' => false, 'msg' => 'Title is required.']); exit;

    } elseif ($action === 'delete_item') {
        $item_id  = $_POST['item_id'] ?? '';
        $portfolio = array_values(array_filter($portfolio, fn($i) => $i['id'] !== $item_id));

        // Delete image file if exists
        foreach ($portfolio as $item) {
            // already filtered out
        }
        // Re-fetch to get the deleted item's image before filtering
        $all = json_decode($provider['portfolio'] ?? '[]', true) ?: [];
        foreach ($all as $item) {
            if ($item['id'] === $item_id && !empty($item['image'])) {
                $file = '../../../' . $item['image'];
                if (file_exists($file)) unlink($file);
            }
        }

        $json = json_encode($portfolio);
        $stmt = $conn->prepare("UPDATE service_providers SET portfolio = ? WHERE id = ?");
        $stmt->bind_param("si", $json, $provider_id);
        $stmt->execute();
        echo json_encode(['ok' => true]); exit;
    }
}

$lang = $_GET['lang'] ?? 'en';
?>

<style>
.portfolio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
.portfolio-card { border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); background: #fff; }
.portfolio-card img { width: 100%; height: 150px; object-fit: cover; }
.portfolio-card .no-img { width: 100%; height: 150px; background: linear-gradient(135deg, var(--light-bg), #e0e0e0); display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 2rem; }
.portfolio-card .info { padding: .75rem; }
.portfolio-card .info h6 { font-size: .88rem; font-weight: 600; margin-bottom: .25rem; }
.portfolio-card .info p { font-size: .78rem; color: #666; margin-bottom: .4rem; }
.portfolio-card .tags span { font-size: .7rem; background: var(--light-bg); color: var(--primary-color); border-radius: 20px; padding: 1px 8px; margin-right: 3px; }
.portfolio-card .actions { padding: .5rem .75rem; border-top: 1px solid #f0f0f0; display: flex; justify-content: flex-end; }
</style>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-images me-2" style="color:var(--accent-color)"></i>Portfolio
            <span class="badge bg-secondary ms-1"><?php echo count($portfolio); ?></span>
        </span>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
            <i class="fas fa-plus me-1"></i>Add Item
        </button>
    </div>
    <div class="card-body">

        <?php if (empty($portfolio)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No portfolio items yet</h6>
                <p class="text-muted small">Showcase your past work to attract more customers.</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                    <i class="fas fa-plus me-1"></i>Add First Item
                </button>
            </div>
        <?php else: ?>
            <div class="portfolio-grid">
                <?php foreach (array_reverse($portfolio) as $item): ?>
                <div class="portfolio-card">
                    <?php if (!empty($item['image'])): ?>
                        <img src="../../../<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <?php else: ?>
                        <div class="no-img"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                    <div class="info">
                        <h6><?php echo htmlspecialchars($item['title']); ?></h6>
                        <?php if (!empty($item['description'])): ?>
                            <p><?php echo htmlspecialchars(mb_strimwidth($item['description'], 0, 80, '...')); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($item['tags'])): ?>
                            <div class="tags mb-1">
                                <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                    <span><?php echo htmlspecialchars(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <small class="text-muted"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></small>
                    </div>
                    <div class="actions">
                        <button class="btn btn-outline-danger btn-sm del-portfolio-btn" data-id="<?php echo $item['id']; ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Add Portfolio Modal -->
<div class="modal fade" id="addPortfolioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Portfolio Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="portfolioAlert"></div>
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pTitle" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="pDesc" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tags <small class="text-muted">(comma separated)</small></label>
                    <input type="text" class="form-control" id="pTags" placeholder="e.g. networking, repair, windows">
                </div>
                <div class="mb-3">
                    <label class="form-label">Image <small class="text-muted">(max 5MB)</small></label>
                    <input type="file" class="form-control" id="pImage" accept="image/*">
                    <div id="pPreview" class="mt-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="savePortfolioBtn">
                    <i class="fas fa-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const lang = '<?php echo $lang; ?>';
    const url  = 'index.php?page=portfolio&lang=' + lang;

    // Image preview
    document.getElementById('pImage')?.addEventListener('change', function() {
        const preview = document.getElementById('pPreview');
        if (this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => preview.innerHTML = `<img src="${e.target.result}" style="max-height:120px;border-radius:6px;">`;
            reader.readAsDataURL(this.files[0]);
        } else {
            preview.innerHTML = '';
        }
    });

    // Save portfolio item
    document.getElementById('savePortfolioBtn')?.addEventListener('click', () => {
        const title = document.getElementById('pTitle').value.trim();
        if (!title) {
            document.getElementById('portfolioAlert').innerHTML = '<div class="alert alert-danger py-1">Title is required.</div>';
            return;
        }
        const data = new FormData();
        data.append('action', 'add_item');
        data.append('title', title);
        data.append('description', document.getElementById('pDesc').value);
        data.append('tags', document.getElementById('pTags').value);
        const img = document.getElementById('pImage').files[0];
        if (img) data.append('image', img);

        document.getElementById('savePortfolioBtn').disabled = true;
        fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('addPortfolioModal'))?.hide();
                    if (typeof loadPage === 'function') loadPage('portfolio', false);
                } else {
                    document.getElementById('portfolioAlert').innerHTML = `<div class="alert alert-danger py-1">${res.msg}</div>`;
                    document.getElementById('savePortfolioBtn').disabled = false;
                }
            });
    });

    // Delete item
    document.querySelectorAll('.del-portfolio-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Remove this portfolio item?')) return;
            const data = new FormData();
            data.append('action', 'delete_item');
            data.append('item_id', btn.dataset.id);
            fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(() => { if (typeof loadPage === 'function') loadPage('portfolio', false); });
        });
    });
})();
</script>
