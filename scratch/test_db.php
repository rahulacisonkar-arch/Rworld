<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=artee_utility;port=3306', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Delete mock bills with due dates in January 2026
    $stmt = $pdo->prepare("DELETE FROM bills WHERE due_date BETWEEN '2026-01-13' AND '2026-01-19'");
    $stmt->execute();
    echo "Deleted " . $stmt->rowCount() . " mock bills from the database.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
