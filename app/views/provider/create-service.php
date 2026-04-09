<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=create-service&lang=$lang"); exit;
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

if (!$provider) {
    $stmt = $conn->prepare("INSERT INTO service_providers (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $provider_id = $conn->insert_id;
} else {
    $provider_id = $provider['id'];
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price  = (float)($_POST['base_price'] ?? 0);

    $cat_stmt = $conn->prepare("SELECT id FROM service_categories LIMIT 1");
    $cat_stmt->execute();
    $category    = $cat_stmt->get_result()->fetch_assoc();
    $category_id = $category['id'] ?? null;

    if (!$category_id) {
        $error = "No service categories available. Please contact admin.";
    } else {
        $service_type = 'online_service';
        $status       = 'active';
        $stmt = $conn->prepare("INSERT INTO provider_services (provider_id, category_id, title, description, service_type, base_price, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssds", $provider_id, $category_id, $title, $description, $service_type, $base_price, $status);
        if ($stmt->execute()) {
            $success = "Service created successfully!";
        } else {
            $error = "Failed to create service.";
        }
    }
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plus me-2" style="color:var(--accent-color)"></i>Create New Service</span>
        <a href="#" class="btn btn-sm btn-outline-secondary nav-link-ajax" data-page="my-services">
            <i class="fas fa-arrow-left me-1"></i>My Services
        </a>
    </div>
    <div class="card-body">
        <div class="row justify-content-center">
            <div class="col-md-8">

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <a href="#" class="nav-link-ajax ms-2" data-page="my-services">View My Services →</a>
                    </div>
                <?php endif; ?>

                <form method="POST" class="create-service-form">
                    <div class="mb-3">
                        <label class="form-label">Service Title</label>
                        <input type="text" class="form-control" name="title" required
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="5" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Base Price ($)</label>
                        <input type="number" class="form-control" name="base_price" step="0.01" min="0" required
                               value="<?php echo htmlspecialchars($_POST['base_price'] ?? ''); ?>">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="#" class="btn btn-secondary nav-link-ajax" data-page="my-services">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Create Service
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
document.querySelector('.create-service-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const data = new FormData(this);
    fetch('index.php?page=create-service&lang=<?php echo $_GET["lang"] ?? "en"; ?>', {
        method: 'POST', body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(() => {
        if (typeof loadPage === 'function') loadPage('create-service', false);
    });
});
</script>
