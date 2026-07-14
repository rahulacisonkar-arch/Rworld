<?php
/**
 * Sales Orders — List
 */
$statusColors = [
    'open'      => ['bg' => '#3b82f6', 'label' => 'Open'],
    'partial'   => ['bg' => '#f59e0b', 'label' => 'Partial Paid'],
    'closed'    => ['bg' => '#22c55e', 'label' => 'Fully Paid'],
    'cancelled' => ['bg' => '#6b7280', 'label' => 'Cancelled'],
];
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-file-invoice mr-2 text-primary"></i>Sales Orders</h1>
        <div class="page-subtitle">Quotation → <strong>Sales Order</strong> → Invoice</div>
    </div>
    <a href="<?= APP_URL ?>/salesorder/create" class="btn btn-primary">
        <i class="fas fa-plus mr-1"></i> New Sales Order
    </a>
</div>

<!-- Filters -->
<div class="qb-card mb-3">
    <div class="qb-card-body py-3">
        <form method="GET" action="<?= APP_URL ?>/salesorder" class="d-flex" style="gap:10px">
            <input type="text" name="q" class="form-control" placeholder="Search order no or customer…" value="<?= htmlspecialchars($search) ?>" style="max-width:260px">
            <select name="status" class="form-control" style="max-width:160px">
                <option value="">All Statuses</option>
                <?php foreach ($statusColors as $key => $s): ?>
                    <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search mr-1"></i>Filter</button>
            <a href="<?= APP_URL ?>/salesorder" class="btn btn-outline-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th style="text-align:right">Order Value</th>
                        <th style="text-align:right">Paid</th>
                        <th style="text-align:right">Balance</th>
                        <th style="text-align:center">Status</th>
                        <th style="text-align:center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records['data'])): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">
                            <i class="fas fa-file-invoice fa-2x mb-2 d-block opacity-40"></i>
                            No sales orders found.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($records['data'] as $row):
                            $sc = $statusColors[$row['status']] ?? ['bg' => '#6b7280', 'label' => ucfirst($row['status'])];
                            $balance = $row['net_amount'] - $row['paid_amount'];
                        ?>
                        <tr>
                            <td><a href="<?= APP_URL ?>/salesorder/view/<?= $row['id'] ?>" class="font-weight-600 text-primary font-monospace"><?= $row['doc_no'] ?></a></td>
                            <td><?= date('d M Y', strtotime($row['doc_date'])) ?></td>
                            <td><?= htmlspecialchars($row['customer_display']) ?></td>
                            <td style="text-align:right;font-weight:600">$<?= number_format($row['net_amount'], 2) ?></td>
                            <td style="text-align:right;color:#22c55e;font-weight:600">$<?= number_format($row['paid_amount'], 2) ?></td>
                            <td style="text-align:right;color:<?= $balance > 0 ? '#ef4444' : '#22c55e' ?>;font-weight:700">
                                <?= $balance > 0 ? '$' . number_format($balance, 2) : '<i class="fas fa-check-circle text-success"></i>' ?>
                            </td>
                            <td style="text-align:center">
                                <span class="status-badge" style="background:<?= $sc['bg'] ?>15;color:<?= $sc['bg'] ?>;border:1px solid <?= $sc['bg'] ?>40;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700">
                                    <?= $sc['label'] ?>
                                </span>
                            </td>
                            <td style="text-align:center">
                                <div class="d-flex justify-content-center" style="gap:4px">
                                    <a href="<?= APP_URL ?>/salesorder/view/<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                    <?php if (!in_array($row['status'], ['closed','cancelled'])): ?>
                                        <a href="<?= APP_URL ?>/salesorder/view/<?= $row['id'] ?>#pay" class="btn btn-sm btn-outline-success" title="Record Payment"><i class="fas fa-rupee-sign"></i></a>
                                        <form method="POST" action="<?= APP_URL ?>/salesorder/convert/<?= $row['id'] ?>" style="display:inline" onsubmit="return confirm('Convert to Invoice?')">
                                            <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
                                            <button class="btn btn-sm btn-outline-warning" title="Convert to Invoice"><i class="fas fa-arrow-right"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($row['converted_sale_id']): ?>
                                        <span class="btn btn-sm btn-success disabled" title="Invoice Created"><i class="fas fa-check"></i></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (!empty($records['total_pages']) && $records['total_pages'] > 1): ?>
        <div class="d-flex justify-content-center p-3">
            <?php for ($p = 1; $p <= $records['total_pages']; $p++): ?>
                <a href="?page=<?= $p ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>"
                   class="btn btn-sm mx-1 <?= $p == $records['current_page'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.font-monospace { font-family: 'Courier New', monospace; }
.font-weight-600 { font-weight: 600; }
.opacity-40 { opacity: .4; }
</style>
