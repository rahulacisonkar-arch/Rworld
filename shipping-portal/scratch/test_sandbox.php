<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/EasyshipService.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM label_requests WHERE id = 13");
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        die("Request 13 not found.\n");
    }

    echo "Fetching rates from Production...\n";
    $rates = EasyshipService::getRates($request, 'production');
    print_r($rates);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
