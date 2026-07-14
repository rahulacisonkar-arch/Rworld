<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();

$pageTitle = 'Bulk Import Bills';
require_once dirname(__DIR__) . '/src/header.php';

$success = '';
$error = '';
$importResults = null;
$validationErrors = [];

// Handle Bulk File Upload & Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        $error = "Security validation failed. Please try again.";
    } else {
        $file = $_FILES['excel_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "File upload failed with error code: " . $file['error'];
        } else {
            $tmpPath = $file['tmp_name'];
            $fileName = $file['name'];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if ($extension !== 'xlsx') {
                $error = "Invalid file type. Please upload a standard Excel spreadsheet (.xlsx).";
            } else {
                // Save temporarily
                $tempName = 'bulk_import_' . uniqid() . '.xlsx';
                $destPath = UPLOAD_DIR . $tempName;
                
                if (move_uploaded_file($tmpPath, $destPath)) {
                    // Call Python parsing script using virtualenv python path directly with standard cmd.exe quoting
                    $pythonPath = "c:\\Users\\Artee Admin\\Desktop\\browser-use-main\\.venv\\Scripts\\python.exe";
                    $scriptPath = dirname(__DIR__) . '/src/parse_bulk_excel.py';
                    $command = '""' . $pythonPath . '" "' . $scriptPath . '" "' . $destPath . '""';
                    $output = shell_exec($command);
                    
                    // Clean up temp file
                    if (file_exists($destPath)) {
                        unlink($destPath);
                    }
                    
                    $result = null;
                    if ($output) {
                        $result = json_decode($output, true);
                        if ($result === null) {
                            // Strip warnings/headers and look for json row
                            $lines = explode("\n", $output);
                            foreach ($lines as $line) {
                                $decoded = json_decode(trim($line), true);
                                if ($decoded !== null) {
                                    $result = $decoded;
                                    break;
                                }
                            }
                        }
                    }
                    
                    if ($result === null) {
                        $error = "Failed to parse Excel file. Make sure the template format was not modified. Output: " . $output;
                    } elseif (isset($result['success']) && $result['success'] === false) {
                        $error = $result['error'] ?? "Validation failed.";
                        if (isset($result['errors'])) {
                            $validationErrors = $result['errors'];
                        }
                    } else {
                        // Import validated rows
                        $rows = $result['rows'] ?? [];
                        $importedStores = 0;
                        $importedConnections = 0;
                        $importedBills = 0;
                        $skippedBills = 0;
                        
                        try {
                            $pdo->beginTransaction();
                            
                            $stmtFindStore = $pdo->prepare("SELECT id FROM stores WHERE store_code = ?");
                            $stmtInsertStore = $pdo->prepare("INSERT INTO stores (store_code, store_name, address, city, state, zip, phone, notification_emails, location_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Showroom')");
                            
                            $stmtFindConn = $pdo->prepare("SELECT id FROM utility_connections WHERE store_id = ? AND utility_type = ?");
                            $stmtInsertConn = $pdo->prepare("INSERT INTO utility_connections (store_id, utility_type, provider_name, account_number, notes, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                            
                            $stmtFindBill = $pdo->prepare("SELECT id FROM bills WHERE connection_id = ? AND store_id = ? AND statement_date = ? AND due_date = ? AND amount = ?");
                            $stmtInsertBill = $pdo->prepare("INSERT INTO bills (connection_id, store_id, statement_date, due_date, amount, bill_file_path, status, paid_at, transaction_ref, notes) VALUES (?, ?, ?, ?, ?, '', ?, ?, ?, ?)");
                            
                            foreach ($rows as $row) {
                                $storeCode = $row['store_code'];
                                
                                // 1. Get or Create Store
                                $stmtFindStore->execute([$storeCode]);
                                $storeRow = $stmtFindStore->fetch(PDO::FETCH_ASSOC);
                                
                                if ($storeRow) {
                                    $storeId = $storeRow['id'];
                                } else {
                                    // Dynamically register store with basic default settings
                                    $storeName = "Store " . $storeCode;
                                    $stmtInsertStore->execute([
                                        $storeCode,
                                        $storeName,
                                        '100 Main St',
                                        'Cityville',
                                        'US',
                                        '12345',
                                        '1-800-555-0199',
                                        'admin@arteefabrics.com'
                                    ]);
                                    $storeId = $pdo->lastInsertId();
                                    $importedStores++;
                                }
                                
                                // 2. Get or Create Connection
                                $utilityType = $row['utility_type'];
                                $stmtFindConn->execute([$storeId, $utilityType]);
                                $connRow = $stmtFindConn->fetch(PDO::FETCH_ASSOC);
                                
                                if ($connRow) {
                                    $connectionId = $connRow['id'];
                                } else {
                                    $stmtInsertConn->execute([
                                        $storeId,
                                        $utilityType,
                                        $row['provider_name'],
                                        $row['account_number'],
                                        'Bulk imported connection'
                                    ]);
                                    $connectionId = $pdo->lastInsertId();
                                    $importedConnections++;
                                }
                                
                                // 3. Insert Bill (prevent duplicates)
                                $stmtFindBill->execute([
                                    $connectionId,
                                    $storeId,
                                    $row['statement_date'],
                                    $row['due_date'],
                                    $row['amount']
                                ]);
                                
                                if ($stmtFindBill->fetch()) {
                                    $skippedBills++;
                                    continue;
                                }
                                
                                // Insert Bill
                                $billStatus = $row['status']; // Paid or Pending
                                $paidAt = ($billStatus === 'Paid') ? date('Y-m-d H:i:s') : null;
                                $txnRef = $row['transaction_ref'];
                                
                                $stmtInsertBill->execute([
                                    $connectionId,
                                    $storeId,
                                    $row['statement_date'],
                                    $row['due_date'],
                                    $row['amount'],
                                    $billStatus,
                                    $paidAt,
                                    $txnRef,
                                    $row['notes']
                                ]);
                                
                                $importedBills++;
                                log_activity($userId, "Bulk imported bill for Store {$storeCode} - {$utilityType} ($" . number_format($row['amount'], 2) . ")");
                            }
                            
                            $pdo->commit();
                            
                            $success = "Bulk utility data imported successfully!";
                            $importResults = [
                                "total_rows" => count($rows),
                                "imported_bills" => $importedBills,
                                "skipped_bills" => $skippedBills,
                                "imported_stores" => $importedStores,
                                "imported_connections" => $importedConnections
                            ];
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $error = "Failed importing data to database: " . $e->getMessage();
                        }
                    }
                } else {
                    $error = "Failed to store uploaded spreadsheet file.";
                }
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-primary-dark">Bulk Upload Utility Data</h3>
        <p class="text-muted mb-0">Download the official template, input utility data across fiscal years, and upload it in bulk.</p>
    </div>
</div>

<div class="row">
    <!-- Left Column: Guidelines & Template Download -->
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white h-100">
            <div class="card-header bg-primary-dark text-white py-3 border-0">
                <h5 class="card-title mb-0 fw-semibold"><i class="bi bi-info-circle me-1"></i> Import Guidelines</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <p class="text-muted small">To ensure your bulk data is mapped correctly to retail stores, connections, and ledger columns, follow these guidelines:</p>
                    
                    <ul class="list-group list-group-flush mb-4 text-secondary small">
                        <li class="list-group-item px-0 py-2 border-light d-flex align-items-start gap-2 bg-transparent">
                            <span class="badge bg-primary rounded-circle p-1.5 mt-0.5"><i class="bi bi-check" style="font-size: 0.65rem;"></i></span>
                            <div><strong>Store Code:</strong> Use numeric store codes (e.g., 53, 83, 70). Unrecognized codes will dynamically create a new showroom automatically.</div>
                        </li>
                        <li class="list-group-item px-0 py-2 border-light d-flex align-items-start gap-2 bg-transparent">
                            <span class="badge bg-primary rounded-circle p-1.5 mt-0.5"><i class="bi bi-check" style="font-size: 0.65rem;"></i></span>
                            <div><strong>Utility Types:</strong> Must be one of: <code>Telephone</code>, <code>Internet</code>, <code>Gas</code>, <code>Electricity</code>, <code>Water</code>, or <code>Sewer</code>.</div>
                        </li>
                        <li class="list-group-item px-0 py-2 border-light d-flex align-items-start gap-2 bg-transparent">
                            <span class="badge bg-primary rounded-circle p-1.5 mt-0.5"><i class="bi bi-check" style="font-size: 0.65rem;"></i></span>
                            <div><strong>Dates Format:</strong> Ensure dates are set to YYYY-MM-DD or standard Excel date values.</div>
                        </li>
                        <li class="list-group-item px-0 py-2 border-light d-flex align-items-start gap-2 bg-transparent">
                            <span class="badge bg-primary rounded-circle p-1.5 mt-0.5"><i class="bi bi-check" style="font-size: 0.65rem;"></i></span>
                            <div><strong>Payment Status:</strong> Should be <code>Paid</code> or <code>Pending</code>. If <code>Paid</code>, a <strong>Transaction Reference</strong> is required.</div>
                        </li>
                    </ul>
                </div>
                
                <div class="bg-light rounded-3 p-3 text-center border">
                    <div class="fw-semibold text-primary mb-2" style="font-size: 0.9rem;">Download Sample Template</div>
                    <p class="text-muted small mb-3">Download our pre-styled sample Excel workbook format with guides already filled in.</p>
                    <a href="utility_upload_template.xlsx" download class="btn btn-outline-primary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-file-earmark-arrow-down"></i> Download Template (.xlsx)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Drag-and-Drop Uploader -->
    <div class="col-lg-7 mb-4">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom border-light">
                <h5 class="card-title mb-0 fw-semibold text-primary-dark"><i class="bi bi-cloud-arrow-up me-1"></i> Upload Completed Excel File</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 rounded-3 shadow-xs d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <div>
                            <strong class="d-block"><?php echo $success; ?></strong>
                            <span class="small text-muted">
                                Imported <?php echo $importResults['imported_bills']; ?> bills, 
                                created <?php echo $importResults['imported_connections']; ?> connections, 
                                and <?php echo $importResults['imported_stores']; ?> new stores. 
                                (<?php echo $importResults['skipped_bills']; ?> duplicate rows skipped).
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 rounded-3 shadow-xs d-flex align-items-start gap-2 mb-4">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-5 mt-0.5"></i>
                        <div>
                            <strong class="d-block">Import Error</strong>
                            <span class="small"><?php echo $error; ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($validationErrors): ?>
                    <div class="mb-4">
                        <div class="text-danger fw-semibold mb-2" style="font-size:0.88rem;"><i class="bi bi-x-circle-fill"></i> Row Validation Failures (No rows were imported):</div>
                        <div class="table-responsive border rounded-3 bg-light" style="max-height: 250px;">
                            <table class="table table-sm table-striped mb-0 small text-secondary">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th class="text-center" style="width: 80px;">Row</th>
                                        <th style="width: 100px;">Store Code</th>
                                        <th style="width: 120px;">Utility Type</th>
                                        <th>Reason(s) for Failure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($validationErrors as $err): ?>
                                        <tr>
                                            <td class="text-center fw-bold"><?php echo $err['row']; ?></td>
                                            <td><span class="badge bg-secondary-light text-secondary"><?php echo e($err['store_code']); ?></span></td>
                                            <td><span class="badge bg-primary-light text-primary"><?php echo e($err['utility_type']); ?></span></td>
                                            <td class="text-danger small">
                                                <ul class="mb-0 ps-3">
                                                    <?php foreach ($err['reasons'] as $reason): ?>
                                                        <li><?php echo e($reason); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="bulkUploadForm" method="POST" action="bulk_upload.php" enctype="multipart/form-data">
                    <?php csrf_input(); ?>
                    
                    <div id="dropzone" class="border border-2 border-dashed rounded-3 p-5 text-center mb-4" style="border-color: #ced4da !important; cursor: pointer; transition: all 0.2s;" ondragover="event.preventDefault(); this.style.borderColor='#1E5AA8'; this.style.backgroundColor='#F0F6FC';" ondragleave="this.style.borderColor='#ced4da'; this.style.backgroundColor='transparent';" ondrop="event.preventDefault(); this.style.borderColor='#ced4da'; this.style.backgroundColor='transparent'; handleFileDrop(event);">
                        <i class="bi bi-file-earmark-excel text-primary" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 fw-bold">Drag and drop your spreadsheet here</h6>
                        <p class="text-muted small mb-3">or click to browse local files (.xlsx format only)</p>
                        
                        <input type="file" id="excel_file" name="excel_file" accept=".xlsx" class="d-none" onchange="handleFileSelect(this)">
                        <button type="button" class="btn btn-primary btn-sm px-4" onclick="document.getElementById('excel_file').click()"><i class="bi bi-folder2-open"></i> Browse Files</button>
                    </div>

                    <div id="fileInfo" class="d-none alert alert-light border rounded-3 p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-spreadsheet text-success fs-3"></i>
                                <div>
                                    <div class="fw-semibold small" id="selectedFileName">filename.xlsx</div>
                                    <div class="text-muted" style="font-size: 0.72rem;" id="selectedFileSize">0 KB</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle p-1" onclick="clearSelectedFile()"><i class="bi bi-x-lg" style="font-size: 0.75rem;"></i></button>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn btn-primary w-100 py-2.5 d-flex align-items-center justify-content-center gap-2" disabled>
                        <i class="bi bi-check2-circle"></i> Validate &amp; Import Bulk Ledger
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function handleFileDrop(e) {
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        document.getElementById('excel_file').files = files;
        updateFileInfo(files[0]);
    }
}

function handleFileSelect(input) {
    if (input.files.length > 0) {
        updateFileInfo(input.files[0]);
    }
}

function updateFileInfo(file) {
    if (file.name.split('.').pop().toLowerCase() !== 'xlsx') {
        Swal.fire({
            title: 'Invalid File',
            text: 'Please select a valid Excel spreadsheet file (.xlsx extension only).',
            icon: 'warning',
            confirmButtonColor: '#164888'
        });
        clearSelectedFile();
        return;
    }
    
    document.getElementById('dropzone').classList.add('d-none');
    document.getElementById('fileInfo').classList.remove('d-none');
    document.getElementById('selectedFileName').textContent = file.name;
    document.getElementById('selectedFileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('submitBtn').removeAttribute('disabled');
}

function clearSelectedFile() {
    document.getElementById('excel_file').value = '';
    document.getElementById('dropzone').classList.remove('d-none');
    document.getElementById('fileInfo').classList.add('d-none');
    document.getElementById('submitBtn').setAttribute('disabled', 'true');
}
</script>

<?php
require_once dirname(__DIR__) . '/src/footer.php';
?>
