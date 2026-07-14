<?php
/**
 * Sales Order — View Detail
 */
$statusColors = [
    'open'      => ['bg' => '#3b82f6', 'label' => 'Open'],
    'partial'   => ['bg' => '#f59e0b', 'label' => 'Partial Paid'],
    'closed'    => ['bg' => '#22c55e', 'label' => 'Fully Paid'],
    'cancelled' => ['bg' => '#6b7280', 'label' => 'Cancelled'],
];
$sc      = $statusColors[$order['status']] ?? ['bg' => '#6b7280', 'label' => ucfirst($order['status'])];
$balance = (float)$order['net_amount'] - (float)$order['paid_amount'];
$canAct  = !in_array($order['status'], ['closed', 'cancelled']);
$converted = !empty($order['converted_sale_id']);
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
    <div>
        <h1 class="page-title"><i class="fas fa-file-contract mr-2 text-primary"></i><?= $order['doc_no'] ?></h1>
        <div class="page-subtitle">
            <?= date('d M Y', strtotime($order['doc_date'])) ?>
            &nbsp;·&nbsp; <?= htmlspecialchars($order['customer_display']) ?>
            &nbsp;·&nbsp;
            <span class="status-badge" style="background:<?= $sc['bg'] ?>15;color:<?= $sc['bg'] ?>;border:1px solid <?= $sc['bg'] ?>40;padding:2px 10px;border-radius:20px;font-size:.78rem;font-weight:700;vertical-align:middle">
                <?= $sc['label'] ?>
            </span>
            <?php if ($converted): ?>
                <span class="ml-2 text-success font-weight-600"><i class="fas fa-check-circle"></i> Converted → Invoice</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex" style="gap:8px;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/salesorder" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        <?php if ($canAct && !$converted): ?>
            <form method="POST" action="<?= APP_URL ?>/salesorder/convert/<?= $order['id'] ?>" style="display:inline" onsubmit="return confirm('Convert this Sales Order to a final Invoice?\n\nThis cannot be undone.')">
                <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
                <button class="btn btn-warning"><i class="fas fa-arrow-right mr-1"></i>Convert to Invoice</button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/salesorder/cancel/<?= $order['id'] ?>" style="display:inline" onsubmit="return confirm('Cancel this order?')">
                <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
                <button class="btn btn-outline-danger"><i class="fas fa-times mr-1"></i>Cancel</button>
            </form>
        <?php endif; ?>
        <?php if ($converted): ?>
            <span class="btn btn-success disabled">
                <i class="fas fa-check-circle mr-1"></i>Invoice Created
            </span>
        <?php endif; ?>
        <button class="btn btn-outline-secondary" onclick="window.open('<?= APP_URL ?>/salesorder/print/<?= $order['id'] ?>', 'OrderPrint', 'width=850,height=900,scrollbars=yes')"><i class="fas fa-print mr-1"></i>Print</button>
    </div>
</div>

<!-- Workflow Breadcrumb -->
<div class="qb-card mb-3" style="background:linear-gradient(90deg,#1e293b,#0f172a);border:none;color:#fff">
    <div class="qb-card-body py-3 d-flex align-items-center" style="gap:0">
        <?php
        $steps = [
            ['icon' => 'fa-file-alt',      'label' => 'Quotation',    'url' => $order['source_quotation_id'] ? APP_URL.'/quotation/view/'.$order['source_quotation_id'] : null],
            ['icon' => 'fa-file-contract', 'label' => 'Sales Order',  'url' => null],
            ['icon' => 'fa-file-invoice',  'label' => 'Invoice',      'url' => $converted ? APP_URL.'/salesreturn' : null],
        ];
        foreach ($steps as $i => $step):
            $isActive = ($step['label'] === 'Sales Order');
            $isDone   = ($step['label'] === 'Quotation' && $order['source_quotation_id'])
                     || ($step['label'] === 'Invoice'   && $converted);
        ?>
        <div class="d-flex align-items-center">
            <?php if ($i > 0): ?>
            <div style="width:40px;height:2px;background:<?= $isDone||$isActive?'#4ade80':'rgba(255,255,255,.15)' ?>"></div>
            <?php endif; ?>
            <div style="text-align:center;padding:0 8px">
                <?php if ($step['url']): ?>
                    <a href="<?= $step['url'] ?>" style="color:<?= $isDone?'#4ade80':'rgba(255,255,255,.5)' ?>;text-decoration:none">
                        <i class="fas <?= $step['icon'] ?> fa-lg"></i><br>
                        <small style="font-size:.72rem"><?= $step['label'] ?></small>
                    </a>
                <?php else: ?>
                    <span style="color:<?= $isActive?'#60a5fa':($isDone?'#4ade80':'rgba(255,255,255,.3)') ?>">
                        <i class="fas <?= $step['icon'] ?> fa-lg"></i><br>
                        <small style="font-size:.72rem"><?= $step['label'] ?></small>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row">
    <!-- Left — Line Items -->
    <div class="col-lg-8">
        <div class="qb-card mb-3">
            <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-list mr-1"></i>Order Items</span></div>
            <div class="qb-card-body p-0">
                <table class="qb-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Stock No</th>
                            <th>Description</th>
                            <th style="text-align:right">Rate</th>
                            <th style="text-align:center">Qty</th>
                            <th style="text-align:right">Disc%</th>
                            <th style="text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $i => $ln): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td class="font-monospace font-weight-600"><?= htmlspecialchars($ln['stock_no'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ln['description'] ?? '') ?></td>
                            <td style="text-align:right">$<?= number_format($ln['rate'], 2) ?></td>
                            <td style="text-align:center"><?= number_format($ln['qty'], 2) ?></td>
                            <td style="text-align:right"><?= number_format($ln['disc_perc'], 2) ?>%</td>
                            <td style="text-align:right;font-weight:700;color:#198754">$<?= number_format($ln['net_value'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:#f8f9fa">
                        <tr>
                            <td colspan="5"></td>
                            <td style="text-align:right;font-weight:600">ORDER TOTAL</td>
                            <td style="text-align:right;font-weight:700;font-size:1.05rem">$<?= number_format($order['net_amount'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Payment History -->
        <div class="qb-card" id="pay">
            <div class="qb-card-header d-flex justify-content-between align-items-center">
                <span class="qb-card-title"><i class="fas fa-rupee-sign mr-1 text-success"></i>Payment History</span>
                <span style="font-size:.85rem;color:#6b7280"><?= count($payments) ?> payment(s)</span>
            </div>
            <div class="qb-card-body p-0">
                <?php if (empty($payments)): ?>
                    <p class="text-center text-muted py-4"><i class="fas fa-info-circle mr-1"></i>No payments recorded yet.</p>
                <?php else: ?>
                    <table class="qb-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Mode</th>
                                <th>Reference</th>
                                <th style="text-align:right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($p['doc_date'])) ?></td>
                                <td><span class="badge badge-secondary"><?= $p['payment_mode'] ?></span></td>
                                <td class="text-muted"><?= htmlspecialchars($p['reference'] ?: $p['narration'] ?: '—') ?></td>
                                <td style="text-align:right;font-weight:700;color:#22c55e">$<?= number_format($p['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background:#f0fdf4">
                            <tr>
                                <td colspan="3" style="font-weight:700">Total Paid</td>
                                <td style="text-align:right;font-weight:700;color:#16a34a;font-size:1.05rem">$<?= number_format($totalPaid, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right — Summary & Payment Form -->
    <div class="col-lg-4">

        <!-- Order Summary -->
        <div class="qb-card mb-3" style="background:linear-gradient(135deg,#1a1f3a,#2d3561);color:#fff;border:none">
            <div class="qb-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Order Value</span><strong>$<?= number_format($order['net_amount'], 2) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Total Paid</span><strong class="text-success">$<?= number_format($order['paid_amount'], 2) ?></strong></div>
                <hr style="border-color:rgba(255,255,255,.2)">
                <div class="d-flex justify-content-between" style="font-size:1.25rem">
                    <span style="color:<?= $balance > 0 ? '#fca5a5' : '#4ade80' ?>">Balance Due</span>
                    <strong style="color:<?= $balance > 0 ? '#fca5a5' : '#4ade80' ?>">
                        <?= $balance > 0 ? '$'.number_format($balance, 2) : '✓ Fully Paid' ?>
                    </strong>
                </div>
            </div>
        </div>

        <!-- Record Payment Form -->
        <?php if ($canAct && !$converted): ?>
        <div class="qb-card mb-3" style="border-left:4px solid #22c55e">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-plus-circle mr-1 text-success"></i>Record Payment</span>
            </div>
            <div class="qb-card-body">
                <form method="POST" action="<?= APP_URL ?>/salesorder/pay/<?= $order['id'] ?>">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-group mb-2">
                        <label class="form-label small font-weight-600 text-muted">AMOUNT ($) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                            <input type="number" name="amount" class="form-control" required
                                   min="0.01" step="0.01"
                                   value="<?= $balance > 0 ? number_format($balance, 2, '.', '') : '' ?>"
                                   placeholder="0.00">
                        </div>
                        <?php if ($balance > 0): ?>
                        <small class="text-muted">Balance: $<?= number_format($balance, 2) ?> — pre-filled for full settlement.</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-2">
                        <label class="form-label small font-weight-600 text-muted">PAYMENT MODE</label>
                        <select name="payment_mode" class="form-control">
                            <option>Cash</option>
                            <option>Card</option>
                            <option>UPI</option>
                            <option>Check</option>
                            <option>Bank Transfer</option>
                        </select>
                    </div>

                    <div class="form-group mb-2">
                        <label class="form-label small font-weight-600 text-muted">REFERENCE / CHEQUE NO</label>
                        <input type="text" name="reference" class="form-control" placeholder="Optional">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label small font-weight-600 text-muted">NARRATION</label>
                        <textarea name="narration" class="form-control" rows="2" placeholder="Optional note"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-block btn-lg" style="border-radius:10px;font-weight:700">
                        <i class="fas fa-check-circle mr-1"></i>Record Payment
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($converted): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle mr-2"></i>
            This order has been converted to an invoice. No further payments can be recorded here.
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="qb-card">
            <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-bolt mr-1"></i>Quick Actions</span></div>
            <div class="qb-card-body">
                <div class="d-flex flex-column" style="gap:8px">
                    <a href="<?= APP_URL ?>/salesorder/create" class="btn btn-outline-primary btn-block">
                        <i class="fas fa-plus mr-1"></i>New Sales Order
                    </a>
                    <?php if ($canAct && !$converted): ?>
                    <form method="POST" action="<?= APP_URL ?>/salesorder/convert/<?= $order['id'] ?>" onsubmit="return confirm('Convert to Invoice now?')">
                        <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
                        <button class="btn btn-warning btn-block"><i class="fas fa-arrow-right mr-1"></i>Convert → Invoice</button>
                    </form>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/salesorder" class="btn btn-outline-secondary btn-block">
                        <i class="fas fa-list mr-1"></i>All Sales Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.font-monospace{font-family:'Courier New',monospace}
.font-weight-600{font-weight:600}
@media print {
  .page-header .btn, .qb-card:has(form), #pay + .qb-card { display:none !important; }
}
</style>
