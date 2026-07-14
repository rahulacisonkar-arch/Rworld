<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
redirect_unauthenticated();

// Run shift auto-closing logic
process_auto_close_shifts($pdo);

if ($_SESSION['role'] !== 'Store User') {
    header("Location: admin_dashboard.php");
    exit;
}

$storeId = $_SESSION['store_id'];
$storeName = $_SESSION['store_name'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

$error = '';
$success = '';

// Handle Add Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = "CSRF verification failed.";
    } else {
        $name = trim($_POST['employee_name'] ?? '');
        $email = trim($_POST['employee_email'] ?? '');
        $phone = trim($_POST['employee_phone'] ?? '');
        $designation = trim($_POST['employee_designation'] ?? '');

        if (empty($name)) {
            $error = "Employee name is required.";
        } elseif (empty($designation)) {
            $error = "Designation/Role is required.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address format.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO employees (store_id, name, email, phone, designation) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$storeId, $name, $email, $phone, $designation]);
                $success = "Employee added successfully.";
            } catch (Exception $e) {
                $error = "Failed to add employee: " . $e->getMessage();
            }
        }
    }
}

// Handle Check-in / Check-out actions
if (isset($_GET['action']) && isset($_GET['employee_id'])) {
    $action = $_GET['action'];
    $employeeId = intval($_GET['employee_id']);

    // Verify employee belongs to this store
    $stmtVerify = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND store_id = ?");
    $stmtVerify->execute([$employeeId, $storeId]);
    $employeeExists = $stmtVerify->fetchColumn();

    if ($employeeExists) {
        if ($action === 'checkin') {
            // Check if already checked in or checked out today
            $stmtCheckToday = $pdo->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND date = ?");
            $stmtCheckToday->execute([$employeeId, date('Y-m-d')]);
            if ($stmtCheckToday->fetchColumn()) {
                $_SESSION['action_error'] = "This employee has already checked in or completed their shift for today.";
            } else {
                $type = isset($_GET['type']) ? trim($_GET['type']) : 'Regular';
                
                if ($type === 'Paid Leave' || $type === 'Unpaid Leave') {
                    $stmtIn = $pdo->prepare("INSERT INTO attendance_logs (employee_id, store_id, login_time, logout_time, date, status, log_type, calculated_hours, manager_approved) VALUES (?, ?, ?, ?, ?, 'Completed', ?, 0.00, 1)");
                    $stmtIn->execute([$employeeId, $storeId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), date('Y-m-d'), $type]);
                    $_SESSION['action_success'] = "Leave registry recorded successfully.";
                } else {
                    $stmtIn = $pdo->prepare("INSERT INTO attendance_logs (employee_id, store_id, login_time, date, status, log_type) VALUES (?, ?, ?, ?, 'Checked In', ?)");
                    $stmtIn->execute([$employeeId, $storeId, date('Y-m-d H:i:s'), date('Y-m-d'), $type]);
                    $_SESSION['action_success'] = "Employee checked in successfully for $type Shift.";
                }
            }
        } elseif ($action === 'checkout') {
            $stmtGetLog = $pdo->prepare("SELECT l.id, l.status, l.login_time, l.date, s.shift_start, s.shift_end FROM attendance_logs l JOIN stores s ON l.store_id = s.id WHERE l.employee_id = ? AND l.store_id = ? AND l.logout_time IS NULL");
            $stmtGetLog->execute([$employeeId, $storeId]);
            $logRow = $stmtGetLog->fetch();
            if ($logRow) {
                $logId = $logRow['id'];
                $logoutTime = date('Y-m-d H:i:s');
                if ($logRow['status'] === 'On Break') {
                    $stmtEndBreak = $pdo->prepare("UPDATE attendance_breaks SET break_end = ? WHERE log_id = ? AND break_end IS NULL");
                    $stmtEndBreak->execute([$logoutTime, $logId]);
                }
                
                // Fetch breaks
                $stmtBreaks = $pdo->prepare("SELECT * FROM attendance_breaks WHERE log_id = ?");
                $stmtBreaks->execute([$logId]);
                $breaks = $stmtBreaks->fetchAll();
                $totalBreakSecs = 0;
                foreach ($breaks as $b) {
                    if ($b['break_end']) {
                        $totalBreakSecs += strtotime($b['break_end']) - strtotime($b['break_start']);
                    }
                }
                
                // Calculate metrics
                $metrics = calculate_attendance_metrics($logRow['login_time'], $logoutTime, $logRow['date'], $totalBreakSecs, $logRow['shift_start'], $logRow['shift_end']);
                
                $stmtOut = $pdo->prepare("
                    UPDATE attendance_logs 
                    SET logout_time = ?, 
                        status = 'Completed',
                        is_late = ?,
                        is_early_departure = ?,
                        calculated_hours = ?,
                        calculated_overtime = ?
                    WHERE id = ?
                ");
                $stmtOut->execute([$logoutTime, $metrics['is_late'], $metrics['is_early_departure'], $metrics['total_hours'], $metrics['overtime'], $logId]);
                $_SESSION['action_success'] = "Employee checked out successfully.";
            }
        } elseif ($action === 'breakout') {
            $stmtGetLog = $pdo->prepare("SELECT id, status FROM attendance_logs WHERE employee_id = ? AND store_id = ? AND logout_time IS NULL");
            $stmtGetLog->execute([$employeeId, $storeId]);
            $logRow = $stmtGetLog->fetch();
            if ($logRow && $logRow['status'] !== 'On Break') {
                $logId = $logRow['id'];
                $stmtBreak = $pdo->prepare("INSERT INTO attendance_breaks (log_id, break_start) VALUES (?, ?)");
                $stmtBreak->execute([$logId, date('Y-m-d H:i:s')]);
                $stmtLogUpdate = $pdo->prepare("UPDATE attendance_logs SET status = 'On Break' WHERE id = ?");
                $stmtLogUpdate->execute([$logId]);
                $_SESSION['action_success'] = "Employee went on break successfully.";
            }
        } elseif ($action === 'breakin') {
            $stmtGetLog = $pdo->prepare("SELECT id, status FROM attendance_logs WHERE employee_id = ? AND store_id = ? AND logout_time IS NULL");
            $stmtGetLog->execute([$employeeId, $storeId]);
            $logRow = $stmtGetLog->fetch();
            if ($logRow && $logRow['status'] === 'On Break') {
                $logId = $logRow['id'];
                $stmtEndBreak = $pdo->prepare("UPDATE attendance_breaks SET break_end = ? WHERE log_id = ? AND break_end IS NULL");
                $stmtEndBreak->execute([date('Y-m-d H:i:s'), $logId]);
                $stmtLogUpdate = $pdo->prepare("UPDATE attendance_logs SET status = 'Checked In' WHERE id = ?");
                $stmtLogUpdate->execute([$logId]);
                $_SESSION['action_success'] = "Employee returned from break successfully.";
            }
        }
    }
    // Redirect to clear GET variables
    header("Location: dashboard.php");
    exit;
}

// Check session success/error messages after redirect
if (isset($_SESSION['action_error'])) {
    $error = $_SESSION['action_error'];
    unset($_SESSION['action_error']);
}
if (isset($_SESSION['action_success'])) {
    $success = $_SESSION['action_success'];
    unset($_SESSION['action_success']);
}

// Fetch Roster
$stmtRoster = $pdo->prepare("SELECT e.*, 
    l.login_time AS today_login,
    l.logout_time AS today_logout,
    l.status AS log_status,
    IF(l.id IS NOT NULL AND l.logout_time IS NULL, l.id, NULL) AS active_log_id,
    IF(l.id IS NOT NULL AND l.logout_time IS NOT NULL, 1, 0) AS completed_today
    FROM employees e 
    LEFT JOIN attendance_logs l ON l.employee_id = e.id 
         AND l.id = (SELECT MAX(id) FROM attendance_logs WHERE employee_id = e.id AND date = ?)
    WHERE e.store_id = ? AND e.status = 'Active' 
    ORDER BY e.name ASC");
$stmtRoster->execute([date('Y-m-d'), $storeId]);
$roster = $stmtRoster->fetchAll();

// Fetch Today's Logs
$stmtLogs = $pdo->prepare("SELECT l.*, e.name AS employee_name, e.designation 
    FROM attendance_logs l 
    JOIN employees e ON l.employee_id = e.id 
    WHERE l.store_id = ? AND l.date = CURDATE() 
    ORDER BY l.login_time DESC");
$stmtLogs->execute([$storeId]);
$todayLogs = $stmtLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Artée Attendance</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="css/style.css" rel="stylesheet">
    <style>
        /* Compact UI enhancements to fit components on a single screen page */
        body {
            font-size: 0.82rem !important;
            line-height: 1.35 !important;
        }
        #top-navbar {
            height: 48px !important;
            padding: 4px 16px !important;
        }
        .container-fluid {
            padding: 12px !important;
            margin-top: 5px !important;
        }
        .card {
            padding: 14px 18px !important;
        }
        .table th, .table td {
            padding: 5px 8px !important;
            font-size: 0.8rem !important;
        }
        .btn-sm {
            padding: 2.5px 8px !important;
            font-size: 0.72rem !important;
        }
        .badge {
            font-size: 0.65rem !important;
            padding: 3.5px 7px !important;
        }
        h3, h4 {
            font-size: 0.95rem !important;
        }
        .gap-3 {
            gap: 0.5rem !important;
        }
        .gap-4 {
            gap: 0.6rem !important;
        }
        .space-y-6 > * + * {
            margin-top: 0.6rem !important;
        }
        .mb-4 {
            margin-bottom: 0.6rem !important;
        }
        .alert {
            padding: 8px 12px !important;
            margin-bottom: 12px !important;
            font-size: 0.78rem !important;
        }
        .form-control, .form-select {
            padding: 4px 8px !important;
            font-size: 0.82rem !important;
        }
    </style>
</head>
<body>

<div id="app-container">

    <!-- ==========================================
         LEFT SIDEBAR
    ========================================== -->
    <aside class="sidebar-command" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-white" style="font-size: 1.4rem;"></i>
                <div>
                    <div class="sidebar-brand-text">ARTÉE ATTENDANCE</div>
                    <div class="sidebar-brand-sub">Portal</div>
                </div>
            </div>
        </div>
        <nav>
            <ul class="sidebar-menu">
                <li class="sidebar-section-title">Operations</li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="dashboard.php">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
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

    <!-- ==========================================
         MAIN CONTENT
    ========================================== -->
    <div id="main-content">
        
        <!-- Top Navbar -->
        <nav class="navbar-command" id="top-navbar">
            <div class="d-flex align-items-center">
                <h4 class="mb-0 text-primary-dark fw-bold" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;">Roster Management</h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-lg-flex flex-column align-items-end" style="line-height: 1.2;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-primary);" id="current-live-time">--:--:-- --</div>
                    <div style="font-size: 0.68rem; color: var(--text-muted);" id="current-live-date">Loading...</div>
                </div>
                <div class="dropdown">
                    <button class="d-flex align-items-center gap-2 border-0 bg-transparent" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 4px 0;">
                        <div style="width: 34px; height: 34px; background: var(--primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8rem;">
                            <?php echo strtoupper(substr($username, 0, 2)); ?>
                        </div>
                        <div class="d-none d-md-block text-start">
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><?php echo e($username); ?></div>
                            <div style="font-size: 0.68rem; color: var(--text-muted);"><?php echo e($storeName); ?></div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border mt-2">
                        <li>
                            <a class="dropdown-item py-2" href="logout.php">
                                <i class="bi bi-box-arrow-right text-danger me-2"></i>
                                Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid p-4" style="margin-top: 20px;">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 mb-4"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success border-0 mb-4"><?php echo e($success); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Roster & Actions -->
                <div class="col-lg-8 space-y-6">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                        <div class="border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
                            <h3 class="fw-bold mb-0 text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">Active Roster</h3>
                            <span class="badge bg-primary-light text-primary px-2 py-1 rounded" style="font-size: 0.72rem;"><?php echo count($roster); ?> Employees</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead>
                                    <tr class="text-secondary" style="font-size: 0.78rem; text-transform: uppercase; font-family: 'Outfit', sans-serif; letter-spacing: 0.5px;">
                                        <th>Employee Name</th>
                                        <th>Designation</th>
                                        <th>Login Time</th>
                                        <th>Logout Time</th>
                                        <th>Shift Status</th>
                                        <th class="text-end">Clock Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($roster)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted font-mono" style="font-size: 0.82rem;">No employees registered yet. Use the registration form to add employees.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($roster as $emp): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div style="width: 32px; height: 32px; background: var(--primary-light); border: 1px solid var(--border); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; font-size: 0.78rem; flex-shrink: 0;">
                                                            <?php echo strtoupper(substr($emp['name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold text-primary-dark" style="font-size: 0.88rem;"><?php echo e($emp['name']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-secondary fw-medium" style="font-size: 0.82rem;"><?php echo e($emp['designation'] ?: 'Staff Member'); ?></span>
                                                </td>
                                                <td class="font-mono text-success fw-semibold" style="font-size: 0.8rem;">
                                                    <?php echo $emp['today_login'] ? date('h:i A', strtotime($emp['today_login'])) : '<span class="text-muted fw-normal">—</span>'; ?>
                                                </td>
                                                <td class="font-mono text-danger fw-semibold" style="font-size: 0.8rem;">
                                                    <?php echo $emp['today_logout'] ? date('h:i A', strtotime($emp['today_logout'])) : '<span class="text-muted fw-normal">—</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($emp['active_log_id'] && $emp['log_status'] === 'On Break'): ?>
                                                        <span class="badge" style="background-color: #fff3cd; color: #856404; border: 1px solid rgba(133, 100, 4, 0.25); font-size: 0.72rem; padding: 5px 10px; font-weight: 600;">On Break</span>
                                                    <?php elseif ($emp['active_log_id']): ?>
                                                        <span class="badge" style="background-color: var(--success-light); color: var(--success); border: 1px solid rgba(40, 167, 69, 0.25); font-size: 0.72rem; padding: 5px 10px; font-weight: 600;">Checked In</span>
                                                    <?php elseif ($emp['completed_today'] > 0): ?>
                                                        <span class="badge" style="background-color: var(--border-light); color: var(--text-secondary); border: 1px solid var(--border); font-size: 0.72rem; padding: 5px 10px; font-weight: 600;">Shift Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-secondary border px-2.5 py-1.5" style="font-size: 0.72rem; font-weight: 500;">Off Duty</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                                        <?php if ($emp['active_log_id']): ?>
                                                            <?php if ($emp['log_status'] === 'On Break'): ?>
                                                                <a href="dashboard.php?action=breakin&employee_id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-warning text-dark px-3 font-semibold" style="border-radius: 6px; font-size: 0.78rem;">
                                                                    <i class="bi bi-play-circle me-1"></i>Return from Break
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="dashboard.php?action=breakout&employee_id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-outline-warning text-dark px-2 font-semibold" style="border-radius: 6px; font-size: 0.78rem; border-color: #ffc107;">
                                                                    <i class="bi bi-pause-circle me-1"></i>Start Break
                                                                </a>
                                                                <a href="dashboard.php?action=checkout&employee_id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-danger px-3 font-semibold" style="border-radius: 6px; font-size: 0.78rem; background: var(--danger); border-color: var(--danger);">
                                                                    <i class="bi bi-box-arrow-left me-1"></i>Clock Out
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php elseif ($emp['completed_today'] > 0): ?>
                                                            <button class="btn btn-sm btn-light px-3 font-semibold" disabled style="border-radius: 6px; font-size: 0.78rem; background: #e9ecef; border-color: #dee2e6; color: #6c757d;">
                                                                <i class="bi bi-check2-circle text-success me-1"></i>Finished Today
                                                            </button>
                                                        <?php else: ?>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-primary dropdown-toggle px-3 font-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 6px; font-size: 0.78rem; background: var(--primary); border-color: var(--primary);">
                                                                    <i class="bi bi-box-arrow-in-right me-1"></i>Clock Actions
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end shadow border" style="font-size: 0.8rem;">
                                                                    <li><a class="dropdown-item py-1.5" href="dashboard.php?action=checkin&employee_id=<?php echo $emp['id']; ?>&type=Regular"><i class="bi bi-person-fill text-primary me-2"></i>Regular Shift</a></li>
                                                                    <li><a class="dropdown-item py-1.5" href="dashboard.php?action=checkin&employee_id=<?php echo $emp['id']; ?>&type=Field Work"><i class="bi bi-globe2 text-success me-2"></i>Field Work</a></li>
                                                                    <li><a class="dropdown-item py-1.5" href="dashboard.php?action=checkin&employee_id=<?php echo $emp['id']; ?>&type=Half Day"><i class="bi bi-circle-half text-warning me-2"></i>Half Day</a></li>
                                                                    <li><hr class="dropdown-divider my-1"></li>
                                                                    <li><a class="dropdown-item py-1.5 text-purple" href="dashboard.php?action=checkin&employee_id=<?php echo $emp['id']; ?>&type=Paid Leave"><i class="bi bi-calendar-check me-2"></i>Record Paid Leave</a></li>
                                                                    <li><a class="dropdown-item py-1.5 text-danger" href="dashboard.php?action=checkin&employee_id=<?php echo $emp['id']; ?>&type=Unpaid Leave"><i class="bi bi-calendar-x me-2"></i>Record Unpaid Leave</a></li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Registration Sidebar -->
                <div class="col-lg-4 space-y-6">
                    <!-- Add Employee -->
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                        <h3 class="fw-bold mb-3 text-primary-dark border-bottom pb-2" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">Register New Employee</h3>
                        
                        <form action="dashboard.php" method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="add_employee">
                            <?php csrf_input(); ?>

                            <div class="mb-3">
                                <label for="employee_name" class="form-label text-primary-dark fw-medium mb-1.5" style="font-size: 0.8rem;">Full Name</label>
                                <input type="text" name="employee_name" id="employee_name" class="form-control" placeholder="e.g. John Doe" required style="border-radius: 8px; font-size: 0.85rem; padding: 8px 12px; border-color: var(--border);">
                            </div>

                            <div class="mb-3">
                                <label for="employee_designation" class="form-label text-primary-dark fw-medium mb-1.5" style="font-size: 0.8rem;">Designation / Role</label>
                                <input type="text" name="employee_designation" id="employee_designation" class="form-control" placeholder="e.g. Sales Associate" required style="border-radius: 8px; font-size: 0.85rem; padding: 8px 12px; border-color: var(--border);">
                            </div>

                            <div class="mb-3">
                                <label for="employee_email" class="form-label text-primary-dark fw-medium mb-1.5" style="font-size: 0.8rem;">Email Address</label>
                                <input type="email" name="employee_email" id="employee_email" class="form-control" placeholder="e.g. employee@arteefabrics.com" style="border-radius: 8px; font-size: 0.85rem; padding: 8px 12px; border-color: var(--border);">
                            </div>

                            <div class="mb-4">
                                <label for="employee_phone" class="form-label text-primary-dark fw-medium mb-1.5" style="font-size: 0.8rem;">Phone Number</label>
                                <input type="text" name="employee_phone" id="employee_phone" class="form-control" placeholder="e.g. 757-555-0199" style="border-radius: 8px; font-size: 0.85rem; padding: 8px 12px; border-color: var(--border);">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 font-semibold" style="background: var(--brand-brown); border-color: var(--brand-brown); border-radius: 8px; padding: 10px; font-size: 0.82rem;">
                                <i class="bi bi-person-plus me-1.5"></i>Add to Roster
                            </button>
                        </form>
                    </div>

                    <!-- Today's Logs Log -->
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                        <h3 class="fw-bold mb-3 text-primary-dark border-bottom pb-2" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">Today's Activity Log</h3>
                        <div class="space-y-3" style="max-height: 280px; overflow-y: auto; padding-right: 4px;">
                            <?php if (empty($todayLogs)): ?>
                                <div class="text-center py-4 text-muted font-mono" style="font-size: 0.78rem; italic">No shift entries recorded today.</div>
                            <?php else: ?>
                                <?php foreach ($todayLogs as $log): ?>
                                    <div class="p-2.5 rounded border border-light bg-light/30 d-flex justify-content-between align-items-center" style="font-size: 0.8rem;">
                                        <div>
                                            <div class="fw-semibold text-primary-dark"><?php echo e($log['employee_name']); ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?php echo e($log['designation']); ?></div>
                                        </div>
                                        <div class="text-end font-mono" style="font-size: 0.72rem; line-height: 1.3;">
                                            <div class="text-success"><i class="bi bi-box-arrow-in-right me-0.5"></i><?php echo date('h:i A', strtotime($log['login_time'])); ?></div>
                                            <?php if ($log['logout_time']): ?>
                                                <div class="text-danger"><i class="bi bi-box-arrow-left me-0.5"></i><?php echo date('h:i A', strtotime($log['logout_time'])); ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-success text-white py-0.5 px-1 mt-0.5" style="font-size: 0.6rem;">Active</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live Clock scripts
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        document.getElementById('current-live-time').textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
        
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('current-live-date').textContent = now.toLocaleDateString('en-US', options);
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>
</body>
</html>
