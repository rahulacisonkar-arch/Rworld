<!-- P&L Statement — matches ReportController::pl() variables ($sales, $purchases, $expenses, $netProfit, $startDate, $endDate) -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-balance-scale mr-2 text-primary"></i>Profit & Loss Statement</h1>
        <div class="page-subtitle">Artee Fabrics and Home — Income vs Expenditure</div>
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
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-calculator mr-1"></i> Generate P&L</button>
            </div>
        </form>
    </div>
</div>

<?php
$grossProfit = $sales - $purchases;
?>

<div class="row">
    <div class="col-md-7">
        <!-- P&L Table -->
        <div class="qb-card">
            <div class="qb-card-header"><span class="qb-card-title">Statement of Operations</span>
                <small class="text-muted ml-2"><?= date('m/d/Y', strtotime($startDate)) ?> – <?= date('m/d/Y', strtotime($endDate)) ?></small>
            </div>
            <div class="qb-card-body p-0">
                <table class="qb-table">
                    <thead><tr><th>Description</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        <tr class="table-light"><td colspan="2"><strong>INCOME</strong></td></tr>
                        <tr><td class="pl-4">Net Sales Revenue</td><td class="text-right text-success">$<?= number_format($sales, 2) ?></td></tr>

                        <tr class="table-light"><td colspan="2"><strong>COST OF GOODS SOLD</strong></td></tr>
                        <tr><td class="pl-4">Total Purchases</td><td class="text-right text-danger">($<?= number_format($purchases, 2) ?>)</td></tr>

                        <tr class="font-weight-bold table-light">
                            <td>GROSS PROFIT</td>
                            <td class="text-right <?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $grossProfit < 0 ? '(' : '' ?>$<?= number_format(abs($grossProfit), 2) ?><?= $grossProfit < 0 ? ')' : '' ?>
                            </td>
                        </tr>

                        <tr class="table-light"><td colspan="2"><strong>OPERATING EXPENSES</strong></td></tr>
                        <tr><td class="pl-4">Total Expenses</td><td class="text-right text-danger">($<?= number_format($expenses, 2) ?>)</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold" style="border-top:2px solid #343a40;background:#f8f9fa;font-size:1.05em">
                            <td>NET PROFIT / (LOSS)</td>
                            <td class="text-right <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $netProfit < 0 ? '(' : '' ?>$<?= number_format(abs($netProfit), 2) ?><?= $netProfit < 0 ? ')' : '' ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="qb-card stat-card stat-green mb-3">
            <div class="stat-value">$<?= number_format($sales, 2) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="qb-card stat-card stat-blue mb-3">
            <div class="stat-value">$<?= number_format($grossProfit, 2) ?></div>
            <div class="stat-label">Gross Profit</div>
        </div>
        <div class="qb-card stat-card <?= $netProfit >= 0 ? 'stat-purple' : 'stat-red' ?> mb-3">
            <div class="stat-value">$<?= number_format(abs($netProfit), 2) ?></div>
            <div class="stat-label">Net <?= $netProfit >= 0 ? 'Profit' : 'Loss' ?></div>
        </div>
        <?php if ($sales > 0): ?>
        <div class="qb-card stat-card stat-orange">
            <div class="stat-value"><?= number_format(($netProfit / $sales) * 100, 1) ?>%</div>
            <div class="stat-label">Net Profit Margin</div>
        </div>
        <?php endif; ?>
    </div>
</div>
