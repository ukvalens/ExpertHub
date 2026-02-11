<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login.php");
    exit();
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $type = $_POST['type']; // 'owner' or 'service'
    $file = $_FILES['photo'];
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed) && $file['size'] <= 5242880) {
        $upload_dir = '../../../uploads/about/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = uniqid() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $stmt = $conn->prepare("INSERT INTO about_photos (type, photo_path, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $type, $filename);
            $stmt->execute();
        }
    }
    header("Location: manage_about.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("SELECT photo_path FROM about_photos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $photo = $stmt->get_result()->fetch_assoc();
    
    if ($photo) {
        unlink('../../../uploads/about/' . $photo['photo_path']);
        $stmt = $conn->prepare("DELETE FROM about_photos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: manage_about.php");
    exit();
}

// Get photos
$owner_photos = $conn->query("SELECT * FROM about_photos WHERE type = 'owner' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$service_photos = $conn->query("SELECT * FROM about_photos WHERE type = 'service' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage About Section - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../../index.php"><i class="fas fa-users-cog me-2"></i>ExpertHub Admin</a>
            <a href="../../../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4">Manage About Section Photos</h2>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-user me-2"></i>Owner Photos</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="type" value="owner">
                            <div class="mb-3">
                                <input type="file" name="photo" class="form-control" accept="image/*" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i>Upload</button>
                        </form>
                        <hr>
                        <div class="row g-2">
                            <?php foreach ($owner_photos as $photo): ?>
                                <div class="col-6 mb-3">
                                    <div class="position-relative">
                                        <img src="/ExpertHUB/uploads/about/<?php echo $photo['photo_path']; ?>" class="img-fluid rounded" style="width: 100%; height: 150px; object-fit: cover;">
                                        <a href="?delete=<?php echo $photo['id']; ?>" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" onclick="return confirm('Delete?')" style="z-index: 10;">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-briefcase me-2"></i>Service Photos</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="type" value="service">
                            <div class="mb-3">
                                <input type="file" name="photo" class="form-control" accept="image/*" required>
                            </div>
                            <button type="submit" class="btn btn-success"><i class="fas fa-upload me-2"></i>Upload</button>
                        </form>
                        <hr>
                        <div class="row g-2">
                            <?php foreach ($service_photos as $photo): ?>
                                <div class="col-6 mb-3">
                                    <div class="position-relative">
                                        <img src="/ExpertHUB/uploads/about/<?php echo $photo['photo_path']; ?>" class="img-fluid rounded" style="width: 100%; height: 150px; object-fit: cover;">
                                        <a href="?delete=<?php echo $photo['id']; ?>" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" onclick="return confirm('Delete?')" style="z-index: 10;">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
