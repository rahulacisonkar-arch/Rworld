<?php
$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/src/header.php';

// Fetch Statistics
try {
    // Total connections
    $totalConns = $pdo->query("SELECT COUNT(*) FROM utility_connections WHERE status = 'Active'")->fetchColumn();
    
    // Total pending bills
    $pendingBillsCount = $pdo->query("SELECT COUNT(*) FROM bills WHERE status IN ('Pending', 'Overdue')")->fetchColumn();
    
    // Total amount due
    $totalAmountDue = $pdo->query("SELECT SUM(amount) FROM bills WHERE status IN ('Pending', 'Overdue')")->fetchColumn() ?: 0;
    
    // Paid this month
    $startOfMonth = date('Y-m-01');
    $stmtPaid = $pdo->prepare("SELECT SUM(amount) FROM bills WHERE status = 'Paid' AND paid_at >= ?");
    $stmtPaid->execute([$startOfMonth]);
    $paidThisMonth = $stmtPaid->fetchColumn() ?: 0;

    // Fetch store connections grid
    $stmtStores = $pdo->query("SELECT s.id, s.store_name, s.store_code, s.location_type, 
                               (SELECT COUNT(*) FROM utility_connections uc WHERE uc.store_id = s.id AND uc.status = 'Active') as active_connections
                               FROM stores s 
                               WHERE s.status = 'Active' 
                               ORDER BY s.store_code ASC");
    $storesGrid = $stmtStores->fetchAll();
    
    // Fetch recent bills
    $stmtRecent = $pdo->query("SELECT b.*, s.store_name, uc.utility_type, uc.provider_name 
                               FROM bills b
                               JOIN stores s ON b.store_id = s.id 
                               JOIN utility_connections uc ON b.connection_id = uc.id
                               ORDER BY b.created_at DESC LIMIT 5");
    $recentBills = $stmtRecent->fetchAll();

} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-primary-dark">Utility Operations Dashboard</h3>
        <p class="text-muted mb-0">Overview of utility connection statuses, bills processing, and payments tracking.</p>
    </div>
    <div>
        <a href="bill_upload.php" class="btn btn-command d-inline-flex align-items-center gap-2">
            <i class="bi bi-cloud-arrow-up-fill"></i> Upload New Bill
        </a>
    </div>
</div>

<!-- ============================================================
     KPI SUMMARY CARDS
============================================================ -->
<div class="row">
    <!-- Active Connections -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-command">
            <div class="widget-stat">
                <div class="widget-icon" style="background: var(--primary-light); color: var(--primary);">
                    <i class="bi bi-activity"></i>
                </div>
                <div>
                    <h5 class="kpi-value mb-1 fw-bold"><?php echo $totalConns; ?></h5>
                    <span class="text-muted small">Active Connections</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Bills Count -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-command">
            <div class="widget-stat">
                <div class="widget-icon" style="background: var(--warning-light); color: var(--warning);">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h5 class="kpi-value mb-1 fw-bold text-warning"><?php echo $pendingBillsCount; ?></h5>
                    <span class="text-muted small">Pending Bills</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Amount Due -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-command">
            <div class="widget-stat">
                <div class="widget-icon" style="background: var(--danger-light); color: var(--danger);">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <h5 class="mb-1 fw-bold text-danger">$<?php echo number_format($totalAmountDue, 2); ?></h5>
                    <span class="text-muted small">Total Due Amount</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Paid This Month -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card-command">
            <div class="widget-stat">
                <div class="widget-icon" style="background: var(--success-light); color: var(--success);">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <h5 class="mb-1 fw-bold text-success">$<?php echo number_format($paidThisMonth, 2); ?></h5>
                    <span class="text-muted small">Paid This Month</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Store Connections List -->
    <div class="col-12 col-lg-7">
        <div class="card-command">
            <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-shop me-2 text-primary"></i>Stores & Connections Summary</h5>
            <div class="table-responsive">
                <table class="table table-hover table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Store Name</th>
                            <th>Active Utilities</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($storesGrid as $store): ?>
                            <tr>
                                <td><span class="badge bg-secondary px-2 py-1"><?php echo e($store['store_code']); ?></span></td>
                                <td>
                                    <strong><?php echo e($store['store_name']); ?></strong>
                                    <span class="badge <?php echo $store['location_type'] === 'Owned Building' ? 'bg-primary text-white' : 'bg-light text-secondary border'; ?> ms-2" style="font-size: 0.72rem; font-weight: 500;">
                                        <?php echo e($store['location_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $store['active_connections'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $store['active_connections']; ?> Active
                                    </span>
                                </td>
                                <td>
                                    <a href="connections.php?store_id=<?php echo $store['id']; ?>" class="btn btn-sm btn-command-secondary px-2 py-1" style="font-size:0.75rem;">
                                        <i class="bi bi-gear"></i> Manage
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Bill Actions -->
    <div class="col-12 col-lg-5">
        <div class="card-command">
            <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Bill Uploads</h5>
            <?php if (empty($recentBills)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-3 mb-2 d-block"></i>
                    <span class="small">No bills processed yet.</span>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentBills as $bill): ?>
                        <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 fw-bold" style="font-size:0.85rem;"><?php echo e($bill['store_name']); ?> &mdash; <?php echo e($bill['utility_type']); ?></h6>
                                <p class="text-muted mb-0 small">Provider: <?php echo e($bill['provider_name']); ?> | Due: <?php echo e($bill['due_date']); ?></p>
                            </div>
                            <div class="text-end">
                                <span class="d-block fw-bold" style="font-size:0.9rem;">$<?php echo number_format($bill['amount'], 2); ?></span>
                                <span class="badge <?php echo $bill['status'] === 'Paid' ? 'bg-success' : ($bill['status'] === 'Overdue' ? 'bg-danger' : 'bg-warning text-dark'); ?>" style="font-size:0.7rem;">
                                    <?php echo $bill['status']; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center pt-3 border-top mt-2">
                    <a href="bills_list.php" class="small fw-semibold text-decoration-none">View All Bills in Ledger</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 3-days due reminder popup notification
    const dueAlerts = <?php echo json_encode(get_due_bills_alerts()); ?>;
    if (dueAlerts.length > 0) {
        let alertContent = '<div style="max-height:220px; overflow-y:auto;"><ul class="text-start small list-group list-group-flush">';
        dueAlerts.forEach(function(bill) {
            const daysLeft = Math.ceil((new Date(bill.due_date) - new Date()) / (1000 * 60 * 60 * 24));
            let badgeHtml = '';
            if (daysLeft < 0) {
                badgeHtml = '<span class="badge bg-danger ms-1">OVERDUE</span>';
            } else if (daysLeft === 0) {
                badgeHtml = '<span class="badge bg-danger ms-1">DUE TODAY</span>';
            } else {
                badgeHtml = `<span class="badge bg-warning text-dark ms-1">Due in ${daysLeft} days</span>`;
            }
            alertContent += `
                <li class="list-group-item px-1 py-2">
                    <strong>${bill.store_name}</strong> - ${bill.utility_type} bill ($${parseFloat(bill.amount).toFixed(2)}) is ${badgeHtml} <br>
                    <span class="text-muted">Account: ${bill.account_number} | Due Date: ${bill.due_date}</span>
                </li>`;
        });
        alertContent += '</ul></div>';
        
        Swal.fire({
            title: '<strong>Upcoming Due Dates!</strong>',
            html: alertContent,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-wallet2"></i> Go to Payments Ledger',
            cancelButtonText: 'Dismiss Reminders',
            confirmButtonColor: 'var(--primary)',
            cancelButtonColor: 'var(--text-muted)',
            customClass: {
                popup: 'rounded-4 shadow-lg'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'bills_list.php';
            }
        });
    }
});
</script>

<?php
require_once dirname(__DIR__) . '/src/footer.php';
?>
