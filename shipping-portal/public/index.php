<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();

// Redirect if already logged in
if (is_logged_in()) {
    if ($_SESSION['role'] === 'Store User') {
        header("Location: dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $remember = isset($_POST['remember']);

    if (!validate_csrf_token($csrf_token)) {
        $error = "Security validation failed. Please refresh and try again.";
    } elseif (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'Active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['store_id'] = $user['store_id'];

            if ($user['store_id']) {
                $stmtStore = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
                $stmtStore->execute([$user['store_id']]);
                $_SESSION['store_name'] = $stmtStore->fetchColumn() ?: 'Store User';
            } else {
                $_SESSION['store_name'] = 'HQ';
            }

            if ($remember) {
                $token = bin2hex(random_bytes(16));
                setcookie('remember_me', $token, time() + SESSION_LIFETIME, "/", "", isset($_SERVER['HTTPS']), true);
            }

            log_activity($user['id'], "User logged in successfully");

            if ($user['role'] === 'Store User') {
                header("Location: dashboard.php");
            } else {
                header("Location: admin_dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Artée Logistics Portal</title>
    <meta name="description" content="Sign in to the Artée Fabrics & Home Logistics Operations Portal.">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="login-container-new">

    <div class="login-split-wrapper">
        <!-- Left Side: Interactive Portal Branding and Network Overview -->
        <div class="login-side-brand d-none d-lg-flex">
            <div class="login-brand-overlay"></div>
            <div class="login-brand-content">
                <div class="brand-top">
                    <span class="badge bg-warning text-dark mb-3 px-3 py-2 fw-semibold uppercase" style="letter-spacing: 1px; font-size: 0.72rem; border-radius: 4px;">Logistics Command Center</span>
                    <h1 class="text-white fw-bold mb-0" style="font-family: 'Outfit', sans-serif; letter-spacing: 0.5px; font-size: 2.2rem;">ARTÉE FABRICS &amp; HOME</h1>
                </div>
                
                <div class="brand-middle my-auto">
                    <h2 class="text-white mb-3 fw-semibold" style="font-size: 1.6rem; line-height: 1.4; max-width: 480px;">Connecting showrooms, warehouses, and freight partners in real-time.</h2>
                    <p class="text-white-50 mb-4" style="max-width: 460px; font-size: 0.92rem; line-height: 1.6;">
                        Securely manage shipments, print shipping labels, track coordinates, and coordinate order dispatches across the enterprise supply chain.
                    </p>
                    
                    <!-- KPI Pills inside the left panel -->
                    <div class="d-flex flex-wrap gap-3 mt-4" style="max-width: 500px;">
                        <div class="stats-pill">
                            <span class="stats-num">99.98%</span>
                            <span class="stats-label">On-Time Arrival</span>
                        </div>
                        <div class="stats-pill">
                            <span class="stats-num">50+</span>
                            <span class="stats-label">Partner Carriers</span>
                        </div>
                    </div>
                </div>
                
                <div class="brand-bottom">
                    <div class="d-flex justify-content-between align-items-center text-white-50" style="font-size: 0.75rem;">
                        <span>System status: <strong class="text-success"><span class="status-dot-pulse"></span> Operational</strong></span>
                        <span>Secured by SSL 256-bit</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side: Login Form Panel -->
        <div class="login-side-form d-flex align-items-center justify-content-center">
            <div class="login-form-box">
                <!-- Branding for Mobile View -->
                <div class="mobile-brand d-lg-none text-center mb-4">
                    <i class="bi bi-shield-fill text-primary mb-2" style="font-size: 2.2rem; display: inline-block;"></i>
                    <h2 class="fw-bold mb-1 text-primary-dark" style="font-family: 'Outfit', sans-serif;">ARTÉE LOGISTICS</h2>
                    <p class="text-muted mb-0" style="letter-spacing: 2px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Shipping Operations Portal</p>
                </div>

                <div class="mb-4">
                    <h3 class="fw-bold mb-2 text-primary-dark" style="font-family: 'Outfit', sans-serif; font-size: 1.6rem;">Sign In</h3>
                    <p class="text-muted mb-0" style="font-size: 0.88rem;">Welcome back. Please authenticate to access your dashboard.</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center border-0 py-3 px-4 mb-4" role="alert" style="border-radius: 8px; font-size: 0.85rem; background: #FDE8E8; color: #9C1A1A;">
                        <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.2rem;"></i>
                        <div><?php echo e($error); ?></div>
                    </div>
                <?php endif; ?>
                
                <form action="index.php" method="POST">
                    <?php csrf_input(); ?>
                    
                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label text-primary-dark fw-medium mb-2" style="font-size: 0.85rem;">Username</label>
                        <div class="input-group-modern">
                            <span class="input-icon">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text"
                                   name="username"
                                   id="username"
                                   class="form-control-modern"
                                   placeholder="Enter your username"
                                   required
                                   autocomplete="username">
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="password" class="form-label text-primary-dark fw-medium mb-0" style="font-size: 0.85rem;">Password</label>
                        </div>
                        <div class="input-group-modern mb-2">
                            <span class="input-icon">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="form-control-modern"
                                   placeholder="Enter your password"
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="btn-toggle-pass" id="btn-toggle-pass" title="Toggle password visibility">
                                <i class="bi bi-eye" id="eye-icon"></i>
                            </button>
                        </div>
                        <!-- Show Password Checkbox -->
                        <div class="form-check custom-check">
                            <input type="checkbox" id="show-password" class="form-check-input" style="cursor: pointer;">
                            <label class="form-check-label text-muted" for="show-password" style="font-size: 0.82rem; cursor: pointer; user-select: none;">Show Password</label>
                        </div>
                    </div>
                    
                    <!-- Remember Me & Forgot Password -->
                    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                        <div class="form-check custom-check">
                            <input type="checkbox" name="remember" id="remember" class="form-check-input" style="cursor: pointer;">
                            <label class="form-check-label text-muted" for="remember" style="font-size: 0.85rem; cursor: pointer; user-select: none;">Keep me signed in</label>
                        </div>
                        <a href="#" onclick="alert('Please contact HQ Administration to reset your password.')" class="forgot-link text-primary" style="font-size: 0.85rem; font-weight: 500; text-decoration: none;">Forgot Password?</a>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit-modern w-100">
                        <span>Sign In to Portal</span>
                        <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
                
                <!-- Footer note -->
                <div class="text-center mt-5" style="font-size: 0.75rem; color: var(--text-muted);">
                    &copy; <?php echo date('Y'); ?> Artée Fabrics &amp; Home. Authorized Access Only.
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const btnTogglePass = document.getElementById('btn-toggle-pass');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        const showPasswordCheckbox = document.getElementById('show-password');

        function togglePassword(show) {
            if (show) {
                passwordInput.type = 'text';
                if (eyeIcon) {
                    eyeIcon.classList.remove('bi-eye');
                    eyeIcon.classList.add('bi-eye-slash');
                }
                if (showPasswordCheckbox) showPasswordCheckbox.checked = true;
            } else {
                passwordInput.type = 'password';
                if (eyeIcon) {
                    eyeIcon.classList.remove('bi-eye-slash');
                    eyeIcon.classList.add('bi-eye');
                }
                if (showPasswordCheckbox) showPasswordCheckbox.checked = false;
            }
        }

        if (btnTogglePass && passwordInput) {
            btnTogglePass.addEventListener('click', function() {
                togglePassword(passwordInput.type === 'password');
            });
        }

        if (showPasswordCheckbox && passwordInput) {
            showPasswordCheckbox.addEventListener('change', function() {
                togglePassword(this.checked);
            });
        }
    </script>
</body>
</html>
