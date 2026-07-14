<?php
require_once __DIR__ . '/db.php';

// Safe session startup
function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set unique session name to prevent collision with other apps on the same domain/IP
        session_name('ARTEE_SHIPPING_SESSID');
        
        // Set secure cookie options if supported
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        if (version_compare(PHP_VERSION, '7.3.0', '<')) {
            session_set_cookie_params(SESSION_LIFETIME, '/', '', $secure, true);
        } else {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_start();
    }
}

// Escaping shorthand for XSS prevention
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Generate CSRF Token
function get_csrf_token() {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF Token
function validate_csrf_token($token) {
    session_start_safe();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Print CSRF hidden input
function csrf_input() {
    echo '<input type="hidden" name="csrf_token" value="' . e(get_csrf_token()) . '">';
}

// Check logged in
function is_logged_in() {
    session_start_safe();
    return isset($_SESSION['user_id']);
}

// Require login and check role
function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }
}

// Check role authorization
function require_role($allowedRoles) {
    require_login();
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    if (!in_array($role, $allowedRoles)) {
        // Forbidden
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p>";
        echo "<p><a href='logout.php' style='display: inline-block; padding: 8px 16px; background-color: #0B2545; color: white; text-decoration: none; border-radius: 4px; font-family: sans-serif; font-size: 0.9rem;'>Log Out / Switch Accounts</a></p>";
        exit;
    }
}

// Find store by zip and address robustly (first word/number matching)
function find_store_by_address($zip, $address, $company = '') {
    global $pdo;
    $zip = trim($zip);
    $address = trim($address);
    $company = trim($company);
    
    if ($zip === '' || $address === '') {
        return null;
    }
    
    try {
        $stmt = $pdo->query("SELECT * FROM stores");
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($stores as $store) {
            if (trim($store['zip']) === $zip) {
                // If company name matches store name keywords (Artee, Printer, etc.)
                $companyLower = strtolower($company);
                if ($companyLower !== '' && (
                    strpos($companyLower, 'artee') !== false ||
                    strpos($companyLower, 'printer') !== false ||
                    strpos($companyLower, 'good goods') !== false ||
                    strpos($companyLower, 'rags') !== false
                )) {
                    return $store;
                }
                
                // If first word (street number) matches
                $storeAddrParts = preg_split('/[^A-Za-z0-9]/', trim($store['address']));
                $shipAddrParts = preg_split('/[^A-Za-z0-9]/', $address);
                if (!empty($storeAddrParts[0]) && !empty($shipAddrParts[0]) && strtolower($storeAddrParts[0]) === strtolower($shipAddrParts[0])) {
                    return $store;
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error in find_store_by_address: " . $e->getMessage());
    }
    return null;
}

// Check if user matches their store (Stores can only view their own requests or requests destined for them)
function check_request_ownership($request) {
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    if ($role === 'Store User') {
        $storeId = $_SESSION['store_id'];
        
        $requestStoreId = is_array($request) ? $request['store_id'] : $request;
        if ($requestStoreId == $storeId) {
            return;
        }
        
        // If it's an array, check if destined for this store
        if (is_array($request) && isset($request['ship_to_zip']) && isset($request['ship_to_address1'])) {
            $destStore = find_store_by_address($request['ship_to_zip'], $request['ship_to_address1'], $request['ship_to_company'] ?? '');
            if ($destStore && $destStore['id'] == $storeId) {
                return;
            }
        }
        
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have access to this request.</p>";
        echo "<p><a href='logout.php' style='display: inline-block; padding: 8px 16px; background-color: #0B2545; color: white; text-decoration: none; border-radius: 4px; font-family: sans-serif; font-size: 0.9rem;'>Log Out / Switch Accounts</a></p>";
        exit;
    }
}

// Log an action to activity_logs
function log_activity($userId, $action, $requestId = null, $details = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, request_id, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $requestId, $details]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Add a notification for a user
function add_notification($userId, $title, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $title, $message]);
    } catch (PDOException $e) {
        error_log("Failed to add notification: " . $e->getMessage());
    }
}

// Get labels associated with a notification by parsing AR-XXXX request number
function get_notification_labels($title, $message) {
    global $pdo;
    if (preg_match('/AR-(\d+)/', $title, $matches) || preg_match('/AR-(\d+)/', $message, $matches)) {
        $reqNum = $matches[0];
        try {
            $stmt = $pdo->prepare("SELECT id FROM label_requests WHERE request_number = ?");
            $stmt->execute([$reqNum]);
            $reqId = $stmt->fetchColumn();
            if ($reqId) {
                $stmtLabels = $pdo->prepare("SELECT id, tracking_number, carrier FROM request_labels WHERE request_id = ?");
                $stmtLabels->execute([$reqId]);
                $labels = $stmtLabels->fetchAll(PDO::FETCH_ASSOC);
                return [
                    'request_id' => intval($reqId),
                    'request_number' => $reqNum,
                    'labels' => $labels
                ];
            }
        } catch (PDOException $e) {
            error_log("Error in get_notification_labels: " . $e->getMessage());
        }
    }
    return null;
}

// Helper to resolve the correct store (destination or origin) for a request from a stores map
function get_correct_store_for_request($request, $storesMap) {
    $shipToZip = trim($request['ship_to_zip'] ?? '');
    $shipToAddr1 = trim($request['ship_to_address1'] ?? '');
    $shipFromZip = trim($request['ship_from_zip'] ?? '');
    $shipFromAddr1 = trim($request['ship_from_address1'] ?? '');

    // Check Ship To (Incoming shipment to a store)
    if ($shipToZip !== '' && $shipToAddr1 !== '') {
        foreach ($storesMap as $store) {
            if (trim($store['zip']) === $shipToZip) {
                // Split addresses by non-alphanumeric to find the first word (often street number)
                $storeAddrParts = preg_split('/[^A-Za-z0-9]/', trim($store['address']));
                $shipAddrParts = preg_split('/[^A-Za-z0-9]/', $shipToAddr1);
                if (!empty($storeAddrParts[0]) && !empty($shipAddrParts[0]) && strtolower($storeAddrParts[0]) === strtolower($shipAddrParts[0])) {
                    return $store;
                }
            }
        }
    }

    // Check Ship From (Outgoing shipment from a store)
    if ($shipFromZip !== '' && $shipFromAddr1 !== '') {
        foreach ($storesMap as $store) {
            if (trim($store['zip']) === $shipFromZip) {
                $storeAddrParts = preg_split('/[^A-Za-z0-9]/', trim($store['address']));
                $shipAddrParts = preg_split('/[^A-Za-z0-9]/', $shipFromAddr1);
                if (!empty($storeAddrParts[0]) && !empty($shipAddrParts[0]) && strtolower($storeAddrParts[0]) === strtolower($shipAddrParts[0])) {
                    return $store;
                }
            }
        }
    }

    // Fallback to r.store_id's store
    $storeId = intval($request['store_id'] ?? 0);
    if ($storeId > 0 && isset($storesMap[$storeId])) {
        return $storesMap[$storeId];
    }

    return null;
}


// Thread-safe Request Number Generator (AR-1001, AR-1002...)
function generate_request_number() {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Select last request number using write-lock
        $stmt = $pdo->query("SELECT request_number FROM label_requests ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $lastRow = $stmt->fetch();
        
        $nextNumber = 1001;
        if ($lastRow) {
            $lastNumStr = $lastRow['request_number'];
            if (preg_match('/AR-(\d+)/', $lastNumStr, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }
        }
        
        $pdo->commit();
        return 'AR-' . $nextNumber;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Failed to generate request number: " . $e->getMessage());
        // Return a backup random identifier in case of total failure
        return 'AR-' . rand(10000, 99999);
    }
}

// Get minimum freight charge from database
function get_minimum_freight_charge() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'minimum_freight_charge'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? floatval($val) : 15.00;
    } catch (Exception $e) {
        return 15.00;
    }
}
// Extract raw text streams from PDF file (decompresses FlateDecode streams)
function extractTextFromPdf($filename) {
    if (!file_exists($filename)) {
        return false;
    }
    
    $content = file_get_contents($filename);
    $texts = [];
    
    // Find all streams in the PDF
    preg_match_all('/stream(.*?)endstream/is', $content, $matches);
    
    foreach ($matches[1] as $stream) {
        $stream = trim($stream);
        
        // Try to decompress stream
        $data = @gzuncompress($stream);
        if ($data === false) {
            // Try to decompress by stripping leading/trailing whitespace/newlines
            $data = @gzuncompress(trim($stream, "\r\n"));
        }
        
        if ($data !== false) {
            $texts[] = $data;
        } else {
            // Maybe it is uncompressed text
            $texts[] = $stream;
        }
    }
    
    // Also include raw content
    $texts[] = $content;
    
    return implode("\n", $texts);
}

// Notify requester store and destination store users when shipment status changes
function notify_shipment_status_change($requestId, $newStatus) {
    global $pdo;
    try {
        // Fetch request details
        $stmt = $pdo->prepare("SELECT r.*, s.store_name, s.store_code 
                                FROM label_requests r 
                                JOIN stores s ON r.store_id = s.id 
                                WHERE r.id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            return;
        }

        // Fetch associated carton tracking info
        $labels_stmt = $pdo->prepare("SELECT tracking_number, carrier FROM request_labels WHERE request_id = ? ORDER BY id ASC");
        $labels_stmt->execute([$requestId]);
        $labels = $labels_stmt->fetchAll(PDO::FETCH_ASSOC);

        $trackingList = [];
        foreach ($labels as $lbl) {
            if (!empty($lbl['tracking_number'])) {
                $trackingList[] = $lbl['tracking_number'] . " (" . ($lbl['carrier'] ?? 'N/A') . ")";
            }
        }
        $trackingStr = !empty($trackingList) ? implode(', ', $trackingList) : 'Not Provided';

        // Notify Creator Store Users
        $stmtStoreUsers = $pdo->prepare("SELECT id FROM users WHERE store_id = ?");
        $stmtStoreUsers->execute([$request['store_id']]);
        $storeUsers = $stmtStoreUsers->fetchAll(PDO::FETCH_ASSOC);

        // Notify Destination Store Users (if different)
        $destStore = find_store_by_address($request['ship_to_zip'], $request['ship_to_address1'], $request['ship_to_company'] ?? '');
        $destStoreUsers = [];
        if ($destStore && $destStore['id'] != $request['store_id']) {
            $stmtDestStoreUsers = $pdo->prepare("SELECT id FROM users WHERE store_id = ?");
            $stmtDestStoreUsers->execute([$destStore['id']]);
            $destStoreUsers = $stmtDestStoreUsers->fetchAll(PDO::FETCH_ASSOC);
        }

        // Construct message based on status
        $titleCreator = "Shipment Status Updated - " . $request['request_number'];
        $titleDest = "Incoming Shipment Status Updated - " . $request['request_number'];
        
        if ($newStatus === 'Label Created') {
            $titleCreator = "Your shipping labels are ready - " . $request['request_number'];
            $titleDest = "Incoming shipment labels are ready - " . $request['request_number'];
            $msg = "Your shipping labels (" . count($labels) . " cartons) are ready. SO#: " . $request['sales_order_number'] . ". Tracking: " . $trackingStr;
        } elseif ($newStatus === 'Completed') {
            $msg = "Shipment status for " . $request['request_number'] . " (SO#: " . $request['sales_order_number'] . ") has been updated to: Completed. Tracking: " . $trackingStr;
        } else {
            $msg = "Shipment status for " . $request['request_number'] . " (SO#: " . $request['sales_order_number'] . ") has been updated to: " . $newStatus;
        }

        foreach ($storeUsers as $su) {
            add_notification($su['id'], $titleCreator, $msg);
        }
        foreach ($destStoreUsers as $su) {
            add_notification($su['id'], $titleDest, $msg);
        }

    } catch (PDOException $e) {
        error_log("Error in notify_shipment_status_change: " . $e->getMessage());
    }
}

