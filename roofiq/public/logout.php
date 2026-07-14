<?php
/**
 * ROOFIQ — Logout
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();

if (is_logged_in()) {
    log_activity('User logged out', 'auth', current_user()['id']);
}

session_destroy();
setcookie(SESSION_NAME, '', time() - 3600, '/');
setcookie('roofiq_remember', '', time() - 3600, '/');
header('Location: index.php');
exit;
