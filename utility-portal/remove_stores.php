<?php
// PHP script to remove Middletown, Newton, and Princeton stores and their details from DB
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Remove Stores</title><link rel='stylesheet' href='public/css/bootstrap.min.css'></head><body class='container mt-5'><h2>Removing Stores Data...</h2><pre>";
} else {
    echo "Removing Stores Data...\n";
}

$storesToRemove = ['MIDDLETOWN', 'NEWTON', 'PRINCETON'];

try {
    $pdo->beginTransaction();

    foreach ($storesToRemove as $code) {
        // Find store
        $stmt = $pdo->prepare("SELECT id, store_name FROM stores WHERE store_code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $storeId = $row['id'];
            $storeName = $row['store_name'];
            
            // Delete the store. Cascade constraints will delete connections and bills automatically.
            $deleteStmt = $pdo->prepare("DELETE FROM stores WHERE id = ?");
            $deleteStmt->execute([$storeId]);
            
            echo "Successfully removed store: $storeName ($code) and all its details.\n";
        } else {
            echo "Store with code '$code' was not found in the database.\n";
        }
    }

    $pdo->commit();
    echo "\nDeletion completed and committed.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nError during deletion: " . $e->getMessage() . "\n";
}

if (!$isCli) {
    echo "</pre><a href='public/dashboard.php' class='btn btn-primary'>Go to Dashboard</a></body></html>";
}
