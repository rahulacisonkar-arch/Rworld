<!-- Day Book — matches ReportController::daybook() variables ($transactions, $date) -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-day mr-2 text-primary"></i>Day Book</h1>
        <div class="page-subtitle">Daily transaction register — <?= date('l, F j, Y', strtotime($date)) ?></div>
    </div>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<!-- Date Filter -->
<div class="qb-card mb-4">
    <div class="qb-card-body">
        <form method="GET" class="form-row align-items-end">
            <div class="form-group col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
            </div>
            <div class="form-group col-md-4">
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i> Load Day Book</button>
            </div>
        </form>
    </div>
</div>

<!-- Day Summary Cards -->
<?php
$salesTotal   = array_sum(array_map(fn($t) => $t['type'] === 'Sale' ? ($t['net_amount'] ?? 0) : 0, $transactions));
$purchTotal   = array_sum(array_map(fn($t) => $t['type'] === 'Purchase' ? ($t['net_amount'] ?? 0) : 0, $transactions));
$expTotal     = array_sum(array_map(fn($t) => $t['type'] === 'Expense' ? ($t['net_amount'] ?? 0) : 0, $transactions));
$netCash      = $salesTotal - $purchTotal - $expTotal;
?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="qb-card stat-card stat-green">
            <div class="stat-value">$<?= number_format($salesTotal, 2) ?></div>
            <div class="stat-label">Sales</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="qb-card stat-card stat-blue">
            <div class="stat-value">$<?= number_format($purchTotal, 2) ?></div>
            <div class="stat-label">Purchases</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="qb-card stat-card stat-red">
            <div class="stat-value">$<?= number_format($expTotal, 2) ?></div>
            <div class="stat-label">Expenses</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="qb-card stat-card <?= $netCash >= 0 ? 'stat-purple' : 'stat-orange' ?>">
            <div class="stat-value">$<?= number_format(abs($netCash), 2) ?></div>
            <div class="stat-label">Net <?= $netCash >= 0 ? 'Surplus' : 'Deficit' ?></div>
        </div>
    </div>
</div>

<!-- Transaction Table -->
<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Document No</th>
                        <th>Type</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="3" class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                        No transactions recorded for <?= date('m/d/Y', strtotime($date)) ?>.
                    </td></tr>
                <?php else: foreach ($transactions as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['doc_no']) ?></strong></td>
                        <td>
                            <span class="badge badge-<?= [
                                'Sale'=>'success','Purchase'=>'info','Expense'=>'danger'
                            ][$t['type']] ?? 'secondary' ?>">
                                <?= htmlspecialchars($t['type']) ?>
                            </span>
                        </td>
                        <td class="text-right font-weight-bold">$<?= number_format($t['net_amount'] ?? 0, 2) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
