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
$filterStore = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
$filterEmployee = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
// Defaults to current bi-weekly period (monday this week - 7 days to monday this week + 6 days, i.e. 2 weeks)
$defaultStart = date('Y-m-d', strtotime('monday this week'));
$defaultEnd = date('Y-m-d', strtotime($defaultStart . ' + 13 days'));
$filterStartDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : $defaultStart;
$filterEndDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : $defaultEnd;

// Fetch all stores for filters
$stmtStores = $pdo->query("SELECT id, store_name, store_code, city FROM stores ORDER BY store_code ASC");
$allStores = $stmtStores->fetchAll();

// Fetch employees dynamically based on store filter
$empSql = "SELECT id, name FROM employees WHERE status = 'Active'";
$empParams = [];
if ($filterStore > 0) {
    $empSql .= " AND store_id = ?";
    $empParams[] = $filterStore;
}
$empSql .= " ORDER BY name ASC";
$stmtEmps = $pdo->prepare($empSql);
$stmtEmps->execute($empParams);
$allEmployees = $stmtEmps->fetchAll();

// Fetch active employees matching filters
$sql = "SELECT e.*, s.store_name, s.store_code, s.city 
        FROM employees e 
        JOIN stores s ON e.store_id = s.id 
        WHERE e.status = 'Active'";
$params = [];

if ($filterStore > 0) {
    $sql .= " AND e.store_id = ?";
    $params[] = $filterStore;
}
if ($filterEmployee > 0) {
    $sql .= " AND e.id = ?";
    $params[] = $filterEmployee;
}
$sql .= " ORDER BY s.store_code ASC, e.name ASC";

$stmtEmps = $pdo->prepare($sql);
$stmtEmps->execute($params);
$employeesList = $stmtEmps->fetchAll();

// Aggregation process
$payrollList = [];
$totalHoursWorkedAll = 0.00;
$totalPayoutAll = 0.00;
$totalSalesAll = 0.00;

foreach ($employeesList as $emp) {
    $empId = $emp['id'];
    
    // Fetch logs in range
    $stmtLogs = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND date >= ? AND date <= ?");
    $stmtLogs->execute([$empId, $filterStartDate, $filterEndDate]);
    $logs = $stmtLogs->fetchAll();
    
    $totalHours = 0.00;
    foreach ($logs as $log) {
        // Fetch breaks for this log
        $stmtBreaks = $pdo->prepare("SELECT * FROM attendance_breaks WHERE log_id = ? ORDER BY break_start ASC");
        $stmtBreaks->execute([$log['id']]);
        $breaksList = $stmtBreaks->fetchAll();

        $logBreakSeconds = 0;
        foreach ($breaksList as $b) {
            if ($b['break_end']) {
                $logBreakSeconds += strtotime($b['break_end']) - strtotime($b['break_start']);
            }
        }

        $totalHours += calculate_shift_hours($log['login_time'], $log['logout_time'], $log['date'], $logBreakSeconds);
    }
    
    $rate = floatval($emp['hourly_rate']);
    $payout = $totalHours * $rate;
    
    // Quickbill Sales lookup
    $totalPeriodSales = 0.00;
    if ($pdoQB) {
        $stmtStaff = $pdoQB->prepare("SELECT id FROM sales_staff WHERE email = ? OR name = ? LIMIT 1");
        $stmtStaff->execute([$emp['email'], $emp['name']]);
        $staffId = $stmtStaff->fetchColumn();

        if ($staffId) {
            $stmtTPS = $pdoQB->prepare("SELECT SUM(net_amount) FROM sales_header WHERE sales_staff_id = ? AND doc_date >= ? AND doc_date <= ? AND status = 'confirmed'");
            $stmtTPS->execute([$staffId, $filterStartDate, $filterEndDate]);
            $totalPeriodSales = floatval($stmtTPS->fetchColumn() ?: 0.00);
        }
    }
    
    $payrollList[] = [
        'id' => $empId,
        'name' => $emp['name'],
        'designation' => $emp['designation'],
        'store_code' => $emp['store_code'],
        'store_name' => $emp['store_name'],
        'city' => $emp['city'],
        'hourly_rate' => $rate,
        'total_hours' => $totalHours,
        'payout' => $payout,
        'sales' => $totalPeriodSales
    ];
    
    $totalHoursWorkedAll += $totalHours;
    $totalPayoutAll += $payout;
    $totalSalesAll += $totalPeriodSales;
}

// Handle Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="artee_payroll_report.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    fputcsv($output, [
        'Employee Name',
        'Designation/Role',
        'Store Name',
        'Store Code',
        'Store City',
        'Hourly Rate ($)',
        'Total Hours Worked',
        'Estimated Total Payout ($)',
        'Attributed Sales ($)'
    ]);

    foreach ($payrollList as $item) {
        fputcsv($output, [
            $item['name'],
            $item['designation'] ?: 'Staff',
            $item['store_name'],
            $item['store_code'],
            $item['city'],
            number_format($item['hourly_rate'], 2, '.', ''),
            number_format($item['total_hours'], 2, '.', ''),
            number_format($item['payout'], 2, '.', ''),
            number_format($item['sales'], 2, '.', '')
        ]);
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
    <title>Payroll Report — Artée Attendance</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<div id="app-container">

    <!-- ==========================================
         LEFT SIDEBAR
    ========================================== -->
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
                <li class="sidebar-section-title">HQ Controls</li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="admin_dashboard.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Operations Hub</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="attendance_report.php">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Attendance Report</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="payroll.php">
                        <i class="bi bi-cash-coin"></i>
                        <span>Payroll Hub</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="payroll_report.php">
                        <i class="bi bi-calculator"></i>
                        <span>Payroll Report</span>
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
                <h4 class="mb-0 text-primary-dark fw-bold" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;">Payroll Analytics &amp; Reports</h4>
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

        <div class="container-fluid p-4" style="margin-top: 20px;">

            <!-- Payroll KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--primary) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Est. Total Payout</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;">$<?php echo number_format($totalPayoutAll, 2); ?></div>
                            </div>
                            <div class="bg-primary-light text-primary rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-cash-stack" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--success) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Hours Worked</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;"><?php echo number_format($totalHoursWorkedAll, 2); ?> hrs</div>
                            </div>
                            <div class="bg-success-light text-success rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-hourglass-split" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--purple) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Attributed Sales</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;">$<?php echo number_format($totalSalesAll, 2); ?></div>
                            </div>
                            <div class="bg-purple-light text-purple rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-currency-dollar" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 1rem; font-family: 'Outfit', sans-serif;">Search &amp; Filter Options</h5>
                <form action="payroll_report.php" method="GET" id="filter-form">
                    <div class="row g-3">
                        <!-- Location -->
                        <div class="col-md-3 col-sm-6">
                            <label for="store_id" class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Location</label>
                            <select name="store_id" id="store_id" class="form-select py-1.5 px-3" style="font-size: 0.85rem; border-radius: 6px;" onchange="document.getElementById('employee_id').value=0; this.form.submit();">
                                <option value="0">All Locations</option>
                                <?php foreach ($allStores as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $filterStore === intval($s['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($s['store_code']); ?> — <?php echo e($s['city']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Employee -->
                        <div class="col-md-3 col-sm-6">
                            <label for="employee_id" class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Employee Name</label>
                            <select name="employee_id" id="employee_id" class="form-select py-1.5 px-3" style="font-size: 0.85rem; border-radius: 6px;" onchange="this.form.submit();">
                                <option value="0">All Employees</option>
                                <?php foreach ($allEmployees as $eRow): ?>
                                    <option value="<?php echo $eRow['id']; ?>" <?php echo $filterEmployee === intval($eRow['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($eRow['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-3 col-sm-6">
                            <label for="start_date" class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control py-1.5 px-3" style="font-size: 0.85rem; border-radius: 6px;" value="<?php echo e($filterStartDate); ?>" onchange="this.form.submit();">
                        </div>

                        <!-- End Date -->
                        <div class="col-md-3 col-sm-6">
                            <label for="end_date" class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control py-1.5 px-3" style="font-size: 0.85rem; border-radius: 6px;" value="<?php echo e($filterEndDate); ?>" onchange="this.form.submit();">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-sm px-4 py-2" style="border-radius: 6px; font-weight: 600;">
                            <i class="bi bi-filter me-1.5"></i>Apply Filters
                        </button>
                        <a href="payroll_report.php?action=export&store_id=<?php echo $filterStore; ?>&employee_id=<?php echo $filterEmployee; ?>&start_date=<?php echo $filterStartDate; ?>&end_date=<?php echo $filterEndDate; ?>" class="btn btn-success btn-sm px-4 py-2" style="border-radius: 6px; font-weight: 600;">
                            <i class="bi bi-file-earmark-excel me-1.5"></i>Download Excel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Ledger Card -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <div class="pb-3 border-bottom mb-3">
                    <h3 class="fw-bold mb-1 text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">Bi-Weekly Payroll Summary</h3>
                    <p class="text-muted mb-0" style="font-size: 0.78rem;">
                        Aggregated totals for: <strong><?php echo date('m/d/Y', strtotime($filterStartDate)); ?></strong> to <strong><?php echo date('m/d/Y', strtotime($filterEndDate)); ?></strong>
                    </p>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" style="font-size: 0.82rem;">
                        <thead>
                            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; font-family: 'Outfit', sans-serif; letter-spacing: 0.5px;">
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th>Location</th>
                                <th class="text-end" style="width: 130px;">Hourly Rate ($)</th>
                                <th class="text-end" style="width: 130px;">Total Hours</th>
                                <th class="text-end" style="width: 140px;">Estimated Pay</th>
                                <th class="text-end" style="width: 140px;">Confirmed Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payrollList)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted font-mono" style="font-size: 0.82rem;">No active employees found matching the filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payrollList as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div style="width: 32px; height: 32px; background: var(--primary-light); border: 1px solid var(--border); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; font-size: 0.78rem; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($item['name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-primary-dark" style="font-size: 0.88rem;"><?php echo e($item['name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-secondary" style="font-size: 0.82rem;"><?php echo e($item['designation']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark" style="font-size: 0.85rem; color: var(--text-primary);"><?php echo e($item['city']); ?></div>
                                            <span class="text-secondary" style="font-size: 0.75rem;">Code: <?php echo e($item['store_code']); ?></span>
                                        </td>
                                        <td class="text-end font-mono">
                                            $<?php echo number_format($item['hourly_rate'], 2); ?>
                                        </td>
                                        <td class="text-end font-mono fw-semibold text-success">
                                            <?php echo number_format($item['total_hours'], 2); ?> hrs
                                        </td>
                                        <td class="text-end font-mono text-primary-dark fw-bold">
                                            $<?php echo number_format($item['payout'], 2); ?>
                                        </td>
                                        <td class="text-end font-mono text-purple fw-bold">
                                            $<?php echo number_format($item['sales'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
