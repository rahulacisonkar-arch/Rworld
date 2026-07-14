<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/db.php';
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/EasyshipService.php';

$requestId = 21;
$stmt = $pdo->prepare("SELECT * FROM label_requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    die("Request ID 4 not found.\n");
}

echo "Testing Sandbox Label Creation...\n";
// Let's call the API directly so we see the raw exception without fallback
try {
    // We'll use a real production courier ID (FedEx 2Day: 91e1560d-7895-4a99-8d4d-3dcd1778a09a)
    $courierId = '91e1560d-7895-4a99-8d4d-3dcd1778a09a';
    $res = EasyshipService::createLabel($request, $courierId, 'FedEx 2Day®', 15.00, 'production');
    echo "Result Success: " . ($res['success'] ? 'Yes' : 'No') . "\n";
    if (isset($res['internal_notes'])) {
        echo "Internal Notes: " . $res['internal_notes'] . "\n";
    }
    echo "Tracking: " . $res['tracking_number'] . "\n";
    echo "Label File: " . $res['label_file'] . "\n";
} catch (Exception $e) {
    echo "Raw Exception: " . $e->getMessage() . "\n";
}
