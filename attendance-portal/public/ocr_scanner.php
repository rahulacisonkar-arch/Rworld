<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
redirect_unauthenticated();

if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$storeId = $_SESSION['store_id'] ?? null;

// 1. Handle AJAX POST requests for running PaddleOCR + AI Validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_paddle_ocr') {
    header('Content-Type: application/json');
    $imgData = $_POST['image_data'] ?? '';
    
    if (empty($imgData)) {
        echo json_encode(["success" => false, "error" => "No image data received."]);
        exit;
    }
    
    // Check if FastAPI service is running, start if offline
    $fp = @fsockopen('127.0.0.1', 5000, $errno, $errstr, 0.5);
    if (!$fp) {
        $pythonPath = "c:\\Users\\Artee Admin\\Desktop\\browser-use-main\\.venv\\Scripts\\python.exe";
        $serverScript = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ocr_server.py';
        $cmd = 'start /B "" "' . $pythonPath . '" -u "' . $serverScript . '"';
        pclose(popen($cmd, 'r'));
        usleep(4500000); // wait 4.5s for initialization
    } else {
        fclose($fp);
    }
    
    // Fetch active database employees to send to the AI engine for accurate mapping
    if ($role === 'Super Admin') {
        $stmtEmps = $pdo->query("SELECT id, name, email FROM employees WHERE deleted_at IS NULL ORDER BY name ASC");
    } else {
        $stmtEmps = $pdo->prepare("SELECT id, name, email FROM employees WHERE store_id = ? AND deleted_at IS NULL ORDER BY name ASC");
        $stmtEmps->execute([$storeId]);
    }
    $dbEmployees = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);
    
    $postData = [
        'image_data' => $imgData,
        'employees' => $dbEmployees
    ];
    
    // Call Python OCR microservice
    $ch = curl_init('http://127.0.0.1:5000/scan');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        echo json_encode(["success" => false, "error" => "OCR microservice failed (HTTP code $httpCode). Please ensure the OCR microservice is running."]);
        exit;
    }
    
    echo $response;
    exit;
}

// 2. Handle Commit of Validated Timesheet Log Grid to Database
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'commit_ocr_timesheet') {
    $employeeId = intval($_POST['employee_id']);
    $weekEnding = trim($_POST['week_ending']);
    $logsJson = trim($_POST['logs_data'] ?? '');
    
    $logs = json_decode($logsJson, true);
    
    if (empty($employeeId) || empty($weekEnding) || empty($logs)) {
        $error = "Employee profile, week ending date, and timesheet logs are required.";
    } else {
        // Fetch employee's store and hourly rate
        $stmtEmp = $pdo->prepare("SELECT store_id, hourly_rate FROM employees WHERE id = ?");
        $stmtEmp->execute([$employeeId]);
        $empData = $stmtEmp->fetch();
        
        if (!$empData) {
            $error = "Selected employee not found.";
        } else {
            $empStoreId = $empData['store_id'];
            $hourlyRate = floatval($empData['hourly_rate']);
            
            // Get store shift guidelines
            $stmtStore = $pdo->prepare("SELECT shift_start, shift_end FROM stores WHERE id = ?");
            $stmtStore->execute([$empStoreId]);
            $storeData = $stmtStore->fetch();
            $shiftStart = $storeData['shift_start'] ?? '09:00:00';
            $shiftEnd = $storeData['shift_end'] ?? '18:00:00';
            
            $pdo->beginTransaction();
            try {
                $totalRegHours = 0.00;
                $totalOtHours = 0.00;
                
                // Process each log entry from the editable review grid
                foreach ($logs as $log) {
                    $logDate = trim($log['date'] ?? '');
                    $inTime = trim($log['clock_in'] ?? '');
                    $outTime = trim($log['clock_out'] ?? '');
                    
                    if (empty($logDate) || empty($inTime) || empty($outTime)) {
                        continue;
                    }
                    
                    // Parse login/logout datetime
                    $loginDateTime = $logDate . ' ' . date('H:i:s', strtotime($inTime));
                    $logoutDateTime = $logDate . ' ' . date('H:i:s', strtotime($outTime));
                    
                    // Deduct breaks if daily hours is calculated
                    $breakSecs = 0;
                    
                    // Calculate shift metrics
                    $metrics = calculate_attendance_metrics($loginDateTime, $logoutDateTime, $logDate, $breakSecs, $shiftStart, $shiftEnd);
                    
                    // Check duplicate logs for this employee on this date
                    $stmtCheck = $pdo->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND date = ?");
                    $stmtCheck->execute([$employeeId, $logDate]);
                    $existingLogId = $stmtCheck->fetchColumn();
                    
                    if ($existingLogId) {
                        $stmtUpdate = $pdo->prepare("
                            UPDATE attendance_logs 
                            SET login_time = ?, logout_time = ?, status = 'Completed', calculated_hours = ?, calculated_overtime = ?, is_late = ?, is_early_departure = ?, manager_approved = 1 
                            WHERE id = ?
                        ");
                        $stmtUpdate->execute([
                            $loginDateTime, 
                            $logoutDateTime, 
                            $metrics['total_hours'], 
                            $metrics['overtime'], 
                            $metrics['is_late'], 
                            $metrics['is_early_departure'], 
                            $existingLogId
                        ]);
                    } else {
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO attendance_logs 
                            (employee_id, store_id, date, login_time, logout_time, status, log_type, calculated_hours, calculated_overtime, is_late, is_early_departure, manager_approved) 
                            VALUES (?, ?, ?, ?, ?, 'Completed', 'Regular', ?, ?, ?, ?, 1)
                        ");
                        $stmtInsert->execute([
                            $employeeId, 
                            $empStoreId, 
                            $logDate, 
                            $loginDateTime, 
                            $logoutDateTime, 
                            $metrics['total_hours'], 
                            $metrics['overtime'],
                            $metrics['is_late'],
                            $metrics['is_early_departure']
                        ]);
                    }
                    
                    $totalRegHours += floatval($metrics['total_hours'] - $metrics['overtime']);
                    $totalOtHours += floatval($metrics['overtime']);
                }
                
                // Adjust weekly hours
                $combinedHours = $totalRegHours + $totalOtHours;
                if ($combinedHours > 40.00) {
                    $reg = 40.00;
                    $ot = $combinedHours - 40.00;
                } else {
                    $reg = $combinedHours;
                    $ot = 0.00;
                }
                
                // Upsert employee_timesheets
                $stmtTS = $pdo->prepare("SELECT id FROM employee_timesheets WHERE employee_id = ? AND week_ending = ?");
                $stmtTS->execute([$employeeId, $weekEnding]);
                $tsId = $stmtTS->fetchColumn();
                
                if ($tsId) {
                    $stmtUpdateTS = $pdo->prepare("
                        UPDATE employee_timesheets 
                        SET regular_hours = ?, overtime_hours = ?, total_hours = ?, status = 'Approved' 
                        WHERE id = ?
                    ");
                    $stmtUpdateTS->execute([$reg, $ot, $combinedHours, $tsId]);
                } else {
                    $stmtInsertTS = $pdo->prepare("
                        INSERT INTO employee_timesheets (employee_id, week_ending, regular_hours, overtime_hours, total_hours, status) 
                        VALUES (?, ?, ?, ?, ?, 'Approved')
                    ");
                    $stmtInsertTS->execute([$employeeId, $weekEnding, $reg, $ot, $combinedHours]);
                }
                
                // Upsert payroll_summary
                $periodStart = date('Y-m-d', strtotime($weekEnding . ' - 13 days'));
                $totalPay = ($reg * $hourlyRate) + ($ot * $hourlyRate * 1.5);
                
                $stmtPS = $pdo->prepare("SELECT id FROM payroll_summary WHERE employee_id = ? AND period_end = ?");
                $stmtPS->execute([$employeeId, $weekEnding]);
                $psId = $stmtPS->fetchColumn();
                
                if ($psId) {
                    $stmtUpdatePS = $pdo->prepare("
                        UPDATE payroll_summary 
                        SET period_start = ?, total_hours = ?, total_pay = ?, calculated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmtUpdatePS->execute([$periodStart, $combinedHours, $totalPay, $psId]);
                } else {
                    $stmtInsertPS = $pdo->prepare("
                        INSERT INTO payroll_summary (employee_id, period_start, period_end, total_hours, total_pay) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtInsertPS->execute([$employeeId, $periodStart, $weekEnding, $combinedHours, $totalPay]);
                }
                
                // Save original scan metadata to audit logs
                $stmtAudit = $pdo->prepare("
                    INSERT INTO ocr_audit_logs (user_id, employee_id, file_name, raw_ocr_text, extracted_data, modified_data) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtUser->execute([$username]);
                $userId = $stmtUser->fetchColumn() ?: null;
                
                $fileName = trim($_POST['file_name'] ?? 'scan_timesheet.jpg');
                $rawText = trim($_POST['raw_ocr_text'] ?? '');
                $extractedData = trim($_POST['extracted_data'] ?? '');
                $modifiedData = json_encode([
                    'week_ending' => $weekEnding,
                    'logs' => $logs,
                    'regular_hours' => $reg,
                    'overtime_hours' => $ot,
                    'total_hours' => $combinedHours
                ]);
                
                $stmtAudit->execute([$userId, $employeeId, $fileName, $rawText, $extractedData, $modifiedData]);
                
                $pdo->commit();
                $success = "Validated timesheet data successfully committed to database tables (attendance_logs, employee_timesheets, payroll_summary).";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch active employees
if ($role === 'Super Admin') {
    $stmt = $pdo->query("SELECT id, name FROM employees WHERE deleted_at IS NULL ORDER BY name ASC");
    $employeesList = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, name FROM employees WHERE store_id = ? AND deleted_at IS NULL ORDER BY name ASC");
    $stmt->execute([$storeId]);
    $employeesList = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Timesheet Scanner — Artée Attendance</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="css/style.css" rel="stylesheet">
    <style>
        .drag-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background-color: var(--light-bg);
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .drag-zone:hover, .drag-zone.dragover {
            border-color: var(--primary);
            background-color: rgba(75, 35, 8, 0.05);
        }
        #image-preview {
            max-height: 420px;
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }
        .summary-badge {
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 700;
        }
        .table-editable input {
            border: none;
            background: transparent;
            padding: 2px 5px;
            width: 100%;
        }
        .table-editable input:focus {
            outline: 2px solid var(--primary);
            background: #fff;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div id="app-container">
    <!-- SIDEBAR -->
    <aside class="sidebar-command" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill text-white" style="font-size: 1.4rem;"></i>
                <div>
                    <div class="sidebar-brand-text">ARTÉE ATTENDANCE</div>
                    <div class="sidebar-brand-sub"><?php echo $role === 'Super Admin' ? 'HQ Operations Hub' : 'Store Portal'; ?></div>
                </div>
            </div>
        </div>
        <nav>
            <ul class="sidebar-menu">
                <li class="sidebar-section-title">Operations</li>
                <?php if ($role === 'Super Admin'): ?>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="admin_dashboard.php">
                        <i class="bi bi-grid-fill"></i>
                        <span>HQ Overview</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="manage_employees.php">
                        <i class="bi bi-people-fill"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="payroll.php">
                        <i class="bi bi-wallet2"></i>
                        <span>Payroll Admin</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="reports.php">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="artee_intelligence.php">
                        <i class="bi bi-cpu"></i>
                        <span>Artee Intelligence</span>
                    </a>
                </li>
                <?php else: ?>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="dashboard.php">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="sidebar-item">
                    <a class="sidebar-link" href="schedule.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Schedule</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="ocr_scanner.php">
                        <i class="bi bi-camera-fill"></i>
                        <span>OCR Scanner</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="manual_attendance.php">
                        <i class="bi bi-pencil-square"></i>
                        <span>Manual Attendance</span>
                    </a>
                </li>
                <li class="sidebar-item mt-4">
                    <a class="sidebar-link text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Sign Out</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- MAIN BODY -->
    <div id="main-content" class="w-100">
        <nav class="navbar navbar-expand-lg px-4 py-2 border-bottom bg-white" id="top-navbar">
            <div class="container-fluid p-0">
                <span class="navbar-brand fw-bold text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">
                    <i class="bi bi-camera-fill me-2 text-primary"></i>Enterprise OCR Timesheet Scanner
                </span>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <!-- Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-1.5"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1.5"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Dropzone / Scanner -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                        <h5 class="fw-bold text-primary-dark mb-3">Upload Timesheet Document</h5>
                        
                        <div class="drag-zone mb-3" id="drop-zone">
                            <i class="bi bi-cloud-upload-fill text-muted" style="font-size: 2.5rem;"></i>
                            <p class="mt-2 mb-1 fw-semibold" style="font-size:0.9rem;">Drag &amp; Drop or Click to Scan</p>
                            <span class="text-muted" style="font-size: 0.72rem;">Supports JPG, PNG, TIFF, PDFs</span>
                            <input type="file" id="file-input" accept="image/*,application/pdf" class="d-none">
                        </div>

                        <!-- Image Alignment Pre-processing Controls -->
                        <div class="d-none flex-column gap-3 mb-3 border p-3 rounded" id="editor-controls" style="background-color: #fcfcfc;">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-secondary" style="font-size:0.75rem;">Manual Align &amp; Process</span>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enable-filter" checked>
                                    <label class="form-check-label fw-semibold text-secondary" for="enable-filter" style="font-size:0.75rem;">Binarization Filter</label>
                                </div>
                            </div>
                            
                            <div id="filter-slider-container">
                                <div class="d-flex justify-content-between text-secondary" style="font-size: 0.68rem;">
                                    <span>Threshold Level:</span>
                                    <span class="fw-bold"><span id="threshold-val">120</span></span>
                                </div>
                                <input type="range" class="form-range" id="threshold-slider" min="50" max="220" value="120">
                            </div>

                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-rotate-left">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Rotate Left
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-rotate-right">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Rotate Right
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="btn-ocr-run">
                                    <i class="bi bi-play-circle-fill me-1.5"></i>Analyze Document
                                </button>
                            </div>
                        </div>

                        <div class="text-center mb-3">
                            <img id="image-preview" src="#" alt="Timesheet Preview" style="display:none;">
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress mb-3 d-none" id="progress-container" style="height: 18px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                        <div class="text-center text-muted mb-3 font-mono d-none" id="status-text" style="font-size:0.72rem;">Initializing OCR...</div>
                    </div>

                    <!-- Raw Extracted Text -->
                    <div class="card border-0 shadow-sm p-4 bg-white mt-4" style="border-radius: 12px;">
                        <h5 class="fw-bold text-primary-dark mb-3" style="font-size:0.9rem;">Raw OCR Text Stream</h5>
                        <textarea class="form-control font-mono" id="extracted-text" rows="6" readonly style="font-size:0.72rem; background-color:#f8f9fa;"></textarea>
                    </div>
                </div>

                <!-- Form & Results mapping -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-primary-dark mb-0">AI Extracted Review Grid</h5>
                            <div id="export-actions" class="d-none">
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-download me-1"></i>Export Data
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                        <li><a class="dropdown-item" href="#" onclick="exportGrid('csv')">Export as CSV</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="exportGrid('excel')">Export as Excel</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="exportGrid('pdf')">Export as PDF</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="exportGrid('json')">Export as JSON</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" action="ocr_scanner.php" id="commit-form">
                            <input type="hidden" name="action" value="commit_ocr_timesheet">
                            <input type="hidden" name="logs_data" id="logs-data-input">
                            <input type="hidden" name="file_name" id="file-name-input">
                            <input type="hidden" name="raw_ocr_text" id="raw-ocr-text-input">
                            <input type="hidden" name="extracted_data" id="extracted-data-input">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Employee Profile</label>
                                    <select name="employee_id" id="employee_id_select" class="form-select form-select-sm" required>
                                        <option value="">Select Employee...</option>
                                        <?php foreach ($employeesList as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>"><?php echo e($emp['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Week Ending Date</label>
                                    <input type="date" name="week_ending" id="week-ending-val" class="form-control form-control-sm" required>
                                </div>
                            </div>

                            <!-- Performance badging summary -->
                            <div id="timesheet-metrics" class="row g-2 mb-3 d-none">
                                <div class="col-4">
                                    <div class="bg-light p-2 rounded text-center">
                                        <span class="text-muted d-block" style="font-size:0.68rem;">Regular Hours</span>
                                        <span class="fw-bold text-success" id="reg-hours-summary" style="font-size:0.95rem;">0.00</span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light p-2 rounded text-center">
                                        <span class="text-muted d-block" style="font-size:0.68rem;">Overtime Hours</span>
                                        <span class="fw-bold text-warning" id="ot-hours-summary" style="font-size:0.95rem;">0.00</span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light p-2 rounded text-center">
                                        <span class="text-muted d-block" style="font-size:0.68rem;">Total Period Hours</span>
                                        <span class="fw-bold text-primary" id="total-hours-summary" style="font-size:0.95rem;">0.00</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Validation Warning Messages -->
                            <div id="ai-validation-warnings" class="alert alert-warning py-2 px-3 mb-3 d-none" style="font-size:0.75rem;">
                                <h6 class="fw-bold mb-1" style="font-size:0.78rem;"><i class="bi bi-exclamation-triangle-fill me-1.5"></i>AI Anomalies Detected</h6>
                                <ul class="mb-0 ps-3" id="warning-list"></ul>
                            </div>

                            <!-- Interactive spreadsheet grid -->
                            <div class="table-responsive border rounded mb-3" style="max-height: 290px;">
                                <table class="table table-sm table-bordered table-editable align-middle mb-0" style="font-size:0.78rem;">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th style="width: 25%;">Date</th>
                                            <th style="width: 15%;">Day</th>
                                            <th style="width: 20%;">Clock In</th>
                                            <th style="width: 20%;">Clock Out</th>
                                            <th style="width: 12%;">Hours</th>
                                            <th style="width: 8%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="logs-grid-body">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">No documents processed yet. Please upload a timesheet image/PDF to scan.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-row" style="font-size:0.72rem;">
                                    <i class="bi bi-plus-circle me-1"></i>Add Row Manually
                                </button>
                                <span class="text-muted" style="font-size:0.72rem;">* Click cells to make manual corrections</span>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-2 d-none" id="btn-save-db" style="border-radius:8px;">
                                <i class="bi bi-cloud-arrow-up-fill me-1.5"></i>Approve &amp; Commit Timesheet Log
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const imagePreview = document.getElementById('image-preview');
const editorControls = document.getElementById('editor-controls');
const btnRotateLeft = document.getElementById('btn-rotate-left');
const btnRotateRight = document.getElementById('btn-rotate-right');
const btnOcrRun = document.getElementById('btn-ocr-run');
const enableFilter = document.getElementById('enable-filter');
const thresholdSlider = document.getElementById('threshold-slider');
const thresholdVal = document.getElementById('threshold-val');

const progressBar = document.getElementById('progress-bar');
const progressContainer = document.getElementById('progress-container');
const statusText = document.getElementById('status-text');
const extractedText = document.getElementById('extracted-text');
const employeeSelect = document.getElementById('employee_id_select');
const weekEndingVal = document.getElementById('week-ending-val');
const logsGridBody = document.getElementById('logs-grid-body');
const btnAddRow = document.getElementById('btn-add-row');
const btnSaveDb = document.getElementById('btn-save-db');
const commitForm = document.getElementById('commit-form');

const logsDataInput = document.getElementById('logs-data-input');
const fileNameInput = document.getElementById('file-name-input');
const rawOcrTextInput = document.getElementById('raw-ocr-text-input');
const extractedDataInput = document.getElementById('extracted-data-input');

const timesheetMetrics = document.getElementById('timesheet-metrics');
const regHoursSummary = document.getElementById('reg-hours-summary');
const otHoursSummary = document.getElementById('ot-hours-summary');
const totalHoursSummary = document.getElementById('total-hours-summary');
const aiValidationWarnings = document.getElementById('ai-validation-warnings');
const warningList = document.getElementById('warning-list');
const exportActions = document.getElementById('export-actions');

let originalImageSrc = '';
let currentRotation = 0; // 0, 90, 180, 270
let uploadedFileName = 'scan_timesheet.jpg';
let rawTextResult = '';
let jsonResultStr = '';

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        uploadedFileName = files[0].name;
        handleFile(files[0]);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        uploadedFileName = e.target.files[0].name;
        handleFile(e.target.files[0]);
    }
});

function handleFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        originalImageSrc = e.target.result;
        currentRotation = 0;
        
        applyFiltersAndRotation();
        imagePreview.style.display = 'inline-block';
        editorControls.classList.replace('d-none', 'd-flex');
    }
    reader.readAsDataURL(file);
}

btnRotateLeft.addEventListener('click', () => {
    currentRotation = (currentRotation - 90 + 360) % 360;
    applyFiltersAndRotation();
});

btnRotateRight.addEventListener('click', () => {
    currentRotation = (currentRotation + 90) % 360;
    applyFiltersAndRotation();
});

enableFilter.addEventListener('change', () => applyFiltersAndRotation());

thresholdSlider.addEventListener('input', (e) => {
    thresholdVal.innerText = e.target.value;
    applyFiltersAndRotation();
});

function applyFiltersAndRotation() {
    if (!originalImageSrc) return;
    
    // PDF rendering bypasses canvas filter preview direct rendering
    if (originalImageSrc.startsWith('data:application/pdf')) {
        imagePreview.style.display = 'none';
        statusText.classList.remove('d-none');
        statusText.innerText = 'PDF document loaded ready for OCR execution.';
        return;
    }
    
    const img = new Image();
    img.onload = function() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        if (currentRotation === 90 || currentRotation === 270) {
            canvas.width = img.height;
            canvas.height = img.width;
        } else {
            canvas.width = img.width;
            canvas.height = img.height;
        }
        
        // Translate & Rotate Canvas
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate((currentRotation * Math.PI) / 180);
        ctx.drawImage(img, -img.width / 2, -img.height / 2);
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        
        // Grayscale & Binarization Threshold Filter
        if (enableFilter.checked) {
            const threshold = parseInt(thresholdSlider.value);
            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imgData.data;
            for (let i = 0; i < data.length; i += 4) {
                const r = data[i];
                const g = data[i+1];
                const b = data[i+2];
                const v = 0.2126 * r + 0.7152 * g + 0.0722 * b;
                const binary = v < threshold ? 0 : 255;
                data[i] = binary;
                data[i+1] = binary;
                data[i+2] = binary;
            }
            ctx.putImageData(imgData, 0, 0);
        }
        
        imagePreview.src = canvas.toDataURL('image/jpeg');
    }
    img.src = originalImageSrc;
}

btnOcrRun.addEventListener('click', () => runOCR());

function drawBoundingBoxes(boxes) {
    if (originalImageSrc.startsWith('data:application/pdf')) return;
    
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    img.onload = function() {
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0);
        
        ctx.strokeStyle = '#4b2308'; // brown branding color
        ctx.lineWidth = 2;
        ctx.font = '10px Courier New';
        ctx.fillStyle = '#4b2308';
        
        boxes.forEach(b => {
            const box = b.box;
            if (box && box.length === 4) {
                ctx.beginPath();
                ctx.moveTo(box[0][0], box[0][1]);
                ctx.lineTo(box[1][0], box[1][1]);
                ctx.lineTo(box[2][0], box[2][1]);
                ctx.lineTo(box[3][0], box[3][1]);
                ctx.closePath();
                ctx.stroke();
            }
        });
        imagePreview.src = canvas.toDataURL('image/jpeg');
    };
    img.src = originalImageSrc;
}

function runOCR() {
    progressContainer.classList.remove('d-none');
    statusText.classList.remove('d-none');
    statusText.innerText = 'Pre-processing document alignment...';
    progressBar.style.width = '10%';
    progressBar.innerText = '10%';
    
    let loadingPct = 10;
    const progressInterval = setInterval(() => {
        if (loadingPct < 90) {
            loadingPct += 10;
            progressBar.style.width = loadingPct + '%';
            progressBar.innerText = loadingPct + '%';
            if (loadingPct === 40) statusText.innerText = 'Running PP-OCRv4 Character Extraction...';
            if (loadingPct === 70) statusText.innerText = 'NVIDIA Llama-3.1 AI validation executing...';
        }
    }, 1200);

    const formData = new FormData();
    formData.append('action', 'run_paddle_ocr');
    // For PDFs, send original file stream, otherwise send enhanced canvas image
    if (originalImageSrc.startsWith('data:application/pdf')) {
        formData.append('image_data', originalImageSrc);
    } else {
        formData.append('image_data', imagePreview.src);
    }
    
    fetch('ocr_scanner.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressContainer.classList.add('d-none');
        statusText.classList.add('d-none');
        
        if (data.error) {
            extractedText.value = "PaddleOCR Processing Error: " + data.error;
            if (data.traceback) console.error(data.traceback);
            return;
        }
        
        rawTextResult = data.raw_text || '';
        jsonResultStr = JSON.stringify(data);
        extractedText.value = rawTextResult;
        
        // Draw overlay text bounding boxes
        if (data.boxes) {
            drawBoundingBoxes(data.boxes);
        }
        
        // Populate inputs
        if (data.employee_id) {
            employeeSelect.value = data.employee_id;
        }
        if (data.week_ending) {
            weekEndingVal.value = data.week_ending;
        }
        
        // Populate editable grid
        populateGrid(data.logs || []);
        
        // Populate summaries
        regHoursSummary.innerText = parseFloat(data.regular_hours || 0).toFixed(2);
        otHoursSummary.innerText = parseFloat(data.overtime_hours || 0).toFixed(2);
        totalHoursSummary.innerText = parseFloat(data.total_hours || 0).toFixed(2);
        timesheetMetrics.classList.remove('d-none');
        
        // Populate warnings
        warningList.innerHTML = '';
        if (data.errors && data.errors.length > 0) {
            data.errors.forEach(e => {
                const li = document.createElement('li');
                li.innerText = e;
                warningList.appendChild(li);
            });
            aiValidationWarnings.classList.remove('d-none');
        } else {
            aiValidationWarnings.classList.add('d-none');
        }
        
        btnSaveDb.classList.remove('d-none');
        exportActions.classList.remove('d-none');
    })
    .catch(err => {
        clearInterval(progressInterval);
        progressContainer.classList.add('d-none');
        statusText.innerText = 'Analysis failed: ' + err;
        console.error(err);
    });
}

function populateGrid(logs) {
    logsGridBody.innerHTML = '';
    if (logs.length === 0) {
        logsGridBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">No logs parsed.</td></tr>`;
        return;
    }
    
    logs.forEach((log, idx) => {
        addRowToGrid(log.date || '', log.day || '', log.clock_in || '', log.clock_out || '', log.daily_hours || 0);
    });
}

function addRowToGrid(date = '', day = '', clockIn = '', clockOut = '', hours = 0) {
    // Remove empty placeholder row if exists
    if (logsGridBody.children.length === 1 && logsGridBody.children[0].cells.length === 1) {
        logsGridBody.innerHTML = '';
    }
    
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="date" class="grid-date" value="${date}" onchange="recalculateTotals()"></td>
        <td><input type="text" class="grid-day" value="${day}"></td>
        <td><input type="text" class="grid-in" value="${clockIn}" placeholder="e.g. 09:00 AM" onchange="recalculateRowHours(this)"></td>
        <td><input type="text" class="grid-out" value="${clockOut}" placeholder="e.g. 05:00 PM" onchange="recalculateRowHours(this)"></td>
        <td><input type="number" step="0.01" class="grid-hours" value="${parseFloat(hours).toFixed(2)}" onchange="recalculateTotals()"></td>
        <td class="text-center">
            <button type="button" class="btn btn-link text-danger p-0 m-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    logsGridBody.appendChild(tr);
    recalculateTotals();
}

btnAddRow.addEventListener('click', () => {
    const today = new Date().toISOString().split('T')[0];
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const currentDay = days[new Date().getDay()];
    addRowToGrid(today, currentDay, '09:00 AM', '05:00 PM', 8.00);
});

function removeRow(btn) {
    btn.closest('tr').remove();
    recalculateTotals();
    if (logsGridBody.children.length === 0) {
        logsGridBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">No logs. Click "Add Row" to start.</td></tr>`;
    }
}

function recalculateRowHours(input) {
    const tr = input.closest('tr');
    const inVal = tr.querySelector('.grid-in').value;
    const outVal = tr.querySelector('.grid-out').value;
    
    if (inVal && outVal) {
        const timeIn = parseTimeString(inVal);
        const timeOut = parseTimeString(outVal);
        if (timeIn && timeOut) {
            let diff = (timeOut - timeIn) / (1000 * 60 * 60);
            if (diff < 0) diff += 24; // overnight shift handling
            tr.querySelector('.grid-hours').value = diff.toFixed(2);
        }
    }
    recalculateTotals();
}

function parseTimeString(timeStr) {
    const match = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/i);
    if (!match) {
        // try 24 hour parsing
        const match24 = timeStr.match(/^(\d{1,2}):(\d{2})$/);
        if (match24) {
            const d = new Date();
            d.setHours(parseInt(match24[1]), parseInt(match24[2]), 0, 0);
            return d;
        }
        return null;
    }
    
    let hours = parseInt(match[1]);
    const minutes = parseInt(match[2]);
    const ampm = match[3] ? match[3].toUpperCase() : null;
    
    if (ampm === 'PM' && hours < 12) hours += 12;
    if (ampm === 'AM' && hours === 12) hours = 0;
    
    const d = new Date();
    d.setHours(hours, minutes, 0, 0);
    return d;
}

function recalculateTotals() {
    let total = 0;
    const rows = logsGridBody.querySelectorAll('tr');
    rows.forEach(tr => {
        const hrsInput = tr.querySelector('.grid-hours');
        if (hrsInput) {
            total += parseFloat(hrsInput.value || 0);
        }
    });
    
    let reg = total > 40 ? 40 : total;
    let ot = total > 40 ? total - 40 : 0;
    
    regHoursSummary.innerText = reg.toFixed(2);
    otHoursSummary.innerText = ot.toFixed(2);
    totalHoursSummary.innerText = total.toFixed(2);
}

commitForm.addEventListener('submit', (e) => {
    const rows = logsGridBody.querySelectorAll('tr');
    const logs = [];
    
    rows.forEach(tr => {
        const date = tr.querySelector('.grid-date')?.value;
        const day = tr.querySelector('.grid-day')?.value;
        const clock_in = tr.querySelector('.grid-in')?.value;
        const clock_out = tr.querySelector('.grid-out')?.value;
        const daily_hours = tr.querySelector('.grid-hours')?.value;
        
        if (date && clock_in && clock_out) {
            logs.push({ date, day, clock_in, clock_out, daily_hours });
        }
    });
    
    logsDataInput.value = JSON.stringify(logs);
    fileNameInput.value = uploadedFileName;
    rawOcrTextInput.value = rawTextResult;
    extractedDataInput.value = jsonResultStr;
});

// Grid Exports client side triggers
function exportGrid(format) {
    const employeeName = employeeSelect.options[employeeSelect.selectedIndex].text;
    const weekEnding = weekEndingVal.value;
    const rows = logsGridBody.querySelectorAll('tr');
    const data = [];
    
    rows.forEach(tr => {
        const date = tr.querySelector('.grid-date')?.value;
        const day = tr.querySelector('.grid-day')?.value;
        const clock_in = tr.querySelector('.grid-in')?.value;
        const clock_out = tr.querySelector('.grid-out')?.value;
        const hours = tr.querySelector('.grid-hours')?.value;
        if (date) data.push({ date, day, clock_in, clock_out, hours });
    });

    if (format === 'json') {
        const jsonStr = JSON.stringify({ employee: employeeName, week_ending: weekEnding, logs: data }, null, 2);
        downloadFile(jsonStr, 'application/json', `timesheet_${employeeName.replace(/\s+/g, '_')}.json`);
    } else if (format === 'csv' || format === 'excel') {
        let csv = 'Date,Day,Clock In,Clock Out,Hours Worked\r\n';
        data.forEach(d => {
            csv += `${d.date},${d.day},${d.clock_in},${d.clock_out},${d.hours}\r\n`;
        });
        const mime = format === 'csv' ? 'text/csv' : 'application/vnd.ms-excel';
        const ext = format === 'csv' ? 'csv' : 'xls';
        downloadFile(csv, mime, `timesheet_${employeeName.replace(/\s+/g, '_')}.${ext}`);
    } else if (format === 'pdf') {
        window.print();
    }
}

function downloadFile(content, mimeType, filename) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>
