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

$error = '';
$success = '';

// Handle Save Rates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rates') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = "CSRF verification failed.";
    } else {
        $rates = $_POST['rates'] ?? [];
        try {
            $pdo->beginTransaction();
            $stmtUpdate = $pdo->prepare("UPDATE employees SET hourly_rate = ? WHERE id = ?");
            foreach ($rates as $empId => $rate) {
                $stmtUpdate->execute([floatval($rate), intval($empId)]);
            }
            $pdo->commit();
            $success = "Hourly rates updated successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update rates: " . $e->getMessage();
        }
    }
}

// Week Calculation (Bi-weekly: Week 1 and Week 2)
$selectedDate = isset($_GET['week']) ? trim($_GET['week']) : date('Y-m-d');
$weekCommencing = date('Y-m-d', strtotime('monday this week', strtotime($selectedDate)));

$w1_start = $weekCommencing;
$w1_end = date('Y-m-d', strtotime($weekCommencing . ' + 6 days'));
$w2_start = date('Y-m-d', strtotime($weekCommencing . ' + 7 days'));
$w2_end = date('Y-m-d', strtotime($weekCommencing . ' + 13 days'));

// Fetch Employees & hours worked
$stmt = $pdo->prepare("SELECT e.*, s.store_name, s.store_code, s.city,
    (SELECT SUM(
        TIMESTAMPDIFF(SECOND, login_time, logout_time) - 
        (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)), 0) 
         FROM attendance_breaks 
         WHERE log_id = l.id AND break_end IS NOT NULL)
     ) 
     FROM attendance_logs l 
     WHERE employee_id = e.id 
       AND logout_time IS NOT NULL 
       AND date >= ? 
       AND date <= ?) AS week1_seconds,
    (SELECT SUM(
        TIMESTAMPDIFF(SECOND, login_time, logout_time) - 
        (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)), 0) 
         FROM attendance_breaks 
         WHERE log_id = l.id AND break_end IS NOT NULL)
     ) 
     FROM attendance_logs l 
     WHERE employee_id = e.id 
       AND logout_time IS NOT NULL 
       AND date >= ? 
       AND date <= ?) AS week2_seconds
    FROM employees e
    JOIN stores s ON e.store_id = s.id
    WHERE e.status = 'Active'
    ORDER BY s.store_code ASC, e.name ASC");
$stmt->execute([$w1_start, $w1_end, $w2_start, $w2_end]);
$payrollList = $stmt->fetchAll();

// KPIs
$totalWeeklyPayout = 0;
$totalHoursWorked = 0;
foreach ($payrollList as $item) {
    $w1_hrs = round(intval($item['week1_seconds'] ?: 0) / 3600, 2);
    $w2_hrs = round(intval($item['week2_seconds'] ?: 0) / 3600, 2);
    $hrs = $w1_hrs + $w2_hrs;
    $totalHoursWorked += $hrs;
    $totalWeeklyPayout += $hrs * floatval($item['hourly_rate']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HQ Payroll Hub — Artée Attendance</title>
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
                    <a class="sidebar-link active" href="payroll.php">
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
                <h4 class="mb-0 text-primary-dark fw-bold" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;">Payroll Management</h4>
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

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 mb-4"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success border-0 mb-4"><?php echo e($success); ?></div>
            <?php endif; ?>

            <!-- Payroll KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--primary) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Est. Total Bi-Weekly Pay</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;">$<?php echo number_format($totalWeeklyPayout, 2); ?></div>
                            </div>
                            <div class="bg-primary-light text-primary rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-cash-stack" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px; border-left: 4px solid var(--success) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Bi-Weekly Hours</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.6rem; line-height: 1;"><?php echo number_format($totalHoursWorked, 2); ?> hrs</div>
                            </div>
                            <div class="bg-success-light text-success rounded p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-hourglass-split" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payroll Ledger card -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <div class="pb-3 border-bottom mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h3 class="fw-bold mb-1 text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">Bi-Weekly Payroll Ledger</h3>
                        <p class="text-muted mb-0" style="font-size: 0.78rem;">
                            Week 1: <strong><?php echo date('m/d/Y', strtotime($w1_start)); ?> - <?php echo date('m/d/Y', strtotime($w1_end)); ?></strong> | 
                            Week 2: <strong><?php echo date('m/d/Y', strtotime($w2_start)); ?> - <?php echo date('m/d/Y', strtotime($w2_end)); ?></strong>
                        </p>
                    </div>

                    <!-- Week Date Filter & Actions -->
                    <div class="d-flex align-items-center gap-2">
                        <form action="payroll.php" method="GET" class="d-flex align-items-center gap-2">
                            <label for="week" class="form-label mb-0 text-secondary" style="font-size: 0.8rem; white-space: nowrap;">Select Week 1 Start:</label>
                            <input type="date" name="week" id="week" class="form-select py-1 px-2.5" value="<?php echo e($selectedDate); ?>" style="font-size: 0.85rem; border-radius: 6px; width: 160px;" onchange="this.form.submit()">
                        </form>
                        <a href="export_payroll.php?week=<?php echo $w1_start; ?>" class="btn btn-success font-semibold px-3 py-1.5" style="border-radius: 6px; font-size: 0.82rem;">
                            <i class="bi bi-file-earmark-excel me-1"></i>Download Excel
                        </a>
                    </div>
                </div>

                <form action="payroll.php?week=<?php echo $selectedDate; ?>" method="POST">
                    <input type="hidden" name="action" value="save_rates">
                    <?php csrf_input(); ?>

                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead>
                                <tr class="text-secondary" style="font-size: 0.78rem; text-transform: uppercase; font-family: 'Outfit', sans-serif; letter-spacing: 0.5px;">
                                    <th>Employee Name</th>
                                    <th>Designation</th>
                                    <th>Location</th>
                                    <th class="text-end" style="width: 130px;">Hourly Rate ($)</th>
                                    <th class="text-end" style="width: 120px;">Week 1 Hours</th>
                                    <th class="text-end" style="width: 120px;">Week 2 Hours</th>
                                    <th class="text-end" style="width: 120px;">Total Hours</th>
                                    <th class="text-end" style="width: 140px;">Total Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payrollList)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted font-mono" style="font-size: 0.82rem;">No active employees found to display.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payrollList as $item): ?>
                                        <?php 
                                        $w1_hours = round(intval($item['week1_seconds'] ?: 0) / 3600, 2);
                                        $w2_hours = round(intval($item['week2_seconds'] ?: 0) / 3600, 2);
                                        $total_hours = $w1_hours + $w2_hours;
                                        $rate = floatval($item['hourly_rate'] ?: 0);
                                        $payout = $total_hours * $rate;
                                        ?>
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
                                            <td class="text-end">
                                                <div class="input-group input-group-sm justify-content-end align-items-center">
                                                    <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 6px 0 0 6px; font-size: 0.82rem; padding: 4px 8px; border-color: var(--border);">$</span>
                                                    <input type="number" step="0.01" min="0" name="rates[<?php echo $item['id']; ?>]" value="<?php echo number_format($rate, 2, '.', ''); ?>" class="form-control text-end border-start-0" required style="max-width: 90px; border-radius: 0 6px 6px 0; font-size: 0.85rem; padding: 4px 8px; border-color: var(--border);">
                                                </div>
                                            </td>
                                            <td class="text-end font-mono fw-semibold text-success" style="font-size: 0.85rem;">
                                                <?php echo number_format($w1_hours, 2); ?> hrs
                                            </td>
                                            <td class="text-end font-mono fw-semibold text-info" style="font-size: 0.85rem;">
                                                <?php echo number_format($w2_hours, 2); ?> hrs
                                            </td>
                                            <td class="text-end font-mono fw-semibold" style="font-size: 0.85rem; background-color: var(--border-light);">
                                                <?php echo number_format($total_hours, 2); ?> hrs
                                            </td>
                                            <td class="text-end font-mono text-primary-dark fw-bold" style="font-size: 0.88rem;">
                                                $<?php echo number_format($payout, 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($payrollList)): ?>
                        <div class="border-top pt-3.5 mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn text-white font-semibold px-4 py-2" style="background: var(--brand-brown); border-color: var(--brand-brown); border-radius: 8px; font-size: 0.85rem;">
                                <i class="bi bi-save me-1.5"></i>Save Rates & Recalculate
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
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
