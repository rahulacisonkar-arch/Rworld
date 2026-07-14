<?php
/**
 * Quotations — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-file-alt mr-2 text-info"></i>Quotations</h1>
        <div class="page-subtitle">Create and track customer price quotations</div>
    </div>
    <a href="<?= APP_URL ?>/quotation/create" class="btn btn-info text-white"><i class="fas fa-plus mr-1"></i> New Quotation</a>
</div>

<div class="qb-card mb-3">
    <div class="qb-card-body d-flex" style="gap:8px; flex-wrap:wrap">
        <form method="GET" class="d-flex" style="gap:8px; flex:1">
            <input type="text" name="q" class="form-control" placeholder="Search by doc no or customer…" value="<?= htmlspecialchars($search) ?>" style="max-width:280px">
            <select name="status" class="form-control" style="max-width:160px">
                <option value="">All Statuses</option>
                <option value="open" <?= $filterStatus=='open'?'selected':'' ?>>Open</option>
                <option value="converted" <?= $filterStatus=='converted'?'selected':'' ?>>Converted</option>
                <option value="expired" <?= $filterStatus=='expired'?'selected':'' ?>>Expired</option>
                <option value="cancelled" <?= $filterStatus=='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-outline-info"><i class="fas fa-search"></i></button>
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
                        <th>Valid Till</th>
                        <th>Customer</th>
                        <th class="text-right">Amount</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-file-alt fa-2x mb-2 d-block"></i>
                        No quotations found. <a href="<?= APP_URL ?>/quotation/create">Create the first quotation.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <?php
                        $statusClass = [
                            'open' => 'badge-primary',
                            'converted' => 'badge-success',
                            'expired' => 'badge-secondary',
                            'cancelled' => 'badge-danger',
                        ][$row['status']] ?? 'badge-light';
                    ?>
                    <tr>
                        <td><a href="<?= APP_URL ?>/quotation/view/<?= $row['id'] ?>"><strong><?= htmlspecialchars($row['doc_no']) ?></strong></a></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><?= $row['valid_till'] ? date('m/d/Y', strtotime($row['valid_till'])) : '—' ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? '—') ?></td>
                        <td class="text-right"><strong>$<?= number_format($row['net_amount'], 2) ?></strong></td>
                        <td><span class="badge <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
                        <td class="text-center" style="white-space:nowrap">
                            <a href="<?= APP_URL ?>/quotation/view/<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                            <button class="btn btn-sm btn-outline-dark" onclick="window.open('<?= APP_URL ?>/quotation/print/<?= $row['id'] ?>', 'QuotationPrint', 'width=850,height=900,scrollbars=yes')" title="Print"><i class="fas fa-print"></i></button>
                            <?php if ($row['status'] === 'open'): ?>
                            <button class="btn btn-sm btn-outline-success" onclick="convertQtn(<?= $row['id'] ?>, '<?= CSRF::generate() ?>')" title="Convert to Order"><i class="fas fa-exchange-alt"></i></button>
                            <?php endif; ?>
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

<script>
function convertQtn(id, csrf) {
    if (!confirm('Convert this quotation to a Sales Order?')) return;
    fetch('<?= APP_URL ?>/quotation/convert/' + id, {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(d => {
        if (d.success) { alert(d.message); location.reload(); }
        else { alert('Error: ' + d.message); }
    });
}
</script>
