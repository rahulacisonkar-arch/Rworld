<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

session_start_safe();
require_login();

$userId    = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$role      = $_SESSION['role'];
$name      = $_SESSION['name'] ?? 'System User';

// Run overdue bills check automatically on page load
check_overdue_bills();

// Unread notifications count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
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
    <title><?php echo isset($pageTitle) ? $pageTitle . ' — ' : ''; ?>Artée Utility Portal</title>
    <meta name="description" content="Artée Fabrics & Home enterprise utility management and payment tracking platform.">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Enterprise Style -->
    <link href="css/style.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- SweetAlert2 for beautiful popups -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <i class="bi bi-lightning-charge-fill text-white" style="font-size: 1.4rem; flex-shrink: 0;"></i>
                <div>
                    <div class="sidebar-brand-text">ARTÉE UTILITY</div>
                    <div class="sidebar-brand-sub">Management Portal</div>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav>
            <ul class="sidebar-menu">
                <li class="sidebar-section-title">Core Operations</li>
                <li class="sidebar-item">
                    <a class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="sidebar-item">
                    <a class="sidebar-link <?php echo $currentPage === 'bill_upload.php' ? 'active' : ''; ?>" href="bill_upload.php">
                        <i class="bi bi-cloud-arrow-up-fill"></i>
                        <span>Upload Utility Bill</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link <?php echo $currentPage === 'bulk_upload.php' ? 'active' : ''; ?>" href="bulk_upload.php">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                        <span>Bulk Import</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link <?php echo $currentPage === 'bills_list.php' ? 'active' : ''; ?>" href="bills_list.php">
                        <i class="bi bi-receipt"></i>
                        <span>Bills Ledger</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link <?php echo $currentPage === 'yoy_report.php' ? 'active' : ''; ?>" href="yoy_report.php">
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>YoY Report</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link <?php echo $currentPage === 'schedule.php' ? 'active' : ''; ?>" href="schedule.php">
                        <i class="bi bi-calendar-event"></i>
                        <span>Scheduling</span>
                    </a>
                </li>

                <?php if ($role === 'Admin'): ?>
                    <li class="sidebar-section-title">Administration</li>
                    <li class="sidebar-item">
                        <a class="sidebar-link <?php echo $currentPage === 'connections.php' ? 'active' : ''; ?>" href="connections.php">
                            <i class="bi bi-activity"></i>
                            <span>Utility Connections</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Sidebar Footer — Role + Logout -->
        <div class="sidebar-footer">
            <div class="px-2 mb-2">
                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">Logged in as</div>
                <div style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);"><?php echo e($name); ?></div>
                <div style="font-size: 0.72rem; color: var(--primary); font-weight: 600;"><?php echo e($role); ?> Portal</div>
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
        <!-- Left: Toggle -->
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
        </div>
    </nav>

    <!-- Main Content Area -->
    <main id="main-content">
        <div class="container-fluid p-4">
