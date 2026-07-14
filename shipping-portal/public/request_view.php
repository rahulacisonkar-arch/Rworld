<?php
$pageTitle = "View Shipment Details";
require_once dirname(__DIR__) . '/src/header.php';
require_once dirname(__DIR__) . '/src/EasyshipService.php';
require_once dirname(__DIR__) . '/src/mail.php';

$requestId = intval($_GET['id'] ?? 0);
if ($requestId <= 0) {
    die("Invalid Request ID");
}

$error = '';
$success_msg = '';

// Fetch Request Details
try {
    $stmt = $pdo->prepare("SELECT r.*, s.store_name, s.store_code, s.notification_emails as store_email 
                            FROM label_requests r 
                            JOIN stores s ON r.store_id = s.id 
                            WHERE r.id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        die("Shipping request not found.");
    }

    // Verify Ownership (allows requester store or destination store)
    check_request_ownership($request);

    // Fetch associated carton labels
    $labels_stmt = $pdo->prepare("SELECT * FROM request_labels WHERE request_id = ? ORDER BY id ASC");
    $labels_stmt->execute([$requestId]);
    $request_labels = $labels_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error loading request details: " . $e->getMessage());
}

// Admin Action Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role, ['Super Admin', 'Logistics Admin'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $error = "Security validation failed (CSRF token invalid).";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_request') {
            $newStatus = trim($_POST['status'] ?? '');
            $internalNotes = trim($_POST['internal_notes'] ?? '');

            if (empty($error)) {
                try {
                    // Update Database Row
                    $stmt = $pdo->prepare("UPDATE label_requests 
                                            SET status = :status, internal_notes = :notes 
                                            WHERE id = :id");
                    $stmt->execute([
                        ':status' => $newStatus,
                        ':notes' => $internalNotes ?: null,
                        ':id' => $request['id']
                    ]);

                    // Audit Logs depending on what changed
                    if ($newStatus !== $request['status']) {
                        log_activity($userId, "Status of request " . $request['request_number'] . " updated to " . $newStatus, $request['id']);
                        notify_shipment_status_change($request['id'], $newStatus);
                    }

                    // Reload updated request data
                    $stmt = $pdo->prepare("SELECT r.*, s.store_name, s.store_code, s.notification_emails as store_email 
                                            FROM label_requests r 
                                            JOIN stores s ON r.store_id = s.id 
                                            WHERE r.id = ?");
                    $stmt->execute([$request['id']]);
                    $request = $stmt->fetch();

                    $success_msg = "Request details updated successfully.";

                } catch (PDOException $e) {
                    $error = "Database update error: " . $e->getMessage();
                }
            }
        } 
        
        elseif ($action === 'approve_create_label') {
            try {
                $selectedCourierId = trim($_POST['selected_courier_id'] ?? '');
                $selectedCourierName = trim($_POST['selected_courier_name'] ?? '');
                $selectedCourierCost = trim($_POST['selected_courier_cost'] ?? '');
                $selectedEnv = trim($_POST['selected_env'] ?? 'production');

                if (empty($selectedCourierId)) {
                    // Automatically query and pick the cheapest rate
                    $rates = EasyshipService::getRates($request, $selectedEnv);
                    if (!empty($rates)) {
                        $cheapestRate = $rates[0]; // Already sorted ascending by cost
                        $selectedCourierId = $cheapestRate['courier_id'];
                        $selectedCourierName = $cheapestRate['courier_name'];
                        $selectedCourierCost = $cheapestRate['shipment_charge'];
                    } else {
                        throw new Exception("Could not retrieve shipping rates for this package.");
                    }
                }

                $res = EasyshipService::createLabel($request, $selectedCourierId, $selectedCourierName, $selectedCourierCost, $selectedEnv);
                
                if (!$res['success']) {
                    $error = "Easyship label generation failed: " . $res['error'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO request_labels (request_id, label_file, tracking_number, carrier, estimated_delivery_date, actual_shipping_cost, easyship_shipment_id, tracking_status) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, 'Label Ready')");
                    $stmt->execute([
                        $request['id'],
                        $res['label_file'],
                        $res['tracking_number'],
                        $res['carrier'],
                        $res['estimated_delivery_date'],
                        $res['actual_shipping_cost'],
                        $res['easyship_shipment_id']
                    ]);
                    $insertedLabelId = $pdo->lastInsertId();
                    
                    // Set session variable to trigger automatic download after redirect
                    session_start_safe();
                    $_SESSION['download_label_id'] = $insertedLabelId;

                    $update_status_stmt = $pdo->prepare("UPDATE label_requests SET status = 'Label Created', shipping_method = ? WHERE id = ?");
                    $update_status_stmt->execute([$res['carrier'], $request['id']]);
                    $request['status'] = 'Label Created';
                    $request['shipping_method'] = $res['carrier'];
                    notify_shipment_status_change($request['id'], 'Label Created');
                    
                    log_activity($userId, "Approved request and generated Easyship label (Tracking: " . $res['tracking_number'] . ", Cost: $" . number_format($res['actual_shipping_cost'], 2) . ")", $request['id']);
                    
                    // Reload updated labels list to include the newly generated label
                    $labels_stmt = $pdo->prepare("SELECT * FROM request_labels WHERE request_id = ? ORDER BY id ASC");
                    $labels_stmt->execute([$request['id']]);
                    $request_labels = $labels_stmt->fetchAll();

                    $success_msg = "Request approved and Easyship label generated successfully.";
                }
            } catch (Exception $e) {
                $error = "Error creating shipping label: " . $e->getMessage();
            }
        }
        
        elseif ($action === 'add_label') {
            $trackingNumber = trim($_POST['tracking_number'] ?? '');
            $carrier = trim($_POST['carrier'] ?? 'UPS');
            $estDelivery = trim($_POST['estimated_delivery'] ?? '');
            
            $fileUploaded = !empty($_FILES['label_file']['name']);
            if (!$fileUploaded) {
                $error = "Please select a PDF file to upload.";
            } else {
                $file = $_FILES['label_file'];
                $fileName = $file['name'];
                $fileTmp = $file['tmp_name'];
                $fileSize = $file['size'];
                $fileError = $file['error'];
                
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if ($fileExt !== 'pdf') {
                    $error = "Only PDF files are allowed.";
                } elseif ($fileSize > 20 * 1024 * 1024) {
                    $error = "File size exceeds maximum limit of 20MB.";
                } elseif ($fileError !== UPLOAD_ERR_OK) {
                    $error = "File upload failed with error code: " . $fileError;
                } else {
                    // Generate secure filename
                    $savedFilename = 'label_' . $request['id'] . '_' . bin2hex(random_bytes(8)) . '.pdf';
                    $destPath = UPLOAD_DIR . $savedFilename;
                    
                    if (!move_uploaded_file($fileTmp, $destPath)) {
                        $error = "Failed to save file to secure storage directory.";
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO request_labels (request_id, label_file, tracking_number, carrier, estimated_delivery_date) 
                                                    VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $request['id'],
                                $savedFilename,
                                $trackingNumber ?: null,
                                $carrier ?: null,
                                $estDelivery ?: null
                            ]);
                            
                            log_activity($userId, "Added carton label " . $trackingNumber . " for request " . $request['request_number'], $request['id']);
                            
                            // Auto transition status to 'Label Created' if currently 'Pending' or 'Processing'
                            if (in_array($request['status'], ['Pending', 'Processing'])) {
                                $update_status_stmt = $pdo->prepare("UPDATE label_requests SET status = 'Label Created' WHERE id = ?");
                                $update_status_stmt->execute([$request['id']]);
                                $request['status'] = 'Label Created';
                                notify_shipment_status_change($request['id'], 'Label Created');
                            }
                            
                            $success_msg = "Carton label added successfully.";
                            
                            // Reload updated labels list
                            $labels_stmt = $pdo->prepare("SELECT * FROM request_labels WHERE request_id = ? ORDER BY id ASC");
                            $labels_stmt->execute([$requestId]);
                            $request_labels = $labels_stmt->fetchAll();
                        } catch (PDOException $e) {
                            $error = "Database insert error: " . $e->getMessage();
                        }
                    }
                }
            }
        }
        
        elseif ($action === 'auto_add_label') {
            $fileUploaded = !empty($_FILES['label_file']['name']);
            if (!$fileUploaded) {
                $error = "Please select a PDF file to upload.";
            } else {
                $file = $_FILES['label_file'];
                $fileName = $file['name'];
                $fileTmp = $file['tmp_name'];
                $fileSize = $file['size'];
                $fileError = $file['error'];
                
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if ($fileExt !== 'pdf') {
                    $error = "Only PDF files are allowed.";
                } elseif ($fileSize > 20 * 1024 * 1024) {
                    $error = "File size exceeds maximum limit of 20MB.";
                } elseif ($fileError !== UPLOAD_ERR_OK) {
                    $error = "File upload failed with error code: " . $fileError;
                } else {
                    // Generate secure filename
                    $savedFilename = 'label_' . $request['id'] . '_' . bin2hex(random_bytes(8)) . '.pdf';
                    $destPath = UPLOAD_DIR . $savedFilename;
                    
                    if (!move_uploaded_file($fileTmp, $destPath)) {
                        $error = "Failed to save file to secure storage directory.";
                    } else {
                        // Extract text and auto-detect tracking/carrier/est delivery
                        $pdfText = extractTextFromPdf($destPath);
                        
                        // 1. Detect Tracking Number
                        $trackingNumber = '';
                        if (preg_match('/TRACKING:\s*([^\s\)]+)/i', $pdfText, $match)) {
                            $trackingNumber = trim($match[1]);
                        } elseif (preg_match('/1Z[A-Z0-9]{16}/i', $pdfText, $match)) {
                            $trackingNumber = trim($match[0]);
                        } elseif (preg_match('/\b\d{12}\b/', $pdfText, $match)) {
                            $trackingNumber = trim($match[0]);
                        } elseif (preg_match('/\b\d{15}\b/', $pdfText, $match)) {
                            $trackingNumber = trim($match[0]);
                        } elseif (preg_match('/\b\d{20}\b/', $pdfText, $match)) {
                            $trackingNumber = trim($match[0]);
                        } else {
                            // Fallback if not detected
                            $trackingNumber = 'TRK' . rand(100000000, 999999999);
                        }
                        
                        // 2. Detect Carrier
                        $carrier = 'UPS'; // Default
                        if (preg_match('/FedEx/i', $pdfText)) {
                            $carrier = 'FedEx';
                        } elseif (preg_match('/DHL/i', $pdfText)) {
                            $carrier = 'DHL';
                        } elseif (preg_match('/USPS/i', $pdfText)) {
                            $carrier = 'USPS';
                        } elseif (preg_match('/UPS/i', $pdfText)) {
                            $carrier = 'UPS';
                        }
                        
                        // 3. Detect Est. Delivery Date (e.g. YYYY-MM-DD or MM/DD/YYYY or DD-MM-YYYY)
                        $estDelivery = null;
                        if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $pdfText, $match)) {
                            $estDelivery = date('Y-m-d', strtotime($match[1]));
                        } elseif (preg_match('/\b(\d{2}-\d{2}-20\d{2})\b/', $pdfText, $match)) {
                            $estDelivery = date('Y-m-d', strtotime($match[1]));
                        } elseif (preg_match('/\b(\d{2}\/\d{2}\/20\d{2})\b/', $pdfText, $match)) {
                            $estDelivery = date('Y-m-d', strtotime($match[1]));
                        } else {
                            // Default to +3 business days
                            $estDelivery = date('Y-m-d', strtotime('+3 days'));
                        }
                        
                        try {
                            $stmt = $pdo->prepare("INSERT INTO request_labels (request_id, label_file, tracking_number, carrier, estimated_delivery_date) 
                                                    VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $request['id'],
                                $savedFilename,
                                $trackingNumber,
                                $carrier,
                                $estDelivery
                            ]);
                            
                            log_activity($userId, "Automatically added carton label " . $trackingNumber . " for request " . $request['request_number'], $request['id']);
                            
                            // Auto transition status to 'Label Created' if currently 'Pending' or 'Processing'
                            if (in_array($request['status'], ['Pending', 'Processing'])) {
                                $update_status_stmt = $pdo->prepare("UPDATE label_requests SET status = 'Label Created' WHERE id = ?");
                                $update_status_stmt->execute([$request['id']]);
                                $request['status'] = 'Label Created';
                                notify_shipment_status_change($request['id'], 'Label Created');
                            }
                            
                            $success_msg = "Carton label automatically uploaded and parsed. Detected " . $carrier . " Tracking: " . $trackingNumber;
                            
                            // Reload updated labels list
                            $labels_stmt = $pdo->prepare("SELECT * FROM request_labels WHERE request_id = ? ORDER BY id ASC");
                            $labels_stmt->execute([$request['id']]);
                            $request_labels = $labels_stmt->fetchAll();
                        } catch (PDOException $e) {
                            $error = "Database insert error: " . $e->getMessage();
                        }
                    }
                }
            }
        }
        
        elseif ($action === 'delete_label') {
            $labelId = intval($_POST['label_id'] ?? 0);
            if ($labelId <= 0) {
                $error = "Invalid label identifier.";
            } else {
                try {
                    // Fetch label details to delete file
                    $lbl_stmt = $pdo->prepare("SELECT label_file, tracking_number FROM request_labels WHERE id = ? AND request_id = ?");
                    $lbl_stmt->execute([$labelId, $request['id']]);
                    $label_to_delete = $lbl_stmt->fetch();
                    
                    if ($label_to_delete) {
                        // Delete file
                        $filePath = UPLOAD_DIR . $label_to_delete['label_file'];
                        if (file_exists($filePath) && is_file($filePath)) {
                            @unlink($filePath);
                        }
                        
                        // Delete record
                        $del_stmt = $pdo->prepare("DELETE FROM request_labels WHERE id = ?");
                        $del_stmt->execute([$labelId]);
                        
                        log_activity($userId, "Deleted carton label " . $label_to_delete['tracking_number'] . " from request " . $request['request_number'], $request['id']);
                        
                        $success_msg = "Carton label deleted successfully.";
                        
                        // Reload updated labels list
                        $labels_stmt = $pdo->prepare("SELECT * FROM request_labels WHERE request_id = ? ORDER BY id ASC");
                        $labels_stmt->execute([$requestId]);
                        $request_labels = $labels_stmt->fetchAll();
                    } else {
                        $error = "Label not found.";
                    }
                } catch (PDOException $e) {
                    $error = "Database deletion error: " . $e->getMessage();
                }
            }
        }
        
        elseif ($action === 'send_label') {
            // Check if label file and tracking number are available
            if (empty($request_labels)) {
                $error = "Cannot send label. Please upload at least one carton label first.";
            } else {
                try {
                    $newStatus = trim($_POST['status'] ?? 'Label Sent');
                    $internalNotes = trim($_POST['internal_notes'] ?? '');

                    // 1. Update status and internal notes in database
                    $stmt = $pdo->prepare("UPDATE label_requests SET status = :status, internal_notes = :notes WHERE id = :id");
                    $stmt->execute([
                        ':status' => $newStatus,
                        ':notes' => $internalNotes ?: null,
                        ':id' => $request['id']
                    ]);
                    
                    // Update current request variable
                    $request['status'] = $newStatus;
                    $request['internal_notes'] = $internalNotes;

                    log_activity($userId, "Sent label and updated status of request " . $request['request_number'] . " to " . $newStatus, $request['id']);

                    // 2. Create in-app notifications
                    notify_shipment_status_change($request['id'], $newStatus);

                    $success_msg = "Label delivery triggered successfully. Status updated to " . $newStatus . " and store(s) notified (app notifications generated).";

                } catch (PDOException $e) {
                    $error = "Error sending label: " . $e->getMessage();
                } catch (Exception $e) {
                    $error = "Error sending label: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="row">
    <div class="col-lg-8">
        
        <?php if (!empty($error)): ?>
            <div class="alert-command-danger mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-command-success mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo e($success_msg); ?>
            </div>
        <?php endif; ?>

        <!-- REQUEST CARD -->
        <div class="card mb-4">
            <div class="card-header-primary py-3 d-flex align-items-center justify-content-between">
                <div>
                    <div style="font-size: 0.68rem; color: rgba(255,255,255,0.65); text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Shipment Details</div>
                    <h3 class="mb-0 brand-font" style="color: #fff; font-size: 1.2rem;"><?php echo e($request['request_number']); ?></h3>
                </div>
                <div>
                    <?php 
                    $statusClass = 'badge-pending';
                    if ($request['status'] === 'Processing') $statusClass = 'badge-processing';
                    if ($request['status'] === 'Label Created') $statusClass = 'badge-label-created';
                    if ($request['status'] === 'Label Sent') $statusClass = 'badge-label-sent';
                    if ($request['status'] === 'Completed') $statusClass = 'badge-completed';
                    if ($request['status'] === 'Cancelled') $statusClass = 'badge-cancelled';
                    ?>
                    <span class="badge-command <?php echo $statusClass; ?>" style="font-size: 0.82rem; padding: 6px 14px;">
                        <?php echo e($request['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="card-body p-4">
                
                <!-- SHIP FROM / SHIP TO -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6" style="border-right: 1px solid var(--border);">
                        <h6 class="text-uppercase fw-bold mb-3" style="color: var(--primary); font-size: 0.72rem; letter-spacing: 1px;"><i class="bi bi-geo-alt me-1"></i> Ship From Address</h6>
                        <div>
                            <div class="fw-bold" style="color: var(--text-primary);"><?php echo e($request['ship_from_name']); ?></div>
                            <div style="color: var(--text-muted); font-size: 0.82rem;"><?php echo e($request['ship_from_company']); ?></div>
                            <div style="color: var(--text-secondary); margin-top: 4px;"><?php echo e($request['ship_from_address1']); ?></div>
                            <?php if ($request['ship_from_address2']): ?>
                                <div style="color: var(--text-secondary);"><?php echo e($request['ship_from_address2']); ?></div>
                            <?php endif; ?>
                            <div style="color: var(--text-secondary);"><?php echo e($request['ship_from_city']); ?>, <?php echo e($request['ship_from_state']); ?> <?php echo e($request['ship_from_zip']); ?></div>
                            <div style="color: var(--text-muted); font-size: 0.82rem; margin-top: 6px;"><i class="bi bi-telephone me-1"></i> <?php echo e($request['ship_from_phone']); ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-uppercase fw-bold mb-3" style="color: var(--primary); font-size: 0.72rem; letter-spacing: 1px;"><i class="bi bi-geo-fill me-1"></i> Ship To Address</h6>
                        <div>
                            <div class="fw-bold" style="color: var(--text-primary);"><?php echo e($request['ship_to_name']); ?></div>
                            <div style="color: var(--text-muted); font-size: 0.82rem;"><?php echo e($request['ship_to_company']); ?></div>
                            <div style="color: var(--text-secondary); margin-top: 4px;"><?php echo e($request['ship_to_address1']); ?></div>
                            <?php if ($request['ship_to_address2']): ?>
                                <div style="color: var(--text-secondary);"><?php echo e($request['ship_to_address2']); ?></div>
                            <?php endif; ?>
                            <div class="text-secondary"><?php echo e($request['ship_to_city']); ?>, <?php echo e($request['ship_to_state']); ?> <?php echo e($request['ship_to_zip']); ?></div>
                            <div class="text-muted small mt-2"><i class="bi bi-telephone me-1"></i> <?php echo e($request['ship_to_phone']); ?></div>
                        </div>
                    </div>
                </div>

                <hr class="mb-4" style="border-color: var(--border);">

                <!-- ORDER & PACKAGE INFO -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Sales Order (SO#)</div>
                        <div class="text-dark fw-bold"><?php echo e($request['sales_order_number']); ?></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Request Ref (REQ#)</div>
                        <div class="text-dark fw-bold"><?php echo e($request['request_reference'] ?: '-'); ?></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Store Creator</div>
                        <div class="text-dark fw-bold"><?php echo e($request['store_name']); ?></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Date Submitted</div>
                        <div class="text-dark fw-bold"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Shipping Method</div>
                        <div class="text-dark fw-bold"><?php echo e($request['shipping_method']); ?></div>
                    </div>
                    <?php if (in_array($role, ['Super Admin', 'Logistics Admin'])): ?>
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Customer Freight Charge</div>
                        <div class="text-warning fw-bold">$<?php echo number_format($request['customer_freight_charge'], 2); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Dimensions</div>
                        <div class="text-dark fw-bold"><?php echo floatval($request['length']); ?> x <?php echo floatval($request['width']); ?> x <?php echo floatval($request['height']); ?> IN</div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="text-muted small mb-1">Weight (LBS)</div>
                        <div class="text-dark fw-bold"><?php echo floatval($request['weight_lbs']); ?> lbs</div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="text-muted small mb-2">Special Instructions</div>
                    <div class="border p-3 rounded small" style="min-height:60px; background: var(--bg); border-color: var(--border) !important; color: #212529 !important;">
                        <?php echo nl2br(e($request['special_instructions'] ?: 'No special instructions.')); ?>
                    </div>
                </div>

                <!-- CARTON LABELS SYSTEM -->
                <div class="border rounded p-3" style="background: var(--bg); border-color: var(--border) !important; color: var(--text-primary);">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-box-seam text-warning me-2"></i>Carton Labels (<?php echo count($request_labels); ?>)</h6>
                    <?php if (empty($request_labels)): ?>
                        <div class="alert py-2 small mb-0" style="background: rgba(255, 193, 7, 0.1); color: var(--warning-color); border: 1px solid rgba(255, 193, 7, 0.2);"><i class="bi bi-exclamation-circle me-1"></i>No carton labels uploaded yet.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($request_labels as $index => $label): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3 <?php echo $index > 0 ? 'mt-2' : ''; ?> border rounded" style="background: #ffffff; border-color: var(--border) !important; color: var(--text-primary);">
                                    <div>
                                        <div class="fw-bold text-dark"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Carton #<?php echo $index + 1; ?> Label</div>
                                        <div class="text-muted small">Tracking: 
                                            <?php if ($label['tracking_number']): ?>
                                                <a href="download_label.php?id=<?php echo $label['id']; ?>" class="text-danger fw-bold text-decoration-none">
                                                    <u><?php echo e($label['tracking_number']); ?></u>
                                                </a>
                                                <?php if (!empty($label['tracking_status'])): ?>
                                                    <span class="badge bg-secondary text-white ms-2" style="font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;"><?php echo e($label['tracking_status']); ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <strong class="text-secondary">N/A</strong>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($label['carrier']): ?>
                                            <span class="badge bg-secondary text-white font-weight-normal mt-1"><?php echo e($label['carrier']); ?></span>
                                        <?php endif; ?>
                                        <?php if (in_array($role, ['Super Admin', 'Logistics Admin']) && isset($label['actual_shipping_cost']) && $label['actual_shipping_cost'] !== null): ?>
                                            <span class="badge bg-success text-white font-weight-normal mt-1">Cost: $<?php echo number_format($label['actual_shipping_cost'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="download_label.php?id=<?php echo $label['id']; ?>" class="btn-command-primary px-3 py-1 btn-sm text-decoration-none d-inline-block">
                                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download
                                        </a>
                                        <?php if (in_array($role, ['Super Admin', 'Logistics Admin'])): ?>
                                            <form action="request_view.php?id=<?php echo $request['id']; ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this label?');" style="display:inline; margin:0;">
                                                <?php csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete_label">
                                                <input type="hidden" name="label_id" value="<?php echo $label['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-command-secondary p-1" style="line-height:1; min-height:0; padding: 5px 8px !important;">
                                                    <i class="bi bi-trash text-danger"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- AUTO UPLOAD CARTON LABEL (DRAG & DROP) -->
                <?php if (in_array($role, ['Super Admin', 'Logistics Admin'])): ?>
                    <div class="card mt-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>Add Carton Label (Automatic)</h6>
                            <form action="request_view.php?id=<?php echo $request['id']; ?>" method="POST" enctype="multipart/form-data" id="form-auto-add-label">
                                <?php csrf_input(); ?>
                                <input type="hidden" name="action" value="auto_add_label">
                                <input type="file" name="label_file" id="auto-label-file-input" accept="application/pdf" style="display: none;">
                                
                                <div id="auto-label-drag-drop-zone" class="drag-drop-zone p-4 border border-dashed rounded text-center" style="cursor: pointer; background: rgba(0,0,0,0.01); border-color: var(--border) !important; border-width: 2px !important; transition: all 0.2s ease;">
                                    <div class="py-3">
                                        <i class="bi bi-cloud-arrow-up-fill text-primary" style="font-size: 2.2rem;"></i>
                                        <div class="fw-bold text-dark mt-2" style="font-size: 0.95rem;">Drag & Drop Label PDF here</div>
                                        <div class="text-muted small mt-1">or click to browse from files</div>
                                        <div id="auto-file-selected-name" class="text-success small mt-2 fw-bold" style="display: none;"></div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- ADMIN ACTION SIDE PANEL -->
    <div class="col-lg-4">
        
        <!-- TRACKING DETAILS CARD -->
        <div class="glass-card mb-4">
            <div class="card-header-premium py-3">
                <h6 class="mb-0 text-dark fw-bold"><i class="bi bi-truck-flatbed me-2 text-primary"></i>Delivery & Tracking Timeline</h6>
            </div>
            <div class="card-body p-4">
                <?php 
                $statusOrder = ['Pending', 'Processing', 'Label Created', 'Label Sent', 'Completed'];
                $currentStatusIndex = array_search($request['status'], $statusOrder);
                if ($currentStatusIndex === false) {
                    $currentStatusIndex = -1; // e.g. for Cancelled
                }
                ?>
                <div class="timeline-logistics mb-4">
                    <div class="timeline-item <?php echo $currentStatusIndex >= 0 ? 'active' : ''; ?>">
                        <div class="fw-bold text-dark small">Pending</div>
                        <div class="text-muted small">Request submitted by store and awaiting review</div>
                    </div>
                    <div class="timeline-item <?php echo $currentStatusIndex >= 1 ? 'active' : ''; ?>">
                        <div class="fw-bold text-dark small">Processing</div>
                        <div class="text-muted small">Logistics team is compiling shipment details</div>
                    </div>
                    <div class="timeline-item <?php echo $currentStatusIndex >= 2 ? 'active' : ''; ?>">
                        <div class="fw-bold text-dark small">Label Created</div>
                        <div class="text-muted small">PDF shipping labels generated for all cartons</div>
                    </div>
                    <div class="timeline-item <?php echo $currentStatusIndex >= 3 ? 'active' : ''; ?>">
                        <div class="fw-bold text-dark small">Label Sent</div>
                        <div class="text-muted small">Labels delivered and store notified</div>
                    </div>
                    <div class="timeline-item <?php echo $currentStatusIndex >= 4 ? 'active' : ''; ?>">
                        <div class="fw-bold text-dark small">Completed</div>
                        <div class="text-muted small">Shipment picked up and tracking active</div>
                    </div>
                </div>

                <?php if (empty($request_labels)): ?>
                    <div class="text-muted small text-center p-2 border rounded" style="background: rgba(0,0,0,0.02); border-color: var(--border);">Tracking numbers have not been generated yet.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($request_labels as $index => $lbl): ?>
                            <div class="p-2 border rounded" style="background: rgba(0,0,0,0.02); border-color: var(--border);">
                                <div class="text-muted small fw-bold">Carton #<?php echo $index + 1; ?>:</div>
                                <div class="fs-6 fw-bold text-dark mt-1 d-flex gap-2 align-items-center flex-wrap">
                                    <span class="badge bg-dark text-primary border px-2 py-1" style="border-color: rgba(255,255,255,0.1) !important;"><?php echo e($lbl['tracking_number'] ?: 'N/A'); ?></span>
                                    <?php if ($lbl['carrier']): ?>
                                        <span class="badge bg-secondary text-white px-2 py-1"><?php echo e($lbl['carrier']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($lbl['tracking_status'])): ?>
                                        <span class="badge bg-secondary text-white px-2 py-1"><?php echo e($lbl['tracking_status']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($lbl['estimated_delivery_date'])): ?>
                                        <span class="small text-muted" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-1"></i>Est: <?php echo date('M d, Y', strtotime($lbl['estimated_delivery_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (in_array($role, ['Super Admin', 'Logistics Admin'])): ?>
            <style>
            /* Premium Rate Selection Cards */
            .rate-card {
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 12px 14px;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #ffffff;
                position: relative;
                user-select: none;
                border-left: 5px solid var(--border);
                margin-bottom: 8px;
            }
            .rate-card:hover {
                border-color: #93C5FD;
                background: #f8fafc;
            }
            .rate-card.selected {
                border-color: var(--primary);
                background: var(--primary-light);
                box-shadow: 0 4px 12px rgba(30, 90, 168, 0.08);
            }
            .rate-card-left {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .rate-card-radio {
                transform: scale(1.1);
                cursor: pointer;
            }
            .rate-carrier-icon {
                font-size: 0.7rem;
                width: 36px;
                height: 36px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                letter-spacing: -0.5px;
                flex-shrink: 0;
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
                gap: 1px;
            }
            .rate-name {
                font-weight: 700;
                color: var(--text-primary);
                font-size: 0.88rem;
            }
            .rate-time {
                font-size: 0.75rem;
                color: var(--text-muted);
            }
            .rate-card-right {
                text-align: right;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 4px;
                flex-shrink: 0;
            }
            .rate-price {
                font-size: 1.15rem;
                font-weight: 800;
                color: var(--success);
                font-family: 'Outfit', sans-serif;
            }
            .badge-rate {
                font-size: 0.6rem;
                padding: 2px 6px;
                border-radius: 4px;
                font-weight: 700;
                text-transform: uppercase;
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

            <div class="glass-card mb-4">
                <div class="card-header-premium py-3">
                    <h6 class="mb-0 text-dark fw-bold"><i class="bi bi-gear-fill me-2 text-primary"></i>Admin Operations Control</h6>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Form 0: Approve & Create Label (Easyship API) -->
                    <div class="border rounded p-3 mb-4" style="background: rgba(0,0,0,0.01); border-color: var(--border) !important;">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-cpu text-primary me-2"></i>Easyship API Automations</h6>
                        
                        <?php if (in_array($request['status'], ['Pending', 'Processing'])): ?>
                            <form action="request_view.php?id=<?php echo $request['id']; ?>" method="POST" id="form-approve-create-label">
                                <?php csrf_input(); ?>
                                <input type="hidden" name="action" value="approve_create_label">
                                <input type="hidden" name="selected_courier_id" id="form-selected-courier-id" value="">
                                <input type="hidden" name="selected_courier_name" id="form-selected-courier-name" value="">
                                <input type="hidden" name="selected_courier_cost" id="form-selected-courier-cost" value="">

                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold">API Environment Mode <span class="text-danger">*</span></label>
                                    <select name="selected_env" id="selected_env" class="form-select form-control-command">
                                        <option value="sandbox" <?php echo (defined('EASYSHIP_ENV') && EASYSHIP_ENV === 'sandbox') ? 'selected' : ''; ?>>Sandbox Simulator Mode</option>
                                        <option value="production" <?php echo (defined('EASYSHIP_ENV') && EASYSHIP_ENV === 'production') ? 'selected' : ''; ?>>Production Live Mode</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <button type="button" id="btn-fetch-rates" class="btn btn-sm btn-command-secondary py-2 px-3">
                                        <i class="bi bi-arrow-repeat me-1"></i> Retrieve Live Rates
                                    </button>
                                    <span id="rates-loading-spinner" class="ms-2" style="display:none;">
                                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                                    </span>
                                </div>

                                <!-- Live Rates List Container -->
                                <div class="mb-3" id="rates-result-container" style="display:none;">
                                    <div class="fw-bold mb-2 text-dark small">Select Shipping Solution:</div>
                                    <div id="rates-list-container" class="d-flex flex-column">
                                        <!-- Dynamically loaded rates -->
                                    </div>
                                </div>

                                <button type="submit" id="submit-approve-btn" class="btn btn-warning w-100 py-2 fw-bold" style="border: none; color: #3B1F0E; font-size: 0.85rem; letter-spacing: 0.5px; border-radius: 6px; background: #FFC107;" disabled>
                                    <i class="bi bi-printer-fill me-2"></i>CREATE &amp; PRINT LABEL
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info py-2 px-3 mb-0 small" style="background: rgba(23,162,184,0.1); border: 1px solid rgba(23,162,184,0.2); color: #17a2b8; border-radius: 6px;">
                                <i class="bi bi-info-circle-fill me-2"></i>Shipment already approved/processed.
                            </div>
                        <?php endif; ?>
                    </div>


                    <!-- Form 2: Unified Update & Notification Form -->
                    <form action="request_view.php?id=<?php echo $request['id']; ?>" method="POST" id="form-admin-operations" class="mb-4">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" id="admin-operations-action" value="update_request">

                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold">Update Request Status</label>
                            <select name="status" class="form-select form-control-command">
                                <option value="Pending" <?php echo $request['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processing" <?php echo $request['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="Label Created" <?php echo $request['status'] === 'Label Created' ? 'selected' : ''; ?>>Label Created</option>
                                <option value="Label Sent" <?php echo $request['status'] === 'Label Sent' ? 'selected' : ''; ?>>Label Sent</option>
                                <option value="Completed" <?php echo $request['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $request['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold">Internal Logistics Notes</label>
                            <textarea name="internal_notes" class="form-control form-control-command" rows="3" placeholder="Add internal notes here..."><?php echo e($request['internal_notes']); ?></textarea>
                        </div>

                        <div class="d-flex flex-column gap-2">
                            <button type="submit" onclick="document.getElementById('admin-operations-action').value='update_request';" class="btn-command-primary w-100 py-2">
                                <i class="bi bi-save me-1"></i> Save Status & Notes
                            </button>
                            <button type="submit" onclick="document.getElementById('admin-operations-action').value='send_label';" class="btn btn-primary w-100 py-2 fw-bold" <?php echo empty($request_labels) ? 'disabled' : ''; ?> style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; color: #FFFFFF; font-size: 0.85rem; letter-spacing: 0.5px; border-radius: 6px; <?php echo empty($request_labels) ? 'opacity: 0.5; cursor: not-allowed; background: gray; box-shadow: none;' : ''; ?>">
                                <i class="bi bi-send-fill me-1"></i> SEND LABEL & NOTIFY STORE
                            </button>
                        </div>
                        <?php if (empty($request_labels)): ?>
                            <div class="text-center text-danger small mt-2">Upload or generate at least one carton label to enable sending.</div>
                        <?php endif; ?>
                    </form>

                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php
session_start_safe();
if (isset($_SESSION['download_label_id'])): 
    $dlId = intval($_SESSION['download_label_id']);
    unset($_SESSION['download_label_id']);
?>
<script>
$(document).ready(function() {
    // Automatically trigger label PDF download
    window.location.href = 'download_label.php?id=<?php echo $dlId; ?>';
});
</script>
<?php endif; ?>

<script>
$(document).ready(function() {
    // Rates retrieval handler for request approval
    $('#btn-fetch-rates').on('click', function(e) {
        e.preventDefault();
        
        // Reset selections
        $('#rates-result-container').hide();
        $('#rates-list-container').empty();
        $('#form-selected-courier-id').val('');
        $('#form-selected-courier-name').val('');
        $('#form-selected-courier-cost').val('');
        $('#submit-approve-btn').prop('disabled', true);

        $('#rates-loading-spinner').show();
        $(this).prop('disabled', true);

        // Gather static details of this store request from PHP
        const data = {
            ship_from_name: <?php echo json_encode($request['ship_from_name']); ?>,
            ship_from_company: <?php echo json_encode($request['ship_from_company']); ?>,
            ship_from_address1: <?php echo json_encode($request['ship_from_address1']); ?>,
            ship_from_address2: <?php echo json_encode($request['ship_from_address2']); ?>,
            ship_from_city: <?php echo json_encode($request['ship_from_city']); ?>,
            ship_from_state: <?php echo json_encode($request['ship_from_state']); ?>,
            ship_from_zip: <?php echo json_encode($request['ship_from_zip']); ?>,
            ship_from_phone: <?php echo json_encode($request['ship_from_phone']); ?>,
            ship_from_email: <?php echo json_encode($request['ship_from_email']); ?>,
            
            ship_to_name: <?php echo json_encode($request['ship_to_name']); ?>,
            ship_to_company: <?php echo json_encode($request['ship_to_company']); ?>,
            ship_to_address1: <?php echo json_encode($request['ship_to_address1']); ?>,
            ship_to_address2: <?php echo json_encode($request['ship_to_address2']); ?>,
            ship_to_city: <?php echo json_encode($request['ship_to_city']); ?>,
            ship_to_state: <?php echo json_encode($request['ship_to_state']); ?>,
            ship_to_zip: <?php echo json_encode($request['ship_to_zip']); ?>,
            ship_to_phone: <?php echo json_encode($request['ship_to_phone']); ?>,
            ship_to_email: <?php echo json_encode($request['ship_to_email']); ?>,
            
            length: <?php echo json_encode(floatval($request['length'])); ?>,
            width: <?php echo json_encode(floatval($request['width'])); ?>,
            height: <?php echo json_encode(floatval($request['height'])); ?>,
            weight_lbs: <?php echo json_encode(floatval($request['weight_lbs'])); ?>,
            env: $('#selected_env').val()
        };

        $.ajax({
            url: 'ajax_get_live_rates.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            timeout: 60000,
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

                        const safeCharge = parseFloat(rate.shipment_charge) || 0;
                        const cardHtml = `
                            <div class="rate-card ${carrierClass}" 
                                 data-id="${rate.courier_id}" 
                                 data-name="${rate.courier_name}" 
                                 data-cost="${safeCharge}">
                                <div class="rate-card-left">
                                    <input class="form-check-input rate-card-radio select-courier-radio" type="radio" name="courier_radio" 
                                           id="radio_rate_${idx}"
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
                                    <div class="rate-price">$${safeCharge.toFixed(2)}</div>
                                    ${badges}
                                </div>
                            </div>
                        `;
                        $('#rates-list-container').append(cardHtml);
                    });

                } else {
                    alert("Could not load shipping rates. Error: " + (response.error || 'No matching rates returned.'));
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                $('#rates-loading-spinner').hide();
                $('#btn-fetch-rates').prop('disabled', false);
                let msg = 'An error occurred while fetching live rates.';
                if (textStatus === 'timeout') {
                    msg = 'The rates request timed out (>60s). Please try again.';
                } else if (xhr.responseText) {
                    msg = 'Rates fetch error: ' + textStatus + ' - ' + xhr.responseText.substring(0, 200);
                }
                alert(msg);
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

        $('#form-selected-courier-id').val(selectedId);
        $('#form-selected-courier-name').val(selectedName);
        $('#form-selected-courier-cost').val(selectedCost);
        
        $('#submit-approve-btn').prop('disabled', false);
    });

    // Form submit loader
    $('#form-approve-create-label').on('submit', function() {
        $('#submit-approve-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating Label...');
    });
});
</script>

<?php require_once dirname(__DIR__) . '/src/footer.php'; ?>
