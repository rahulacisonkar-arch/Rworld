<?php
// Configuration File for Artee Fabrics & Home Shipping Portal
date_default_timezone_set('America/New_York');

// Error Reporting (Change to 0 in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'artee_shipping');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// SMTP Configuration for Email Notifications
define('SMTP_HOST', 'smtp.mailtrap.io'); // Change to production SMTP host
define('SMTP_PORT', 587);                // 587 (TLS), 465 (SSL) or 25
define('SMTP_USER', '');                 // SMTP username
define('SMTP_PASS', '');                 // SMTP password
define('SMTP_FROM_EMAIL', 'shipping@arteefabrics.com');
define('SMTP_FROM_NAME', 'Artee Fabrics & Home Logistics');
define('SMTP_SECURE', 'tls');            // 'tls', 'ssl' or ''

// Path Settings
define('UPLOAD_DIR', dirname(__DIR__) . '/secure_uploads/');
define('APP_URL', 'http://localhost/shipping-portal/public'); // Adjust to portal base URL

// Session Config
define('SESSION_LIFETIME', 86400 * 30); // 30 days remember me

// Easyship API Configuration
define('EASYSHIP_PROD_API_KEY', 'prod_IGyvnafAWObx9FPZ8aCbSCwKr/OBoqnSo+qkoH19uIo=');
define('EASYSHIP_SAND_API_KEY', 'sand_3KOqCQJlieroshOiX69P8t1gyLRgHepKJughFLLKF3o=');
define('EASYSHIP_API_KEY', EASYSHIP_PROD_API_KEY); // Default fallback key
define('EASYSHIP_ENV', 'production'); // Default fallback environment

