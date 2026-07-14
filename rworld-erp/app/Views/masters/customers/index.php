<?php
/**
 * Customers — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-users mr-2 text-primary"></i>Customers</h1>
        <div class="page-subtitle">Manage your customer accounts and credit settings</div>
    </div>
    <a href="<?= APP_URL ?>/customer/create" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i> Add Customer
    </a>
</div>

<div class="qb-card mb-4">
    <div class="qb-card-body">
        <form method="GET" class="d-flex" style="gap:8px">
            <input type="text" name="q" class="form-control" placeholder="Search by name, code, or phone…" value="<?= htmlspecialchars($search) ?>" style="max-width:320px">
            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="<?= APP_URL ?>/customer" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
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
                        <i class="fas fa-users fa-2x mb-2 d-block"></i>
                        No customers found. <a href="<?= APP_URL ?>/customer/create">Add the first customer.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><span class="badge badge-light"><?= htmlspecialchars($row['code']) ?></span></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['phone1'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['city'] ?? '—') ?></td>
                        <td class="text-right">$<?= number_format($row['credit_limit'], 2) ?></td>
                        <td>
                            <?php if ($row['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center" style="white-space:nowrap">
                            <a href="<?= APP_URL ?>/customer/edit/<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRecord(<?= $row['id'] ?>, '<?= CSRF::generate() ?>')" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($records['last_page'] > 1): ?>
    <div class="qb-card-body border-top d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $records['from'] ?>–<?= $records['to'] ?> of <?= $records['total'] ?> customers</small>
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
function deleteRecord(id, csrf) {
    if (!confirm('Deactivate this customer?')) return;
    fetch('<?= APP_URL ?>/customer/delete/' + id, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(d => {
        if (d.success) { location.reload(); }
        else { alert(d.message); }
    });
}
</script>
