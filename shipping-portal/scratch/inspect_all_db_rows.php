<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

echo "=== ALL LABEL REQUESTS ===\n";
$stmt = $pdo->query("SELECT id, request_number, sales_order_number, shipping_method, customer_freight_charge, status, created_at FROM label_requests ORDER BY id ASC");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($requests as $r) {
    echo "ID: {$r['id']} | Num: {$r['request_number']} | SO: {$r['sales_order_number']} | Method: {$r['shipping_method']} | Charge: {$r['customer_freight_charge']} | Status: {$r['status']} | Created: {$r['created_at']}\n";
}

echo "\n=== ALL REQUEST LABELS ===\n";
$stmt = $pdo->query("SELECT id, request_id, tracking_number, carrier, actual_shipping_cost, label_file, created_at FROM request_labels ORDER BY id ASC");
$labels = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($labels as $l) {
    echo "ID: {$l['id']} | ReqID: {$l['request_id']} | Track: {$l['tracking_number']} | Carrier: {$l['carrier']} | Cost: {$l['actual_shipping_cost']} | File: {$l['label_file']} | Created: {$l['created_at']}\n";
}
