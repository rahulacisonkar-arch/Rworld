<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

session_start_safe();
require_login();

$userId    = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$role      = $_SESSION['role'];
$storeId   = $_SESSION['store_id'];
$storeName = $_SESSION['store_name'];

// Unread notifications count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadNotificationsCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $unreadNotificationsCount = 0;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' — ' : ''; ?>Artée Logistics Portal</title>
    <meta name="description" content="Artée Fabrics & Home enterprise logistics and shipping management platform.">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Enterprise Style -->
    <link href="css/style.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<div id="app-container">

    <!-- ============================================================
         LEFT SIDEBAR
    ============================================================ -->
    <aside class="sidebar-command" id="sidebar">
        <!-- Brand Header -->
        <div class="sidebar-header">
            <div class="d-flex align-items-center gap-2" style="overflow: hidden; white-space: nowrap;">
                <i class="bi bi-shield-fill text-white" style="font-size: 1.4rem; flex-shrink: 0;"></i>
                <div>
                    <div class="sidebar-brand-text">ARTÉE LOGISTICS</div>
                    <div class="sidebar-brand-sub">Shipping Portal</div>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav>
            <ul class="sidebar-menu">
                <?php if ($role === 'Store User'): ?>
                    <li class="sidebar-section-title">Store Operations</li>
                    <li class="sidebar-item">
                        <a class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="bi bi-grid-fill"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link <?php echo $currentPage === 'request_create.php' ? 'active' : ''; ?>" href="request_create.php">
                            <i class="bi bi-plus-circle-fill"></i>
                            <span>Create Request</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="sidebar-section-title">HQ Operations</li>
                    <li class="sidebar-item">
                        <a class="sidebar-link <?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span>Operations Hub</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link <?php echo $currentPage === 'admin_label_create.php' ? 'active' : ''; ?>" href="admin_label_create.php">
                            <i class="bi bi-plus-circle-fill"></i>
                            <span>Create Vendor Label</span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="sidebar-section-title">Communication</li>
                <li class="sidebar-item">
                    <a class="sidebar-link <?php echo $currentPage === 'notifications_center.php' ? 'active' : ''; ?>" href="notifications_center.php">
                        <i class="bi bi-bell-fill"></i>
                        <span>Notifications
                            <?php if ($unreadNotificationsCount > 0): ?>
                                <span class="badge bg-danger ms-1" style="font-size: 0.6rem;"><?php echo $unreadNotificationsCount; ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Sidebar Footer — Role + Logout -->
        <div class="sidebar-footer">
            <div class="px-2 mb-2">
                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">Logged in as</div>
                <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);"><?php echo e($username); ?></div>
                <div style="font-size: 0.72rem; color: var(--primary); font-weight: 600;"><?php echo e($role); ?></div>
            </div>
            <div class="sidebar-item">
                <a class="sidebar-link sidebar-logout" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- ============================================================
         TOP NAVIGATION BAR
    ============================================================ -->
    <nav class="navbar-command" id="top-navbar">
        <!-- Left: Toggle + Search -->
        <div class="d-flex align-items-center gap-3">
            <button class="navbar-icon-btn border-0" id="sidebar-toggle-btn" title="Toggle Sidebar" style="background: transparent; box-shadow: none;">
                <i class="bi bi-list" style="font-size: 1.25rem; color: var(--text-secondary);"></i>
            </button>
        </div>

        <!-- Right: Clock + Bell + Profile -->
        <div class="d-flex align-items-center gap-3">
            <!-- Live Clock -->
            <div class="d-none d-lg-flex flex-column align-items-end" style="line-height: 1.2;">
                <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-primary);" id="current-live-time">--:--:-- --</div>
                <div style="font-size: 0.68rem; color: var(--text-muted);" id="current-live-date">Loading...</div>
            </div>

            <!-- Notification Bell -->
            <div class="position-relative">
                <button class="navbar-icon-btn" id="bell-toggle-btn" title="Notifications">
                    <i class="bi bi-bell-fill" style="font-size: 0.95rem;"></i>
                    <span id="unread-count" class="notification-badge" <?php echo $unreadNotificationsCount == 0 ? 'style="display:none;"' : ''; ?>>
                        <?php echo $unreadNotificationsCount; ?>
                    </span>
                </button>
            </div>

            <!-- Profile Dropdown -->
            <div class="dropdown">
                <button class="d-flex align-items-center gap-2 border-0 bg-transparent cursor-pointer" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer; padding: 4px 0;">
                    <div style="width: 34px; height: 34px; background: var(--primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8rem; flex-shrink: 0;">
                        <?php echo strtoupper(substr($username, 0, 2)); ?>
                    </div>
                    <div class="d-none d-md-block text-start">
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-primary);"><?php echo e($username); ?></div>
                        <div style="font-size: 0.68rem; color: var(--text-muted);"><?php echo e($role); ?></div>
                    </div>
                    <i class="bi bi-chevron-down d-none d-md-block" style="font-size: 0.7rem; color: var(--text-muted);"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border mt-2" style="min-width: 200px; border-color: var(--border) !important; border-radius: 10px;">
                    <?php if ($role === 'Store User'): ?>
                        <li class="px-3 py-2 border-bottom">
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Connected Store</div>
                            <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-primary);"><?php echo e($storeName); ?></div>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item py-2" href="logout.php">
                            <i class="bi bi-box-arrow-right text-danger me-2"></i>
                            Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ============================================================
         MAIN CONTENT AREA
    ============================================================ -->
    <main id="main-content">
        <div class="container-fluid p-4">
