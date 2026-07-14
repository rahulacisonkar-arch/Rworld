<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fpdf.php';

/**
 * EasyshipService — LIVE PRODUCTION ONLY
 * All mock/simulator fallbacks have been removed.
 * This service exclusively calls the real Easyship API.
 */
class EasyshipService {

    // Easyship 2024-09 API base URL for production
    private static $PROD_URL = 'https://public-api.easyship.com';
    private static $SAND_URL = 'https://public-api-sandbox.easyship.com';

    /**
     * Resolve the API key and base URL from environment setting.
     * Throws exception if no valid key is configured.
     */
    private static function resolveApiCredentials($env) {
        if ($env === 'production') {
            $apiKey = defined('EASYSHIP_PROD_API_KEY') ? EASYSHIP_PROD_API_KEY : '';
            $baseUrl = self::$PROD_URL;
        } else {
            $apiKey = defined('EASYSHIP_SAND_API_KEY') ? EASYSHIP_SAND_API_KEY : '';
            $baseUrl = self::$SAND_URL;
        }

        if (empty($apiKey)) {
            throw new Exception("Easyship API key for '{$env}' is not configured. Please add it to config.php.");
        }

        return [$apiKey, $baseUrl];
    }

    /**
     * Build standard parcel payload from a request array.
     */
    private static function buildParcelPayload($request) {
        return [
            [
                'box' => [
                    'length' => round(floatval($request['length']) * 2.54, 2), // in → cm
                    'width'  => round(floatval($request['width']) * 2.54, 2),  // in → cm
                    'height' => round(floatval($request['height']) * 2.54, 2)  // in → cm
                ],
                'items' => [
                    [
                        'description'           => 'Fabrics & Home Logistics Items',
                        'quantity'              => 1,
                        'actual_weight'         => round(floatval($request['weight_lbs']) * 0.453592, 4), // lbs → kg
                        'declared_currency'     => 'USD',
                        'declared_customs_value'=> 100.00,
                        'category'              => 'home_decor'
                    ]
                ]
            ]
        ];
    }

    /**
     * Execute a cURL POST request to the Easyship API.
     * Returns decoded JSON array or throws on error.
     */
    private static function apiPost($url, $payload, $apiKey, $timeout = 30) {
        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            throw new Exception('Failed to JSON-encode request payload: ' . json_last_error_msg());
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Network error connecting to Easyship: {$curlError}");
        }
        if ($httpCode === 0) {
            throw new Exception("No response from Easyship API (connection timed out).");
        }
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errMsg = '';
            if (isset($decoded['error'])) {
                $errMsg = is_array($decoded['error']) ? json_encode($decoded['error']) : $decoded['error'];
            } elseif (isset($decoded['message'])) {
                $errMsg = $decoded['message'];
            } elseif (isset($decoded['errors'])) {
                $errMsg = json_encode($decoded['errors']);
            } else {
                $errMsg = substr($response, 0, 300);
            }
            throw new Exception("Easyship API HTTP {$httpCode}: {$errMsg}");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception("Easyship API returned invalid JSON: " . substr($response, 0, 200));
        }

        return $data;
    }

    /**
     * Execute a cURL GET request to the Easyship API.
     * Returns decoded JSON array or throws on error.
     */
    private static function apiGet($url, $apiKey, $timeout = 30) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Network error connecting to Easyship: {$curlError}");
        }
        if ($httpCode === 0) {
            throw new Exception("No response from Easyship API (connection timed out).");
        }
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errMsg = '';
            if (isset($decoded['error'])) {
                $errMsg = is_array($decoded['error']) ? json_encode($decoded['error']) : $decoded['error'];
            } elseif (isset($decoded['message'])) {
                $errMsg = $decoded['message'];
            } elseif (isset($decoded['errors'])) {
                $errMsg = json_encode($decoded['errors']);
            } else {
                $errMsg = substr($response, 0, 300);
            }
            throw new Exception("Easyship API HTTP {$httpCode}: {$errMsg}");
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception("Easyship API returned invalid JSON: " . substr($response, 0, 200));
        }

        return $data;
    }

    /**
     * Get LIVE shipping rates from Easyship API.
     * NEVER returns mock data — throws Exception on any failure.
     *
     * @param array  $request  Shipping request array (from DB or temp form)
     * @param string $env      'production' or 'sandbox'
     * @return array           Array of rate objects sorted cheapest first
     * @throws Exception       On any API or network error
     */
    public static function getRates($request, $env) {
        list($apiKey, $baseUrl) = self::resolveApiCredentials($env);

        $payload = [
            'origin_address' => [
                'contact_name'  => substr(trim($request['ship_from_name'] ?? ''), 0, 22),
                'company_name'  => trim($request['ship_from_company'] ?? ''),
                'line_1'        => trim($request['ship_from_address1']),
                'line_2'        => trim($request['ship_from_address2'] ?? ''),
                'city'          => trim($request['ship_from_city']),
                'state'         => trim($request['ship_from_state']),
                'postal_code'   => trim($request['ship_from_zip']),
                'country_alpha2'=> 'US',
                'contact_phone' => trim($request['ship_from_phone'] ?? '')
            ],
            'destination_address' => [
                'contact_name'  => substr(trim($request['ship_to_name'] ?? ''), 0, 22),
                'company_name'  => trim($request['ship_to_company'] ?? ''),
                'line_1'        => trim($request['ship_to_address1']),
                'line_2'        => trim($request['ship_to_address2'] ?? ''),
                'city'          => trim($request['ship_to_city']),
                'state'         => trim($request['ship_to_state']),
                'postal_code'   => trim($request['ship_to_zip']),
                'country_alpha2'=> 'US',
                'contact_phone' => trim($request['ship_to_phone'] ?? '')
            ],
            'parcels' => self::buildParcelPayload($request)
        ];

        $data = self::apiPost($baseUrl . '/2024-09/rates', $payload, $apiKey, 30);

        $apiRates = $data['rates'] ?? [];
        if (empty($apiRates)) {
            throw new Exception("Easyship returned 0 available rates for this route/package. Check addresses and dimensions.");
        }

        $rates = [];
        foreach ($apiRates as $r) {
            $minDays = $r['min_delivery_time'] ?? null;
            $maxDays = $r['max_delivery_time'] ?? null;
            if ($minDays !== null && $maxDays !== null && $minDays === $maxDays) {
                $deliveryTime = $minDays . ' business day' . ($minDays == 1 ? '' : 's');
            } elseif ($minDays !== null && $maxDays !== null) {
                $deliveryTime = $minDays . '-' . $maxDays . ' business days';
            } else {
                $deliveryTime = 'Varies';
            }

            // Use the service-level UUID (unique per service), not the carrier-level courier_id
            $serviceId = $r['courier_service']['id'] ?? $r['id'] ?? '';

            $rates[] = [
                'courier_id'      => $serviceId,
                'courier_name'    => $r['courier_service']['name'] ?? 'Unknown Carrier',
                'shipment_charge' => floatval($r['total_charge'] ?? $r['shipment_charge'] ?? 0.00),
                'delivery_time'   => $deliveryTime
            ];
        }

        // Sort ascending by price
        usort($rates, function($a, $b) {
            return $a['shipment_charge'] <=> $b['shipment_charge'];
        });

        return $rates;
    }

    /**
     * Create a LIVE shipment + label via Easyship API.
     * NEVER falls back to mock — throws Exception on any failure.
     *
     * @param array       $request           DB request row
     * @param string|null $selectedCourierId Service-level UUID from getRates()
     * @param string|null $selectedCourierName
     * @param float|null  $selectedCost
     * @param string|null $selectedEnv       'production' or 'sandbox'
     * @return array  [success, tracking_number, carrier, estimated_delivery_date, label_file, actual_shipping_cost]
     * @throws Exception On any API or I/O error
     */
    public static function createLabel($request, $selectedCourierId = null, $selectedCourierName = null, $selectedCost = null, $selectedEnv = null) {
        $env = $selectedEnv ?: (defined('EASYSHIP_ENV') ? EASYSHIP_ENV : 'production');
        list($apiKey, $baseUrl) = self::resolveApiCredentials($env);

        return self::callEasyshipApi($request, $apiKey, $baseUrl, $selectedCourierId, $selectedCourierName, $selectedCost);
    }

    /**
     * Update tracking statuses for all active, stale shipments.
     * Stale = not updated in the last 15 minutes.
     * Non-terminal = not in 'delivered', 'cancelled', 'returned'.
     */
    public static function updateTrackingStatuses($selectedEnv = null) {
        global $pdo;
        
        $env = $selectedEnv ?: (defined('EASYSHIP_ENV') ? EASYSHIP_ENV : 'production');
        
        try {
            // Find request labels that are not in a terminal state and need an update
            $stmt = $pdo->query("SELECT id, request_id, easyship_shipment_id, tracking_status 
                                 FROM request_labels 
                                 WHERE easyship_shipment_id IS NOT NULL 
                                   AND (tracking_status IS NULL OR LOWER(tracking_status) NOT IN ('delivered', 'cancelled', 'returned'))
                                   AND (tracking_updated_at IS NULL OR tracking_updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE))");
            $staleLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($staleLabels)) {
                return; // Nothing to update
            }
            
            list($apiKey, $baseUrl) = self::resolveApiCredentials($env);
            
            // Chunk by 20 to avoid exceeding URL length limits
            $chunks = array_chunk($staleLabels, 20);
            
            foreach ($chunks as $chunk) {
                $ids = array_column($chunk, 'easyship_shipment_id');
                
                $queryParts = [];
                foreach ($ids as $id) {
                    $queryParts[] = 'easyship_shipment_id[]=' . urlencode($id);
                }
                
                $url = $baseUrl . '/2024-09/shipments/trackings?' . implode('&', $queryParts);
                
                $data = self::apiGet($url, $apiKey);
                $apiShipments = $data['shipments'] ?? [];
                
                $updateStmt = $pdo->prepare("UPDATE request_labels SET tracking_status = ?, tracking_updated_at = NOW() WHERE easyship_shipment_id = ?");
                
                foreach ($apiShipments as $s) {
                    $easyshipId = $s['easyship_shipment_id'] ?? '';
                    $status = $s['status'] ?? ''; // e.g. "Label Ready", "In Transit", "Delivered"
                    
                    if (!empty($easyshipId) && !empty($status)) {
                        $updateStmt->execute([$status, $easyshipId]);
                        
                        // If the status is "Delivered", check if we need to auto-complete the parent request
                        if (strtolower($status) === 'delivered') {
                            // Find the request_id for this label
                            $labelInfo = null;
                            foreach ($chunk as $item) {
                                if ($item['easyship_shipment_id'] === $easyshipId) {
                                    $labelInfo = $item;
                                    break;
                                }
                            }
                            if ($labelInfo) {
                                $reqId = $labelInfo['request_id'];
                                // Check if all other labels for this request are also delivered
                                $allLabelsStmt = $pdo->prepare("SELECT tracking_status FROM request_labels WHERE request_id = ?");
                                $allLabelsStmt->execute([$reqId]);
                                $allLabels = $allLabelsStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                $allDelivered = true;
                                foreach ($allLabels as $al) {
                                    if (strtolower($al['tracking_status'] ?? '') !== 'delivered') {
                                        $allDelivered = false;
                                        break;
                                    }
                                }
                                
                                if ($allDelivered) {
                                    // Transition parent to 'Completed'
                                    $parentStmt = $pdo->prepare("UPDATE label_requests SET status = 'Completed' WHERE id = ? AND status != 'Completed'");
                                    $parentStmt->execute([$reqId]);
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Log error silently so dashboard doesn't crash on network failures
            error_log("Error in updateTrackingStatuses: " . $e->getMessage());
        }
    }

    /**
     * Internal: Call Easyship API to create a shipment and buy a label synchronously.
     */
    private static function callEasyshipApi($request, $apiKey, $baseUrl, $selectedCourierId = null, $selectedCourierName = null, $selectedCost = null) {
        $url = $baseUrl . '/2024-09/shipments';

        $payload = [
            'origin_address' => [
                'contact_name'  => substr(trim($request['ship_from_name'] ?? ''), 0, 22),
                'company_name'  => trim($request['ship_from_company'] ?? ''),
                'line_1'        => trim($request['ship_from_address1']),
                'line_2'        => trim($request['ship_from_address2'] ?? ''),
                'city'          => trim($request['ship_from_city']),
                'state'         => trim($request['ship_from_state']),
                'postal_code'   => trim($request['ship_from_zip']),
                'country_alpha2'=> 'US',
                'contact_phone' => trim($request['ship_from_phone'] ?? ''),
                'contact_email' => !empty(trim($request['ship_from_email'] ?? '')) ? trim($request['ship_from_email']) : 'shipping@arteefabrics.com'
            ],
            'destination_address' => [
                'contact_name'  => substr(trim($request['ship_to_name'] ?? ''), 0, 22),
                'company_name'  => trim($request['ship_to_company'] ?? ''),
                'line_1'        => trim($request['ship_to_address1']),
                'line_2'        => trim($request['ship_to_address2'] ?? ''),
                'city'          => trim($request['ship_to_city']),
                'state'         => trim($request['ship_to_state']),
                'postal_code'   => trim($request['ship_to_zip']),
                'country_alpha2'=> 'US',
                'contact_phone' => trim($request['ship_to_phone'] ?? ''),
                'contact_email' => !empty(trim($request['ship_to_email'] ?? '')) ? trim($request['ship_to_email']) : 'customer@example.com'
            ],
            'parcels' => self::buildParcelPayload($request),
            'shipping_settings' => [
                'buy_label'              => true,
                'buy_label_synchronous'  => true,
                'printing_options'       => [
                    'format' => 'pdf',
                    'label'  => 'A4'
                ]
            ]
        ];

        // Pass selected service UUID when user picks a specific carrier from rates
        if (!empty($selectedCourierId)) {
            $payload['courier_settings'] = [
                'courier_service_id' => $selectedCourierId
            ];
        }

        // Synchronous label creation can take up to 45s
        $data = self::apiPost($url, $payload, $apiKey, 90);

        $shipment = $data['shipment'] ?? null;
        if (!$shipment) {
            throw new Exception("Easyship API did not return a shipment object. Response: " . json_encode($data));
        }

        $easyshipId = $shipment['easyship_shipment_id'] ?? null;
        if (!$easyshipId) {
            throw new Exception("Easyship Shipment ID missing from response: " . json_encode($shipment));
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
        $shippingDocuments = $shipment['shipping_documents'] ?? [];
        foreach ($shippingDocuments as $doc) {
            if (($doc['category'] ?? '') === 'label') {
                $labelUrl = $doc['url'] ?? '';
                break;
            }
        }
        if (empty($labelUrl)) {
            $labelUrl = $shipment['label']['label_raw_url'] ?? $shipment['label']['label_url'] ?? '';
        }

        // Poll if tracking number or label URL is missing (async label generation)
        if (empty($labelUrl) || empty($trackingNumber)) {
            $getShipmentUrl = $baseUrl . '/2024-09/shipments/' . $easyshipId;
            for ($attempt = 1; $attempt <= 8; $attempt++) {
                usleep(2500000); // Wait 2.5 seconds
                try {
                    $pollData = self::apiGet($getShipmentUrl, $apiKey);
                    $pollShipment = $pollData['shipment'] ?? null;
                    if ($pollShipment) {
                        $chkTracking = '';
                        if (!empty($pollShipment['trackings'])) {
                            $chkTracking = $pollShipment['trackings'][0]['tracking_number'] ?? '';
                        }
                        if (empty($chkTracking)) {
                            $chkTracking = $pollShipment['label']['tracking_number'] ?? '';
                        }

                        $chkLabelUrl = '';
                        foreach ($pollShipment['shipping_documents'] ?? [] as $doc) {
                            if (($doc['category'] ?? '') === 'label') {
                                $chkLabelUrl = $doc['url'] ?? '';
                                break;
                            }
                        }
                        if (empty($chkLabelUrl)) {
                            $chkLabelUrl = $pollShipment['label']['label_raw_url'] ?? $pollShipment['label']['label_url'] ?? '';
                        }

                        if (!empty($chkLabelUrl)) {
                            $labelUrl = $chkLabelUrl;
                        }
                        if (!empty($chkTracking)) {
                            $trackingNumber = $chkTracking;
                        }

                        $shipment = $pollShipment;

                        if (!empty($labelUrl) && !empty($trackingNumber)) {
                            break;
                        }
                    }
                } catch (Exception $ex) {
                    // Ignore error and continue retrying
                }
            }
        }

        if (empty($trackingNumber)) {
            throw new Exception("Tracking number not provided in shipment response for {$easyshipId}.");
        }
        if (empty($labelUrl)) {
            throw new Exception("Label PDF URL not found in shipment response for {$easyshipId}.");
        }

        // Force page_size to a4 to make the printed label exactly half of the page
        if (strpos($labelUrl, 'page_size=') !== false) {
            $labelUrl = preg_replace('/page_size=[a-zA-Z0-9_]+/i', 'page_size=a4', $labelUrl);
        } else {
            $separator = (strpos($labelUrl, '?') !== false) ? '&' : '?';
            $labelUrl .= $separator . 'page_size=a4';
        }

        // Download the PDF label
        $pdfContent = self::downloadFile($labelUrl);

        // Save to secure uploads
        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        $savedFilename = 'label_' . $request['id'] . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $destPath = UPLOAD_DIR . $savedFilename;

        if (file_put_contents($destPath, $pdfContent) === false) {
            throw new Exception("Failed to save label PDF to storage. Tracking: {$trackingNumber}");
        }

        // Calculate actual cost
        $actualCost = floatval($selectedCost ?? 0.00);
        $minDeliveryTime = 3;
        $rates = $shipment['rates'] ?? [];
        foreach ($rates as $r) {
            $rateServiceId = $r['courier_service']['id'] ?? $r['id'] ?? '';
            if ($rateServiceId === $selectedCourierId) {
                $actualCost = floatval($r['total_charge'] ?? $r['shipment_charge'] ?? $actualCost);
                if (!empty($r['min_delivery_time'])) {
                    $minDeliveryTime = intval($r['min_delivery_time']);
                }
                break;
            }
        }

        // Estimated delivery
        $estDelivery = date('Y-m-d', strtotime('+' . $minDeliveryTime . ' days'));

        $carrier = $shipment['courier_service']['name'] ?? $selectedCourierName ?? 'Easyship Carrier';

        return [
            'success'               => true,
            'tracking_number'       => $trackingNumber,
            'carrier'               => $carrier,
            'estimated_delivery_date' => $estDelivery,
            'label_file'            => $savedFilename,
            'actual_shipping_cost'  => $actualCost,
            'easyship_shipment_id'  => $easyshipId,
            'error'                 => ''
        ];
    }

    /**
     * Download a file from a URL and return its contents.
     * Throws on network or HTTP errors.
     */
    private static function downloadFile($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Failed to download label PDF: {$curlError}");
        }
        if ($httpCode >= 400 || empty($content)) {
            throw new Exception("Label PDF download failed (HTTP {$httpCode}) from URL: {$url}");
        }
        return $content;
    }
}
