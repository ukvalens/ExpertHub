<?php
if (!isset($conn)) {
    session_start();
    require_once '../../../config/database.php';
    $lang = $_GET['lang'] ?? 'en';
    $sid  = isset($_GET['service_id']) ? '&service_id='.(int)$_GET['service_id'] : '';
    $did  = isset($_GET['device_id'])  ? '&device_id='.(int)$_GET['device_id']   : '';
    header("Location: ../dashboard/index.php?page=order&lang=$lang$sid$did"); exit;
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { echo '<div class="alert alert-danger">Access denied.</div>'; return; }

$user_id    = $_SESSION['user_id'];
$lang       = $_GET['lang'] ?? 'en';
$service_id = (int)($_GET['service_id'] ?? 0);
$device_id  = (int)($_GET['device_id']  ?? 0) ?: null;

if (!$service_id) {
    echo '<div class="alert alert-warning">No service selected.
        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="loadPage(\'browse-services\')">Browse Services</button>
    </div>';
    return;
}

$stmt = $conn->prepare("SELECT ps.*, u.first_name, u.last_name, sp.id as provider_id, sp.rating, sp.verification_status, u.profile_image
    FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id=sp.id
    JOIN users u ON sp.user_id=u.id
    WHERE ps.id=? AND ps.status='active'");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    echo '<div class="alert alert-danger">Service not found or no longer available.</div>'; return;
}

// Prefill user phone
$stmt = $conn->prepare("SELECT phone FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

// Handle submission
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $description = trim($_POST['description'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');

    if (!$description || !$phone || !$address) {
        $error = 'Please fill in all required fields.';
    } else {
        $order_number = 'ORD'.date('Ymd').rand(1000,9999);
        $requirements = json_encode(['description'=>$description,'phone'=>$phone,'address'=>$address]);
        $stmt = $conn->prepare("INSERT INTO orders (order_number, customer_id, provider_id, service_id, device_id, service_title, service_description, customer_requirements, quoted_price, status, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,'requested',NOW())");
        $stmt->bind_param("siiiiisssd",
            $order_number, $user_id, $service['provider_id'], $service_id, $device_id,
            $service['title'], $service['description'], $requirements, $service['base_price']
        );
        if ($stmt->execute()) {
            $order_id = $conn->insert_id;
            header("Location: ../customer/payment.php?order_id=$order_id&lang=$lang"); exit;
        }
        $error = 'Failed to place order. Please try again.';
    }
}
?>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-shopping-cart me-2" style="color:var(--accent-color)"></i>Place Order</span>
        <button class="btn btn-sm btn-outline-secondary"
                onclick="loadPage('<?php echo $device_id ? 'request-service' : 'browse-services'; ?>', true, <?php echo $device_id ? "{device_id:$device_id}" : '{}'; ?>)">
            <i class="fas fa-arrow-left me-1"></i>Back
        </button>
    </div>
    <div class="card-body">

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Service summary -->
        <div class="d-flex align-items-start gap-3 p-3 rounded mb-4" style="background:var(--light-bg)">
            <?php if (!empty($service['profile_image'])): ?>
                <img src="../../../<?php echo htmlspecialchars($service['profile_image']); ?>"
                     class="rounded-circle flex-shrink-0" style="width:48px;height:48px;object-fit:cover;">
            <?php else: ?>
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;font-size:.9rem;">
                    <?php echo strtoupper(substr($service['first_name'],0,1).substr($service['last_name'],0,1)); ?>
                </div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <h6 class="mb-0 text-primary"><?php echo htmlspecialchars($service['title']); ?></h6>
                <small class="text-muted">
                    <?php echo htmlspecialchars($service['first_name'].' '.$service['last_name']); ?>
                    <?php if ($service['verification_status']==='verified'): ?>
                        <i class="fas fa-check-circle text-success ms-1" style="font-size:.7rem;"></i>
                    <?php endif; ?>
                </small>
                <p class="text-muted small mb-0 mt-1">
                    <?php echo htmlspecialchars(mb_strimwidth($service['description']??'',0,120,'...')); ?>
                </p>
            </div>
            <div class="text-end flex-shrink-0">
                <div class="fw-bold text-success fs-5">$<?php echo number_format($service['base_price'],2); ?></div>
                <small class="text-muted">starting at</small>
            </div>
        </div>

        <form method="POST">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Service Requirements <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" name="description" rows="4" required
                              placeholder="Describe exactly what you need done..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control form-control-sm" name="phone" required
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? $me['phone'] ?? ''); ?>"
                           placeholder="Your contact number">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Service Address <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="address" required
                           value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                           placeholder="Where should the service be provided?">
                </div>
            </div>

            <!-- Order summary box -->
            <div class="p-3 rounded mt-4 mb-3" style="background:var(--light-bg)">
                <h6 class="mb-2">Order Summary</h6>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Service</span>
                    <span><?php echo htmlspecialchars($service['title']); ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Pricing model</span>
                    <span><?php echo ucfirst($service['pricing_model'] ?? 'fixed'); ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total</span>
                    <span class="text-success">$<?php echo number_format($service['base_price'],2); ?></span>
                </div>
                <small class="text-muted">Payment processed via Mobile Money (MoMo)</small>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="submit" name="place_order" class="btn btn-primary btn-sm">
                    <i class="fas fa-credit-card me-1"></i>Proceed to Payment
                </button>
            </div>
        </form>

    </div>
</div>
