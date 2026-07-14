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
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterStartDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d', strtotime('monday this week'));
$filterEndDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d', strtotime('sunday this week'));

// Handle Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $sql = "SELECT l.*, e.name AS employee_name, e.designation, s.store_name, s.store_code, s.city 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.date >= ? AND l.date <= ?";
    $params = [$filterStartDate, $filterEndDate];

    if ($filterStore > 0) {
        $sql .= " AND l.store_id = ?";
        $params[] = $filterStore;
    }
    if ($filterEmployee > 0) {
        $sql .= " AND l.employee_id = ?";
        $params[] = $filterEmployee;
    }
    if (!empty($filterStatus)) {
        $sql .= " AND l.status = ?";
        $params[] = $filterStatus;
    }
    $sql .= " ORDER BY l.login_time DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="artee_attendance_report.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    fputcsv($output, [
        'Date',
        'Employee Name',
        'Designation/Role',
        'Store Name',
        'Store Code',
        'Store City',
        'Clock-In Time',
        'Break-Out Time(s)',
        'Break-In Time(s)',
        'Clock-Out Time',
        'Total Break Duration (mins)',
        'Net Work Duration (hrs)',
        'Status'
    ]);

    foreach ($logs as $log) {
        $stmtBreaks = $pdo->prepare("SELECT * FROM attendance_breaks WHERE log_id = ? ORDER BY break_start ASC");
        $stmtBreaks->execute([$log['id']]);
        $breaksList = $stmtBreaks->fetchAll();

        $breakOuts = [];
        $breakIns = [];
        $totalBreakSeconds = 0;
        foreach ($breaksList as $b) {
            $breakOuts[] = date('h:i A', strtotime($b['break_start']));
            if ($b['break_end']) {
                $breakIns[] = date('h:i A', strtotime($b['break_end']));
                $totalBreakSeconds += strtotime($b['break_end']) - strtotime($b['break_start']);
            } else {
                $breakIns[] = 'Active';
            }
        }

        $breakOutStr = implode('; ', $breakOuts);
        $breakInStr = implode('; ', $breakIns);
        $totalBreakMins = round($totalBreakSeconds / 60);

        $rowHours = calculate_shift_hours($log['login_time'], $log['logout_time'], $log['date'], $totalBreakSeconds);

        fputcsv($output, [
            date('m/d/Y', strtotime($log['date'])),
            $log['employee_name'],
            $log['designation'] ?: 'Staff',
            $log['store_name'],
            $log['store_code'],
            $log['city'],
            date('h:i:s A', strtotime($log['login_time'])),
            $breakOutStr ?: '—',
            $breakInStr ?: '—',
            $log['logout_time'] ? date('h:i:s A', strtotime($log['logout_time'])) : 'Active',
            $totalBreakMins,
            number_format($rowHours, 2),
            $log['status']
        ]);
    }

    fclose($output);
    exit;
}

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

// Fetch filtered logs
$sql = "SELECT l.*, e.name AS employee_name, e.designation, s.store_name, s.store_code, s.city 
        FROM attendance_logs l 
        JOIN employees e ON l.employee_id = e.id 
        JOIN stores s ON l.store_id = s.id 
        WHERE l.date >= ? AND l.date <= ?";
$params = [$filterStartDate, $filterEndDate];

if ($filterStore > 0) {
    $sql .= " AND l.store_id = ?";
    $params[] = $filterStore;
}
if ($filterEmployee > 0) {
    $sql .= " AND l.employee_id = ?";
    $params[] = $filterEmployee;
}
if (!empty($filterStatus)) {
    $sql .= " AND l.status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY l.login_time DESC";

$stmtLogs = $pdo->prepare($sql);
$stmtLogs->execute($params);
$logs = $stmtLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report — Artée Attendance</title>
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
                    <a class="sidebar-link active" href="attendance_report.php">
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
                    <a class="sidebar-link" href="payroll_report.php">
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
                <h4 class="mb-0 text-primary-dark fw-bold" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;">Attendance Analytics &amp; Reports</h4>
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

            <!-- Filters Section -->
            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 1rem; font-family: 'Outfit', sans-serif;">Search &amp; Filter Options</h5>
                <form action="attendance_report.php" method="GET" id="filter-form">
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

                        <!-- Status -->
                        <div class="col-md-2 col-sm-6">
                            <label for="status" class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Status</label>
                            <select name="status" id="status" class="form-select py-1.5 px-3" style="font-size: 0.85rem; border-radius: 6px;" onchange="this.form.submit();">
                                <option value="">All Statuses</option>
                                <option value="Checked In" <?php echo $filterStatus === 'Checked In' ? 'selected' : ''; ?>>Checked In</option>
                                <option value="On Break" <?php echo $filterStatus === 'On Break' ? 'selected' : ''; ?>>On Break</option>
                                <option value="Completed" <?php echo $filterStatus === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-2 col-sm-6">
                            <label for="start_date" class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control py-1.5 px-3" style="font-size: 0.85rem; border-radius: 6px;" value="<?php echo e($filterStartDate); ?>" onchange="this.form.submit();">
                        </div>

                        <!-- End Date -->
                        <div class="col-md-2 col-sm-6">
                            <label for="end_date" class="form-label text-secondary fw-semibold mb-1" style="font-size: 0.78rem;">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control py-1.5 px-3" style="font-size: 0.85rem; border-radius: 6px;" value="<?php echo e($filterEndDate); ?>" onchange="this.form.submit();">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-sm px-4 py-2" style="border-radius: 6px; font-weight: 600;">
                            <i class="bi bi-filter me-1.5"></i>Apply Filters
                        </button>
                        <a href="attendance_report.php?action=export&store_id=<?php echo $filterStore; ?>&employee_id=<?php echo $filterEmployee; ?>&status=<?php echo urlencode($filterStatus); ?>&start_date=<?php echo $filterStartDate; ?>&end_date=<?php echo $filterEndDate; ?>" class="btn btn-success btn-sm px-4 py-2" style="border-radius: 6px; font-weight: 600;">
                            <i class="bi bi-file-earmark-excel me-1.5"></i>Download Excel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Ledger Card -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <div class="pb-3 border-bottom mb-3">
                    <h3 class="fw-bold mb-1 text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">Attendance Summary Logs</h3>
                    <p class="text-muted mb-0" style="font-size: 0.78rem;">
                        Displaying log results for: <strong><?php echo date('m/d/Y', strtotime($filterStartDate)); ?></strong> to <strong><?php echo date('m/d/Y', strtotime($filterEndDate)); ?></strong>
                    </p>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" style="font-size: 0.82rem;">
                        <thead>
                            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; font-family: 'Outfit', sans-serif; letter-spacing: 0.5px;">
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Location</th>
                                <th>Clock-In</th>
                                <th>Lunch/Breaks</th>
                                <th>Clock-Out</th>
                                <th class="text-end" style="width: 100px;">Break Duration</th>
                                <th class="text-end" style="width: 100px;">Total Hours</th>
                                <th class="text-center" style="width: 110px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted font-mono" style="font-size: 0.82rem;">No matching logs found for the selected range/filters.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php 
                                    $stmtBreaks = $pdo->prepare("SELECT * FROM attendance_breaks WHERE log_id = ? ORDER BY break_start ASC");
                                    $stmtBreaks->execute([$log['id']]);
                                    $breaksList = $stmtBreaks->fetchAll();

                                    $breakOuts = [];
                                    $breakIns = [];
                                    $totalBreakSeconds = 0;
                                    foreach ($breaksList as $b) {
                                        $breakOuts[] = date('h:i A', strtotime($b['break_start']));
                                        if ($b['break_end']) {
                                            $breakIns[] = date('h:i A', strtotime($b['break_end']));
                                            $totalBreakSeconds += strtotime($b['break_end']) - strtotime($b['break_start']);
                                        } else {
                                            $breakIns[] = 'Active';
                                        }
                                    }
                                    $totalBreakMins = round($totalBreakSeconds / 60);

                                    $rowHours = calculate_shift_hours($log['login_time'], $log['logout_time'], $log['date'], $totalBreakSeconds);

                                    // Status styling class
                                    $statusClass = 'badge-pending';
                                    if ($log['status'] === 'Completed') {
                                        $statusClass = 'badge-completed';
                                    } elseif ($log['status'] === 'On Break') {
                                        $statusClass = 'badge-label-created';
                                    } elseif ($log['status'] === 'Checked In') {
                                        $statusClass = 'badge-processing';
                                    }
                                    ?>
                                    <tr>
                                        <td class="fw-semibold text-dark">
                                            <?php echo date('m/d/Y', strtotime($log['date'])); ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-primary-dark"><?php echo e($log['employee_name']); ?></div>
                                            <div class="text-secondary" style="font-size: 0.72rem;"><?php echo e($log['designation'] ?: 'Staff'); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold" style="color: var(--text-primary);"><?php echo e($log['city']); ?></div>
                                            <div class="text-secondary" style="font-size: 0.72rem;">Code: <?php echo e($log['store_code']); ?></div>
                                        </td>
                                        <td class="font-mono text-success fw-semibold">
                                            <?php echo date('h:i:s A', strtotime($log['login_time'])); ?>
                                        </td>
                                        <td>
                                            <?php if (empty($breaksList)): ?>
                                                <span class="text-muted">—</span>
                                            <?php else: ?>
                                                <div style="font-size: 0.74rem; line-height: 1.3;">
                                                    <?php for ($i = 0; $i < count($breakOuts); $i++): ?>
                                                        <div>
                                                            <i class="bi bi-pause-circle text-danger me-1"></i><?php echo $breakOuts[$i]; ?>
                                                            <i class="bi bi-play-circle text-success mx-1"></i><?php echo $breakIns[$i] ?? 'Active'; ?>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-mono text-danger fw-semibold">
                                            <?php echo $log['logout_time'] ? date('h:i:s A', strtotime($log['logout_time'])) : '<span class="text-primary-dark blink">Active Shift</span>'; ?>
                                        </td>
                                        <td class="text-end font-mono">
                                            <?php echo $totalBreakMins; ?> mins
                                        </td>
                                        <td class="text-end font-mono fw-bold text-primary-dark">
                                            <?php echo number_format($rowHours, 2); ?> hrs
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-command <?php echo $statusClass; ?>" style="font-size: 0.7rem;">
                                                <?php echo e($log['status']); ?>
                                            </span>
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
