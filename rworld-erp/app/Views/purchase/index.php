<!-- Purchase List -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-shopping-cart mr-2 text-success"></i>Purchase List</h1>
        <div class="page-subtitle">All purchase invoices with supplier details and payment status</div>
    </div>
    <a href="<?= APP_URL ?>/purchase/create" class="btn btn-success"><i class="fas fa-plus mr-1"></i> New Purchase</a>
</div>

<div class="qb-card mb-3">
    <div class="qb-card-body">
        <form method="GET" class="d-flex" style="gap:8px">
            <input type="text" name="q" class="form-control" placeholder="Search by doc no or supplier…" value="<?= htmlspecialchars($search) ?>" style="max-width:300px">
            <button type="submit" class="btn btn-outline-success"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="<?= APP_URL ?>/purchase" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
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
                        <th>Supplier</th>
                        <th class="text-right">Net Amount</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance Due</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">
                        <i class="fas fa-shopping-cart fa-2x mb-2 d-block"></i>
                        No purchases found. <a href="<?= APP_URL ?>/purchase/create">Create the first purchase.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/purchase/view/<?= $row['id'] ?>"><strong><?= htmlspecialchars($row['doc_no']) ?></strong></a></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
                        <td class="text-right">$<?= number_format($row['net_amount'], 2) ?></td>
                        <td class="text-right text-success">$<?= number_format($row['paid_amount'], 2) ?></td>
                        <td class="text-right <?= $row['balance_due'] > 0 ? 'text-danger font-weight-bold' : 'text-muted' ?>">
                            $<?= number_format($row['balance_due'], 2) ?>
                        </td>
                        <td>
                            <?php
                            $sc = ['confirmed'=>'badge-success','draft'=>'badge-warning','cancelled'=>'badge-danger'];
                            ?>
                            <span class="badge <?= $sc[$row['status']] ?? 'badge-light' ?>"><?= ucfirst($row['status']) ?></span>
                        </td>
                        <td class="text-center">
                            <a href="<?= APP_URL ?>/purchase/view/<?= $row['id'] ?>" class="btn btn-sm btn-outline-success" title="View"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (($records['last_page'] ?? 1) > 1): ?>
    <div class="qb-card-body border-top">
        <small class="text-muted">Showing <?= $records['from'] ?>–<?= $records['to'] ?> of <?= $records['total'] ?></small>
    </div>
    <?php endif; ?>
</div>
