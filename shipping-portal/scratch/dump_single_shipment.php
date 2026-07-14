<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';

$apiKey = EASYSHIP_PROD_API_KEY;
$url = 'https://public-api.easyship.com/2024-09/shipments?per_page=1';

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
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['shipments'][0])) {
    file_put_contents('c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/scratch/single_shipment_dump.json', json_encode($data['shipments'][0], JSON_PRETTY_PRINT));
    echo "Dumped first shipment details successfully.\n";
} else {
    echo "No shipments found in response.\n";
}
