<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$validIds = [8, 10, 11, 13, 14, 15, 16];
$validIdsStr = implode(',', $validIds);

try {
    // Start transaction
    $pdo->beginTransaction();

    // 1. Find all label files to delete
    $stmt = $pdo->query("SELECT label_file FROM request_labels WHERE request_id NOT IN ($validIdsStr)");
    $filesToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($filesToDelete as $file) {
        if (!empty($file)) {
            $filePath = UPLOAD_DIR . $file;
            if (file_exists($filePath) && is_file($filePath)) {
                if (@unlink($filePath)) {
                    echo "Deleted file: {$file}\n";
                } else {
                    echo "Failed to delete file: {$file}\n";
                }
            }
        }
    }

    // 2. Delete request_labels records
    $stmt1 = $pdo->exec("DELETE FROM request_labels WHERE request_id NOT IN ($validIdsStr)");
    echo "Deleted {$stmt1} records from request_labels.\n";

    // 3. Delete activity_logs associated with deleted requests
    $stmt2 = $pdo->exec("DELETE FROM activity_logs WHERE request_id NOT IN ($validIdsStr) OR request_id IS NULL");
    echo "Deleted {$stmt2} records from activity_logs.\n";

    // 4. Delete notifications associated with deleted requests
    // (We don't have request_id in notifications, but we can delete all of them to be clean, or leave them)
    $stmt3 = $pdo->exec("DELETE FROM notifications");
    echo "Cleared notifications table (deleted {$stmt3} records).\n";

    // 5. Delete label_requests records
    $stmt4 = $pdo->exec("DELETE FROM label_requests WHERE id NOT IN ($validIdsStr)");
    echo "Deleted {$stmt4} records from label_requests.\n";

    // Commit transaction
    $pdo->commit();
    echo "Cleanup transaction committed successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
