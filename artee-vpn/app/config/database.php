<?php
// ============================================================
//  ARTEE VPN — Database Configuration
//  PHP 7.0.1 Compatible
// ============================================================

define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'arteevpn');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

// NetBird Management API
define('NETBIRD_API_URL', getenv('NETBIRD_API_URL') ?: 'http://management:33073');
define('NETBIRD_PAT',     getenv('NETBIRD_PAT')     ?: '');   // Personal Access Token

// App Settings
define('APP_NAME',    'Artee VPN');
define('APP_URL',     getenv('APP_URL')     ?: 'http://10.10.1.14/artee/artee-vpn/public');
define('APP_ENV',     getenv('APP_ENV')     ?: 'production');
define('SESSION_LIFETIME', 7200); // 2 hours in seconds

/**
 * Get PDO database connection (singleton)
 */
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}
