<!-- Stock Report View — matches paginated ReportController::stock() variables ($stock) -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-cubes mr-2 text-primary"></i>Stock Report</h1>
        <div class="page-subtitle">Current inventory levels and valuation</div>
    </div>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<!-- Summary Cards -->
<?php
// Compute quick estimates from paginated overview
$totalItems = $stock['total'] ?? 0;
$totalQty   = array_sum(array_column($stock['data'] ?? [], 'stock_qty'));
$outOfStock = count(array_filter($stock['data'] ?? [], fn($r) => $r['stock_qty'] <= 0));
?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="qb-card stat-card stat-blue">
            <div class="stat-value"><?= number_format($totalItems) ?></div>
            <div class="stat-label">Total SKUs in Catalog</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card stat-card stat-green">
            <div class="stat-value"><?= number_format($totalQty, 0) ?></div>
            <div class="stat-label">Units in Stock (This Page)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qb-card stat-card stat-red">
            <div class="stat-value"><?= $outOfStock ?></div>
            <div class="stat-label">Out of Stock (This Page)</div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>SKU / Stock No</th>
                        <th>Item Name</th>
                        <th class="text-right">Current Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($stock['data'])): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">
                        <i class="fas fa-boxes fa-2x mb-2 d-block"></i>
                        No stock data found.
                    </td></tr>
                <?php else: foreach ($stock['data'] as $row):
                    $qty = (float)$row['stock_qty'];
                    if ($qty <= 0) $badge = '<span class="badge badge-danger">Out of Stock</span>';
                    elseif ($qty <= 5) $badge = '<span class="badge badge-warning">Low Stock</span>';
                    else $badge = '<span class="badge badge-success">OK</span>';
                ?>
                    <tr>
                        <td><code><?= htmlspecialchars($row['stock_no'] ?? '—') ?></code></td>
                        <td><strong><?= htmlspecialchars($row['description']) ?></strong></td>
                        <td class="text-right <?= $qty <= 0 ? 'text-danger font-weight-bold' : ($qty <= 5 ? 'text-warning font-weight-bold' : '') ?>"><?= number_format($qty, 2) ?></td>
                        <td><?= $badge ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (!empty($stock['total_pages']) && $stock['total_pages'] > 1): ?>
        <div class="d-flex justify-content-center p-3">
            <?php for ($p = 1; $p <= $stock['total_pages']; $p++): ?>
                <?php if ($p <= 10 || $p == $stock['total_pages'] || abs($p - $stock['current_page']) <= 2): ?>
                    <?php if ($p > 10 && abs($p - $stock['current_page']) > 2 && $prev_dot !== $p - 1): ?>
                        <span class="px-2 align-self-end">...</span>
                        <?php $prev_dot = $p; ?>
                    <?php endif; ?>
                    <a href="?page=<?= $p ?>"
                       class="btn btn-sm mx-1 <?= $p == $stock['current_page'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= $p ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
