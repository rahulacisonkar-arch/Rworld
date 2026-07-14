<?php
// Easyship Webhook Listener Endpoint
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

// Set response headers
header('Content-Type: application/json');

// Retrieve raw request body
$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

// Log webhook payload locally for debugging / audit trail
$logDir = dirname(__DIR__) . '/secure_uploads/logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
file_put_contents($logDir . 'easyship_webhook_' . date('Ymd_His') . '_' . uniqid() . '.json', $rawPayload);

// Retrieve event details
$event = $data['event'] ?? '';
$shipment = $data['shipment'] ?? ($data['data'] ?? []);

// Extract order identifier (mapped to request_number in our system)
$requestNumber = $shipment['platform_order_number'] ?? $shipment['selected_courier']['platform_order_number'] ?? '';

if (empty($requestNumber)) {
    // If not found in standard fields, try to check other meta fields
    $requestNumber = $shipment['metadata']['request_number'] ?? '';
}

if (empty($requestNumber)) {
    http_response_code(200); // Return 200 so Easyship doesn't retry, but log missing identifier
    echo json_encode(['success' => false, 'error' => 'No platform_order_number found in payload']);
    exit;
}

try {
    // Look up shipping request in database
    $stmt = $pdo->prepare("SELECT * FROM label_requests WHERE request_number = ?");
    $stmt->execute([$requestNumber]);
    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => "Shipping request $requestNumber not found in database"]);
        exit;
    }

    // Determine status update based on event or shipment status
    $easyshipStatus = strtolower($shipment['status'] ?? $shipment['tracking_status'] ?? '');
    $newStatus = null;
    $logMsg = "";

    // Map Easyship status values to label_requests ENUM ('Pending', 'Processing', 'Label Created', 'Label Sent', 'Completed', 'Cancelled')
    if (in_array($easyshipStatus, ['shipment_created', 'label_generated', 'label_created'])) {
        $newStatus = 'Label Sent';
        $logMsg = "Easyship generated label";
    } elseif (in_array($easyshipStatus, ['in_transit', 'out_for_delivery', 'picked_up'])) {
        $newStatus = 'Completed'; // Underway / Picked up
        $logMsg = "Package in transit";
    } elseif (in_array($easyshipStatus, ['delivered'])) {
        $newStatus = 'Completed';
        $logMsg = "Package delivered successfully";
    } elseif (in_array($easyshipStatus, ['cancelled', 'voided'])) {
        $newStatus = 'Cancelled';
        $logMsg = "Shipment cancelled via Easyship";
    }

    if ($newStatus && $newStatus !== $request['status']) {
        // Update request status in database
        $updateStmt = $pdo->prepare("UPDATE label_requests SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $request['id']]);

        // Insert audit log
        log_activity(
            1, // System User ID or HQ Admin
            "Webhook ($event): " . $logMsg . " (Status updated to: $newStatus)", 
            $request['id'],
            json_encode($shipment)
        );

        // Create in-app notifications for requester and destination stores
        notify_shipment_status_change($request['id'], $newStatus);

    }

    echo json_encode(['success' => true, 'message' => 'Webhook processed successfully']);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
