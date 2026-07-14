<?php
/**
 * Supplier Payments Made — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-money-bill-wave mr-2 text-danger"></i>Supplier Payments</h1>
        <div class="page-subtitle">Track outbound payments made to vendors and suppliers</div>
    </div>
    <a href="<?= APP_URL ?>/receipt/create" class="btn btn-danger"><i class="fas fa-plus mr-1"></i> Pay Supplier</a>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Doc No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th class="text-right">Amount</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="fas fa-money-bill-wave fa-2x mb-2 d-block"></i>
                        No supplier payments found. <a href="<?= APP_URL ?>/receipt/create">Record first payment.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
                        <td class="text-right text-danger font-weight-bold">$<?= number_format($row['amount'], 2) ?></td>
                        <td><?= date('m/d/Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
