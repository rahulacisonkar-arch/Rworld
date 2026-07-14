<?php
/**
 * Ledger Accounts — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-book mr-2 text-primary"></i>Ledger Accounts</h1>
        <div class="page-subtitle">Manage general ledger chart of accounts</div>
    </div>
    <a href="<?= APP_URL ?>/ledger/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New Account</a>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Group</th>
                        <th class="text-right">Opening Balance</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">
                        <i class="fas fa-book fa-2x mb-2 d-block"></i>
                        No ledger accounts. <a href="<?= APP_URL ?>/ledger/create">Create the first account.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($row['code']) ?></code></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['group_name'] ?? '—') ?></td>
                        <td class="text-right">$<?= number_format($row['opening_balance'], 2) ?></td>
                        <td><span class="badge badge-secondary"><?= htmlspecialchars($row['opening_bal_type'] ?? 'Dr') ?></span></td>
                        <td><?= $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
