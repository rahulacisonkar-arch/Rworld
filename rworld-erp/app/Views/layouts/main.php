<!DOCTYPE html>
<html lang="en" data-app-url="<?= APP_URL ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Rworld ERP</title>
<meta name="description" content="Rworld ERP Point of Sale Management System">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body class="qb-body">

<!-- ── Sidebar ─────────────────────────────────────────────────── -->
<nav class="qb-sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fas fa-cash-register"></i>
            </div>
            <div class="sidebar-logo-text">
                <span class="brand">Rworld ERP</span>
                <span class="sub">Point of Sale</span>
            </div>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Collapse Sidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
    </div>

    <!-- Company Info -->
    <div class="sidebar-company">
        <i class="fas fa-building"></i>
        <span class="sidebar-label">Artee Fabrics and Home</span>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav" id="sidebarNav">

        <!-- Dashboard -->
        <a href="<?= APP_URL ?>/dashboard" class="nav-item <?= View::activeClass('/dashboard') ?>">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="sidebar-label">Dashboard</span>
        </a>

        <!-- POS / Billing -->
        <div class="nav-group">
            <div class="nav-group-label"><span>Billing</span></div>
            <a href="<?= APP_URL ?>/salesorder/create" class="nav-item <?= View::activeClass('/salesorder/create') ?>">
                <i class="fas fa-file-contract nav-icon"></i>
                <span class="sidebar-label">Sales Order</span>
                <span class="badge ml-auto sidebar-label" style="background:#f59e0b;color:#fff;font-size:.68rem">ORDER</span>
            </a>
            <a href="<?= APP_URL ?>/salesorder" class="nav-item <?= View::activeClass('/salesorder') ?>">
                <i class="fas fa-clipboard-list nav-icon"></i>
                <span class="sidebar-label">Order List</span>
            </a>
            <a href="<?= APP_URL ?>/sales/create" class="nav-item <?= View::activeClass('/sales/create') ?>">
                <i class="fas fa-receipt nav-icon"></i>
                <span class="sidebar-label">New Sale</span>
                <span class="badge badge-success ml-auto sidebar-label">POS</span>
            </a>
            <a href="<?= APP_URL ?>/sales" class="nav-item <?= View::activeClass('/sales') ?>">
                <i class="fas fa-list nav-icon"></i>
                <span class="sidebar-label">Sales List</span>
            </a>
            <a href="<?= APP_URL ?>/salesreturn" class="nav-item <?= View::activeClass('/salesreturn') ?>">
                <i class="fas fa-undo nav-icon"></i>
                <span class="sidebar-label">Sales Returns</span>
            </a>
            <a href="<?= APP_URL ?>/quotation" class="nav-item <?= View::activeClass('/quotation') ?>">
                <i class="fas fa-file-alt nav-icon"></i>
                <span class="sidebar-label">Quotations</span>
            </a>
            <a href="<?= APP_URL ?>/deliverynote" class="nav-item <?= View::activeClass('/deliverynote') ?>">
                <i class="fas fa-truck nav-icon"></i>
                <span class="sidebar-label">Delivery Notes</span>
            </a>
        </div>

        <!-- Purchase -->
        <div class="nav-group">
            <div class="nav-group-label"><span>Purchase</span></div>
            <a href="<?= APP_URL ?>/purchase/create" class="nav-item <?= View::activeClass('/purchase/create') ?>">
                <i class="fas fa-shopping-cart nav-icon"></i>
                <span class="sidebar-label">New Purchase</span>
            </a>
            <a href="<?= APP_URL ?>/purchase" class="nav-item <?= View::activeClass('/purchase') ?>">
                <i class="fas fa-file-invoice nav-icon"></i>
                <span class="sidebar-label">Purchase List</span>
            </a>
            <a href="<?= APP_URL ?>/purchasereturn" class="nav-item <?= View::activeClass('/purchasereturn') ?>">
                <i class="fas fa-reply nav-icon"></i>
                <span class="sidebar-label">Purchase Returns</span>
            </a>
            <a href="<?= APP_URL ?>/purchaseorder" class="nav-item <?= View::activeClass('/purchaseorder') ?>">
                <i class="fas fa-clipboard-list nav-icon"></i>
                <span class="sidebar-label">Purchase Orders</span>
            </a>
            <a href="<?= APP_URL ?>/grn" class="nav-item <?= View::activeClass('/grn') ?>">
                <i class="fas fa-boxes nav-icon"></i>
                <span class="sidebar-label">GRN</span>
            </a>
        </div>

        <!-- Inventory -->
        <div class="nav-group">
            <div class="nav-group-label"><span>Inventory</span></div>
            <a href="<?= APP_URL ?>/item" class="nav-item <?= View::activeClass('/item') ?>">
                <i class="fas fa-box nav-icon"></i>
                <span class="sidebar-label">Item Master</span>
            </a>
            <a href="<?= APP_URL ?>/inventory/bulkupload" class="nav-item <?= View::activeClass('/inventory/bulkupload') ?>">
                <i class="fas fa-file-upload nav-icon"></i>
                <span class="sidebar-label">Bulk Inventory Upload</span>
            </a>
            <a href="<?= APP_URL ?>/stockledger" class="nav-item <?= View::activeClass('/stockledger') ?>">
                <i class="fas fa-warehouse nav-icon"></i>
                <span class="sidebar-label">Stock Ledger</span>
            </a>
            <a href="<?= APP_URL ?>/stockjournal" class="nav-item <?= View::activeClass('/stockjournal') ?>">
                <i class="fas fa-exchange-alt nav-icon"></i>
                <span class="sidebar-label">Stock Journal</span>
            </a>
            <a href="<?= APP_URL ?>/transfer" class="nav-item <?= View::activeClass('/transfer') ?>">
                <i class="fas fa-random nav-icon"></i>
                <span class="sidebar-label">Branch Transfer</span>
            </a>
        </div>

        <!-- Accounts -->
        <div class="nav-group">
            <div class="nav-group-label"><span>Accounts</span></div>
            <a href="<?= APP_URL ?>/payment" class="nav-item <?= View::activeClass('/payment') ?>">
                <i class="fas fa-hand-holding-usd nav-icon"></i>
                <span class="sidebar-label">Payments</span>
            </a>
            <a href="<?= APP_URL ?>/receipt" class="nav-item <?= View::activeClass('/receipt') ?>">
                <i class="fas fa-money-bill-wave nav-icon"></i>
                <span class="sidebar-label">Receipts</span>
            </a>
            <a href="<?= APP_URL ?>/voucher" class="nav-item <?= View::activeClass('/voucher') ?>">
                <i class="fas fa-file-invoice-dollar nav-icon"></i>
                <span class="sidebar-label">Journal Vouchers</span>
            </a>
            <a href="<?= APP_URL ?>/expense" class="nav-item <?= View::activeClass('/expense') ?>">
                <i class="fas fa-wallet nav-icon"></i>
                <span class="sidebar-label">Expenses</span>
            </a>
        </div>

        <!-- Masters -->
        <div class="nav-group">
            <div class="nav-group-label"><span>Masters</span></div>
            <a href="<?= APP_URL ?>/customer" class="nav-item <?= View::activeClass('/customer') ?>">
                <i class="fas fa-users nav-icon"></i>
                <span class="sidebar-label">Customers</span>
            </a>
            <a href="<?= APP_URL ?>/supplier" class="nav-item <?= View::activeClass('/supplier') ?>">
                <i class="fas fa-industry nav-icon"></i>
                <span class="sidebar-label">Suppliers</span>
            </a>
            <a href="<?= APP_URL ?>/ledger" class="nav-item <?= View::activeClass('/ledger') ?>">
                <i class="fas fa-book nav-icon"></i>
                <span class="sidebar-label">Ledger Accounts</span>
            </a>
            <a href="<?= APP_URL ?>/taxmaster" class="nav-item <?= View::activeClass('/taxmaster') ?>">
                <i class="fas fa-percent nav-icon"></i>
                <span class="sidebar-label">Tax Master</span>
            </a>
        </div>

        <!-- Reports -->
        <div class="nav-group">
            <div class="nav-group-label"><span>Reports</span></div>
            <a href="<?= APP_URL ?>/report/sales" class="nav-item <?= View::activeClass('/report/sales') ?>">
                <i class="fas fa-chart-bar nav-icon"></i>
                <span class="sidebar-label">Sales Report</span>
            </a>
            <a href="<?= APP_URL ?>/report/purchase" class="nav-item <?= View::activeClass('/report/purchase') ?>">
                <i class="fas fa-chart-line nav-icon"></i>
                <span class="sidebar-label">Purchase Report</span>
            </a>
            <a href="<?= APP_URL ?>/report/stock" class="nav-item <?= View::activeClass('/report/stock') ?>">
                <i class="fas fa-cubes nav-icon"></i>
                <span class="sidebar-label">Stock Report</span>
            </a>
            <a href="<?= APP_URL ?>/report/gst" class="nav-item <?= View::activeClass('/report/gst') ?>">
                <i class="fas fa-receipt nav-icon"></i>
                <span class="sidebar-label">Tax Report</span>
            </a>
            <a href="<?= APP_URL ?>/report/pl" class="nav-item <?= View::activeClass('/report/pl') ?>">
                <i class="fas fa-balance-scale nav-icon"></i>
                <span class="sidebar-label">P&amp;L Statement</span>
            </a>
            <a href="<?= APP_URL ?>/report/daybook" class="nav-item <?= View::activeClass('/report/daybook') ?>">
                <i class="fas fa-calendar-day nav-icon"></i>
                <span class="sidebar-label">Day Book</span>
            </a>
        </div>

        <!-- Admin -->
        <div class="nav-group">
            <div class="nav-group-label"><span>Administration</span></div>
            <a href="<?= APP_URL ?>/user" class="nav-item <?= View::activeClass('/user') ?>">
                <i class="fas fa-user-shield nav-icon"></i>
                <span class="sidebar-label">User Management</span>
            </a>
            <a href="<?= APP_URL ?>/settings" class="nav-item <?= View::activeClass('/settings') ?>">
                <i class="fas fa-cog nav-icon"></i>
                <span class="sidebar-label">Settings</span>
            </a>
            <a href="<?= APP_URL ?>/backup" class="nav-item <?= View::activeClass('/backup') ?>">
                <i class="fas fa-database nav-icon"></i>
                <span class="sidebar-label">Backup &amp; Restore</span>
            </a>
        </div>

    </div><!-- /sidebar-nav -->

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-info sidebar-label">
                <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                <span class="user-role"><?= htmlspecialchars($_SESSION['role_name'] ?? 'Administrator') ?></span>
            </div>
            <a href="<?= APP_URL ?>/auth/logout" class="user-logout sidebar-label" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>

<!-- ── Main Content ───────────────────────────────────────────── -->
<div class="qb-main" id="qbMain">

    <!-- Top Bar -->
    <header class="qb-topbar">
        <div class="topbar-left">
            <button class="topbar-hamburger d-lg-none" id="mobileSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <?php if (isset($breadcrumbs)): ?>
            <nav class="breadcrumb-nav">
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php if ($i < count($breadcrumbs) - 1): ?>
                        <a href="<?= $crumb['url'] ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                        <i class="fas fa-chevron-right"></i>
                    <?php else: ?>
                        <span><?= htmlspecialchars($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>

        <div class="topbar-right">
            <!-- Quick Actions -->
            <a href="<?= APP_URL ?>/sales/create" class="topbar-btn" title="New Sale (F2)">
                <i class="fas fa-plus"></i>
            </a>
            <!-- Date/Time -->
            <div class="topbar-datetime" id="topbarClock">
                <i class="fas fa-clock"></i>
                <span id="clockDisplay"><?= date('d/m/Y H:i') ?></span>
            </div>
            <!-- Branch -->
            <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): 
                $db = Database::getInstance();
                $all_branches = $db->fetchAll("SELECT id, name FROM branches WHERE company_id = ? AND is_active = 1 ORDER BY name", [$_SESSION['company_id']]);
            ?>
            <div class="topbar-branch dropdown">
                <button class="btn btn-sm btn-link text-white dropdown-toggle p-0 d-flex align-items-center" data-toggle="dropdown" style="text-decoration:none;font-weight:600;font-size:.9rem">
                    <i class="fas fa-map-marker-alt mr-1 text-warning"></i>
                    <span><?= htmlspecialchars($_SESSION['branch_name'] ?? 'Head Office') ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-right" style="max-height: 300px; overflow-y: auto;">
                    <div class="dropdown-header">Switch Location</div>
                    <?php foreach ($all_branches as $b): ?>
                        <a class="dropdown-item <?= $_SESSION['branch_id'] == $b['id'] ? 'active' : '' ?>" href="<?= APP_URL ?>/settings/switchBranch?id=<?= $b['id'] ?>">
                            <?= htmlspecialchars($b['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="topbar-branch">
                <i class="fas fa-map-marker-alt"></i>
                <span><?= htmlspecialchars($_SESSION['branch_name'] ?? 'Head Office') ?></span>
            </div>
            <?php endif; ?>
            <!-- Notifications -->
            <div class="topbar-notif dropdown" id="notifBtn">
                <button class="topbar-btn" data-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <span class="notif-dot" id="notifDot" style="display:none"></span>
                </button>
                <div class="dropdown-menu dropdown-menu-right notif-dropdown">
                    <div class="notif-header">Notifications</div>
                    <div id="notifList"><div class="notif-empty">No new notifications</div></div>
                </div>
            </div>
            <!-- User -->
            <div class="dropdown">
                <button class="topbar-user" data-toggle="dropdown">
                    <div class="topbar-avatar">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <span><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></span>
                    <i class="fas fa-chevron-down" style="font-size:10px;margin-left:4px"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="<?= APP_URL ?>/user/profile">
                        <i class="fas fa-user-edit"></i> My Profile
                    </a>
                    <a class="dropdown-item" href="<?= APP_URL ?>/user/changepassword">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="qb-content">
        <?= View::flash() ?>
        <?= $content ?>
    </main>

    <footer class="qb-footer">
        Rworld ERP v1.0 &nbsp;|&nbsp;
        © <?= date('Y') ?> &nbsp;|&nbsp;
        <span id="footerTime"></span>
    </footer>
</div><!-- /qb-main -->

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- jQuery, Bootstrap, App JS -->
<!-- jQuery, Bootstrap, App JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
