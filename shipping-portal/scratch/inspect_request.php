<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

echo "=== ACTIVITY LOGS FOR ID 13 ===\n";
$stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE request_id = 13 OR details LIKE '%1013%'");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
