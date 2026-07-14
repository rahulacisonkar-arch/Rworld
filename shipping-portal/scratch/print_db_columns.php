<?php
require_once dirname(__DIR__) . '/src/db.php';

echo "=== TABLE label_requests ===\n";
$q = $pdo->query("DESCRIBE label_requests");
while ($r = $q->fetch()) {
    echo $r['Field'] . " | " . $r['Type'] . " | " . $r['Null'] . " | " . $r['Key'] . "\n";
}

echo "\n=== TABLE request_labels ===\n";
$q = $pdo->query("DESCRIBE request_labels");
while ($r = $q->fetch()) {
    echo $r['Field'] . " | " . $r['Type'] . " | " . $r['Null'] . " | " . $r['Key'] . "\n";
}

echo "\n=== RECENT RECORDS (label_requests) ===\n";
$q = $pdo->query("SELECT id, request_number, sales_order_number, shipping_method, status FROM label_requests ORDER BY id DESC LIMIT 5");
while ($r = $q->fetch()) {
    echo "ID: {$r['id']} | ReqNum: {$r['request_number']} | SO: {$r['sales_order_number']} | Method: {$r['shipping_method']} | Status: {$r['status']}\n";
    // Also get request_labels
    $ql = $pdo->prepare("SELECT * FROM request_labels WHERE request_id = ?");
    $ql->execute([$r['id']]);
    while ($rl = $ql->fetch()) {
        echo "   -> Label ID: {$rl['id']} | Carrier: {$rl['carrier']} | Tracking: {$rl['tracking_number']} | Cost: {$rl['actual_shipping_cost']}\n";
    }
}
