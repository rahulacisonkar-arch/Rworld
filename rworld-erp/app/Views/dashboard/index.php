<?php
/**
 * QuickBill POS — Dashboard View
 * app/Views/dashboard/index.php
 */
?>
<style>
/* ── Dashboard-specific styles ───────────────────────────────────────────── */
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.kpi-card {
    background: #fff;
    border-radius: 16px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f5;
    transition: transform .2s, box-shadow .2s;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
.kpi-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.kpi-icon.indigo  { background: rgba(79,70,229,0.12); color: #4f46e5; }
.kpi-icon.emerald { background: rgba(16,185,129,0.12); color: #10b981; }
.kpi-icon.sky     { background: rgba(14,165,233,0.12); color: #0ea5e9; }
.kpi-icon.amber   { background: rgba(245,158,11,0.12); color: #f59e0b; }
.kpi-icon.violet  { background: rgba(139,92,246,0.12); color: #8b5cf6; }
.kpi-icon.rose    { background: rgba(239,68,68,0.12); color: #ef4444; }
.kpi-body { flex: 1; min-width: 0; }
.kpi-label { font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px; }
.kpi-value { font-size: 26px; font-weight: 800; color: #1e293b; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kpi-sub { font-size: 12px; color: #64748b; margin-top: 3px; }
.kpi-badge {
    position: absolute; top: 14px; right: 14px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.kpi-badge.warn { background: rgba(239,68,68,0.12); color: #ef4444; }
.kpi-badge.ok   { background: rgba(16,185,129,0.12); color: #10b981; }

/* Charts & Tables */
.dash-row { display: grid; grid-template-columns: 1.6fr 1fr; gap: 20px; margin-bottom: 20px; }
@media(max-width:900px){ .dash-row { grid-template-columns: 1fr; } }
.dash-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f5;
}
.dash-card-title {
    font-size: 15px; font-weight: 700; color: #1e293b;
    margin-bottom: 18px;
    display: flex; align-items: center; gap: 10px;
}
.dash-card-title i { color: #4f46e5; }

/* Sales table */
.sales-table { width:100%; border-collapse: collapse; font-size: 13px; }
.sales-table th { color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: .5px; padding: 0 12px 10px; text-align: left; }
.sales-table td { padding: 10px 12px; border-top: 1px solid #f1f5f9; color: #374151; }
.sales-table tr:hover td { background: #f8fafc; }
.badge-paid    { background: rgba(16,185,129,0.1); color: #10b981; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-partial { background: rgba(245,158,11,0.1); color: #d97706; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-unpaid  { background: rgba(239,68,68,0.1); color: #ef4444; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }

/* Top items */
.top-item-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid #f1f5f9; }
.top-item-rank { width: 24px; height: 24px; border-radius: 8px; background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.top-item-name { flex: 1; font-size: 13px; color: #374151; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top-item-amt  { font-size: 13px; font-weight: 700; color: #4f46e5; }

/* Chart canvas */
#salesChart { max-height: 200px; }

/* Empty state */
.empty-state { text-align: center; padding: 32px 16px; color: #94a3b8; }
.empty-state i { font-size: 36px; display: block; margin-bottom: 10px; opacity: .5; }
.empty-state p { font-size: 13px; }
</style>

<!-- ── KPI Rows ─────────────────────────────────────────────────────────── -->
<div class="dash-kpi-grid">
    <!-- Today Sales Amount -->
    <div class="kpi-card">
        <div class="kpi-icon indigo"><i class="fas fa-receipt"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Today's Sales</div>
            <div class="kpi-value">$<?= number_format($todaySales, 2) ?></div>
            <div class="kpi-sub"><?= $todaySaleCount ?> transaction<?= $todaySaleCount !== 1 ? 's' : '' ?> today</div>
        </div>
    </div>

    <!-- Today's Purchases -->
    <div class="kpi-card">
        <div class="kpi-icon emerald"><i class="fas fa-shopping-cart"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Today's Purchases</div>
            <div class="kpi-value">$<?= number_format($todayPurchases, 2) ?></div>
            <div class="kpi-sub">Total inbound transactions</div>
        </div>
    </div>

    <!-- Cash Balance -->
    <div class="kpi-card">
        <div class="kpi-icon sky"><i class="fas fa-wallet"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Cash Balance</div>
            <div class="kpi-value">$<?= number_format($cashBalance, 2) ?></div>
            <div class="kpi-sub">In-hand cashier cash drawer</div>
        </div>
    </div>

    <!-- Bank Balance -->
    <div class="kpi-card">
        <div class="kpi-icon sky"><i class="fas fa-university"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Bank Balance</div>
            <div class="kpi-value">$<?= number_format($bankBalance, 2) ?></div>
            <div class="kpi-sub">Active business operating bank</div>
        </div>
    </div>
</div>

<div class="dash-kpi-grid">
    <!-- Outstanding Receivables -->
    <div class="kpi-card">
        <div class="kpi-icon amber"><i class="fas fa-sign-in-alt"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Receivables</div>
            <div class="kpi-value" style="color:#f59e0b">$<?= number_format($receivables, 2) ?></div>
            <div class="kpi-sub">Outstanding customer balances</div>
        </div>
    </div>

    <!-- Outstanding Payables -->
    <div class="kpi-card">
        <div class="kpi-icon rose"><i class="fas fa-sign-out-alt"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Payables</div>
            <div class="kpi-value" style="color:#ef4444">$<?= number_format($payables, 2) ?></div>
            <div class="kpi-sub">Outstanding supplier balances</div>
        </div>
    </div>

    <!-- Pending Orders -->
    <div class="kpi-card">
        <div class="kpi-icon violet"><i class="fas fa-clock"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Pending Orders</div>
            <div class="kpi-value"><?= $pendingOrders ?></div>
            <div class="kpi-sub">Sales/purchase pending orders</div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="kpi-card">
        <div class="kpi-icon violet"><i class="fas fa-boxes"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Low Stock items</div>
            <div class="kpi-value"><?= $lowStock ?></div>
            <div class="kpi-sub">Reorder level alerts</div>
        </div>
        <?php if ($lowStock > 0): ?>
        <span class="kpi-badge warn"><i class="fas fa-exclamation-triangle"></i> <?= $lowStock ?> Low</span>
        <?php else: ?>
        <span class="kpi-badge ok"><i class="fas fa-check"></i> Stock OK</span>
        <?php endif; ?>
    </div>
</div>

<div class="dash-kpi-grid">
    <!-- Expiring Batches -->
    <div class="kpi-card">
        <div class="kpi-icon rose"><i class="fas fa-hourglass-half"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Expiring Batches</div>
            <div class="kpi-value"><?= $expiringBatches ?></div>
            <div class="kpi-sub">Expiring within 30 days</div>
        </div>
    </div>

    <!-- Customers -->
    <div class="kpi-card">
        <div class="kpi-icon indigo"><i class="fas fa-users"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Customers</div>
            <div class="kpi-value"><?= number_format($totalCustomers) ?></div>
            <div class="kpi-sub">Total active accounts</div>
        </div>
    </div>

    <!-- Items -->
    <div class="kpi-card">
        <div class="kpi-icon indigo"><i class="fas fa-box"></i></div>
        <div class="kpi-body">
            <div class="kpi-label">Product SKUs</div>
            <div class="kpi-value"><?= number_format($totalItems) ?></div>
            <div class="kpi-sub">Active in-catalog items</div>
        </div>
    </div>
</div>

<!-- ── Charts + Recent Sales ──────────────────────────────────────────── -->
<div class="dash-row">

    <!-- Recent Sales Table -->
    <div class="dash-card">
        <div class="dash-card-title">
            <i class="fas fa-list-alt"></i> Recent Transactions
            <a href="<?= APP_URL ?>/sales" class="btn btn-sm btn-outline-primary ml-auto" style="font-size:12px;padding:3px 12px;">View All</a>
        </div>
        <?php if (empty($recentSales)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No sales recorded yet.<br>
            <a href="<?= APP_URL ?>/sales/create">Create your first sale →</a></p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="sales-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th style="text-align:right">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentSales as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['doc_no']) ?></strong></td>
                    <td><?= htmlspecialchars($s['customer_name']) ?></td>
                    <td style="color:#64748b;"><?= date('m/d/Y', strtotime($s['doc_date'])) ?></td>
                    <td style="text-align:right;font-weight:700;">$<?= number_format($s['net_amount'], 2) ?></td>
                    <td>
                        <?php
                        $due = (float)($s['balance_due'] ?? 0);
                        $amt = (float)($s['net_amount']  ?? 0);
                        if ($due <= 0) {
                            $cls = 'badge-paid';    $lbl = 'Paid';
                        } elseif ($due < $amt) {
                            $cls = 'badge-partial'; $lbl = 'Partial';
                        } else {
                            $cls = 'badge-unpaid';  $lbl = 'Unpaid';
                        }
                        ?>
                        <span class="<?= $cls ?>"><?= $lbl ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Items -->
    <div class="dash-card">
        <div class="dash-card-title">
            <i class="fas fa-fire"></i> Top Items This Month
        </div>
        <?php if (empty($topItems)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>No sales data for this month yet.</p>
        </div>
        <?php else: ?>
        <?php foreach ($topItems as $rank => $item): ?>
        <div class="top-item-row">
            <div class="top-item-rank"><?= $rank + 1 ?></div>
            <div class="top-item-name" title="<?= htmlspecialchars($item['item_name']) ?>">
                <?= htmlspecialchars($item['item_name']) ?>
                <div style="font-size:11px;color:#94a3b8"><?= htmlspecialchars($item['item_code']) ?> &middot; <?= number_format($item['total_qty'], 0) ?> qty</div>
            </div>
            <div class="top-item-amt">$<?= number_format($item['total_amt'], 0) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ── Sales Sparkline Chart ──────────────────────────────────────────── -->
<?php if (!empty($chartData)): ?>
<div class="dash-card" style="margin-bottom:20px;">
    <div class="dash-card-title"><i class="fas fa-chart-bar"></i> Last 7 Days — Daily Sales</div>
    <canvas id="salesChart"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    const labels = <?= json_encode(array_map(fn($r) => date('m/d', strtotime($r['sale_date'])), $chartData)) ?>;
    const data   = <?= json_encode(array_map(fn($r) => (float)$r['total'], $chartData)) ?>;

    const ctx = document.getElementById('salesChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(79,70,229,0.3)');
    gradient.addColorStop(1, 'rgba(79,70,229,0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Sales ($)',
                data,
                borderColor: '#4f46e5',
                backgroundColor: gradient,
                borderWidth: 2.5,
                pointBackgroundColor: '#4f46e5',
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '$' + v.toLocaleString('en-US'),
                        font: { size: 11 }
                    },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    ticks: { font: { size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });
})();
</script>

<?php endif; ?>

<!-- ── Quick Actions ──────────────────────────────────────────────────── -->
<div class="dash-card" style="margin-bottom:20px;">
    <div class="dash-card-title"><i class="fas fa-bolt"></i> Quick Actions</div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;">
        <a href="<?= APP_URL ?>/sales/create" class="btn btn-primary" style="border-radius:10px;padding:10px 20px;font-weight:600;">
            <i class="fas fa-plus"></i> New Sale (POS)
        </a>
        <a href="<?= APP_URL ?>/purchase/create" class="btn btn-outline-secondary" style="border-radius:10px;padding:10px 20px;font-weight:600;">
            <i class="fas fa-shopping-cart"></i> New Purchase
        </a>
        <a href="<?= APP_URL ?>/customer" class="btn btn-outline-secondary" style="border-radius:10px;padding:10px 20px;font-weight:600;">
            <i class="fas fa-users"></i> Customers
        </a>
        <a href="<?= APP_URL ?>/item" class="btn btn-outline-secondary" style="border-radius:10px;padding:10px 20px;font-weight:600;">
            <i class="fas fa-box"></i> Items
        </a>
        <a href="<?= APP_URL ?>/report/sales" class="btn btn-outline-secondary" style="border-radius:10px;padding:10px 20px;font-weight:600;">
            <i class="fas fa-chart-bar"></i> Sales Report
        </a>
    </div>
</div>
