<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $country = $_POST['country'];
    $timezone = $_POST['timezone'];
    $language_preference = $_POST['language_preference'];
    
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_dir = '../../../uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $profile_image = 'uploads/profiles/' . $user_id . '_' . time() . '.' . $file_ext;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], '../../../' . $profile_image);
    }
    
    if ($profile_image) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, country = ?, timezone = ?, language_preference = ?, profile_image = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $first_name, $last_name, $phone, $country, $timezone, $language_preference, $profile_image, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, country = ?, timezone = ?, language_preference = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $first_name, $last_name, $phone, $country, $timezone, $language_preference, $user_id);
    }
    
    if ($stmt->execute()) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">
                <i class="fas fa-users-cog me-2"></i>ExpertHub
            </a>
            <div class="navbar-nav mx-auto">
                <a class="nav-link" href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Home</a>
                <a class="nav-link active" href="profile.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Profile</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <?php if ($user['profile_image']): ?>
                        <img src="../../../<?php echo $user['profile_image']; ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    Customer
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-danger" href="../../../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-user me-2"></i>My Profile</h3>
                        <p class="mb-0">Manage your personal information and preferences</p>
                    </div>
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" name="profile_image" accept="image/*">
                                <?php if ($user['profile_image']): ?>
                                    <small class="text-muted">Current: <?php echo basename($user['profile_image']); ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($user['country']); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-control" name="timezone">
                                        <option value="UTC" <?php echo $user['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        <option value="Africa/Kigali" <?php echo $user['timezone'] == 'Africa/Kigali' ? 'selected' : ''; ?>>Africa/Kigali</option>
                                        <option value="America/New_York" <?php echo $user['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Language</label>
                                    <select class="form-control" name="language_preference">
                                        <option value="en" <?php echo $user['language_preference'] == 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="fr" <?php echo $user['language_preference'] == 'fr' ? 'selected' : ''; ?>>French</option>
                                        <option value="rw" <?php echo $user['language_preference'] == 'rw' ? 'selected' : ''; ?>>Kinyarwanda</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>