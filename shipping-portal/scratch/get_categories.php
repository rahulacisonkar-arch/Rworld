<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';

$apiKey = EASYSHIP_API_KEY;
$url = 'https://public-api.easyship.com/2024-09/item_categories?per_page=100';

$headers = [
    'Authorization: Bearer ' . $apiKey
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['item_categories'])) {
    foreach ($data['item_categories'] as $cat) {
        echo "ID: " . $cat['id'] . " | Name: " . $cat['name'] . " | Slug: " . $cat['slug'] . "\n";
    }
} else {
    echo "No categories found or API error.\n";
}
