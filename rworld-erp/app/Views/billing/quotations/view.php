<!-- Quotation View -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-file-alt mr-2 text-info"></i><?= htmlspecialchars($record['doc_no']) ?></h1>
        <div class="page-subtitle">Quotation details and line items</div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="<?= APP_URL ?>/quotation" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <?php if ($record['status'] === 'open'): ?>
        <button class="btn btn-success" onclick="convertQtn(<?= $record['id'] ?>, '<?= CSRF::generate() ?>')">
            <i class="fas fa-exchange-alt mr-1"></i> Convert to Order
        </button>
        <?php endif; ?>
        <button class="btn btn-outline-dark" onclick="openPrint(<?= $record['id'] ?>)"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="qb-card mb-4">
            <div class="qb-card-header d-flex justify-content-between">
                <span class="qb-card-title">Line Items</span>
                <?php
                    $statusBadges = ['open'=>'badge-primary','converted'=>'badge-success','expired'=>'badge-secondary','cancelled'=>'badge-danger'];
                ?>
                <span class="badge <?= $statusBadges[$record['status']] ?? 'badge-light' ?>"><?= ucfirst($record['status']) ?></span>
            </div>
            <div class="qb-card-body p-0">
                <table class="qb-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Disc %</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($lines)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No line items</td></tr>
                    <?php else: foreach ($lines as $i => $ln): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($ln['description']) ?></strong>
                                <?php if ($ln['stock_no']): ?><br><small class="text-muted"><?= $ln['stock_no'] ?></small><?php endif; ?></td>
                            <td class="text-right"><?= number_format($ln['qty'], 2) ?></td>
                            <td class="text-right">$<?= number_format($ln['unit_price'], 2) ?></td>
                            <td class="text-right"><?= number_format($ln['discount_pct'], 2) ?>%</td>
                            <td class="text-right"><strong>$<?= number_format($ln['amount'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="5" class="text-right">Subtotal</td><td class="text-right">$<?= number_format($record['gross_amount'], 2) ?></td></tr>
                        <tr><td colspan="5" class="text-right">Sales Tax (8.25%)</td><td class="text-right">$<?= number_format($record['total_tax'], 2) ?></td></tr>
                        <tr class="font-weight-bold">
                            <td colspan="5" class="text-right">Total</td>
                            <td class="text-right text-primary h5 mb-0">$<?= number_format($record['net_amount'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card mb-4">
            <div class="qb-card-header"><span class="qb-card-title">Quotation Info</span></div>
            <div class="qb-card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th>Doc No</th><td><?= htmlspecialchars($record['doc_no']) ?></td></tr>
                    <tr><th>Date</th><td><?= date('m/d/Y', strtotime($record['doc_date'])) ?></td></tr>
                    <tr><th>Valid Till</th><td><?= $record['valid_till'] ? date('m/d/Y', strtotime($record['valid_till'])) : '—' ?></td></tr>
                    <tr><th>Customer</th><td><?= htmlspecialchars($record['customer_name'] ?? '—') ?></td></tr>
                    <tr><th>Remarks</th><td><?= htmlspecialchars($record['remarks'] ?? '—') ?></td></tr>
                    <tr><th>Created</th><td><?= date('m/d/Y H:i', strtotime($record['created_at'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function openPrint(id) {
    window.open('<?= APP_URL ?>/quotation/print/' + id, 'QuotationPrint', 'width=850,height=900,scrollbars=yes');
}

function convertQtn(id, csrf) {
    if (!confirm('Convert this quotation to a Sales Order?')) return;
    fetch('<?= APP_URL ?>/quotation/convert/' + id, {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(d => {
        alert(d.message);
        if (d.success) location.href = '<?= APP_URL ?>/quotation';
    });
}

// Auto open print popup if redirected from create
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('print') === '1') {
        openPrint(<?= $record['id'] ?>);
    }
});
</script>
