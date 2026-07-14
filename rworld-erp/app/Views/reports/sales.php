<?php
/**
 * QuickBill POS - Sales Report View
 */
?>
<div class="page-header">
    <div>
        <h1>Sales Report</h1>
        <div class="page-subtitle">Historical transactional analysis, filters, and payment state summaries</div>
    </div>
</div>

<!-- Filters Panel -->
<div class="qb-card mb-4">
    <div class="qb-card-body">
        <form method="GET" action="<?= APP_URL ?>/report/sales">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summaries KPI Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <div class="label">Transactions</div>
                <div class="value"><?= number_format($summary['total_transactions']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <div class="label">Gross Sales</div>
                <div class="value">$<?= number_format($summary['total_gross'], 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-percent"></i></div>
            <div class="stat-info">
                <div class="label">Tax Collected</div>
                <div class="value">$<?= number_format($summary['total_tax'], 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-info">
                <div class="label">Balance Due</div>
                <div class="value">$<?= number_format($summary['total_due'], 2) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Data Table -->
<div class="qb-card">
    <div class="qb-card-header">
        <span class="qb-card-title"><i class="fas fa-list"></i> Transaction History</span>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
    </div>
    <div class="qb-card-body">
        <?php if (empty($invoices)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                <p>No transactions found for the selected date range.</p>
            </div>
        <?php else: ?>
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th style="text-align:right">Gross Amount</th>
                        <th style="text-align:right">Tax Amount</th>
                        <th style="text-align:right">Net Value</th>
                        <th style="text-align:right">Balance Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inv['doc_no']) ?></strong></td>
                            <td><?= date('m/d/Y', strtotime($inv['doc_date'])) ?></td>
                            <td><?= htmlspecialchars($inv['display_name']) ?></td>
                            <td style="text-align:right">$<?= number_format($inv['gross_amount'], 2) ?></td>
                            <td style="text-align:right">$<?= number_format($inv['total_tax'], 2) ?></td>
                            <td style="text-align:right; font-weight:700">$<?= number_format($inv['net_amount'], 2) ?></td>
                            <td style="text-align:right; color: <?= $inv['balance_due'] > 0 ? '#ef4444' : '#10b981' ?>">
                                $<?= number_format($inv['balance_due'], 2) ?>
                            </td>
                            <td>
                                <?php if ($inv['balance_due'] <= 0): ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Due</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
