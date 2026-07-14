<?php
/**
 * QuickBill POS - Sales Returns Listing View
 */
?>
<div class="page-header">
    <div>
        <h1>Sales Returns</h1>
        <div class="page-subtitle">Track and process customer returned goods</div>
    </div>
    <div class="page-actions">
        <a href="<?= APP_URL ?>/salesreturn/create" class="btn btn-primary">
            <i class="fas fa-undo"></i> Process Return
        </a>
    </div>
</div>

<div class="qb-card">
    <div class="qb-card-body">
        <?php if (empty($returns)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-reply fa-3x mb-3 opacity-50"></i>
                <p>No sales return transactions logged yet.</p>
            </div>
        <?php else: ?>
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Return No</th>
                        <th>Return Date</th>
                        <th>Original Invoice</th>
                        <th>Customer</th>
                        <th style="text-align:right">Tax Amount</th>
                        <th style="text-align:right">Total Refund</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returns as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['doc_no']) ?></strong></td>
                            <td><?= date('m/d/Y', strtotime($r['doc_date'])) ?></td>
                            <td><?= htmlspecialchars($r['orig_doc_no']) ?></td>
                            <td><?= htmlspecialchars($r['display_name']) ?></td>
                            <td style="text-align:right">$<?= number_format($r['total_tax'], 2) ?></td>
                            <td style="text-align:right; font-weight:700">$<?= number_format($r['net_amount'], 2) ?></td>
                            <td>
                                <?php if ($r['status'] === 'confirmed'): ?>
                                    <span class="badge badge-success">Processed</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
