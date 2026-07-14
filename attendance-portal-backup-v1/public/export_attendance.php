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

$filterStore = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';

// Query
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

$sql .= " ORDER BY l.login_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Filename suffix
$suffix = date('Y-m-d');
if ($filterDate) {
    $suffix = $filterDate;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="artee_attendance_' . $suffix . '.csv"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for proper Excel rendering of characters
fputs($output, "\xEF\xBB\xBF");

// Headers
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
    'Total Break Duration',
    'Net Work Duration',
    'Status'
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
    
    $totalBreakMins = round($totalBreakSeconds / 60);
    $breakDurationStr = $totalBreakMins . ' mins';

    $rowHours = calculate_shift_hours($log['login_time'], $log['logout_time'], $log['date'], $totalBreakSeconds);
    $hours = floor($rowHours);
    $mins = round(($rowHours - $hours) * 60);
    if ($log['logout_time']) {
        $netDurationStr = "{$hours}h {$mins}m";
    } else {
        $netDurationStr = "{$hours}h {$mins}m (Active)";
    }

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
        $breakDurationStr,
        $netDurationStr,
        $log['status']
    ]);
}

fclose($output);
exit;
