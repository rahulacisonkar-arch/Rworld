<?php
// ============================================================
//  ARTEE VPN — Auth Helper
//  PHP 7.0.1 Compatible (no typed properties, no arrow fns)
// ============================================================
require_once __DIR__ . '/../config/database.php';

/**
 * Start or resume the PHP session safely.
 */
function session_start_safe()
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure',   1);
        ini_set('session.use_strict_mode', 1);
        session_set_cookie_params(SESSION_LIFETIME, '/', '', true, true);
        session_start();
    }
}

/**
 * Get currently logged-in user or null.
 */
function current_user()
{
    session_start_safe();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Require user to be logged in; redirect to login if not.
 */
function require_auth()
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require admin role; redirect to dashboard if not admin.
 */
function require_admin()
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Log the user in. Returns user array on success, false on failure.
 */
function login($email, $password)
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    if ($user['status'] !== 'active') {
        return false;
    }

    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];

    // Log activity
    log_activity($user['id'], null, 'user.login', 'User logged in', $_SERVER['REMOTE_ADDR'] ?? '');

    return $user;
}

/**
 * Log the current user out.
 */
function logout()
{
    session_start_safe();
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        log_activity($uid, null, 'user.logout', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? '');
    }
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

/**
 * Write an entry to the activity_logs table.
 */
function log_activity($user_id, $peer_id, $event_type, $description, $ip = '')
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, peer_id, event_type, description, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user_id, $peer_id, $event_type, $description, $ip]);
    } catch (Exception $e) {
        // Non-fatal — silently fail logging
    }
}

/**
 * Simple CSRF token generation and verification.
 */
function csrf_token()
{
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token)
{
    session_start_safe();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output to prevent XSS.
 */
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
