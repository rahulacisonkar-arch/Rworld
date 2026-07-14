<?php
// ============================================================
//  ARTEE VPN — User Management Console
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../app/helpers/auth.php';
require_auth();
require_admin(); // Admins only can access user list

$pdo = db();
$error = '';
$success = '';

// Handle user creation, suspension, activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF verification failed.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create') {
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = $_POST['role'] ?? 'user';

            if (empty($name) || empty($email) || empty($password)) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'A user with this email address already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (name, email, password, role, status)
                         VALUES (?, ?, ?, ?, "active")'
                    );
                    $stmt->execute([$name, $email, $hash, $role]);

                    log_activity(current_user()['id'], null, 'user.create', "Created user: $name ($email)", $_SERVER['REMOTE_ADDR']);
                    $success = 'User created successfully.';
                }
            }
        } elseif ($action === 'status' && !empty($_POST['id'])) {
            $user_id = (int)$_POST['id'];
            $status  = $_POST['status'] === 'suspended' ? 'suspended' : 'active';

            // Protect the current user from suspending themselves
            if ($user_id === current_user()['id']) {
                $error = 'You cannot suspend your own account.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
                $stmt->execute([$status, $user_id]);

                log_activity(current_user()['id'], null, 'user.status', "Changed status of user ID $user_id to $status", $_SERVER['REMOTE_ADDR']);
                $success = 'User status updated successfully.';
            }
        }
    }
}

// Fetch all users
$stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll();

$page_title = 'Users';
$page_subtitle = 'Manage organization team members, roles, and authorization status';
$active_nav = 'users';

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom: 20px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <?php echo e($success); ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-error" style="margin-bottom: 20px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo e($error); ?>
  </div>
<?php endif; ?>

<div class="two-col">

  <!-- Users List -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Registered Users</h2>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>User Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="peer-name-cell">
                <div class="user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;">
                  <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                </div>
                <strong><?php echo e($u['name']); ?></strong>
              </div>
            </td>
            <td><?php echo e($u['email']); ?></td>
            <td><span class="badge" style="background: rgba(255,255,255,0.06); text-transform: capitalize;"><?php echo e($u['role']); ?></span></td>
            <td>
              <span class="badge badge-<?php echo $u['status'] === 'active' ? 'success' : 'muted'; ?>">
                <?php echo e($u['status']); ?>
              </span>
            </td>
            <td class="text-muted"><?php echo e(date('M d, Y', strtotime($u['created_at']))); ?></td>
            <td style="text-align: right;">
              <?php if ($u['id'] !== current_user()['id']): ?>
              <form method="POST" action="users.php" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                <input type="hidden" name="action" value="status" />
                <input type="hidden" name="id" value="<?php echo $u['id']; ?>" />
                
                <?php if ($u['status'] === 'active'): ?>
                  <input type="hidden" name="status" value="suspended" />
                  <button type="submit" class="btn btn-outline" style="padding: 4px 10px; border-color: rgba(239,68,68,0.2); color: #fca5a5; font-size: 0.75rem;">
                    Suspend
                  </button>
                <?php else: ?>
                  <input type="hidden" name="status" value="active" />
                  <button type="submit" class="btn btn-outline" style="padding: 4px 10px; border-color: rgba(16,185,129,0.2); color: #6ee7b7; font-size: 0.75rem;">
                    Activate
                  </button>
                <?php endif; ?>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add User Panel -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Add New User</h2>
    </div>
    <div style="padding: 20px;">
      <form method="POST" action="users.php" class="auth-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
        <input type="hidden" name="action" value="create" />

        <div class="form-group">
          <label for="name" class="form-label">Full Name</label>
          <div class="input-wrap">
            <input type="text" id="name" name="name" class="form-input" style="padding-left: 14px;" placeholder="e.g. John Doe" required />
          </div>
        </div>

        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <div class="input-wrap">
            <input type="email" id="email" name="email" class="form-input" style="padding-left: 14px;" placeholder="e.g. john@artee.com" required />
          </div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Initial Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" class="form-input" style="padding-left: 14px;" placeholder="••••••••" required />
          </div>
        </div>

        <div class="form-group">
          <label for="role" class="form-label">System Role</label>
          <select id="role" name="role" class="form-input" style="padding: 10px 14px; background: rgba(255,255,255,0.04); border-color: var(--border-bright);">
            <option value="user" style="background:#111;">Standard User (Vpn Client access)</option>
            <option value="admin" style="background:#111;">Administrator (Dashboard access)</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 10px;">
          <span>Register User</span>
        </button>
      </form>
    </div>
  </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
