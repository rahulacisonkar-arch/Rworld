<?php
$pageTitle = 'Bills Ledger';
require_once dirname(__DIR__) . '/src/header.php';

$success = '';
$error = '';

// Handle Payment Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    $csrf = $_POST['csrf_token'] ?? '';
    $billId = intval($_POST['bill_id'] ?? 0);
    $txRef = trim($_POST['transaction_ref'] ?? '');
    
    if (!validate_csrf_token($csrf)) {
        $error = "Security validation failed. Please try again.";
    } elseif ($billId <= 0 || empty($txRef)) {
        $error = "Transaction confirmation reference is required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE bills SET status = 'Paid', paid_at = NOW(), paid_by = ?, transaction_ref = ? WHERE id = ?");
            $stmt->execute([$userId, $txRef, $billId]);
            
            // Log it
            log_activity("Processed payment for utility bill", "Bill ID: $billId | Ref: $txRef");
            
            $success = "Payment successfully marked for bill ID: #$billId.";
        } catch (PDOException $e) {
            $error = "Failed to process payment: " . $e->getMessage();
        }
    }
}

// Handle Delete (Admin Only)
if (isset($_GET['delete_id'])) {
    require_role('Admin');
    $deleteId = intval($_GET['delete_id']);
    try {
        // Fetch file name to delete from filesystem
        $stmtFile = $pdo->prepare("SELECT bill_file_path FROM bills WHERE id = ?");
        $stmtFile->execute([$deleteId]);
        $fileName = $stmtFile->fetchColumn();
        
        $stmt = $pdo->prepare("DELETE FROM bills WHERE id = ?");
        $stmt->execute([$deleteId]);
        
        if ($fileName && file_exists(UPLOAD_DIR . $fileName)) {
            unlink(UPLOAD_DIR . $fileName);
        }
        
        log_activity("Deleted utility bill record", "Bill ID: $deleteId");
        $success = "Bill record deleted successfully.";
    } catch (PDOException $e) {
        $error = "Failed to delete bill: " . $e->getMessage();
    }
}

// Filters
$filterStore = intval($_GET['store_id'] ?? 0);
$filterType = trim($_GET['utility_type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "SELECT b.*, s.store_name, s.store_code, uc.utility_type, uc.provider_name, uc.account_number, u.name as paid_by_name 
        FROM bills b 
        JOIN stores s ON b.store_id = s.id
        JOIN utility_connections uc ON b.connection_id = uc.id
        LEFT JOIN users u ON b.paid_by = u.id
        WHERE 1=1";

$params = [];

if ($filterStore > 0) {
    $sql .= " AND b.store_id = ?";
    $params[] = $filterStore;
}
if ($filterType !== '') {
    $sql .= " AND uc.utility_type = ?";
    $params[] = $filterType;
}
if ($filterStatus !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY b.due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Fetch stores for filter dropdown
$stores = $pdo->query("SELECT id, store_name, store_code FROM stores WHERE status = 'Active' ORDER BY store_code ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-primary-dark">Utility Bills Ledger</h3>
        <p class="text-muted mb-0">Ledger registry of pending, overdue, and paid utility bills across all showrooms.</p>
    </div>
    <div>
        <a href="bill_upload.php" class="btn btn-command d-inline-flex align-items-center gap-2">
            <i class="bi bi-cloud-arrow-up-fill"></i> Upload New Bill
        </a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 py-3 px-4 mb-4" role="alert" style="border-radius: 8px;">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 py-3 px-4 mb-4" role="alert" style="border-radius: 8px; background: #FDE8E8; color: #9C1A1A;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

<!-- Filters Form -->
<div class="card-command mb-4">
    <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-2"></i>Filter Bills</h6>
    <form action="bills_list.php" method="GET" class="row g-3">
        <!-- Store -->
        <div class="col-12 col-md-3">
            <select class="form-select" name="store_id">
                <option value="0">All Showrooms</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $filterStore == $s['id'] ? 'selected' : ''; ?>>
                        [<?php echo e($s['store_code']); ?>] <?php echo e($s['store_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Utility Type -->
        <div class="col-12 col-md-3">
            <select class="form-select" name="utility_type">
                <option value="">All Utility Types</option>
                <option value="Telephone" <?php echo $filterType === 'Telephone' ? 'selected' : ''; ?>>Telephone</option>
                <option value="Internet" <?php echo $filterType === 'Internet' ? 'selected' : ''; ?>>Internet</option>
                <option value="Gas" <?php echo $filterType === 'Gas' ? 'selected' : ''; ?>>Gas</option>
                <option value="Electricity" <?php echo $filterType === 'Electricity' ? 'selected' : ''; ?>>Electricity</option>
                <option value="Sewer" <?php echo $filterType === 'Sewer' ? 'selected' : ''; ?>>Sewer</option>
                <option value="Water" <?php echo $filterType === 'Water' ? 'selected' : ''; ?>>Water</option>
            </select>
        </div>
        
        <!-- Status -->
        <div class="col-12 col-md-3">
            <select class="form-select" name="status">
                <option value="">All Payment Statuses</option>
                <option value="Pending" <?php echo $filterStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Paid" <?php echo $filterStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="Overdue" <?php echo $filterStatus === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
            </select>
        </div>

        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-command-secondary w-50 py-2">
                <i class="bi bi-filter"></i> Apply
            </button>
            <a href="bills_list.php" class="btn btn-outline-secondary w-50 py-2 d-flex align-items-center justify-content-center">
                Clear
            </a>
        </div>
    </form>
</div>

<!-- Ledger Table -->
<div class="card-command">
    <div class="table-responsive">
        <table class="table table-hover table-custom mb-0">
            <thead>
                <tr>
                    <th>Bill ID</th>
                    <th>Showroom</th>
                    <th>Utility Type</th>
                    <th>Provider</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Details</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bills)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No utility bills found matching the selected filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bills as $b): ?>
                        <tr>
                            <td>#<?php echo $b['id']; ?></td>
                            <td><strong>[<?php echo e($b['store_code']); ?>]</strong> <span class="d-none d-md-inline"><?php echo e($b['store_name']); ?></span></td>
                            <td>
                                <span class="badge bg-primary-light text-primary border border-primary-subtle px-2 py-1">
                                    <?php echo e($b['utility_type']); ?>
                                </span>
                            </td>
                            <td><?php echo e($b['provider_name']); ?> <br><small class="text-muted">Acct: <?php echo e($b['account_number']); ?></small></td>
                            <td class="fw-bold">$<?php echo number_format($b['amount'], 2); ?></td>
                            <td class="<?php echo $b['status'] === 'Overdue' ? 'text-danger fw-bold' : ''; ?>"><?php echo e($b['due_date']); ?></td>
                            <td>
                                <span class="badge <?php echo $b['status'] === 'Paid' ? 'bg-success' : ($b['status'] === 'Overdue' ? 'bg-danger animate-pulse' : 'bg-warning text-dark'); ?>">
                                    <?php echo $b['status']; ?>
                                </span>
                            </td>
                            <td style="font-size:0.78rem;">
                                <?php if ($b['status'] === 'Paid'): ?>
                                    <span class="text-success small">Paid on <?php echo date('m/d/Y', strtotime($b['paid_at'])); ?> <br>Ref: <?php echo e($b['transaction_ref']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Statement: <?php echo e($b['statement_date']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Download PDF -->
                                    <a href="download_bill.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary px-2 py-1" style="font-size:0.75rem;" title="Download Bill Invoice">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF
                                    </a>
                                    
                                    <!-- Pay button (Show pay modal if pending/overdue) -->
                                    <?php if ($b['status'] !== 'Paid'): ?>
                                        <button class="btn btn-sm btn-success px-2 py-1 pay-trigger-btn" style="font-size:0.75rem;" data-id="<?php echo $b['id']; ?>" data-amount="<?php echo number_format($b['amount'], 2); ?>" data-type="<?php echo e($b['utility_type']); ?>" data-store="<?php echo e($b['store_name']); ?>">
                                            <i class="bi bi-wallet2"></i> Pay
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Delete button (Admin Only) -->
                                    <?php if ($role === 'Admin'): ?>
                                        <button class="btn btn-sm btn-outline-danger px-2 py-1 delete-bill-btn" style="font-size:0.75rem;" data-id="<?php echo $b['id']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pay Confirmation Modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Process Utility Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="bills_list.php" method="POST">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="pay">
                <input type="hidden" name="bill_id" id="modal-bill-id">
                
                <div class="modal-body p-4">
                    <p class="mb-3">You are marking the following utility invoice as <strong>Paid</strong>:</p>
                    <div class="p-3 mb-4 rounded bg-light" style="font-size: 0.85rem; border-left: 4px solid var(--success);">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Showroom:</span>
                            <span class="fw-bold" id="modal-bill-store"></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Utility Type:</span>
                            <span class="fw-bold" id="modal-bill-type"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Amount Due:</span>
                            <span class="fw-bold text-success" id="modal-bill-amount"></span>
                        </div>
                    </div>

                    <!-- Payment confirmation code -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Transaction Reference / Check No. / Confirmation Code</label>
                        <input type="text" class="form-control" name="transaction_ref" placeholder="e.g. TXN-1985732" required>
                    </div>
                </div>
                
                <div class="modal-footer p-3 bg-light border-0 rounded-bottom-4">
                    <button type="button" class="btn btn-command-secondary py-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success py-2 px-3 fw-bold">
                        Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Pay modal triggering
    $('.pay-trigger-btn').on('click', function() {
        const id = $(this).data('id');
        const amount = $(this).data('amount');
        const type = $(this).data('type');
        const store = $(this).data('store');
        
        $('#modal-bill-id').val(id);
        $('#modal-bill-store').text(store);
        $('#modal-bill-type').text(type);
        $('#modal-bill-amount').text('$' + amount);
        
        const myModal = new bootstrap.Modal(document.getElementById('payModal'));
        myModal.show();
    });

    // Delete confirmation popup
    $('.delete-bill-btn').on('click', function() {
        const id = $(this).data('id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are deleting utility bill record #${id}. This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'No, cancel',
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'bills_list.php?delete_id=' + id;
            }
        });
    });
});
</script>

<?php
require_once dirname(__DIR__) . '/src/footer.php';
?>
