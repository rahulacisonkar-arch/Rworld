<?php
/**
 * Delivery Notes — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-truck mr-2 text-primary"></i>Delivery Notes</h1>
        <div class="page-subtitle">Track customer goods delivery logs</div>
    </div>
    <a href="<?= APP_URL ?>/deliverynote/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New Delivery Note</a>
</div>

<div class="qb-card mb-3">
    <div class="qb-card-body">
        <form method="GET" class="d-flex" style="gap:8px">
            <input type="text" name="q" class="form-control" placeholder="Search by doc no or customer…" value="<?= htmlspecialchars($search) ?>" style="max-width:320px">
            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
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
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="fas fa-truck fa-2x mb-2 d-block"></i>
                        No delivery notes found. <a href="<?= APP_URL ?>/deliverynote/create">Create one.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= htmlspecialchars($row['customer_display_name']) ?></td>
                        <td><span class="badge badge-success"><?= ucfirst($row['status']) ?></span></td>
                        <td><?= date('m/d/Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
