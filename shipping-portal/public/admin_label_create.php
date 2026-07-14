<?php
$pageTitle = "Create Vendor Label";
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();
require_role(['Super Admin', 'Logistics Admin']);

$userId    = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$role      = $_SESSION['role'];

// Fetch all stores and saved addresses for dropdowns
try {
    $stmtStores = $pdo->query("SELECT * FROM stores ORDER BY store_code ASC");
    $allStores = $stmtStores->fetchAll();

    // Determine default store ID for backend context association
    $defaultStoreId = 1;
    if (!empty($allStores)) {
        $defaultStoreId = intval($allStores[0]['id']);
    }

    // Fetch unique saved addresses from database to prevent duplicates in the admin selectors
    $stmtSavedFrom = $pdo->query("SELECT sa.*, s.store_code, s.store_name 
                                  FROM saved_addresses sa 
                                  JOIN stores s ON sa.store_id = s.id 
                                  WHERE sa.address_type = 'from' 
                                    AND sa.id IN (SELECT MIN(id) FROM saved_addresses WHERE address_type = 'from' GROUP BY company, address1)
                                  ORDER BY sa.company ASC, sa.name ASC");
    $savedFromAddresses = $stmtSavedFrom->fetchAll();

    $stmtSavedTo = $pdo->query("SELECT sa.*, s.store_code, s.store_name 
                                FROM saved_addresses sa 
                                JOIN stores s ON sa.store_id = s.id 
                                WHERE sa.address_type = 'to' 
                                  AND sa.id IN (SELECT MIN(id) FROM saved_addresses WHERE address_type = 'to' GROUP BY company, address1)
                                ORDER BY sa.company ASC, sa.name ASC");
    $savedToAddresses = $stmtSavedTo->fetchAll();
} catch (Exception $e) {
    die("Error retrieving configuration data: " . $e->getMessage());
}

$minFreightCharge = get_minimum_freight_charge();
$error = '';
$success = false;
$generatedReqNumber = '';
$labelDetails = null;
$storeDetails = null;
$destStoreDetails = null;

// Check if redirected after success to download
if (isset($_GET['success']) && isset($_GET['req_num'])) {
    $success = true;
    $generatedReqNumber = trim($_GET['req_num']);
    $successLabelId = intval($_GET['label_id'] ?? 0);
    
    try {
        // Query request and label information
        $stmt = $pdo->prepare("SELECT lr.*, rl.tracking_number, rl.carrier 
                                FROM label_requests lr
                                LEFT JOIN request_labels rl ON rl.request_id = lr.id
                                WHERE lr.request_number = ? AND (rl.id = ? OR ? = 0)
                                LIMIT 1");
        $stmt->execute([$generatedReqNumber, $successLabelId, $successLabelId]);
        $labelDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($labelDetails) {
            // Get the store the label was created for (context store)
            $stmtStore = $pdo->prepare("SELECT id, store_name, store_code, address, city, state, zip FROM stores WHERE id = ?");
            $stmtStore->execute([$labelDetails['store_id']]);
            $storeDetails = $stmtStore->fetch(PDO::FETCH_ASSOC);
            
            // Check if the destination address matches any registered store address
            $stmtDest = $pdo->prepare("SELECT id, store_name, store_code, address, city, state, zip FROM stores WHERE zip = ? AND address = ?");
            $stmtDest->execute([$labelDetails['ship_to_zip'], $labelDetails['ship_to_address1']]);
            $destStoreDetails = $stmtDest->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching success details: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF
    if (!validate_csrf_token($csrf_token)) {
        $error = "Security validation failed (CSRF token invalid). Please try again.";
    } else {
        $storeId = intval($_POST['store_id'] ?? $defaultStoreId);
        
        // Collect Ship From Fields
        $ship_from_name = trim($_POST['ship_from_name'] ?? '');
        $ship_from_company = trim($_POST['ship_from_company'] ?? '');
        $ship_from_address1 = trim($_POST['ship_from_address1'] ?? '');
        $ship_from_address2 = trim($_POST['ship_from_address2'] ?? '');
        $ship_from_city = trim($_POST['ship_from_city'] ?? '');
        $ship_from_state = trim($_POST['ship_from_state'] ?? '');
        $ship_from_zip = trim($_POST['ship_from_zip'] ?? '');
        $ship_from_phone = trim($_POST['ship_from_phone'] ?? '');
        $ship_from_email = trim($_POST['ship_from_email'] ?? '');

        // Collect Ship To Fields
        $ship_to_name = trim($_POST['ship_to_name'] ?? '');
        $ship_to_company = trim($_POST['ship_to_company'] ?? '');
        $ship_to_address1 = trim($_POST['ship_to_address1'] ?? '');
        $ship_to_address2 = trim($_POST['ship_to_address2'] ?? '');
        $ship_to_city = trim($_POST['ship_to_city'] ?? '');
        $ship_to_state = trim($_POST['ship_to_state'] ?? '');
        $ship_to_zip = trim($_POST['ship_to_zip'] ?? '');
        $ship_to_phone = trim($_POST['ship_to_phone'] ?? '');
        $ship_to_email = trim($_POST['ship_to_email'] ?? '');

        // Order details
        $sales_order_number = trim($_POST['sales_order_number'] ?? '');
        $request_reference = trim($_POST['request_reference'] ?? '');

        // Package details
        $length = floatval($_POST['length'] ?? 0);
        $width = floatval($_POST['width'] ?? 0);
        $height = floatval($_POST['height'] ?? 0);
        $weight_lbs = floatval($_POST['weight_lbs'] ?? 0);

        // Selected Rate details
        $selectedCourierId = trim($_POST['selected_courier_id'] ?? '');
        $selectedCourierName = trim($_POST['selected_courier_name'] ?? '');
        $selectedCourierCost = floatval($_POST['selected_courier_cost'] ?? 0);
        $selectedEnv = trim($_POST['selected_env'] ?? 'production');
        $customer_freight_charge = floatval($_POST['customer_freight_charge'] ?? 0);
        $special_instructions = trim($_POST['special_instructions'] ?? '');

        // Validation Checks
        if (empty($ship_from_name) || empty($ship_from_company) || empty($ship_from_address1) || empty($ship_from_city) || empty($ship_from_state) || empty($ship_from_zip) || empty($ship_from_phone)) {
            $error = "All 'Ship From' address fields are required.";
        } elseif (empty($ship_to_name) || empty($ship_to_company) || empty($ship_to_address1) || empty($ship_to_city) || empty($ship_to_state) || empty($ship_to_zip) || empty($ship_to_phone) || empty($ship_to_email)) {
            $error = "All 'Ship To' address fields are required.";
        } elseif (empty($sales_order_number)) {
            $error = "Sales/Sales Order/Purchase Order is required.";
        } elseif ($length <= 0 || $width <= 0 || $height <= 0 || $weight_lbs <= 0) {
            $error = "Package dimensions and weight must be greater than zero.";
        } elseif (empty($selectedCourierId)) {
            $error = "Please select a live shipping rate before creating the label.";
        }

        // If no errors, create label and insert request directly
        if (empty($error)) {
            try {
                // Generate sequential request number
                $request_number = generate_request_number();

                $sql = "INSERT INTO label_requests (
                            request_number, store_id, 
                            ship_from_name, ship_from_company, ship_from_address1, ship_from_address2, ship_from_city, ship_from_state, ship_from_zip, ship_from_phone, ship_from_email,
                            ship_to_name, ship_to_company, ship_to_address1, ship_to_address2, ship_to_city, ship_to_state, ship_to_zip, ship_to_phone, ship_to_email,
                            sales_order_number, request_reference, 
                            length, width, height, weight_lbs, 
                            shipping_method, customer_freight_charge, special_instructions, status
                        ) VALUES (
                            :req_num, :store_id,
                            :from_name, :from_comp, :from_addr1, :from_addr2, :from_city, :from_state, :from_zip, :from_phone, :from_email,
                            :to_name, :to_comp, :to_addr1, :to_addr2, :to_city, :to_state, :to_zip, :to_phone, :to_email,
                            :so_num, :req_ref,
                            :len, :wid, :hgt, :wgt,
                            :method, :freight, :instructions, 'Pending'
                        )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':req_num' => $request_number,
                    ':store_id' => $storeId,
                    ':from_name' => $ship_from_name,
                    ':from_comp' => $ship_from_company,
                    ':from_addr1' => $ship_from_address1,
                    ':from_addr2' => $ship_from_address2 ?: null,
                    ':from_city' => $ship_from_city,
                    ':from_state' => $ship_from_state,
                    ':from_zip' => $ship_from_zip,
                    ':from_phone' => $ship_from_phone,
                    ':from_email' => $ship_from_email ?: null,
                    ':to_name' => $ship_to_name,
                    ':to_comp' => $ship_to_company,
                    ':to_addr1' => $ship_to_address1,
                    ':to_addr2' => $ship_to_address2 ?: null,
                    ':to_city' => $ship_to_city,
                    ':to_state' => $ship_to_state,
                    ':to_zip' => $ship_to_zip,
                    ':to_phone' => $ship_to_phone,
                    ':to_email' => $ship_to_email ?: null,
                    ':so_num' => $sales_order_number,
                    ':req_ref' => $request_reference ?: null,
                    ':len' => $length,
                    ':wid' => $width,
                    ':hgt' => $height,
                    ':wgt' => $weight_lbs,
                    ':method' => $selectedCourierName,
                    ':freight' => $customer_freight_charge,
                    ':instructions' => $special_instructions ?: null
                ]);

                $requestId = $pdo->lastInsertId();

                // Save Ship From address if checkbox is checked
                $save_ship_from = isset($_POST['save_ship_from']) && $_POST['save_ship_from'] === '1';
                if ($save_ship_from) {
                    $stmtCheckFrom = $pdo->prepare("SELECT id FROM saved_addresses WHERE store_id = ? AND address_type = 'from' AND name = ? AND company = ? AND address1 = ? AND address2 = ? AND city = ? AND state = ? AND zip = ? AND phone = ? AND email = ?");
                    $stmtCheckFrom->execute([$storeId, $ship_from_name, $ship_from_company, $ship_from_address1, $ship_from_address2 ?: '', $ship_from_city, $ship_from_state, $ship_from_zip, $ship_from_phone, $ship_from_email ?: '']);
                    if (!$stmtCheckFrom->fetch()) {
                        $stmtInsertFrom = $pdo->prepare("INSERT INTO saved_addresses (store_id, address_type, name, company, address1, address2, city, state, zip, phone, email) VALUES (?, 'from', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtInsertFrom->execute([$storeId, $ship_from_name, $ship_from_company, $ship_from_address1, $ship_from_address2 ?: null, $ship_from_city, $ship_from_state, $ship_from_zip, $ship_from_phone, $ship_from_email ?: null]);
                    }
                }

                // Save Ship To address if checkbox is checked
                $save_ship_to = isset($_POST['save_ship_to']) && $_POST['save_ship_to'] === '1';
                if ($save_ship_to) {
                    $stmtCheckTo = $pdo->prepare("SELECT id FROM saved_addresses WHERE store_id = ? AND address_type = 'to' AND name = ? AND company = ? AND address1 = ? AND address2 = ? AND city = ? AND state = ? AND zip = ? AND phone = ? AND email = ?");
                    $stmtCheckTo->execute([$storeId, $ship_to_name, $ship_to_company, $ship_to_address1, $ship_to_address2 ?: '', $ship_to_city, $ship_to_state, $ship_to_zip, $ship_to_phone, $ship_to_email ?: '']);
                    if (!$stmtCheckTo->fetch()) {
                        $stmtInsertTo = $pdo->prepare("INSERT INTO saved_addresses (store_id, address_type, name, company, address1, address2, city, state, zip, phone, email) VALUES (?, 'to', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtInsertTo->execute([$storeId, $ship_to_name, $ship_to_company, $ship_to_address1, $ship_to_address2 ?: null, $ship_to_city, $ship_to_state, $ship_to_zip, $ship_to_phone, $ship_to_email ?: null]);
                    }
                }

                // CALL EASYSHIP SERVICE DIRECTLY
                require_once dirname(__DIR__) . '/src/EasyshipService.php';
                
                // Construct temporary request dictionary mock for the API creator
                $reqMock = [
                    'id' => $requestId,
                    'request_number' => $request_number,
                    'ship_from_name' => $ship_from_name,
                    'ship_from_company' => $ship_from_company,
                    'ship_from_address1' => $ship_from_address1,
                    'ship_from_address2' => $ship_from_address2,
                    'ship_from_city' => $ship_from_city,
                    'ship_from_state' => $ship_from_state,
                    'ship_from_zip' => $ship_from_zip,
                    'ship_from_phone' => $ship_from_phone,
                    'ship_from_email' => $ship_from_email,
                    'ship_to_name' => $ship_to_name,
                    'ship_to_company' => $ship_to_company,
                    'ship_to_address1' => $ship_to_address1,
                    'ship_to_address2' => $ship_to_address2,
                    'ship_to_city' => $ship_to_city,
                    'ship_to_state' => $ship_to_state,
                    'ship_to_zip' => $ship_to_zip,
                    'ship_to_phone' => $ship_to_phone,
                    'ship_to_email' => $ship_to_email,
                    'sales_order_number' => $sales_order_number,
                    'length' => $length,
                    'width' => $width,
                    'height' => $height,
                    'weight_lbs' => $weight_lbs
                ];

                $res = EasyshipService::createLabel($reqMock, $selectedCourierId, $selectedCourierName, $selectedCourierCost, $selectedEnv);
                
                if (!$res['success']) {
                    throw new Exception("Easyship Label Creation Failed: " . $res['error']);
                }

                // Insert into request_labels
                $stmtLabel = $pdo->prepare("INSERT INTO request_labels (request_id, label_file, tracking_number, carrier, estimated_delivery_date, actual_shipping_cost, easyship_shipment_id, tracking_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Label Ready')");
                $stmtLabel->execute([
                    $requestId,
                    $res['label_file'],
                    $res['tracking_number'],
                    $res['carrier'],
                    $res['estimated_delivery_date'],
                    $res['actual_shipping_cost'],
                    $res['easyship_shipment_id']
                ]);
                $insertedLabelId = $pdo->lastInsertId();

                // Update shipping method and status with actual carrier returned (vendor labels are immediately completed)
                $stmtUpdateMethod = $pdo->prepare("UPDATE label_requests SET status = 'Completed', shipping_method = ? WHERE id = ?");
                $stmtUpdateMethod->execute([$res['carrier'], $requestId]);

                // Fetch store details to get notification emails
                $stmtStoreInfo = $pdo->prepare("SELECT store_name, notification_emails FROM stores WHERE id = ?");
                $stmtStoreInfo->execute([$storeId]);
                $storeInfo = $stmtStoreInfo->fetch();
                $storeEmail = $storeInfo ? $storeInfo['notification_emails'] : '';

                // Also find destination store if it's different and notify its users
                $destStore = find_store_by_address($ship_to_zip, $ship_to_address1, $ship_to_company);

                // Create in-app notification for the store user
                // Find all users belonging to this store
                $stmtStoreUsers = $pdo->prepare("SELECT id FROM users WHERE store_id = ?");
                $stmtStoreUsers->execute([$storeId]);
                $storeUsers = $stmtStoreUsers->fetchAll();
                
                $destStoreUsers = [];
                if ($destStore && $destStore['id'] != $storeId) {
                    $stmtDestStoreUsers = $pdo->prepare("SELECT id FROM users WHERE store_id = ?");
                    $stmtDestStoreUsers->execute([$destStore['id']]);
                    $destStoreUsers = $stmtDestStoreUsers->fetchAll();
                }
                
                $trackingStr = $res['tracking_number'] ? $res['tracking_number'] . " (" . ($res['carrier'] ?? 'N/A') . ")" : 'Not Provided';
                
                $notifMsg = "A new vendor shipping label has been created for your store. SO#: " . $sales_order_number . ". Tracking: " . $trackingStr;
                foreach ($storeUsers as $su) {
                    add_notification($su['id'], "Vendor shipping label created - " . $request_number, $notifMsg);
                }
                foreach ($destStoreUsers as $su) {
                    add_notification($su['id'], "Incoming vendor shipping label created - " . $request_number, $notifMsg);
                }



                // Log Activity
                log_activity($userId, "Created Vendor label request directly $request_number (Tracking: " . $res['tracking_number'] . ")", $requestId);

                // Redirect to success
                header("Location: admin_label_create.php?success=1&req_num=" . urlencode($request_number) . "&label_id=" . $insertedLabelId);
                exit;

            } catch (Exception $e) {
                $error = "Error executing request: " . $e->getMessage();
            }
        }
    }
}

require_once dirname(__DIR__) . '/src/header.php';
?>

<style>
/* Premium Rate Selection Cards */
.rate-card {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    position: relative;
    user-select: none;
    border-left: 5px solid var(--border);
}
.rate-card:hover {
    border-color: #93C5FD;
    background: #f8fafc;
    transform: translateY(-1px);
}
.rate-card.selected {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 4px 12px rgba(30, 90, 168, 0.08);
}
.rate-card-left {
    display: flex;
    align-items: center;
    gap: 15px;
}
.rate-card-radio {
    transform: scale(1.15);
    cursor: pointer;
}
.rate-carrier-icon {
    font-size: 0.75rem;
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    letter-spacing: -0.5px;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.carrier-ups {
    border-left: 5px solid #3c2415 !important;
}
.carrier-ups-icon {
    background: #3c2415;
    color: #ffb500;
}
.carrier-fedex {
    border-left: 5px solid #4d148c !important;
}
.carrier-fedex-icon {
    background: #4d148c;
    color: #ffffff;
}
.carrier-usps {
    border-left: 5px solid #002f6c !important;
}
.carrier-usps-icon {
    background: #002f6c;
    color: #ffffff;
}
.carrier-other {
    border-left: 5px solid var(--primary) !important;
}
.carrier-other-icon {
    background: var(--primary);
    color: #ffffff;
}
.rate-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.rate-name {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 0.95rem;
}
.rate-time {
    font-size: 0.8rem;
    color: var(--text-muted);
}
.rate-card-right {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}
.rate-price {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--success);
    font-family: 'Outfit', sans-serif;
}
.badge-rate {
    font-size: 0.65rem;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-cheapest {
    background-color: #dcfce7;
    color: #15803d;
}
.badge-fastest {
    background-color: #dbeafe;
    color: #1d4ed8;
}
.badge-best-value {
    background-color: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        
        <?php if ($success): ?>
            <div class="card p-5 text-center my-4" style="border-radius: 12px;">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill" style="font-size: 5rem; color: var(--success);"></i>
                </div>
                <h1 class="brand-font mb-2" style="color: var(--text-primary); font-size: 1.6rem;">Vendor Shipping Label Created!</h1>
                <p style="color: var(--text-secondary); font-size: 1rem;">The label has been successfully generated and is downloading automatically.</p>
                
                <?php if (isset($_GET['label_id'])): ?>
                    <p class="mb-3" style="font-size: 0.95rem;">
                        <a href="download_label.php?id=<?php echo intval($_GET['label_id']); ?>" class="btn btn-sm btn-outline-primary fw-bold px-3">
                            <i class="bi bi-download me-1"></i> Click here if download did not start
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ($labelDetails): ?>
                    <div class="card shadow-sm border border-light mx-auto mb-4 p-4 text-start" style="max-width: 500px; border-radius: 10px; background: #ffffff;">
                        <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="bi bi-info-circle-fill text-primary me-2"></i>Shipment & Store Details</h5>
                        
                        <div class="row g-2 small mb-2">
                            <div class="col-5 text-muted fw-bold">Associated Store:</div>
                            <div class="col-7 text-dark fw-bold">
                                <?php if ($storeDetails): ?>
                                    <?php echo e($storeDetails['store_name']); ?> (Code: <?php echo e($storeDetails['store_code']); ?>)
                                <?php else: ?>
                                    Unknown Store (ID: <?php echo e($labelDetails['store_id']); ?>)
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($destStoreDetails): ?>
                            <div class="row g-2 small mb-2">
                                <div class="col-5 text-muted fw-bold">Destination Store:</div>
                                <div class="col-7 text-dark fw-bold">
                                    <span class="badge bg-info text-dark fw-semibold" style="font-size: 0.78rem;">
                                        <i class="bi bi-shop me-1"></i><?php echo e($destStoreDetails['store_name']); ?> (Code: <?php echo e($destStoreDetails['store_code']); ?>)
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-2 small mb-2">
                            <div class="col-5 text-muted fw-bold">Sales Order #:</div>
                            <div class="col-7 text-dark"><?php echo e($labelDetails['sales_order_number']); ?></div>
                        </div>

                        <div class="row g-2 small mb-2">
                            <div class="col-5 text-muted fw-bold">Tracking Number:</div>
                            <div class="col-7">
                                <?php if ($labelDetails['tracking_number'] && isset($_GET['label_id'])): ?>
                                    <a href="download_label.php?id=<?php echo intval($_GET['label_id']); ?>" class="text-danger fw-bold text-decoration-none d-inline-flex align-items-center gap-1" title="Click to download PDF label">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-6"></i> 
                                        <u><?php echo e($labelDetails['tracking_number']); ?></u>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not Available</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-2 small">
                            <div class="col-5 text-muted fw-bold">Carrier / Method:</div>
                            <div class="col-7 text-secondary fw-semibold"><?php echo e($labelDetails['carrier'] ?: $labelDetails['shipping_method']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-inline-block mx-auto mb-4 p-3 rounded-3" style="min-width: 280px; background: var(--primary-light); border: 1px solid #93C5FD;">
                    <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: var(--text-muted);">Shipment Number</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary); font-family: 'Outfit', sans-serif;"><?php echo e($generatedReqNumber); ?></div>
                </div>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="admin_dashboard.php" class="btn-command-secondary px-4">Back to Dashboard</a>
                    <a href="admin_label_create.php" class="btn-command-primary px-4">Create Another Label</a>
                </div>
            </div>
        <?php else: ?>

            <div class="card mb-5" style="border-radius: 12px; overflow: hidden;">
                <!-- Card Header -->
                <div class="card-header-primary py-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle" style="font-size: 1.1rem;"></i>
                        <h3 class="mb-0 fw-bold" style="font-size: 1.05rem; color: #fff; font-family: 'Outfit', sans-serif;">Create Vendor Label (Direct Creation)</h3>
                    </div>
                    <a href="admin_dashboard.php" class="btn-command-secondary" style="padding: 5px 14px; font-size: 0.82rem; background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); color: #fff !important;">
                        <i class="bi bi-arrow-left"></i> Back to Hub
                    </a>
                </div>

                <div class="card-body p-4">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert-command-danger mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Error:</strong> <?php echo e($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="admin_label_create.php" method="POST" id="label-create-form">
                        <?php csrf_input(); ?>

                        <!-- Context & Environment -->
                        <div class="row mb-4 g-3">
                            <div class="col-md-6">
                                <label class="form-label-command">Associate with Store Location <span class="text-danger">*</span></label>
                                <select name="store_id" id="store_id" class="form-control-command" required>
                                    <?php foreach ($allStores as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $defaultStoreId ? 'selected' : ''; ?>>
                                            <?php echo e($s['store_code']); ?> — <?php echo e($s['store_name']); ?> (<?php echo e($s['city']); ?>, <?php echo e($s['state']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">API Environment Mode <span class="text-danger">*</span></label>
                                <select name="selected_env" id="selected_env" class="form-control-command">
                                    <option value="sandbox" <?php echo (defined('EASYSHIP_ENV') && EASYSHIP_ENV === 'sandbox') ? 'selected' : ''; ?>>Sandbox Simulator Mode</option>
                                    <option value="production" <?php echo (defined('EASYSHIP_ENV') && EASYSHIP_ENV === 'production') ? 'selected' : ''; ?>>Production Live Mode</option>
                                </select>
                            </div>
                        </div>

                        <!-- ==========================================
                             SECTION 1: SHIP FROM
                        =========================================== -->
                        <div class="mb-4 pb-3" style="border-bottom: 2px solid var(--primary-light);">
                            <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                <i class="bi bi-house-door-fill me-2"></i>1. Ship From Address (Vendor)
                            </h5>
                        </div>

                        <!-- Saved Ship From Address Selector -->
                        <div class="mb-4 p-3 rounded-3" style="background: var(--primary-light); border: 1px solid #93C5FD;">
                            <label class="form-label-command" style="color: var(--primary);">
                                <i class="bi bi-bookmark-fill me-1"></i>Load Saved Address
                            </label>
                            <select id="ship_from_address_selector" class="form-control-command">
                                <option value="">— Select a saved address to load —</option>
                                <optgroup label="Default Store Addresses">
                                    <?php foreach ($allStores as $s): ?>
                                        <option value="store_<?php echo $s['id']; ?>" 
                                                data-name="Store Staff"
                                                data-company="<?php echo e($s['store_name']); ?>"
                                                data-address1="<?php echo e($s['address']); ?>"
                                                data-address2=""
                                                data-city="<?php echo e($s['city']); ?>"
                                                data-state="<?php echo e($s['state']); ?>"
                                                data-zip="<?php echo e($s['zip']); ?>"
                                                data-phone="<?php echo e($s['phone']); ?>"
                                                data-email="<?php echo e($s['notification_emails']); ?>">
                                            <?php echo e($s['store_code']); ?> — <?php echo e($s['store_name']); ?> (<?php echo e($s['city']); ?>, <?php echo e($s['state']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php if (!empty($savedFromAddresses)): ?>
                                    <optgroup label="Custom Saved From Addresses">
                                        <?php foreach ($savedFromAddresses as $sa): ?>
                                            <option value="saved_<?php echo $sa['id']; ?>" 
                                                    data-store-id="<?php echo $sa['store_id']; ?>"
                                                    data-name="<?php echo e($sa['name']); ?>"
                                                    data-company="<?php echo e($sa['company']); ?>"
                                                    data-address1="<?php echo e($sa['address1']); ?>"
                                                    data-address2="<?php echo e($sa['address2']); ?>"
                                                    data-city="<?php echo e($sa['city']); ?>"
                                                    data-state="<?php echo e($sa['state']); ?>"
                                                    data-zip="<?php echo e($sa['zip']); ?>"
                                                    data-phone="<?php echo e($sa['phone']); ?>"
                                                    data-email="<?php echo e($sa['email']); ?>">
                                                <?php echo e($sa['company']); ?> — <?php echo e($sa['name']); ?> (<?php echo e($sa['city']); ?>, <?php echo e($sa['state']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="row mb-4 g-3">
                            <div class="col-md-6">
                                <label class="form-label-command">Name <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_name" id="ship_from_name" class="form-control-command" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_company" id="ship_from_company" class="form-control-command" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_address1" id="ship_from_address1" class="form-control-command" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 2</label>
                                <input type="text" name="ship_from_address2" id="ship_from_address2" class="form-control-command">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">City <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_city" id="ship_from_city" class="form-control-command" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">State <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_state" id="ship_from_state" class="form-control-command" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">ZIP <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_zip" id="ship_from_zip" class="form-control-command" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Phone <span class="text-danger">*</span></label>
                                <input type="tel" name="ship_from_phone" id="ship_from_phone" class="form-control-command" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Email</label>
                                <input type="email" name="ship_from_email" id="ship_from_email" class="form-control-command">
                            </div>
                            <div class="col-12">
                                <div class="form-check p-3 rounded" style="background: var(--accent-gold-light); border: 1px solid #fde68a;">
                                    <input class="form-check-input" type="checkbox" name="save_ship_from" id="save_ship_from" value="1">
                                    <label class="form-check-label fw-semibold" for="save_ship_from" style="color: var(--accent-gold-dark); cursor: pointer;">
                                        <i class="bi bi-bookmark-plus me-1"></i> Save this address for future use (associated with the selected store context)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- ==========================================
                             SECTION 2: SHIP TO
                        =========================================== -->
                        <div class="mb-4 pb-3" style="border-bottom: 2px solid var(--primary-light);">
                            <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                <i class="bi bi-geo-alt-fill me-2"></i>2. Ship To Address (Store or Customer)
                            </h5>
                        </div>

                        <!-- Ship To Saved Address Selector -->
                        <div class="mb-4 p-3 rounded-3" style="background: var(--primary-light); border: 1px solid #93C5FD;">
                            <label class="form-label-command" style="color: var(--primary);">
                                <i class="bi bi-bookmark-fill me-1"></i>Load Saved Address
                            </label>
                            <select id="ship_to_address_selector" class="form-control-command">
                                <option value="">— Select a saved address to load —</option>
                                <optgroup label="Default Store Addresses">
                                    <?php foreach ($allStores as $s): ?>
                                        <option value="store_<?php echo $s['id']; ?>" 
                                                data-name="Store Staff"
                                                data-company="<?php echo e($s['store_name']); ?>"
                                                data-address1="<?php echo e($s['address']); ?>"
                                                data-address2=""
                                                data-city="<?php echo e($s['city']); ?>"
                                                data-state="<?php echo e($s['state']); ?>"
                                                data-zip="<?php echo e($s['zip']); ?>"
                                                data-phone="<?php echo e($s['phone']); ?>"
                                                data-email="<?php echo e($s['notification_emails']); ?>">
                                            <?php echo e($s['store_code']); ?> — <?php echo e($s['store_name']); ?> (<?php echo e($s['city']); ?>, <?php echo e($s['state']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php if (!empty($savedToAddresses)): ?>
                                    <optgroup label="Custom Saved To Addresses">
                                        <?php foreach ($savedToAddresses as $sa): ?>
                                            <option value="saved_<?php echo $sa['id']; ?>" 
                                                    data-store-id="<?php echo $sa['store_id']; ?>"
                                                    data-name="<?php echo e($sa['name']); ?>"
                                                    data-company="<?php echo e($sa['company']); ?>"
                                                    data-address1="<?php echo e($sa['address1']); ?>"
                                                    data-address2="<?php echo e($sa['address2']); ?>"
                                                    data-city="<?php echo e($sa['city']); ?>"
                                                    data-state="<?php echo e($sa['state']); ?>"
                                                    data-zip="<?php echo e($sa['zip']); ?>"
                                                    data-phone="<?php echo e($sa['phone']); ?>"
                                                    data-email="<?php echo e($sa['email']); ?>">
                                                <?php echo e($sa['company']); ?> — <?php echo e($sa['name']); ?> (<?php echo e($sa['city']); ?>, <?php echo e($sa['state']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="row mb-4 g-3">
                            <div class="col-md-6">
                                <label class="form-label-command">Name <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_name" id="ship_to_name" class="form-control-command" placeholder="Recipient's Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_company" id="ship_to_company" class="form-control-command" placeholder="Destination Company Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_address1" id="ship_to_address1" class="form-control-command" placeholder="Street Address" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 2</label>
                                <input type="text" name="ship_to_address2" id="ship_to_address2" class="form-control-command" placeholder="Suite, Apt...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">City <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_city" id="ship_to_city" class="form-control-command" placeholder="City" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">State <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_state" id="ship_to_state" class="form-control-command" placeholder="State" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">ZIP <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_zip" id="ship_to_zip" class="form-control-command" placeholder="ZIP" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Phone <span class="text-danger">*</span></label>
                                <input type="tel" name="ship_to_phone" id="ship_to_phone" class="form-control-command" placeholder="Phone" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Email <span class="text-danger">*</span></label>
                                <input type="email" name="ship_to_email" id="ship_to_email" class="form-control-command" placeholder="Email" required>
                            </div>
                            <div class="col-12">
                                <div class="form-check p-3 rounded" style="background: var(--accent-gold-light); border: 1px solid #fde68a;">
                                    <input class="form-check-input" type="checkbox" name="save_ship_to" id="save_ship_to" value="1">
                                    <label class="form-check-label fw-semibold" for="save_ship_to" style="color: var(--accent-gold-dark); cursor: pointer;">
                                        <i class="bi bi-bookmark-plus me-1"></i> Save this address for future use (associated with the selected store context)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- ==========================================
                             SECTION 3 + 4: ORDER & PACKAGE DETAILS
                        =========================================== -->
                        <div class="row mb-4 g-4">
                            <!-- Order Details -->
                            <div class="col-md-6">
                                <div class="mb-3 pb-2" style="border-bottom: 2px solid var(--primary-light);">
                                    <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                        <i class="bi bi-receipt me-2"></i>3. Order Details
                                    </h5>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label-command">Sales/Sales Order/Purchase Order <span class="text-danger">*</span></label>
                                        <input type="text" name="sales_order_number" class="form-control-command" placeholder="e.g. SO-100234" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-command">Request Reference (REQ#)</label>
                                        <input type="text" name="request_reference" class="form-control-command" placeholder="e.g. REQ-987">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-command">Freight Charged by Store ($) <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <span style="background: var(--bg); border: 1px solid var(--border); padding: 9px 12px; border-radius: 8px; font-size: 0.9rem; font-weight: 700; color: var(--text-secondary); flex-shrink: 0;">$</span>
                                            <input type="number" step="0.01" name="customer_freight_charge" id="customer_freight_charge" class="form-control-command" placeholder="0.00" required value="<?php echo number_format($minFreightCharge, 2); ?>" style="flex:1;">
                                        </div>
                                        <div class="form-text">Amount the store charged the customer for this shipment.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Package Details -->
                            <div class="col-md-6">
                                <div class="mb-3 pb-2" style="border-bottom: 2px solid var(--primary-light);">
                                    <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                        <i class="bi bi-box-seam-fill me-2"></i>4. Package Details
                                    </h5>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label-command">Dimensions (L × W × H) <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="number" step="0.1" name="length" id="length" class="form-control-command text-center" placeholder="L" required min="0.1" style="flex:1;">
                                            <span style="color: var(--text-muted); font-weight: 700; flex-shrink: 0;">×</span>
                                            <input type="number" step="0.1" name="width" id="width" class="form-control-command text-center" placeholder="W" required min="0.1" style="flex:1;">
                                            <span style="color: var(--text-muted); font-weight: 700; flex-shrink: 0;">×</span>
                                            <input type="number" step="0.1" name="height" id="height" class="form-control-command text-center" placeholder="H" required min="0.1" style="flex:1;">
                                            <span style="background: var(--bg); border: 1px solid var(--border); padding: 9px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); flex-shrink: 0;">IN</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-command">Weight <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="number" step="0.01" name="weight_lbs" id="weight_lbs" class="form-control-command" placeholder="Total weight" required min="0.01" style="flex:1;">
                                            <span style="background: var(--bg); border: 1px solid var(--border); padding: 9px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); flex-shrink: 0;">LBS</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==========================================
                             SECTION 5: LIVE RATES RETRIEVAL
                        =========================================== -->
                        <div class="mb-4 pb-3" style="border-bottom: 2px solid var(--primary-light);">
                            <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                <i class="bi bi-truck me-2"></i>5. Live Rates &amp; Carrier Selection
                            </h5>
                        </div>

                        <div class="mb-4">
                            <button type="button" id="btn-fetch-rates" class="btn-command-secondary py-2 px-4">
                                <i class="bi bi-arrow-repeat me-1"></i> Retrieve Live Rates
                            </button>
                            <span id="rates-loading-spinner" class="ms-3" style="display:none;">
                                <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                                <span class="text-muted small ms-1">Fetching live rates from Easyship...</span>
                            </span>
                        </div>

                        <!-- Live Rates Result Container -->
                        <div class="mb-4 p-4 rounded-3" id="rates-result-container" style="display:none; background: #ffffff; border: 1px solid var(--border);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-ui-checks-grid text-primary" style="font-size: 1.15rem;"></i>
                                <div class="fw-bold text-dark" style="font-size: 0.95rem; font-family: 'Outfit', sans-serif;">Select Shipping Solution</div>
                            </div>
                            <div id="rates-list-container" class="d-flex flex-column gap-2">
                                <!-- Dynamically generated rate cards -->
                            </div>
                        </div>

                        <!-- Hidden fields to store selected courier details -->
                        <input type="hidden" name="selected_courier_id" id="selected_courier_id" required>
                        <input type="hidden" name="selected_courier_name" id="selected_courier_name" required>
                        <input type="hidden" name="selected_courier_cost" id="selected_courier_cost" required>

                        <div class="row mb-4 g-3" id="freight-charge-section" style="display:none;">
                            <div class="col-12">
                                <label class="form-label-command">Special Instructions</label>
                                <textarea name="special_instructions" class="form-control-command" rows="3" placeholder="Enter any special instructions..."></textarea>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid mt-4">
                            <button type="submit" id="submit-create-btn" class="btn-command-primary py-3 justify-content-center" style="font-size: 1rem; letter-spacing: 0.5px; border-radius: 10px;" disabled>
                                <i class="bi bi-printer-fill me-2"></i> CREATE &amp; PRINT VENDOR LABEL
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php
// Auto download logic
if (isset($_GET['label_id'])): 
    $dlId = intval($_GET['label_id']);
?>
<script>
$(document).ready(function() {
    window.location.href = 'download_label.php?id=<?php echo $dlId; ?>';
});
</script>
<?php endif; ?>

<!-- Client-side Interactive Logic -->
<script>
$(document).ready(function() {
    let freightChargeManuallyEdited = false;
    $('#customer_freight_charge').on('input', function() {
        freightChargeManuallyEdited = true;
    });

    // Address load handlers
    $('#ship_from_address_selector').on('change', function() {
        const option = $(this).find('option:selected');
        const val = $(this).val();
        if (val !== '') {
            $('#ship_from_name').val(option.data('name') || 'Store Staff');
            $('#ship_from_company').val(option.data('company') || '');
            $('#ship_from_address1').val(option.data('address1') || '');
            $('#ship_from_address2').val(option.data('address2') || '');
            $('#ship_from_city').val(option.data('city') || '');
            $('#ship_from_state').val(option.data('state') || '');
            $('#ship_from_zip').val(option.data('zip') || '');
            $('#ship_from_phone').val(option.data('phone') || '');
            $('#ship_from_email').val(option.data('email') || '');
            
            const storeIdVal = option.data('store-id');
            if (storeIdVal) {
                $('#store_id').val(storeIdVal);
            } else if (val.indexOf('store_') === 0) {
                const storeIdValDirect = val.replace('store_', '');
                $('#store_id').val(storeIdValDirect);
            }
        }
    });

    $('#ship_to_address_selector').on('change', function() {
        const option = $(this).find('option:selected');
        const val = $(this).val();
        if (val !== '') {
            $('#ship_to_name').val(option.data('name') || 'Store Staff');
            $('#ship_to_company').val(option.data('company') || '');
            $('#ship_to_address1').val(option.data('address1') || '');
            $('#ship_to_address2').val(option.data('address2') || '');
            $('#ship_to_city').val(option.data('city') || '');
            $('#ship_to_state').val(option.data('state') || '');
            $('#ship_to_zip').val(option.data('zip') || '');
            $('#ship_to_phone').val(option.data('phone') || '');
            $('#ship_to_email').val(option.data('email') || '');
            
            const storeIdVal = option.data('store-id');
            if (storeIdVal) {
                $('#store_id').val(storeIdVal);
            } else if (val.indexOf('store_') === 0) {
                const storeIdValDirect = val.replace('store_', '');
                $('#store_id').val(storeIdValDirect);
            }
        }
    });

    // Rates retrieval handler
    $('#btn-fetch-rates').on('click', function(e) {
        e.preventDefault();
        
        // Reset selections
        $('#rates-result-container').hide();
        $('#rates-list-container').empty();
        $('#selected_courier_id').val('');
        $('#selected_courier_name').val('');
        $('#selected_courier_cost').val('');
        $('#submit-create-btn').prop('disabled', true);
        $('#freight-charge-section').hide();

        // Check required fields
        let missing = false;
        const requiredFields = [
            '#ship_from_address1', '#ship_from_city', '#ship_from_state', '#ship_from_zip',
            '#ship_to_address1', '#ship_to_city', '#ship_to_state', '#ship_to_zip',
            '#length', '#width', '#height', '#weight_lbs'
        ];

        requiredFields.forEach(function(field) {
            if ($(field).val().trim() === '') {
                $(field).addClass('is-invalid');
                missing = true;
            } else {
                $(field).removeClass('is-invalid');
            }
        });

        if (missing) {
            alert("Please fill in the origin/destination address and package dimensions to fetch live rates.");
            return;
        }

        $('#rates-loading-spinner').show();
        $(this).prop('disabled', true);

        // Gather data
        const data = {
            ship_from_name: $('#ship_from_name').val(),
            ship_from_company: $('#ship_from_company').val(),
            ship_from_address1: $('#ship_from_address1').val(),
            ship_from_address2: $('#ship_from_address2').val(),
            ship_from_city: $('#ship_from_city').val(),
            ship_from_state: $('#ship_from_state').val(),
            ship_from_zip: $('#ship_from_zip').val(),
            ship_from_phone: $('#ship_from_phone').val(),
            
            ship_to_name: $('#ship_to_name').val(),
            ship_to_company: $('#ship_to_company').val(),
            ship_to_address1: $('#ship_to_address1').val(),
            ship_to_address2: $('#ship_to_address2').val(),
            ship_to_city: $('#ship_to_city').val(),
            ship_to_state: $('#ship_to_state').val(),
            ship_to_zip: $('#ship_to_zip').val(),
            ship_to_phone: $('#ship_to_phone').val(),
            
            length: $('#length').val(),
            width: $('#width').val(),
            height: $('#height').val(),
            weight_lbs: $('#weight_lbs').val(),
            env: $('#selected_env').val()
        };

        $.ajax({
            url: 'ajax_get_live_rates.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                $('#rates-loading-spinner').hide();
                $('#btn-fetch-rates').prop('disabled', false);

                if (response.success && response.rates && response.rates.length > 0) {
                    $('#rates-result-container').show();
                    
                    // Determine fastest rate
                    let minDays = 999;
                    let fastestIdx = -1;
                    response.rates.forEach(function(rate, idx) {
                        let days = 999;
                        let match = rate.delivery_time.match(/(\d+)/);
                        if (match) {
                            days = parseInt(match[1]);
                        }
                        if (days < minDays) {
                            minDays = days;
                            fastestIdx = idx;
                        }
                    });

                    response.rates.forEach(function(rate, idx) {
                        // Classify carrier
                        let nameLower = rate.courier_name.toLowerCase();
                        let carrierClass = 'carrier-other';
                        let iconClass = 'carrier-other-icon';
                        let carrierLabel = 'SHIP';
                        
                        if (nameLower.indexOf('ups') !== -1) {
                            carrierClass = 'carrier-ups';
                            iconClass = 'carrier-ups-icon';
                            carrierLabel = 'UPS';
                        } else if (nameLower.indexOf('fedex') !== -1) {
                            carrierClass = 'carrier-fedex';
                            iconClass = 'carrier-fedex-icon';
                            carrierLabel = 'FedEx';
                        } else if (nameLower.indexOf('usps') !== -1) {
                            carrierClass = 'carrier-usps';
                            iconClass = 'carrier-usps-icon';
                            carrierLabel = 'USPS';
                        }

                        // Determine badges
                        let badges = '';
                        if (idx === 0 && idx === fastestIdx) {
                            badges = '<span class="badge-rate badge-best-value"><i class="bi bi-star-fill me-1"></i>Best Value &amp; Fastest</span>';
                        } else if (idx === 0) {
                            badges = '<span class="badge-rate badge-cheapest"><i class="bi bi-tag-fill me-1"></i>Cheapest</span>';
                        } else if (idx === fastestIdx) {
                            badges = '<span class="badge-rate badge-fastest"><i class="bi bi-lightning-fill me-1"></i>Fastest</span>';
                        }

                        let charge = parseFloat(rate.shipment_charge) || 0;

                        const cardHtml = `
                            <div class="rate-card ${carrierClass}" 
                                 data-id="${rate.courier_id}" 
                                 data-name="${rate.courier_name}" 
                                 data-cost="${charge}">
                                <div class="rate-card-left">
                                    <input class="form-check-input rate-card-radio select-courier-radio" type="radio" name="courier_radio" 
                                           id="radio_${rate.courier_id}_${idx}"
                                           value="${rate.courier_id}">
                                    <div class="rate-carrier-icon ${iconClass}">
                                        ${carrierLabel}
                                    </div>
                                    <div class="rate-details">
                                        <div class="rate-name">${rate.courier_name}</div>
                                        <div class="rate-time"><i class="bi bi-calendar3 me-1"></i>Est. Transit: ${rate.delivery_time}</div>
                                    </div>
                                </div>
                                <div class="rate-card-right">
                                    <div class="rate-price">$${charge.toFixed(2)}</div>
                                    ${badges}
                                </div>
                            </div>
                        `;
                        $('#rates-list-container').append(cardHtml);
                    });

                } else {
                    alert("Could not load shipping rates. Error: " + (response.error || 'No matching rates returned from courier.'));
                }
            },
            error: function(xhr) {
                $('#rates-loading-spinner').hide();
                $('#btn-fetch-rates').prop('disabled', false);
                alert("An error occurred while calling the rates endpoint. Please check address details.");
            }
        });
    });

    // Delegated click handler on the cards themselves
    $(document).on('click', '.rate-card', function(e) {
        if (!$(e.target).hasClass('rate-card-radio')) {
            $(this).find('.rate-card-radio').prop('checked', true).trigger('change');
        }
    });

    // Delegated radio change listener
    $(document).on('change', '.rate-card-radio', function() {
        $('.rate-card').removeClass('selected');
        const parentCard = $(this).closest('.rate-card');
        parentCard.addClass('selected');

        const selectedId = parentCard.data('id');
        const selectedName = parentCard.data('name');
        const selectedCost = parseFloat(parentCard.data('cost'));

        $('#selected_courier_id').val(selectedId);
        $('#selected_courier_name').val(selectedName);
        $('#selected_courier_cost').val(selectedCost);
        
        if (!freightChargeManuallyEdited) {
            $('#customer_freight_charge').val(selectedCost.toFixed(2));
        }
        $('#freight-charge-section').show();
        $('#submit-create-btn').prop('disabled', false);
    });

    // Form submit loading visual
    $('#label-create-form').on('submit', function() {
        $('#submit-create-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating & Printing Label...');
    });

});
</script>

<?php require_once dirname(__DIR__) . '/src/footer.php'; ?>
