<!-- GRN Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-boxes mr-2 text-primary"></i>New Goods Receipt (GRN)</h1>
        <div class="page-subtitle">Receive stock into warehouse and update stock ledgers</div>
    </div>
    <a href="<?= APP_URL ?>/grn" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<form method="POST" id="grnForm">
    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
    <input type="hidden" name="line_items" id="lineItemsJson" value="[]">

    <div class="row">
        <div class="col-md-8">
            <div class="qb-card mb-4" style="position:relative">
                <div class="qb-card-header d-flex justify-content-between align-items-center">
                    <span class="qb-card-title">Received Items</span>
                    <div class="d-flex" style="gap:6px; position:relative">
                        <input type="text" id="itemSearch" class="form-control form-control-sm" placeholder="Search item by name or code…" style="width:280px" autocomplete="off">
                        <div id="itemDropdown" class="position-absolute bg-white border rounded shadow" style="display:none;z-index:1000;width:320px;max-height:220px;overflow-y:auto;right:0;top:35px"></div>
                    </div>
                </div>
                <div class="qb-card-body p-0">
                    <table class="qb-table table-sm" id="lineTable">
                        <thead>
                            <tr>
                                <th style="width:70%">Item Description</th>
                                <th style="width:25%" class="text-right">Qty Received</th>
                                <th style="width:5%"></th>
                            </tr>
                        </thead>
                        <tbody id="lineBody">
                            <tr id="emptyRow">
                                <td colspan="3" class="text-center py-4 text-muted">No items added — search and select an item above to add.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title">Supplier Info</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplierSelect" class="form-control" required>
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block" onclick="return prepareSubmit()"><i class="fas fa-save mr-1"></i> Post Goods Receipt</button>
        </div>
    </div>
</form>

<script>
let lines = [];

function addBlankRow(item=null) {
    if (!item) return;
    if (lines.some(l => l.item_id === item.id)) {
        alert('Item already added.');
        return;
    }
    lines.push({ item_id: item.id, description: item.description, qty: 1 });
    renderLines();
}

function renderLines() {
    const tbody = document.getElementById('lineBody');
    tbody.innerHTML = '';
    
    if (lines.length === 0) {
        tbody.innerHTML = `<tr id="emptyRow"><td colspan="3" class="text-center py-4 text-muted">No items added — search and select an item above to add.</td></tr>`;
        return;
    }

    lines.forEach((ln, i) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <strong>${escHtml(ln.description)}</strong>
                <input type="hidden" value="${ln.item_id}">
            </td>
            <td><input type="number" class="form-control form-control-sm text-right" value="${ln.qty}" min="0.01" step="0.01" style="width:120px; display:inline-block" onchange="lines[${i}].qty=parseFloat(this.value)||1"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="lines.splice(${i},1);renderLines()"><i class="fas fa-times"></i></button></td>`;
        tbody.appendChild(tr);
    });
}

function prepareSubmit() {
    if (!lines.length) { alert('Add at least one line item.'); return false; }
    document.getElementById('lineItemsJson').value = JSON.stringify(lines);
    return true;
}

function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

const searchInput = document.getElementById('itemSearch');
const dropdown = document.getElementById('itemDropdown');
let searchTimer = null;

searchInput.addEventListener('input', function(){
    const q = this.value.trim();
    if (!q) { dropdown.style.display='none'; return; }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetch('<?= APP_URL ?>/item/search?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(matches => {
                if (!matches.length) { dropdown.style.display='none'; return; }
                dropdown.innerHTML = matches.map(i=>`<div class="p-2 border-bottom hover-bg" onclick="addBlankRow(${JSON.stringify({id:i.id,description:i.description}).replace(/"/g,'&quot;')});searchInput.value='';dropdown.style.display='none'" style="cursor:pointer">
                    <strong>${escHtml(i.description)}</strong> <small class="text-muted">${i.stock_no}</small></div>`).join('');
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
