<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';

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

echo "HTTP Code: $httpCode\n";
$data = json_decode($response, true);
if ($data && isset($data['shipments'])) {
    foreach ($data['shipments'] as $s) {
        $easyshipId = $s['easyship_shipment_id'] ?? 'N/A';
        $trackingNumber = 'N/A';
        if (!empty($s['trackings'])) {
            $trackingNumber = $s['trackings'][0]['tracking_number'] ?? 'N/A';
        }
        $courier = $s['courier_service']['name'] ?? 'N/A';
        $status = $s['status'] ?? 'N/A';
        $trackingStatus = $s['tracking_status'] ?? 'N/A';
        echo "ID: $easyshipId | Tracking: $trackingNumber | Carrier: $courier | Status: $status | Tracking Status: $trackingStatus\n";
    }
} else {
    echo "No shipments or invalid response:\n";
    echo substr($response, 0, 1000) . "\n";
}
