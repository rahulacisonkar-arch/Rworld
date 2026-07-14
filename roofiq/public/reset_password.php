<?php
/**
 * ROOFIQ — Reset Password
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();

$error   = '';
$success = '';
$token   = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    header('Location: index.php');
    exit;
}

$user = db_fetch("SELECT * FROM users WHERE reset_token=? AND reset_expires > NOW() AND status='Active'", array($token));
if (!$user) {
    $error = 'The password reset token is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm  = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_update('users', array(
            'password_hash' => $hash,
            'reset_token'   => null,
            'reset_expires' => null
        ), 'id=?', array($user['id']));

        log_activity('Password reset successfully via token', 'auth', $user['id']);
        header('Location: index.php?reset_success=1');
        exit;
    }
}

$appName = roofiq_app_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — <?php echo e($appName); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="css/roofiq.css">
</head>
<body class="roofiq-login-body">
<div class="login-split-wrapper" style="justify-content:center;">
  <div class="login-side-form" style="width:100%;max-width:480px;border-radius:12px;">
    <div class="login-form-inner">
      <div class="login-form-title">Reset Password</div>
      <div class="login-form-sub" style="margin-bottom:24px;">Enter a new password for your account.</div>

      <?php if ($error): ?>
        <div style="background:rgba(255,23,68,0.1);border:1px solid rgba(255,23,68,0.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#ff6b6b;font-size:0.85rem;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <?php if ($user): ?>
        <form method="POST">
          <?php csrf_field(); ?>
          <div class="login-input-wrap" style="margin-bottom:16px;">
            <i class="li-icon fas fa-lock"></i>
            <input type="password" name="password" placeholder="New Password" required minlength="6">
          </div>
          <div class="login-input-wrap" style="margin-bottom:24px;">
            <i class="li-icon fas fa-lock"></i>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required minlength="6">
          </div>

          <button type="submit" class="btn-login-submit">
            <i class="fas fa-key mr-2"></i> Update Password
          </button>
        </form>
      <?php else: ?>
        <a href="forgot_password.php" class="btn btn-block btn-roofiq" style="border-radius:10px;padding:12px 0;text-align:center;display:block;text-decoration:none;">
          Request New Reset Link
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
