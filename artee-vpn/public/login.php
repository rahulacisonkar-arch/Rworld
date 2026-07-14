<?php
// ============================================================
//  ARTEE VPN — Login Page
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../app/helpers/auth.php';

// If already logged in, redirect to dashboard
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } else {
            $user = login($email, $password);
            if ($user) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials or account is inactive.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign In — Artee VPN</title>
  <meta name="robots" content="noindex" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="auth-body">

<div class="auth-bg">
  <div class="auth-glow auth-glow-1"></div>
  <div class="auth-glow auth-glow-2"></div>
</div>

<div class="auth-container">
  <div class="auth-card">
    <!-- Logo -->
    <a href="index.php" class="auth-logo">
      <span class="logo-icon logo-icon-lg">⬡</span>
      <span class="logo-text logo-text-lg">Artee <strong>VPN</strong></span>
    </a>

    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to your admin portal</p>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo e($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="auth-form" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />

      <div class="form-group">
        <label for="email" class="form-label">Username</label>
        <div class="input-wrap">
          <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input
            type="text"
            id="email"
            name="email"
            class="form-input"
            placeholder="admin"
            value="<?php echo e($_POST['email'] ?? ''); ?>"
            required
            autofocus
          />
        </div>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <div class="input-wrap">
          <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input
            type="password"
            id="password"
            name="password"
            class="form-input"
            placeholder="••••••••"
            required
          />
          <button type="button" class="toggle-pass" onclick="togglePass()" title="Show/hide password">
            <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" id="login-btn">
        <span id="btn-text">Sign In to Artee VPN</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
      </button>
    </form>

    <p class="auth-footer-note">
      Artee VPN © <?php echo date('Y'); ?> &mdash; <a href="index.php">Back to home</a>
    </p>
  </div>
</div>

<script>
function togglePass() {
    var field = document.getElementById('password');
    field.type = field.type === 'password' ? 'text' : 'password';
}
document.querySelector('.auth-form').addEventListener('submit', function() {
    var btn = document.getElementById('login-btn');
    var txt = document.getElementById('btn-text');
    btn.disabled = true;
    txt.textContent = 'Signing in…';
});
</script>
</body>
</html>
