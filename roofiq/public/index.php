<?php
/**
 * ROOFIQ AI ENTERPRISE — Login Page
 * PHP 7.0.1 Compatible | AdminLTE 3 | MySQL 5.7
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();

// Already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

// Handle timeout message
if (!empty($_GET['timeout'])) {
    $error = 'Your session expired due to inactivity. Please sign in again.';
}

if (!empty($_GET['reset_success'])) {
    $success = 'Password reset successfully. Please sign in with your new password.';
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf     = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = !empty($_POST['remember']);

    if (!validate_csrf($csrf)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $user = db_fetch("SELECT * FROM users WHERE username=? AND status='Active'", array($username));
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['last_activity'] = time();

            // Remember me cookie
            if ($remember) {
                $token = bin2hex(random_bytes(24));
                setcookie('roofiq_remember', $token, time() + 2592000, '/', '', false, true);
                db_update('users', array('remember_token' => $token), 'id=?', array($user['id']));
            }

            // Update last login
            db_update('users', array('last_login' => date('Y-m-d H:i:s')), 'id=?', array($user['id']));
            log_activity('User logged in', 'auth', $user['id']);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            log_activity('Failed login attempt for: ' . $username, 'auth');
        }
    }
}

$appName     = roofiq_app_name();
$companyName = roofiq_company_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — <?php echo e($appName); ?></title>
  <meta name="description" content="<?php echo e($companyName); ?> — Professional Roofing Intelligence Platform">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="css/roofiq.css">
  <style>
    body { margin:0; padding: 20px; background: #0a0e1a; }
  </style>
</head>
<body class="roofiq-login-body">

<div class="login-split-wrapper">

  <!-- LEFT: Branding Panel -->
  <div class="login-side-brand">
    <div style="z-index:1;position:relative;">
      <div class="login-logo-ring">
        <i class="fas fa-drafting-compass"></i>
      </div>
      <div class="login-brand-title">
        SHEKHAR<br><span>ROOFIQ AI</span>
      </div>
      <div style="font-size:0.75rem;letter-spacing:2px;color:rgba(148,163,184,0.6);text-transform:uppercase;margin-bottom:12px;">ENTERPRISE EDITION</div>
      <p class="login-brand-sub">
        Professional roofing intelligence platform for estimation, procurement, material takeoff, solar analysis, and project management.
      </p>

      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;">
        <div class="login-stat-pill">
          <span class="stat-num">15+</span>
          <span class="stat-lbl">Analysis Modules</span>
        </div>
        <div class="login-stat-pill">
          <span class="stat-num">YOLO11</span>
          <span class="stat-lbl">AI Damage Detection</span>
        </div>
        <div class="login-stat-pill">
          <span class="stat-num">3D</span>
          <span class="stat-lbl">CesiumJS Viewer</span>
        </div>
      </div>

      <ul class="login-features">
        <li><i class="fas fa-check-circle"></i> CesiumJS 3D Eagle Eye Roof Viewer</li>
        <li><i class="fas fa-check-circle"></i> AI Damage Detection (YOLO11 + SAM2)</li>
        <li><i class="fas fa-check-circle"></i> Automated Material Takeoff &amp; BOM</li>
        <li><i class="fas fa-check-circle"></i> Vendor Pricing &amp; Procurement Engine</li>
        <li><i class="fas fa-check-circle"></i> Solar Feasibility Analysis</li>
        <li><i class="fas fa-check-circle"></i> Professional PDF Report Generation</li>
      </ul>
    </div>

    <div style="margin-top:auto;padding-top:24px;z-index:1;position:relative;">
      <div style="display:flex;align-items:center;gap:8px;font-size:0.75rem;color:rgba(148,163,184,0.5);">
        <span class="status-dot"></span>
        System Operational &nbsp;|&nbsp; <?php echo e($companyName); ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: Login Form -->
  <div class="login-side-form">
    <div class="login-form-inner">
      <div class="login-form-title">Welcome Back</div>
      <div class="login-form-sub">Sign in to your <?php echo e($appName); ?> account.</div>

      <?php if (!empty($error)): ?>
        <div style="background:rgba(255,23,68,0.1);border:1px solid rgba(255,23,68,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#ff6b6b;font-size:0.85rem;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="background:rgba(0,230,118,0.1);border:1px solid rgba(0,230,118,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#69F0AE;font-size:0.85rem;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="index.php" id="login-form">
        <?php csrf_field(); ?>

        <div class="login-input-wrap">
          <i class="li-icon fas fa-user"></i>
          <input type="text" name="username" id="username" placeholder="Username" required
                 autocomplete="username" value="<?php echo e(isset($_POST['username']) ? $_POST['username'] : ''); ?>">
        </div>

        <div class="login-input-wrap">
          <i class="li-icon fas fa-lock"></i>
          <input type="password" name="password" id="password" placeholder="Password" required autocomplete="current-password">
          <button type="button" id="btn-toggle-pass" title="Show/hide password"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(148,163,184,0.6);cursor:pointer;padding:0;">
            <i class="far fa-eye" id="eye-icon"></i>
          </button>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;color:rgba(148,163,184,0.7);font-size:0.82rem;">
            <input type="checkbox" name="remember" style="accent-color:#00d4ff;"> Keep me signed in
          </label>
          <a href="forgot_password.php" style="color:#00d4ff;font-size:0.82rem;text-decoration:none;">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login-submit" id="btn-login">
          <i class="fas fa-sign-in-alt mr-2"></i> Sign In to RoofIQ
        </button>
      </form>

      <div style="text-align:center;margin-top:28px;font-size:0.72rem;color:rgba(148,163,184,0.4);">
        &copy; <?php echo date('Y'); ?> <?php echo e($companyName); ?> &mdash; Authorized Access Only
      </div>
    </div>
  </div>

</div>

<script>
  document.getElementById('btn-toggle-pass').addEventListener('click', function() {
    var p = document.getElementById('password');
    var ic = document.getElementById('eye-icon');
    if (p.type === 'password') {
      p.type = 'text';
      ic.className = 'far fa-eye-slash';
    } else {
      p.type = 'password';
      ic.className = 'far fa-eye';
    }
  });

  document.getElementById('login-form').addEventListener('submit', function() {
    var btn = document.getElementById('btn-login');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Signing in...';
    btn.disabled = true;
  });
</script>
</body>
</html>
