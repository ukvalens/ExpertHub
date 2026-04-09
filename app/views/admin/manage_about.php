<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=manage-photos&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$lang = $_GET['lang'] ?? 'en';

// --- AJAX: upload ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    header('Content-Type: application/json');
    $type    = in_array($_POST['type'] ?? '', ['owner','service']) ? $_POST['type'] : null;
    $file    = $_FILES['photo'];
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!$type || !in_array($ext, $allowed) || $file['size'] > 5242880 || $file['error'] !== 0) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid file or type.']); exit;
    }

    $upload_dir = '../../../uploads/about/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = uniqid() . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        $stmt = $conn->prepare("INSERT INTO about_photos (type, photo_path, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $type, $filename);
        $stmt->execute();
        echo json_encode(['ok' => true, 'id' => $conn->insert_id, 'path' => $filename]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Upload failed.']);
    }
    exit;
}

// --- AJAX: delete ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    header('Content-Type: application/json');
    $id   = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("SELECT photo_path FROM about_photos WHERE id=?");
    $stmt->bind_param("i", $id); $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $path = '../../../uploads/about/' . $row['photo_path'];
        if (file_exists($path)) unlink($path);
        $d = $conn->prepare("DELETE FROM about_photos WHERE id=?");
        $d->bind_param("i", $id); $d->execute();
    }
    echo json_encode(['ok' => true]); exit;
}

// Fetch photos
$owner_photos   = $conn->query("SELECT * FROM about_photos WHERE type='owner'   ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$service_photos = $conn->query("SELECT * FROM about_photos WHERE type='service' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$owner_count    = count($owner_photos);
$service_count  = count($service_photos);
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-images me-2" style="color:var(--accent-color)"></i>Manage About Section</span>
        <div class="d-flex gap-2">
            <span class="badge bg-primary"><i class="fas fa-user me-1"></i><?php echo $owner_count; ?> Team</span>
            <span class="badge bg-success"><i class="fas fa-briefcase me-1"></i><?php echo $service_count; ?> Services</span>
        </div>
    </div>
    <div class="card-body">

        <div id="uploadAlert"></div>

        <div class="row g-4">

            <!-- Owner / Team Photos -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center"
                         style="background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));color:#fff;">
                        <span><i class="fas fa-user-tie me-2"></i>Team / Owner Photos</span>
                        <span class="badge bg-white text-primary" id="ownerCount"><?php echo $owner_count; ?></span>
                    </div>
                    <div class="card-body">
                        <!-- Upload zone -->
                        <div class="upload-zone mb-3" id="ownerZone" data-type="owner"
                             onclick="document.getElementById('ownerFile').click()"
                             ondragover="event.preventDefault();this.classList.add('drag-over')"
                             ondragleave="this.classList.remove('drag-over')"
                             ondrop="handleDrop(event,'owner')">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <div class="small text-muted">Click or drag &amp; drop to upload</div>
                            <div class="text-muted" style="font-size:.72rem;">JPG, PNG, GIF, WEBP · max 5 MB</div>
                            <input type="file" id="ownerFile" accept="image/*" multiple class="d-none"
                                   onchange="uploadFiles(this.files,'owner')">
                        </div>

                        <!-- Grid -->
                        <div class="row g-2" id="ownerGrid">
                            <?php foreach ($owner_photos as $p): ?>
                            <div class="col-6 col-md-4" id="photo-<?php echo $p['id']; ?>">
                                <div class="photo-card position-relative">
                                    <img src="/ExpertHUB/uploads/about/<?php echo htmlspecialchars($p['photo_path']); ?>"
                                         class="img-fluid rounded" style="width:100%;height:110px;object-fit:cover;"
                                         onclick="previewPhoto(this.src)">
                                    <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 delete-btn"
                                            onclick="deletePhoto(<?php echo $p['id']; ?>,'owner')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <div class="text-muted text-center mt-1" style="font-size:.65rem;">
                                        <?php echo date('M j, Y', strtotime($p['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($owner_photos)): ?>
                            <div class="col-12 text-center text-muted py-3 small" id="ownerEmpty">
                                <i class="fas fa-image fa-2x mb-2 d-block"></i>No team photos yet
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Photos -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center"
                         style="background:linear-gradient(135deg,#2e7d32,#388e3c);color:#fff;">
                        <span><i class="fas fa-briefcase me-2"></i>Service Photos</span>
                        <span class="badge bg-white text-success" id="serviceCount"><?php echo $service_count; ?></span>
                    </div>
                    <div class="card-body">
                        <!-- Upload zone -->
                        <div class="upload-zone mb-3" id="serviceZone" data-type="service"
                             onclick="document.getElementById('serviceFile').click()"
                             ondragover="event.preventDefault();this.classList.add('drag-over')"
                             ondragleave="this.classList.remove('drag-over')"
                             ondrop="handleDrop(event,'service')">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <div class="small text-muted">Click or drag &amp; drop to upload</div>
                            <div class="text-muted" style="font-size:.72rem;">JPG, PNG, GIF, WEBP · max 5 MB</div>
                            <input type="file" id="serviceFile" accept="image/*" multiple class="d-none"
                                   onchange="uploadFiles(this.files,'service')">
                        </div>

                        <!-- Grid -->
                        <div class="row g-2" id="serviceGrid">
                            <?php foreach ($service_photos as $p): ?>
                            <div class="col-6 col-md-4" id="photo-<?php echo $p['id']; ?>">
                                <div class="photo-card position-relative">
                                    <img src="/ExpertHUB/uploads/about/<?php echo htmlspecialchars($p['photo_path']); ?>"
                                         class="img-fluid rounded" style="width:100%;height:110px;object-fit:cover;"
                                         onclick="previewPhoto(this.src)">
                                    <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 delete-btn"
                                            onclick="deletePhoto(<?php echo $p['id']; ?>,'service')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <div class="text-muted text-center mt-1" style="font-size:.65rem;">
                                        <?php echo date('M j, Y', strtotime($p['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($service_photos)): ?>
                            <div class="col-12 text-center text-muted py-3 small" id="serviceEmpty">
                                <i class="fas fa-image fa-2x mb-2 d-block"></i>No service photos yet
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <img id="previewImg" src="" class="img-fluid rounded" style="max-height:75vh;">
            </div>
        </div>
    </div>
</div>

<style>
.upload-zone {
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #fafafa;
}
.upload-zone:hover, .upload-zone.drag-over {
    border-color: var(--accent-color);
    background: var(--light-bg);
}
.photo-card img { cursor: zoom-in; transition: transform .15s; }
.photo-card img:hover { transform: scale(1.03); }
.delete-btn { opacity: 0; transition: opacity .15s; padding: 3px 6px; font-size: .7rem; }
.photo-card:hover .delete-btn { opacity: 1; }
</style>

<script>
(function(){
    const base = 'index.php?page=manage-photos&lang=<?php echo $lang; ?>';

    function showAlert(msg, type='success') {
        const el = document.getElementById('uploadAlert');
        el.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show py-2 small">
            <i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'} me-2"></i>${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
        setTimeout(() => el.innerHTML = '', 4000);
    }

    window.uploadFiles = function(files, type) {
        [...files].forEach(file => {
            const fd = new FormData();
            fd.append('photo', file);
            fd.append('type', type);

            // Optimistic preview
            const reader = new FileReader();
            reader.onload = e => {
                const grid = document.getElementById(type + 'Grid');
                const empty = document.getElementById(type + 'Empty');
                if (empty) empty.remove();

                const col = document.createElement('div');
                col.className = 'col-6 col-md-4';
                col.innerHTML = `<div class="photo-card position-relative">
                    <div class="d-flex align-items-center justify-content-center rounded bg-light" style="width:100%;height:110px;">
                        <i class="fas fa-spinner fa-spin text-muted"></i>
                    </div></div>`;
                grid.prepend(col);

                fetch(base, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.ok) {
                            col.id = 'photo-' + d.id;
                            col.innerHTML = `<div class="photo-card position-relative">
                                <img src="/ExpertHUB/uploads/about/${d.path}" class="img-fluid rounded"
                                     style="width:100%;height:110px;object-fit:cover;" onclick="previewPhoto(this.src)">
                                <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 delete-btn"
                                        onclick="deletePhoto(${d.id},'${type}')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <div class="text-muted text-center mt-1" style="font-size:.65rem;">Just now</div>
                            </div>`;
                            const cnt = document.getElementById(type + 'Count');
                            cnt.textContent = parseInt(cnt.textContent) + 1;
                            showAlert('Photo uploaded successfully.');
                        } else {
                            col.remove();
                            showAlert(d.msg || 'Upload failed.', 'danger');
                        }
                    })
                    .catch(() => { col.remove(); showAlert('Upload failed.', 'danger'); });
            };
            reader.readAsDataURL(file);
        });
        // Reset input so same file can be re-selected
        document.getElementById(type + 'File').value = '';
    };

    window.handleDrop = function(e, type) {
        e.preventDefault();
        document.getElementById(type + 'Zone').classList.remove('drag-over');
        uploadFiles(e.dataTransfer.files, type);
    };

    window.deletePhoto = function(id, type) {
        if (!confirm('Delete this photo?')) return;
        fetch(base, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ delete_id: id })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const el = document.getElementById('photo-' + id);
                if (el) el.remove();
                const cnt = document.getElementById(type + 'Count');
                const newVal = Math.max(0, parseInt(cnt.textContent) - 1);
                cnt.textContent = newVal;
                const grid = document.getElementById(type + 'Grid');
                if (newVal === 0 && grid) {
                    grid.innerHTML = `<div class="col-12 text-center text-muted py-3 small" id="${type}Empty">
                        <i class="fas fa-image fa-2x mb-2 d-block"></i>No photos yet</div>`;
                }
                showAlert('Photo deleted.');
            }
        });
    };

    window.previewPhoto = function(src) {
        document.getElementById('previewImg').src = src;
        new bootstrap.Modal(document.getElementById('previewModal')).show();
    };
})();
</script>
