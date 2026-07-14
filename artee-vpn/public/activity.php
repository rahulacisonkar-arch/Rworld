<?php
// ============================================================
//  ARTEE VPN — Activity Log Viewer
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../app/helpers/auth.php';
require_auth();

$pdo = db();

// Pagination settings
$limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Fetch total records for pagination
$stmt = $pdo->query('SELECT COUNT(*) FROM activity_logs');
$total_records = (int)$stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) {
    $total_pages = 1;
}

// Fetch logs page
$stmt = $pdo->prepare(
    'SELECT al.*, u.name as user_name
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$page_title = 'Activity Log';
$page_subtitle = 'Audit trails, connection logs, and administrative modifications';
$active_nav = 'activity';

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Logs Table ────────────────────────────────────── -->
<div class="card">
  <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
    <h2 class="card-title">System Audit Log (Total: <?php echo $total_records; ?>)</h2>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 80px;">ID</th>
          <th>Timestamp</th>
          <th>Event Type</th>
          <th>Description</th>
          <th>User</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr>
          <td colspan="6" class="empty-state">No activities logged in the system database.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td class="text-muted">#<?php echo $log['id']; ?></td>
          <td class="text-muted" style="white-space: nowrap;"><?php echo e($log['created_at']); ?></td>
          <td><code class="ip-code" style="color: var(--accent-cyan); font-weight: 600;"><?php echo e($log['event_type']); ?></code></td>
          <td><strong><?php echo e($log['description'] ?? '—'); ?></strong></td>
          <td><?php echo e($log['user_name'] ?? 'System / Anonymous'); ?></td>
          <td><code style="font-size: 0.8rem;"><?php echo e($log['ip_address'] ?: '—'); ?></code></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Pagination ────────────────────────────────────── -->
<?php if ($total_pages > 1): ?>
<div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 20px;">
  <?php if ($page > 1): ?>
    <a href="activity.php?page=<?php echo $page - 1; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.78rem;">&laquo; Previous</a>
  <?php endif; ?>
  
  <span class="text-muted" style="font-size: 0.85rem;">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
  
  <?php if ($page < $total_pages): ?>
    <a href="activity.php?page=<?php echo $page + 1; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.78rem;">Next &raquo;</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
