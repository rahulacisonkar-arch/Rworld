<?php
// Easyship Live Rates Fetcher — by Request ID (PRODUCTION ONLY)
set_time_limit(120);

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();
require_role(['Super Admin', 'Logistics Admin']);

header('Content-Type: application/json');

$requestId = intval($_GET['id'] ?? 0);
if ($requestId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Request ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM label_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Shipping request not found']);
        exit;
    }

    // Always use production unless sandbox explicitly passed
    $requestedEnv = trim($_GET['env'] ?? '');
    $env = ($requestedEnv === 'sandbox') ? 'sandbox' : 'production';

    require_once dirname(__DIR__) . '/src/EasyshipService.php';
    $rates = EasyshipService::getRates($request, $env);

    echo json_encode([
        'success' => true,
        'rates'   => $rates,
        'env'     => $env,
        'count'   => count($rates)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
