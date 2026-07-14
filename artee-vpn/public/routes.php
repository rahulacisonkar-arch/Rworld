<?php
// ============================================================
//  ARTEE VPN — Network Routes Console
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../app/helpers/auth.php';
require_auth();

$pdo = db();
$error = '';
$success = '';

// Handle route creation, deletion, or toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF verification failed.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create') {
            $name    = trim($_POST['name'] ?? '');
            $network = trim($_POST['network'] ?? '');
            $gateway = !empty($_POST['gateway_peer']) ? (int)$_POST['gateway_peer'] : null;
            $metric  = !empty($_POST['metric']) ? (int)$_POST['metric'] : 9999;

            if (empty($name) || empty($network)) {
                $error = 'Route name and network CIDR are required.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO routes (name, network, gateway_peer, metric, enabled)
                     VALUES (?, ?, ?, ?, 1)'
                );
                $stmt->execute([$name, $network, $gateway, $metric]);

                log_activity(current_user()['id'], null, 'route.create', "Created route: $name ($network)", $_SERVER['REMOTE_ADDR']);
                $success = 'Network route created successfully.';
            }
        } elseif ($action === 'delete' && !empty($_POST['id'])) {
            $route_id = (int)$_POST['id'];

            $stmt = $pdo->prepare('DELETE FROM routes WHERE id = ?');
            $stmt->execute([$route_id]);

            log_activity(current_user()['id'], null, 'route.delete', "Deleted route ID: $route_id", $_SERVER['REMOTE_ADDR']);
            $success = 'Route deleted successfully.';
        } elseif ($action === 'toggle' && !empty($_POST['id'])) {
            $route_id = (int)$_POST['id'];
            $enabled  = $_POST['enabled'] == '1' ? 1 : 0;

            $stmt = $pdo->prepare('UPDATE routes SET enabled = ? WHERE id = ?');
            $stmt->execute([$enabled, $route_id]);

            log_activity(current_user()['id'], null, 'route.toggle', "Toggled route ID $route_id status to " . ($enabled ? 'enabled' : 'disabled'), $_SERVER['REMOTE_ADDR']);
            $success = 'Route status updated successfully.';
        }
    }
}

// Fetch all routes
$stmt = $pdo->query(
    'SELECT r.*, p.name as gateway_name
     FROM routes r
     LEFT JOIN peers p ON p.id = r.gateway_peer
     ORDER BY r.created_at DESC'
);
$routes = $stmt->fetchAll();

// Fetch peers for gateway selection dropdown
$stmt = $pdo->query('SELECT id, name, ip_address FROM peers ORDER BY name ASC');
$peers = $stmt->fetchAll();

$page_title = 'Routes';
$page_subtitle = 'Manage network routing and access to external subnets or private networks';
$active_nav = 'routes';

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

  <!-- Routes List -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Network Routes</h2>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Route Name</th>
            <th>Network CIDR</th>
            <th>Gateway Peer</th>
            <th>Metric</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($routes)): ?>
          <tr>
            <td colspan="6" class="empty-state">No custom network routes defined. Use the form on the right to add a route.</td>
          </tr>
          <?php else: ?>
          <?php foreach ($routes as $r): ?>
          <tr>
            <td><strong><?php echo e($r['name']); ?></strong></td>
            <td><code class="ip-code"><?php echo e($r['network']); ?></code></td>
            <td><?php echo e($r['gateway_name'] ?? 'None (Direct)'); ?></td>
            <td class="text-muted"><?php echo $r['metric']; ?></td>
            <td>
              <span class="badge badge-<?php echo $r['enabled'] ? 'success' : 'muted'; ?>">
                <?php echo $r['enabled'] ? 'Enabled' : 'Disabled'; ?>
              </span>
            </td>
            <td style="text-align: right;">
              <form method="POST" action="routes.php" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                <input type="hidden" name="action" value="toggle" />
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>" />
                <input type="hidden" name="enabled" value="<?php echo $r['enabled'] ? '0' : '1'; ?>" />
                <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 0.75rem;">
                  <?php echo $r['enabled'] ? 'Disable' : 'Enable'; ?>
                </button>
              </form>

              <form method="POST" action="routes.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this route?');">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?php echo $r['id']; ?>" />
                <button type="submit" class="btn btn-outline" style="padding: 4px 10px; border-color: rgba(239,68,68,0.2); color: #fca5a5; font-size: 0.75rem;">
                  Delete
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add Route Panel -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Add Network Route</h2>
    </div>
    <div style="padding: 20px;">
      <form method="POST" action="routes.php" class="auth-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
        <input type="hidden" name="action" value="create" />

        <div class="form-group">
          <label for="name" class="form-label">Route Name</label>
          <div class="input-wrap">
            <input type="text" id="name" name="name" class="form-input" style="padding-left: 14px;" placeholder="e.g. Office Subnet" required />
          </div>
        </div>

        <div class="form-group">
          <label for="network" class="form-label">Network Range (CIDR)</label>
          <div class="input-wrap">
            <input type="text" id="network" name="network" class="form-input" style="padding-left: 14px;" placeholder="e.g. 192.168.10.0/24" required />
          </div>
        </div>

        <div class="form-group">
          <label for="gateway_peer" class="form-label">Gateway Peer (Routing Node)</label>
          <select id="gateway_peer" name="gateway_peer" class="form-input" style="padding: 10px 14px; background: rgba(255,255,255,0.04); border-color: var(--border-bright);">
            <option value="" style="background:#111;">None (Direct route)</option>
            <?php foreach ($peers as $p): ?>
              <option value="<?php echo $p['id']; ?>" style="background:#111;"><?php echo e($p['name']); ?> (<?php echo e($p['ip_address']); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="metric" class="form-label">Metric Priority</label>
          <div class="input-wrap">
            <input type="number" id="metric" name="metric" class="form-input" style="padding-left: 14px;" placeholder="e.g. 100" min="1" value="9999" />
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 10px;">
          <span>Create Route</span>
        </button>
      </form>
    </div>
  </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
