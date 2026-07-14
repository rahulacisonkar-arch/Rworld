<?php
/**
 * GRNs — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-boxes mr-2 text-primary"></i>Goods Receipt Notes (GRN)</h1>
        <div class="page-subtitle">Track incoming supplier inventory receipts</div>
    </div>
    <a href="<?= APP_URL ?>/grn/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Post GRN</a>
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
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="fas fa-boxes fa-2x mb-2 d-block"></i>
                        No GRN records found. <a href="<?= APP_URL ?>/grn/create">Post a goods receipt.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td>Supplier #<?= htmlspecialchars($row['supplier_id']) ?></td>
                        <td><span class="badge badge-success"><?= ucfirst($row['status']) ?></span></td>
                        <td><?= date('m/d/Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
