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

// 1. Natural Language Query Parser Heuristic
$nlpQuery = trim($_GET['nlp_query'] ?? '');
$nlpResults = null;
$nlpTitle = '';

if (!empty($nlpQuery)) {
    $q = strtolower($nlpQuery);
    
    if (strpos($q, 'late') !== false) {
        $nlpTitle = "Recently Late Employees";
        $stmt = $pdo->query("
            SELECT l.date, e.name AS employee_name, s.city, l.login_time, s.shift_start 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.is_late = 1 
            ORDER BY l.login_time DESC LIMIT 10
        ");
        $nlpResults = $stmt->fetchAll();
    } elseif (strpos($q, 'overtime') !== false || strpos($q, 'ot') !== false) {
        $nlpTitle = "Recent Shifts with Overtime";
        $stmt = $pdo->query("
            SELECT l.date, e.name AS employee_name, s.city, l.calculated_hours, l.calculated_overtime 
            FROM attendance_logs l 
            JOIN employees e ON l.employee_id = e.id 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.calculated_overtime > 0 
            ORDER BY l.calculated_overtime DESC LIMIT 10
        ");
        $nlpResults = $stmt->fetchAll();
    } elseif (strpos($q, 'absent') !== false) {
        $nlpTitle = "Employees Absent Today (" . date('m/d/Y') . ")";
        $stmt = $pdo->query("
            SELECT e.name AS employee_name, s.city 
            FROM employees e 
            JOIN stores s ON e.store_id = s.id 
            WHERE e.deleted_at IS NULL AND e.status = 'Active' 
              AND e.id NOT IN (SELECT DISTINCT employee_id FROM attendance_logs WHERE date = CURDATE())
        ");
        $nlpResults = $stmt->fetchAll();
    } elseif (strpos($q, 'payroll') !== false || strpos($q, 'payout') !== false) {
        $nlpTitle = "Location-Wise Payroll Estimates (Last 30 Days)";
        $stmt = $pdo->query("
            SELECT s.city, s.store_code, 
                   COUNT(DISTINCT e.id) as headcount,
                   ROUND(SUM(l.calculated_hours), 2) as total_hours,
                   ROUND(SUM(l.calculated_hours * e.hourly_rate), 2) as total_payout
            FROM stores s
            JOIN employees e ON e.store_id = s.id
            LEFT JOIN attendance_logs l ON l.employee_id = e.id AND l.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            WHERE e.deleted_at IS NULL
            GROUP BY s.id
            ORDER BY total_payout DESC
        ");
        $nlpResults = $stmt->fetchAll();
    } else {
        // Fallback: Check if user mentions a city
        $matchedCity = null;
        $stmtStores = $pdo->query("SELECT city FROM stores");
        while ($row = $stmtStores->fetch()) {
            if (strpos($q, strtolower($row['city'])) !== false) {
                $matchedCity = $row['city'];
                break;
            }
        }
        
        if ($matchedCity) {
            $nlpTitle = "Recent Logs for $matchedCity Location";
            $stmt = $pdo->prepare("
                SELECT l.date, e.name AS employee_name, s.city, l.login_time, l.logout_time, l.calculated_hours 
                FROM attendance_logs l 
                JOIN employees e ON l.employee_id = e.id 
                JOIN stores s ON l.store_id = s.id 
                WHERE s.city = ? 
                ORDER BY l.login_time DESC LIMIT 10
            ");
            $stmt->execute([$matchedCity]);
            $nlpResults = $stmt->fetchAll();
        } else {
            $nlpTitle = "Query Not Recognized";
            $nlpResults = []; // Empty results
        }
    }
}

// 2. Anomaly Detection Heuristics
$anomalies = [];

// A. Irregular Clock-In Hours (before 6 AM or after 10 PM)
$stmt = $pdo->query("
    SELECT l.*, e.name AS employee_name, s.city 
    FROM attendance_logs l 
    JOIN employees e ON l.employee_id = e.id 
    JOIN stores s ON l.store_id = s.id 
    WHERE HOUR(l.login_time) < 6 OR HOUR(l.login_time) >= 22
");
while ($row = $stmt->fetch()) {
    $anomalies[] = [
        'type' => 'Irregular Time clock-In',
        'desc' => "{$row['employee_name']} logged in at " . date('h:i A', strtotime($row['login_time'])) . " (unusual hour) on " . date('m/d/Y', strtotime($row['date'])),
        'severity' => 'Medium',
        'action' => "Verify shift legitimacy at {$row['city']}"
    ];
}

// B. Extremely Long Shift Hours (Shift exceeding 12 hours)
$stmt = $pdo->query("
    SELECT l.*, e.name AS employee_name, s.city 
    FROM attendance_logs l 
    JOIN employees e ON l.employee_id = e.id 
    JOIN stores s ON l.store_id = s.id 
    WHERE l.calculated_hours > 12.00
");
while ($row = $stmt->fetch()) {
    $anomalies[] = [
        'type' => 'Excessive Duration',
        'desc' => "{$row['employee_name']} logged {$row['calculated_hours']} hours on " . date('m/d/Y', strtotime($row['date'])) . ". Double login or forgot check-out.",
        'severity' => 'High',
        'action' => "Check break history or verify manual clock-out time."
    ];
}

// C. Auto-Closed Shifts (Missed Clock-outs)
$stmt = $pdo->query("
    SELECT l.*, e.name AS employee_name, s.city 
    FROM attendance_logs l 
    JOIN employees e ON l.employee_id = e.id 
    JOIN stores s ON l.store_id = s.id 
    WHERE l.auto_closed = 1 AND l.manager_approved = 0
");
while ($row = $stmt->fetch()) {
    $anomalies[] = [
        'type' => 'Missed Check-Out',
        'desc' => "{$row['employee_name']}'s shift was automatically closed by the system after maximum hours limit on " . date('m/d/Y', strtotime($row['date'])),
        'severity' => 'High',
        'action' => "Needs supervisor verification and approval on admin dashboard."
    ];
}


// 3. Staffing Recommendations Heuristics (Group by day of week)
// Analyze the average logins by day of week to recommend optimal scheduling counts
$staffingRecs = [];
$days = [1 => 'Mondays', 2 => 'Tuesdays', 3 => 'Wednesdays', 4 => 'Thursdays', 5 => 'Fridays', 6 => 'Saturdays', 7 => 'Sundays'];

foreach ($days as $num => $dayName) {
    // Get average logins on this day of week over the last 60 days
    $avgLogins = $pdo->query("
        SELECT COALESCE(ROUND(AVG(daily_count), 1), 0.0)
        FROM (
            SELECT date, COUNT(*) as daily_count 
            FROM attendance_logs 
            WHERE DAYOFWEEK(date) = " . (($num % 7) + 1) . " AND date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            GROUP BY date
        ) as sub
    ")->fetchColumn();
    
    $recCount = max(2, ceil($avgLogins * 1.15)); // Recommend average + 15% safety buffer
    
    $staffingRecs[] = [
        'day' => $dayName,
        'avg' => $avgLogins,
        'recommended' => $recCount,
        'note' => $avgLogins > 5 ? 'High demand day. Schedule key associates.' : 'Standard operations.'
    ];
}

// 4. Trend Forecasting (Simple exponential smoothing estimation)
$forecastingWeeks = [];
$totalLoginsLastWeek = intval($pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn());
$totalLoginsTwoWeeksAgo = intval($pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn());

// Growth rate
$diff = $totalLoginsLastWeek - $totalLoginsTwoWeeksAgo;
$forecastNextWeek = max(0, $totalLoginsLastWeek + round($diff * 0.5));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artee Intelligence — Artée Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .severity-High {
            color: var(--danger);
            font-weight: bold;
        }
        .severity-Medium {
            color: var(--warning);
            font-weight: bold;
        }
        .severity-Low {
            color: var(--info);
            font-weight: bold;
        }
        .ai-banner {
            background: linear-gradient(135deg, #4b2308 0%, #7c441c 100%);
            border-radius: 12px;
            color: #fff;
            padding: 24px;
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
                    <a class="sidebar-link" href="reports.php">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="artee_intelligence.php">
                        <i class="bi bi-cpu"></i>
                        <span>Artee Intelligence</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="quickbooks_integration.php">
                        <i class="bi bi-shuffle"></i>
                        <span>QuickBooks</span>
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
                    <i class="bi bi-cpu-fill me-2 text-primary"></i>Artée Heuristic Intelligence
                </span>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <!-- AI Header Banner -->
            <div class="ai-banner mb-4 d-flex align-items-center justify-content-between shadow-sm">
                <div>
                    <h3 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif;">Artee Intelligence Portal</h3>
                    <p class="text-white-50 mb-0" style="font-size:0.82rem;">Real-time anomaly monitoring, automated staffing advice, and natural language analytics.</p>
                </div>
                <div class="d-none d-md-block" style="font-size: 2.5rem; opacity:0.8;">
                    <i class="bi bi-cpu"></i>
                </div>
            </div>

            <!-- Natural Language Query Section -->
            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                <h5 class="fw-bold text-primary-dark mb-2" style="font-size: 0.95rem;">Natural Language Reporting Interface</h5>
                <p class="text-muted mb-3" style="font-size: 0.78rem;">Ask questions like: <code class="text-danger">"who was late"</code>, <code class="text-danger">"show me overtime shifts"</code>, <code class="text-danger">"absent employees"</code>, or query by city: <code class="text-danger">"raleigh"</code>.</p>
                
                <form method="GET" action="artee_intelligence.php" class="d-flex gap-2">
                    <input type="text" name="nlp_query" class="form-control" placeholder="Ask a question about employee attendance..." value="<?php echo e($nlpQuery); ?>" style="font-size:0.85rem; border-radius:6px;">
                    <button type="submit" class="btn btn-primary px-4" style="border-radius:6px; font-weight:600;">
                        <i class="bi bi-send-fill me-1.5"></i>Analyze
                    </button>
                </form>

                <?php if ($nlpResults !== null): ?>
                    <div class="mt-4 border-top pt-3">
                        <h6 class="fw-bold text-primary-dark mb-3"><i class="bi bi-journal-text me-1.5"></i><?php echo $nlpTitle; ?></h6>
                        <?php if (empty($nlpResults)): ?>
                            <p class="text-muted font-mono" style="font-size:0.8rem;">Query did not return any records. Try checking spelling or search keywords.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle table-hover mb-0" style="font-size: 0.78rem;">
                                    <thead>
                                        <tr class="text-secondary">
                                            <?php foreach (array_keys($nlpResults[0]) as $k): ?>
                                                <?php if (is_string($k)): ?>
                                                    <th><?php echo str_replace('_', ' ', ucfirst($k)); ?></th>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($nlpResults as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $k => $v): ?>
                                                    <?php if (is_string($k)): ?>
                                                        <td><?php echo e($v); ?></td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Anomalies & Staffing Grid -->
            <div class="row g-4 mb-4">
                <!-- Anomaly Detection -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 12px;">
                        <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 0.95rem;"><i class="bi bi-shield-fill-exclamation me-1.5 text-danger"></i>Anomaly Detection Monitor</h5>
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table align-middle table-hover mb-0" style="font-size:0.75rem;">
                                <thead>
                                    <tr class="text-secondary">
                                        <th>Severity</th>
                                        <th>Issue / Description</th>
                                        <th>Recommendation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($anomalies)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted">✓ No anomalies detected in current active logs.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($anomalies as $a): ?>
                                            <tr>
                                                <td><span class="severity-<?php echo $a['severity']; ?>"><?php echo $a['severity']; ?></span></td>
                                                <td>
                                                    <strong><?php echo $a['type']; ?></strong><br>
                                                    <span class="text-secondary"><?php echo $a['desc']; ?></span>
                                                </td>
                                                <td class="text-muted"><?php echo $a['action']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Staffing Decisions & Forecasting -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm p-4 bg-white h-100" style="border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-primary-dark" style="font-size: 0.95rem;"><i class="bi bi-graph-up-arrow me-1.5 text-success"></i>Staffing Scheduling Advice</h5>
                            <span class="badge bg-success-light text-success">Next Week Forecast: <?php echo $forecastNextWeek; ?> shifts</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0" style="font-size:0.75rem;">
                                <thead>
                                    <tr class="text-secondary">
                                        <th>Day of Week</th>
                                        <th>Historical Average</th>
                                        <th>Recommended Scheduling</th>
                                        <th>Decision Rationale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staffingRecs as $s): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo $s['day']; ?></td>
                                            <td class="font-mono"><?php echo $s['avg']; ?> associates</td>
                                            <td class="font-mono text-success fw-bold"><?php echo $s['recommended']; ?> staff</td>
                                            <td class="text-secondary"><?php echo $s['note']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
