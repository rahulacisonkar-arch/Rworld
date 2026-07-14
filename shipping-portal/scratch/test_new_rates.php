<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/db.php';

$requestId = 4;
$stmt = $pdo->prepare("SELECT * FROM label_requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    die("Request ID 4 not found in database.\n");
}

$apiKey = EASYSHIP_API_KEY;
$env = EASYSHIP_ENV;
$apiBaseUrl = 'https://public-api.easyship.com'; // Production

echo "Using API Key: $apiKey\n\n";

// Test 1: Corrected Payload with 2024-09/rates
$url1 = $apiBaseUrl . '/2024-09/rates';
$payload1 = [
    'origin_address' => [
        'line_1' => $request['ship_from_address1'],
        'line_2' => $request['ship_from_address2'] ?: '',
        'city' => $request['ship_from_city'],
        'state' => $request['ship_from_state'],
        'postal_code' => $request['ship_from_zip'],
        'country_alpha2' => 'US',
        'contact_name' => substr($request['ship_from_name'], 0, 22), // max 22 chars
        'contact_phone' => $request['ship_from_phone']
    ],
    'destination_address' => [
        'line_1' => $request['ship_to_address1'],
        'line_2' => $request['ship_to_address2'] ?: '',
        'city' => $request['ship_to_city'],
        'state' => $request['ship_to_state'],
        'postal_code' => $request['ship_to_zip'],
        'country_alpha2' => 'US',
        'contact_name' => substr($request['ship_to_name'], 0, 22), // max 22 chars
        'contact_phone' => $request['ship_to_phone']
    ],
    'parcels' => [
        [
            'box' => [
                'length' => floatval($request['length']),
                'width' => floatval($request['width']),
                'height' => floatval($request['height']),
            ],
            'items' => [
                [
                    'description' => 'Fabrics & Home Logistics Items',
                    'quantity' => 1,
                    'actual_weight' => floatval($request['weight_lbs']) * 0.453592, // convert to kg
                    'declared_currency' => 'USD',
                    'declared_customs_value' => 100.00,
                    'category' => 'home_decor' // added category
                ]
            ]
        ]
    ]
];

$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
];

$ch = curl_init($url1);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload1));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "TEST 1 (2024-09/rates with truncated contact_name and category):\n";
echo "HTTP Code: $httpCode1\n";
echo "Response: $response1\n\n";
