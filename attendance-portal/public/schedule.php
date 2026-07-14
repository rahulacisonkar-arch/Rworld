<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
redirect_unauthenticated();

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$userStoreId = $_SESSION['store_id'] ?? null;

// Handle Form Submission
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_schedule') {
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $scheduled_hours = floatval($_POST['scheduled_hours'] ?? 0.00);

    // Fetch employee detail to verify access/store
    $stmtEmp = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'Active'");
    $stmtEmp->execute([$employee_id]);
    $emp = $stmtEmp->fetch();

    if (!$emp) {
        $error = "Selected employee not found or inactive.";
    } elseif ($role === 'Store User' && $emp['store_id'] != $userStoreId) {
        $error = "Access denied: Employee does not belong to your store.";
    } elseif (empty($date) || $scheduled_hours <= 0) {
        $error = "Please fill in all fields with valid values.";
    } else {
        try {
            // Insert/Update schedule (upsert logic by employee + date)
            $stmtCheck = $pdo->prepare("SELECT id FROM future_schedules WHERE employee_id = ? AND date = ?");
            $stmtCheck->execute([$employee_id, $date]);
            $existing = $stmtCheck->fetchColumn();

            if ($existing) {
                $stmtUpdate = $pdo->prepare("UPDATE future_schedules SET scheduled_hours = ? WHERE id = ?");
                $stmtUpdate->execute([$scheduled_hours, $existing]);
                $msg = "Schedule successfully updated!";
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO future_schedules (employee_id, store_id, date, scheduled_hours) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute([$employee_id, $emp['store_id'], $date, $scheduled_hours]);
                $msg = "Schedule successfully added!";
            }
        } catch (Exception $e) {
            $error = "Failed to save schedule: " . $e->getMessage();
        }
    }
}

// Handle Delete Action
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Check access before delete
    if ($role !== 'Super Admin') {
        $error = "Access denied: Only Super Admins can delete schedules.";
    }

    if (empty($error)) {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM future_schedules WHERE id = ?");
            $stmtDel->execute([$delete_id]);
            $msg = "Schedule successfully deleted.";
        } catch (Exception $e) {
            $error = "Failed to delete schedule: " . $e->getMessage();
        }
    }
}

// Fetch all stores for filter / dropdown
if ($role === 'Super Admin') {
    $allStores = $pdo->query("SELECT * FROM stores ORDER BY store_code ASC")->fetchAll();
} else {
    $stmtStore = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
    $stmtStore->execute([$userStoreId]);
    $allStores = $stmtStore->fetchAll();
}

// Fetch employees list for submission dropdown
if ($role === 'Super Admin') {
    $submitEmployees = $pdo->query("SELECT * FROM employees WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
} else {
    $stmtEmps = $pdo->prepare("SELECT * FROM employees WHERE store_id = ? AND status = 'Active' ORDER BY name ASC");
    $stmtEmps->execute([$userStoreId]);
    $submitEmployees = $stmtEmps->fetchAll();
}

// Query schedules list
$sqlSched = "SELECT fs.*, e.name AS employee_name, e.employment_type, s.city, s.store_code 
             FROM future_schedules fs
             JOIN employees e ON fs.employee_id = e.id
             JOIN stores s ON fs.store_id = s.id";
$params = [];

if ($role === 'Store User') {
    $sqlSched .= " WHERE fs.store_id = ?";
    $params[] = $userStoreId;
}

$sqlSched .= " ORDER BY fs.date ASC, e.name ASC";
$stmtSched = $pdo->prepare($sqlSched);
$stmtSched->execute($params);
$rawSchedules = $stmtSched->fetchAll();

// Calculate baseline hours and alerts dynamically for each future schedule
$schedules = [];
$highAlerts = 0;
$medAlerts = 0;
$lowAlerts = 0;

foreach ($rawSchedules as $sched) {
    $empId = $sched['employee_id'];
    $schedDate = $sched['date'];
    
    // Calculate 28-day baseline average hours per week
    $baselineStart = date('Y-m-d', strtotime($schedDate . ' - 28 days'));
    $baselineEnd = date('Y-m-d', strtotime($schedDate . ' - 1 day'));
    
    $stmtLogs = $pdo->prepare("
        SELECT l.*,
               (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)), 0)
                FROM attendance_breaks
                WHERE log_id = l.id AND break_end IS NOT NULL) AS total_break_seconds
        FROM attendance_logs l
        WHERE l.employee_id = ? AND l.date >= ? AND l.date <= ?
    ");
    $stmtLogs->execute([$empId, $baselineStart, $baselineEnd]);
    $logs = $stmtLogs->fetchAll();
    
    // Group logs by week starting Monday
    $weeksData = [];
    foreach ($logs as $log) {
        $monday = date('Y-m-d', strtotime('monday this week', strtotime($log['date'])));
        if (!isset($weeksData[$monday])) {
            $weeksData[$monday] = 0.00;
        }
        $weeksData[$monday] += calculate_shift_hours($log['login_time'], $log['logout_time'], $log['date'], $log['total_break_seconds']);
    }
    
    if (count($weeksData) > 0) {
        $baselineHours = round(array_sum($weeksData) / count($weeksData), 2);
    } else {
        // Fallback baseline based on employment type
        $type = $sched['employment_type'];
        if ($type === 'Salaried') {
            $baselineHours = 40.00;
        } elseif ($type === 'Hourly') {
            $baselineHours = 30.00;
        } else { // Contractual/1099
            $baselineHours = 20.00;
        }
    }
    
    $diff = $sched['scheduled_hours'] - $baselineHours;
    $alertLevel = 'NORMAL';
    if ($diff > 0) {
        if ($diff > 10.00) {
            $alertLevel = 'HIGH';
            $highAlerts++;
        } elseif ($diff > 3.00) {
            $alertLevel = 'MEDIUM';
            $medAlerts++;
        } else {
            $alertLevel = 'LOW';
            $lowAlerts++;
        }
    }
    
    $sched['baseline_hours'] = $baselineHours;
    $sched['diff'] = round($diff, 2);
    $sched['alert_level'] = $alertLevel;
    $schedules[] = $sched;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Future Schedule & Over-Hours Alerts — Artée Attendance</title>
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
                    <a class="sidebar-link active" href="schedule.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Schedule</span>
                    </a>
                </li>
                <?php if ($role === 'Super Admin'): ?>
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
                <?php endif; ?>
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
                <h4 class="mb-0 text-primary-dark fw-bold" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;">Future Scheduling & Alerts</h4>
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

        <div class="container-fluid p-3" style="margin-top: 5px;">

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success border-0 mb-3 shadow-sm" style="border-radius: 8px; padding: 10px 15px;">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo e($msg); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 mb-3 shadow-sm" style="border-radius: 8px; padding: 10px 15px;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <!-- KPIs -->
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-2 bg-white" style="border-radius: 8px; border-left: 4px solid var(--danger) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2;">High Alerts</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.3rem; line-height: 1;"><?php echo $highAlerts; ?></div>
                            </div>
                            <div class="bg-danger-light text-danger rounded p-1" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-exclamation-octagon" style="font-size: 1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-2 bg-white" style="border-radius: 8px; border-left: 4px solid var(--warning) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2;">Medium Alerts</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.3rem; line-height: 1;"><?php echo $medAlerts; ?></div>
                            </div>
                            <div class="bg-warning-light text-warning rounded p-1" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-exclamation-triangle" style="font-size: 1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-2 bg-white" style="border-radius: 8px; border-left: 4px solid var(--info) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2;">Low Alerts</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.3rem; line-height: 1;"><?php echo $lowAlerts; ?></div>
                            </div>
                            <div class="bg-info-light text-info rounded p-1" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-info-circle" style="font-size: 1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-2 bg-white" style="border-radius: 8px; border-left: 4px solid var(--success) !important;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="text-secondary" style="font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2;">Normal</div>
                                <div class="fw-bold text-primary-dark mt-1" style="font-size: 1.3rem; line-height: 1;"><?php echo count($schedules) - ($highAlerts + $medAlerts + $lowAlerts); ?></div>
                            </div>
                            <div class="bg-success-light text-success rounded p-1" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-check-circle" style="font-size: 1rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Submit Form Card -->
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius: 10px;">
                        <h5 class="fw-bold text-primary-dark mb-2" style="font-size: 0.95rem;">Provide Future Schedule</h5>
                        <form method="POST" action="schedule.php">
                            <input type="hidden" name="action" value="add_schedule">
                            
                            <div class="mb-2">
                                <label class="form-label fw-semibold text-secondary" style="font-size: 0.72rem; margin-bottom: 2px;">Select Employee</label>
                                <select name="employee_id" class="form-select" style="font-size: 0.8rem; padding: 4px 8px;" required>
                                    <option value="">-- Choose Employee --</option>
                                    <?php foreach ($submitEmployees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>">
                                            <?php echo e($emp['name']); ?> ([<?php echo e($emp['employment_type'] === 'Contractual/1099' ? '1099' : $emp['employment_type']); ?>])
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label fw-semibold text-secondary" style="font-size: 0.72rem; margin-bottom: 2px;">Target Date</label>
                                <input type="date" name="date" class="form-control" style="font-size: 0.8rem; padding: 4px 8px;" value="<?php echo date('Y-m-d', strtotime('next monday')); ?>" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-semibold text-secondary" style="font-size: 0.72rem; margin-bottom: 2px;">Scheduled Hours (Weekly)</label>
                                <input type="number" step="0.01" name="scheduled_hours" class="form-control" style="font-size: 0.8rem; padding: 4px 8px;" placeholder="e.g. 45.00" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-1.5 fw-semibold" style="background: var(--brand-brown); border: none; font-size: 0.8rem;">
                                <i class="bi bi-plus-circle me-1"></i> Submit Schedule
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Analysis & Table Card -->
                <div class="col-lg-9">
                    <div class="card border-0 shadow-sm bg-white" style="border-radius: 10px; overflow: hidden;">
                        <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center bg-light">
                            <h5 class="mb-0 fw-bold text-primary-dark" style="font-size: 0.95rem;">Schedule Alert Analysis</h5>
                            <span class="badge bg-secondary" style="font-size: 0.7rem;"><?php echo count($schedules); ?> Total</span>
                        </div>
                        <div class="table-responsive" style="max-height: 290px; overflow-y: auto;">
                            <table class="table align-middle mb-0" style="font-size: 0.8rem;">
                                <thead class="table-light text-uppercase" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.3px;">
                                    <tr>
                                        <th class="px-3 py-2">Store</th>
                                        <th class="py-2">Employee</th>
                                        <th class="py-2">Type</th>
                                        <th class="py-2">Date</th>
                                        <th class="py-2">Sched</th>
                                        <th class="py-2">Baseline</th>
                                        <th class="py-2">Alert</th>
                                        <?php if ($role === 'Super Admin'): ?>
                                        <th class="px-3 py-2 text-end">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($schedules)): ?>
                                        <tr>
                                            <td colspan="<?php echo $role === 'Super Admin' ? '8' : '7'; ?>" class="text-center text-muted py-5">
                                                <i class="bi bi-calendar-x d-block fs-2 mb-2 text-secondary" style="opacity: 0.4;"></i>
                                                No future schedules provided yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($schedules as $item): ?>
                                            <?php 
                                            $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                            if ($item['alert_level'] === 'HIGH') {
                                                $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            } elseif ($item['alert_level'] === 'MEDIUM') {
                                                $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                            } elseif ($item['alert_level'] === 'LOW') {
                                                $badgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                                            }
                                            ?>
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <span class="fw-bold text-dark"><?php echo e($item['store_code']); ?></span>
                                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo e($item['city']); ?></div>
                                                </td>
                                                <td class="py-2"><span class="fw-semibold text-dark" style="font-size: 0.8rem;"><?php echo e($item['employee_name']); ?></span></td>
                                                <td class="py-2"><span class="badge bg-light text-dark border" style="font-size: 0.72rem;"><?php echo e($item['employment_type'] === 'Contractual/1099' ? '1099' : $item['employment_type']); ?></span></td>
                                                <td class="py-2"><?php echo date('m/d/Y', strtotime($item['date'])); ?></td>
                                                <td class="py-2 fw-bold"><?php echo number_format($item['scheduled_hours'], 2); ?> hrs</td>
                                                <td class="py-2 text-secondary"><?php echo number_format($item['baseline_hours'], 2); ?> hrs</td>
                                                <td class="py-2">
                                                    <span class="badge <?php echo $badgeClass; ?> px-1.5 py-0.5" style="font-size: 0.72rem; font-weight: 600;">
                                                        <?php if ($item['alert_level'] !== 'NORMAL'): ?>
                                                            <i class="bi bi-exclamation-triangle-fill me-0.5"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-check-circle-fill me-0.5"></i>
                                                        <?php endif; ?>
                                                        <?php echo $item['alert_level']; ?>
                                                        <?php if ($item['diff'] > 0): ?>
                                                            (+<?php echo $item['diff']; ?>)
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <?php if ($role === 'Super Admin'): ?>
                                                <td class="px-3 py-2 text-end">
                                                    <a href="schedule.php?delete_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" style="border-radius: 6px; padding: 1px 4px;" onclick="return confirm('Are you sure you want to delete this schedule?');">
                                                        <i class="bi bi-trash" style="font-size: 0.78rem;"></i>
                                                    </a>
                                                </td>
                                                <?php endif; ?>
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

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live time clock update
    function updateLiveTime() {
        const now = new Date();
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
        document.getElementById('current-live-time').innerText = now.toLocaleTimeString('en-US', timeOptions);
        document.getElementById('current-live-date').innerText = now.toLocaleDateString('en-US', dateOptions);
    }
    setInterval(updateLiveTime, 1000);
    updateLiveTime();
</script>
</body>
</html>
