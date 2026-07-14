<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();

$month = intval($_GET['month'] ?? 5); // default to May
if ($month < 1 || $month > 12) {
    $month = 5;
}

$reportType = $_GET['type'] ?? 'tele_int'; // 'tele_int' or 'gas_elec'
if (!in_array($reportType, ['tele_int', 'gas_elec'])) {
    $reportType = 'tele_int';
}

$monthsMap = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$monthName = $monthsMap[$month] ?? 'May';

$scriptName = ($reportType === 'gas_elec') ? 'generate_yoy_gas_elec.py' : 'generate_yoy_report.py';
$fileKeyword = ($reportType === 'gas_elec') ? 'YoY_Gas_Elec_Analysis' : 'YoY_Bill_Analysis';

// Handle Excel file download action
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $scriptPath = dirname(__DIR__) . "/" . $scriptName;
    $command = "uv run " . escapeshellarg($scriptPath) . " " . escapeshellarg($month);
    
    $output = shell_exec($command);
    $filePath = trim($output);
    
    // Strip warnings if any in stdout
    if (strpos($filePath, $fileKeyword) !== false) {
        $lines = explode("\n", $filePath);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, $fileKeyword) !== false && file_exists($line)) {
                $filePath = $line;
                break;
            }
        }
    }

    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        die("Error: Generated file not found. System Output: " . $output);
    }
}

// Fetch JSON data for preview
$scriptPath = dirname(__DIR__) . "/" . $scriptName;
$command = "uv run " . escapeshellarg($scriptPath) . " " . escapeshellarg($month) . " json";
$output = shell_exec($command);

$previewData = json_decode($output, true);
if ($previewData === null) {
    // Strip warnings and try again
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        $decoded = json_decode(trim($line), true);
        if ($decoded !== null) {
            $previewData = $decoded;
            break;
        }
    }
}

$pageTitle = 'YoY Bill Analysis';
require_once dirname(__DIR__) . '/src/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-primary-dark">YoY Combined Expense Analysis</h3>
        <p class="text-muted mb-0">Compare historical utility costs across fiscal years for the same month.</p>
    </div>
</div>

<div class="row">
    <!-- Filter Selector Card -->
    <div class="col-12 col-md-4 mb-4">
        <div class="card-command">
            <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-filter-square-fill me-2 text-primary"></i>Select Analysis Parameters</h5>
            <form action="yoy_report.php" method="GET">
                <!-- Report Type Dropdown -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Utility Scope</label>
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="tele_int" <?php echo $reportType === 'tele_int' ? 'selected' : ''; ?>>Telephone &amp; Internet</option>
                        <option value="gas_elec" <?php echo $reportType === 'gas_elec' ? 'selected' : ''; ?>>Gas &amp; Electricity</option>
                    </select>
                </div>

                <!-- Compare Month Dropdown -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Compare Month</label>
                    <select class="form-select" name="month" onchange="this.form.submit()">
                        <?php foreach ($monthsMap as $mNum => $mName): ?>
                            <option value="<?php echo $mNum; ?>" <?php echo $month === $mNum ? 'selected' : ''; ?>>
                                <?php echo $mName; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <a href="yoy_report.php?action=download&month=<?php echo $month; ?>&type=<?php echo $reportType; ?>" class="btn btn-command w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-file-earmark-excel-fill"></i> Download YoY Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Table Card -->
    <div class="col-12 col-md-8 mb-4">
        <div class="card-command">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h5 class="fw-bold mb-0 text-success">
                    <i class="bi bi-table me-2"></i>Analysis Preview: <?php echo $monthName; ?> (YoY)
                </h5>
                <span class="badge bg-primary text-white">
                    <?php echo ($reportType === 'gas_elec') ? 'Gas &amp; Electricity Combined' : 'Telephone &amp; Internet Combined'; ?>
                </span>
            </div>

            <?php if (!$previewData || empty($previewData['data'])): ?>
                <div class="alert alert-warning border-0 py-3 px-4 mb-0" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> No data found for the month of <?php echo $monthName; ?> in the repository.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0" style="vertical-align: middle;">
                        <thead>
                            <tr>
                                <th>Store Code</th>
                                <th>Store Name</th>
                                <?php foreach ($previewData['years'] as $yr): ?>
                                    <th class="text-end"><?php echo $yr; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totals = array_fill_keys($previewData['years'], 0.0);
                            foreach ($previewData['data'] as $row): 
                            ?>
                                <tr>
                                    <td><strong>[<?php echo sprintf('%02d', $row['code']); ?>]</strong></td>
                                    <td><?php echo e($row['store']); ?></td>
                                    <?php foreach ($previewData['years'] as $yr): ?>
                                        <td class="text-end">
                                            <?php 
                                            $val = floatval($row[$yr]);
                                            $totals[$yr] += $val;
                                            echo $val > 0 ? '$' . number_format($val, 2) : '<span class="text-muted">&mdash;</span>'; 
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top border-dark fw-bold">
                            <tr>
                                <td colspan="2" class="text-end">TOTAL EXPENSE:</td>
                                <?php foreach ($previewData['years'] as $yr): ?>
                                    <td class="text-end text-primary-dark">
                                        $<?php echo number_format($totals[$yr], 2); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="bi bi-info-circle-fill text-primary"></i> Click the <strong>Download YoY Excel</strong> button to obtain a formatted spreadsheet containing a complete grouped Bar Chart comparing these figures automatically.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/src/footer.php';
?>
