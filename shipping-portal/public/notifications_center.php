<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

session_start_safe();
require_login();

$userId = $_SESSION['user_id'];

// Mark notifications as read if button clicked via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        log_activity($userId, "Marked all notifications as read");
        header("Location: notifications_center.php");
        exit;
    } catch (PDOException $e) {
        die("Error updating notifications: " . $e->getMessage());
    }
}

$pageTitle = "Notification Center";
require_once dirname(__DIR__) . '/src/header.php';

// Fetch Notifications for this user
try {

    // Fetch Unread Notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $unreadNotifications = $stmt->fetchAll();

    // Fetch Read Notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 1 ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$userId]);
    $readNotifications = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error retrieving notifications: " . $e->getMessage());
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-dark mb-1"><i class="bi bi-bell-fill me-2 text-primary"></i>Notification Center</h2>
                <p class="text-muted mb-0">Stay updated with status changes, label delivery, and log actions.</p>
            </div>
            <?php if (!empty($unreadNotifications)): ?>
                <form action="notifications_center.php" method="POST">
                    <button type="submit" name="mark_all" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-check2-all me-1"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- UNREAD NOTIFICATIONS -->
        <div class="card shadow border-0 mb-4">
            <div class="card-header bg-custom py-3 navbar-custom text-white d-flex align-items-center">
                <h5 class="brand-font mb-0 text-white"><i class="bi bi-envelope-open me-2 text-warning"></i>Unread Alerts</h5>
                <span class="badge bg-warning text-dark ms-2 fw-bold"><?php echo count($unreadNotifications); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($unreadNotifications)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bell-slash fs-2 mb-2 d-block"></i>
                        No new notifications at this time.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($unreadNotifications as $n): ?>
                            <?php $enrich = get_notification_labels($n['title'], $n['message']); ?>
                            <div class="list-group-item list-group-item-action p-3 border-bottom position-relative bg-light">
                                <span class="position-absolute top-50 start-0 translate-middle p-1 bg-danger border border-light rounded-circle" style="left:15px !important;"></span>
                                <div class="ps-3">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-primary fw-bold"><?php echo e($n['title']); ?></h6>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 text-dark small"><?php echo e($n['message']); ?></p>
                                    
                                    <?php if ($enrich): ?>
                                        <div class="mt-2 d-flex flex-wrap gap-1">
                                            <?php if (!empty($enrich['labels'])): ?>
                                                <?php foreach ($enrich['labels'] as $lbl): ?>
                                                    <a href="download_label.php?id=<?php echo $lbl['id']; ?>" class="btn btn-sm btn-danger text-white py-1 px-2 text-decoration-none rounded d-inline-flex align-items-center" style="font-size: 0.72rem; font-weight: 500; gap: 4px; box-shadow: 0 1px 3px rgba(220,53,69,0.2);">
                                                        <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF (<?php echo e($lbl['tracking_number'] ?: 'Label'); ?>)
                                                    </a>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <a href="request_view.php?id=<?php echo $enrich['request_id']; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none rounded d-inline-flex align-items-center" style="font-size: 0.72rem; font-weight: 500; gap: 4px;">
                                                <i class="bi bi-eye"></i> View Request
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- HISTORICAL READ NOTIFICATIONS -->
        <div class="card shadow border-0">
            <div class="card-header bg-light py-3 d-flex align-items-center">
                <h5 class="brand-font mb-0 text-dark"><i class="bi bi-archive-fill me-2 text-muted"></i>Read Archive</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($readNotifications)): ?>
                    <div class="text-center text-muted py-5">
                        No read notifications in your archive.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($readNotifications as $n): ?>
                            <?php $enrich = get_notification_labels($n['title'], $n['message']); ?>
                            <div class="list-group-item list-group-item-action p-3 border-bottom">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 text-muted fw-semibold"><?php echo e($n['title']); ?></h6>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></small>
                                </div>
                                <p class="mb-1 text-muted small"><?php echo e($n['message']); ?></p>
                                
                                <?php if ($enrich): ?>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <?php if (!empty($enrich['labels'])): ?>
                                            <?php foreach ($enrich['labels'] as $lbl): ?>
                                                <a href="download_label.php?id=<?php echo $lbl['id']; ?>" class="btn btn-sm btn-danger text-white py-1 px-2 text-decoration-none rounded d-inline-flex align-items-center" style="font-size: 0.72rem; font-weight: 500; gap: 4px; box-shadow: 0 1px 3px rgba(220,53,69,0.2);">
                                                    <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF (<?php echo e($lbl['tracking_number'] ?: 'Label'); ?>)
                                                </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <a href="request_view.php?id=<?php echo $enrich['request_id']; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none rounded d-inline-flex align-items-center" style="font-size: 0.72rem; font-weight: 500; gap: 4px;">
                                            <i class="bi bi-eye"></i> View Request
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/src/footer.php'; ?>
