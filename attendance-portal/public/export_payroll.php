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

// Read dates
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = trim($_GET['start_date']);
    $end_date = trim($_GET['end_date']);
} else {
    // Default 2-week period
    $selectedDate = isset($_GET['week']) ? trim($_GET['week']) : date('Y-m-d');
    $weekCommencing = date('Y-m-d', strtotime('monday this week', strtotime($selectedDate)));
    $start_date = $weekCommencing;
    $end_date = date('Y-m-d', strtotime($weekCommencing . ' + 13 days'));
}

// Generate unique temp file path in workspace temp/ or system temp
$temp_dir = sys_get_temp_dir();
$temp_file = $temp_dir . DIRECTORY_SEPARATOR . 'payroll_report_' . uniqid() . '.xlsx';

// Python path and execution script
$python_exe = 'c:\\Users\\Artee Admin\\Desktop\\browser-use-main\\.venv\\Scripts\\python.exe';
$script_path = dirname(__DIR__) . '/src/generate_sales_report.py';

// Escape shell arguments
$cmd = sprintf(
    '"%s" "%s" %s %s %s',
    $python_exe,
    $script_path,
    escapeshellarg($start_date),
    escapeshellarg($end_date),
    escapeshellarg($temp_file)
);

// Execute command
exec($cmd, $output, $return_var);

if ($return_var !== 0 || !file_exists($temp_file)) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Failed to generate Excel report. Details: " . implode("\n", $output));
}

// Set headers to download Excel
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="SALES PER HOUR REPORT (' . $start_date . ' - ' . $end_date . ').xlsx"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($temp_file));

// Output file stream
readfile($temp_file);

// Clean up
unlink($temp_file);
exit;
