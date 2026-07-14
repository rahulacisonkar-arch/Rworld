<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
redirect_unauthenticated();

if ($_SESSION['role'] !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validate_csrf_token($csrf_token)) {
        $error = "CSRF verification failed.";
    } else {
        if ($action === 'add' || $action === 'edit') {
            $empId = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $designation = trim($_POST['designation'] ?? '');
            $hourly_rate = floatval($_POST['hourly_rate'] ?? 0.00);
            $store_id = intval($_POST['store_id'] ?? 0);
            $employment_type = trim($_POST['employment_type'] ?? 'Full-time');
            $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
            $salary_grade = trim($_POST['salary_grade'] ?? 'Grade A');
            
            // Format emergency contact as JSON
            $emergency = [
                'name' => trim($_POST['emergency_name'] ?? ''),
                'phone' => trim($_POST['emergency_phone'] ?? ''),
                'relationship' => trim($_POST['emergency_relation'] ?? '')
            ];
            $emergency_json = json_encode($emergency);

            if (empty($name) || $store_id === 0) {
                $error = "Name and Store Assignment are required fields.";
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO employees (name, email, phone, designation, hourly_rate, store_id, employment_type, hire_date, salary_grade, emergency_contacts, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
                        $stmt->execute([$name, $email, $phone, $designation, $hourly_rate, $store_id, $employment_type, $hire_date, $salary_grade, $emergency_json]);
                        $success = "Employee created successfully.";
                    } else {
                        // Transfer employee or update fields
                        $stmt = $pdo->prepare("UPDATE employees SET name = ?, email = ?, phone = ?, designation = ?, hourly_rate = ?, store_id = ?, employment_type = ?, hire_date = ?, salary_grade = ?, emergency_contacts = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $phone, $designation, $hourly_rate, $store_id, $employment_type, $hire_date, $salary_grade, $emergency_json, $empId]);
                        $success = "Employee details updated successfully.";
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $empId = intval($_POST['id'] ?? 0);
            try {
                // Soft delete: set status to 'Inactive' and set deleted_at
                $stmt = $pdo->prepare("UPDATE employees SET status = 'Inactive', deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$empId]);
                $success = "Employee soft-deleted successfully.";
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } elseif ($action === 'upload_doc') {
            $empId = intval($_POST['employee_id'] ?? 0);
            $docName = trim($_POST['doc_name'] ?? '');
            
            if ($empId === 0 || empty($docName) || empty($_FILES['doc_file']['name'])) {
                $error = "Please fill in all document upload fields.";
            } else {
                $file = $_FILES['doc_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];

                if (!in_array($ext, $allowed)) {
                    $error = "Only PDF, PNG, JPG, and DOC files are allowed.";
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error = "File size exceeds 5MB limit.";
                } else {
                    $destDir = __DIR__ . '/public/uploads/';
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    $fileName = 'emp_' . $empId . '_' . time() . '.' . $ext;
                    $destPath = 'uploads/' . $fileName; // Relative path for DB

                    if (move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $destPath)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO employee_documents (employee_id, document_name, file_path) VALUES (?, ?, ?)");
                            $stmt->execute([$empId, $docName, $destPath]);
                            $success = "Document uploaded successfully.";
                        } catch (Exception $e) {
                            $error = "Database save error: " . $e->getMessage();
                        }
                    } else {
                        $error = "Failed to move uploaded file.";
                    }
                }
            }
        }
    }
}

// Fetch search filter parameters
$search = trim($_GET['search'] ?? '');
$filterStore = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
$filterType = trim($_GET['employment_type'] ?? '');

// Fetch all stores
$stmtStores = $pdo->query("SELECT id, store_code, city, store_name FROM stores ORDER BY store_code ASC");
$allStores = $stmtStores->fetchAll();

// Fetch employees list
$sql = "SELECT e.*, s.city, s.store_code 
        FROM employees e 
        JOIN stores s ON e.store_id = s.id 
        WHERE e.deleted_at IS NULL AND e.status = 'Active'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (e.name LIKE ? OR e.designation LIKE ? OR e.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($filterStore > 0) {
    $sql .= " AND e.store_id = ?";
    $params[] = $filterStore;
}
if (!empty($filterType)) {
    $sql .= " AND e.employment_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY e.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Map emergency contacts and documents
$empDocs = [];
$empListFormatted = [];
foreach ($employees as $emp) {
    // Fetch docs
    $stmtDocs = $pdo->prepare("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY uploaded_at DESC");
    $stmtDocs->execute([$emp['id']]);
    $docs = $stmtDocs->fetchAll();
    
    $emergency = json_decode($emp['emergency_contacts'] ?? '', true) ?: ['name' => '', 'phone' => '', 'relationship' => ''];
    
    $emp['emergency'] = $emergency;
    $emp['docs'] = $docs;
    $empListFormatted[] = $emp;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Directory — Artée Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<div id="app-container">
    <!-- SIDEBAR -->
    <aside class="sidebar-command" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill text-white" style="font-size: 1.4rem;"></i>
                <div>
                    <div class="sidebar-brand-text">ARTÉE ATTENDANCE</div>
                    <div class="sidebar-brand-sub">HQ Operations Hub</div>
                </div>
            </div>
        </div>
        <nav>
            <ul class="sidebar-menu">
                <li class="sidebar-section-title">Operations</li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="admin_dashboard.php">
                        <i class="bi bi-grid-fill"></i>
                        <span>HQ Overview</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="manage_employees.php">
                        <i class="bi bi-people-fill"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="payroll.php">
                        <i class="bi bi-wallet2"></i>
                        <span>Payroll Admin</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="reports.php">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="artee_intelligence.php">
                        <i class="bi bi-cpu"></i>
                        <span>Artee Intelligence</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="quickbooks_integration.php">
                        <i class="bi bi-shuffle"></i>
                        <span>QuickBooks</span>
                    </a>
                </li>
                <li class="sidebar-item mt-4">
                    <a class="sidebar-link text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Sign Out</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- MAIN BODY -->
    <div id="main-content" class="w-100">
        <nav class="navbar navbar-expand-lg px-4 py-2 border-bottom bg-white" id="top-navbar">
            <div class="container-fluid p-0">
                <span class="navbar-brand fw-bold text-primary-dark" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">
                    <i class="bi bi-people me-2 text-primary"></i>Employee Management
                </span>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-primary btn-sm px-3 py-1.5" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="bi bi-person-plus-fill me-1.5"></i>Add Employee
                    </button>
                </div>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 mb-4"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success border-0 mb-4"><?php echo e($success); ?></div>
            <?php endif; ?>

            <!-- Search Filters -->
            <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                <form method="GET" action="manage_employees.php" class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search Name, Email, Designation..." value="<?php echo e($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="store_id" class="form-select">
                            <option value="0">All Locations</option>
                            <?php foreach ($allStores as $st): ?>
                                <option value="<?php echo $st['id']; ?>" <?php echo $filterStore === intval($st['id']) ? 'selected' : ''; ?>>
                                    [<?php echo e($st['store_code']); ?>] <?php echo e($st['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="employment_type" class="form-select">
                            <option value="">All Employment Types</option>
                            <option value="Full-time" <?php echo $filterType === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                            <option value="Part-time" <?php echo $filterType === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                            <option value="Contractor" <?php echo $filterType === 'Contractor' ? 'selected' : ''; ?>>Contractor</option>
                            <option value="Intern" <?php echo $filterType === 'Intern' ? 'selected' : ''; ?>>Intern</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" style="background: var(--brand-brown); border-color: var(--brand-brown);">
                            <i class="bi bi-funnel-fill me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Employee List -->
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" style="font-size: 0.82rem;">
                        <thead>
                            <tr class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase;">
                                <th>Name / Email</th>
                                <th>Location</th>
                                <th>Designation / Type</th>
                                <th>Salary / Grade</th>
                                <th>Emergency Contact</th>
                                <th>Documents</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($empListFormatted)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No employees found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($empListFormatted as $emp): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-primary-dark"><?php echo e($emp['name']); ?></div>
                                            <div class="text-secondary" style="font-size: 0.72rem;"><?php echo e($emp['email'] ?: 'No email'); ?> | <?php echo e($emp['phone'] ?: 'No phone'); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($emp['city']); ?></div>
                                            <div class="text-secondary" style="font-size: 0.72rem;">Code: <?php echo e($emp['store_code']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($emp['designation']); ?></div>
                                            <span class="badge bg-light text-dark border"><?php echo e($emp['employment_type']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold">$<?php echo number_format($emp['hourly_rate'], 2); ?>/hr</div>
                                            <div class="text-secondary" style="font-size: 0.72rem;"><?php echo e($emp['salary_grade']); ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($emp['emergency']['name'])): ?>
                                                <div class="fw-semibold"><?php echo e($emp['emergency']['name']); ?> (<?php echo e($emp['emergency']['relationship']); ?>)</div>
                                                <div class="text-muted" style="font-size: 0.72rem;"><?php echo e($emp['emergency']['phone']); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($emp['docs'] as $d): ?>
                                                    <a href="<?php echo e($d['file_path']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-1.5" style="font-size: 0.68rem;" title="<?php echo e($d['document_name']); ?>">
                                                        <i class="bi bi-file-earmark-arrow-down-fill text-danger me-0.5"></i><?php echo e(strlen($d['document_name']) > 8 ? substr($d['document_name'], 0, 8) . '...' : $d['document_name']); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                                <button class="btn btn-primary btn-sm py-0 px-1.5" style="font-size: 0.68rem; border-radius: 4px;" onclick="prepareUpload(<?php echo $emp['id']; ?>, '<?php echo e(addslashes($emp['name'])); ?>')" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                                                    <i class="bi bi-upload"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1.5">
                                                <button class="btn btn-outline-primary btn-sm" onclick='prepareEdit(<?php echo json_encode($emp); ?>)' data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                                <form method="POST" action="manage_employees.php" onsubmit="return confirm('Are you sure you want to soft-delete this employee?');" style="display:inline-block;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                                                    <?php csrf_input(); ?>
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </form>
                                            </div>
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
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="manage_employees.php">
                <input type="hidden" name="action" value="add">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Register New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Employee Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="John Doe">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="john@arteefabrics.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="555-0199">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Designation / Title</label>
                        <input type="text" name="designation" class="form-control" placeholder="Showroom Associate">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Hourly Rate ($) *</label>
                        <input type="number" step="0.01" name="hourly_rate" class="form-control" required value="15.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Location Assignment *</label>
                        <select name="store_id" class="form-select" required>
                            <option value="">Select Location</option>
                            <?php foreach ($allStores as $st): ?>
                                <option value="<?php echo $st['id']; ?>">[<?php echo e($st['store_code']); ?>] <?php echo e($st['city']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Employment Type</label>
                        <select name="employment_type" class="form-select">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contractor">Contractor</option>
                            <option value="Intern">Intern</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Hire Date</label>
                        <input type="date" name="hire_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Salary Grade</label>
                        <input type="text" name="salary_grade" class="form-control" value="Grade A">
                    </div>
                    
                    <div class="border-top mt-4 pt-3 w-100">
                        <h6 class="fw-bold text-secondary mb-3">Emergency Contact</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label text-muted" style="font-size:0.75rem;">Contact Name</label>
                                <input type="text" name="emergency_name" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted" style="font-size:0.75rem;">Contact Phone</label>
                                <input type="text" name="emergency_phone" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted" style="font-size:0.75rem;">Relationship</label>
                                <input type="text" name="emergency_relation" class="form-control" placeholder="Spouse, Friend, etc.">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="manage_employees.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Employee Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Designation / Title</label>
                        <input type="text" name="designation" id="edit_designation" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Hourly Rate ($) *</label>
                        <input type="number" step="0.01" name="hourly_rate" id="edit_hourly_rate" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Location Assignment *</label>
                        <select name="store_id" id="edit_store_id" class="form-select" required>
                            <option value="">Select Location</option>
                            <?php foreach ($allStores as $st): ?>
                                <option value="<?php echo $st['id']; ?>">[<?php echo e($st['store_code']); ?>] <?php echo e($st['city']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Employment Type</label>
                        <select name="employment_type" id="edit_employment_type" class="form-select">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contractor">Contractor</option>
                            <option value="Intern">Intern</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Hire Date</label>
                        <input type="date" name="hire_date" id="edit_hire_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Salary Grade</label>
                        <input type="text" name="salary_grade" id="edit_salary_grade" class="form-control">
                    </div>
                    
                    <div class="border-top mt-4 pt-3 w-100">
                        <h6 class="fw-bold text-secondary mb-3">Emergency Contact</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label text-muted" style="font-size:0.75rem;">Contact Name</label>
                                <input type="text" name="emergency_name" id="edit_emergency_name" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted" style="font-size:0.75rem;">Contact Phone</label>
                                <input type="text" name="emergency_phone" id="edit_emergency_phone" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted" style="font-size:0.75rem;">Relationship</label>
                                <input type="text" name="emergency_relation" id="edit_emergency_relation" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- UPLOAD MODAL -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_employees.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_doc">
                <input type="hidden" name="employee_id" id="upload_employee_id">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Upload Document for <span id="upload_employee_name" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div>
                        <label class="form-label fw-semibold">Document Name / Type</label>
                        <input type="text" name="doc_name" class="form-control" required placeholder="e.g. W-4, ID Card, Certificate">
                    </div>
                    <div>
                        <label class="form-label fw-semibold">File Upload *</label>
                        <input type="file" name="doc_file" class="form-control" required>
                        <div class="form-text text-muted" style="font-size:0.75rem;">Allowed formats: PDF, PNG, JPG. Max size: 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function prepareEdit(emp) {
    document.getElementById('edit_id').value = emp.id;
    document.getElementById('edit_name').value = emp.name;
    document.getElementById('edit_email').value = emp.email;
    document.getElementById('edit_phone').value = emp.phone;
    document.getElementById('edit_designation').value = emp.designation;
    document.getElementById('edit_hourly_rate').value = emp.hourly_rate;
    document.getElementById('edit_store_id').value = emp.store_id;
    document.getElementById('edit_employment_type').value = emp.employment_type;
    document.getElementById('edit_hire_date').value = emp.hire_date || '';
    document.getElementById('edit_salary_grade').value = emp.salary_grade || '';
    
    document.getElementById('edit_emergency_name').value = emp.emergency.name || '';
    document.getElementById('edit_emergency_phone').value = emp.emergency.phone || '';
    document.getElementById('edit_emergency_relation').value = emp.emergency.relationship || '';
}

function prepareUpload(id, name) {
    document.getElementById('upload_employee_id').value = id;
    document.getElementById('upload_employee_name').innerText = name;
}
</script>
</body>
</html>
