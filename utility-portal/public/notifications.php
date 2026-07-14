<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
if (!is_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';

if ($action === 'count') {
    $count = get_unread_notifications_count();
    header('Content-Type: application/json');
    echo json_encode(['count' => $count]);
    exit;
}

if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 15");
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode(['notifications' => $notifications]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['notifications' => []]);
    }
    exit;
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->exec("UPDATE notifications SET is_read = 1");
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
