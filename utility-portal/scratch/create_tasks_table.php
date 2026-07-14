<?php
require_once dirname(__DIR__) . '/src/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS scheduler_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        task_date DATE NOT NULL,
        task_time VARCHAR(20) NOT NULL,
        assignee VARCHAR(100) NOT NULL,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "Table 'scheduler_tasks' created successfully or already exists.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
