<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';

$apiKey = EASYSHIP_PROD_API_KEY;
$shipmentId = 'ESUS339105720';
$url = 'https://public-api.easyship.com/2024-09/shipments/trackings?easyship_shipment_id[]=' . $shipmentId;

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
file_put_contents('c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/scratch/tracking_response_dump.json', json_encode(json_decode($response, true), JSON_PRETTY_PRINT));
echo "Dumped tracking details successfully.\n";
