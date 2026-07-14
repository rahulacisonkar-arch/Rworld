<?php $isEdit = !empty($record); ?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-box mr-2 text-violet"></i>Item Master</h1>
        <div class="page-subtitle">Product catalog — prices, categories, and inventory settings</div>
    </div>
    <a href="<?= APP_URL ?>/item/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New Item</a>
</div>

<div class="qb-card mb-3">
    <div class="qb-card-body">
        <form method="GET" class="d-flex" style="gap:8px">
            <input type="text" name="q" class="form-control" placeholder="Search by name, stock no, or barcode…" value="<?= htmlspecialchars($search) ?>" style="max-width:340px">
            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="<?= APP_URL ?>/item" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Stock No</th>
                        <th>Description</th>
                        <th>Barcode</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Price 1 ($)</th>
                        <th class="text-right">MRP ($)</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">
                        <i class="fas fa-box fa-2x mb-2 d-block"></i>
                        No items found. <a href="<?= APP_URL ?>/item/create">Add the first item.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($row['stock_no']) ?></code></td>
                        <td><strong><?= htmlspecialchars($row['description']) ?></strong></td>
                        <td><?= htmlspecialchars($row['barcode'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['category_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['unit_name'] ?? '—') ?></td>
                        <td class="text-right">$<?= number_format($row['price1'], 2) ?></td>
                        <td class="text-right">$<?= number_format($row['mrp'], 2) ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                        <td class="text-center" style="white-space:nowrap">
                            <a href="<?= APP_URL ?>/item/edit/<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?= $row['id'] ?>, '<?= CSRF::generate() ?>')" title="Deactivate"><i class="fas fa-ban"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($records['last_page'] > 1): ?>
    <div class="qb-card-body border-top d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $records['from'] ?>–<?= $records['to'] ?> of <?= $records['total'] ?> items</small>
        <div>
            <?php for ($p = 1; $p <= $records['last_page']; $p++): ?>
                <a href="?page=<?= $p ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                   class="btn btn-sm <?= $p == $records['current_page'] ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function deleteItem(id, csrf) {
    if (!confirm('Deactivate this item?')) return;
    fetch('<?= APP_URL ?>/item/delete/' + id, {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
}
</script>
