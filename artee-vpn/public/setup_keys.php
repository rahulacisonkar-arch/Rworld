<?php
// ============================================================
//  ARTEE VPN — Setup Keys Console
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../app/helpers/auth.php';
require_auth();

$pdo = db();
$error = '';
$success = '';

// Handle setup key creation or revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF verification failed.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create') {
            $name = trim($_POST['key_name'] ?? '');
            $type = $_POST['key_type'] ?? 'reusable';
            $max_usage = !empty($_POST['max_usage']) ? (int)$_POST['max_usage'] : null;
            $expires_days = !empty($_POST['expires_days']) ? (int)$_POST['expires_days'] : null;

            if (empty($name)) {
                $error = 'Key name is required.';
            } else {
                // Generate a cryptographically secure random key
                $key_val = 'setup-' . bin2hex(random_bytes(16));
                
                $expires_at = null;
                if ($expires_days !== null && $expires_days > 0) {
                    $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_days days"));
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO setup_keys (user_id, key_name, setup_key, key_type, usage_count, max_usage, expires_at)
                     VALUES (?, ?, ?, ?, 0, ?, ?)'
                );
                $stmt->execute([current_user()['id'], $name, $key_val, $type, $max_usage, $expires_at]);

                log_activity(current_user()['id'], null, 'key.create', "Generated setup key: $name", $_SERVER['REMOTE_ADDR']);
                $success = 'Setup key generated successfully.';
            }
        } elseif ($action === 'revoke' && !empty($_POST['id'])) {
            $key_id = (int)$_POST['id'];

            $stmt = $pdo->prepare('UPDATE setup_keys SET revoked = 1 WHERE id = ?');
            $stmt->execute([$key_id]);

            log_activity(current_user()['id'], null, 'key.revoke', "Revoked setup key ID: $key_id", $_SERVER['REMOTE_ADDR']);
            $success = 'Setup key revoked successfully.';
        }
    }
}

// Fetch active setup keys
$stmt = $pdo->query(
    'SELECT sk.*, u.name as creator_name
     FROM setup_keys sk
     LEFT JOIN users u ON u.id = sk.user_id
     ORDER BY sk.revoked ASC, sk.created_at DESC'
);
$keys = $stmt->fetchAll();

$page_title = 'Setup Keys';
$page_subtitle = 'Generate tokens to authenticate new devices into the VPN network';
$active_nav = 'setup_keys';

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

  <!-- Setup Keys List -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Setup Keys</h2>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Key Name</th>
            <th>Type</th>
            <th>Usage</th>
            <th>Token</th>
            <th>Status</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($keys)): ?>
          <tr>
            <td colspan="6" class="empty-state">No setup keys generated yet. Use the panel on the right to create one.</td>
          </tr>
          <?php else: ?>
          <?php foreach ($keys as $k): ?>
          <tr>
            <td>
              <strong><?php echo e($k['key_name']); ?></strong>
              <div class="text-muted" style="font-size: 0.75rem;">Created by: <?php echo e($k['creator_name']); ?></div>
            </td>
            <td><span class="badge" style="background: rgba(255,255,255,0.06);"><?php echo e($k['key_type']); ?></span></td>
            <td>
              <?php echo $k['usage_count']; ?> / <?php echo $k['max_usage'] !== null ? $k['max_usage'] : '∞'; ?>
            </td>
            <td>
              <code class="ip-code" style="cursor: pointer;" onclick="navigator.clipboard.writeText('<?php echo $k['setup_key']; ?>'); alert('Key copied to clipboard!');">
                <?php echo e(substr($k['setup_key'], 0, 12)); ?>...
              </code>
            </td>
            <td>
              <?php if ($k['revoked']): ?>
                <span class="badge badge-muted">Revoked</span>
              <?php elseif ($k['expires_at'] && strtotime($k['expires_at']) < time()): ?>
                <span class="badge badge-muted">Expired</span>
              <?php else: ?>
                <span class="badge badge-success">Active</span>
              <?php endif; ?>
            </td>
            <td style="text-align: right;">
              <?php if (!$k['revoked']): ?>
              <form method="POST" action="setup_keys.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to revoke this setup key? New devices won\'t be able to connect using it.');">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                <input type="hidden" name="action" value="revoke" />
                <input type="hidden" name="id" value="<?php echo $k['id']; ?>" />
                <button type="submit" class="btn btn-outline" style="padding: 4px 10px; border-color: rgba(239,68,68,0.2); color: #fca5a5; font-size: 0.75rem;">
                  Revoke
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Generate Key Panel -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Generate Key</h2>
    </div>
    <div style="padding: 20px;">
      <form method="POST" action="setup_keys.php" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
        <input type="hidden" name="action" value="create" />

        <div class="form-group">
          <label for="key_name" class="form-label">Key Description Name</label>
          <div class="input-wrap">
            <input type="text" id="key_name" name="key_name" class="form-input" style="padding-left: 14px;" placeholder="e.g. Developer Laptop Key" required />
          </div>
        </div>

        <div class="form-group">
          <label for="key_type" class="form-label">Type</label>
          <select id="key_type" name="key_type" class="form-input" style="padding: 10px 14px; background: rgba(255,255,255,0.04); border-color: var(--border-bright);">
            <option value="reusable" style="background:#111;">Reusable (Unlimited machines)</option>
            <option value="one-off" style="background:#111;">One-off (Single use)</option>
          </select>
        </div>

        <div class="form-group">
          <label for="max_usage" class="form-label">Max Usage count (Optional)</label>
          <div class="input-wrap">
            <input type="number" id="max_usage" name="max_usage" class="form-input" style="padding-left: 14px;" placeholder="e.g. 5" min="1" />
          </div>
        </div>

        <div class="form-group">
          <label for="expires_days" class="form-label">Expires In (Days - Optional)</label>
          <div class="input-wrap">
            <input type="number" id="expires_days" name="expires_days" class="form-input" style="padding-left: 14px;" placeholder="e.g. 30" min="1" />
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 10px;">
          <span>Generate Token</span>
        </button>
      </form>
    </div>
  </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
