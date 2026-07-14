<?php
$pageTitle = "HQ Operations Hub";
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/EasyshipService.php';

session_start_safe();
require_login();
require_role(['Super Admin', 'Logistics Admin']);

EasyshipService::updateTrackingStatuses();

// Retrieve filter params
$search        = trim($_GET['search'] ?? '');
$store_filter  = trim($_GET['store'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$method_filter = trim($_GET['method'] ?? '');
$start_date    = trim($_GET['start_date'] ?? '');
$end_date      = trim($_GET['end_date'] ?? '');
$freight_filter = trim($_GET['freight_filter'] ?? '');
$min_freight   = trim($_GET['min_freight'] ?? '');
$max_freight   = trim($_GET['max_freight'] ?? '');

// Stores for filter dropdown and mapping
$storesMap = [];
try {
    $stores_stmt = $pdo->query("SELECT * FROM stores ORDER BY store_name ASC");
    $all_stores  = $stores_stmt->fetchAll();
    foreach ($all_stores as $st) {
        $storesMap[$st['id']] = $st;
    }
} catch (PDOException $e) {
    die("Database error loading stores: " . $e->getMessage());
}

// Build dynamic query
$sql = "SELECT r.*, s.store_name, s.store_code,
               COALESCE((SELECT carrier FROM request_labels WHERE request_id = r.id ORDER BY id DESC LIMIT 1), r.shipping_method) AS shipping_method,
               GROUP_CONCAT(rl.tracking_number SEPARATOR ', ') AS tracking_number,
               GROUP_CONCAT(CONCAT(rl.tracking_number, '||', COALESCE(rl.tracking_status, '')) SEPARATOR '::') AS tracking_info,
               GROUP_CONCAT(rl.estimated_delivery_date SEPARATOR ', ') AS delivery_dates,
               SUM(rl.actual_shipping_cost) AS easyship_cost
        FROM label_requests r
        JOIN stores s ON r.store_id = s.id
        LEFT JOIN request_labels rl ON r.id = rl.request_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (r.request_number LIKE :search1 OR r.sales_order_number LIKE :search2 OR r.request_reference LIKE :search3 OR rl.tracking_number LIKE :search4)";
    $params[':search1'] = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
    $params[':search3'] = '%' . $search . '%';
    $params[':search4'] = '%' . $search . '%';
}
if ($store_filter !== '') {
    $sql .= " AND r.store_id = :store_filter";
    $params[':store_filter'] = $store_filter;
}
if ($status_filter !== '') {
    $sql .= " AND r.status = :status_filter";
    $params[':status_filter'] = $status_filter;
}
if ($method_filter !== '') {
    $resolvedMethodSql = "COALESCE((SELECT carrier FROM request_labels WHERE request_id = r.id ORDER BY id DESC LIMIT 1), r.shipping_method)";
    if ($method_filter === 'Other') {
        $sql .= " AND ($resolvedMethodSql NOT LIKE '%UPS%' AND $resolvedMethodSql NOT LIKE '%FedEx%' AND $resolvedMethodSql NOT LIKE '%USPS%' AND $resolvedMethodSql NOT LIKE '%LTL%')";
    } else {
        // Normalize terms (e.g. 2nd -> 2, or space to %) to be flexible with registry symbols like ® or variations
        $term = $method_filter;
        if ($term === 'FedEx 2 Day') {
            $term = 'FedEx%2%Day';
        } elseif ($term === 'UPS 2nd Day Air') {
            $term = 'UPS%2%Day';
        } else {
            $term = str_replace(' ', '%', $term);
        }
        $sql .= " AND $resolvedMethodSql LIKE :method_filter";
        $params[':method_filter'] = '%' . $term . '%';
    }
}
if ($start_date !== '') {
    $sql .= " AND DATE(r.created_at) >= :start_date";
    $params[':start_date'] = $start_date;
}
if ($end_date !== '') {
    $sql .= " AND DATE(r.created_at) <= :end_date";
    $params[':end_date'] = $end_date;
}
if ($freight_filter === 'above_15') {
    $sql .= " AND r.customer_freight_charge > 15.00";
} elseif ($freight_filter === 'below_25') {
    $sql .= " AND r.customer_freight_charge < 25.00";
} elseif ($freight_filter === 'range') {
    if ($min_freight !== '') { $sql .= " AND r.customer_freight_charge >= :min_freight"; $params[':min_freight'] = floatval($min_freight); }
    if ($max_freight !== '') { $sql .= " AND r.customer_freight_charge <= :max_freight"; $params[':max_freight'] = floatval($max_freight); }
}

$sql .= " GROUP BY r.id ORDER BY r.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    // Update store details in requests list to correct values based on address matching
    foreach ($requests as &$req) {
        $correctStore = get_correct_store_for_request($req, $storesMap);
        if ($correctStore) {
            $req['store_name'] = $correctStore['store_name'];
            $req['store_code'] = $correctStore['store_code'];
        }
    }
    unset($req);
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// HANDLE EXPORTS
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    if (ob_get_level()) ob_end_clean();

    if ($exportType === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=artee_shipping_requests_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['VENDORS /STORES', 'DATE', 'TRACKING NO.', 'REF NO.1', 'F NO.', 'RECEIVER', 'UPS CHARGE', 'Delivery date', 'SHIPPING FROM', 'weight', 'DIMENSION', 'freight charge from cmr', 'Remark', 'PACKAGE LOST']);
        foreach ($requests as $row) {
            $dimensionStr   = floatval($row['length']) . 'x' . floatval($row['width']) . 'x' . floatval($row['height']);
            $receiverStr    = $row['ship_to_name'] . ($row['ship_to_company'] ? ' - ' . $row['ship_to_company'] : '');
            $shipFromStr    = $row['ship_from_name'] . ($row['ship_from_company'] ? ' - ' . $row['ship_from_company'] : '');
            $remarkStr      = $row['internal_notes'] ?: ($row['special_instructions'] ?: '');
            $packageLostStr = ($row['status'] === 'Cancelled') ? 'Yes' : 'No';
            $easyshipCostStr = ($row['easyship_cost'] !== null) ? number_format($row['easyship_cost'], 2) : '0.00';
            fputcsv($output, [
                $row['store_name'],
                date('Y-m-d', strtotime($row['created_at'])),
                $row['tracking_number'] ?: 'Not Provided',
                $row['sales_order_number'],
                $row['request_reference'] ?: '',
                $receiverStr,
                $easyshipCostStr,
                $row['delivery_dates'] ?: 'N/A',
                $shipFromStr,
                $row['weight_lbs'],
                $dimensionStr,
                number_format($row['customer_freight_charge'], 2),
                $remarkStr,
                $packageLostStr
            ]);
        }
        fclose($output);
        exit;
    }

    if ($exportType === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=artee_shipping_requests_' . date('Ymd_His') . '.xls');
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta http-equiv="Content-type" content="text/html;charset=utf-8" /></head><body><table border="1">';
        $headers = ['VENDORS /STORES', 'DATE', 'TRACKING NO.', 'REF NO.1', 'F NO.', 'RECEIVER', 'UPS CHARGE', 'Delivery date', 'SHIPPING FROM', 'weight', 'DIMENSION', 'freight charge from cmr', 'Remark', 'PACKAGE LOST'];
        echo '<tr>';
        foreach ($headers as $h) echo '<th style="background-color:#FFFF00; color:#000000; font-weight:bold;">' . $h . '</th>';
        echo '</tr>';
        foreach ($requests as $row) {
            $dimensionStr   = floatval($row['length']) . 'x' . floatval($row['width']) . 'x' . floatval($row['height']);
            $receiverStr    = $row['ship_to_name'] . ($row['ship_to_company'] ? ' - ' . $row['ship_to_company'] : '');
            $shipFromStr    = $row['ship_from_name'] . ($row['ship_from_company'] ? ' - ' . $row['ship_from_company'] : '');
            $remarkStr      = $row['internal_notes'] ?: ($row['special_instructions'] ?: '');
            $packageLostStr = ($row['status'] === 'Cancelled') ? 'Yes' : 'No';
            $easyshipCostStr = ($row['easyship_cost'] !== null) ? number_format($row['easyship_cost'], 2) : '0.00';
            echo '<tr>' .
                 '<td>' . e($row['store_name']) . '</td>' .
                 '<td>' . e(date('Y-m-d', strtotime($row['created_at']))) . '</td>' .
                 '<td>' . e($row['tracking_number'] ?: 'Not Provided') . '</td>' .
                 '<td>' . e($row['sales_order_number']) . '</td>' .
                 '<td>' . e($row['request_reference'] ?: '') . '</td>' .
                 '<td>' . e($receiverStr) . '</td>' .
                 '<td>' . $easyshipCostStr . '</td>' .
                 '<td>' . e($row['delivery_dates'] ?: 'N/A') . '</td>' .
                 '<td>' . e($shipFromStr) . '</td>' .
                 '<td>' . e($row['weight_lbs']) . '</td>' .
                 '<td>' . e($dimensionStr) . '</td>' .
                 '<td>' . number_format($row['customer_freight_charge'], 2) . '</td>' .
                 '<td>' . e($remarkStr) . '</td>' .
                 '<td>' . e($packageLostStr) . '</td>' .
                 '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }
}

// Stats
try {
    $stat_total      = $pdo->query("SELECT COUNT(*) FROM label_requests")->fetchColumn();
    $stat_pending    = $pdo->query("SELECT COUNT(*) FROM label_requests WHERE status = 'Pending'")->fetchColumn();
    $stat_processing = $pdo->query("SELECT COUNT(*) FROM label_requests WHERE status = 'Processing'")->fetchColumn();
    $stat_sent       = $pdo->query("SELECT COUNT(*) FROM label_requests WHERE status = 'Label Sent'")->fetchColumn();
    $stat_completed  = $pdo->query("SELECT COUNT(*) FROM label_requests WHERE status = 'Completed'")->fetchColumn();
    $stat_freight    = $pdo->query("SELECT SUM(customer_freight_charge) FROM label_requests WHERE status NOT IN ('Cancelled')")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    die("Database stats query error: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/src/header.php';
?>

<!-- Print Styles -->
<style>
@media print {
    body { background: white !important; color: black !important; font-size: 10pt; }
    .navbar-command, .sidebar-command, .btn, .btn-group, footer, #sidebar-toggle-btn, .notification-drawer, .card form { display: none !important; }
    #main-content { margin-left: 0 !important; padding-top: 0 !important; }
    .card, .glass-card { box-shadow: none !important; border: 1px solid #ccc !important; }
    .table-command thead th { background: #eee !important; color: black !important; border: 1px solid #ccc; }
    .table-command tbody td { border: 1px solid #ccc; color: black !important; }
    .print-only-header { display: block !important; margin-bottom: 20px; }
}
.print-only-header { display: none; }
</style>

<!-- Print Header -->
<div class="print-only-header text-center">
    <h2>ARTÉE FABRICS &amp; HOME</h2>
    <h4>Shipping Requests Report &mdash; Generated on <?php echo date('M d, Y h:i A'); ?></h4>
    <hr>
</div>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="mb-1" style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">
            <i class="bi bi-speedometer2 me-2" style="color: var(--primary);"></i>HQ Operations Hub
        </h1>
        <p class="mb-0" style="color: var(--text-secondary); font-size: 0.875rem;">
            National shipment overview &mdash; <?php echo date('l, F j, Y'); ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn-command-secondary">
            <i class="bi bi-filetype-csv"></i> CSV
        </a>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn-command-secondary">
            <i class="bi bi-file-earmark-excel"></i> Excel
        </a>
        <button onclick="window.print();" class="btn-command-secondary">
            <i class="bi bi-printer"></i> Print
        </button>
        <a href="download_credentials.php" class="btn-command-primary">
            <i class="bi bi-file-earmark-pdf"></i> Credentials PDF
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-blue">
            <div class="kpi-accent"></div>
            <div class="kpi-label">Total</div>
            <div class="kpi-value"><?php echo number_format($stat_total); ?></div>
            <div class="kpi-sub">All requests</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-orange">
            <div class="kpi-accent"></div>
            <div class="kpi-label">Pending</div>
            <div class="kpi-value"><?php echo number_format($stat_pending); ?></div>
            <div class="kpi-sub">Awaiting action</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-blue">
            <div class="kpi-accent"></div>
            <div class="kpi-label">Processing</div>
            <div class="kpi-value"><?php echo number_format($stat_processing); ?></div>
            <div class="kpi-sub">In progress</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-purple">
            <div class="kpi-accent"></div>
            <div class="kpi-label">Labels Sent</div>
            <div class="kpi-value"><?php echo number_format($stat_sent); ?></div>
            <div class="kpi-sub">Dispatched</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-green">
            <div class="kpi-accent"></div>
            <div class="kpi-label">Completed</div>
            <div class="kpi-value"><?php echo number_format($stat_completed); ?></div>
            <div class="kpi-sub">Delivered</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-gold">
            <div class="kpi-accent"></div>
            <div class="kpi-label">Freight Rev.</div>
            <div class="kpi-value" style="font-size: 1.4rem;">$<?php echo number_format($stat_freight, 0); ?></div>
            <div class="kpi-sub">Total charges</div>
        </div>
    </div>
</div>

<!-- Advanced Filters -->
<div class="card mb-4" style="border-radius: 10px; overflow: visible;">
    <div class="card-header-premium">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-funnel-fill" style="color: var(--primary);"></i>
            <h5 class="mb-0 fw-bold" style="font-size: 0.95rem;">Advanced Filters</h5>
        </div>
        <?php $hasFilters = ($search || $store_filter || $status_filter || $method_filter || $start_date || $end_date || $freight_filter); ?>
        <?php if ($hasFilters): ?>
            <a href="admin_dashboard.php" class="btn-command-secondary" style="padding: 5px 14px; font-size: 0.8rem; border-color: var(--danger); color: var(--danger) !important;">
                <i class="bi bi-x-circle"></i> Clear All
            </a>
        <?php endif; ?>
    </div>
    <div class="p-4">
        <form method="GET" action="admin_dashboard.php" class="row g-3">
            <div class="col-md-4 col-lg-3">
                <label class="form-label-command">Search</label>
                <input type="text" name="search" class="form-control-command" placeholder="REQ#, SO#, Tracking..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label-command">Store</label>
                <select name="store" class="form-control-command">
                    <option value="">All Stores</option>
                    <?php foreach ($all_stores as $st): ?>
                        <option value="<?php echo $st['id']; ?>" <?php echo $store_filter == $st['id'] ? 'selected' : ''; ?>>
                            <?php echo e($st['store_name']); ?> (<?php echo e($st['store_code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label-command">Status</label>
                <select name="status" class="form-control-command">
                    <option value="">All Statuses</option>
                    <option value="Pending"       <?php echo $status_filter === 'Pending'       ? 'selected' : ''; ?>>Pending</option>
                    <option value="Processing"    <?php echo $status_filter === 'Processing'    ? 'selected' : ''; ?>>Processing</option>
                    <option value="Label Created" <?php echo $status_filter === 'Label Created' ? 'selected' : ''; ?>>Label Created</option>
                    <option value="Label Sent"    <?php echo $status_filter === 'Label Sent'    ? 'selected' : ''; ?>>Label Sent</option>
                    <option value="Completed"     <?php echo $status_filter === 'Completed'     ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label-command">Shipping Method</label>
                <select name="method" class="form-control-command">
                    <option value="">All Methods</option>
                    <option value="UPS Ground"      <?php echo $method_filter === 'UPS Ground'      ? 'selected' : ''; ?>>UPS Ground</option>
                    <option value="FedEx Ground"    <?php echo $method_filter === 'FedEx Ground'    ? 'selected' : ''; ?>>FedEx Ground</option>
                    <option value="UPS Next Day Air"<?php echo $method_filter === 'UPS Next Day Air'? 'selected' : ''; ?>>UPS Next Day Air</option>
                    <option value="UPS 2nd Day Air" <?php echo $method_filter === 'UPS 2nd Day Air' ? 'selected' : ''; ?>>UPS 2nd Day Air</option>
                    <option value="FedEx Overnight" <?php echo $method_filter === 'FedEx Overnight' ? 'selected' : ''; ?>>FedEx Overnight</option>
                    <option value="FedEx 2 Day"     <?php echo $method_filter === 'FedEx 2 Day'     ? 'selected' : ''; ?>>FedEx 2 Day</option>
                    <option value="USPS Priority"   <?php echo $method_filter === 'USPS Priority'   ? 'selected' : ''; ?>>USPS Priority</option>
                    <option value="LTL Freight"     <?php echo $method_filter === 'LTL Freight'     ? 'selected' : ''; ?>>LTL Freight</option>
                    <option value="Other"           <?php echo $method_filter === 'Other'           ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label-command">Date From</label>
                <input type="date" name="start_date" class="form-control-command" value="<?php echo e($start_date); ?>">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label-command">Date To</label>
                <input type="date" name="end_date" class="form-control-command" value="<?php echo e($end_date); ?>">
            </div>
            <div class="col-md-4 col-lg-3">
                <label class="form-label-command">Freight Charge Filter</label>
                <select name="freight_filter" id="freight_filter_select" class="form-control-command" onchange="toggleFreightRangeInputs()">
                    <option value="">All Charges</option>
                    <option value="above_15" <?php echo $freight_filter === 'above_15' ? 'selected' : ''; ?>>Above $15.00</option>
                    <option value="below_25" <?php echo $freight_filter === 'below_25' ? 'selected' : ''; ?>>Below $25.00</option>
                    <option value="range"    <?php echo $freight_filter === 'range'    ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-4" id="freight_range_inputs" style="<?php echo $freight_filter !== 'range' ? 'display:none;' : ''; ?>">
                <label class="form-label-command">Freight Range ($)</label>
                <div class="d-flex gap-2">
                    <input type="number" step="0.01" name="min_freight" class="form-control-command" placeholder="Min $" value="<?php echo e($min_freight); ?>">
                    <input type="number" step="0.01" name="max_freight" class="form-control-command" placeholder="Max $" value="<?php echo e($max_freight); ?>">
                </div>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2 mt-2 pt-3 border-top">
                <a href="admin_dashboard.php" class="btn-command-secondary">Reset</a>
                <button type="submit" class="btn-command-primary">
                    <i class="bi bi-funnel-fill"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Shipment Requests Table -->
<div class="card" style="border-radius: 10px; overflow: hidden;">
    <div class="card-header-premium">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-table" style="color: var(--primary); font-size: 1rem;"></i>
            <h5 class="mb-0 fw-bold" style="font-size: 1rem;">Shipment Requests Queue</h5>
            <span class="badge ms-1" style="background: var(--primary-light); color: var(--primary); font-size: 0.72rem; border-radius: 12px; padding: 3px 9px;"><?php echo count($requests); ?> records</span>
        </div>
        <div class="text-muted" style="font-size: 0.78rem;">
            <?php if ($hasFilters): ?>
                <i class="bi bi-funnel-fill text-warning me-1"></i>Filtered results
            <?php else: ?>
                Showing all requests
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-command table-admin">
            <thead>
                <tr>
                    <th>Shipment #</th>
                    <th>SO#</th>
                    <th>Store</th>
                    <th>Shipping Method</th>
                    <th>Freight Charge</th>
                    <th>Status</th>
                    <th>Tracking #</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5" style="color: var(--text-muted);">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No requests match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <?php
                        $statusClass = 'badge-pending';
                        if ($req['status'] === 'Processing')    $statusClass = 'badge-processing';
                        if ($req['status'] === 'Label Created') $statusClass = 'badge-label-created';
                        if ($req['status'] === 'Label Sent')    $statusClass = 'badge-label-sent';
                        if ($req['status'] === 'Completed')     $statusClass = 'badge-completed';
                        if ($req['status'] === 'Cancelled')     $statusClass = 'badge-cancelled';
                        ?>
                        <tr>
                            <td>
                                <span style="font-weight: 700; color: var(--primary);"><?php echo e($req['request_number']); ?></span>
                            </td>
                            <td><?php echo e($req['sales_order_number']); ?></td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.82rem;"><?php echo e($req['store_name']); ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;"><?php echo e($req['store_code']); ?></div>
                            </td>
                            <td style="font-size: 0.82rem;"><?php echo e($req['shipping_method']); ?></td>
                            <td><strong style="color: var(--success);">$<?php echo number_format($req['customer_freight_charge'], 2); ?></strong></td>
                            <td><span class="badge-command <?php echo $statusClass; ?>"><?php echo e($req['status']); ?></span></td>
                            <td>
                                <?php if (!empty($req['tracking_info'])): ?>
                                    <div class="d-flex flex-column gap-2 align-items-center">
                                        <?php 
                                        $items = explode('::', $req['tracking_info']);
                                        foreach ($items as $item):
                                            $parts = explode('||', $item);
                                            $tok = trim($parts[0] ?? '');
                                            $trackingStatus = trim($parts[1] ?? '');
                                            if ($tok !== ''):
                                        ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="tracking-number-badge"><?php echo e($tok); ?></span>
                                                <?php if ($trackingStatus !== ''): ?>
                                                    <span class="badge bg-secondary text-white mt-1" style="font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;"><?php echo e($trackingStatus); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.78rem; white-space: nowrap;"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                            <td>
                                <a href="request_view.php?id=<?php echo $req['id']; ?>" class="btn-command-primary" style="padding: 5px 12px; font-size: 0.78rem; box-shadow: none;">
                                    <i class="bi bi-pencil-square"></i> Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleFreightRangeInputs() {
    const val = document.getElementById('freight_filter_select').value;
    document.getElementById('freight_range_inputs').style.display = val === 'range' ? 'block' : 'none';
}
</script>

<?php require_once dirname(__DIR__) . '/src/footer.php'; ?>
