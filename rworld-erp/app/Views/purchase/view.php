<!-- Purchase View -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-shopping-cart mr-2 text-success"></i><?= htmlspecialchars($record['doc_no']) ?></h1>
        <div class="page-subtitle">Purchase invoice detail</div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= APP_URL ?>/purchase" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <button class="btn btn-outline-dark" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="qb-card mb-4">
            <div class="qb-card-body p-0">
                <table class="qb-table">
                    <thead>
                        <tr><th>#</th><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($lines)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No line items</td></tr>
                    <?php else: foreach ($lines as $i => $ln): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars($ln['description']) ?></strong>
                                <?php if ($ln['stock_no']): ?><br><small class="text-muted"><?= $ln['stock_no'] ?></small><?php endif; ?></td>
                            <td class="text-right"><?= number_format($ln['qty'], 2) ?></td>
                            <td class="text-right">$<?= number_format($ln['rate'], 2) ?></td>
                            <td class="text-right"><strong>$<?= number_format($ln['net_value'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4" class="text-right">Subtotal</td><td class="text-right">$<?= number_format($record['gross_amount'], 2) ?></td></tr>
                        <tr><td colspan="4" class="text-right">Sales Tax (8.25%)</td><td class="text-right">$<?= number_format($record['total_tax'], 2) ?></td></tr>
                        <tr class="font-weight-bold"><td colspan="4" class="text-right">Net Total</td><td class="text-right text-success h5 mb-0">$<?= number_format($record['net_amount'], 2) ?></td></tr>
                        <tr><td colspan="4" class="text-right text-muted">Paid</td><td class="text-right text-muted">$<?= number_format($record['paid_amount'], 2) ?></td></tr>
                        <tr class="font-weight-bold"><td colspan="4" class="text-right">Balance Due</td><td class="text-right <?= $record['balance_due'] > 0 ? 'text-danger' : 'text-muted' ?>">$<?= number_format($record['balance_due'], 2) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card mb-4">
            <div class="qb-card-header"><span class="qb-card-title">Invoice Information</span></div>
            <div class="qb-card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th>Doc No</th><td><?= htmlspecialchars($record['doc_no']) ?></td></tr>
                    <tr><th>Date</th><td><?= date('m/d/Y', strtotime($record['doc_date'])) ?></td></tr>
                    <tr><th>Supplier</th><td><?= htmlspecialchars($record['supplier_name'] ?? '—') ?></td></tr>
                    <tr><th>Supplier Inv</th><td><?= htmlspecialchars($record['supplier_inv_no'] ?? '—') ?></td></tr>
                    <tr><th>Status</th><td>
                        <?php $sc = ['confirmed'=>'badge-success','draft'=>'badge-warning','cancelled'=>'badge-danger']; ?>
                        <span class="badge <?= $sc[$record['status']] ?? 'badge-light' ?>"><?= ucfirst($record['status']) ?></span>
                    </td></tr>
                    <tr><th>Remarks</th><td><?= htmlspecialchars($record['remarks'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
