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

// Filters for timesheet sync
$filterStartDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d', strtotime('monday this week'));
$filterEndDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d', strtotime('sunday this week'));

// Fetch aggregated timesheets ready for sync
$stmt = $pdo->prepare("
    SELECT e.name AS employee_name, e.email AS employee_email, e.hourly_rate, s.city, s.store_code,
           COALESCE(SUM(l.calculated_hours), 0.00) AS total_hours,
           COALESCE(SUM(l.calculated_overtime), 0.00) AS total_ot
    FROM employees e 
    JOIN stores s ON e.store_id = s.id 
    JOIN attendance_logs l ON l.employee_id = e.id
    WHERE e.deleted_at IS NULL AND l.date >= ? AND l.date <= ?
    GROUP BY e.id
    HAVING total_hours > 0
    ORDER BY e.name ASC
");
$stmt->execute([$filterStartDate, $filterEndDate]);
$syncData = $stmt->fetchAll();

// Handle exports
$action = $_GET['action'] ?? '';
if ($action === 'export_qb_json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="quickbooks_timesheets_' . $filterStartDate . '_to_' . $filterEndDate . '.json"');
    
    $timeActivities = [];
    foreach ($syncData as $item) {
        $timeActivities[] = [
            'TimeActivity' => [
                'NameOf' => 'Employee',
                'EmployeeRef' => [
                    'name' => $item['employee_name'],
                    'value' => $item['employee_email']
                ],
                'TxnDate' => $filterStartDate, // Representative date
                'Hours' => floatval($item['total_hours']),
                'HourlyRate' => floatval($item['hourly_rate']),
                'BillableStatus' => 'NotBillable',
                'ClassRef' => [
                    'name' => $item['city'] . ' Location',
                    'value' => $item['store_code']
                ],
                'Description' => 'Synced from Artee Attendance Portal. Total Overtime included: ' . $item['total_ot'] . ' hrs'
            ]
        ];
    }
    
    echo json_encode([
        'Header' => [
            'Source' => 'Artee Attendance Portal',
            'SyncPeriodStart' => $filterStartDate,
            'SyncPeriodEnd' => $filterEndDate,
            'RecordCount' => count($timeActivities),
            'GeneratedAt' => date('Y-m-d H:i:s')
        ],
        'TimeActivities' => $timeActivities
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickBooks Online Integration — Artée Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .qb-card {
            border-top: 4px solid #2CA01C !important; /* QuickBooks Brand Green */
        }
        .status-badge-connected {
            background-color: rgba(44, 160, 28, 0.15);
            color: #2CA01C;
            font-weight: 700;
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
                    <a class="sidebar-link" href="artee_intelligence.php">
                        <i class="bi bi-cpu"></i>
                        <span>Artee Intelligence</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="quickbooks_integration.php">
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
                    <i class="bi bi-shuffle me-2 text-success"></i>QuickBooks Online Integration Hub
                </span>
                <span class="badge status-badge-connected px-2.5 py-1.5 rounded"><i class="bi bi-cloud-check-fill me-1"></i>Connected to QuickBooks API</span>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <!-- Connection Status and Setup Config -->
            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm p-4 bg-white qb-card h-100" style="border-radius: 12px;">
                        <h5 class="fw-bold mb-2 text-primary-dark" style="font-size: 1rem;">Setup Config &amp; Mapping Rules</h5>
                        <p class="text-secondary" style="font-size:0.8rem;">Configure how timesheet entries from Artee Attendance map into QuickBooks Online transaction fields.</p>
                        
                        <div class="row g-3 mt-1" style="font-size: 0.8rem;">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">QuickBooks Customer/Job mapping</label>
                                <select class="form-select" disabled>
                                    <option>Map Store Location -> QuickBooks Class (Default)</option>
                                    <option>Map Store Location -> QuickBooks Customer Sub-Job</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">QuickBooks Service Item mapping</label>
                                <select class="form-select" disabled>
                                    <option>Map Designation -> Service Product Item (Default)</option>
                                    <option>Map Designation -> Service Class Reference</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Billable Status Defaults</label>
                                <select class="form-select" disabled>
                                    <option>Not Billable (Default)</option>
                                    <option>Billable to Customers</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Overtime Pay Items</label>
                                <select class="form-select" disabled>
                                    <option>Overtime Hourly Rate -> QB Overtime Earnings (Default)</option>
                                    <option>Overtime Hours -> Regular Earnings</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4 bg-white qb-card h-100" style="border-radius: 12px;">
                        <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 1rem;">Direct Sync Actions</h5>
                        <p class="text-secondary" style="font-size:0.8rem;">Select a date range to generate payroll files matching the QuickBooks Time Activity schema.</p>
                        
                        <form method="GET" action="quickbooks_integration.php" class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-semibold" style="font-size:0.75rem;">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo e($filterStartDate); ?>" onchange="this.form.submit();">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold" style="font-size:0.75rem;">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo e($filterEndDate); ?>" onchange="this.form.submit();">
                            </div>
                            <div class="col-12 mt-3 pt-2">
                                <a href="quickbooks_integration.php?action=export_qb_json&start_date=<?php echo $filterStartDate; ?>&end_date=<?php echo $filterEndDate; ?>" class="btn btn-success w-100" style="background:#2CA01C; border-color:#2CA01C;">
                                    <i class="bi bi-filetype-json me-2"></i>Download QB Timesheet JSON
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sync Overview Ledger -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <h5 class="fw-bold mb-3 text-primary-dark" style="font-size: 1rem; font-family: 'Outfit', sans-serif;">Timesheet Records Ready for QuickBooks Sync</h5>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" style="font-size:0.82rem;">
                        <thead>
                            <tr class="text-secondary" style="font-size:0.75rem; text-transform: uppercase;">
                                <th>Employee</th>
                                <th>Location Class</th>
                                <th>Hourly Rate</th>
                                <th>Regular Hours</th>
                                <th>Overtime Hours</th>
                                <th>Total Hours</th>
                                <th>Estimated Amount</th>
                                <th class="text-center">Integration Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($syncData)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No attendance logs found in this period to sync.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($syncData as $row): ?>
                                    <?php 
                                    $regHours = floatval($row['total_hours']) - floatval($row['total_ot']);
                                    $estPay = floatval($row['hourly_rate']) * floatval($row['total_hours']);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold text-primary-dark">
                                            <?php echo e($row['employee_name']); ?><br>
                                            <span class="text-muted" style="font-size:0.72rem;"><?php echo e($row['employee_email']); ?></span>
                                        </td>
                                        <td><?php echo e($row['city']); ?> [<?php echo e($row['store_code']); ?>]</td>
                                        <td class="font-mono">$<?php echo number_format($row['hourly_rate'], 2); ?></td>
                                        <td class="font-mono"><?php echo number_format($regHours, 2); ?> hrs</td>
                                        <td class="font-mono text-danger"><?php echo number_format($row['total_ot'], 2); ?> hrs</td>
                                        <td class="font-mono fw-bold"><?php echo number_format($row['total_hours'], 2); ?> hrs</td>
                                        <td class="font-mono fw-bold text-success">$<?php echo number_format($estPay, 2); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-success-light text-success rounded"><i class="bi bi-arrow-repeat me-1"></i>Ready for Sync</span>
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
</body>
</html>
