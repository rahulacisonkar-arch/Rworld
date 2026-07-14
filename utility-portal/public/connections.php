<?php
$pageTitle = 'Utility Connections';
require_once dirname(__DIR__) . '/src/header.php';
require_role('Admin');

$success = '';
$error = '';

// Handle connection addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $csrf = $_POST['csrf_token'] ?? '';
    $storeId = intval($_POST['store_id'] ?? 0);
    $type = trim($_POST['utility_type'] ?? '');
    $provider = trim($_POST['provider_name'] ?? '');
    $account = trim($_POST['account_number'] ?? '');
    $loginUser = trim($_POST['login_user'] ?? '');
    $loginPassword = trim($_POST['login_password'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!validate_csrf_token($csrf)) {
        $error = "Security check failed. Please try again.";
    } elseif ($storeId <= 0 || empty($type) || empty($provider) || empty($account)) {
        $error = "All fields except login details and notes are required.";
    } else {
        try {
            // Check if connection already exists for this store/type
            $chk = $pdo->prepare("SELECT id FROM utility_connections WHERE store_id = ? AND utility_type = ?");
            $chk->execute([$storeId, $type]);
            if ($chk->fetch()) {
                $error = "A connection for this utility type already exists for the selected store.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO utility_connections (store_id, utility_type, provider_name, account_number, login_user, login_password, website, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$storeId, $type, $provider, $account, $loginUser, $loginPassword, $website, $notes]);
                $success = "Utility connection added successfully.";
                log_activity("Added utility connection", "Store ID: $storeId | Type: $type");
            }
        } catch (PDOException $e) {
            $error = "Failed to add connection: " . $e->getMessage();
        }
    }
}

// Handle status toggle
if (isset($_GET['toggle_id'])) {
    $toggleId = intval($_GET['toggle_id']);
    try {
        $stmt = $pdo->prepare("SELECT status, utility_type, store_id FROM utility_connections WHERE id = ?");
        $stmt->execute([$toggleId]);
        $conn = $stmt->fetch();
        if ($conn) {
            $newStatus = $conn['status'] === 'Active' ? 'Inactive' : 'Active';
            $update = $pdo->prepare("UPDATE utility_connections SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $toggleId]);
            $success = "Connection status updated to $newStatus.";
            log_activity("Toggled utility connection status", "Conn ID: $toggleId | Status: $newStatus");
        }
    } catch (PDOException $e) {
        $error = "Failed to toggle status: " . $e->getMessage();
    }
}

// Fetch all stores for dropdown
$stores = $pdo->query("SELECT id, store_name, store_code, location_type FROM stores WHERE status = 'Active' ORDER BY store_code ASC")->fetchAll();

// Fetch connections list with filters
$filterStore = intval($_GET['store_id'] ?? 0);
$sql = "SELECT uc.*, s.store_name, s.store_code, s.location_type 
        FROM utility_connections uc 
        JOIN stores s ON uc.store_id = s.id";
$params = [];
if ($filterStore > 0) {
    $sql .= " WHERE uc.store_id = ?";
    $params[] = $filterStore;
}
$sql .= " ORDER BY s.store_code ASC, uc.utility_type ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$connections = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-primary-dark">Utility Connections Manager</h3>
        <p class="text-muted mb-0">Register and manage water, electric, gas, internet, and sewer providers for showrooms.</p>
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

<div class="row">
    <!-- Left Column: Add connection form -->
    <div class="col-12 col-lg-4">
        <div class="card-command">
            <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-plus-circle me-2 text-primary"></i>Register Connection</h5>
            <form action="connections.php" method="POST">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="add">

                <!-- Store Dropdown -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Target Store</label>
                    <select class="form-select" name="store_id" required>
                        <option value="">-- Choose Store Location --</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $filterStore == $s['id'] ? 'selected' : ''; ?>>
                                [<?php echo e($s['store_code']); ?>] <?php echo e($s['store_name']); ?> (<?php echo e($s['location_type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Utility Type -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Utility Type</label>
                    <select class="form-select" name="utility_type" required>
                        <option value="">-- Select Connection Type --</option>
                        <option value="Telephone">Telephone</option>
                        <option value="Internet">Internet</option>
                        <option value="Gas">Gas</option>
                        <option value="Electricity">Electricity</option>
                        <option value="Sewer">Sewer</option>
                        <option value="Water">Water</option>
                    </select>
                </div>

                <!-- Provider Name -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Provider / Vendor Name</label>
                    <input type="text" class="form-control" name="provider_name" placeholder="e.g. Duke Energy" required>
                </div>

                <!-- Account Number -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Account Number</label>
                    <input type="text" class="form-control" name="account_number" placeholder="e.g. ELEC-95827" required>
                </div>

                <!-- Login Details -->
                <div class="border-top pt-2 mt-3">
                    <h6 class="fw-bold mb-2 text-primary small">Portal Access & Credentials</h6>
                    <div class="mb-2">
                        <label class="form-label fw-semibold text-muted" style="font-size: 0.75rem;">Portal Username / Email</label>
                        <input type="text" class="form-control form-control-sm" name="login_user" placeholder="e.g. ap.artee@gmail.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold text-muted" style="font-size: 0.75rem;">Portal Password</label>
                        <input type="text" class="form-control form-control-sm" name="login_password" placeholder="Password token">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold text-muted" style="font-size: 0.75rem;">Portal Login Website Link</label>
                        <input type="url" class="form-control form-control-sm" name="website" placeholder="https://portal.provider.com/login">
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-3 mt-3">
                    <label class="form-label fw-semibold small">Notes / Instructions</label>
                    <textarea class="form-control" name="notes" rows="2" placeholder="Enter billing cycles or connection notes..."></textarea>
                </div>

                <button type="submit" class="btn btn-command w-100 py-2">
                    <i class="bi bi-check-lg"></i> Register Utility Connection
                </button>
            </form>
        </div>
    </div>

    <!-- Right Column: Connections list -->
    <div class="col-12 col-lg-8">
        <div class="card-command">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h5 class="fw-bold mb-0"><i class="bi bi-list-stars me-2 text-primary"></i>Active Utility Directory</h5>
                
                <!-- Filter form -->
                <form action="connections.php" method="GET" class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" name="store_id" onchange="this.form.submit()">
                        <option value="0">Show All Showrooms</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $filterStore == $s['id'] ? 'selected' : ''; ?>>
                                [<?php echo e($s['store_code']); ?>] <?php echo e($s['store_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-custom table-connections mb-0" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>Store</th>
                            <th>Type</th>
                            <th>Provider / Account</th>
                            <th>Portal Login Credentials</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($connections)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No utility connections registered.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($connections as $c): ?>
                                <tr>
                                    <td>
                                        <strong>[<?php echo e($c['store_code']); ?>]</strong> <span class="d-none d-md-inline"><?php echo e($c['store_name']); ?></span>
                                        <span class="badge <?php echo $c['location_type'] === 'Owned Building' ? 'bg-primary text-white' : 'bg-light text-secondary border'; ?> d-block d-md-inline ms-md-2" style="font-size: 0.68rem; font-weight: 500; padding: 2px 4px;">
                                            <?php echo e($c['location_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-light text-primary border border-primary-subtle px-2 py-1">
                                            <?php echo e($c['utility_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo e($c['provider_name']); ?></div>
                                        <code style="font-size: 0.78rem;"><?php echo e($c['account_number']); ?></code>
                                    </td>
                                    <td>
                                        <?php if (!empty($c['website'])): ?>
                                            <a href="<?php echo e($c['website']); ?>" target="_blank" class="btn btn-sm btn-outline-primary px-2 py-0 mb-1" style="font-size:0.7rem;">
                                                <i class="bi bi-box-arrow-up-right"></i> Log In Portal
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small d-block mb-1">No Portal URL</span>
                                        <?php endif; ?>
                                        <?php if (!empty($c['login_user'])): ?>
                                            <div class="text-muted small" style="font-size: 0.72rem;"><strong>User:</strong> <code><?php echo e($c['login_user']); ?></code></div>
                                        <?php endif; ?>
                                        <?php if (!empty($c['login_password'])): ?>
                                            <div class="text-muted small" style="font-size: 0.72rem;"><strong>Pass:</strong> <code><?php echo e($c['login_password']); ?></code></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $c['status'] === 'Active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $c['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="connections.php?toggle_id=<?php echo $c['id']; ?>&store_id=<?php echo $filterStore; ?>" class="btn btn-sm <?php echo $c['status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success'; ?> px-2 py-1" style="font-size:0.75rem;">
                                            <?php echo $c['status'] === 'Active' ? '<i class="bi bi-x-circle"></i> Deactivate' : '<i class="bi bi-check-circle"></i> Activate'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/src/footer.php';
?>
