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
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';

// Fetch all stores for filters
$stmtStores = $pdo->query("SELECT id, store_name, store_code, city FROM stores ORDER BY store_code ASC");
$allStores = $stmtStores->fetchAll();

// Dynamic query building for logs
$sql = "SELECT l.*, e.name AS employee_name, e.designation, s.store_name, s.store_code, s.city 
        FROM attendance_logs l 
        JOIN employees e ON l.employee_id = e.id 
        JOIN stores s ON l.store_id = s.id 
        WHERE 1=1";
$params = [];

if ($filterStore > 0) {
    $sql .= " AND l.store_id = ?";
    $params[] = $filterStore;
}

if (!empty($filterDate)) {
    $sql .= " AND l.date = ?";
    $params[] = $filterDate;
}

if ($filterStore > 0 || !empty($filterDate)) {
    $sql .= " ORDER BY l.login_time DESC";
} else {
    // Default to show latest 10 entries if no date or store filters are selected
    $sql .= " ORDER BY l.login_time DESC LIMIT 10";
}

$stmtLogs = $pdo->prepare($sql);
$stmtLogs->execute($params);
$logs = $stmtLogs->fetchAll();

// KPIs
$kpiTotalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
$kpiActiveToday = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date = CURDATE() AND logout_time IS NULL")->fetchColumn();
$kpiActiveStores = $pdo->query("SELECT COUNT(DISTINCT store_id) FROM attendance_logs WHERE date = CURDATE()")->fetchColumn();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HQ Operations Hub — Artée Attendance</title>
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
                    <a class="sidebar-link active" href="admin_dashboard.php">
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
                <h4 class="mb-0 text-primary-dark fw-bold" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;">Attendance Ledger</h4>
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
            
            <!-- KPIs Banner -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--primary) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Global Roster</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;"><?php echo $kpiTotalEmployees; ?></div>
                            </div>
                            <div class="bg-primary-light text-primary rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-people-fill" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--success) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">On Shift Today</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;"><?php echo $kpiActiveToday; ?></div>
                            </div>
                            <div class="bg-success-light text-success rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-person-check-fill" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--warning) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Active Locations</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;"><?php echo $kpiActiveStores; ?> / 11</div>
                            </div>
                            <div class="bg-warning-light text-warning rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-shop" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Log Content -->
            <div class="row g-4">
                <div class="col-lg-12">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                        
                        <!-- Filters Header -->
                        <div class="pb-3 border-bottom mb-3">
                            <h3 class="fw-bold mb-3 text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">
                                <?php echo ($filterStore === 0 && empty($filterDate)) ? 'Top 10 Latest Shift Logs' : 'Filtered Shift Activity Logs'; ?>
                            </h3>
                            
                            <form action="admin_dashboard.php" method="GET" class="row g-2">
                                <div class="col-md-4">
                                    <select name="store_id" class="form-select" style="font-size: 0.85rem; border-radius: 6px;">
                                        <option value="0">All Locations</option>
                                        <?php foreach ($allStores as $st): ?>
                                            <option value="<?php echo $st['id']; ?>" <?php echo $filterStore === intval($st['id']) ? 'selected' : ''; ?>>
                                                [<?php echo e($st['store_code']); ?>] <?php echo e($st['store_name']); ?> - <?php echo e($st['city']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="date" class="form-select" value="<?php echo e($filterDate); ?>" style="font-size: 0.85rem; border-radius: 6px;">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100" style="background: var(--brand-brown); border-color: var(--brand-brown); font-size: 0.85rem; font-weight: 500; border-radius: 6px;">
                                        <i class="bi bi-funnel-fill me-1"></i>Filter
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <a href="export_attendance.php?store_id=<?php echo $filterStore; ?>&date=<?php echo urlencode($filterDate); ?>" class="btn btn-success w-100" style="font-size: 0.85rem; font-weight: 500; border-radius: 6px;">
                                        <i class="bi bi-file-earmark-excel me-1"></i>Download Excel
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Logs Ledger Table -->
                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead>
                                    <tr class="text-secondary" style="font-size: 0.78rem; text-transform: uppercase; font-family: 'Outfit', sans-serif;">
                                        <th>Employee</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Login / Logout Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted font-mono" style="font-size: 0.82rem;">No shift records match this search filter.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-primary-dark" style="font-size: 0.88rem;"><?php echo e($log['employee_name']); ?></div>
                                                    <span class="text-secondary" style="font-size: 0.75rem;"><?php echo e($log['designation']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark" style="font-size: 0.88rem; color: var(--text-primary);"><?php echo e($log['city']); ?></div>
                                                    <span class="text-secondary" style="font-size: 0.75rem;">Code: <?php echo e($log['store_code']); ?></span>
                                                </td>
                                                <td class="font-mono" style="font-size: 0.82rem;">
                                                    <?php echo date('m/d/Y', strtotime($log['date'])); ?>
                                                </td>
                                                <td class="font-mono" style="font-size: 0.8rem; line-height: 1.3;">
                                                    <div class="text-success"><i class="bi bi-box-arrow-in-right me-1"></i><?php echo date('h:i:s A', strtotime($log['login_time'])); ?></div>
                                                    <?php if ($log['logout_time']): ?>
                                                        <div class="text-danger"><i class="bi bi-box-arrow-left me-1"></i><?php echo date('h:i:s A', strtotime($log['logout_time'])); ?></div>
                                                    <?php else: ?>
                                                        <div class="text-secondary"><i class="bi bi-dash me-1"></i>Active Shift</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="font-mono" style="font-size: 0.82rem; font-weight: 500;">
                                                    <?php 
                                                    if ($log['logout_time']) {
                                                        $diff = strtotime($log['logout_time']) - strtotime($log['login_time']);
                                                        $hours = floor($diff / 3600);
                                                        $mins = floor(($diff % 3600) / 60);
                                                        echo "{$hours}h {$mins}m";
                                                    } else {
                                                        echo "Active";
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['status'] === 'Checked In'): ?>
                                                        <span class="badge bg-success text-white" style="font-size: 0.68rem; padding: 4px 8px;">On Shift</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-secondary border" style="font-size: 0.68rem; padding: 4px 8px;">Completed</span>
                                                    <?php endif; ?>
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
