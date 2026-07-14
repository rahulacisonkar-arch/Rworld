<?php
$pageTitle = "Store Dashboard";
require_once dirname(__DIR__) . '/src/header.php';
require_once dirname(__DIR__) . '/src/EasyshipService.php';

require_role('Store User');

EasyshipService::updateTrackingStatuses();

try {
    // Get store details to check for destined shipments
    $stmtStore = $pdo->prepare("SELECT zip, address FROM stores WHERE id = ?");
    $stmtStore->execute([$storeId]);
    $currentStore = $stmtStore->fetch();
    $storeZip = $currentStore ? trim($currentStore['zip']) : '-----';
    $storeAddrFirst = '-----';
    if ($currentStore) {
        $addrParts = preg_split('/[^A-Za-z0-9]/', trim($currentStore['address']));
        $storeAddrFirst = !empty($addrParts[0]) ? trim($addrParts[0]) : '-----';
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM label_requests WHERE store_id = :store_id OR (ship_to_zip = :store_zip AND ship_to_address1 LIKE :store_addr_first)");
    $stmt->execute([':store_id' => $storeId, ':store_zip' => $storeZip, ':store_addr_first' => '%' . $storeAddrFirst . '%']);
    $totalRequests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM label_requests WHERE (store_id = :store_id OR (ship_to_zip = :store_zip AND ship_to_address1 LIKE :store_addr_first)) AND status = 'Pending'");
    $stmt->execute([':store_id' => $storeId, ':store_zip' => $storeZip, ':store_addr_first' => '%' . $storeAddrFirst . '%']);
    $pendingRequests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM label_requests WHERE (store_id = :store_id OR (ship_to_zip = :store_zip AND ship_to_address1 LIKE :store_addr_first)) AND status IN ('Label Created', 'Label Sent')");
    $stmt->execute([':store_id' => $storeId, ':store_zip' => $storeZip, ':store_addr_first' => '%' . $storeAddrFirst . '%']);
    $labelsReceived = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM label_requests WHERE (store_id = :store_id OR (ship_to_zip = :store_zip AND ship_to_address1 LIKE :store_addr_first)) AND status = 'Completed'");
    $stmt->execute([':store_id' => $storeId, ':store_zip' => $storeZip, ':store_addr_first' => '%' . $storeAddrFirst . '%']);
    $completedShipments = $stmt->fetchColumn();

    $search       = trim($_GET['search'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');

    $sql = "SELECT lr.*, 
                   COALESCE((SELECT carrier FROM request_labels WHERE request_id = lr.id ORDER BY id DESC LIMIT 1), lr.shipping_method) AS shipping_method,
                   GROUP_CONCAT(rl.tracking_number SEPARATOR ', ') AS tracking_number,
                   GROUP_CONCAT(CONCAT(rl.tracking_number, '||', COALESCE(rl.tracking_status, '')) SEPARATOR '::') AS tracking_info
            FROM label_requests lr
            LEFT JOIN request_labels rl ON lr.id = rl.request_id
            WHERE (lr.store_id = :store_id OR (lr.ship_to_zip = :store_zip AND lr.ship_to_address1 LIKE :store_addr_first))";
    $params = [
        ':store_id' => $storeId,
        ':store_zip' => $storeZip,
        ':store_addr_first' => '%' . $storeAddrFirst . '%'
    ];

    if ($search !== '') {
        $sql .= " AND (lr.request_number LIKE :search1 OR lr.sales_order_number LIKE :search2 OR lr.request_reference LIKE :search3 OR rl.tracking_number LIKE :search4)";
        $params[':search1'] = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
        $params[':search4'] = '%' . $search . '%';
    }

    if ($statusFilter !== '') {
        $sql .= " AND lr.status = :status";
        $params[':status'] = $statusFilter;
    }

    $sql .= " GROUP BY lr.id ORDER BY lr.created_at DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error retrieving dashboard data: " . $e->getMessage());
}
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="mb-1" style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">
            Welcome back, <?php echo e($storeName); ?>
        </h1>
        <p class="mb-0" style="color: var(--text-secondary); font-size: 0.875rem;">
            <i class="bi bi-calendar3 me-1"></i>
            <?php echo date('l, F j, Y'); ?> &mdash; Store Shipping Dashboard
        </p>
    </div>
    <a href="request_create.php" class="btn-command-primary">
        <i class="bi bi-plus-circle-fill"></i> Create Label Request
    </a>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-accent"></div>
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Total Requests</div>
                    <div class="kpi-value"><?php echo number_format($totalRequests); ?></div>
                    <div class="kpi-sub">All time submissions</div>
                </div>
                <div class="kpi-icon">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-orange">
            <div class="kpi-accent"></div>
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Pending</div>
                    <div class="kpi-value"><?php echo number_format($pendingRequests); ?></div>
                    <div class="kpi-sub">Awaiting processing</div>
                </div>
                <div class="kpi-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple">
            <div class="kpi-accent"></div>
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Labels Received</div>
                    <div class="kpi-value"><?php echo number_format($labelsReceived); ?></div>
                    <div class="kpi-sub">Ready for pickup</div>
                </div>
                <div class="kpi-icon">
                    <i class="bi bi-qr-code-scan"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-green">
            <div class="kpi-accent"></div>
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Completed</div>
                    <div class="kpi-value"><?php echo number_format($completedShipments); ?></div>
                    <div class="kpi-sub">Successfully shipped</div>
                </div>
                <div class="kpi-icon">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Panel -->
<div class="card mb-4" style="border-radius: 10px;">
    <div class="p-3">
        <form method="GET" action="dashboard.php" class="row g-2 align-items-end">
            <div class="col-md-5 col-lg-4">
                <label class="form-label-command">Search</label>
                <div class="input-group-cmd">
                    <span class="input-group-text-cmd"><i class="bi bi-search" style="font-size: 0.85rem;"></i></span>
                    <input type="text"
                           name="search"
                           class="form-control-command"
                           placeholder="REQ#, SO#, tracking number..."
                           value="<?php echo e($search); ?>"
                           style="border-radius: 0 8px 8px 0; border-left: none;">
                </div>
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label-command">Status</label>
                <select name="status" class="form-control-command">
                    <option value="">All Statuses</option>
                    <option value="Pending"       <?php echo $statusFilter === 'Pending'       ? 'selected' : ''; ?>>Pending</option>
                    <option value="Processing"    <?php echo $statusFilter === 'Processing'    ? 'selected' : ''; ?>>Processing</option>
                    <option value="Label Created" <?php echo $statusFilter === 'Label Created' ? 'selected' : ''; ?>>Label Created</option>
                    <option value="Label Sent"    <?php echo $statusFilter === 'Label Sent'    ? 'selected' : ''; ?>>Label Sent</option>
                    <option value="Completed"     <?php echo $statusFilter === 'Completed'     ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn-command-primary">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
            </div>
            <?php if ($search !== '' || $statusFilter !== ''): ?>
                <div class="col-auto">
                    <a href="dashboard.php" class="btn-command-secondary" style="border-color: var(--danger); color: var(--danger) !important;">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Requests Table -->
<div class="card" style="border-radius: 10px; overflow: hidden;">
    <div class="card-header-premium">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-list-task" style="color: var(--primary); font-size: 1rem;"></i>
            <h5 class="mb-0 fw-bold" style="font-size: 1rem;">Recent Requests</h5>
            <span class="badge ms-1" style="background: var(--primary-light); color: var(--primary); font-size: 0.72rem; border-radius: 12px; padding: 3px 9px;"><?php echo count($requests); ?></span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-command table-store">
            <thead>
                <tr>
                    <th>Shipment #</th>
                    <th>SO#</th>
                    <th>Req. Ref</th>
                    <th>Shipping Method</th>
                    <th>Status</th>
                    <th>Tracking #</th>
                    <th>Date Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5" style="color: var(--text-muted);">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No shipping requests found<?php echo ($search || $statusFilter) ? ' matching your filters.' : '.'; ?>
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
                            <td><span style="font-weight: 700; color: var(--primary);"><?php echo e($req['request_number']); ?></span></td>
                            <td><?php echo e($req['sales_order_number']); ?></td>
                            <td><?php echo e($req['request_reference'] ?: '—'); ?></td>
                            <td><?php echo e($req['shipping_method']); ?></td>
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
                                    <span style="color: var(--text-muted); font-size: 0.78rem;">Not available</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.8rem;"><?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?></td>
                            <td>
                                <a href="request_view.php?id=<?php echo $req['id']; ?>" class="btn-command-secondary" style="padding: 5px 12px; font-size: 0.78rem;">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/src/footer.php'; ?>
