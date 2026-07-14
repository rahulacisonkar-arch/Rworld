<?php
/**
 * Expenses — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-wallet mr-2 text-primary"></i>Expenses</h1>
        <div class="page-subtitle">Track operational business expenses and categories</div>
    </div>
    <a href="<?= APP_URL ?>/expense/create" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i> Record Expense
    </a>
</div>

<div class="row mb-4">
    <div class="col-md-9">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="GET" class="form-inline" style="gap:10px">
                    <div class="form-group mb-0">
                        <label class="mr-2">From</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate) ?>">
                    </div>
                    <div class="form-group mb-0">
                        <label class="mr-2">To</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate) ?>">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i>Filter</button>
                    <?php if ($fromDate !== date('Y-m-01') || $toDate !== date('Y-m-d')): ?>
                        <a href="<?= APP_URL ?>/expense" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="qb-card bg-light border">
            <div class="qb-card-body text-center py-3">
                <div class="text-muted small">Total Filtered Expense</div>
                <h3 class="text-danger mb-0 font-weight-bold">$<?= number_format($totalExpense, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Remarks / Description</th>
                        <th class="text-right">Taxable Amount</th>
                        <th class="text-right">Net Total</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">
                        <i class="fas fa-wallet fa-2x mb-2 d-block"></i>
                        No expense records found. <a href="<?= APP_URL ?>/expense/create">Record the first expense.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($row['category_name'] ?? 'General') ?></span></td>
                        <td><?= htmlspecialchars($row['remarks'] ?? '—') ?></td>
                        <td class="text-right">$<?= number_format($row['amount'], 2) ?></td>
                        <td class="text-right text-danger font-weight-bold">$<?= number_format($row['net_amount'], 2) ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(<?= $row['id'] ?>, '<?= CSRF::generate() ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteExpense(id, csrf) {
    if (!confirm('Are you sure you want to delete this expense record?')) return;
    fetch('<?= APP_URL ?>/expense/delete/' + id, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(d => {
        if (d.success) { location.reload(); }
        else { alert(d.message); }
    });
}
</script>
