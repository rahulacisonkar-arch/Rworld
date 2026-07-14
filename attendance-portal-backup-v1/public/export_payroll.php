<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
redirect_unauthenticated();

if ($_SESSION['role'] !== 'Super Admin') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

// Week Filter (Bi-weekly period starting from selected Monday)
$weekCommencing = isset($_GET['week']) ? trim($_GET['week']) : date('Y-m-d', strtotime('monday this week'));

$w1_start = $weekCommencing;
$w1_end = date('Y-m-d', strtotime($weekCommencing . ' + 6 days'));
$w2_start = date('Y-m-d', strtotime($weekCommencing . ' + 7 days'));
$w2_end = date('Y-m-d', strtotime($weekCommencing . ' + 13 days'));

// Query all logs in the 2-week period
$stmt = $pdo->prepare("SELECT l.*, e.name AS employee_name, e.email AS employee_email, e.hourly_rate 
    FROM attendance_logs l
    JOIN employees e ON l.employee_id = e.id
    WHERE l.date >= ? AND l.date <= ?
    ORDER BY e.name ASC, l.date ASC, l.login_time ASC");
$stmt->execute([$w1_start, $w2_end]);
$logs = $stmt->fetchAll();

// Pre-calculate 2-week total worked hours for each employee
$empTotalHours = [];
foreach ($logs as $log) {
    $empId = $log['employee_id'];
    if (!isset($empTotalHours[$empId])) {
        $empTotalHours[$empId] = 0.00;
    }
    
    $stmtBreaks = $pdo->prepare("SELECT * FROM attendance_breaks WHERE log_id = ? ORDER BY break_start ASC");
    $stmtBreaks->execute([$log['id']]);
    $breaksList = $stmtBreaks->fetchAll();

    $logBreakSeconds = 0;
    foreach ($breaksList as $b) {
        if ($b['break_end']) {
            $logBreakSeconds += strtotime($b['break_end']) - strtotime($b['break_start']);
        }
    }

    $empTotalHours[$empId] += calculate_shift_hours($log['login_time'], $log['logout_time'], $log['date'], $logBreakSeconds);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="artee_payroll.csv"');

$output = fopen('php://output', 'w');

// UTF-8 BOM
fputs($output, "\xEF\xBB\xBF");

// Headers matching the requested order exactly
fputcsv($output, [
    'Employee Name',
    'Date',
    'Checkin',
    'Break in',
    'Break out',
    'Check out',
    'Total hours',
    'Hourly rates',
    'Week 1',
    'Week 2',
    'total of 2 week',
    'Id wise sales'
]);

foreach ($logs as $log) {
    // Fetch breaks for this log
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

    // Calculate shift net seconds
    $rowHours = calculate_shift_hours($log['login_time'], $log['logout_time'], $log['date'], $totalBreakSeconds);

    // Determine Week 1 vs Week 2
    $logDate = $log['date'];
    $w1_hours = 0;
    $w2_hours = 0;
    if ($logDate >= $w1_start && $logDate <= $w1_end) {
        $w1_hours = $rowHours;
    } elseif ($logDate >= $w2_start && $logDate <= $w2_end) {
        $w2_hours = $rowHours;
    }

    // Overall 2-week hours for this employee
    $total2WeekHours = $empTotalHours[$log['employee_id']] ?? 0.00;

    // Quickbill Sales lookup
    $totalPeriodSales = 0.00;
    if ($pdoQB) {
        // Find staff id
        $stmtStaff = $pdoQB->prepare("SELECT id FROM sales_staff WHERE email = ? OR name = ? LIMIT 1");
        $stmtStaff->execute([$log['employee_email'], $log['employee_name']]);
        $staffId = $stmtStaff->fetchColumn();

        if ($staffId) {
            // Total 2-Week Sales (Id wise sales)
            $stmtTPS = $pdoQB->prepare("SELECT SUM(net_amount) FROM sales_header WHERE sales_staff_id = ? AND doc_date >= ? AND doc_date <= ? AND status = 'confirmed'");
            $stmtTPS->execute([$staffId, $w1_start, $w2_end]);
            $totalPeriodSales = floatval($stmtTPS->fetchColumn() ?: 0);
        }
    }

    // Output row matching columns
    fputcsv($output, [
        $log['employee_name'],
        date('m/d/Y', strtotime($logDate)),
        date('h:i:s A', strtotime($log['login_time'])),
        $breakInStr ?: '—',
        $breakOutStr ?: '—',
        $log['logout_time'] ? date('h:i:s A', strtotime($log['logout_time'])) : 'Active',
        number_format($rowHours, 2),
        number_format(floatval($log['hourly_rate']), 2),
        number_format($w1_hours, 2),
        number_format($w2_hours, 2),
        number_format($total2WeekHours, 2),
        number_format($totalPeriodSales, 2)
    ]);
}

fclose($output);
exit;
