<?php
$pageTitle = "Create Label Request";
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();

$userId    = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$role      = $_SESSION['role'];
$storeId   = $_SESSION['store_id'];
$storeName = $_SESSION['store_name'];

require_role('Store User');

// Fetch default store details, current user profile, and all stores for dropdown populate
try {
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
    if (!$store) {
        throw new Exception("Store details not found.");
    }

    $stmtUser = $pdo->prepare("SELECT email, phone, name FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $currentUser = $stmtUser->fetch();

    $stmtAll = $pdo->query("SELECT * FROM stores ORDER BY store_code ASC");
    $allStores = $stmtAll->fetchAll();

    // Fetch custom saved addresses
    $stmtSavedFrom = $pdo->prepare("SELECT * FROM saved_addresses WHERE store_id = ? AND address_type = 'from' ORDER BY company ASC, name ASC");
    $stmtSavedFrom->execute([$storeId]);
    $savedFromAddresses = $stmtSavedFrom->fetchAll();

    $stmtSavedTo = $pdo->prepare("SELECT * FROM saved_addresses WHERE store_id = ? AND address_type = 'to' ORDER BY company ASC, name ASC");
    $stmtSavedTo->execute([$storeId]);
    $savedToAddresses = $stmtSavedTo->fetchAll();
} catch (Exception $e) {
    die("Error retrieving store configurations: " . $e->getMessage());
}

// Get dynamic minimum freight charge
$minFreightCharge = get_minimum_freight_charge();

$error = '';
$success = false;
$generatedReqNumber = '';

// Check if redirected after success to prevent duplicate submissions on refresh
if (isset($_GET['success']) && isset($_GET['req_num'])) {
    $success = true;
    $generatedReqNumber = trim($_GET['req_num']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF
    if (!validate_csrf_token($csrf_token)) {
        $error = "Security validation failed (CSRF token invalid). Please try again.";
    } else {
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

        // Shipping and freight details
        $shipping_method = trim($_POST['shipping_method'] ?? '');
        $freight_charge_raw = trim($_POST['customer_freight_charge'] ?? '');
        $customer_freight_charge = floatval($freight_charge_raw);
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
        } elseif (empty($shipping_method)) {
            $error = "Please select a shipping method.";
        } 
        // Freight Validation Rule
        elseif ($freight_charge_raw === '') {
            $error = "Freight charge is required. Please enter the amount charged to the customer.";
        } elseif ($customer_freight_charge < $minFreightCharge) {
            $error = "Minimum freight charge is $" . number_format($minFreightCharge, 2) . ". Please charge the customer a higher amount before submitting this request.";
        }

        // If no errors, insert in DB
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
                    ':method' => $shipping_method,
                    ':freight' => $customer_freight_charge,
                    ':instructions' => $special_instructions ?: null
                ]);

                $requestId = $pdo->lastInsertId();

                // Save Ship From address if selected alternative and checkbox is checked
                $save_ship_from = isset($_POST['save_ship_from']) && $_POST['save_ship_from'] === '1' && isset($_POST['ship_from_type']) && $_POST['ship_from_type'] === 'alternative';
                if ($save_ship_from) {
                    $stmtCheckFrom = $pdo->prepare("SELECT id FROM saved_addresses WHERE store_id = ? AND address_type = 'from' AND name = ? AND company = ? AND address1 = ? AND address2 = ? AND city = ? AND state = ? AND zip = ? AND phone = ? AND email = ?");
                    $stmtCheckFrom->execute([
                        $storeId,
                        $ship_from_name,
                        $ship_from_company,
                        $ship_from_address1,
                        $ship_from_address2 ?: '',
                        $ship_from_city,
                        $ship_from_state,
                        $ship_from_zip,
                        $ship_from_phone,
                        $ship_from_email ?: ''
                    ]);
                    if (!$stmtCheckFrom->fetch()) {
                        $stmtInsertFrom = $pdo->prepare("INSERT INTO saved_addresses (store_id, address_type, name, company, address1, address2, city, state, zip, phone, email) VALUES (?, 'from', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtInsertFrom->execute([
                            $storeId,
                            $ship_from_name,
                            $ship_from_company,
                            $ship_from_address1,
                            $ship_from_address2 ?: null,
                            $ship_from_city,
                            $ship_from_state,
                            $ship_from_zip,
                            $ship_from_phone,
                            $ship_from_email ?: null
                        ]);
                    }
                }

                // Save Ship To address if checkbox is checked
                $save_ship_to = isset($_POST['save_ship_to']) && $_POST['save_ship_to'] === '1';
                if ($save_ship_to) {
                    $stmtCheckTo = $pdo->prepare("SELECT id FROM saved_addresses WHERE store_id = ? AND address_type = 'to' AND name = ? AND company = ? AND address1 = ? AND address2 = ? AND city = ? AND state = ? AND zip = ? AND phone = ? AND email = ?");
                    $stmtCheckTo->execute([
                        $storeId,
                        $ship_to_name,
                        $ship_to_company,
                        $ship_to_address1,
                        $ship_to_address2 ?: '',
                        $ship_to_city,
                        $ship_to_state,
                        $ship_to_zip,
                        $ship_to_phone,
                        $ship_to_email ?: ''
                    ]);
                    if (!$stmtCheckTo->fetch()) {
                        $stmtInsertTo = $pdo->prepare("INSERT INTO saved_addresses (store_id, address_type, name, company, address1, address2, city, state, zip, phone, email) VALUES (?, 'to', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmtInsertTo->execute([
                            $storeId,
                            $ship_to_name,
                            $ship_to_company,
                            $ship_to_address1,
                            $ship_to_address2 ?: null,
                            $ship_to_city,
                            $ship_to_state,
                            $ship_to_zip,
                            $ship_to_phone,
                            $ship_to_email ?: null
                        ]);
                    }
                }

                // 1. Audit Log: Record freight charge value and address type used
                $addressTypeUsed = (isset($_POST['ship_from_type']) && $_POST['ship_from_type'] === 'alternative') ? 'Alternate Address Used' : 'Default Address Used';
                log_activity($userId, "Request $request_number Created - Freight Charge: $" . number_format($customer_freight_charge, 2), $requestId, "Address Mode: $addressTypeUsed");

                // 2. Add Notification for logistics staff
                // Query all administrators
                $stmtAdmins = $pdo->query("SELECT id FROM users WHERE role IN ('Super Admin', 'Logistics Admin')");
                $admins = $stmtAdmins->fetchAll();
                foreach ($admins as $adm) {
                    add_notification($adm['id'], "New Request submitted: $request_number", "Store $storeName has submitted a new label request. SO#: $sales_order_number");
                }

                // 3. Send SMTP Email to HQ Admins
                require_once dirname(__DIR__) . '/src/mail.php';


                // Redirect to success state
                header("Location: request_create.php?success=1&req_num=" . urlencode($request_number));
                exit;

            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Retrieve primary email from notification_emails
$storeEmail = !empty($store['notification_emails']) ? $store['notification_emails'] : ($currentUser['email'] ?? '');
if ($storeEmail && strpos($storeEmail, ',') !== false) {
    $storeEmail = trim(explode(',', $storeEmail)[0]);
}
$storePhone = !empty($store['phone']) ? $store['phone'] : ($currentUser['phone'] ?? '');
require_once dirname(__DIR__) . '/src/header.php';
?>

<!-- Hidden element storing default address variables for jQuery -->
<div id="store-data" style="display:none;" 
     data-name="<?php echo e($currentUser['name'] ?? 'Store User'); ?>"
     data-company="<?php echo e($store['store_name']); ?>"
     data-address="<?php echo e($store['address']); ?>"
     data-city="<?php echo e($store['city']); ?>"
     data-state="<?php echo e($store['state']); ?>"
     data-zip="<?php echo e($store['zip']); ?>"
     data-phone="<?php echo e($storePhone); ?>"
     data-email="<?php echo e($storeEmail); ?>">
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        
        <?php if ($success): ?>
            <div class="card p-5 text-center my-4" style="border-radius: 12px;">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill" style="font-size: 5rem; color: var(--success);"></i>
                </div>
                <h1 class="brand-font mb-2" style="color: var(--text-primary); font-size: 1.6rem;">Shipment Request Submitted!</h1>
                <p style="color: var(--text-secondary); font-size: 1rem;">The logistics team has been notified and your request is now queued.</p>
                <div class="d-inline-block mx-auto mb-4 p-4 rounded-3" style="min-width: 280px; background: var(--primary-light); border: 1px solid #93C5FD;">
                    <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: var(--text-muted);">Shipment Number</div>
                    <div style="font-size: 2rem; font-weight: 800; color: var(--primary); font-family: 'Outfit', sans-serif;"><?php echo e($generatedReqNumber); ?></div>
                </div>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="dashboard.php" class="btn-command-secondary px-4">Back to Dashboard</a>
                    <a href="request_create.php" class="btn-command-primary px-4">Submit Another Request</a>
                </div>
            </div>
        <?php else: ?>

            <div class="card mb-5" style="border-radius: 12px; overflow: hidden;">
                <!-- Card Header -->
                <div class="card-header-primary py-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-plus" style="font-size: 1.1rem;"></i>
                        <h3 class="mb-0 fw-bold" style="font-size: 1.05rem; color: #fff; font-family: 'Outfit', sans-serif;">Create Shipment Request</h3>
                    </div>
                    <a href="dashboard.php" class="btn-command-secondary" style="padding: 5px 14px; font-size: 0.82rem; background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); color: #fff !important;">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>

                <div class="card-body p-4">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert-command-danger mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Error:</strong> <?php echo e($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="request_create.php" method="POST" id="request-form">
                        <?php csrf_input(); ?>

                        <!-- ==========================================
                             SECTION 1: SHIP FROM
                        =========================================== -->
                        <div class="mb-4 pb-3" style="border-bottom: 2px solid var(--primary-light);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                    <i class="bi bi-house-door-fill me-2"></i>1. Ship From Address
                                </h5>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ship_from_type" id="ship_from_default" value="default" checked>
                                        <label class="form-check-label fw-semibold" for="ship_from_default" style="color: var(--text-primary); cursor: pointer;">Default Store</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ship_from_type" id="ship_from_alt" value="alternative">
                                        <label class="form-check-label fw-semibold" for="ship_from_alt" style="color: var(--text-primary); cursor: pointer;">Alternative Location</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Saved Ship From Address Selector -->
                        <div class="mb-4 p-3 rounded-3" style="background: var(--primary-light); border: 1px solid #93C5FD;" id="store-selector-wrapper">
                            <label class="form-label-command" style="color: var(--primary);">
                                <i class="bi bi-bookmark-fill me-1"></i>Saved Addresses — Auto-Fill
                            </label>
                            <select id="store_address_selector" class="form-control-command">
                                <option value="">— Select a saved address to auto-fill —</option>
                                <optgroup label="Default Store Addresses">
                                    <?php foreach ($allStores as $s): ?>
                                        <option value="store_<?php echo $s['id']; ?>" 
                                                data-name="<?php echo e($currentUser['name'] ?? 'Store User'); ?>"
                                                data-company="<?php echo e($s['store_name']); ?>"
                                                data-address="<?php echo e($s['address']); ?>"
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
                                    <optgroup label="Custom Saved Addresses">
                                        <?php foreach ($savedFromAddresses as $sa): ?>
                                            <option value="saved_<?php echo $sa['id']; ?>" 
                                                    data-name="<?php echo e($sa['name']); ?>"
                                                    data-company="<?php echo e($sa['company']); ?>"
                                                    data-address="<?php echo e($sa['address1']); ?>"
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
                                <input type="text" name="ship_from_name" id="ship_from_name" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_company" id="ship_from_company" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_address1" id="ship_from_address1" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 2</label>
                                <input type="text" name="ship_from_address2" id="ship_from_address2" class="form-control-command ship-from-field" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">City <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_city" id="ship_from_city" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">State <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_state" id="ship_from_state" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-command">ZIP <span class="text-danger">*</span></label>
                                <input type="text" name="ship_from_zip" id="ship_from_zip" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Phone <span class="text-danger">*</span></label>
                                <input type="tel" name="ship_from_phone" id="ship_from_phone" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Email <span class="text-danger">*</span></label>
                                <input type="email" name="ship_from_email" id="ship_from_email" class="form-control-command ship-from-field" required readonly>
                            </div>
                            <!-- Save Address Checkbox -->
                            <div class="col-12" id="save-ship-from-wrapper" style="display:none;">
                                <div class="form-check p-3 rounded" style="background: var(--accent-gold-light); border: 1px solid #fde68a;">
                                    <input class="form-check-input" type="checkbox" name="save_ship_from" id="save_ship_from" value="1">
                                    <label class="form-check-label fw-semibold" for="save_ship_from" style="color: var(--accent-gold-dark); cursor: pointer;">
                                        <i class="bi bi-bookmark-plus me-1"></i> Save this address for future use
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- ==========================================
                             SECTION 2: SHIP TO
                        =========================================== -->
                        <div class="mb-4 pb-3" style="border-bottom: 2px solid var(--primary-light);">
                            <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                <i class="bi bi-geo-alt-fill me-2"></i>2. Ship To Address
                            </h5>
                        </div>

                        <!-- Ship To Saved Address Selector -->
                        <div class="mb-4 p-3 rounded-3" style="background: var(--primary-light); border: 1px solid #93C5FD;" id="ship-to-selector-wrapper">
                            <label class="form-label-command" style="color: var(--primary);">
                                <i class="bi bi-bookmark-fill me-1"></i>Saved Addresses — Auto-Fill
                            </label>
                            <select id="ship_to_address_selector" class="form-control-command">
                                <option value="">— Select a saved address to auto-fill —</option>
                                <optgroup label="Default Store Addresses">
                                    <?php foreach ($allStores as $s): ?>
                                        <option value="store_<?php echo $s['id']; ?>" 
                                                data-name="<?php echo e($currentUser['name'] ?? 'Store User'); ?>"
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
                                    <optgroup label="Custom Saved Addresses">
                                        <?php foreach ($savedToAddresses as $sa): ?>
                                            <option value="saved_<?php echo $sa['id']; ?>" 
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
                                <input type="text" name="ship_to_name" id="ship_to_name" class="form-control-command" placeholder="Recipient's Full Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_company" id="ship_to_company" class="form-control-command" placeholder="Destination Company Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" name="ship_to_address1" id="ship_to_address1" class="form-control-command" placeholder="Street Address, P.O. Box, etc." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Address Line 2</label>
                                <input type="text" name="ship_to_address2" id="ship_to_address2" class="form-control-command" placeholder="Apartment, suite, unit, etc.">
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
                                <input type="text" name="ship_to_zip" id="ship_to_zip" class="form-control-command" placeholder="ZIP code" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Phone <span class="text-danger">*</span></label>
                                <input type="tel" name="ship_to_phone" id="ship_to_phone" class="form-control-command" placeholder="Recipient's Phone" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Email <span class="text-danger">*</span></label>
                                <input type="email" name="ship_to_email" id="ship_to_email" class="form-control-command" placeholder="Recipient's Email" required>
                            </div>
                            <!-- Save Ship To Address Checkbox -->
                            <div class="col-12">
                                <div class="form-check p-3 rounded" style="background: var(--accent-gold-light); border: 1px solid #fde68a;">
                                    <input class="form-check-input" type="checkbox" name="save_ship_to" id="save_ship_to" value="1">
                                    <label class="form-check-label fw-semibold" for="save_ship_to" style="color: var(--accent-gold-dark); cursor: pointer;">
                                        <i class="bi bi-bookmark-plus me-1"></i> Save this address for future use
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
                                        <input type="text" name="sales_order_number" class="form-control-command" placeholder="e.g. SO-98765" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-command">Request Reference (REQ#)</label>
                                        <input type="text" name="request_reference" class="form-control-command" placeholder="e.g. REQ-4321 (optional)">
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
                                            <input type="number" step="0.1" name="length" class="form-control-command text-center" placeholder="L" required min="0.1" style="flex:1;">
                                            <span style="color: var(--text-muted); font-weight: 700; flex-shrink: 0;">×</span>
                                            <input type="number" step="0.1" name="width" class="form-control-command text-center" placeholder="W" required min="0.1" style="flex:1;">
                                            <span style="color: var(--text-muted); font-weight: 700; flex-shrink: 0;">×</span>
                                            <input type="number" step="0.1" name="height" class="form-control-command text-center" placeholder="H" required min="0.1" style="flex:1;">
                                            <span style="background: var(--bg); border: 1px solid var(--border); padding: 9px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); flex-shrink: 0;">IN</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-command">Weight <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="number" step="0.01" name="weight_lbs" class="form-control-command" placeholder="Total weight" required min="0.01" style="flex:1;">
                                            <span style="background: var(--bg); border: 1px solid var(--border); padding: 9px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); flex-shrink: 0;">LBS</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==========================================
                             SECTION 5: SHIPPING METHOD & FREIGHT
                        =========================================== -->
                        <div class="mb-4 pb-3" style="border-bottom: 2px solid var(--primary-light);">
                            <h5 class="mb-0 fw-bold" style="color: var(--primary); font-size: 1rem;">
                                <i class="bi bi-truck me-2"></i>5. Shipping Method &amp; Charges
                            </h5>
                        </div>

                        <div class="row mb-4 g-3">
                            <div class="col-md-6">
                                <label class="form-label-command">Shipping Method <span class="text-danger">*</span></label>
                                <select name="shipping_method" class="form-control-command" required>
                                    <option value="" disabled selected>Select Shipping Method...</option>
                                    <option value="UPS Ground">UPS Ground</option>
                                    <option value="FedEx Ground">FedEx Ground</option>
                                    <option value="UPS Next Day Air">UPS Next Day Air</option>
                                    <option value="UPS 2nd Day Air">UPS 2nd Day Air</option>
                                    <option value="FedEx Overnight">FedEx Overnight</option>
                                    <option value="FedEx 2 Day">FedEx 2 Day</option>
                                    <option value="USPS Priority">USPS Priority</option>
                                    <option value="LTL Freight">LTL Freight</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-command">Customer Freight Charge ($) <span class="text-danger">*</span></label>
                                <div class="d-flex gap-2 align-items-center">
                                    <span style="background: var(--bg); border: 1px solid var(--border); padding: 9px 12px; border-radius: 8px; font-size: 0.9rem; font-weight: 700; color: var(--text-secondary); flex-shrink: 0;">$</span>
                                    <input type="number" step="0.01" name="customer_freight_charge" id="customer_freight_charge" 
                                           class="form-control-command" placeholder="0.00" required
                                           data-min-charge="<?php echo $minFreightCharge; ?>"
                                           style="flex:1;">
                                </div>
                                <div class="invalid-feedback" id="freight-error-msg" style="display:none; color: var(--danger); font-size: 0.78rem; margin-top: 4px;"></div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                                    Minimum charge: <strong>$<?php echo number_format($minFreightCharge, 2); ?></strong>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label-command">Special Instructions</label>
                                <textarea name="special_instructions" class="form-control-command" rows="3" placeholder="Enter any special delivery instructions or handling notes..."></textarea>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid mt-4">
                            <button type="submit" id="submit-request-btn" class="btn-command-primary py-3 justify-content-center" style="font-size: 1rem; letter-spacing: 0.5px; border-radius: 10px;">
                                <i class="bi bi-send-fill me-2"></i> SUBMIT SHIPPING REQUEST
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/src/footer.php'; ?>
