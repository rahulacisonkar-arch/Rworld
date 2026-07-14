<?php
/**
 * Sales Order — Create / Edit
 * Same item-lookup UX as POS but supports partial advance payment.
 */
$fromQuot = !empty($quotData);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-file-contract mr-2 text-primary"></i>New Sales Order</h1>
        <div class="page-subtitle">
            <?php if ($fromQuot): ?>
                Converting from Quotation <strong><?= $quotData['doc_no'] ?></strong>
            <?php else: ?>
                Quotation → <strong>Sales Order</strong> → Invoice
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex" style="gap:8px">
        <button class="btn btn-outline-secondary" onclick="clearAll()"><i class="fas fa-broom mr-1"></i>Clear</button>
        <button class="btn btn-primary btn-lg" id="saveOrderBtn" style="min-width:170px;border-radius:10px;font-weight:700">
            <i class="fas fa-save mr-1"></i>Save Order
        </button>
    </div>
</div>

<?php if ($fromQuot): ?>
<div class="alert alert-info mb-3">
    <i class="fas fa-info-circle mr-2"></i>
    Pre-filled from Quotation <strong><?= $quotData['doc_no'] ?></strong> — Adjust quantities or prices before saving.
</div>
<?php endif; ?>

<div class="row">

    <!-- ══════════ LEFT — Items ══════════ -->
    <div class="col-lg-8">
        <div class="qb-card mb-3">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-barcode mr-1 text-primary"></i>Order Items</span>
                <small class="text-muted ml-2">Type Stock ID → <kbd>Enter</kbd> to add</small>
            </div>
            <div class="qb-card-body pb-2">
                <div class="d-flex align-items-start" style="gap:8px; position:relative">

                    <div style="flex:0 0 190px; position:relative">
                        <label class="form-label mb-1 small font-weight-600 text-muted">STOCK NO / ID</label>
                        <input type="text" id="itemCodeInput" class="form-control font-monospace"
                               placeholder="e.g. FAB-00123" autocomplete="off" spellcheck="false">
                        <div id="itemSuggestions" class="item-suggestions-box" style="display:none"></div>
                    </div>

                    <div style="flex:1">
                        <label class="form-label mb-1 small font-weight-600 text-muted">DESCRIPTION</label>
                        <input type="text" id="itemDesc" class="form-control" readonly style="background:#f8f9fa">
                    </div>

                    <div style="flex:0 0 110px">
                        <label class="form-label mb-1 small font-weight-600 text-muted">UNIT PRICE</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                            <input type="number" id="itemPrice" class="form-control" step="0.01" min="0" value="0.00">
                        </div>
                    </div>

                    <div style="flex:0 0 80px">
                        <label class="form-label mb-1 small font-weight-600 text-muted">QTY</label>
                        <input type="number" id="itemQty" class="form-control text-center" value="1" min="0.01" step="0.01">
                    </div>

                    <div style="flex:0 0 auto; padding-top:22px">
                        <button type="button" class="btn btn-primary px-4" id="addItemBtn" style="height:38px">
                            <i class="fas fa-plus mr-1"></i>Add
                        </button>
                    </div>
                </div>
                <div id="itemStatus" class="mt-2" style="min-height:20px;font-size:.82rem"></div>
            </div>
        </div>

        <!-- Order Grid -->
        <div class="qb-card">
            <div class="qb-card-body p-0">
                <div class="table-responsive">
                    <table class="qb-table" id="orderGrid">
                        <thead>
                            <tr>
                                <th style="width:130px">Stock No</th>
                                <th>Description</th>
                                <th style="width:105px;text-align:right">Rate</th>
                                <th style="width:90px;text-align:center">Qty</th>
                                <th style="width:100px;text-align:right">Disc %</th>
                                <th style="width:115px;text-align:right">Line Total</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody id="orderGridBody">
                            <tr id="emptyRow">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-file-contract fa-2x mb-2 d-block" style="opacity:.3"></i>
                                    No items — type a Stock No above
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Remarks -->
        <div class="qb-card mt-3">
            <div class="qb-card-body">
                <label class="form-label font-weight-600">Remarks / Notes</label>
                <textarea id="orderRemarks" class="form-control" rows="2" placeholder="Internal notes, delivery instructions…"></textarea>
            </div>
        </div>
    </div>

    <!-- ══════════ RIGHT — Customer / Payment ══════════ -->
    <div class="col-lg-4">

        <!-- Customer -->
        <div class="qb-card mb-3">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-user-tie mr-1"></i>Customer</span>
            </div>
            <div class="qb-card-body">
                <div class="form-group mb-2">
                    <label class="form-label small text-muted font-weight-600">SELECT CUSTOMER</label>
                    <select class="form-control" id="customerSelector">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                <?= ($fromQuot && $quotData['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                                <?= $c['phone1'] ? '(' . htmlspecialchars($c['phone1']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($fromQuot && $quotData['source_quotation_id'] ?? false): ?>
                    <div class="form-group mb-0">
                        <label class="form-label small text-muted font-weight-600">SOURCE QUOTATION</label>
                        <input type="text" class="form-control" value="<?= $quotData['doc_no'] ?>" readonly>
                        <input type="hidden" id="sourceQuotId" value="<?= $quotData['id'] ?>">
                    </div>
                <?php else: ?>
                    <input type="hidden" id="sourceQuotId" value="<?= $fromQuot ? $quotData['id'] : '' ?>">
                <?php endif; ?>
            </div>
        </div>

        <!-- Advance Payment -->
        <div class="qb-card mb-3" style="border-left:4px solid #22c55e">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-rupee-sign mr-1 text-success"></i>Advance / Deposit</span>
                <small class="text-muted">(optional — can pay more later)</small>
            </div>
            <div class="qb-card-body">
                <div class="form-group mb-2">
                    <label class="form-label small text-muted font-weight-600">PAYMENT MODE</label>
                    <select class="form-control" id="paymodeSelector">
                        <option value="Cash">💵 Cash</option>
                        <option value="Card">💳 Card</option>
                        <option value="UPI">📱 UPI / Mobile Pay</option>
                        <option value="Check">🏦 Check</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label small text-muted font-weight-600">ADVANCE AMOUNT ($)</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="number" id="advanceAmount" class="form-control" min="0" step="0.01" value="0" placeholder="0.00">
                    </div>
                    <small class="text-muted">Leave 0 for no advance. Full order value or partial allowed.</small>
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
        <div class="qb-card mb-3" style="background:linear-gradient(135deg,#1a1f3a 0%,#2d3561 100%);color:#fff;border:none">
            <div class="qb-card-body">
                <div class="d-flex justify-content-between mb-2" style="font-size:.95rem">
                    <span class="text-white-50">Subtotal</span><strong id="sumSubtotal">$0.00</strong>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:.95rem">
                    <span class="text-white-50">Bill Discount</span><strong id="sumDisc" class="text-warning">-$0.00</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.95rem">
                    <div class="d-flex align-items-center" style="gap:8px">
                        <span class="text-white-50" id="taxLabel">Sales Tax (8.25%)</span>
                        <label class="tax-toggle" style="margin-bottom:0"><input type="checkbox" id="taxEnabled" checked><span class="tax-slider"></span></label>
                    </div>
                    <strong id="sumTax">$0.00</strong>
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
                    <strong id="sumHandling" class="text-info">$0.00</strong>
                </div>
                <hr style="border-color:rgba(255,255,255,.2)">
                <div class="d-flex justify-content-between" style="font-size:1.35rem">
                    <span>ORDER TOTAL</span><strong id="sumTotal" style="color:#4ade80">$0.00</strong>
                </div>
                <hr style="border-color:rgba(255,255,255,.15)">
                <div class="d-flex justify-content-between mb-1" style="font-size:.95rem">
                    <span class="text-white-50">Advance Paid</span><strong id="sumAdvance" class="text-success">$0.00</strong>
                </div>
                <div class="d-flex justify-content-between" style="font-size:1.1rem">
                    <span style="color:#fca5a5">Balance Due</span>
                    <strong id="sumBalance" style="color:#fca5a5">$0.00</strong>
                </div>
            </div>
        </div>

        <button class="btn btn-primary btn-block btn-lg" id="saveOrderBtnSide"
                style="border-radius:12px;font-weight:700;font-size:1.1rem;height:52px">
            <i class="fas fa-save mr-2"></i>Save Sales Order
        </button>
    </div>
</div>

<style>
.font-monospace{font-family:'Courier New',monospace}.font-weight-600{font-weight:600}
.item-suggestions-box{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #dee2e6;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.15);z-index:9999;max-height:280px;overflow-y:auto}
.item-suggestion-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f3f5;display:flex;align-items:center;gap:10px;transition:background .12s}
.item-suggestion-item:hover,.item-suggestion-item.active{background:#e8f0fe}
.sug-code{font-family:'Courier New',monospace;font-weight:700;color:#1a73e8;min-width:110px;font-size:.85rem}
.sug-desc{flex:1;font-size:.88rem;color:#343a40;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sug-price{font-weight:700;color:#198754;font-size:.88rem}
#itemStatus .status-ok{color:#198754;font-weight:600}
#itemStatus .status-err{color:#dc3545;font-weight:600}
#itemStatus .status-info{color:#0d6efd}
.btn-row-del{background:none;border:none;color:#adb5bd;cursor:pointer;padding:2px 6px;border-radius:4px;font-size:.85rem;transition:color .15s,background .15s}
.btn-row-del:hover{color:#dc3545;background:#fff5f5}
.price-cell-input{width:90px;text-align:right;border:1px solid transparent;background:transparent;padding:2px 4px;border-radius:4px;transition:border-color .15s,background .15s}
.price-cell-input:focus{border-color:#0d6efd;background:#fff;outline:none}
.tax-toggle{position:relative;display:inline-block;width:34px;height:18px;margin:0;cursor:pointer;flex-shrink:0}
.tax-toggle input{opacity:0;width:0;height:0}
.tax-slider{position:absolute;inset:0;background:rgba(255,255,255,.2);border-radius:18px;transition:background .2s}
.tax-slider::before{content:'';position:absolute;width:12px;height:12px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s}
.tax-toggle input:checked+.tax-slider{background:#4ade80}
.tax-toggle input:checked+.tax-slider::before{transform:translateX(16px)}
kbd{background:#e9ecef;border:1px solid #ced4da;border-radius:4px;padding:1px 5px;font-size:.78rem;color:#495057}
</style>

<script>
(function(){
'use strict';

/* ── DOM ── */
const codeInput     = document.getElementById('itemCodeInput');
const descInput     = document.getElementById('itemDesc');
const priceInput    = document.getElementById('itemPrice');
const qtyInput      = document.getElementById('itemQty');
const addBtn        = document.getElementById('addItemBtn');
const gridBody      = document.getElementById('orderGridBody');
const emptyRow      = document.getElementById('emptyRow');
const statusEl      = document.getElementById('itemStatus');
const billDiscEl    = document.getElementById('billDiscount');
const taxToggle     = document.getElementById('taxEnabled');
const taxLabel      = document.getElementById('taxLabel');
const handlingInput = document.getElementById('handlingPct');
const advanceInput  = document.getElementById('advanceAmount');
const sugBox        = document.getElementById('itemSuggestions');

let gridItems    = [];
let selectedItem = null;
let searchTimer  = null;
let activeSugIdx = -1;
let suggestions  = [];

/* ── Pre-fill from quotation ── */
<?php if ($fromQuot && !empty($quotItems)): ?>
(function(){
  const prefill = <?= json_encode(array_map(fn($l) => [
      'id'    => $l['item_id'],
      'code'  => $l['stock_no'],
      'desc'  => $l['description'],
      'price' => (float)$l['rate'],
      'qty'   => (float)$l['qty'],
      'disc'  => (float)$l['disc_perc'],
  ], $quotItems)) ?>;
  gridItems = prefill;
  renderGrid();
})();
<?php endif; ?>

/* ── AJAX lookup ── */
function lookupItem(q){
  q=q.trim(); if(!q){setStatus('','');clearSug();return;}
  setStatus('<i class="fas fa-spinner fa-spin"></i> Looking up…','info');
  fetch(`<?= APP_URL ?>/salesorder/search?q=${encodeURIComponent(q)}&limit=8`)
    .then(r=>r.json()).then(rows=>{
      if(!rows||!rows.length){setStatus(`<span class="status-err"><i class="fas fa-times-circle"></i> No item found for "${q}"</span>`,'');clearSug();selectedItem=null;descInput.value='';priceInput.value='0.00';return;}
      suggestions=rows;
      if(rows.length===1&&rows[0].stock_no.toUpperCase()===q.toUpperCase()){applyItem(rows[0]);clearSug();}
      else showSug(rows);
    }).catch(()=>setStatus('<span class="status-err">Network error</span>',''));
}
function applyItem(item){
  selectedItem=item;codeInput.value=item.stock_no;descInput.value=item.description;
  priceInput.value=parseFloat(item.price1).toFixed(2);
  setStatus(`<span class="status-ok"><i class="fas fa-check-circle"></i> ${item.stock_no}</span> — ${item.description} | <strong>$${parseFloat(item.price1).toFixed(2)}</strong>`,'');
  clearSug();qtyInput.focus();qtyInput.select();
}
function setStatus(html){statusEl.innerHTML=html;}
function showSug(rows){
  sugBox.innerHTML='';activeSugIdx=-1;
  rows.forEach((row,i)=>{
    const d=document.createElement('div');d.className='item-suggestion-item';d.dataset.idx=i;
    d.innerHTML=`<span class="sug-code">${row.stock_no}</span><span class="sug-desc">${row.description}</span><span class="sug-price">$${parseFloat(row.price1).toFixed(2)}</span>`;
    d.addEventListener('mousedown',e=>{e.preventDefault();applyItem(row);});
    sugBox.appendChild(d);
  });
  sugBox.style.display='block';
}
function clearSug(){sugBox.style.display='none';sugBox.innerHTML='';activeSugIdx=-1;}
function moveSug(dir){
  const items=sugBox.querySelectorAll('.item-suggestion-item');if(!items.length)return;
  items[activeSugIdx]?.classList.remove('active');
  activeSugIdx=Math.max(0,Math.min(items.length-1,activeSugIdx+dir));
  items[activeSugIdx]?.classList.add('active');items[activeSugIdx]?.scrollIntoView({block:'nearest'});
}
codeInput.addEventListener('keydown',e=>{
  if(e.key==='ArrowDown'){e.preventDefault();moveSug(1);return;}
  if(e.key==='ArrowUp'){e.preventDefault();moveSug(-1);return;}
  if(e.key==='Enter'){e.preventDefault();if(activeSugIdx>=0&&suggestions[activeSugIdx]){applyItem(suggestions[activeSugIdx]);return;}lookupItem(codeInput.value.trim());return;}
  if(e.key==='Escape'){clearSug();return;}
  if(e.key==='Tab'&&activeSugIdx>=0&&suggestions[activeSugIdx]){e.preventDefault();applyItem(suggestions[activeSugIdx]);}
});
codeInput.addEventListener('input',()=>{
  selectedItem=null;clearTimeout(searchTimer);
  const q=codeInput.value.trim();if(q.length<1){clearSug();setStatus('');return;}
  searchTimer=setTimeout(()=>lookupItem(q),280);
});
document.addEventListener('click',e=>{
  if(!e.target.closest('#itemCodeInput')&&!e.target.closest('#itemSuggestions'))clearSug();
});

/* ── Add item ── */
function addItem(){
  if(!selectedItem){setStatus('<span class="status-err"><i class="fas fa-exclamation-triangle"></i> Look up an item first</span>');codeInput.focus();return;}
  const price=parseFloat(priceInput.value)||0;
  const qty=parseFloat(qtyInput.value)||1;
  const existing=gridItems.find(r=>r.id===selectedItem.id);
  if(existing){existing.qty+=qty;setStatus(`<span class="status-ok">Qty updated → ${existing.qty}</span>`);}
  else gridItems.push({id:selectedItem.id,code:selectedItem.stock_no,desc:selectedItem.description,price,qty,disc:0});
  renderGrid();
  selectedItem=null;codeInput.value='';descInput.value='';priceInput.value='0.00';qtyInput.value='1';clearSug();setStatus('');codeInput.focus();
}
addBtn.addEventListener('click',addItem);
qtyInput.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();addItem();}});

/* ── Grid render ── */
function renderGrid(){
  if(!gridItems.length){gridBody.innerHTML='';emptyRow.style.display='';gridBody.appendChild(emptyRow);updateTotals();return;}
  emptyRow.style.display='none';gridBody.innerHTML='';
  gridItems.forEach((item,i)=>{
    const lt=item.price*item.qty*(1-item.disc/100);
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td><strong class="font-monospace" style="font-size:.88rem">${item.code}</strong></td>
      <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${item.desc}</td>
      <td style="text-align:right"><input type="number" class="price-cell-input" value="${item.price.toFixed(2)}" min="0" step="0.01" data-idx="${i}" data-field="price"></td>
      <td style="text-align:center"><input type="number" class="price-cell-input text-center" value="${item.qty}" min="0.01" step="0.01" style="width:68px" data-idx="${i}" data-field="qty"></td>
      <td style="text-align:right"><input type="number" class="price-cell-input text-center" value="${item.disc}" min="0" max="100" step="0.01" style="width:68px" data-idx="${i}" data-field="disc"></td>
      <td style="text-align:right;font-weight:700;color:#198754">$${lt.toFixed(2)}</td>
      <td><button class="btn-row-del" data-idx="${i}"><i class="fas fa-trash-alt"></i></button></td>`;
    gridBody.appendChild(tr);
  });
  gridBody.querySelectorAll('.price-cell-input').forEach(inp=>{
    inp.addEventListener('change',function(){gridItems[+this.dataset.idx][this.dataset.field]=parseFloat(this.value)||0;renderGrid();});
  });
  gridBody.querySelectorAll('.btn-row-del').forEach(btn=>{
    btn.addEventListener('click',function(){gridItems.splice(+this.dataset.idx,1);renderGrid();});
  });
  updateTotals();
}

/* ── Totals ── */
function updateTotals(){
  let subtotal=0;gridItems.forEach(item=>subtotal+=item.price*item.qty*(1-item.disc/100));
  const discPct=parseFloat(billDiscEl.value)||0;
  const discAmt=subtotal*discPct/100;
  const afterDisc=subtotal-discAmt;
  const taxOn=taxToggle.checked;
  const taxSel=document.getElementById('taxSelector');
  const taxRate=taxOn?parseFloat(taxSel.value):0;
  const taxAmt=afterDisc*(taxRate/100);
  taxLabel.textContent=taxOn?`Sales Tax (${taxRate}%)`:`Sales Tax (OFF)`;
  document.getElementById('sumTax').style.opacity=taxOn?'1':'0.35';
  const handlingPct=parseFloat(handlingInput.value)||0;
  const handlingAmt=afterDisc*handlingPct/100;
  const grand=afterDisc+taxAmt+handlingAmt;
  const advance=parseFloat(advanceInput.value)||0;
  const balance=Math.max(0,grand-advance);

  document.getElementById('sumSubtotal').textContent='$'+subtotal.toFixed(2);
  document.getElementById('sumDisc').textContent='-$'+discAmt.toFixed(2);
  document.getElementById('sumTax').textContent='$'+taxAmt.toFixed(2);
  document.getElementById('sumHandling').textContent='$'+handlingAmt.toFixed(2);
  document.getElementById('sumTotal').textContent='$'+grand.toFixed(2);
  document.getElementById('sumAdvance').textContent='$'+advance.toFixed(2);
  document.getElementById('sumBalance').textContent='$'+balance.toFixed(2);
  return {subtotal,discPct,discAmt,taxAmt,handlingAmt,grand,advance,balance,taxTypeId:taxOn?taxSel.options[taxSel.selectedIndex].dataset.id:null};
}
[billDiscEl,taxToggle,handlingInput,advanceInput,document.getElementById('taxSelector')].forEach(el=>el.addEventListener('input',updateTotals));
[billDiscEl,taxToggle,handlingInput,advanceInput,document.getElementById('taxSelector')].forEach(el=>el.addEventListener('change',updateTotals));

/* ── Save ── */
function saveOrder(){
  if(!gridItems.length){alert('Add at least one item before saving.');codeInput.focus();return;}
  const t=updateTotals();
  const custSel=document.getElementById('customerSelector');
  const mappedItems=gridItems.map(item=>({
    ...item,
    tax_type_id:t.taxTypeId
  }));
  const payload={
    _csrf:'<?= CSRF::generate() ?>',
    customer_id:custSel.value||null,
    customer_name:custSel.value?custSel.options[custSel.selectedIndex].dataset.name:'Walk-in Customer',
    payment_mode:document.getElementById('paymodeSelector').value,
    items:mappedItems,
    gross_amount:t.subtotal,
    disc_perc:parseFloat(billDiscEl.value)||0,
    disc_amount:t.discAmt,
    tax_amount:t.taxAmt,
    handling_fee:t.handlingAmt,
    net_amount:t.grand,
    paid_amount:t.advance,
    remarks:document.getElementById('orderRemarks').value,
    source_quotation_id:document.getElementById('sourceQuotId').value||null
  };

  const btn=document.getElementById('saveOrderBtn');
  const btnSide=document.getElementById('saveOrderBtnSide');
  btn.disabled=btnSide.disabled=true;
  btn.innerHTML=btnSide.innerHTML='<i class="fas fa-spinner fa-spin mr-1"></i>Saving…';

  fetch('<?= APP_URL ?>/salesorder/create',{
    method:'POST',
    headers:{'Content-Type':'application/json','Accept':'application/json'},
    body:JSON.stringify(payload)
  }).then(r=>r.json()).then(data=>{
    if(data.success){
      // Open print layout popup automatically
      const printUrl = '<?= APP_URL ?>/salesorder/print/' + data.order_id;
      window.open(printUrl, 'OrderPrint', 'width=850,height=900,scrollbars=yes');
      
      alert(`Sales Order ${data.doc_no} saved successfully.`);
      window.location.href='<?= APP_URL ?>/salesorder/view/'+data.order_id;
    } else {
      alert('Error: '+(data.message||'Unknown error'));
      btn.disabled=btnSide.disabled=false;
      btn.innerHTML=btnSide.innerHTML='<i class="fas fa-save mr-1"></i>Save Order';
    }
  }).catch(()=>{alert('Network error — please try again.');btn.disabled=btnSide.disabled=false;btn.innerHTML=btnSide.innerHTML='<i class="fas fa-save mr-1"></i>Save Order';});
}

document.getElementById('saveOrderBtn').addEventListener('click',saveOrder);
document.getElementById('saveOrderBtnSide').addEventListener('click',saveOrder);

window.clearAll=function(){
  if(gridItems.length&&!confirm('Clear all items?'))return;
  gridItems=[];renderGrid();codeInput.value='';descInput.value='';priceInput.value='0.00';qtyInput.value='1';
  billDiscEl.value='';advanceInput.value='0';setStatus('');codeInput.focus();
};

renderGrid();
codeInput.focus();
})();
</script>
