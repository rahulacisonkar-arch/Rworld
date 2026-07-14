<?php
/**
 * QuickBill POS - Sales List View
 */
?>
<div class="page-header">
    <div>
        <h1>Sales List</h1>
        <div class="page-subtitle">Track all standard and POS sales invoices</div>
    </div>
    <div class="page-actions">
        <a href="<?= APP_URL ?>/sales/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> New POS Sale
        </a>
    </div>
</div>

<div class="qb-card">
    <div class="qb-card-body">
        <?php if (empty($sales)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-receipt fa-3x mb-3 opacity-50"></i>
                <p>No invoices recorded yet.</p>
            </div>
        <?php else: ?>
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th style="text-align:right">Taxable Amt</th>
                        <th style="text-align:right">Tax</th>
                        <th style="text-align:right">Net Amount</th>
                        <th>Status</th>
                        <th style="text-align:center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['doc_no']) ?></strong></td>
                            <td><?= date('m/d/Y', strtotime($s['doc_date'])) ?></td>
                            <td><?= htmlspecialchars($s['display_name']) ?></td>
                            <td style="text-align:right">$<?= number_format($s['taxable_amount'], 2) ?></td>
                            <td style="text-align:right">$<?= number_format($s['total_tax'], 2) ?></td>
                            <td style="text-align:right; font-weight:700">$<?= number_format($s['net_amount'], 2) ?></td>
                            <td>
                                <?php if ($s['status'] === 'confirmed'): ?>
                                    <span class="badge badge-success">Confirmed</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Cancelled</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center">
                                <button class="btn btn-sm btn-outline-dark" onclick="window.open('<?= APP_URL ?>/sales/print/<?= $s['id'] ?>', 'InvoicePrint', 'width=850,height=900,scrollbars=yes')">
                                    <i class="fas fa-print mr-1"></i> Print
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
