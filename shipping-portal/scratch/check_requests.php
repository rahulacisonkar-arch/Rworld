<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';

echo "=== DETAILED RECENT REQUESTS ===\n";
$stmt = $pdo->query("SELECT id, request_number, store_id, ship_from_company, ship_from_address1, ship_to_company, ship_to_address1, sales_order_number, status FROM label_requests ORDER BY id DESC LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
