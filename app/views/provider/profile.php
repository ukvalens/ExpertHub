<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $country = $_POST['country'];
    $professional_title = $_POST['professional_title'];
    $bio = $_POST['bio'];
    $experience_years = $_POST['experience_years'];
    $hourly_rate = $_POST['hourly_rate'];
    
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_dir = '../../../uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $profile_image = 'uploads/profiles/' . $user_id . '_' . time() . '.' . $file_ext;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], '../../../' . $profile_image);
    }
    
    // Update user table
    if ($profile_image) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, country = ?, profile_image = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $first_name, $last_name, $phone, $country, $profile_image, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, country = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $country, $user_id);
    }
    $stmt->execute();
    
    // Update or insert provider profile
    $stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();
    
    if ($provider) {
        $stmt = $conn->prepare("UPDATE service_providers SET professional_title = ?, bio = ?, experience_years = ?, hourly_rate = ? WHERE user_id = ?");
        $stmt->bind_param("ssidi", $professional_title, $bio, $experience_years, $hourly_rate, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO service_providers (user_id, professional_title, bio, experience_years, hourly_rate) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issid", $user_id, $professional_title, $bio, $experience_years, $hourly_rate);
    }
    
    if ($stmt->execute()) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
}

$stmt = $conn->prepare("SELECT u.*, sp.* FROM users u LEFT JOIN service_providers sp ON u.id = sp.user_id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Profile - ExpertHub</title>
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
                    Provider
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
            <div class="col-md-10 mx-auto">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-user-tie me-2"></i>Provider Profile</h3>
                        <p class="mb-0">Manage your professional information and service details</p>
                    </div>
                    <div class="p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <h5 class="mb-3">Personal Information</h5>
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
                            
                            <div class="mb-3">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" name="profile_image" accept="image/*">
                                <?php if ($user['profile_image']): ?>
                                    <small class="text-muted">Current: <?php echo basename($user['profile_image']); ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <hr class="my-4">
                            <h5 class="mb-3">Professional Information</h5>
                            
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Professional Title</label>
                                    <input type="text" class="form-control" name="professional_title" value="<?php echo htmlspecialchars($user['professional_title'] ?? ''); ?>" placeholder="e.g., Senior Web Developer">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Years of Experience</label>
                                    <input type="number" class="form-control" name="experience_years" value="<?php echo $user['experience_years'] ?? 0; ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Professional Bio</label>
                                <textarea class="form-control" name="bio" rows="4" placeholder="Tell clients about your expertise and experience..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hourly Rate ($)</label>
                                    <input type="number" class="form-control" name="hourly_rate" value="<?php echo $user['hourly_rate'] ?? 0; ?>" step="0.01" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Verification Status</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['verification_status'] ?? 'pending'); ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="../dashboard/index.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-success">
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