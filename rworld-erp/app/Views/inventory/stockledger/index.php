<?php
/**
 * Stock Ledger — Production View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-warehouse mr-2 text-primary"></i>Stock Ledger</h1>
        <div class="page-subtitle">Track historical inbound and outbound physical inventory activities</div>
    </div>
</div>

<div class="qb-card mb-3">
    <div class="qb-card-body">
        <form method="GET" id="filterForm" class="d-flex align-items-center" style="gap:10px; position:relative">
            <div style="position:relative; width:320px">
                <input type="text" id="itemSearch" class="form-control" placeholder="<?= $selectedItemId ? htmlspecialchars($selectedItemName) : 'Search product SKU to filter...' ?>" autocomplete="off">
                <div id="itemDropdown" class="position-absolute bg-white border rounded shadow" style="display:none;z-index:1000;width:320px;max-height:220px;overflow-y:auto;left:0;top:40px"></div>
            </div>
            <input type="hidden" name="item_id" id="itemIdHidden" value="<?= htmlspecialchars($selectedItemId) ?>">
            <?php if ($selectedItemId): ?>
                <a href="<?= APP_URL ?>/stockledger" class="btn btn-outline-secondary">Clear Filter</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
const searchInput = document.getElementById('itemSearch');
const dropdown = document.getElementById('itemDropdown');
const hiddenInput = document.getElementById('itemIdHidden');
const form = document.getElementById('filterForm');
let searchTimer = null;

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

searchInput.addEventListener('input', function() {
    const q = this.value.trim();
    if (!q) { dropdown.style.display = 'none'; return; }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetch('<?= APP_URL ?>/item/search?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(matches => {
                if (!matches.length) { dropdown.style.display = 'none'; return; }
                dropdown.innerHTML = matches.map(i =>
                    `<div class="p-2 border-bottom hover-bg" onclick="hiddenInput.value='${i.id}';form.submit();" style="cursor:pointer">
                        <strong>${escHtml(i.description)}</strong> <small class="text-muted">${i.stock_no}</small>
                    </div>`
                ).join('');
                dropdown.style.display = 'block';
            });
    }, 250);
});

document.addEventListener('click', e => {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});
</script>

<style>
.hover-bg:hover {
    background-color: #f1f5f9;
}
</style>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product SKU</th>
                        <th>Transaction Type</th>
                        <th>Reference Doc</th>
                        <th class="text-right">Qty In</th>
                        <th class="text-right">Qty Out</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No stock movements recorded.</td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><?= date('m/d/Y', strtotime($row['txn_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($row['description']) ?></strong> <br><small class="text-muted"><?= htmlspecialchars($row['stock_no']) ?></small></td>
                        <td><span class="badge badge-light"><?= htmlspecialchars($row['txn_type']) ?></span></td>
                        <td><code><?= htmlspecialchars($row['doc_no'] ?? '—') ?></code></td>
                        <td class="text-right text-success font-weight-bold"><?= $row['qty_in'] > 0 ? '+'.number_format($row['qty_in'], 2) : '—' ?></td>
                        <td class="text-right text-danger font-weight-bold"><?= $row['qty_out'] > 0 ? '-'.number_format($row['qty_out'], 2) : '—' ?></td>
                        <td class="text-right">$<?= number_format($row['rate'], 2) ?></td>
                        <td class="text-right">$<?= number_format($row['value'], 2) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
