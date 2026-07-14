<?php
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/config.php';
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/db.php';

$requestId = 4;
$stmt = $pdo->prepare("SELECT * FROM label_requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

$apiKey = EASYSHIP_SAND_API_KEY;
$url1 = 'https://public-api-sandbox.easyship.com/2024-09/rates';

$payload1 = [
    'origin_address' => [
        'line_1' => $request['ship_from_address1'],
        'line_2' => $request['ship_from_address2'] ?: '',
        'city' => $request['ship_from_city'],
        'state' => $request['ship_from_state'],
        'postal_code' => $request['ship_from_zip'],
        'country_alpha2' => 'US',
        'contact_name' => substr($request['ship_from_name'], 0, 22),
        'contact_phone' => $request['ship_from_phone']
    ],
    'destination_address' => [
        'line_1' => $request['ship_to_address1'],
        'line_2' => $request['ship_to_address2'] ?: '',
        'city' => $request['ship_to_city'],
        'state' => $request['ship_to_state'],
        'postal_code' => $request['ship_to_zip'],
        'country_alpha2' => 'US',
        'contact_name' => substr($request['ship_to_name'], 0, 22),
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
                    'actual_weight' => floatval($request['weight_lbs']) * 0.453592,
                    'declared_currency' => 'USD',
                    'declared_customs_value' => 100.00,
                    'category' => 'home_decor'
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

$res = curl_exec($ch);
if ($res === false) {
    echo "cURL Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
    echo "Response: " . $res . "\n";
}
curl_close($ch);
