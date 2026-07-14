<!-- Purchase Report View — matches ReportController::purchase() variables -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-chart-line mr-2 text-primary"></i>Purchase Report</h1>
        <div class="page-subtitle">Analyze procurement spend by date range</div>
    </div>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<!-- Filters -->
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
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i> Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="qb-card stat-card stat-blue">
            <div class="stat-value">$<?= number_format($summary['total_net'] ?? 0, 2) ?></div>
            <div class="stat-label">Total Purchased</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card stat-card stat-purple">
            <div class="stat-value"><?= $summary['total_tx'] ?? 0 ?></div>
            <div class="stat-label">Invoice Count</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card stat-card stat-green">
            <div class="stat-value">$<?= ($summary['total_tx'] ?? 0) > 0 ? number_format(($summary['total_net'] ?? 0) / $summary['total_tx'], 2) : '0.00' ?></div>
            <div class="stat-label">Avg Invoice Value</div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th class="text-right">Net Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No purchase data for the selected period.</td></tr>
                <?php else: foreach ($purchases as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no'] ?? $row['invoice_no'] ?? '—') ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'] ?? $row['invoice_date'])) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
                        <td class="text-right font-weight-bold">$<?= number_format($row['net_amount'] ?? 0, 2) ?></td>
                        <td><span class="badge badge-success"><?= ucfirst($row['status'] ?? 'posted') ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($purchases)): ?>
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="3" class="text-right">GRAND TOTAL:</td>
                        <td class="text-right text-primary">$<?= number_format($summary['total_net'] ?? 0, 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
