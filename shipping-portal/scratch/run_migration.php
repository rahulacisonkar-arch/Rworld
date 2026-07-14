<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/db.php';
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';

try {
    echo "1. Adding columns to request_labels...\n";
    
    // Check if column exists first
    $check_easyship_id = $pdo->query("SHOW COLUMNS FROM request_labels LIKE 'easyship_shipment_id'")->fetch();
    if (!$check_easyship_id) {
        $pdo->exec("ALTER TABLE request_labels ADD COLUMN easyship_shipment_id VARCHAR(100) NULL");
        echo "Added easyship_shipment_id column.\n";
    } else {
        echo "easyship_shipment_id column already exists.\n";
    }

    $check_tracking_status = $pdo->query("SHOW COLUMNS FROM request_labels LIKE 'tracking_status'")->fetch();
    if (!$check_tracking_status) {
        $pdo->exec("ALTER TABLE request_labels ADD COLUMN tracking_status VARCHAR(100) DEFAULT 'Label Created'");
        echo "Added tracking_status column.\n";
    } else {
        echo "tracking_status column already exists.\n";
    }

    $check_updated_at = $pdo->query("SHOW COLUMNS FROM request_labels LIKE 'tracking_updated_at'")->fetch();
    if (!$check_updated_at) {
        $pdo->exec("ALTER TABLE request_labels ADD COLUMN tracking_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "Added tracking_updated_at column.\n";
    } else {
        echo "tracking_updated_at column already exists.\n";
    }

    echo "\n2. Fetching shipments from Easyship API to map existing records...\n";
    
    $apiKey = EASYSHIP_PROD_API_KEY;
    $url = 'https://public-api.easyship.com/2024-09/shipments?per_page=100';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Easyship API returned HTTP $httpCode");
    }

    $data = json_decode($response, true);
    $shipments = $data['shipments'] ?? [];
    echo "Found " . count($shipments) . " shipments in Easyship.\n";

    $updateStmt = $pdo->prepare("UPDATE request_labels SET easyship_shipment_id = ?, tracking_status = ? WHERE tracking_number = ?");

    $mappedCount = 0;
    foreach ($shipments as $s) {
        $easyshipId = $s['easyship_shipment_id'] ?? '';
        $trackingNumber = '';
        if (!empty($s['trackings'])) {
            $trackingNumber = $s['trackings'][0]['tracking_number'] ?? '';
        }
        $status = $s['status'] ?? 'Label Ready';

        if (!empty($trackingNumber) && !empty($easyshipId)) {
            // Check if we have this tracking number in our DB
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM request_labels WHERE tracking_number = ?");
            $stmtCheck->execute([$trackingNumber]);
            if ($stmtCheck->fetchColumn() > 0) {
                $updateStmt->execute([$easyshipId, $status, $trackingNumber]);
                echo "Mapped: Tracking $trackingNumber -> Easyship ID $easyshipId (Status: $status)\n";
                $mappedCount++;
            }
        }
    }
    echo "\nSuccessfully mapped $mappedCount shipments.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
