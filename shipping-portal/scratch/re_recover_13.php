<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

// Remove the existing label entry for request 13 from DB first
$stmt = $pdo->prepare("SELECT label_file FROM request_labels WHERE request_id = 13");
$stmt->execute();
$row = $stmt->fetch();
if ($row) {
    $filePath = UPLOAD_DIR . $row['label_file'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    $pdo->prepare("DELETE FROM request_labels WHERE request_id = 13")->execute();
    echo "Removed old A4 label file and DB entry.\n";
}

// Now include and run the recover script (which will download the 4x6 version since we updated the code)
require_once __DIR__ . '/recover_labels.php';
