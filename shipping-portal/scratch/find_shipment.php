<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$stmt = $pdo->prepare("SELECT rl.*, lr.request_number FROM request_labels rl JOIN label_requests lr ON rl.request_id = lr.id WHERE rl.tracking_number = ?");
$stmt->execute(['873161414477']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
