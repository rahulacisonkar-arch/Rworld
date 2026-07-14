<?php
/**
 * Branch Transfers — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-random mr-2 text-primary"></i>Branch Transfers</h1>
        <div class="page-subtitle">Move inventory between company branches and warehouses</div>
    </div>
    <a href="<?= APP_URL ?>/transfer/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New Transfer</a>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Doc No</th>
                        <th>Date</th>
                        <th>To Branch</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="fas fa-random fa-2x mb-2 d-block"></i>
                        No branch transfers found. <a href="<?= APP_URL ?>/transfer/create">Create one.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= htmlspecialchars($row['dest_branch_name'] ?? '—') ?></td>
                        <td><span class="badge badge-info"><?= ucfirst($row['status']) ?></span></td>
                        <td><?= date('m/d/Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
