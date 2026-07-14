<?php
/**
 * ROOFIQ AI ENTERPRISE — Core Configuration
 * PHP 7.0.1 Compatible — No typed properties, no arrow functions, no 7.1+ features
 */

define('ROOFIQ_ROOT',    dirname(__DIR__));
define('ROOFIQ_PUBLIC',  ROOFIQ_ROOT . '/public');
define('ROOFIQ_DATA',    ROOFIQ_ROOT . '/data');
define('ROOFIQ_VENDOR',  ROOFIQ_ROOT . '/vendor');
define('ROOFIQ_VERSION', '3.0.0');

// Session config
define('SESSION_NAME',     'ROOFIQ_SESSID');
define('SESSION_LIFETIME', 7200); // 2 hours default

// ---------------------------------------------------------------
// DB Config — edit for production
// ---------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'roofiq');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------
// Settings loader (reads from DB via lazy load, falls back to JSON)
// ---------------------------------------------------------------

$_ROOFIQ_SETTINGS_CACHE = null;

function roofiq_setting($key, $default = '') {
    global $_ROOFIQ_SETTINGS_CACHE, $pdo;

    if ($_ROOFIQ_SETTINGS_CACHE === null) {
        $_ROOFIQ_SETTINGS_CACHE = array();
        // Try JSON file first (available before DB install)
        $jsonFile = ROOFIQ_DATA . '/roofiq_settings.json';
        if (file_exists($jsonFile)) {
            $json = file_get_contents($jsonFile);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $_ROOFIQ_SETTINGS_CACHE = $data;
            }
        }
        // Override from DB if available
        if (isset($pdo)) {
            try {
                $stmt = $pdo->query("SELECT `key`, `value` FROM `settings`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $_ROOFIQ_SETTINGS_CACHE[$row['key']] = $row['value'];
                }
            } catch (Exception $e) {
                // DB not ready yet, use JSON values
            }
        }
    }

    return isset($_ROOFIQ_SETTINGS_CACHE[$key]) ? $_ROOFIQ_SETTINGS_CACHE[$key] : $default;
}

function roofiq_save_setting($key, $value) {
    global $pdo;
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO `settings` (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?");
            $stmt->execute(array($key, $value, $value));
            global $_ROOFIQ_SETTINGS_CACHE;
            $_ROOFIQ_SETTINGS_CACHE[$key] = $value;
            return true;
        } catch (Exception $e) {
            error_log("roofiq_save_setting failed: " . $e->getMessage());
        }
    }
    return false;
}

function roofiq_app_name() {
    return roofiq_setting('app_name', 'SHEKHAR ROOFIQ AI ENTERPRISE');
}

function roofiq_company_name() {
    return roofiq_setting('company_name', 'Shekhar Building Materials');
}

function roofiq_google_key() {
    return roofiq_setting('google_maps_api_key', '');
}

function roofiq_cesium_token() {
    return roofiq_setting('cesium_ion_token', '');
}

function roofiq_maptiler_key() {
    return roofiq_setting('maptiler_api_key', '');
}

function roofiq_ai_service_url() {
    return rtrim(roofiq_setting('ai_service_url', 'http://localhost:5001'), '/');
}

function roofiq_solar_key() {
    return roofiq_setting('google_maps_api_key', '');
}
