<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();

$billId = intval($_GET['id'] ?? 0);
if ($billId <= 0) {
    http_response_code(400);
    die("Invalid bill identifier.");
}

try {
    $stmt = $pdo->prepare("SELECT b.bill_file_path, s.store_code, uc.utility_type 
                            FROM bills b 
                            JOIN stores s ON b.store_id = s.id
                            JOIN utility_connections uc ON b.connection_id = uc.id
                            WHERE b.id = ?");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        http_response_code(404);
        die("Bill invoice not found.");
    }

    $filePath = UPLOAD_DIR . $bill['bill_file_path'];

    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        die("The bill invoice file could not be found in secure storage.");
    }

    if (ob_get_level()) {
        ob_end_clean();
    }

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeType = ($ext === 'pdf') ? 'application/pdf' : 'image/' . $ext;
    
    $cleanStore = preg_replace('/[^A-Za-z0-9_-]/', '_', $bill['store_code']);
    $cleanType = preg_replace('/[^A-Za-z0-9_-]/', '_', $bill['utility_type']);
    $filename = "bill_" . $cleanStore . "_" . $cleanType . "_" . $billId . "." . $ext;

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($filePath);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    die("Database error occurred: " . $e->getMessage());
}
