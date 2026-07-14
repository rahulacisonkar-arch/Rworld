<?php
/**
 * ROOFIQ — Projects Page
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();
$user = current_user();

// Handle create/update actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $prop_id = intval(isset($_POST['property_id'])  ? $_POST['property_id']  : 0);
        $name    = trim(isset($_POST['project_name'])   ? $_POST['project_name'] : '');
        $type    = isset($_POST['project_type'])        ? $_POST['project_type'] : 'Replacement';
        $status  = isset($_POST['status'])              ? $_POST['status']       : 'Lead';
        $est_id  = intval(isset($_POST['estimator_id']) ? $_POST['estimator_id'] : $user['id']);
        $value   = isset($_POST['contract_value'])      ? floatval($_POST['contract_value']) : null;
        $notes   = isset($_POST['notes'])               ? $_POST['notes']        : '';

        if ($prop_id && $name) {
            $id = db_insert('projects', array(
                'property_id'    => $prop_id,
                'project_name'   => $name,
                'project_type'   => $type,
                'status'         => $status,
                'estimator_id'   => $est_id,
                'contract_value' => $value,
                'notes'          => $notes,
            ));
            log_activity('Project created: ' . $name, 'projects', $id);
            $success = 'Project created successfully.';
        }
    } elseif ($action === 'update_status') {
        $id     = intval(isset($_POST['id'])      ? $_POST['id']     : 0);
        $status = isset($_POST['status'])         ? $_POST['status'] : '';
        if ($id && $status) {
            db_update('projects', array('status' => $status), 'id=?', array($id));
            log_activity('Project status updated to ' . $status, 'projects', $id);
        }
        header('Location: projects.php');
        exit;
    }
}

// Pre-fill from analysis page
$new_prop_id = intval(isset($_GET['property_id']) ? $_GET['property_id'] : 0);
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where = '';
$params = array();
if ($filter_status) {
    $where  = ' WHERE pj.status=?';
    $params = array($filter_status);
}

$projects = db_fetch_all(
    "SELECT pj.*, p.address, p.formatted_address, u.full_name as estimator_name
     FROM projects pj
     LEFT JOIN properties p ON p.id=pj.property_id
     LEFT JOIN users u ON u.id=pj.estimator_id
     {$where}
     ORDER BY pj.updated_at DESC",
    $params
);

$all_properties = db_fetch_all("SELECT id, address, formatted_address FROM properties ORDER BY created_at DESC");
$all_users      = db_fetch_all("SELECT id, full_name FROM users WHERE status='Active' ORDER BY full_name");

$statuses = array('Lead','Inspection','Estimate','Proposal Sent','Sold','Ordered','Installed','Closed','Cancelled');
$types    = array('Repair','Replacement','New Install','Inspection','Solar','Maintenance');

include_page_header('Projects');
page_content_header('Project Management', array('Projects' => ''));
?>

<div class="row mb-3">
  <div class="col-12">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <!-- Status Filter -->
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <a href="projects.php" class="btn btn-sm <?php echo !$filter_status ? 'btn-roofiq' : 'btn-outline-secondary'; ?>">All</a>
        <?php foreach ($statuses as $s): ?>
          <a href="projects.php?status=<?php echo urlencode($s); ?>"
             class="btn btn-sm <?php echo ($filter_status === $s) ? 'btn-roofiq' : 'btn-outline-secondary'; ?>"><?php echo e($s); ?></a>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-roofiq" data-toggle="modal" data-target="#modalCreateProject">
        <i class="fas fa-plus mr-1"></i> New Project
      </button>
    </div>
  </div>
</div>

<?php if (!empty($success)): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i><?php echo e($success); ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Project Name</th><th>Property</th><th>Type</th><th>Status</th>
          <th>Estimator</th><th>Value</th><th>Updated</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($projects)): ?>
          <tr><td colspan="8" class="text-center py-5 text-muted">
            No projects yet.
            <button class="btn btn-sm btn-roofiq ml-2" data-toggle="modal" data-target="#modalCreateProject">Create First Project</button>
          </td></tr>
        <?php else: foreach ($projects as $pj): ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#fff;"><?php echo e($pj['project_name']); ?></div>
              <div style="font-size:0.72rem;color:#94a3b8;">#<?php echo $pj['id']; ?></div>
            </td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.82rem;color:#94a3b8;">
              <a href="analysis.php?property_id=<?php echo e($pj['property_id']); ?>" style="color:#00d4ff;">
                <?php echo e($pj['formatted_address'] ? $pj['formatted_address'] : $pj['address']); ?>
              </a>
            </td>
            <td><span class="badge badge-secondary"><?php echo e($pj['project_type']); ?></span></td>
            <td>
              <form method="POST" style="display:inline;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?php echo e($pj['id']); ?>">
                <select name="status" onchange="this.form.submit()" class="form-control form-control-sm" style="width:130px;display:inline-block;">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?php echo e($s); ?>" <?php echo ($pj['status'] === $s) ? 'selected' : ''; ?>><?php echo e($s); ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="font-size:0.82rem;"><?php echo e($pj['estimator_name'] ? $pj['estimator_name'] : '—'); ?></td>
            <td style="font-weight:600;color:#00d4ff;"><?php echo $pj['contract_value'] ? fmt_currency($pj['contract_value']) : '—'; ?></td>
            <td style="font-size:0.72rem;color:#94a3b8;"><?php echo fmt_ago($pj['updated_at']); ?></td>
            <td>
              <a href="analysis.php?property_id=<?php echo e($pj['property_id']); ?>" class="btn btn-xs btn-outline-info" title="Analysis">
                <i class="fas fa-chart-line"></i>
              </a>
              <a href="report/generate.php?property_id=<?php echo e($pj['property_id']); ?>" target="_blank" class="btn btn-xs btn-outline-warning" title="PDF">
                <i class="fas fa-file-pdf"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div><!-- /table-responsive -->
  </div>
</div>

<!-- Create Project Modal -->
<div class="modal fade" id="modalCreateProject">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-project-diagram mr-2" style="color:#ffd600;"></i>New Project</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" action="projects.php">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Property *</label>
              <select name="property_id" class="form-control" required>
                <option value="">Select property...</option>
                <?php foreach ($all_properties as $p):
                  $sel = ($new_prop_id && $p['id'] == $new_prop_id) ? 'selected' : '';
                ?>
                  <option value="<?php echo e($p['id']); ?>" <?php echo $sel; ?>>
                    <?php echo e(substr($p['formatted_address'] ? $p['formatted_address'] : $p['address'], 0, 60)); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label>Project Name *</label>
              <input type="text" name="project_name" class="form-control" required placeholder="e.g. Full Roof Replacement - Smith Residence">
            </div>
            <div class="col-md-4 form-group">
              <label>Project Type</label>
              <select name="project_type" class="form-control">
                <?php foreach ($types as $t): ?>
                  <option><?php echo e($t); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 form-group">
              <label>Status</label>
              <select name="status" class="form-control">
                <?php foreach ($statuses as $s): ?>
                  <option><?php echo e($s); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 form-group">
              <label>Contract Value</label>
              <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                <input type="number" name="contract_value" class="form-control" step="0.01" placeholder="0.00">
              </div>
            </div>
            <div class="col-md-6 form-group">
              <label>Estimator</label>
              <select name="estimator_id" class="form-control">
                <?php foreach ($all_users as $u): ?>
                  <option value="<?php echo e($u['id']); ?>" <?php echo ($u['id'] == $user['id']) ? 'selected' : ''; ?>>
                    <?php echo e($u['full_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12 form-group">
              <label>Notes</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Project notes, special requirements..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-roofiq"><i class="fas fa-save mr-1"></i> Create Project</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
page_content_footer();
include_page_footer();
?>
