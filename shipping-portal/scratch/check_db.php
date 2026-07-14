<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';

echo "=== LABEL REQUESTS ===\n";
$stmt = $pdo->query("SELECT id, request_number, sales_order_number, shipping_method, customer_freight_charge, status, created_at FROM label_requests ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== REQUEST LABELS ===\n";
$stmt = $pdo->query("SELECT id, request_id, tracking_number, carrier, actual_shipping_cost, label_file, created_at FROM request_labels ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
