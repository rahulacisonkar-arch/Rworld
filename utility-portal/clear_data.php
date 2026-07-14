<?php
// PHP script to clear transactional data (bills, notifications, logs) while keeping stores & users
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Clear Data</title><link rel='stylesheet' href='public/css/bootstrap.min.css'></head><body class='container mt-5'><h2>Clearing Data...</h2><pre>";
} else {
    echo "Clearing Data...\n";
}

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear bills
    $pdo->exec("TRUNCATE TABLE bills");
    echo "✓ Cleared all bills data.\n";
    
    // Clear notifications
    $pdo->exec("TRUNCATE TABLE notifications");
    echo "✓ Cleared all notification logs.\n";
    
    // Clear activity logs
    $pdo->exec("TRUNCATE TABLE activity_logs");
    echo "✓ Cleared all system activity logs.\n";
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\nAll transactional data has been successfully cleared. Store addresses, default connections, and user accounts remain intact.\n";
} catch (Exception $e) {
    echo "\nError during operation: " . $e->getMessage() . "\n";
}

if (!$isCli) {
    echo "</pre><a href='public/dashboard.php' class='btn btn-primary'>Go to Dashboard</a></body></html>";
}
