<?php
// PHP script to clear utility connections while keeping stores and users
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Clear Connections</title><link rel='stylesheet' href='public/css/bootstrap.min.css'></head><body class='container mt-5'><h2>Clearing Connections...</h2><pre>";
} else {
    echo "Clearing Connections...\n";
}

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear utility connections
    $pdo->exec("TRUNCATE TABLE utility_connections");
    echo "✓ Cleared all utility connections.\n";
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\nAll utility connections have been successfully cleared. Only store addresses and user accounts remain.\n";
} catch (Exception $e) {
    echo "\nError during operation: " . $e->getMessage() . "\n";
}

if (!$isCli) {
    echo "</pre><a href='public/dashboard.php' class='btn btn-primary'>Go to Dashboard</a></body></html>";
}
