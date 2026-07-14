<?php
/**
 * QuickBill POS - Application Configuration
 * Phase 2: MVC Framework
 */

// ── Database ──────────────────────────────────────────────────────────────
define('DB_HOST',     '127.0.0.1');
define('DB_PORT',     3306);
define('DB_NAME',     'rworld_erp');
define('DB_USER',     'rworld_erp_user');
define('DB_PASS',     'QB_SecurePass_2026!');
define('DB_CHARSET',  'utf8mb4');

// ── Application ───────────────────────────────────────────────────────────
define('APP_NAME',    'Rworld ERP');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/rworld-erp');
define('APP_ROOT',    dirname(__DIR__));  // project root

// ── Security ──────────────────────────────────────────────────────────────
// ⚠️  CHANGE APP_KEY before deploying — generate with: bin2hex(random_bytes(32))
define('APP_KEY',     '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08');
define('SESSION_LIFETIME', 480);   // minutes of inactivity before session expires
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// ── Paths ─────────────────────────────────────────────────────────────────
define('APP_PATH',     APP_ROOT . '/app');
define('CORE_PATH',    APP_ROOT . '/core');
define('CONFIG_PATH',  APP_ROOT . '/config');
define('UPLOAD_PATH',  APP_ROOT . '/uploads');
define('REPORT_PATH',  APP_ROOT . '/reports');
define('BACKUP_PATH',  APP_ROOT . '/backup');
define('VIEW_PATH',    APP_PATH . '/Views');

// ── Date / Locale ─────────────────────────────────────────────────────────
define('DATE_FORMAT',     'm/d/Y');
define('DATETIME_FORMAT', 'm/d/Y h:i A');
define('TIME_FORMAT',     'h:i A');
define('TIMEZONE',        'America/New_York');

date_default_timezone_set(TIMEZONE);

// ── Pagination ────────────────────────────────────────────────────────────
define('ITEMS_PER_PAGE', 25);

// ── Tax Region ────────────────────────────────────────────────────────────
define('TAX_REGION', 'US_SALES_TAX');   // US_SALES_TAX | IN_GST | CUSTOM

// ── Environment ───────────────────────────────────────────────────────────
// ⚠️  Set DEBUG_MODE=false in production — never expose stack traces to users
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    // Log errors to file in production:
    ini_set('log_errors', 1);
    ini_set('error_log', APP_ROOT . '/logs/php_errors.log');
}
