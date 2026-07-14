<?php
/**
 * Stock Journal — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-exchange-alt mr-2 text-primary"></i>Stock Journal</h1>
        <div class="page-subtitle">Post physical count adjustments and audits</div>
    </div>
    <a href="<?= APP_URL ?>/stockjournal/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New Adjustment</a>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Doc No</th>
                        <th>Date</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        No adjustments recorded. <a href="<?= APP_URL ?>/stockjournal/create">Post first adjustment.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td><span class="badge badge-success"><?= ucfirst($row['status']) ?></span></td>
                        <td><?= date('m/d/Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
