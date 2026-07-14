<?php
/**
 * Payments Received — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-hand-holding-usd mr-2 text-primary"></i>Payments Received</h1>
        <div class="page-subtitle">Track incoming customer payments and receipts</div>
    </div>
    <a href="<?= APP_URL ?>/payment/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Record Payment</a>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Doc No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th class="text-right">Amount</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">
                        <i class="fas fa-hand-holding-usd fa-2x mb-2 d-block"></i>
                        No payments recorded. <a href="<?= APP_URL ?>/payment/create">Record first payment.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? 'Walk-in') ?></td>
                        <td class="text-right text-success font-weight-bold">$<?= number_format($row['amount'], 2) ?></td>
                        <td><span class="badge badge-success"><?= ucfirst($row['status']) ?></span></td>
                        <td><?= date('m/d/Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
