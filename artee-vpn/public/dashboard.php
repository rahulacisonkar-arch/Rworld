<?php
// ============================================================
//  ARTEE VPN — Refactored Dashboard Home
//  Uses includes/header.php and includes/footer.php
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../app/helpers/auth.php';
require_auth();
$user = current_user();

// ── Fetch Dashboard Stats ──────────────────────────────────
$pdo = db();

// Total users
$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM users');
$total_users = (int)$stmt->fetch()['cnt'];

// Active peers (seen in last 5 minutes)
$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM peers WHERE status = "online"');
$online_peers = (int)$stmt->fetch()['cnt'];

// Total peers
$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM peers');
$total_peers = (int)$stmt->fetch()['cnt'];

// Setup keys
$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM setup_keys WHERE revoked = 0');
$active_keys = (int)$stmt->fetch()['cnt'];

// Recent activity (last 10 entries)
$stmt = $pdo->query(
    'SELECT al.*, u.name as user_name
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT 10'
);
$activities = $stmt->fetchAll();

// Recent peers
$stmt = $pdo->query(
    'SELECT p.*, u.name as user_name
     FROM peers p
     LEFT JOIN users u ON u.id = p.user_id
     ORDER BY p.updated_at DESC
     LIMIT 8'
);
$peers = $stmt->fetchAll();

$page_title = 'Dashboard';
$page_subtitle = 'Welcome back, ' . $user['name'];
$active_nav = 'dashboard';

require_once __DIR__ . '/includes/header.php';
?>

    <!-- ── Stat Cards ──────────────────────────────────── -->
    <div class="stats-grid">
      <div class="stat-card stat-card-blue">
        <div class="stat-card-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a8.38 8.38 0 0 1 13 0"/></svg>
        </div>
        <div class="stat-card-body">
          <p class="stat-card-value"><?php echo $online_peers; ?></p>
          <p class="stat-card-label">Online Peers</p>
        </div>
        <div class="stat-card-trend trend-up">↑ Live</div>
      </div>

      <div class="stat-card stat-card-purple">
        <div class="stat-card-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-card-body">
          <p class="stat-card-value"><?php echo $total_users; ?></p>
          <p class="stat-card-label">Total Users</p>
        </div>
        <div class="stat-card-trend">Registered</div>
      </div>

      <div class="stat-card stat-card-green">
        <div class="stat-card-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="stat-card-body">
          <p class="stat-card-value"><?php echo $active_keys; ?></p>
          <p class="stat-card-label">Active Setup Keys</p>
        </div>
        <div class="stat-card-trend">Ready</div>
      </div>

      <div class="stat-card stat-card-orange">
        <div class="stat-card-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="stat-card-body">
          <p class="stat-card-value"><?php echo $total_peers; ?></p>
          <p class="stat-card-label">Total Peers</p>
        </div>
        <div class="stat-card-trend">All Time</div>
      </div>
    </div>

    <!-- ── Two Column Layout ────────────────────────────── -->
    <div class="two-col">

      <!-- Peers Table -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Recent Peers</h2>
          <a href="peers.php" class="card-action">View all →</a>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Peer Name</th>
                <th>IP Address</th>
                <th>OS</th>
                <th>Status</th>
                <th>Last Seen</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($peers)): ?>
              <tr><td colspan="5" class="empty-state">No peers connected yet. Download the client and use a Setup Key to connect.</td></tr>
              <?php else: ?>
              <?php foreach ($peers as $peer): ?>
              <tr>
                <td>
                  <div class="peer-name-cell">
                    <span class="peer-dot peer-dot-<?php echo e($peer['status']); ?>"></span>
                    <?php echo e($peer['name']); ?>
                  </div>
                </td>
                <td><code class="ip-code"><?php echo e($peer['ip_address'] ?? '—'); ?></code></td>
                <td><?php echo e($peer['os'] ?? '—'); ?></td>
                <td><span class="badge badge-<?php echo $peer['status'] === 'online' ? 'success' : 'muted'; ?>"><?php echo e($peer['status']); ?></span></td>
                <td class="text-muted"><?php echo $peer['last_seen'] ? e(date('M d, H:i', strtotime($peer['last_seen']))) : 'Never'; ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Activity Log -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Activity Log</h2>
          <a href="activity.php" class="card-action">View all →</a>
        </div>
        <div class="activity-list">
          <?php if (empty($activities)): ?>
          <div class="empty-state">No activity recorded yet.</div>
          <?php else: ?>
          <?php foreach ($activities as $log): ?>
          <div class="activity-item">
            <div class="activity-dot"></div>
            <div class="activity-body">
              <p class="activity-event"><?php echo e($log['event_type']); ?></p>
              <p class="activity-desc"><?php echo e($log['description'] ?? ''); ?></p>
              <p class="activity-meta">
                <?php echo $log['user_name'] ? e($log['user_name']) . ' · ' : ''; ?>
                <?php echo e(date('M d, H:i', strtotime($log['created_at']))); ?>
              </p>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /.two-col -->

<?php
require_once __DIR__ . '/includes/footer.php';
?>
