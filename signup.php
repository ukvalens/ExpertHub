<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_type  = $_POST['user_type'];
    $phone      = trim($_POST['phone'] ?? '');
    $country    = trim($_POST['country'] ?? '');

    // Validate role
    if (!in_array($user_type, ['customer', 'provider', 'admin'])) {
        $error = "Invalid account type selected.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "An account with that email already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, user_type, phone, country, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')");
            $stmt->bind_param("sssssss", $first_name, $last_name, $email, $password, $user_type, $phone, $country);

            if ($stmt->execute()) {
                $upd = $conn->prepare("UPDATE users SET status='active', email_verified=1 WHERE email=?");
                $upd->bind_param("s", $email);
                $upd->execute();
                $success   = "Account created successfully! You can now sign in.";
                $reg_role  = $user_type;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ExpertHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=17" rel="stylesheet">
    <style>
        .role-card {
            border: 2px solid #e9ecef;
            border-radius: 14px;
            padding: 18px 12px;
            cursor: pointer;
            transition: all .2s ease;
            text-align: center;
            background: #fff;
            position: relative;
            user-select: none;
        }
        .role-card:hover { border-color: var(--accent-color); box-shadow: 0 4px 16px rgba(0,191,166,.15); transform: translateY(-2px); }
        .role-card.selected { border-color: var(--primary-color); background: linear-gradient(135deg,#f0f8ff,#e6f2f1); box-shadow: 0 4px 16px rgba(0,119,182,.18); }
        .role-card .role-icon { width: 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 10px; }
        .role-card .role-title { font-weight: 700; font-size: .92rem; margin-bottom: 4px; color: var(--text-color); }
        .role-card .role-desc { font-size: .75rem; color: var(--text-muted); line-height: 1.4; }
        .role-card .check-badge { position: absolute; top: 8px; right: 10px; width: 20px; height: 20px; border-radius: 50%; background: var(--primary-color); color: #fff; font-size: .65rem; display: none; align-items: center; justify-content: center; }
        .role-card.selected .check-badge { display: flex; }

        /* Step indicator */
        .step-indicator { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 18px; }
        .step-dot { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #dee2e6; background: #fff; color: #aaa; font-size: .75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; transition: all .2s; }
        .step-dot.active { border-color: var(--primary-color); background: var(--primary-color); color: #fff; }
        .step-dot.done { border-color: var(--accent-color); background: var(--accent-color); color: #fff; }
        .step-line { flex: 1; height: 2px; background: #dee2e6; max-width: 50px; transition: background .2s; }
        .step-line.done { background: var(--accent-color); }
        .step-label { font-size: .68rem; color: var(--text-muted); text-align: center; margin-top: 3px; }

        .form-step { display: none; }
        .form-step.active { display: block; }

        .password-toggle { position: relative; }
        .password-toggle .toggle-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #aaa; cursor: pointer; padding: 0; font-size: .85rem; }
    </style>
</head>
<body class="auth-body">
    <div class="container">
        <div class="row align-items-center py-4 g-4">

            <!-- Left panel -->
            <div class="col-lg-5 d-none d-lg-block">
                <div class="pe-4">
                    <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2 mb-4">
                        <i class="fas fa-users-cog fa-lg" style="color:var(--primary-color)"></i>
                        <span class="fw-bold fs-5" style="color:var(--primary-color)">ExpertHub</span>
                    </a>
                    <h2 class="fw-bold mb-3" style="color:var(--secondary-color)">Join ExpertHub Today</h2>
                    <p class="text-muted mb-4">Connect with verified professionals or offer your expertise to thousands of clients.</p>

                    <div class="d-flex flex-column gap-3">
                        <?php foreach ([
                            ['fa-shield-alt', 'Secure & Trusted', 'Enterprise-grade security protects your data and payments.'],
                            ['fa-users',      'Verified Network',  'Pre-screened professionals with proven track records.'],
                            ['fa-clock',      '24/7 Support',      'Dedicated support team available whenever you need help.'],
                        ] as [$icon, $title, $desc]): ?>
                        <div class="d-flex align-items-start gap-3">
                            <div class="service-icon flex-shrink-0" style="width:42px;height:42px;font-size:1rem;">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small"><?php echo $title; ?></div>
                                <div class="text-muted" style="font-size:.78rem"><?php echo $desc; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right panel -->
            <div class="col-lg-7">
                <div class="auth-card mx-auto" style="max-width:520px;">
                    <div class="auth-header">
                        <h5><i class="fas fa-user-plus me-2"></i>Create Your Account</h5>
                        <p class="mb-0 small">Choose your role and get started in minutes</p>
                    </div>
                    <div class="p-3">

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger py-2 small"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success py-2 small"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
                            <div class="text-center mt-2">
                                <a href="login.php" class="btn btn-primary btn-sm"><i class="fas fa-sign-in-alt me-1"></i>Sign In Now</a>
                            </div>
                        <?php else: ?>

                        <!-- Step indicator -->
                        <div class="step-indicator">
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-dot active" id="dot1">1</div>
                                <div class="step-label">Role</div>
                            </div>
                            <div class="step-line" id="line1"></div>
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-dot" id="dot2">2</div>
                                <div class="step-label">Details</div>
                            </div>
                            <div class="step-line" id="line2"></div>
                            <div class="d-flex flex-column align-items-center">
                                <div class="step-dot" id="dot3">3</div>
                                <div class="step-label">Done</div>
                            </div>
                        </div>

                        <form method="POST" id="signupForm">
                            <input type="hidden" name="user_type" id="user_type_input" value="">

                            <!-- Step 1: Role selection -->
                            <div class="form-step active" id="step1">
                                <p class="text-muted small text-center mb-3">What brings you to ExpertHub?</p>
                                <div class="row g-2 mb-3">
                                    <div class="col-4">
                                        <div class="role-card" data-role="customer" onclick="selectRole(this)">
                                            <div class="check-badge"><i class="fas fa-check"></i></div>
                                            <div class="role-icon" style="background:linear-gradient(135deg,#e3f2fd,#bbdefb);color:#1565c0;">
                                                <i class="fas fa-shopping-cart"></i>
                                            </div>
                                            <div class="role-title">Customer</div>
                                            <div class="role-desc">Hire experts & get services done</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="role-card" data-role="provider" onclick="selectRole(this)">
                                            <div class="check-badge"><i class="fas fa-check"></i></div>
                                            <div class="role-icon" style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);color:#2e7d32;">
                                                <i class="fas fa-briefcase"></i>
                                            </div>
                                            <div class="role-title">Provider</div>
                                            <div class="role-desc">Offer your skills & earn money</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="role-card" data-role="admin" onclick="selectRole(this)">
                                            <div class="check-badge"><i class="fas fa-check"></i></div>
                                            <div class="role-icon" style="background:linear-gradient(135deg,#fce4ec,#f8bbd0);color:#880e4f;">
                                                <i class="fas fa-user-shield"></i>
                                            </div>
                                            <div class="role-title">Admin</div>
                                            <div class="role-desc">Manage the platform & users</div>
                                        </div>
                                    </div>
                                </div>
                                <div id="roleError" class="text-danger small text-center mb-2" style="display:none;">
                                    <i class="fas fa-exclamation-circle me-1"></i>Please select a role to continue.
                                </div>
                                <button type="button" class="btn btn-primary w-100 btn-sm" onclick="goStep2()">
                                    Continue <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </div>

                            <!-- Step 2: Personal details -->
                            <div class="form-step" id="step2">
                                <div id="roleTag" class="text-center mb-3"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-1">First Name *</label>
                                        <input type="text" class="form-control form-control-sm" name="first_name" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Last Name *</label>
                                        <input type="text" class="form-control form-control-sm" name="last_name" required>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Email Address *</label>
                                    <input type="email" class="form-control form-control-sm" name="email" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Password *</label>
                                    <div class="password-toggle">
                                        <input type="password" class="form-control form-control-sm" name="password" id="passwordInput" required minlength="6" placeholder="Min. 6 characters">
                                        <button type="button" class="toggle-btn" onclick="togglePassword()"><i class="fas fa-eye" id="eyeIcon"></i></button>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Phone</label>
                                        <input type="tel" class="form-control form-control-sm" name="phone" placeholder="Optional">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Country</label>
                                        <input type="text" class="form-control form-control-sm" name="country" placeholder="Optional">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0" onclick="goStep1()">
                                        <i class="fas fa-arrow-left me-1"></i>Back
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                        <i class="fas fa-user-plus me-1"></i>Create Account
                                    </button>
                                </div>
                            </div>

                        </form>
                        <?php endif; ?>

                        <hr class="my-2">
                        <div class="text-center" style="font-size:.8rem;">
                            Already have an account? <a href="login.php" class="text-decoration-none fw-semibold">Sign In</a>
                            &nbsp;·&nbsp; <a href="index.php" class="text-decoration-none text-muted">← Home</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedRole = '';

        const roleLabels = {
            customer: { label: 'Customer',  icon: 'fa-shopping-cart', color: '#1565c0', bg: '#e3f2fd' },
            provider: { label: 'Provider',  icon: 'fa-briefcase',     color: '#2e7d32', bg: '#e8f5e9' },
            admin:    { label: 'Admin',      icon: 'fa-user-shield',   color: '#880e4f', bg: '#fce4ec' },
        };

        function selectRole(card) {
            document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedRole = card.dataset.role;
            document.getElementById('user_type_input').value = selectedRole;
            document.getElementById('roleError').style.display = 'none';
        }

        function goStep2() {
            if (!selectedRole) {
                document.getElementById('roleError').style.display = 'block';
                return;
            }
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');

            // Update step indicator
            document.getElementById('dot1').classList.replace('active', 'done');
            document.getElementById('dot1').innerHTML = '<i class="fas fa-check" style="font-size:.6rem"></i>';
            document.getElementById('line1').classList.add('done');
            document.getElementById('dot2').classList.add('active');

            // Show role tag
            const r = roleLabels[selectedRole];
            document.getElementById('roleTag').innerHTML =
                `<span class="badge rounded-pill px-3 py-1" style="background:${r.bg};color:${r.color};font-size:.78rem;">
                    <i class="fas ${r.icon} me-1"></i>Signing up as <strong>${r.label}</strong>
                </span>`;
        }

        function goStep1() {
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step1').classList.add('active');
            document.getElementById('dot1').classList.replace('done', 'active');
            document.getElementById('dot1').textContent = '1';
            document.getElementById('line1').classList.remove('done');
            document.getElementById('dot2').classList.remove('active');
        }

        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon  = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // If PHP returned an error, jump straight to step 2 and restore role
        <?php if (isset($error) && !empty($_POST['user_type'])): ?>
        selectedRole = '<?php echo htmlspecialchars($_POST['user_type']); ?>';
        document.getElementById('user_type_input').value = selectedRole;
        const card = document.querySelector(`.role-card[data-role="${selectedRole}"]`);
        if (card) card.classList.add('selected');
        goStep2();
        <?php endif; ?>
    </script>
</body>
</html>
