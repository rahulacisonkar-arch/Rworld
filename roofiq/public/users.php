<?php
/**
 * ROOFIQ — Users Management (Admin only)
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();
require_role('Admin');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $username  = trim(isset($_POST['username'])  ? $_POST['username']  : '');
        $email     = trim(isset($_POST['email'])     ? $_POST['email']     : '');
        $full_name = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
        $role      = isset($_POST['role'])           ? $_POST['role']      : 'Estimator';
        $password  = isset($_POST['password'])       ? $_POST['password']  : '';

        if ($username && $email && $full_name && $password && strlen($password) >= 6) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $existing = db_fetch("SELECT id FROM users WHERE username=? OR email=?", array($username, $email));
            if ($existing) {
                $msg = 'ERROR: Username or email already exists.';
            } else {
                $id = db_insert('users', array(
                    'username'      => $username,
                    'email'         => $email,
                    'full_name'     => $full_name,
                    'role'          => $role,
                    'password_hash' => $hash,
                    'status'        => 'Active',
                ));
                $msg = 'SUCCESS: User ' . $username . ' created.';
                log_activity('User created: ' . $username, 'users', $id);
            }
        } else {
            $msg = 'ERROR: All fields required. Password must be at least 6 characters.';
        }
    } elseif ($action === 'toggle_status') {
        $id     = intval(isset($_POST['user_id']) ? $_POST['user_id'] : 0);
        $status = isset($_POST['status'])          ? $_POST['status']  : '';
        if ($id && in_array($status, array('Active','Inactive'))) {
            db_update('users', array('status' => $status), 'id=?', array($id));
            $msg = 'SUCCESS: User status updated.';
        }
    } elseif ($action === 'reset_password') {
        $id      = intval(isset($_POST['user_id']) ? $_POST['user_id'] : 0);
        $newpass = isset($_POST['new_password'])   ? $_POST['new_password'] : '';
        if ($id && strlen($newpass) >= 6) {
            $hash = password_hash($newpass, PASSWORD_BCRYPT);
            db_update('users', array('password_hash' => $hash), 'id=?', array($id));
            $msg = 'SUCCESS: Password reset.';
        } else {
            $msg = 'ERROR: Password must be at least 6 characters.';
        }
    }
}

$users = db_fetch_all("SELECT * FROM users ORDER BY role, created_at");

include_page_header('User Management');
page_content_header('User Management', array('Users' => ''));
?>

<?php if ($msg): ?>
  <div class="alert <?php echo strpos($msg,'ERROR') === 0 ? 'alert-danger' : 'alert-success'; ?> mb-3">
    <i class="fas <?php echo strpos($msg,'ERROR') === 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?> mr-2"></i><?php echo e(substr($msg, strpos($msg,':')+2)); ?>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <i class="fas fa-users mr-2" style="color:#00d4ff;"></i>All Users
        <div class="card-tools">
          <button class="btn btn-sm btn-roofiq" data-toggle="modal" data-target="#modalAddUser">
            <i class="fas fa-user-plus mr-1"></i>Add User
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div style="font-weight:600;color:#fff;"><?php echo e($u['full_name']); ?></div>
                  <div style="font-size:0.72rem;color:#94a3b8;"><?php echo e($u['email']); ?></div>
                </td>
                <td style="color:#00d4ff;font-size:0.88rem;"><code style="color:#00d4ff;"><?php echo e($u['username']); ?></code></td>
                <td>
                  <span class="badge" style="background:<?php echo $u['role'] === 'Admin' ? 'rgba(167,139,250,0.2)' : 'rgba(0,212,255,0.15)'; ?>;color:<?php echo $u['role'] === 'Admin' ? '#a78bfa' : '#00d4ff'; ?>;border:1px solid <?php echo $u['role'] === 'Admin' ? 'rgba(167,139,250,0.3)' : 'rgba(0,212,255,0.3)'; ?>;">
                    <?php echo e($u['role']); ?>
                  </span>
                </td>
                <td><?php echo status_badge($u['status']); ?></td>
                <td style="font-size:0.78rem;color:#94a3b8;"><?php echo $u['last_login'] ? fmt_ago($u['last_login']) : 'Never'; ?></td>
                <td>
                  <?php if ($u['id'] != current_user()['id']): ?>
                    <div style="display:flex;gap:5px;align-items:center;">
                    <form method="POST" style="display:inline;">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?php echo e($u['id']); ?>">
                      <input type="hidden" name="status" value="<?php echo $u['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                      <button type="submit" class="btn btn-xs btn-outline-warning" title="<?php echo $u['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>">
                        <i class="fas <?php echo $u['status'] === 'Active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                      </button>
                    </form>
                    <button class="btn btn-xs btn-outline-info"
                            onclick="showResetPwd(<?php echo $u['id']; ?>, '<?php echo e(addslashes($u['full_name'])); ?>')"
                            title="Reset Password">
                      <i class="fas fa-key"></i>
                    </button>
                    </div>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:0.72rem;">You</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div><!-- /table-responsive -->
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="fas fa-user-plus mr-2" style="color:#00e676;"></i>Add New User</div>
      <div class="card-body">
        <form method="POST">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="create">
          <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="full_name" class="form-control" required placeholder="John Smith">
          </div>
          <div class="form-group">
            <label>Username *</label>
            <input type="text" name="username" class="form-control" required placeholder="jsmith">
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" class="form-control" required placeholder="john@company.com">
          </div>
          <div class="form-group">
            <label>Role *</label>
            <select name="role" class="form-control">
              <option value="Estimator">Estimator</option>
              <option value="Admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label>Password *</label>
            <input type="password" name="password" class="form-control" required placeholder="Min 6 characters">
          </div>
          <button type="submit" class="btn btn-roofiq btn-block">
            <i class="fas fa-user-plus mr-1"></i> Create User
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="modalResetPwd">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-key mr-2"></i>Reset Password — <span id="reset-username"></span></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="reset-user-id">
        <div class="modal-body">
          <div class="form-group">
            <label>New Password (min 6 characters)</label>
            <input type="password" name="new_password" class="form-control" required placeholder="New password">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-roofiq">Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showResetPwd(id, name) {
  document.getElementById('reset-user-id').value  = id;
  document.getElementById('reset-username').textContent = name;
  $('#modalResetPwd').modal('show');
}
</script>

<?php
page_content_footer();
include_page_footer();
?>
