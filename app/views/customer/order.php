<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header("Location: ../../../login.php");
    exit();
}

$service_id = $_GET['service_id'] ?? null;
$device_id = $_GET['device_id'] ?? null;
if (!$service_id) {
    header("Location: browse-services.php");
    exit();
}

// Get service details
$stmt = $conn->prepare("SELECT ps.*, u.first_name, u.last_name, sp.id as provider_id 
                       FROM provider_services ps 
                       JOIN service_providers sp ON ps.provider_id = sp.id 
                       JOIN users u ON sp.user_id = u.id 
                       WHERE ps.id = ? AND ps.status = 'active'");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    header("Location: browse-services.php");
    exit();
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = $_POST['description'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    // Generate order number
    $order_number = 'ORD' . date('Ymd') . rand(1000, 9999);
    
    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders (order_number, customer_id, provider_id, service_id, device_id, service_title, service_description, customer_requirements, quoted_price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'requested', NOW())");
    $requirements = json_encode(['description' => $description, 'phone' => $phone, 'address' => $address]);
    $stmt->bind_param("siiiiissd", $order_number, $_SESSION['user_id'], $service['provider_id'], $service_id, $device_id, $service['title'], $service['description'], $requirements, $service['base_price']);
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        header("Location: payment.php?order_id=$order_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_GET['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - ExpertHub</title>
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
                <a class="nav-link" href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">Browse Services</a>
                <a class="nav-link" href="orders.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>">My Orders</a>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user me-1"></i>Customer
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item text-danger" href="../../../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-shopping-cart me-2"></i>Place Order</h3>
                        <p class="mb-0">Complete your service order</p>
                    </div>
                    <div class="p-4">
                        <!-- Service Details -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?php echo htmlspecialchars($service['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie me-1"></i>
                                            Provider: <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="h5 text-success">$<?php echo number_format($service['base_price'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Form -->
                        <form method="POST">
                            <div class="mb-3">
                                <label for="description" class="form-label">Service Requirements</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required placeholder="Describe your specific requirements..."></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required placeholder="Your phone number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Service Address</label>
                                    <input type="text" class="form-control" id="address" name="address" required placeholder="Where should the service be provided?">
                                </div>
                            </div>

                            <!-- Order Summary -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="card-title">Order Summary</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Service Price:</span>
                                        <span class="fw-bold">$<?php echo number_format($service['base_price'], 2); ?></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">Total:</span>
                                        <span class="fw-bold text-success">$<?php echo number_format($service['base_price'], 2); ?></span>
                                    </div>
                                    <small class="text-muted">Payment will be processed via Mobile Money (MoMo)</small>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="browse-services.php?lang=<?php echo $_GET['lang'] ?? 'en'; ?>" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-1"></i>Back
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-credit-card me-1"></i>Proceed to Payment
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