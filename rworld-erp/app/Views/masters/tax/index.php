<?php
/**
 * Tax Master — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-percent mr-2 text-primary"></i>Tax Master</h1>
        <div class="page-subtitle">Configure US Sales Tax types and applicable rates</div>
    </div>
    <a href="<?= APP_URL ?>/taxmaster/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New Tax Type</a>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Region</th>
                        <th>Inclusive</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="fas fa-percent fa-2x mb-2 d-block"></i>
                        No tax types configured. <a href="<?= APP_URL ?>/taxmaster/create">Create the first tax type.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($row['code']) ?></code></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['tax_region'] ?? 'US_SALES_TAX') ?></td>
                        <td><?= $row['is_inclusive'] ? '<span class="badge badge-info">Inclusive</span>' : '<span class="badge badge-secondary">Exclusive</span>' ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
