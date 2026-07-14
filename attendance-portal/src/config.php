<?php
date_default_timezone_set('America/New_York');

function load_env() {
    $envPath = dirname(__DIR__) . '/.env';
    if (!file_exists($envPath)) {
        return;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
            $value = $matches[1];
        }
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
load_env();

// Application Environment
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Error Reporting Config
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/error.log');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'artee_attendance');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// External Quickbill Database Configuration
define('QB_HOST', getenv('QB_HOST') ?: 'localhost');
define('QB_NAME', getenv('QB_NAME') ?: 'quickbill');
define('QB_USER', getenv('QB_USER') ?: 'root');
define('QB_PASS', getenv('QB_PASS') !== false ? getenv('QB_PASS') : '');

// Path Settings
define('APP_URL', 'http://localhost/attendance-portal/public');

// Session Config
define('SESSION_LIFETIME', 86400 * 30); // 30 days remember me

// QuickBooks API Configuration
define('QUICKBOOKS_CLIENT_ID', getenv('QUICKBOOKS_CLIENT_ID') ?: '');
define('QUICKBOOKS_CLIENT_SECRET', getenv('QUICKBOOKS_CLIENT_SECRET') ?: '');
define('QUICKBOOKS_REDIRECT_URI', getenv('QUICKBOOKS_REDIRECT_URI') ?: 'http://localhost/attendance-portal/public/quickbooks_callback.php');

