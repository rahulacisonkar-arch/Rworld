<!-- GST / Sales Tax Report — matches ReportController::gst() variables ($taxSummary, $startDate, $endDate) -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-receipt mr-2 text-warning"></i>Tax Report</h1>
        <div class="page-subtitle">Sales Tax collected by period for Artee Fabrics and Home</div>
    </div>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<!-- Period Filter -->
<div class="qb-card mb-4">
    <div class="qb-card-body">
        <form method="GET" class="form-row align-items-end">
            <div class="form-group col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="form-group col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="form-group col-md-4">
                <button type="submit" class="btn btn-warning btn-block text-white"><i class="fas fa-filter mr-1"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<?php
$totalCollected = array_sum(array_column($taxSummary, 'total_collected'));
?>

<!-- Summary Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="qb-card stat-card stat-orange">
            <div class="stat-value">$<?= number_format($totalCollected, 2) ?></div>
            <div class="stat-label">Total Tax Collected</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card stat-card stat-blue">
            <div class="stat-value"><?= date('M Y', strtotime($startDate)) ?></div>
            <div class="stat-label">Period Start</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card stat-card stat-purple">
            <div class="stat-value"><?= date('M Y', strtotime($endDate)) ?></div>
            <div class="stat-label">Period End</div>
        </div>
    </div>
</div>

<!-- Tax Summary Table -->
<div class="qb-card">
    <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-receipt mr-1 text-warning"></i>Tax Collected Breakdown</span></div>
    <div class="qb-card-body p-0">
        <table class="qb-table">
            <thead>
                <tr>
                    <th>Tax Type</th>
                    <th class="text-right">Amount Collected</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($taxSummary)): ?>
                <tr><td colspan="2" class="text-center py-4 text-muted">
                    No tax records for the selected period.
                </td></tr>
            <?php else: foreach ($taxSummary as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['tax_name']) ?></td>
                    <td class="text-right text-success font-weight-bold">$<?= number_format($row['total_collected'], 2) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($taxSummary)): ?>
            <tfoot>
                <tr class="font-weight-bold">
                    <td class="text-right">TOTAL TAX COLLECTED:</td>
                    <td class="text-right text-warning">$<?= number_format($totalCollected, 2) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
