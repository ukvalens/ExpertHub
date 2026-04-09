<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=profile&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../../login.php"); exit;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';
$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $country    = trim($_POST['country']    ?? '');

    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $upload_dir = '../../../uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext           = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $profile_image = 'uploads/profiles/' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], '../../../' . $profile_image);
    }

    if ($profile_image) {
        $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, country=?, profile_image=? WHERE id=?");
        $stmt->bind_param("sssssi", $first_name, $last_name, $phone, $country, $profile_image, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, country=? WHERE id=?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $country, $user_id);
    }
    $success = $stmt->execute() ? "Profile updated successfully!" : "Failed to update profile.";

    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

if (!isset($user)) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

// Platform stats
$stmt = $conn->prepare("SELECT
    (SELECT COUNT(*) FROM users WHERE user_type='customer' AND status='active')  as customers,
    (SELECT COUNT(*) FROM users WHERE user_type='provider' AND status='active')  as providers,
    (SELECT COUNT(*) FROM orders WHERE status IN ('accepted','in_progress'))      as active_orders,
    (SELECT COUNT(*) FROM orders WHERE status='completed')                        as completed_orders,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='completed') as total_revenue,
    (SELECT COUNT(*) FROM provider_services WHERE status='active')               as active_services");
$stmt->execute();
$pstats = $stmt->get_result()->fetch_assoc();
?>

<div class="content-card">
    <div class="card-header">
        <i class="fas fa-user-shield me-2" style="color:var(--accent-color)"></i>Admin Profile
    </div>
    <div class="card-body p-4">

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show py-2">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Summary banner -->
        <div class="d-flex align-items-center gap-4 mb-4 p-3 rounded" style="background:var(--light-bg)">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="../../../<?php echo htmlspecialchars($user['profile_image']); ?>"
                     class="rounded-circle flex-shrink-0" style="width:72px;height:72px;object-fit:cover;">
            <?php else: ?>
                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:72px;height:72px;font-size:1.4rem;">
                    <?php echo strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)); ?>
                </div>
            <?php endif; ?>
            <div>
                <h5 class="mb-0"><?php echo htmlspecialchars($user['first_name'].' '.$user['last_name']); ?></h5>
                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small><br>
                <span class="badge bg-danger mt-1"><i class="fas fa-user-shield me-1"></i>Administrator</span>
            </div>
            <div class="ms-auto d-none d-md-flex gap-4 text-center">
                <div>
                    <div class="fw-bold text-primary"><?php echo number_format($pstats['customers']); ?></div>
                    <small class="text-muted">Customers</small>
                </div>
                <div>
                    <div class="fw-bold text-info"><?php echo number_format($pstats['providers']); ?></div>
                    <small class="text-muted">Providers</small>
                </div>
                <div>
                    <div class="fw-bold text-warning"><?php echo number_format($pstats['active_orders']); ?></div>
                    <small class="text-muted">Active Orders</small>
                </div>
                <div>
                    <div class="fw-bold text-success">$<?php echo number_format($pstats['total_revenue'],0); ?></div>
                    <small class="text-muted">Revenue</small>
                </div>
            </div>
        </div>

        <!-- Platform stats row -->
        <div class="row g-3 mb-4">
            <?php
            $tiles = [
                ['label'=>'Active Services',    'value'=>number_format($pstats['active_services']),    'icon'=>'fa-briefcase',    'color'=>'primary'],
                ['label'=>'Completed Orders',   'value'=>number_format($pstats['completed_orders']),   'icon'=>'fa-check-circle', 'color'=>'success'],
                ['label'=>'Active Orders',      'value'=>number_format($pstats['active_orders']),      'icon'=>'fa-spinner',      'color'=>'warning'],
                ['label'=>'Total Revenue',      'value'=>'$'.number_format($pstats['total_revenue'],0),'icon'=>'fa-dollar-sign',  'color'=>'success'],
            ];
            foreach ($tiles as $t): ?>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded text-center" style="background:var(--light-bg)">
                    <i class="fas <?php echo $t['icon']; ?> text-<?php echo $t['color']; ?> mb-1"></i>
                    <div class="fw-bold text-<?php echo $t['color']; ?>"><?php echo $t['value']; ?></div>
                    <small class="text-muted"><?php echo $t['label']; ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">

            <h6 class="fw-semibold mb-3">Account Information</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control form-control-sm" name="first_name"
                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control form-control-sm" name="last_name"
                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small class="text-muted">Email cannot be changed</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control form-control-sm" name="phone"
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Country</label>
                    <input type="text" class="form-control form-control-sm" name="country"
                           value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Profile Picture</label>
                    <input type="file" class="form-control form-control-sm" name="profile_image" accept="image/*">
                    <?php if (!empty($user['profile_image'])): ?>
                        <small class="text-muted">Current: <?php echo basename($user['profile_image']); ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-2">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>

        </form>
    </div>
</div>
