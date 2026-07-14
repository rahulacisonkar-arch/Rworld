<!-- Quotation Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-file-alt mr-2 text-info"></i>New Quotation</h1>
        <div class="page-subtitle">Create a price quotation for a customer</div>
    </div>
    <a href="<?= APP_URL ?>/quotation" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to List</a>
</div>

<form method="POST" id="qtForm">
    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
    <input type="hidden" name="line_items" id="lineItemsJson" value="[]">

    <div class="row">
        <div class="col-md-8">
            <!-- Line Items Card -->
            <div class="qb-card mb-4">
                <div class="qb-card-header d-flex justify-content-between align-items-center">
                    <span class="qb-card-title"><i class="fas fa-list mr-1"></i>Line Items</span>
                    <div class="d-flex" style="gap:6px">
                        <input type="text" id="itemSearch" class="form-control form-control-sm" placeholder="Search product…" style="width:200px" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addBlankRow()"><i class="fas fa-plus"></i> Add Row</button>
                    </div>
                </div>
                <div class="qb-card-body p-0">
                    <div class="table-responsive">
                        <table class="qb-table table-sm" id="lineTable">
                            <thead>
                                <tr>
                                    <th style="width:35%">Description</th>
                                    <th class="text-right" style="width:10%">Qty</th>
                                    <th class="text-right" style="width:14%">Unit Price</th>
                                    <th class="text-right" style="width:10%">Disc %</th>
                                    <th class="text-right" style="width:14%">Amount</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="lineBody"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-right font-weight-bold">Subtotal</td>
                                    <td class="text-right" id="subtotalDisp">$0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                     <td colspan="4" class="text-right align-middle">
                                         <div class="d-inline-flex align-items-center" style="gap:8px">
                                             <span>Sales Tax</span>
                                             <select id="taxSelector" class="form-control form-control-sm" style="width:180px;height:30px;font-size:.85rem" onchange="updateTotals()">
                                                 <option value="8.25" data-id="2">Standard Tax (8.25%)</option>
                                                 <option value="6.25" data-id="3">State Tax (6.25%)</option>
                                                 <option value="8.875" data-id="4">NY Tax (8.875%)</option>
                                                 <option value="7.25" data-id="5">CA Tax (7.25%)</option>
                                                 <option value="6.0" data-id="6">FL Tax (6.0%)</option>
                                                 <option value="0.0" data-id="1">Exempt (0%)</option>
                                             </select>
                                         </div>
                                     </td>
                                     <td class="text-right align-middle" id="taxDisp">$0.00</td>
                                     <td></td>
                                 </tr>
                                <tr class="font-weight-bold">
                                    <td colspan="4" class="text-right">Total</td>
                                    <td class="text-right text-primary" id="totalDisp">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title">Quotation Details</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customerSelect" class="form-control" onchange="setCustomerName()">
                            <option value="">-- Walk-in Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="customer_name" id="customerName" value="Walk-in">
                    <div class="form-group">
                        <label class="form-label">Quotation Date</label>
                        <input type="date" name="doc_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid Till</label>
                        <input type="date" name="valid_till" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-info btn-block text-white" id="saveBtn" onclick="return prepareSubmit()">
                <i class="fas fa-save mr-1"></i> Save Quotation
            </button>
        </div>
    </div>
</form>

<div id="itemDropdown" class="position-absolute bg-white border rounded shadow" style="display:none;z-index:1000;width:320px;max-height:220px;overflow-y:auto"></div>

<script>
const ITEMS = <?= json_encode(array_map(function($i){ return ['id'=>$i['id'],'description'=>$i['description'],'price1'=>(float)$i['price1'],'stock_no'=>$i['stock_no']]; }, $items)) ?>;
let lines = [];

function addBlankRow(item=null) {
    const row = {
        item_id: item ? item.id : null,
        description: item ? item.description : '',
        qty: 1, unit_price: item ? item.price1 : 0, discount_pct: 0, amount: item ? item.price1 : 0
    };
    lines.push(row);
    renderLines();
}

function renderLines() {
    const tbody = document.getElementById('lineBody');
    tbody.innerHTML = '';
    lines.forEach((ln, i) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="form-control form-control-sm" value="${escHtml(ln.description)}" onchange="lines[${i}].description=this.value"></td>
            <td><input type="number" class="form-control form-control-sm text-right" value="${ln.qty}" min="0.01" step="0.01" style="width:70px" onchange="lines[${i}].qty=parseFloat(this.value)||1;calcLine(${i})"></td>
            <td><input type="number" class="form-control form-control-sm text-right" value="${ln.unit_price}" min="0" step="0.01" style="width:90px" onchange="lines[${i}].unit_price=parseFloat(this.value)||0;calcLine(${i})"></td>
            <td><input type="number" class="form-control form-control-sm text-right" value="${ln.discount_pct}" min="0" max="100" step="0.01" style="width:65px" onchange="lines[${i}].discount_pct=parseFloat(this.value)||0;calcLine(${i})"></td>
            <td class="text-right" id="amt_${i}">$${ln.amount.toFixed(2)}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="lines.splice(${i},1);renderLines()"><i class="fas fa-times"></i></button></td>`;
        tbody.appendChild(tr);
    });
    updateTotals();
}

function calcLine(i) {
    const ln = lines[i];
    const disc = 1 - (ln.discount_pct / 100);
    ln.amount = Math.round(ln.qty * ln.unit_price * disc * 100) / 100;
    document.getElementById('amt_' + i).textContent = '$' + ln.amount.toFixed(2);
    updateTotals();
}

function updateTotals() {
    const sub = lines.reduce((s, l) => s + l.amount, 0);
    const taxSel = document.getElementById('taxSelector');
    const taxRate = parseFloat(taxSel.value) || 0;
    const tax = Math.round(sub * (taxRate / 100) * 100) / 100;
    document.getElementById('subtotalDisp').textContent = '$' + sub.toFixed(2);
    document.getElementById('taxDisp').textContent = '$' + tax.toFixed(2);
    document.getElementById('totalDisp').textContent = '$' + (sub + tax).toFixed(2);
}

function prepareSubmit() {
    if (!lines.length) { alert('Please add at least one line item.'); return false; }
    
    // Set tax_type_id for each line item
    const taxSel = document.getElementById('taxSelector');
    const taxTypeId = taxSel.options[taxSel.selectedIndex].dataset.id;
    lines.forEach(l => {
        l.tax_type_id = taxTypeId;
    });

    document.getElementById('lineItemsJson').value = JSON.stringify(lines);
    return true;
}

function setCustomerName() {
    const sel = document.getElementById('customerSelect');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customerName').value = opt.dataset.name || 'Walk-in';
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Item live search
const searchInput = document.getElementById('itemSearch');
const dropdown = document.getElementById('itemDropdown');
let searchTimer = null;
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
                    `<div class="p-2 border-bottom cursor-pointer hover-bg" onclick="addBlankRow(${JSON.stringify({id:i.id,description:i.description,price1:parseFloat(i.price1),stock_no:i.stock_no}).replace(/"/g,'&quot;')});document.getElementById('itemSearch').value='';dropdown.style.display='none'" style="cursor:pointer">
                        <strong>${escHtml(i.description)}</strong> <small class="text-muted">${i.stock_no}</small>
                        <span class="float-right text-success">$${parseFloat(i.price1).toFixed(2)}</span>
                    </div>`
                ).join('');
                const rect = searchInput.getBoundingClientRect();
                dropdown.style.left = rect.left + 'px';
                dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
                dropdown.style.display = 'block';
            });
    }, 250);
});
document.addEventListener('click', e => { if (!searchInput.contains(e.target)) dropdown.style.display = 'none'; });
</script>
