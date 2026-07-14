<?php
/**
 * Professional Top-Tier Retail Invoice Print Template
 * Handles: TAX INVOICE, SALES ORDER, and QUOTATION
 */
$gross = (float)($record['gross_amount'] ?? 0);
$disc  = (float)($record['bill_disc_amount'] ?? 0);
$tax   = (float)($record['total_tax'] ?? 0);
$fee   = (float)($record['handling_fee'] ?? 0);
$net   = (float)($record['net_amount'] ?? 0);
$paid  = (float)($record['paid_amount'] ?? 0);
$bal   = (float)($record['balance_due'] ?? $net - $paid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $type ?> — <?= htmlspecialchars($record['doc_no']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --green: #10b981;
            --amber: #f59e0b;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            color: var(--text-dark);
            background: #fff;
            line-height: 1.5;
            padding: 40px;
            font-size: 14px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Container designed to fit on A4 / Letter */
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* ── Header ── */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 25px;
            margin-bottom: 30px;
        }

        .brand-logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            background: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 20px;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .brand-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .doc-type-badge {
            text-align: right;
        }

        .doc-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .doc-no {
            font-family: 'Space Mono', monospace;
            font-size: 16px;
            font-weight: 700;
            color: var(--text-muted);
        }

        /* ── Meta Info (From/To) ── */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .meta-section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 5px;
        }

        .meta-content {
            font-size: 13.5px;
            color: #334155;
        }

        .meta-content strong {
            font-size: 15px;
            color: var(--text-dark);
            display: block;
            margin-bottom: 4px;
        }

        .meta-right {
            text-align: right;
        }
        .meta-right .meta-content {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .meta-label-value {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 3px;
        }
        .meta-label-value span:first-child {
            color: var(--text-muted);
            font-weight: 500;
        }
        .meta-label-value span:last-child {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* ── Items Table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background: var(--bg-light);
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .items-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            color: #334155;
            vertical-align: middle;
        }

        .items-table tbody tr:last-child td {
            border-bottom: 2px solid var(--primary);
        }

        .item-sku {
            font-family: 'Space Mono', monospace;
            font-size: 12px;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
        }

        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }

        /* ── Totals section ── */
        .totals-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 40px;
            margin-bottom: 40px;
        }

        .notes-area {
            flex: 1;
            max-width: 400px;
            background: var(--bg-light);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .notes-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .notes-content {
            font-size: 12.5px;
            color: #475569;
        }

        .totals-box {
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #334155;
        }

        .total-row.grand-total {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            border-top: 2px dashed var(--border-color);
            border-bottom: 2px dashed var(--border-color);
            padding: 10px 0;
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .total-row.balance-due {
            color: var(--amber);
            font-weight: 700;
        }
        .total-row.fully-paid {
            color: var(--green);
            font-weight: 700;
        }

        /* ── Footer ── */
        .invoice-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 25px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 50px;
        }

        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="invoice-container">

    <!-- Header -->
    <div class="invoice-header">
        <div class="brand-logo-area">
            <div class="brand-logo">A</div>
            <div>
                <div class="brand-name"><?= htmlspecialchars($company['name'] ?? 'Artee Fabrics and Home') ?></div>
                <div class="brand-subtitle">Retail, Wholesale &amp; Custom Design</div>
            </div>
        </div>
        <div class="doc-type-badge">
            <div class="doc-title"><?= $type ?></div>
            <div class="doc-no"><?= htmlspecialchars($record['doc_no']) ?></div>
        </div>
    </div>

    <!-- Meta Grid -->
    <div class="meta-grid">
        <div>
            <div class="meta-section-title">Store Details</div>
            <div class="meta-content">
                <strong>Head Office Branch</strong>
                <?= htmlspecialchars($company['address1'] ?? '') ?><br>
                <?= htmlspecialchars($company['city'] ?? '') ?>, <?= htmlspecialchars($company['state'] ?? '') ?><br>
                United States
            </div>
        </div>
        <div class="meta-right">
            <div class="meta-section-title">Customer / Billing</div>
            <div class="meta-content">
                <strong><?= htmlspecialchars($record['customer_display']) ?></strong>
                <?php if (!empty($record['customer_phone'])): ?>
                    Phone: <?= htmlspecialchars($record['customer_phone']) ?><br>
                <?php endif; ?>
                <div style="margin-top: 12px; font-size: 12px;">
                    <div class="meta-label-value">
                        <span>Date:</span>
                        <span><?= date('M d, Y', strtotime($record['doc_date'])) ?></span>
                    </div>
                    <?php if (!empty($record['doc_time'])): ?>
                        <div class="meta-label-value">
                            <span>Time:</span>
                            <span><?= date('h:i A', strtotime($record['doc_time'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($record['valid_till'])): ?>
                        <div class="meta-label-value">
                            <span>Valid Till:</span>
                            <span><?= date('M d, Y', strtotime($record['valid_till'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50px;" class="text-center">#</th>
                <th>Item / Description</th>
                <th style="width: 100px;" class="text-right">Price</th>
                <th style="width: 80px;" class="text-center">Qty</th>
                <th style="width: 80px;" class="text-right">Disc %</th>
                <th style="width: 120px;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $idx => $line): ?>
            <tr>
                <td class="text-center"><?= $idx + 1 ?></td>
                <td>
                    <strong><?= htmlspecialchars($line['description']) ?></strong>
                    <?php if (!empty($line['stock_no'])): ?>
                        <span class="item-sku">Stock Code: <?= htmlspecialchars($line['stock_no']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-right">$<?= number_format($line['rate'], 2) ?></td>
                <td class="text-center"><?= number_format($line['qty'], 2) ?></td>
                <td class="text-right"><?= (float)($line['disc_perc'] ?? 0) ?>%</td>
                <td class="text-right" style="font-weight: 600;">$<?= number_format($line['net_value'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Area -->
    <div class="totals-section">
        <div class="notes-area">
            <div class="notes-title">Terms &amp; Conditions</div>
            <div class="notes-content">
                <?php if (!empty($record['remarks'])): ?>
                    <strong>Notes:</strong> <?= htmlspecialchars($record['remarks']) ?><br><br>
                <?php endif; ?>
                Thank you for your business. All custom cut fabric orders are final. Returns are only accepted within 14 days with original receipt on uncut bolts.
            </div>
        </div>
        <div class="totals-box">
            <div class="total-row">
                <span>Subtotal</span>
                <span>$<?= number_format($gross, 2) ?></span>
            </div>
            <?php if ($disc > 0): ?>
            <div class="total-row">
                <span>Discount</span>
                <span style="color: #ef4444;">-$<?= number_format($disc, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row">
                <span>Sales Tax</span>
                <span>$<?= number_format($tax, 2) ?></span>
            </div>
            <?php if ($fee > 0): ?>
            <div class="total-row">
                <span>Handling Fee</span>
                <span>$<?= number_format($fee, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row grand-total">
                <span>Total</span>
                <span>$<?= number_format($net, 2) ?></span>
            </div>
            <div class="total-row">
                <span>Paid amount</span>
                <span style="color: var(--green); font-weight: 600;">$<?= number_format($paid, 2) ?></span>
            </div>
            
            <?php if ($bal <= 0): ?>
                <div class="total-row fully-paid">
                    <span>Balance Due</span>
                    <span>✓ Fully Paid</span>
                </div>
            <?php else: ?>
                <div class="total-row balance-due">
                    <span>Balance Due</span>
                    <span>$<?= number_format($bal, 2) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 5px;">Thank you for shopping at Artee Fabrics and Home!</div>
        <div>If you have any questions about this document, please contact support.</div>
    </div>

</div>

<script>
    // Trigger browser printing popup automatically
    window.addEventListener('DOMContentLoaded', () => {
        window.print();
    });
</script>
</body>
</html>
