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

$error = '';
$success = '';

// 1. Handle Single Manual Attendance Record Insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_single_attendance') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = "CSRF verification failed.";
    } else {
        $employeeId = intval($_POST['employee_id'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        $inTime = trim($_POST['login_time'] ?? '');
        $outTime = trim($_POST['logout_time'] ?? '');
        $logType = trim($_POST['log_type'] ?? 'Regular');
        
        if (empty($employeeId) || empty($date) || empty($inTime) || empty($outTime)) {
            $error = "All fields are required.";
        } else {
            // Fetch employee's store and hourly rate
            $stmtEmp = $pdo->prepare("SELECT store_id, hourly_rate FROM employees WHERE id = ?");
            $stmtEmp->execute([$employeeId]);
            $empData = $stmtEmp->fetch();
            
            if (!$empData) {
                $error = "Employee not found.";
            } else {
                $empStoreId = $empData['store_id'];
                $hourlyRate = floatval($empData['hourly_rate']);
                
                // Get store shift guidelines
                $stmtStore = $pdo->prepare("SELECT shift_start, shift_end FROM stores WHERE id = ?");
                $stmtStore->execute([$empStoreId]);
                $storeData = $stmtStore->fetch();
                $shiftStart = $storeData['shift_start'] ?? '09:00:00';
                $shiftEnd = $storeData['shift_end'] ?? '18:00:00';
                
                $loginDateTime = $date . ' ' . date('H:i:s', strtotime($inTime));
                $logoutDateTime = $date . ' ' . date('H:i:s', strtotime($outTime));
                
                $metrics = calculate_attendance_metrics($loginDateTime, $logoutDateTime, $date, 0, $shiftStart, $shiftEnd);
                
                $pdo->beginTransaction();
                try {
                    // Check duplicate
                    $stmtCheck = $pdo->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND date = ?");
                    $stmtCheck->execute([$employeeId, $date]);
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
                            VALUES (?, ?, ?, ?, ?, 'Completed', ?, ?, ?, ?, ?, 1)
                        ");
                        $stmtInsert->execute([
                            $employeeId, 
                            $empStoreId, 
                            $date, 
                            $loginDateTime, 
                            $logoutDateTime, 
                            $logType,
                            $metrics['total_hours'], 
                            $metrics['overtime'],
                            $metrics['is_late'],
                            $metrics['is_early_departure']
                        ]);
                    }
                    
                    // Recalculate weekly aggregates
                    $weekEnding = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
                    
                    $stmtSum = $pdo->prepare("
                        SELECT SUM(calculated_hours) as tot_hrs, SUM(calculated_overtime) as tot_ot 
                        FROM attendance_logs 
                        WHERE employee_id = ? AND date >= DATE_SUB(?, INTERVAL 6 DAY) AND date <= ?
                    ");
                    $stmtSum->execute([$employeeId, $weekEnding, $weekEnding]);
                    $sumData = $stmtSum->fetch();
                    $totHrs = floatval($sumData['tot_hrs'] ?? 0.0);
                    $totOt = floatval($sumData['tot_ot'] ?? 0.0);
                    
                    if ($totHrs > 40.00) {
                        $reg = 40.00;
                        $ot = $totHrs - 40.00;
                    } else {
                        $reg = $totHrs;
                        $ot = 0.00;
                    }
                    
                    $stmtTS = $pdo->prepare("SELECT id FROM employee_timesheets WHERE employee_id = ? AND week_ending = ?");
                    $stmtTS->execute([$employeeId, $weekEnding]);
                    $tsId = $stmtTS->fetchColumn();
                    if ($tsId) {
                        $pdo->prepare("UPDATE employee_timesheets SET regular_hours = ?, overtime_hours = ?, total_hours = ?, status = 'Approved' WHERE id = ?")->execute([$reg, $ot, $totHrs, $tsId]);
                    } else {
                        $pdo->prepare("INSERT INTO employee_timesheets (employee_id, week_ending, regular_hours, overtime_hours, total_hours, status) VALUES (?, ?, ?, ?, ?, 'Approved')")->execute([$employeeId, $weekEnding, $reg, $ot, $totHrs]);
                    }
                    
                    $periodStart = date('Y-m-d', strtotime($weekEnding . ' - 13 days'));
                    $totalPay = ($reg * $hourlyRate) + ($ot * $hourlyRate * 1.5);
                    
                    $stmtPS = $pdo->prepare("SELECT id FROM payroll_summary WHERE employee_id = ? AND period_end = ?");
                    $stmtPS->execute([$employeeId, $weekEnding]);
                    $psId = $stmtPS->fetchColumn();
                    if ($psId) {
                        $pdo->prepare("UPDATE payroll_summary SET period_start = ?, total_hours = ?, total_pay = ?, calculated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$periodStart, $totHrs, $totalPay, $psId]);
                    } else {
                        $pdo->prepare("INSERT INTO payroll_summary (employee_id, period_start, period_end, total_hours, total_pay) VALUES (?, ?, ?, ?, ?)")->execute([$employeeId, $periodStart, $weekEnding, $totHrs, $totalPay]);
                    }
                    
                    $pdo->commit();
                    $success = "Manual attendance record successfully added and ledger balances updated.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Transaction failed: " . $e->getMessage();
                }
            }
        }
    }
}

// 2. Handle REST Excel Parsing API Forwarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'parse_excel_upload') {
    header('Content-Type: application/json');
    $fileData = $_POST['file_data'] ?? '';
    
    if (empty($fileData)) {
        echo json_encode(["success" => false, "error" => "No file data received."]);
        exit;
    }
    
    // Fetch all active employees
    if ($role === 'Super Admin') {
        $stmtEmps = $pdo->query("SELECT id, name FROM employees WHERE deleted_at IS NULL ORDER BY name ASC");
    } else {
        $stmtEmps = $pdo->prepare("SELECT id, name FROM employees WHERE store_id = ? AND deleted_at IS NULL ORDER BY name ASC");
        $stmtEmps->execute([$storeId]);
    }
    $dbEmployees = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);
    
    $postData = [
        'file_data' => $fileData,
        'employees' => $dbEmployees
    ];
    
    // Call FastAPI service
    $ch = curl_init('http://127.0.0.1:5000/parse_excel');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        echo json_encode(["success" => false, "error" => "Excel parser service offline or failed (HTTP code $httpCode)."]);
        exit;
    }
    
    echo $response;
    exit;
}

// 3. Handle Commit of Uploaded Excel Log Grid to Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'commit_excel_grid') {
    $logsJson = trim($_POST['logs_data'] ?? '');
    $logs = json_decode($logsJson, true);
    
    if (empty($logs)) {
        $error = "No attendance logs provided to commit.";
    } else {
        $pdo->beginTransaction();
        try {
            $impactedEmployees = [];
            
            foreach ($logs as $log) {
                $employeeId = intval($log['employee_id'] ?? 0);
                $logDate = trim($log['date'] ?? '');
                $inTime = trim($log['clock_in'] ?? '');
                $outTime = trim($log['clock_out'] ?? '');
                $hours = floatval($log['daily_hours'] ?? 0.0);
                
                if (empty($employeeId) || empty($logDate) || empty($inTime) || empty($outTime)) {
                    continue;
                }
                
                $stmtEmp = $pdo->prepare("SELECT store_id, hourly_rate FROM employees WHERE id = ?");
                $stmtEmp->execute([$employeeId]);
                $empData = $stmtEmp->fetch();
                if (!$empData) continue;
                
                $empStoreId = $empData['store_id'];
                $hourlyRate = floatval($empData['hourly_rate']);
                
                $stmtStore = $pdo->prepare("SELECT shift_start, shift_end FROM stores WHERE id = ?");
                $stmtStore->execute([$empStoreId]);
                $storeData = $stmtStore->fetch();
                $shiftStart = $storeData['shift_start'] ?? '09:00:00';
                $shiftEnd = $storeData['shift_end'] ?? '18:00:00';
                
                $loginDateTime = $logDate . ' ' . date('H:i:s', strtotime($inTime));
                $logoutDateTime = $logDate . ' ' . date('H:i:s', strtotime($outTime));
                
                $metrics = calculate_attendance_metrics($loginDateTime, $logoutDateTime, $logDate, 0, $shiftStart, $shiftEnd);
                
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
                
                $weekEnding = date('Y-m-d', strtotime('sunday this week', strtotime($logDate)));
                $impactedEmployees[$employeeId][$weekEnding] = true;
            }
            
            foreach ($impactedEmployees as $empId => $weeks) {
                $stmtRate = $pdo->prepare("SELECT hourly_rate FROM employees WHERE id = ?");
                $stmtRate->execute([$empId]);
                $hourlyRate = floatval($stmtRate->fetchColumn() ?: 0.0);
                
                foreach (array_keys($weeks) as $weekEnding) {
                    $stmtSum = $pdo->prepare("
                        SELECT SUM(calculated_hours) as tot_hrs, SUM(calculated_overtime) as tot_ot 
                        FROM attendance_logs 
                        WHERE employee_id = ? AND date >= DATE_SUB(?, INTERVAL 6 DAY) AND date <= ?
                    ");
                    $stmtSum->execute([$empId, $weekEnding, $weekEnding]);
                    $sumData = $stmtSum->fetch();
                    $totHrs = floatval($sumData['tot_hrs'] ?? 0.0);
                    $totOt = floatval($sumData['tot_ot'] ?? 0.0);
                    
                    if ($totHrs > 40.00) {
                        $reg = 40.00;
                        $ot = $totHrs - 40.00;
                    } else {
                        $reg = $totHrs;
                        $ot = 0.00;
                    }
                    
                    $stmtTS = $pdo->prepare("SELECT id FROM employee_timesheets WHERE employee_id = ? AND week_ending = ?");
                    $stmtTS->execute([$empId, $weekEnding]);
                    $tsId = $stmtTS->fetchColumn();
                    if ($tsId) {
                        $pdo->prepare("UPDATE employee_timesheets SET regular_hours = ?, overtime_hours = ?, total_hours = ?, status = 'Approved' WHERE id = ?")->execute([$reg, $ot, $totHrs, $tsId]);
                    } else {
                        $pdo->prepare("INSERT INTO employee_timesheets (employee_id, week_ending, regular_hours, overtime_hours, total_hours, status) VALUES (?, ?, ?, ?, ?, 'Approved')")->execute([$empId, $weekEnding, $reg, $ot, $totHrs]);
                    }
                    
                    $periodStart = date('Y-m-d', strtotime($weekEnding . ' - 13 days'));
                    $totalPay = ($reg * $hourlyRate) + ($ot * $hourlyRate * 1.5);
                    
                    $stmtPS = $pdo->prepare("SELECT id FROM payroll_summary WHERE employee_id = ? AND period_end = ?");
                    $stmtPS->execute([$empId, $weekEnding]);
                    $psId = $stmtPS->fetchColumn();
                    if ($psId) {
                        $pdo->prepare("UPDATE payroll_summary SET period_start = ?, total_hours = ?, total_pay = ?, calculated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$periodStart, $totHrs, $totalPay, $psId]);
                    } else {
                        $pdo->prepare("INSERT INTO payroll_summary (employee_id, period_start, period_end, total_hours, total_pay) VALUES (?, ?, ?, ?, ?)")->execute([$empId, $periodStart, $weekEnding, $totHrs, $totalPay]);
                    }
                }
            }
            
            $pdo->commit();
            $success = "Excel attendance roster parsed and saved successfully to database tables.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Transaction failed: " . $e->getMessage();
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
    <title>Manual Attendance Registry — Artée Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
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
        .table-editable input, .table-editable select {
            border: none;
            background: transparent;
            padding: 2px 5px;
            width: 100%;
        }
        .table-editable input:focus, .table-editable select:focus {
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
                    <a class="sidebar-link" href="ocr_scanner.php">
                        <i class="bi bi-camera-fill"></i>
                        <span>OCR Scanner</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="manual_attendance.php">
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
                    <i class="bi bi-pencil-square me-2 text-primary"></i>Manual Attendance &amp; Excel Import
                </span>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <!-- Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-1.5"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1.5"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Single Manual Entry -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                        <h5 class="fw-bold text-primary-dark mb-3" style="font-size:0.95rem;">Manual Check-In Registry</h5>
                        <form method="POST" action="manual_attendance.php" class="space-y-3">
                            <input type="hidden" name="action" value="add_single_attendance">
                            <?php csrf_input(); ?>
                            
                            <div class="mb-2.5">
                                <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Employee Name</label>
                                <select name="employee_id" class="form-select form-select-sm" required>
                                    <option value="">Select Employee...</option>
                                    <?php foreach ($employeesList as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>"><?php echo e($emp['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-2.5">
                                <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Shift Date</label>
                                <input type="date" name="date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="row g-2 mb-2.5">
                                <div class="col-6">
                                    <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Clock-In Time</label>
                                    <input type="text" name="login_time" class="form-control form-control-sm" placeholder="e.g. 09:00 AM" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Clock-Out Time</label>
                                    <input type="text" name="logout_time" class="form-control form-control-sm" placeholder="e.g. 05:00 PM" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Log Type</label>
                                <select name="log_type" class="form-select form-select-sm">
                                    <option value="Regular">Regular Shift</option>
                                    <option value="Half Day">Half Day</option>
                                    <option value="Holiday">Holiday Shift</option>
                                    <option value="Field Work">Field Work</option>
                                    <option value="Paid Leave">Paid Leave</option>
                                    <option value="Unpaid Leave">Unpaid Leave</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-2 btn-sm" style="border-radius:8px;">
                                <i class="bi bi-plus-circle me-1.5"></i>Save Attendance Log
                            </button>
                        </form>
                    </div>

                    <!-- Excel Upload Dropzone -->
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                        <h5 class="fw-bold text-primary-dark mb-3" style="font-size:0.95rem;">Excel / CSV Timesheet Import</h5>
                        <div class="drag-zone mb-3" id="drop-zone">
                            <i class="bi bi-file-earmark-excel-fill text-muted" style="font-size: 2.2rem;"></i>
                            <p class="mt-2 mb-1 fw-semibold" style="font-size:0.85rem;">Drag &amp; Drop or Click to Upload</p>
                            <span class="text-muted" style="font-size: 0.7rem;">Accepts .xlsx, .xls, .csv templates</span>
                            <input type="file" id="file-input" accept=".xlsx,.xls,.csv" class="d-none">
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress mb-3 d-none" id="progress-container" style="height: 18px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                        <div class="text-center text-muted mb-3 font-mono d-none" id="status-text" style="font-size:0.7rem;">Processing...</div>
                    </div>
                </div>

                <!-- Excel Preview Roster Grid -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 12px;">
                        <h5 class="fw-bold text-primary-dark mb-3" style="font-size:0.95rem;">Excel Import Verification Grid</h5>
                        
                        <form method="POST" action="manual_attendance.php" id="commit-form">
                            <input type="hidden" name="action" value="commit_excel_grid">
                            <input type="hidden" name="logs_data" id="logs-data-input">
                            
                            <!-- Validation Warnings -->
                            <div id="import-warnings" class="alert alert-warning py-2 px-3 mb-3 d-none" style="font-size:0.75rem;">
                                <h6 class="fw-bold mb-1" style="font-size:0.78rem;"><i class="bi bi-exclamation-triangle-fill me-1.5"></i>Mapping Warnings</h6>
                                <ul class="mb-0 ps-3" id="warning-list"></ul>
                            </div>

                            <div class="table-responsive border rounded mb-3" style="max-height: 420px;">
                                <table class="table table-sm table-bordered table-editable align-middle mb-0" style="font-size:0.78rem;">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th style="width: 32%;">Employee</th>
                                            <th style="width: 22%;">Date</th>
                                            <th style="width: 18%;">Clock In</th>
                                            <th style="width: 18%;">Clock Out</th>
                                            <th style="width: 10%;">Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody id="logs-grid-body">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">No spreadsheets processed. Please upload an Excel/CSV timesheet to verify.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-2 d-none btn-sm" id="btn-save-db" style="border-radius:8px;">
                                <i class="bi bi-cloud-arrow-up-fill me-1.5"></i>Approve &amp; Save Excel Logs
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const progressBar = document.getElementById('progress-bar');
const progressContainer = document.getElementById('progress-container');
const statusText = document.getElementById('status-text');
const logsGridBody = document.getElementById('logs-grid-body');
const btnSaveDb = document.getElementById('btn-save-db');
const commitForm = document.getElementById('commit-form');
const logsDataInput = document.getElementById('logs-data-input');
const importWarnings = document.getElementById('import-warnings');
const warningList = document.getElementById('warning-list');

// List of employees for dropdown options in editable grid
const employees = <?php echo json_encode($employeesList); ?>;

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
        handleExcelFile(files[0]);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleExcelFile(e.target.files[0]);
    }
});

function handleExcelFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const base64Data = e.target.result;
        uploadAndParse(base64Data);
    };
    reader.readAsDataURL(file);
}

function uploadAndParse(base64Data) {
    progressContainer.classList.remove('d-none');
    statusText.classList.remove('d-none');
    statusText.innerText = 'Uploading file to local engine...';
    progressBar.style.width = '30%';
    progressBar.innerText = '30%';
    
    let loadingPct = 30;
    const progressInterval = setInterval(() => {
        if (loadingPct < 85) {
            loadingPct += 15;
            progressBar.style.width = loadingPct + '%';
            progressBar.innerText = loadingPct + '%';
            statusText.innerText = 'Pandas parsing columns and mapping employees...';
        }
    }, 400);

    const formData = new FormData();
    formData.append('action', 'parse_excel_upload');
    formData.append('file_data', base64Data);
    
    fetch('manual_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressContainer.classList.add('d-none');
        statusText.classList.add('d-none');
        
        if (!data.success) {
            alert("Error parsing file: " + data.error);
            return;
        }
        
        // Populate Roster Grid
        populateRosterGrid(data.logs || []);
        
        // Show Warnings
        warningList.innerHTML = '';
        if (data.errors && data.errors.length > 0) {
            data.errors.forEach(err => {
                const li = document.createElement('li');
                li.innerText = err;
                warningList.appendChild(li);
            });
            importWarnings.classList.remove('d-none');
        } else {
            importWarnings.classList.add('d-none');
        }
        
        btnSaveDb.classList.remove('d-none');
    })
    .catch(err => {
        clearInterval(progressInterval);
        progressContainer.classList.add('d-none');
        statusText.innerText = 'Processing failed: ' + err;
        console.error(err);
    });
}

function populateRosterGrid(logs) {
    logsGridBody.innerHTML = '';
    if (logs.length === 0) {
        logsGridBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">No logs parsed.</td></tr>`;
        return;
    }
    
    logs.forEach((log) => {
        const tr = document.createElement('tr');
        
        // Create employee selection dropdown
        let selectHtml = `<select class="grid-employee">`;
        employees.forEach(emp => {
            const selected = emp.id == log.employee_id ? 'selected' : '';
            selectHtml += `<option value="${emp.id}" ${selected}>${emp.name}</option>`;
        });
        selectHtml += `</select>`;
        
        tr.innerHTML = `
            <td>${selectHtml}</td>
            <td><input type="date" class="grid-date" value="${log.date}"></td>
            <td><input type="text" class="grid-in" value="${log.clock_in}"></td>
            <td><input type="text" class="grid-out" value="${log.clock_out}"></td>
            <td><input type="number" step="0.01" class="grid-hours" value="${log.daily_hours}"></td>
        `;
        logsGridBody.appendChild(tr);
    });
}

commitForm.addEventListener('submit', (e) => {
    const rows = logsGridBody.querySelectorAll('tr');
    const logs = [];
    
    rows.forEach(tr => {
        const employee_id = tr.querySelector('.grid-employee')?.value;
        const date = tr.querySelector('.grid-date')?.value;
        const clock_in = tr.querySelector('.grid-in')?.value;
        const clock_out = tr.querySelector('.grid-out')?.value;
        const daily_hours = tr.querySelector('.grid-hours')?.value;
        
        if (employee_id && date && clock_in && clock_out) {
            logs.push({ employee_id, date, clock_in, clock_out, daily_hours });
        }
    });
    
    logsDataInput.value = JSON.stringify(logs);
});
</script>
</body>
</html>
