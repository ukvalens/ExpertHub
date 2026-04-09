<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    header("Location: ../dashboard/index.php?page=profile&lang=$lang"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php"); exit;
}

$user_id = $_SESSION['user_id'];
$lang    = $_GET['lang'] ?? 'en';
$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name         = trim($_POST['first_name'] ?? '');
    $last_name          = trim($_POST['last_name'] ?? '');
    $phone              = trim($_POST['phone'] ?? '');
    $country            = trim($_POST['country'] ?? '');
    $professional_title = trim($_POST['professional_title'] ?? '');
    $bio                = trim($_POST['bio'] ?? '');
    $experience_years   = (int)($_POST['experience_years'] ?? 0);
    $hourly_rate        = (float)($_POST['hourly_rate'] ?? 0);

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
    $stmt->execute();

    $stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE service_providers SET professional_title=?, bio=?, experience_years=?, hourly_rate=? WHERE user_id=?");
        $stmt->bind_param("ssidi", $professional_title, $bio, $experience_years, $hourly_rate, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO service_providers (user_id, professional_title, bio, experience_years, hourly_rate) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issid", $user_id, $professional_title, $bio, $experience_years, $hourly_rate);
    }
    $success = $stmt->execute() ? "Profile updated successfully!" : "Failed to update profile.";

    // Refresh user data
    $stmt = $conn->prepare("SELECT u.*, sp.* FROM users u LEFT JOIN service_providers sp ON u.id = sp.user_id WHERE u.id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

if (!isset($user)) {
    $stmt = $conn->prepare("SELECT u.*, sp.* FROM users u LEFT JOIN service_providers sp ON u.id = sp.user_id WHERE u.id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}
?>

<div class="content-card">
    <div class="card-header">
        <i class="fas fa-user-tie me-2" style="color:var(--accent-color)"></i>Provider Profile
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

        <!-- Profile picture + stats summary -->
        <div class="d-flex align-items-center gap-4 mb-4 p-3 rounded" style="background:var(--light-bg)">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="../../../<?php echo htmlspecialchars($user['profile_image']); ?>"
                     class="rounded-circle" style="width:72px;height:72px;object-fit:cover;">
            <?php else: ?>
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
                     style="width:72px;height:72px;font-size:1.4rem;">
                    <?php echo strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)); ?>
                </div>
            <?php endif; ?>
            <div>
                <h5 class="mb-0"><?php echo htmlspecialchars($user['first_name'].' '.$user['last_name']); ?></h5>
                <small class="text-muted"><?php echo htmlspecialchars($user['professional_title'] ?? 'No title set'); ?></small><br>
                <span class="badge <?php echo ($user['verification_status'] ?? '') === 'verified' ? 'bg-success' : 'bg-warning text-dark'; ?> mt-1">
                    <i class="fas fa-<?php echo ($user['verification_status'] ?? '') === 'verified' ? 'check-circle' : 'clock'; ?> me-1"></i>
                    <?php echo ucfirst($user['verification_status'] ?? 'pending'); ?>
                </span>
            </div>
            <div class="ms-auto text-end d-none d-md-block">
                <small class="text-muted d-block">Rating</small>
                <strong><?php echo number_format($user['rating'] ?? 0, 1); ?> <i class="fas fa-star text-warning" style="font-size:.8rem"></i></strong>
                <small class="text-muted d-block mt-1"><?php echo (int)($user['total_reviews'] ?? 0); ?> reviews</small>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">

            <h6 class="fw-semibold mb-3">Personal Information</h6>
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
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control form-control-sm"
                           value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small class="text-muted">Email cannot be changed</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Profile Picture</label>
                    <input type="file" class="form-control form-control-sm" name="profile_image" accept="image/*">
                    <?php if (!empty($user['profile_image'])): ?>
                        <small class="text-muted">Current: <?php echo basename($user['profile_image']); ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-3">
            <h6 class="fw-semibold mb-3">Professional Information</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Professional Title</label>
                    <input type="text" class="form-control form-control-sm" name="professional_title"
                           value="<?php echo htmlspecialchars($user['professional_title'] ?? ''); ?>"
                           placeholder="e.g., Senior Web Developer">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Years of Experience</label>
                    <input type="number" class="form-control form-control-sm" name="experience_years"
                           value="<?php echo (int)($user['experience_years'] ?? 0); ?>" min="0">
                </div>
                <div class="col-12">
                    <label class="form-label">Professional Bio</label>
                    <textarea class="form-control form-control-sm" name="bio" rows="4"
                              placeholder="Tell clients about your expertise and experience..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hourly Rate ($)</label>
                    <input type="number" class="form-control form-control-sm" name="hourly_rate"
                           value="<?php echo number_format((float)($user['hourly_rate'] ?? 0), 2, '.', ''); ?>"
                           step="0.01" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Verification Status</label>
                    <input type="text" class="form-control form-control-sm"
                           value="<?php echo ucfirst($user['verification_status'] ?? 'pending'); ?>" disabled>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-2">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>

        </form>
    </div>
</div>
