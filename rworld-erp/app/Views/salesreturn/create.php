<?php
/**
 * QuickBill POS - Create Sales Return View
 */
?>
<div class="page-header">
    <div>
        <h1>Process Sales Return</h1>
        <div class="page-subtitle">Select invoice to adjust items and return stocks</div>
    </div>
</div>

<div class="qb-card mb-4">
    <div class="qb-card-body">
        <form action="<?= APP_URL ?>/salesreturn/store" method="POST" id="returnForm">
            <?= View::csrfField() ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Original Invoice</label>
                        <select class="form-control" name="orig_sale_id" id="invoiceSelector" required>
                            <option value="">-- Choose Invoice --</option>
                            <?php foreach ($sales as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['doc_no']) ?> (<?= date('m/d/Y', strtotime($s['doc_date'])) ?>) — $<?= number_format($s['net_amount'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Return Reason</label>
                        <select class="form-control" name="reason_id" required>
                            <option value="">-- Choose Reason --</option>
                            <?php foreach ($reasons as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['reason']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Items Table (Loaded dynamically via JS) -->
            <div class="table-responsive mt-4" id="itemsContainer" style="display:none">
                <table class="qb-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th style="text-align:right">Purchased Qty</th>
                            <th style="text-align:right">Rate</th>
                            <th style="text-align:center; width:150px">Return Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>

                <div class="form-group mt-4">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" name="remarks" rows="3" placeholder="Enter notes or additional info..."></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Submit Sales Return
                    </button>
                    <a href="<?= APP_URL ?>/salesreturn" class="btn btn-outline-secondary ml-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const invoiceSelector = document.getElementById('invoiceSelector');
    const itemsContainer = document.getElementById('itemsContainer');
    const itemsTableBody = document.querySelector('#itemsTable tbody');

    invoiceSelector.addEventListener('change', function() {
        const saleId = this.value;
        if (!saleId) {
            itemsContainer.style.display = 'none';
            return;
        }

        fetch('<?= APP_URL ?>/salesreturn/getSaleDetails?sale_id=' + saleId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    itemsTableBody.innerHTML = '';
                    data.items.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong>${item.stock_no}</strong></td>
                            <td>${item.item_snapshot || item.description}</td>
                            <td style="text-align:right">${parseFloat(item.qty).toFixed(2)}</td>
                            <td style="text-align:right">$${parseFloat(item.rate).toFixed(2)}</td>
                            <td style="text-align:center">
                                <input type="number" class="form-control text-center" 
                                       name="qty[${item.id}]" 
                                       value="0" 
                                       min="0" 
                                       max="${item.qty}" 
                                       step="0.01" 
                                       style="width:100px; display:inline-block">
                            </td>
                        `;
                        itemsTableBody.appendChild(tr);
                    });
                    itemsContainer.style.display = 'block';
                } else {
                    alert(data.message || 'Error fetching invoice items.');
                }
            });
    });
});
</script>
