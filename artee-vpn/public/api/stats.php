<?php
// ============================================================
//  ARTEE VPN — Stats API Endpoint
//  Returns live peer/user counts as JSON
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = db();

    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM peers WHERE status = "online"');
    $online = (int)$stmt->fetch()['cnt'];

    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM peers');
    $total = (int)$stmt->fetch()['cnt'];

    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM users WHERE status = "active"');
    $users = (int)$stmt->fetch()['cnt'];

    echo json_encode([
        'peers'        => $online,
        'total_peers'  => $total,
        'active_users' => $users,
        'status'       => 'ok',
        'timestamp'    => time(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch stats.', 'peers' => 0]);
}
