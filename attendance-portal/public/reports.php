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

// Filters
$reportType = trim($_GET['report_type'] ?? 'attendance');
$filterStore = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
$filterEmployee = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$filterStartDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d', strtotime('monday this week'));
$filterEndDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d', strtotime('sunday this week'));

// Fetch Stores and Employees for filters
$stmtStores = $pdo->query("SELECT id, store_code, city FROM stores ORDER BY store_code ASC");
$allStores = $stmtStores->fetchAll();

$stmtEmps = $pdo->query("SELECT id, name FROM employees WHERE deleted_at IS NULL ORDER BY name ASC");
$allEmployees = $stmtEmps->fetchAll();

// Execute report query
$data = [];
$columns = [];

if ($reportType === 'attendance') {
    $columns = ['Date', 'Employee', 'Location', 'Clock-In', 'Clock-Out', 'Breaks', 'Net Hours', 'Status'];
    
    $sql = "SELECT l.*, e.name AS employee_name, s.city, s.store_code 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.date >= ? AND l.date <= ? AND l.log_type IN ('Regular', 'Field Work')";
    $params = [$filterStartDate, $filterEndDate];
    
    if ($filterStore > 0) {
        $sql .= " AND l.store_id = ?";
        $params[] = $filterStore;
    }
    if ($filterEmployee > 0) {
        $sql .= " AND l.employee_id = ?";
        $params[] = $filterEmployee;
    }
    $sql .= " ORDER BY l.login_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
} elseif ($reportType === 'payroll') {
    $columns = ['Employee', 'Location', 'Designation', 'Hourly Rate', 'Total Hours', 'Estimated Payout', 'QuickBooks Status'];
    
    $sql = "SELECT e.id, e.name, e.designation, e.hourly_rate, s.city, s.store_code,
                   COALESCE((SELECT SUM(l.calculated_hours) FROM attendance_logs l WHERE l.employee_id = e.id AND l.date >= ? AND l.date <= ?), 0.00) AS total_hours
            FROM employees e 
            JOIN stores s ON e.store_id = s.id
            WHERE e.deleted_at IS NULL";
    $params = [$filterStartDate, $filterEndDate];
    
    if ($filterStore > 0) {
        $sql .= " AND e.store_id = ?";
        $params[] = $filterStore;
    }
    if ($filterEmployee > 0) {
        $sql .= " AND e.id = ?";
        $params[] = $filterEmployee;
    }
    $sql .= " ORDER BY e.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
} elseif ($reportType === 'overtime') {
    $columns = ['Date', 'Employee', 'Location', 'Regular Hours', 'Overtime Hours', 'Total Hours'];
    
    $sql = "SELECT l.*, e.name AS employee_name, s.city, s.store_code 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.date >= ? AND l.date <= ? AND l.calculated_overtime > 0";
    $params = [$filterStartDate, $filterEndDate];
    
    if ($filterStore > 0) {
        $sql .= " AND l.store_id = ?";
        $params[] = $filterStore;
    }
    if ($filterEmployee > 0) {
        $sql .= " AND l.employee_id = ?";
        $params[] = $filterEmployee;
    }
    $sql .= " ORDER BY l.calculated_overtime DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
} elseif ($reportType === 'late') {
    $columns = ['Date', 'Employee', 'Location', 'Clock-In', 'Shift Start', 'Minutes Late'];
    
    $sql = "SELECT l.*, e.name AS employee_name, s.city, s.store_code, s.shift_start 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.date >= ? AND l.date <= ? AND l.is_late = 1";
    $params = [$filterStartDate, $filterEndDate];
    
    if ($filterStore > 0) {
        $sql .= " AND l.store_id = ?";
        $params[] = $filterStore;
    }
    if ($filterEmployee > 0) {
        $sql .= " AND l.employee_id = ?";
        $params[] = $filterEmployee;
    }
    $sql .= " ORDER BY l.login_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
} elseif ($reportType === 'early') {
    $columns = ['Date', 'Employee', 'Location', 'Clock-Out', 'Shift End'];
    
    $sql = "SELECT l.*, e.name AS employee_name, s.city, s.store_code, s.shift_end 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.date >= ? AND l.date <= ? AND l.is_early_departure = 1";
    $params = [$filterStartDate, $filterEndDate];
    
    if ($filterStore > 0) {
        $sql .= " AND l.store_id = ?";
        $params[] = $filterStore;
    }
    if ($filterEmployee > 0) {
        $sql .= " AND l.employee_id = ?";
        $params[] = $filterEmployee;
    }
    $sql .= " ORDER BY l.logout_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
} elseif ($reportType === 'leave') {
    $columns = ['Date', 'Employee', 'Location', 'Leave Type', 'Approval Status'];
    
    $sql = "SELECT l.*, e.name AS employee_name, s.city, s.store_code 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.date >= ? AND l.date <= ? AND l.log_type IN ('Paid Leave', 'Unpaid Leave')";
    $params = [$filterStartDate, $filterEndDate];
    
    if ($filterStore > 0) {
        $sql .= " AND l.store_id = ?";
        $params[] = $filterStore;
    }
    if ($filterEmployee > 0) {
        $sql .= " AND l.employee_id = ?";
        $params[] = $filterEmployee;
    }
    $sql .= " ORDER BY l.date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
} elseif ($reportType === 'performance') {
    $columns = ['Location', 'Active Staff', 'Total Logins', 'Total Late Arrivals', 'Average Work Hours', 'Total Overtime (hrs)'];
    
    $sql = "SELECT s.city, s.store_code,
                   (SELECT COUNT(*) FROM employees WHERE store_id = s.id AND deleted_at IS NULL AND status = 'Active') AS active_staff,
                   COUNT(l.id) AS total_logins,
                   SUM(CASE WHEN l.is_late = 1 THEN 1 ELSE 0 END) AS late_logins,
                   ROUND(AVG(l.calculated_hours), 2) AS avg_hours,
                   ROUND(SUM(l.calculated_overtime), 2) AS total_ot
            FROM stores s 
            LEFT JOIN attendance_logs l ON l.store_id = s.id AND l.date >= ? AND l.date <= ?
            GROUP BY s.id 
            ORDER BY total_logins DESC";
    $params = [$filterStartDate, $filterEndDate];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
}

// CSV Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="artee_' . $reportType . '_report.csv"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    
    fputcsv($output, $columns);
    
    foreach ($data as $row) {
        if ($reportType === 'attendance') {
            fputcsv($output, [
                $row['date'],
                $row['employee_name'],
                $row['city'] . ' [' . $row['store_code'] . ']',
                $row['login_time'],
                $row['logout_time'] ?: 'Active',
                $row['status'] === 'On Break' ? 'On Break' : 'Completed',
                $row['calculated_hours'],
                $row['status']
            ]);
        } elseif ($reportType === 'payroll') {
            $total_pay = floatval($row['hourly_rate']) * floatval($row['total_hours']);
            fputcsv($output, [
                $row['name'],
                $row['city'] . ' [' . $row['store_code'] . ']',
                $row['designation'],
                '$' . number_format($row['hourly_rate'], 2),
                $row['total_hours'],
                '$' . number_format($total_pay, 2),
                'Ready'
            ]);
        } elseif ($reportType === 'overtime') {
            $regular = min(8.00, floatval($row['calculated_hours']));
            fputcsv($output, [
                $row['date'],
                $row['employee_name'],
                $row['city'] . ' [' . $row['store_code'] . ']',
                $regular,
                $row['calculated_overtime'],
                $row['calculated_hours']
            ]);
        } elseif ($reportType === 'late') {
            $lateMin = round((strtotime("1970-01-01 " . date('H:i:s', strtotime($row['login_time']))) - strtotime("1970-01-01 " . $row['shift_start'])) / 60);
            fputcsv($output, [
                $row['date'],
                $row['employee_name'],
                $row['city'] . ' [' . $row['store_code'] . ']',
                $row['login_time'],
                $row['shift_start'],
                $lateMin > 0 ? $lateMin . ' mins' : '—'
            ]);
        } elseif ($reportType === 'early') {
            fputcsv($output, [
                $row['date'],
                $row['employee_name'],
                $row['city'] . ' [' . $row['store_code'] . ']',
                $row['logout_time'],
                $row['shift_end']
            ]);
        } elseif ($reportType === 'leave') {
            fputcsv($output, [
                $row['date'],
                $row['employee_name'],
                $row['city'] . ' [' . $row['store_code'] . ']',
                $row['log_type'],
                $row['status']
            ]);
        } elseif ($reportType === 'performance') {
            fputcsv($output, [
                $row['city'] . ' [' . $row['store_code'] . ']',
                $row['active_staff'],
                $row['total_logins'],
                $row['late_logins'],
                $row['avg_hours'] ?: '0.00',
                $row['total_ot'] ?: '0.00'
            ]);
        }
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidated Reports — Artée Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
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
                    <a class="sidebar-link active" href="reports.php">
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

    <!-- MAIN BODY -->
    <div id="main-content" class="w-100">
        <nav class="navbar navbar-expand-lg px-4 py-2 border-bottom bg-white" id="top-navbar">
            <div class="container-fluid p-0">
                <span class="navbar-brand fw-bold text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">
                    <i class="bi bi-file-earmark-pdf-fill me-2 text-primary"></i>Consolidated Reports Engine
                </span>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <!-- Filter Options Panel -->
            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 0.95rem;">Select Filters &amp; Report Type</h5>
                <form method="GET" action="reports.php" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.75rem;">Report Target</label>
                        <select name="report_type" class="form-select" onchange="this.form.submit();">
                            <option value="attendance" <?php echo $reportType === 'attendance' ? 'selected' : ''; ?>>Attendance Registry</option>
                            <option value="payroll" <?php echo $reportType === 'payroll' ? 'selected' : ''; ?>>Payroll Summary</option>
                            <option value="overtime" <?php echo $reportType === 'overtime' ? 'selected' : ''; ?>>Overtime Report</option>
                            <option value="late" <?php echo $reportType === 'late' ? 'selected' : ''; ?>>Late Arrivals Report</option>
                            <option value="early" <?php echo $reportType === 'early' ? 'selected' : ''; ?>>Early Departure Report</option>
                            <option value="leave" <?php echo $reportType === 'leave' ? 'selected' : ''; ?>>Leaves &amp; Holidays</option>
                            <option value="performance" <?php echo $reportType === 'performance' ? 'selected' : ''; ?>>Location Performance</option>
                        </select>
                    </div>

                    <?php if ($reportType !== 'performance'): ?>
                        <div class="col-md-3">
                            <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.75rem;">Location</label>
                            <select name="store_id" class="form-select" onchange="this.form.submit();">
                                <option value="0">All Locations</option>
                                <?php foreach ($allStores as $st): ?>
                                    <option value="<?php echo $st['id']; ?>" <?php echo $filterStore === intval($st['id']) ? 'selected' : ''; ?>>
                                        [<?php echo e($st['store_code']); ?>] <?php echo e($st['city']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.75rem;">Employee</label>
                            <select name="employee_id" class="form-select" onchange="this.form.submit();">
                                <option value="0">All Employees</option>
                                <?php foreach ($allEmployees as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" <?php echo $filterEmployee === intval($e['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($e['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-2">
                        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.75rem;">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo e($filterStartDate); ?>" onchange="this.form.submit();">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.75rem;">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo e($filterEndDate); ?>" onchange="this.form.submit();">
                    </div>

                    <div class="col-12 d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-sm px-4 py-2">
                            <i class="bi bi-funnel-fill me-1.5"></i>Apply Filters
                        </button>
                        <a href="reports.php?action=export&report_type=<?php echo $reportType; ?>&store_id=<?php echo $filterStore; ?>&employee_id=<?php echo $filterEmployee; ?>&start_date=<?php echo $filterStartDate; ?>&end_date=<?php echo $filterEndDate; ?>" class="btn btn-success btn-sm px-4 py-2">
                            <i class="bi bi-file-earmark-excel me-1.5"></i>Download Excel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Reports Display -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" style="font-size: 0.82rem;">
                        <thead>
                            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase;">
                                <?php foreach ($columns as $c): ?>
                                    <th><?php echo $c; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data)): ?>
                                <tr>
                                    <td colspan="<?php echo count($columns); ?>" class="text-center py-4 text-muted">No records match the active parameters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data as $row): ?>
                                    <?php if ($reportType === 'attendance'): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo date('m/d/Y', strtotime($row['date'])); ?></td>
                                            <td class="fw-semibold text-primary-dark"><?php echo e($row['employee_name']); ?></td>
                                            <td><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                            <td class="font-mono text-success"><?php echo date('h:i:s A', strtotime($row['login_time'])); ?></td>
                                            <td class="font-mono text-danger"><?php echo $row['logout_time'] ? date('h:i:s A', strtotime($row['logout_time'])) : 'Active'; ?></td>
                                            <td><?php echo $row['status'] === 'On Break' ? 'On Break' : 'Completed'; ?></td>
                                            <td class="font-mono fw-bold"><?php echo $row['calculated_hours']; ?> hrs</td>
                                            <td><span class="badge bg-light text-dark border"><?php echo $row['status']; ?></span></td>
                                        </tr>
                                    <?php elseif ($reportType === 'payroll'): ?>
                                        <?php $totalPay = floatval($row['hourly_rate']) * floatval($row['total_hours']); ?>
                                        <tr>
                                            <td class="fw-semibold text-primary-dark"><?php echo e($row['name']); ?></td>
                                            <td><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                            <td><?php echo e($row['designation']); ?></td>
                                            <td class="font-mono">$<?php echo number_format($row['hourly_rate'], 2); ?></td>
                                            <td class="font-mono fw-bold"><?php echo $row['total_hours']; ?> hrs</td>
                                            <td class="font-mono fw-bold text-success">$<?php echo number_format($totalPay, 2); ?></td>
                                            <td><span class="text-success"><i class="bi bi-check-circle-fill"></i> Synced</span></td>
                                        </tr>
                                    <?php elseif ($reportType === 'overtime'): ?>
                                        <?php $regular = min(8.00, floatval($row['calculated_hours'])); ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo date('m/d/Y', strtotime($row['date'])); ?></td>
                                            <td class="fw-semibold text-primary-dark"><?php echo e($row['employee_name']); ?></td>
                                            <td><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                            <td class="font-mono"><?php echo $regular; ?> hrs</td>
                                            <td class="font-mono fw-bold text-danger"><?php echo $row['calculated_overtime']; ?> hrs</td>
                                            <td class="font-mono fw-bold"><?php echo $row['calculated_hours']; ?> hrs</td>
                                        </tr>
                                    <?php elseif ($reportType === 'late'): ?>
                                        <?php $lateMin = round((strtotime("1970-01-01 " . date('H:i:s', strtotime($row['login_time']))) - strtotime("1970-01-01 " . $row['shift_start'])) / 60); ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo date('m/d/Y', strtotime($row['date'])); ?></td>
                                            <td class="fw-semibold text-primary-dark"><?php echo e($row['employee_name']); ?></td>
                                            <td><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                            <td class="font-mono text-danger"><?php echo date('h:i A', strtotime($row['login_time'])); ?></td>
                                            <td class="font-mono"><?php echo date('h:i A', strtotime($row['shift_start'])); ?></td>
                                            <td class="font-mono text-danger fw-bold"><?php echo $lateMin > 0 ? $lateMin . ' mins' : '—'; ?></td>
                                        </tr>
                                    <?php elseif ($reportType === 'early'): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo date('m/d/Y', strtotime($row['date'])); ?></td>
                                            <td class="fw-semibold text-primary-dark"><?php echo e($row['employee_name']); ?></td>
                                            <td><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                            <td class="font-mono text-danger"><?php echo date('h:i A', strtotime($row['logout_time'])); ?></td>
                                            <td class="font-mono"><?php echo date('h:i A', strtotime($row['shift_end'])); ?></td>
                                        </tr>
                                    <?php elseif ($reportType === 'leave'): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo date('m/d/Y', strtotime($row['date'])); ?></td>
                                            <td class="fw-semibold text-primary-dark"><?php echo e($row['employee_name']); ?></td>
                                            <td><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                            <td><span class="badge bg-purple text-white"><?php echo e($row['log_type']); ?></span></td>
                                            <td><span class="text-success fw-semibold"><i class="bi bi-check-all"></i> Approved</span></td>
                                        </tr>
                                    <?php elseif ($reportType === 'performance'): ?>
                                        <tr>
                                            <td class="fw-bold text-primary-dark"><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                            <td class="fw-semibold"><?php echo $row['active_staff']; ?> staff</td>
                                            <td><?php echo $row['total_logins']; ?> logs</td>
                                            <td class="text-warning fw-bold"><?php echo $row['late_logins']; ?> lates</td>
                                            <td class="font-mono fw-bold"><?php echo $row['avg_hours'] ?: '0.00'; ?> hrs</td>
                                            <td class="font-mono fw-bold text-danger"><?php echo $row['total_ot'] ?: '0.00'; ?> hrs</td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
