<?php
// AJAX Live Rates Fetcher — PRODUCTION ONLY, no mock fallbacks
set_time_limit(120); // Allow up to 2 minutes for Easyship API response

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/EasyshipService.php';

session_start_safe();
require_login();
require_role(['Super Admin', 'Logistics Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Read POST data (form-encoded or JSON body)
$input = $_POST;
if (empty($input)) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: [];
}

// Build request array from input
$tempRequest = [
    'ship_from_name'     => trim($input['ship_from_name'] ?? ''),
    'ship_from_company'  => trim($input['ship_from_company'] ?? ''),
    'ship_from_address1' => trim($input['ship_from_address1'] ?? ''),
    'ship_from_address2' => trim($input['ship_from_address2'] ?? ''),
    'ship_from_city'     => trim($input['ship_from_city'] ?? ''),
    'ship_from_state'    => trim($input['ship_from_state'] ?? ''),
    'ship_from_zip'      => trim($input['ship_from_zip'] ?? ''),
    'ship_from_phone'    => trim($input['ship_from_phone'] ?? ''),

    'ship_to_name'       => trim($input['ship_to_name'] ?? ''),
    'ship_to_company'    => trim($input['ship_to_company'] ?? ''),
    'ship_to_address1'   => trim($input['ship_to_address1'] ?? ''),
    'ship_to_address2'   => trim($input['ship_to_address2'] ?? ''),
    'ship_to_city'       => trim($input['ship_to_city'] ?? ''),
    'ship_to_state'      => trim($input['ship_to_state'] ?? ''),
    'ship_to_zip'        => trim($input['ship_to_zip'] ?? ''),
    'ship_to_phone'      => trim($input['ship_to_phone'] ?? ''),

    'length'             => floatval($input['length'] ?? 0),
    'width'              => floatval($input['width'] ?? 0),
    'height'             => floatval($input['height'] ?? 0),
    'weight_lbs'         => floatval($input['weight_lbs'] ?? 0),
];

// Validate required fields
if (empty($tempRequest['ship_from_address1']) || empty($tempRequest['ship_from_city']) || empty($tempRequest['ship_from_state']) || empty($tempRequest['ship_from_zip'])) {
    echo json_encode(['success' => false, 'error' => 'Incomplete Ship From address. Please fill all required fields.']);
    exit;
}
if (empty($tempRequest['ship_to_address1']) || empty($tempRequest['ship_to_city']) || empty($tempRequest['ship_to_state']) || empty($tempRequest['ship_to_zip'])) {
    echo json_encode(['success' => false, 'error' => 'Incomplete Ship To address. Please fill all required fields.']);
    exit;
}
if ($tempRequest['length'] <= 0 || $tempRequest['width'] <= 0 || $tempRequest['height'] <= 0 || $tempRequest['weight_lbs'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'Package dimensions (L x W x H) and weight must all be greater than zero.']);
    exit;
}

// Resolve environment — always default to production
$requestedEnv = trim($input['env'] ?? '');
if ($requestedEnv === 'sandbox') {
    $env = 'sandbox';
} else {
    // Always use production unless sandbox is explicitly requested
    $env = 'production';
}

try {
    $rates = EasyshipService::getRates($tempRequest, $env);

    echo json_encode([
        'success' => true,
        'rates'   => $rates,
        'env'     => $env,
        'count'   => count($rates)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
