<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=customer-templates&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo '<div class="alert alert-danger">Access denied.</div>'; return;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';

// Ensure active cart exists (used as template store)
$stmt = $conn->prepare("SELECT id FROM shopping_carts WHERE customer_id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart = $stmt->get_result()->fetch_assoc();
if (!$cart) {
    $stmt = $conn->prepare("INSERT INTO shopping_carts (customer_id, status) VALUES (?, 'active')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_id = $conn->insert_id;
} else {
    $cart_id = $cart['id'];
}

// AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');

        if ($name) {
            $tpl = json_encode([
                'template_name' => $name,
                'description'   => $description,
                'phone'         => $phone,
                'address'       => $address,
                'notes'         => $notes,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            // Use service_id=0 as placeholder, selected_package='template' as marker
            $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, service_id, selected_package, custom_requirements) VALUES (?, 0, 'template', ?)");
            $stmt->bind_param("is", $cart_id, $tpl);
            $stmt->execute();
            echo json_encode(['ok' => true]); exit;
        }
        echo json_encode(['ok' => false, 'msg' => 'Name is required.']); exit;
    }

    if ($action === 'delete') {
        $item_id = (int)$_POST['item_id'];
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ? AND selected_package = 'template'");
        $stmt->bind_param("ii", $item_id, $cart_id);
        $stmt->execute();
        echo json_encode(['ok' => true]); exit;
    }

    if ($action === 'edit') {
        $item_id     = (int)$_POST['item_id'];
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');

        // Get existing to preserve created_at
        $stmt = $conn->prepare("SELECT custom_requirements FROM cart_items WHERE id = ? AND cart_id = ? AND selected_package = 'template'");
        $stmt->bind_param("ii", $item_id, $cart_id);
        $stmt->execute();
        $existing = json_decode($stmt->get_result()->fetch_assoc()['custom_requirements'] ?? '{}', true);

        $tpl = json_encode([
            'template_name' => $name,
            'description'   => $description,
            'phone'         => $phone,
            'address'       => $address,
            'notes'         => $notes,
            'created_at'    => $existing['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $stmt = $conn->prepare("UPDATE cart_items SET custom_requirements = ? WHERE id = ? AND cart_id = ?");
        $stmt->bind_param("sii", $tpl, $item_id, $cart_id);
        $stmt->execute();
        echo json_encode(['ok' => true]); exit;
    }
}

// Fetch templates
$stmt = $conn->prepare("SELECT id, custom_requirements, added_at FROM cart_items
    WHERE cart_id = ? AND selected_package = 'template'
    ORDER BY added_at DESC");
$stmt->bind_param("i", $cart_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$templates = [];
foreach ($rows as $row) {
    $data = json_decode($row['custom_requirements'], true) ?? [];
    $templates[] = array_merge($data, ['id' => $row['id'], 'added_at' => $row['added_at']]);
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-copy me-2" style="color:var(--accent-color)"></i>Order Templates
            <span class="badge bg-secondary ms-1"><?php echo count($templates); ?></span>
        </span>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#tplModal" onclick="openTplModal()">
            <i class="fas fa-plus me-1"></i>New Template
        </button>
    </div>
    <div class="card-body">

        <p class="text-muted small mb-3">
            <i class="fas fa-info-circle me-1"></i>
            Save your common order details here and reuse them when placing new orders — no need to retype every time.
        </p>

        <?php if (empty($templates)): ?>
            <div class="text-center py-5">
                <i class="fas fa-copy fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No templates yet</h6>
                <p class="text-muted small">Save your common order requirements to speed up future orders.</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tplModal" onclick="openTplModal()">
                    <i class="fas fa-plus me-1"></i>Create Template
                </button>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($templates as $tpl): ?>
                <div class="col-md-6 col-lg-4" id="tpl-<?php echo $tpl['id']; ?>">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 text-primary"><?php echo htmlspecialchars($tpl['template_name'] ?? 'Untitled'); ?></h6>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($tpl['added_at'])); ?></small>
                            </div>

                            <?php if (!empty($tpl['description'])): ?>
                                <p class="text-muted small mb-1">
                                    <i class="fas fa-clipboard me-1"></i>
                                    <?php echo htmlspecialchars(mb_strimwidth($tpl['description'], 0, 80, '...')); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($tpl['phone'])): ?>
                                <p class="text-muted small mb-1">
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($tpl['phone']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($tpl['address'])): ?>
                                <p class="text-muted small mb-1">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars(mb_strimwidth($tpl['address'], 0, 50, '...')); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($tpl['notes'])): ?>
                                <p class="text-muted small mb-0 fst-italic">
                                    <i class="fas fa-sticky-note me-1"></i>
                                    <?php echo htmlspecialchars(mb_strimwidth($tpl['notes'], 0, 60, '...')); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm flex-fill edit-tpl-btn"
                                    data-id="<?php echo $tpl['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($tpl['template_name'] ?? '', ENT_QUOTES); ?>"
                                    data-description="<?php echo htmlspecialchars($tpl['description'] ?? '', ENT_QUOTES); ?>"
                                    data-phone="<?php echo htmlspecialchars($tpl['phone'] ?? '', ENT_QUOTES); ?>"
                                    data-address="<?php echo htmlspecialchars($tpl['address'] ?? '', ENT_QUOTES); ?>"
                                    data-notes="<?php echo htmlspecialchars($tpl['notes'] ?? '', ENT_QUOTES); ?>">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <a href="../customer/order.php?lang=<?php echo $lang; ?>&tpl_id=<?php echo $tpl['id']; ?>"
                               class="btn btn-success btn-sm flex-fill">
                                <i class="fas fa-shopping-cart me-1"></i>Use
                            </a>
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

<!-- Template Modal -->
<div class="modal fade" id="tplModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tplModalTitle"><i class="fas fa-copy me-2"></i>New Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tplAlert"></div>
                <input type="hidden" id="tplEditId" value="">
                <div class="mb-3">
                    <label class="form-label">Template Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="tplName" placeholder="e.g. Home Repair, Office Setup...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Requirements / Description</label>
                    <textarea class="form-control form-control-sm" id="tplDesc" rows="3"
                              placeholder="Describe your typical service requirements..."></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control form-control-sm" id="tplPhone" placeholder="+250...">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Service Address</label>
                        <input type="text" class="form-control form-control-sm" id="tplAddress" placeholder="Street, City...">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Additional Notes</label>
                    <input type="text" class="form-control form-control-sm" id="tplNotes" placeholder="Any extra info...">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveTplBtn"><i class="fas fa-save me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const lang = '<?php echo $lang; ?>';
    const url  = 'index.php?page=customer-templates&lang=' + lang;

    function post(data) {
        return fetch(url, { method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(r => r.json());
    }

    function getFields() {
        return {
            name:        document.getElementById('tplName').value.trim(),
            description: document.getElementById('tplDesc').value,
            phone:       document.getElementById('tplPhone').value,
            address:     document.getElementById('tplAddress').value,
            notes:       document.getElementById('tplNotes').value,
        };
    }

    function clearModal() {
        document.getElementById('tplEditId').value = '';
        document.getElementById('tplName').value = '';
        document.getElementById('tplDesc').value = '';
        document.getElementById('tplPhone').value = '';
        document.getElementById('tplAddress').value = '';
        document.getElementById('tplNotes').value = '';
        document.getElementById('tplAlert').innerHTML = '';
        document.getElementById('tplModalTitle').innerHTML = '<i class="fas fa-copy me-2"></i>New Template';
    }

    window.openTplModal = function() { clearModal(); };

    // Edit
    document.querySelectorAll('.edit-tpl-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('tplEditId').value   = btn.dataset.id;
            document.getElementById('tplName').value     = btn.dataset.name;
            document.getElementById('tplDesc').value     = btn.dataset.description;
            document.getElementById('tplPhone').value    = btn.dataset.phone;
            document.getElementById('tplAddress').value  = btn.dataset.address;
            document.getElementById('tplNotes').value    = btn.dataset.notes;
            document.getElementById('tplAlert').innerHTML = '';
            document.getElementById('tplModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Template';
            new bootstrap.Modal(document.getElementById('tplModal')).show();
        });
    });

    // Save / Update
    document.getElementById('saveTplBtn')?.addEventListener('click', () => {
        const f = getFields();
        if (!f.name) {
            document.getElementById('tplAlert').innerHTML = '<div class="alert alert-danger py-1">Name is required.</div>';
            return;
        }
        const editId = document.getElementById('tplEditId').value;
        const data   = new FormData();
        data.append('action',      editId ? 'edit' : 'save');
        if (editId) data.append('item_id', editId);
        Object.entries(f).forEach(([k,v]) => data.append(k, v));

        post(data).then(res => {
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('tplModal'))?.hide();
                if (typeof loadPage === 'function') loadPage('customer-templates', false);
            } else {
                document.getElementById('tplAlert').innerHTML = `<div class="alert alert-danger py-1">${res.msg}</div>`;
            }
        });
    });

    // Delete
    document.querySelectorAll('.del-tpl-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Delete this template?')) return;
            const data = new FormData();
            data.append('action', 'delete');
            data.append('item_id', btn.dataset.id);
            post(data).then(() => {
                const card = document.getElementById('tpl-' + btn.dataset.id);
                if (card) card.remove();
            });
        });
    });
})();
</script>
