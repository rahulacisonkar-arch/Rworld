<?php
/**
 * Purchase Returns — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-reply mr-2 text-danger"></i>Purchase Returns</h1>
        <div class="page-subtitle">Manage supplier purchase debit returns</div>
    </div>
    <a href="<?= APP_URL ?>/purchasereturn/create" class="btn btn-danger"><i class="fas fa-plus mr-1"></i> New Return</a>
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
                        <th class="text-right">Net Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="fas fa-reply fa-2x mb-2 d-block"></i>
                        No purchase returns found. <a href="<?= APP_URL ?>/purchasereturn/create">Create one.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                        <td class="text-right">$<?= number_format($row['net_amount'], 2) ?></td>
                        <td><span class="badge badge-success"><?= ucfirst($row['status']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
