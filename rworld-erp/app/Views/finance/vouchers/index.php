<?php
/**
 * Vouchers — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Journal Vouchers</h1>
        <div class="page-subtitle">Track general ledger manual bookings and payment splits</div>
    </div>
    <a href="<?= APP_URL ?>/voucher/create" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i> New Voucher
    </a>
</div>

<div class="qb-card mb-3">
    <div class="qb-card-body">
        <form method="GET" class="d-flex" style="gap:8px">
            <select name="type" class="form-control" style="max-width:200px" onchange="this.form.submit()">
                <option value="">All Voucher Types</option>
                <option value="journal" <?= $filterType==='journal'?'selected':'' ?>>Journal</option>
                <option value="receipt" <?= $filterType==='receipt'?'selected':'' ?>>Receipt</option>
                <option value="payment" <?= $filterType==='payment'?'selected':'' ?>>Payment</option>
                <option value="contra" <?= $filterType==='contra'?'selected':'' ?>>Contra</option>
            </select>
            <?php if ($filterType): ?><a href="<?= APP_URL ?>/voucher" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
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
                        <th>Posting Date</th>
                        <th>Type</th>
                        <th>Narration</th>
                        <th class="text-right">Total Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">
                        <i class="fas fa-file-invoice-dollar fa-2x mb-2 d-block"></i>
                        No vouchers found. <a href="<?= APP_URL ?>/voucher/create">Create a manual entry.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['doc_no']) ?></strong></td>
                        <td><?= date('m/d/Y', strtotime($row['doc_date'])) ?></td>
                        <td><span class="badge badge-secondary"><?= strtoupper($row['voucher_type']) ?></span></td>
                        <td><?= htmlspecialchars($row['narration'] ?? '—') ?></td>
                        <td class="text-right"><strong>$<?= number_format($row['total_amount'], 2) ?></strong></td>
                        <td><span class="badge badge-success"><?= ucfirst($row['status']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
