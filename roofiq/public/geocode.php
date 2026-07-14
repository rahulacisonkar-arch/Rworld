<?php
/**
 * ROOFIQ AI — Geocoding Proxy
 * POST: { address: "..." }
 * Returns: { lat, lng, formatted_address, place_id, viewport }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/src/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body    = file_get_contents('php://input');
$payload = json_decode($body, true);
$address = trim($payload['address'] ?? '');

if (empty($address)) {
    echo json_encode(['error' => 'Address is required']);
    exit;
}

// Cache lookup
$cacheFile = ROOFIQ_ROOT . '/data/geocode_cache.json';
$cache = [];
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true) ?: [];
}

$cacheKey = md5(strtolower($address));
if (isset($cache[$cacheKey])) {
    echo json_encode(array_merge($cache[$cacheKey], ['cached' => true]));
    exit;
}

$apiKey = roofiq_google_key();

if (empty($apiKey)) {
    // Return a mock result if no API key — for development
    $mockResult = [
        'lat'               => 37.4224764,
        'lng'               => -122.0842499,
        'formatted_address' => $address,
        'place_id'          => 'mock_' . $cacheKey,
        'viewport'          => [
            'northeast' => ['lat' => 37.4238254, 'lng' => -122.0829009],
            'southwest' => ['lat' => 37.4211275, 'lng' => -122.0855989],
        ],
        'mock' => true,
    ];
    echo json_encode($mockResult);
    exit;
}

$url = 'https://maps.googleapis.com/maps/api/geocode/json?'
     . 'address=' . urlencode($address)
     . '&key=' . urlencode($apiKey);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Geocoding request failed: ' . $err]);
    exit;
}

$data = json_decode($response, true);
if (!isset($data['results'][0])) {
    echo json_encode(['error' => 'No results found for this address', 'raw' => $data]);
    exit;
}

$result_raw = $data['results'][0];
$result = [
    'lat'               => $result_raw['geometry']['location']['lat'],
    'lng'               => $result_raw['geometry']['location']['lng'],
    'formatted_address' => $result_raw['formatted_address'],
    'place_id'          => $result_raw['place_id'],
    'viewport'          => $result_raw['geometry']['viewport'] ?? null,
];

// Cache the result
$cache[$cacheKey] = $result;
@file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));

echo json_encode($result);
