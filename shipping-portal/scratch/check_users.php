<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

try {
    $stmt = $pdo->query("SELECT id, username, email, role, status FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== ALL REGISTERED USERS ===\n";
    foreach ($users as $u) {
        echo "ID: {$u['id']} | Username: {$u['username']} | Email: {$u['email']} | Role: {$u['role']} | Status: {$u['status']}\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
