<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/EasyshipService.php';

$recoveryMap = [
    13 => 'ESUS339050060', // AR-1013
    11 => 'ESUS339028908', // AR-1011
    10 => 'ESUS339024820', // AR-1010
    8  => 'ESUS339022836'  // AR-1008
];

foreach ($recoveryMap as $reqId => $easyshipId) {
    echo "Recovering Request ID {$reqId} (Easyship: {$easyshipId})...\n";
    
    // Check if label already exists
    $stmt = $pdo->prepare("SELECT id FROM request_labels WHERE request_id = ?");
    $stmt->execute([$reqId]);
    if ($stmt->fetch()) {
        echo "-> Already recovered.\n";
        continue;
    }
    
    // Fetch shipment from DB
    $stmt = $pdo->prepare("SELECT * FROM label_requests WHERE id = ?");
    $stmt->execute([$reqId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        echo "-> Request not found in database.\n";
        continue;
    }
    
    // Call Easyship to retrieve details
    try {
        $apiKey = EASYSHIP_PROD_API_KEY;
        $url = "https://public-api.easyship.com/2024-09/shipments/{$easyshipId}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
            'User-Agent: Mozilla/5.0'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            throw new Exception("Curl error: " . $err);
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP {$httpCode}: " . $response);
        }
        
        $data = json_decode($response, true);
        $shipment = $data['shipment'] ?? null;
        if (!$shipment) {
            throw new Exception("No shipment in response. Response was: " . substr($response, 0, 300));
        }
        
        // Extract tracking number
        $trackingNumber = '';
        if (!empty($shipment['trackings'])) {
            $trackingNumber = $shipment['trackings'][0]['tracking_number'] ?? '';
        }
        if (empty($trackingNumber)) {
            $trackingNumber = $shipment['label']['tracking_number'] ?? '';
        }
        
        // Extract label PDF URL
        $labelUrl = '';
        foreach ($shipment['shipping_documents'] ?? [] as $doc) {
            if (($doc['category'] ?? '') === 'label') {
                $labelUrl = $doc['url'] ?? '';
                break;
            }
        }
        
        if (empty($labelUrl)) {
            $labelUrl = $shipment['label']['label_raw_url'] ?? $shipment['label']['label_url'] ?? '';
        }
        
        if (empty($trackingNumber) || empty($labelUrl)) {
            throw new Exception("Missing tracking number ({$trackingNumber}) or label URL ({$labelUrl})");
        }

        // Force page_size to 4x6 to avoid huge A4 margins which make the printed label very small
        if (strpos($labelUrl, 'page_size=') !== false) {
            $labelUrl = preg_replace('/page_size=[a-zA-Z0-9_]+/i', 'page_size=4x6', $labelUrl);
        } else {
            $separator = (strpos($labelUrl, '?') !== false) ? '&' : '?';
            $labelUrl .= $separator . 'page_size=4x6';
        }
        
        echo "-> Found tracking: {$trackingNumber}\n";
        echo "-> Downloading PDF: {$labelUrl}\n";
        
        // Download PDF
        $ch = curl_init($labelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $pdfContent = curl_exec($ch);
        $pdfCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($pdfCode >= 400 || empty($pdfContent)) {
            throw new Exception("PDF download failed (HTTP {$pdfCode})");
        }
        
        // Save PDF to secure uploads
        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        $savedFilename = 'label_' . $reqId . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $destPath = UPLOAD_DIR . $savedFilename;
        file_put_contents($destPath, $pdfContent);
        
        // Calculate cost
        $actualCost = 11.75; // FedEx 2Day cost from rates
        foreach ($shipment['rates'] ?? [] as $r) {
            if (($r['courier_service']['id'] ?? '') === '84135827-1538-4be2-b26e-afd8b3f3b4bd') {
                $actualCost = floatval($r['total_charge'] ?? $actualCost);
                break;
            }
        }
        
        // Insert into request_labels
        $stmt = $pdo->prepare("INSERT INTO request_labels (request_id, label_file, tracking_number, carrier, estimated_delivery_date, actual_shipping_cost) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $reqId,
            $savedFilename,
            $trackingNumber,
            $shipment['courier_service']['name'] ?? 'FedEx 2Day®',
            date('Y-m-d', strtotime('+2 days')),
            $actualCost
        ]);
        
        // Update label request status
        $stmt = $pdo->prepare("UPDATE label_requests SET status = 'Label Created', shipping_method = ? WHERE id = ?");
        $stmt->execute([$shipment['courier_service']['name'] ?? 'FedEx 2Day®', $reqId]);
        
        echo "-> Successfully recovered!\n";
        
    } catch (Exception $e) {
        echo "-> Error: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
