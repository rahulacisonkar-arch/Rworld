<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';

echo "=== STORES ===\n";
$stmt = $pdo->query("SELECT id, store_code, store_name, zip, address FROM stores");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n=== USERS ===\n";
$stmt = $pdo->query("SELECT id, store_id, name, username, role FROM users");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n=== RECENT NOTIFICATIONS ===\n";
$stmt = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n=== RECENT LABEL REQUESTS ===\n";
$stmt = $pdo->query("SELECT id, request_number, store_id, sales_order_number, status FROM label_requests ORDER BY id DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
