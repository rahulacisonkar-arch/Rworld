<?php
/**
 * Artee ERP — New POS Invoice
 * Item lookup: type Stock ID / Fabric Code, press Enter or click Add — no dropdown.
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-cash-register mr-2 text-primary"></i>Point of Sale</h1>
        <div class="page-subtitle">Artee Fabrics and Home — New Invoice</div>
    </div>
    <div class="d-flex" style="gap:8px">
        <button class="btn btn-outline-secondary" onclick="clearAll()"><i class="fas fa-broom mr-1"></i> Clear</button>
        <button class="btn btn-success btn-lg" id="checkoutBtn" style="border-radius:10px;font-weight:700;min-width:180px">
            <i class="fas fa-check-circle mr-1"></i> Save & Print Invoice
        </button>
    </div>
</div>

<div class="row">

    <!-- ═══════════════ LEFT — Item Entry & Invoice Grid ═══════════════ -->
    <div class="col-lg-8">
        <div class="qb-card mb-3">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-barcode mr-1 text-primary"></i>Add Items</span>
                <small class="text-muted ml-2">Type Stock ID or Fabric Code → Press <kbd>Enter</kbd> to add</small>
            </div>
            <div class="qb-card-body pb-2">

                <!-- ─── Item Search Row ─── -->
                <div class="d-flex align-items-start" style="gap:8px; position:relative">

                    <!-- Stock No / ID Input -->
                    <div style="flex:0 0 190px; position:relative">
                        <label class="form-label mb-1 small font-weight-600 text-muted">STOCK NO / ID</label>
                        <input type="text" id="itemCodeInput" class="form-control font-monospace"
                               placeholder="e.g. FAB-00123"
                               autocomplete="off" spellcheck="false"
                               style="letter-spacing:.04em; font-size:.97rem">
                        <!-- Autocomplete suggestions dropdown -->
                        <div id="itemSuggestions" class="item-suggestions-box" style="display:none"></div>
                    </div>

                    <!-- Description (auto-filled, read-only) -->
                    <div style="flex:1">
                        <label class="form-label mb-1 small font-weight-600 text-muted">DESCRIPTION</label>
                        <input type="text" id="itemDesc" class="form-control" placeholder="— will fill automatically —"
                               readonly style="background:#f8f9fa; color:#495057">
                    </div>

                    <!-- Unit Price -->
                    <div style="flex:0 0 110px">
                        <label class="form-label mb-1 small font-weight-600 text-muted">UNIT PRICE</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                            <input type="number" id="itemPrice" class="form-control" step="0.01" min="0" value="0.00">
                        </div>
                    </div>

                    <!-- Qty -->
                    <div style="flex:0 0 80px">
                        <label class="form-label mb-1 small font-weight-600 text-muted">QTY</label>
                        <input type="number" id="itemQty" class="form-control text-center"
                               value="1" min="0.01" step="0.01">
                    </div>

                    <!-- Add Button -->
                    <div style="flex:0 0 auto; padding-top:22px">
                        <button type="button" class="btn btn-primary px-4" id="addItemBtn"
                                style="white-space:nowrap; height:38px">
                            <i class="fas fa-plus mr-1"></i> Add
                        </button>
                    </div>
                </div>

                <!-- Status bar (lookup result / errors) -->
                <div id="itemStatus" class="mt-2" style="min-height:20px; font-size:.82rem"></div>
            </div>
        </div>

        <!-- ─── Invoice Grid ─── -->
        <div class="qb-card">
            <div class="qb-card-body p-0">
                <div class="table-responsive">
                    <table class="qb-table" id="posGrid">
                        <thead>
                            <tr>
                                <th style="width:130px">Stock No</th>
                                <th>Description</th>
                                <th style="width:105px; text-align:right">Unit Price</th>
                                <th style="width:90px; text-align:center">Qty</th>
                                <th style="width:100px; text-align:right">Disc %</th>
                                <th style="width:115px; text-align:right">Line Total</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody id="posGridBody">
                            <tr id="emptyRow">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-2x mb-2 d-block opacity-40"></i>
                                    No items added — type a Stock No above and press <kbd>Enter</kbd>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ RIGHT — Customer & Checkout ═══════════════ -->
    <div class="col-lg-4">

        <!-- Customer -->
        <div class="qb-card mb-3">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-user-tie mr-1"></i>Customer</span>
            </div>
            <div class="qb-card-body">
                <div class="form-group mb-2">
                    <label class="form-label small text-muted font-weight-600">SELECT CUSTOMER</label>
                    <select class="form-control" id="customerSelector" onchange="checkCustomerExemption()">
                        <option value="" data-is-exempt="0">Walk-in Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-is-exempt="<?= htmlspecialchars($c['is_tax_exempt'] ?? 0) ?>">
                                <?= htmlspecialchars($c['name']) ?>
                                <?= $c['phone1'] ? '(' . htmlspecialchars($c['phone1']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label small text-muted font-weight-600">PAYMENT MODE</label>
                    <select class="form-control" id="paymodeSelector">
                        <option value="Cash">💵 Cash</option>
                        <option value="Card">💳 Debit / Credit Card</option>
                        <option value="UPI">📱 Mobile Wallet / Apple Pay</option>
                        <option value="Check">🏦 Check</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Bill Discount -->
        <div class="qb-card mb-3">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-tag mr-1"></i>Bill Discount</span>
            </div>
            <div class="qb-card-body">
                <div class="input-group">
                    <input type="number" id="billDiscount" class="form-control" placeholder="0" min="0" max="100" step="0.01">
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
            </div>
        </div>

        <!-- Totals -->
        <div class="qb-card mb-3" style="background: linear-gradient(135deg,#1a1f3a 0%,#2d3561 100%); color:#fff; border:none">
            <div class="qb-card-body">

                <!-- Subtotal -->
                <div class="d-flex justify-content-between mb-2" style="font-size:.95rem">
                    <span class="text-white-50">Subtotal</span>
                    <strong id="summarySubtotal">$0.00</strong>
                </div>

                <!-- Bill Discount -->
                <div class="d-flex justify-content-between mb-2" style="font-size:.95rem">
                    <span class="text-white-50">Bill Discount</span>
                    <strong id="summaryDisc" class="text-warning">-$0.00</strong>
                </div>

                <!-- Sales Tax toggle row -->
                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.95rem">
                    <div class="d-flex align-items-center" style="gap:8px">
                        <span class="text-white-50" id="taxLabel">Sales Tax (8.25%)</span>
                        <!-- Toggle switch -->
                        <label class="tax-toggle" title="Enable / Disable tax" style="margin-bottom:0">
                            <input type="checkbox" id="taxEnabled" checked>
                            <span class="tax-slider"></span>
                        </label>
                    </div>
                    <strong id="summaryTax">$0.00</strong>
                </div>
                <!-- Tax Rate Selector -->
                <div class="mb-2" id="taxSelectorRow" style="font-size:.85rem">
                    <select id="taxSelector" class="form-control form-control-sm text-white" 
                            style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.25);border-radius:4px;height:30px;font-size:.85rem">
                        <option value="8.25" data-id="2" class="text-dark">Standard Sales Tax (8.25%)</option>
                        <option value="6.25" data-id="3" class="text-dark">State Sales Tax (6.25%)</option>
                        <option value="8.875" data-id="4" class="text-dark">NY Sales Tax (8.875%)</option>
                        <option value="7.25" data-id="5" class="text-dark">CA Sales Tax (7.25%)</option>
                        <option value="6.0" data-id="6" class="text-dark">FL Sales Tax (6.0%)</option>
                        <option value="0.0" data-id="1" class="text-dark">Exempt / No Tax (0%)</option>
                    </select>
                </div>

                <!-- Handling Fee row -->
                <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:.95rem">
                    <div class="d-flex align-items-center" style="gap:6px">
                        <span class="text-white-50">Handling Fee</span>
                        <div class="input-group input-group-sm" style="width:80px">
                            <input type="number" id="handlingPct" class="form-control form-control-sm text-center"
                                   value="3" min="0" max="100" step="0.1"
                                   style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.25);color:#fff;border-radius:4px 0 0 4px;padding:2px 4px;font-size:.8rem">
                            <div class="input-group-append">
                                <span class="input-group-text" style="background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.25);color:#adb5bd;font-size:.78rem;padding:2px 5px">%</span>
                            </div>
                        </div>
                    </div>
                    <strong id="summaryHandling" class="text-info">$0.00</strong>
                </div>

                <hr style="border-color:rgba(255,255,255,.2)">
                <div class="d-flex justify-content-between" style="font-size:1.4rem">
                    <span>NET PAYABLE</span>
                    <strong id="summaryTotal" style="color:#4ade80">$0.00</strong>
                </div>
            </div>
        </div>

        <button class="btn btn-success btn-block btn-lg" id="checkoutBtnSide"
                style="border-radius:12px; font-weight:700; font-size:1.1rem; height:52px">
            <i class="fas fa-check-circle mr-2"></i> Save &amp; Print Invoice
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     STYLES
═══════════════════════════════════════════════════════ -->
<style>
.font-monospace { font-family: 'Courier New', monospace; }
.font-weight-600 { font-weight: 600; }
.opacity-40 { opacity: .4; }

/* Suggestions dropdown */
.item-suggestions-box {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0,0,0,.15);
    z-index: 9999;
    max-height: 280px;
    overflow-y: auto;
}
.item-suggestion-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f3f5;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background .12s;
}
.item-suggestion-item:hover,
.item-suggestion-item.active {
    background: #e8f0fe;
}
.item-suggestion-item .sug-code {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    color: #1a73e8;
    min-width: 110px;
    font-size: .85rem;
}
.item-suggestion-item .sug-desc {
    flex: 1;
    font-size: .88rem;
    color: #343a40;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.item-suggestion-item .sug-price {
    font-weight: 700;
    color: #198754;
    font-size: .88rem;
}

/* Status messages */
#itemStatus .status-ok   { color: #198754; font-weight: 600; }
#itemStatus .status-err  { color: #dc3545; font-weight: 600; }
#itemStatus .status-info { color: #0d6efd; }

/* Invoice grid row delete button */
.btn-row-del {
    background: none;
    border: none;
    color: #adb5bd;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: .85rem;
    transition: color .15s, background .15s;
}
.btn-row-del:hover { color: #dc3545; background: #fff5f5; }

/* Price cell input */
.price-cell-input {
    width: 90px;
    text-align: right;
    border: 1px solid transparent;
    background: transparent;
    padding: 2px 4px;
    border-radius: 4px;
    transition: border-color .15s, background .15s;
}
.price-cell-input:focus {
    border-color: #0d6efd;
    background: #fff;
    outline: none;
}

kbd {
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 1px 5px;
    font-size: .78rem;
    color: #495057;
}

/* ── Tax toggle switch (iOS style) ─────────────────── */
.tax-toggle {
    position: relative;
    display: inline-block;
    width: 34px;
    height: 18px;
    margin: 0;
    cursor: pointer;
    flex-shrink: 0;
}
.tax-toggle input { opacity: 0; width: 0; height: 0; }
.tax-slider {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,.2);
    border-radius: 18px;
    transition: background .2s;
}
.tax-slider::before {
    content: '';
    position: absolute;
    width: 12px;
    height: 12px;
    left: 3px;
    top: 3px;
    background: #fff;
    border-radius: 50%;
    transition: transform .2s;
}
.tax-toggle input:checked + .tax-slider { background: #4ade80; }
.tax-toggle input:checked + .tax-slider::before { transform: translateX(16px); }
</style>

<!-- ═══════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    /* ── DOM refs ─────────────────────────────────── */
    const codeInput     = document.getElementById('itemCodeInput');
    const descInput     = document.getElementById('itemDesc');
    const priceInput    = document.getElementById('itemPrice');
    const qtyInput      = document.getElementById('itemQty');
    const addBtn        = document.getElementById('addItemBtn');
    const gridBody      = document.getElementById('posGridBody');
    const emptyRow      = document.getElementById('emptyRow');
    const statusEl      = document.getElementById('itemStatus');
    const billDiscEl    = document.getElementById('billDiscount');
    const sugBox        = document.getElementById('itemSuggestions');

    /* ── State ────────────────────────────────────── */
    let gridItems       = [];       // [{id, code, desc, price, qty, disc}]
    let selectedItem    = null;     // item resolved from lookup
    let searchTimer     = null;
    let activeSugIdx    = -1;
    let suggestions     = [];

    /* ═══════════════════════════════════════════════
       ITEM LOOKUP — AJAX
    ═══════════════════════════════════════════════ */
    function lookupItem(q) {
        q = q.trim();
        if (!q) { setStatus('', ''); clearSuggestions(); return; }

        setStatus('<i class="fas fa-spinner fa-spin"></i> Looking up…', 'info');

        fetch(`<?= APP_URL ?>/sales/search?q=${encodeURIComponent(q)}&limit=8`)
            .then(r => r.json())
            .then(rows => {
                if (!rows || rows.length === 0) {
                    setStatus(`<i class="fas fa-times-circle"></i> No item found for "<strong>${q}</strong>"`, 'err');
                    clearSuggestions();
                    selectedItem = null;
                    descInput.value = '';
                    priceInput.value = '0.00';
                    return;
                }

                suggestions = rows;

                if (rows.length === 1 && rows[0].stock_no.toUpperCase() === q.toUpperCase()) {
                    // Exact single match — auto-select
                    applyItem(rows[0]);
                    clearSuggestions();
                } else {
                    // Show suggestions
                    showSuggestions(rows);
                }
            })
            .catch(() => setStatus('<i class="fas fa-exclamation-triangle"></i> Network error', 'err'));
    }

    function applyItem(item) {
        selectedItem        = item;
        codeInput.value     = item.stock_no;
        descInput.value     = item.description;
        priceInput.value    = parseFloat(item.price1).toFixed(2);
        setStatus(
            `<i class="fas fa-check-circle"></i> <span class="status-ok">${item.stock_no}</span>` +
            ` — ${item.description} &nbsp;|&nbsp; <strong>$${parseFloat(item.price1).toFixed(2)}</strong>`,
            'ok'
        );
        clearSuggestions();
        qtyInput.focus();
        qtyInput.select();
    }

    function setStatus(html, type) {
        statusEl.innerHTML  = `<span class="status-${type}">${html}</span>`;
    }

    /* ── Suggestions ─────────────────────────────── */
    function showSuggestions(rows) {
        sugBox.innerHTML    = '';
        activeSugIdx        = -1;

        rows.forEach((row, i) => {
            const div       = document.createElement('div');
            div.className   = 'item-suggestion-item';
            div.dataset.idx = i;
            div.innerHTML   =
                `<span class="sug-code">${row.stock_no}</span>` +
                `<span class="sug-desc">${row.description}</span>` +
                `<span class="sug-price">$${parseFloat(row.price1).toFixed(2)}</span>`;

            div.addEventListener('mousedown', e => {
                e.preventDefault();
                applyItem(row);
            });
            sugBox.appendChild(div);
        });

        sugBox.style.display = 'block';
    }

    function clearSuggestions() {
        sugBox.style.display = 'none';
        sugBox.innerHTML     = '';
        activeSugIdx         = -1;
    }

    function moveSuggestion(dir) {
        const items = sugBox.querySelectorAll('.item-suggestion-item');
        if (!items.length) return;
        items[activeSugIdx]?.classList.remove('active');
        activeSugIdx = Math.max(0, Math.min(items.length - 1, activeSugIdx + dir));
        items[activeSugIdx]?.classList.add('active');
        items[activeSugIdx]?.scrollIntoView({ block: 'nearest' });
    }

    /* ── Keyboard navigation on code input ──────── */
    codeInput.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') { e.preventDefault(); moveSuggestion(1); return; }
        if (e.key === 'ArrowUp')   { e.preventDefault(); moveSuggestion(-1); return; }

        if (e.key === 'Enter') {
            e.preventDefault();
            if (activeSugIdx >= 0 && suggestions[activeSugIdx]) {
                applyItem(suggestions[activeSugIdx]);
                return;
            }
            // No suggestion selected — do a full lookup
            lookupItem(codeInput.value.trim());
            return;
        }

        if (e.key === 'Escape') { clearSuggestions(); return; }
        if (e.key === 'Tab') {
            if (activeSugIdx >= 0 && suggestions[activeSugIdx]) {
                e.preventDefault();
                applyItem(suggestions[activeSugIdx]);
            }
        }
    });

    /* ── Live search as user types ──────────────── */
    codeInput.addEventListener('input', () => {
        selectedItem = null;
        clearTimeout(searchTimer);
        const q = codeInput.value.trim();
        if (q.length < 1) { clearSuggestions(); setStatus('', ''); return; }
        searchTimer = setTimeout(() => lookupItem(q), 280);
    });

    /* Close suggestions on outside click */
    document.addEventListener('click', e => {
        if (!e.target.closest('#itemCodeInput') && !e.target.closest('#itemSuggestions')) {
            clearSuggestions();
        }
    });

    function requestManagerOverride(message = "Manager PIN required to override standard selling price:") {
        const pin = prompt(message);
        const validPins = ['1234', '5555', '9999'];
        if (validPins.includes(pin)) {
            return true;
        }
        alert("Invalid Manager PIN. Override denied.");
        return false;
    }

    /* ── ADD ITEM ───────────────────────────────── */
    function addItem() {
        if (!selectedItem) {
            setStatus('<i class="fas fa-exclamation-triangle"></i> Please look up an item first (type ID and press Enter)', 'err');
            codeInput.focus();
            return;
        }

        const price = parseFloat(priceInput.value) || 0;
        const qty   = parseFloat(qtyInput.value)   || 1;
        const disc  = 0;

        const masterPrice = parseFloat(selectedItem.price1) || 0;
        if (price < masterPrice) {
            if (!requestManagerOverride(`Manager authorization required to sell ${selectedItem.stock_no} below Master price $${masterPrice.toFixed(2)}:`)) {
                priceInput.value = masterPrice.toFixed(2);
                priceInput.focus();
                return;
            }
        }

        const existing = gridItems.find(r => r.id === selectedItem.id);
        if (existing) {
            existing.qty  += qty;
            setStatus(`<i class="fas fa-layer-group"></i> Qty updated → ${existing.qty}`, 'ok');
        } else {
            gridItems.push({
                id:    selectedItem.id,
                code:  selectedItem.stock_no,
                desc:  selectedItem.description,
                price: price,
                qty:   qty,
                disc:  disc,
                master_price: masterPrice
            });
        }

        renderGrid();

        // Reset inputs for next entry
        selectedItem    = null;
        codeInput.value = '';
        descInput.value = '';
        priceInput.value = '0.00';
        qtyInput.value  = '1';
        clearSuggestions();
        setStatus('', '');
        codeInput.focus();
    }

    addBtn.addEventListener('click', addItem);

    /* Also add when pressing Enter in the qty field */
    qtyInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addItem(); }
    });

    /* ═══════════════════════════════════════════════
       RENDER GRID
    ═══════════════════════════════════════════════ */
    function renderGrid() {
        if (gridItems.length === 0) {
            gridBody.innerHTML = '';
            emptyRow.style.display = '';
            gridBody.appendChild(emptyRow);
            updateTotals();
            return;
        }

        emptyRow.style.display = 'none';
        gridBody.innerHTML     = '';

        gridItems.forEach((item, i) => {
            const lineTotal = item.price * item.qty * (1 - item.disc / 100);
            const tr        = document.createElement('tr');
            tr.innerHTML    = `
                <td><strong class="font-monospace" style="font-size:.88rem">${item.code}</strong></td>
                <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${item.desc}">${item.desc}</td>
                <td style="text-align:right">
                    <input type="number" class="price-cell-input" value="${item.price.toFixed(2)}"
                           min="0" step="0.01" data-idx="${i}" data-field="price">
                </td>
                <td style="text-align:center">
                    <input type="number" class="price-cell-input text-center" value="${item.qty}"
                           min="0.01" step="0.01" style="width:68px" data-idx="${i}" data-field="qty">
                </td>
                <td style="text-align:right">
                    <input type="number" class="price-cell-input text-center" value="${item.disc}"
                           min="0" max="100" step="0.01" style="width:68px" data-idx="${i}" data-field="disc">
                </td>
                <td style="text-align:right;font-weight:700;color:#198754">$${lineTotal.toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn-row-del" data-idx="${i}" title="Remove">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            gridBody.appendChild(tr);
        });

        /* inline edit listeners */
        gridBody.querySelectorAll('.price-cell-input').forEach(inp => {
            inp.addEventListener('change', function () {
                const idx   = parseInt(this.dataset.idx);
                const field = this.dataset.field;
                const val   = parseFloat(this.value) || 0;
                
                if (field === 'price' && val < gridItems[idx].master_price) {
                    if (!requestManagerOverride(`Manager authorization required to edit price of ${gridItems[idx].code} below Master price $${gridItems[idx].master_price.toFixed(2)}:`)) {
                        this.value = gridItems[idx].price.toFixed(2);
                        return;
                    }
                }
                
                gridItems[idx][field] = val;
                renderGrid();
            });
        });

        /* delete listeners */
        gridBody.querySelectorAll('.btn-row-del').forEach(btn => {
            btn.addEventListener('click', function () {
                gridItems.splice(parseInt(this.dataset.idx), 1);
                renderGrid();
            });
        });

        updateTotals();
    }

    /* ═══════════════════════════════════════════════
       TOTALS
    ═══════════════════════════════════════════════ */
    const taxToggle     = document.getElementById('taxEnabled');
    const taxLabel      = document.getElementById('taxLabel');
    const handlingInput = document.getElementById('handlingPct');

    function updateTotals() {
        let subtotal = 0;
        gridItems.forEach(item => {
            subtotal += item.price * item.qty * (1 - item.disc / 100);
        });

        const billDiscPct  = parseFloat(billDiscEl.value) || 0;
        const discAmt      = subtotal * billDiscPct / 100;
        const afterDisc    = subtotal - discAmt;

        // Tax — optional
        const taxOn        = taxToggle.checked;
        const taxSel       = document.getElementById('taxSelector');
        const taxRate      = taxOn ? parseFloat(taxSel.value) : 0;
        const taxAmt       = afterDisc * (taxRate / 100);
        taxLabel.textContent = taxOn ? `Sales Tax (${taxRate}%)` : 'Sales Tax (OFF)';
        document.getElementById('summaryTax').style.opacity = taxOn ? '1' : '0.35';

        // Handling fee
        const handlingPct  = parseFloat(handlingInput.value) || 0;
        const handlingAmt  = afterDisc * handlingPct / 100;

        const grand = afterDisc + taxAmt + handlingAmt;

        document.getElementById('summarySubtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('summaryDisc').textContent     = '-$' + discAmt.toFixed(2);
        document.getElementById('summaryTax').textContent      = '$' + taxAmt.toFixed(2);
        document.getElementById('summaryHandling').textContent = '$' + handlingAmt.toFixed(2);
        document.getElementById('summaryTotal').textContent    = '$' + grand.toFixed(2);
    }

    billDiscEl.addEventListener('input', updateTotals);
    taxToggle.addEventListener('change', updateTotals);
    handlingInput.addEventListener('input', updateTotals);
    document.getElementById('taxSelector').addEventListener('change', updateTotals);

    /* ═══════════════════════════════════════════════
       CHECKOUT / SAVE
     ═══════════════════════════════════════════════ */
    function checkout() {
        if (gridItems.length === 0) {
            alert('Please add at least one item before checking out.');
            codeInput.focus();
            return;
        }

        // Compute totals to send
        let subtotal = 0;
        gridItems.forEach(item => {
            subtotal += item.price * item.qty * (1 - item.disc / 100);
        });

        const billDiscPct  = parseFloat(billDiscEl.value) || 0;
        const discAmt      = subtotal * billDiscPct / 100;
        const afterDisc    = subtotal - discAmt;
        const taxOn        = taxToggle.checked;
        const taxSel       = document.getElementById('taxSelector');
        const taxRate      = taxOn ? parseFloat(taxSel.value) : 0;
        const taxAmt       = afterDisc * (taxRate / 100);
        const taxTypeId    = taxOn ? taxSel.options[taxSel.selectedIndex].dataset.id : null;
        const handlingPct  = parseFloat(handlingInput.value) || 0;
        const handlingAmt  = afterDisc * handlingPct / 100;
        const grand        = afterDisc + taxAmt + handlingAmt;

        const custSel      = document.getElementById('customerSelector');
        const paymode      = document.getElementById('paymodeSelector').value;

        // Map items to include tax_type_id
        const mappedItems = gridItems.map(item => ({
            ...item,
            tax_type_id: taxTypeId
        }));

        const payload = {
            _csrf: '<?= CSRF::generate() ?>',
            customer_id: custSel.value || null,
            customer_name: custSel.value ? custSel.options[custSel.selectedIndex].text.trim() : 'Walk-in Customer',
            payment_mode: paymode,
            items: mappedItems,
            gross_amount: subtotal,
            disc_perc: billDiscPct,
            disc_amount: discAmt,
            tax_amount: taxAmt,
            handling_fee: handlingAmt,
            net_amount: grand,
            paid_amount: grand, // fully paid for standard POS checkout
            remarks: ''
        };

        const checkoutBtn = document.getElementById('checkoutBtn');
        const checkoutBtnSide = document.getElementById('checkoutBtnSide');
        checkoutBtn.disabled = checkoutBtnSide.disabled = true;
        checkoutBtn.innerHTML = checkoutBtnSide.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';

        fetch('<?= APP_URL ?>/sales/store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Open print layout in a clean popup window (triggers window.print() inside)
                const printUrl = '<?= APP_URL ?>/sales/print/' + data.sale_id;
                window.open(printUrl, 'InvoicePrint', 'width=850,height=900,scrollbars=yes');
                
                // Clear grid and reset page
                gridItems = [];
                renderGrid();
                clearAll();
                alert('Invoice #' + data.doc_no + ' saved and print popup opened successfully.');
            } else {
                alert('Error saving invoice: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to connect to server. Please try again.');
        })
        .finally(() => {
            checkoutBtn.disabled = checkoutBtnSide.disabled = false;
            checkoutBtn.innerHTML = checkoutBtnSide.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Save & Print Invoice';
        });
    }

    document.getElementById('checkoutBtn').addEventListener('click', checkout);
    document.getElementById('checkoutBtnSide').addEventListener('click', checkout);

    window.clearAll = function () {
        if (gridItems.length && !confirm('Clear all items?')) return;
        gridItems = [];
        renderGrid();
        codeInput.value  = '';
        descInput.value  = '';
        priceInput.value = '0.00';
        qtyInput.value   = '1';
        billDiscEl.value = '';
        setStatus('', '');
        codeInput.focus();
    };

    window.checkCustomerExemption = function () {
        const sel = document.getElementById('customerSelector');
        const opt = sel.options[sel.selectedIndex];
        const isExempt = opt.dataset.isExempt === "1";
        const taxToggle = document.getElementById('taxEnabled');
        const taxSel = document.getElementById('taxSelector');
        
        if (isExempt) {
            taxToggle.checked = true;
            taxSel.value = "0.0";
        } else {
            taxSel.value = "8.25"; // Default rate
        }
        updateTotals();
    };

    /* Init */
    renderGrid();
    codeInput.focus();

})();
</script>
