<?php
/**
 * QuickBill POS - Front Controller
 * Entry point for all requests
 */

// ── Autoload & Bootstrap ──────────────────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Model.php';
require_once CORE_PATH . '/View.php';
require_once CORE_PATH . '/Controller.php';
require_once CORE_PATH . '/App.php';
require_once CORE_PATH . '/CSRF.php';
require_once CORE_PATH . '/TaxEngine.php';

// ── Session ────────────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

session_name('QB_SESSION');
session_start();

// Session timeout check
if (!empty($_SESSION['user_id'])) {
    $last = $_SESSION['last_activity'] ?? time();
    if ((time() - $last) > (SESSION_LIFETIME * 60)) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/auth/login?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ── Run Application ────────────────────────────────────────────────────────
$app = new App();
$app->run();
