<?php
// Configuration File for Artee Fabrics & Home Utility Portal
date_default_timezone_set('America/New_York');

// Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'artee_utility');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Path Settings
define('UPLOAD_DIR', dirname(__DIR__) . '/secure_uploads/');
define('APP_URL', 'http://localhost/utility-portal/public'); // Adjust to your local port/path

// Session Config
define('SESSION_LIFETIME', 86400 * 30); // 30 days remember me
