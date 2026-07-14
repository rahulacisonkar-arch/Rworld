<?php
require_once __DIR__ . '/config.php';

function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        
        // Secure Session Cookie parameters
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_start();
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    session_start_safe();
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input() {
    echo '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
}

function redirect_unauthenticated() {
    session_start_safe();
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }
}

// Forgotten Clock-out Mitigation & Shift Calculator
function calculate_shift_hours($loginTime, $logoutTime, $date, $totalBreakSeconds) {
    $totalShiftSeconds = 0;
    if ($logoutTime) {
        $totalShiftSeconds = strtotime($logoutTime) - strtotime($loginTime);
    } else {
        // Active shift logic
        if ($date === date('Y-m-d')) {
            // Checked in today: calculate up to current time (max 12 hours)
            $elapsed = time() - strtotime($loginTime);
            $totalShiftSeconds = min($elapsed, 12 * 3600);
        } else {
            // Forgotten check-out from a previous day: cap shift duration at 8 hours standard
            $totalShiftSeconds = 8 * 3600 + $totalBreakSeconds;
        }
    }
    $netSeconds = max(0, $totalShiftSeconds - $totalBreakSeconds);
    return round($netSeconds / 3600, 2);
}
