<?php
// ============================================================
//  ARTEE VPN — Peers Management Console
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../app/helpers/auth.php';
require_auth();

$pdo = db();
$error = '';
$success = '';

// Handle Peer Deletion/Revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF verification failed.';
    } else {
        if ($_POST['action'] === 'delete' && !empty($_POST['id'])) {
            $peer_id = (int)$_POST['id'];
            
            // Get peer name for logging
            $stmt = $pdo->prepare('SELECT name FROM peers WHERE id = ?');
            $stmt->execute([$peer_id]);
            $peer = $stmt->fetch();
            
            if ($peer) {
                $stmt = $pdo->prepare('DELETE FROM peers WHERE id = ?');
                $stmt->execute([$peer_id]);
                
                log_activity(current_user()['id'], null, 'peer.delete', "Deleted/revoked peer: " . $peer['name'], $_SERVER['REMOTE_ADDR']);
                $success = 'Peer removed successfully.';
            } else {
                $error = 'Peer not found.';
            }
        }
    }
}

// Fetch all peers
$stmt = $pdo->query(
    'SELECT p.*, u.name as user_name
     FROM peers p
     LEFT JOIN users u ON u.id = p.user_id
     ORDER BY p.status DESC, p.updated_at DESC'
);
$peers = $stmt->fetchAll();

$page_title = 'Peers';
$page_subtitle = 'Manage connected machines, status, and IP addresses';
$active_nav = 'peers';

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

<!-- ── Peers Card ────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">All Connected Peers (<?php echo count($peers); ?>)</h2>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name / Owner</th>
          <th>IP Address</th>
          <th>OS / Version</th>
          <th>Status</th>
          <th>Last Seen</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($peers)): ?>
        <tr>
          <td colspan="6" class="empty-state">
            No peers connected. Log in from your desktop/mobile client using a Setup Key to register devices.
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($peers as $peer): ?>
        <tr>
          <td>
            <div class="peer-name-cell">
              <span class="peer-dot peer-dot-<?php echo e($peer['status']); ?>"></span>
              <div>
                <strong><?php echo e($peer['name']); ?></strong>
                <div class="text-muted" style="font-size: 0.75rem;">Owner: <?php echo e($peer['user_name'] ?? 'System'); ?></div>
              </div>
            </div>
          </td>
          <td><code class="ip-code"><?php echo e($peer['ip_address'] ?? '—'); ?></code></td>
          <td>
            <?php echo e($peer['os'] ?? '—'); ?> 
            <span class="text-muted" style="font-size: 0.75rem;">(NB <?php echo e($peer['version'] ?? '—'); ?>)</span>
          </td>
          <td>
            <span class="badge badge-<?php echo $peer['status'] === 'online' ? 'success' : 'muted'; ?>">
              <?php echo e($peer['status']); ?>
            </span>
          </td>
          <td class="text-muted">
            <?php echo $peer['last_seen'] ? e(date('Y-m-d H:i:s', strtotime($peer['last_seen']))) : 'Never'; ?>
          </td>
          <td style="text-align: right;">
            <form method="POST" action="peers.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to revoke this peer? The device will lose VPN access.');">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="id" value="<?php echo $peer['id']; ?>" />
              <button type="submit" class="btn btn-outline" style="padding: 6px 12px; border-color: rgba(239,68,68,0.2); color: #fca5a5;">
                Disconnect
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

<?php
require_once __DIR__ . '/includes/footer.php';
?>
