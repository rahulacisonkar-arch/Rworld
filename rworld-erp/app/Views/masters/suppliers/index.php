<?php
/**
 * Suppliers — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-industry mr-2 text-warning"></i>Suppliers</h1>
        <div class="page-subtitle">Manage vendor accounts and purchase terms</div>
    </div>
    <a href="<?= APP_URL ?>/supplier/create" class="btn btn-warning text-white">
        <i class="fas fa-plus mr-1"></i> Add Supplier
    </a>
</div>

<div class="qb-card mb-4">
    <div class="qb-card-body">
        <form method="GET" class="d-flex" style="gap:8px">
            <input type="text" name="q" class="form-control" placeholder="Search by name, code, or phone…" value="<?= htmlspecialchars($search) ?>" style="max-width:320px">
            <button type="submit" class="btn btn-outline-warning"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="<?= APP_URL ?>/supplier" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>City</th>
                        <th class="text-right">Credit Limit</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="fas fa-industry fa-2x mb-2 d-block"></i>
                        No suppliers found. <a href="<?= APP_URL ?>/supplier/create">Add the first supplier.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><span class="badge badge-light"><?= htmlspecialchars($row['code']) ?></span></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['phone1'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['city'] ?? '—') ?></td>
                        <td class="text-right">$<?= number_format($row['credit_limit'], 2) ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                        <td class="text-center">
                            <a href="<?= APP_URL ?>/supplier/edit/<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRecord(<?= $row['id'] ?>, '<?= CSRF::generate() ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function deleteRecord(id, csrf) {
    if (!confirm('Deactivate this supplier?')) return;
    fetch('<?= APP_URL ?>/supplier/delete/' + id, {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
}
</script>
