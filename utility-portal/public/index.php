<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: dashboard.php");
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
            $_SESSION['name']     = $user['name'];
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

            log_activity("User logged in successfully", "Auth");

            header("Location: dashboard.php");
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
    <title>Sign In — Artée Utility Portal</title>
    <meta name="description" content="Sign in to the Artée Fabrics & Home Utility Operations & Payments Portal.">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="login-body">

    <div class="login-card">
        <div class="text-center mb-4">
            <div class="mb-3 d-inline-flex align-items-center justify-content-center" style="width:64px; height: 64px; border-radius: 50%; background: var(--primary-light); border: 2px solid var(--primary);">
                <i class="bi bi-lightning-charge-fill text-primary" style="font-size: 2.2rem;"></i>
            </div>
            <h1 class="login-title text-primary-dark mb-1">ARTÉE UTILITY</h1>
            <div class="login-sub fw-bold text-secondary" style="font-size: 0.72rem; letter-spacing: 1px; text-transform: uppercase;">Utility &amp; Payments Command</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center border-0 py-3 px-4 mb-4" role="alert" style="border-radius: 8px; font-size: 0.85rem; background: #FDE8E8; color: #9C1A1A;">
                <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.25rem;"></i>
                <div><?php echo e($error); ?></div>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <?php csrf_input(); ?>

            <!-- Username -->
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold text-secondary" style="font-size: 0.85rem;">Username</label>
                <div class="input-group">
                    <span class="input-group-text border-1 border-end-0" style="background: var(--bg); border-color: var(--border); color: var(--text-secondary);"><i class="bi bi-person-fill"></i></span>
                    <input type="text" class="form-control border-start-0" id="username" name="username" placeholder="e.g. admin" required autofocus style="border-color: var(--border);">
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold text-secondary" style="font-size: 0.85rem;">Password</label>
                <div class="input-group">
                    <span class="input-group-text border-1 border-end-0" style="background: var(--bg); border-color: var(--border); color: var(--text-secondary);"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control border-x-0" id="password" name="password" placeholder="••••••••" required style="border-color: var(--border);">
                    <button type="button" class="btn btn-outline-secondary border-1 border-start-0" id="btn-toggle-pass" style="background: var(--bg); border-color: var(--border); color: var(--text-secondary);" title="Toggle password visibility">
                        <i class="bi bi-eye" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember" style="border-color: var(--border);">
                    <label class="form-check-label text-secondary small" for="remember">
                        Remember session
                    </label>
                </div>
                <a href="#" onclick="alert('Please contact HQ Administration to reset your password.')" class="small text-primary fw-semibold" style="text-decoration: none;">Forgot Password?</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-command w-100 py-3 fw-bold d-flex align-items-center justify-content-center gap-2" style="font-size: 0.95rem; letter-spacing: 0.5px;">
                Authenticate <i class="bi bi-arrow-right"></i>
            </button>
        </form>

        <div class="mt-4 pt-3 border-top border-light text-center text-muted small">
            Secured by SSL 256-bit &mdash; System Status: <strong class="text-success"><i class="bi bi-check-circle-fill"></i> Online</strong>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const btnTogglePass = document.getElementById('btn-toggle-pass');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');

        if (btnTogglePass && passwordInput) {
            btnTogglePass.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.classList.remove('bi-eye');
                    eyeIcon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    eyeIcon.classList.remove('bi-eye-slash');
                    eyeIcon.classList.add('bi-eye');
                }
            });
        }
    </script>
</body>
</html>
