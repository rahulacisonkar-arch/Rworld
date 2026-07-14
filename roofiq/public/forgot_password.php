<?php
/**
 * ROOFIQ — Forgot Password
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        $user = db_fetch("SELECT * FROM users WHERE email=? AND status='Active'", array($email));
        if ($user) {
            // Generate mock reset token
            $token = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            db_update('users', array('reset_token' => $token, 'reset_expires' => $expires), 'id=?', array($user['id']));
            
            // In a real system, send email. For demo/local:
            $reset_link = 'reset_password.php?token=' . $token;
            $success = 'A password reset link has been generated: <a href="' . e($reset_link) . '" style="color:#00d4ff;text-decoration:underline;font-weight:bold;">Reset Password Now</a>';
            log_activity('Password reset requested for: ' . $email, 'auth', $user['id']);
        } else {
            // Security practice: don't reveal if email exists, but for local/demo user experience we can be friendly
            $error = 'Active user with that email address was not found.';
        }
    }
}

$appName = roofiq_app_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — <?php echo e($appName); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="css/roofiq.css">
</head>
<body class="roofiq-login-body">
<div class="login-split-wrapper" style="justify-content:center;">
  <div class="login-side-form" style="width:100%;max-width:480px;border-radius:12px;">
    <div class="login-form-inner">
      <div class="login-form-title">Forgot Password</div>
      <div class="login-form-sub" style="margin-bottom:24px;">Enter your email to receive a password reset link.</div>

      <?php if ($error): ?>
        <div style="background:rgba(255,23,68,0.1);border:1px solid rgba(255,23,68,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#ff6b6b;font-size:0.85rem;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div style="background:rgba(0,230,118,0.1);border:1px solid rgba(0,230,118,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#69f0ae;font-size:0.85rem;">
          <i class="fas fa-info-circle mr-2"></i> <?php echo $success; ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <?php csrf_field(); ?>
        <div class="login-input-wrap" style="margin-bottom:24px;">
          <i class="li-icon fas fa-envelope"></i>
          <input type="email" name="email" placeholder="Email Address" required value="<?php echo e(isset($_POST['email']) ? $_POST['email'] : ''); ?>">
        </div>

        <button type="submit" class="btn-login-submit" style="margin-bottom:16px;">
          <i class="fas fa-paper-plane mr-2"></i> Send Reset Link
        </button>
        <a href="index.php" class="btn btn-block btn-outline-secondary" style="border-radius:10px;font-size:0.88rem;color:#94a3b8;border-color:rgba(255,255,255,0.12);padding:12px 0;text-align:center;display:block;text-decoration:none;">
          <i class="fas fa-arrow-left mr-2"></i> Back to Login
        </a>
      </form>
    </div>
  </div>
</div>
</body>
</html>
