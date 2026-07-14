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

// Run auto-close routine first
process_auto_close_shifts($pdo);

$success = '';
$error = '';

// Handle Manager Approvals/Adjustments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = "CSRF verification failed.";
    } else {
        $action = $_POST['action'];
        $logId = intval($_POST['log_id'] ?? 0);

        if ($logId > 0) {
            if ($action === 'approve_auto_close') {
                $stmt = $pdo->prepare("UPDATE attendance_logs SET manager_approved = 1 WHERE id = ?");
                $stmt->execute([$logId]);
                $success = "Shift log approved successfully.";
            } elseif ($action === 'adjust_auto_close') {
                $adjHours = floatval($_POST['adjusted_hours'] ?? 8.00);
                $overtime = max(0.00, $adjHours - 8.00);
                $stmt = $pdo->prepare("UPDATE attendance_logs SET calculated_hours = ?, calculated_overtime = ?, manager_approved = 1 WHERE id = ?");
                $stmt->execute([$adjHours, $overtime, $logId]);
                $success = "Shift adjusted and approved successfully.";
            } elseif ($action === 'reject_auto_close') {
                $stmt = $pdo->prepare("UPDATE attendance_logs SET status = 'Absent', manager_approved = 1 WHERE id = ?");
                $stmt->execute([$logId]);
                $success = "Shift log rejected and marked as absent.";
            }
        }
    }
}

// 1. Core KPIs calculations
$todayDate = date('Y-m-d');

// Total Active Employees
$totalStaff = $pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL AND status = 'Active'")->fetchColumn();

// Present Today
$totalPresent = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_logs WHERE date = '$todayDate' AND log_type IN ('Regular', 'Field Work')")->fetchColumn();

// Absent Today
$totalAbsent = $pdo->query("
    SELECT COUNT(*) FROM employees e 
    WHERE e.deleted_at IS NULL AND e.status = 'Active' 
      AND e.id NOT IN (SELECT DISTINCT employee_id FROM attendance_logs WHERE date = '$todayDate')
")->fetchColumn();

// On Leave Today
$totalOnLeave = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date = '$todayDate' AND log_type IN ('Paid Leave', 'Unpaid Leave')")->fetchColumn();

// Late Today
$totalLate = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date = '$todayDate' AND is_late = 1")->fetchColumn();

// Active Shifts (No logout time yet)
$activeShifts = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE logout_time IS NULL AND log_type IN ('Regular', 'Field Work')")->fetchColumn();

// Checked Out Today
$totalCheckedOut = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date = '$todayDate' AND logout_time IS NOT NULL")->fetchColumn();

// Overtime today
$totalOvertimeToday = $pdo->query("SELECT COALESCE(SUM(calculated_overtime), 0.00) FROM attendance_logs WHERE date = '$todayDate'")->fetchColumn();

// 2. Pending Approvals
$stmtPending = $pdo->query("
    SELECT l.*, e.name AS employee_name, s.city, s.store_code 
    FROM attendance_logs l 
    JOIN employees e ON l.employee_id = e.id 
    JOIN stores s ON l.store_id = s.id 
    WHERE l.auto_closed = 1 AND l.manager_approved = 0 
    ORDER BY l.login_time DESC
");
$pendingApprovals = $stmtPending->fetchAll();

// 3. Multi-Store Overview Card Data
$stmtStoreKPIs = $pdo->query("
    SELECT s.id, s.city, s.store_code, s.region,
           (SELECT COUNT(*) FROM employees WHERE store_id = s.id AND deleted_at IS NULL AND status = 'Active') AS headcount,
           (SELECT COUNT(DISTINCT employee_id) FROM attendance_logs WHERE store_id = s.id AND date = '$todayDate' AND logout_time IS NULL) AS active_today,
           (SELECT COUNT(*) FROM attendance_logs WHERE store_id = s.id AND date = '$todayDate' AND is_late = 1) AS late_today,
           COALESCE((SELECT ROUND(SUM(calculated_hours), 2) FROM attendance_logs WHERE store_id = s.id AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)), 0.00) AS fortnightly_hours
    FROM stores s 
    ORDER BY s.store_code ASC
");
$storeKPIs = $stmtStoreKPIs->fetchAll();

// 4. Weekly Trend Data (Last 7 Days)
$weeklyDates = [];
$weeklyCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $lbl = date('D (m/d)', strtotime($d));
    $cnt = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date = '$d' AND log_type IN ('Regular', 'Field Work')")->fetchColumn();
    $weeklyDates[] = $lbl;
    $weeklyCounts[] = intval($cnt);
}

// 5. Monthly Trend Data (Last 4 Weeks)
$monthlyLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
$monthlyCounts = [];
for ($w = 3; $w >= 0; $w--) {
    $start = date('Y-m-d', strtotime("-" . (($w * 7) + 6) . " days"));
    $end = date('Y-m-d', strtotime("-" . ($w * 7) . " days"));
    $cnt = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date >= '$start' AND date <= '$end' AND log_type IN ('Regular', 'Field Work')")->fetchColumn();
    $monthlyCounts[] = intval($cnt);
}

// 6. Live Clock-ins feed
$stmtFeed = $pdo->query("
    SELECT l.*, e.name AS employee_name, s.city, s.store_code 
    FROM attendance_logs l 
    JOIN employees e ON l.employee_id = e.id 
    JOIN stores s ON l.store_id = s.id 
    ORDER BY l.login_time DESC 
    LIMIT 6
");
$liveFeed = $stmtFeed->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HQ Overview — Artée Attendance</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <div class="sidebar-brand-sub">HQ Operations Hub</div>
                </div>
            </div>
        </div>
        <nav>
            <ul class="sidebar-menu">
                <li class="sidebar-section-title">Operations</li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="admin_dashboard.php">
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

    <!-- MAIN CONTENT -->
    <div id="main-content">
        <!-- Top Navbar -->
        <nav class="navbar-command px-4 py-2 border-bottom bg-white" id="top-navbar">
            <div class="d-flex align-items-center">
                <h4 class="mb-0 text-primary-dark fw-bold" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;">Executive Overview Dashboard</h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-lg-flex flex-column align-items-end" style="line-height: 1.2;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-primary);" id="current-live-time">--:--:-- --</div>
                    <div style="font-size: 0.68rem; color: var(--text-muted);" id="current-live-date">Loading...</div>
                </div>
                <div class="dropdown">
                    <button class="d-flex align-items-center gap-2 border-0 bg-transparent" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 4px 0;">
                        <div style="width: 34px; height: 34px; background: var(--brand-brown); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8rem;">
                            <?php echo strtoupper(substr($username, 0, 2)); ?>
                        </div>
                        <div class="d-none d-md-block text-start">
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><?php echo e($username); ?></div>
                            <div style="font-size: 0.68rem; color: var(--text-muted);"><?php echo e($role); ?></div>
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

        <div class="container-fluid p-4" style="margin-top: 10px;">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success border-0 mb-4"><?php echo e($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 mb-4"><?php echo e($error); ?></div>
            <?php endif; ?>

            <!-- Executive KPIs Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--primary) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Active Roster</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo $totalStaff; ?></div>
                            </div>
                            <div class="bg-primary-light text-primary rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-people-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--success) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Present Today</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo $totalPresent; ?> <span class="text-secondary" style="font-size:0.75rem;">/ <?php echo $totalStaff; ?></span></div>
                            </div>
                            <div class="bg-success-light text-success rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-check-circle-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--danger) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Absent Today</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo $totalAbsent; ?></div>
                            </div>
                            <div class="bg-danger-light text-danger rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-x-circle-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--warning) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Late Arrivals</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo $totalLate; ?></div>
                            </div>
                            <div class="bg-warning-light text-warning rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic KPI row 2 -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--teal) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">On Active Shift</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo $activeShifts; ?></div>
                            </div>
                            <div class="bg-teal-light text-teal rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-play-circle-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--purple) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">On Approved Leave</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo $totalOnLeave; ?></div>
                            </div>
                            <div class="bg-purple-light text-purple rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-calendar-event-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--info) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Shifts Completed</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo $totalCheckedOut; ?></div>
                            </div>
                            <div class="bg-info-light text-info rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-flag-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--orange) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary uppercase font-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Overtime (Today)</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.4rem;"><?php echo number_format($totalOvertimeToday, 2); ?> hrs</div>
                            </div>
                            <div class="bg-orange-light text-orange rounded p-2" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-stopwatch-fill" style="font-size: 1.1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auto-Closed Pending Approvals -->
            <?php if (!empty($pendingApprovals)): ?>
                <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px; border-top: 4px solid var(--danger) !important;">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                        <h5 class="fw-bold text-danger mb-0" style="font-size: 0.95rem;">
                            <i class="bi bi-exclamation-circle-fill me-1.5"></i>Shifts Requiring Verification (Auto-Closed)
                        </h5>
                        <span class="badge bg-danger text-white rounded"><?php echo count($pendingApprovals); ?> Pending Approval</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0" style="font-size: 0.8rem;">
                            <thead>
                                <tr class="text-secondary">
                                    <th>Employee</th>
                                    <th>Location</th>
                                    <th>Login Time</th>
                                    <th>Auto-Logout Time</th>
                                    <th>Auto Hours</th>
                                    <th class="text-end" style="width: 350px;">Resolution Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingApprovals as $p): ?>
                                    <tr>
                                        <td class="fw-semibold text-primary-dark"><?php echo e($p['employee_name']); ?></td>
                                        <td><?php echo e($p['city']); ?> [<?php echo e($p['store_code']); ?>]</td>
                                        <td class="font-mono"><?php echo date('m/d H:i', strtotime($p['login_time'])); ?></td>
                                        <td class="font-mono text-danger"><?php echo date('m/d H:i', strtotime($p['logout_time'])); ?></td>
                                        <td class="fw-bold text-danger"><?php echo $p['calculated_hours']; ?> hrs</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 align-items-center">
                                                <!-- Quick adjustment form -->
                                                <form method="POST" action="admin_dashboard.php" class="d-inline-flex gap-1 align-items-center">
                                                    <input type="hidden" name="action" value="adjust_auto_close">
                                                    <input type="hidden" name="log_id" value="<?php echo $p['id']; ?>">
                                                    <?php csrf_input(); ?>
                                                    <input type="number" step="0.25" min="0" max="24" name="adjusted_hours" value="8.00" class="form-control form-control-sm text-center" style="max-width: 65px; padding: 2px 4px; font-size: 0.75rem;">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" style="font-size:0.7rem;">Adjust &amp; Approve</button>
                                                </form>

                                                <!-- Approve as is -->
                                                <form method="POST" action="admin_dashboard.php" class="d-inline;">
                                                    <input type="hidden" name="action" value="approve_auto_close">
                                                    <input type="hidden" name="log_id" value="<?php echo $p['id']; ?>">
                                                    <?php csrf_input(); ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success" style="font-size:0.7rem;">Approve</button>
                                                </form>

                                                <!-- Reject -->
                                                <form method="POST" action="admin_dashboard.php" class="d-inline;">
                                                    <input type="hidden" name="action" value="reject_auto_close">
                                                    <input type="hidden" name="log_id" value="<?php echo $p['id']; ?>">
                                                    <?php csrf_input(); ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:0.7rem;">Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Chart & Live Feed Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 12px;">
                        <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 1rem; font-family: 'Outfit', sans-serif;">Attendance &amp; Staffing Trends</h5>
                        <ul class="nav nav-tabs nav-tabs-custom mb-3" id="trendTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1.5 px-3" id="weekly-tab" data-bs-toggle="tab" data-bs-target="#weekly-trend" type="button" role="tab">Weekly Trend</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1.5 px-3" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly-trend" type="button" role="tab">Monthly Trend</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="trendTabsContent">
                            <div class="tab-pane fade show active" id="weekly-trend" role="tabpanel">
                                <div style="position: relative; height: 260px;">
                                    <canvas id="weeklyChart"></canvas>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="monthly-trend" role="tabpanel">
                                <div style="position: relative; height: 260px;">
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 12px;">
                        <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 1rem; font-family: 'Outfit', sans-serif;">Live Clock-in Activity</h5>
                        <div class="space-y-4" style="font-size: 0.8rem;">
                            <?php if (empty($liveFeed)): ?>
                                <p class="text-muted text-center py-4">No recent check-ins recorded.</p>
                            <?php else: ?>
                                <?php foreach ($liveFeed as $f): ?>
                                    <div class="d-flex align-items-start gap-2 pb-2.5 border-bottom">
                                        <div class="rounded-circle bg-success text-white p-1.5 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                            <i class="bi <?php echo $f['logout_time'] ? 'bi-box-arrow-left text-danger' : 'bi-box-arrow-in-right text-success'; ?>" style="font-size: 0.85rem; color:#fff !important;"></i>
                                        </div>
                                        <div class="w-100" style="line-height:1.3;">
                                            <div class="d-flex justify-content-between">
                                                <strong class="text-primary-dark"><?php echo e($f['employee_name']); ?></strong>
                                                <span class="text-muted" style="font-size: 0.72rem;"><?php echo date('h:i A', strtotime($f['login_time'])); ?></span>
                                            </div>
                                            <div class="text-secondary" style="font-size: 0.75rem;">
                                                Location: <?php echo e($f['city']); ?> [<?php echo e($f['store_code']); ?>]
                                            </div>
                                            <div class="text-muted" style="font-size: 0.72rem;">
                                                Type: <?php echo e($f['log_type']); ?> | Status: <span class="fw-bold <?php echo $f['status'] === 'Checked In' ? 'text-success' : 'text-primary'; ?>"><?php echo e($f['status']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Store Specific Performance / Overview -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 1rem; font-family: 'Outfit', sans-serif;">Location-Wise Overview &amp; Health Checks</h5>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" style="font-size: 0.82rem;">
                        <thead>
                            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase;">
                                <th>Store Code</th>
                                <th>City / Region</th>
                                <th>Headcount</th>
                                <th>Active Today</th>
                                <th>Lates Today</th>
                                <th>Worked (Fortnight)</th>
                                <th>Operations Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($storeKPIs as $s): ?>
                                <tr>
                                    <td class="fw-bold text-primary-dark"><?php echo e($s['store_code']); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($s['city']); ?></div>
                                        <div class="text-muted" style="font-size:0.72rem;"><?php echo e($s['region']); ?></div>
                                    </td>
                                    <td class="fw-semibold"><?php echo $s['headcount']; ?> staff</td>
                                    <td>
                                        <span class="badge <?php echo $s['active_today'] > 0 ? 'bg-success' : 'bg-light text-secondary border'; ?>">
                                            <?php echo $s['active_today']; ?> on shift
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $s['late_today'] > 0 ? 'bg-warning text-dark' : 'bg-light text-secondary border'; ?>">
                                            <?php echo $s['late_today']; ?> late
                                        </span>
                                    </td>
                                    <td class="font-mono fw-bold"><?php echo $s['fortnightly_hours']; ?> hrs</td>
                                    <td>
                                        <?php if ($s['active_today'] > 0): ?>
                                            <span class="text-success"><i class="bi bi-circle-fill me-1" style="font-size:0.6rem;"></i> Active</span>
                                        <?php else: ?>
                                            <span class="text-secondary"><i class="bi bi-circle-fill me-1" style="font-size:0.6rem;"></i> Idle</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

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
        
        const timeStr = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        document.getElementById('current-live-time').innerText = timeStr;
        document.getElementById('current-live-date').innerText = dateStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Chart.js renderers
    const ctxWeekly = document.getElementById('weeklyChart').getContext('2d');
    new Chart(ctxWeekly, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($weeklyDates); ?>,
            datasets: [{
                label: 'Shift Logins',
                data: <?php echo json_encode($weeklyCounts); ?>,
                backgroundColor: 'rgba(128, 64, 0, 0.7)',
                borderColor: 'rgba(128, 64, 0, 1)',
                borderWidth: 1.5,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctxMonthly, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthlyLabels); ?>,
            datasets: [{
                label: 'Shift Logins',
                data: <?php echo json_encode($monthlyCounts); ?>,
                backgroundColor: 'rgba(0, 128, 128, 0.2)',
                borderColor: 'rgba(0, 128, 128, 1)',
                borderWidth: 2,
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 5 } }
            }
        }
    });
</script>
</body>
</html>
