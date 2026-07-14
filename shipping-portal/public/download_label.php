<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();

$labelId = intval($_GET['id'] ?? 0);
if ($labelId <= 0) {
    http_response_code(400);
    die("Invalid label identifier.");
}

try {
    // Fetch request details joining request_labels with label_requests and stores
    $stmt = $pdo->prepare("SELECT rl.label_file, rl.tracking_number, lr.store_id, lr.request_number, lr.ship_to_zip, lr.ship_to_address1, s.store_name, s.store_code
                            FROM request_labels rl
                            JOIN label_requests lr ON rl.request_id = lr.id
                            LEFT JOIN stores s ON lr.store_id = s.id
                            WHERE rl.id = ?");
    $stmt->execute([$labelId]);
    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(404);
        die("Shipping label not found.");
    }

    if (empty($request['label_file'])) {
        http_response_code(404);
        die("No shipping label PDF has been uploaded for this package yet.");
    }

    // Verify Ownership / Role permissions
    check_request_ownership($request);

    // Construct full path
    $filePath = UPLOAD_DIR . $request['label_file'];

    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        die("The label file could not be found in secure storage. Please contact administrator.");
    }

    // Clear output buffer to prevent PDF corruption from whitespace
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Construct descriptive filename: e.g. shipping_label_AR-1017_Store_82_Tracking_873149963592.pdf
    $storeCodeClean = preg_replace('/[^A-Za-z0-9_-]/', '_', $request['store_code'] ?? '');
    $trackingClean  = preg_replace('/[^A-Za-z0-9_-]/', '_', $request['tracking_number'] ?? '');
    
    $filename = "shipping_label_" . e($request['request_number']);
    if ($storeCodeClean !== '') {
        $filename .= "_Store_" . $storeCodeClean;
    }
    if ($trackingClean !== '') {
        $filename .= "_Tracking_" . $trackingClean;
    }
    $filename .= ".pdf";

    // Serve secure file with headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Read and output file content safely
    readfile($filePath);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    die("Database error occurred: " . $e->getMessage());
}
