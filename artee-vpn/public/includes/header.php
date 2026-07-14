<?php
// ============================================================
//  ARTEE VPN — Layout Header Template
//  PHP 7.0.1 + MySQL Compatible
// ============================================================
require_once __DIR__ . '/../../app/helpers/auth.php';
require_auth();
$user = current_user();

if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
if (!isset($active_nav)) {
    $active_nav = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo e($page_title); ?> — Artee VPN</title>
  <meta name="robots" content="noindex" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="app-body">

<!-- ── Sidebar ──────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="logo-icon">⬡</span>
    <span class="logo-text">Artee <strong>VPN</strong></span>
  </div>

  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item <?php echo $active_nav === 'dashboard' ? 'active' : ''; ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="peers.php" class="nav-item <?php echo $active_nav === 'peers' ? 'active' : ''; ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a8.38 8.38 0 0 1 13 0"/></svg>
      Peers
    </a>
    <a href="setup_keys.php" class="nav-item <?php echo $active_nav === 'setup_keys' ? 'active' : ''; ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
      Setup Keys
    </a>
    <a href="users.php" class="nav-item <?php echo $active_nav === 'users' ? 'active' : ''; ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Users
    </a>
    <a href="routes.php" class="nav-item <?php echo $active_nav === 'routes' ? 'active' : ''; ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Routes
    </a>
    <a href="activity.php" class="nav-item <?php echo $active_nav === 'activity' ? 'active' : ''; ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Activity Log
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
      <div class="user-info">
        <span class="user-name"><?php echo e($user['name']); ?></span>
        <span class="user-role"><?php echo e($user['role']); ?></span>
      </div>
    </div>
    <a href="logout.php" class="logout-btn" title="Sign out">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
    </a>
  </div>
</aside>

<!-- ── Main Content ──────────────────────────────────────── -->
<main class="main-content">

  <!-- Top Bar -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" onclick="toggleSidebar()" id="sidebar-toggle">☰</button>
      <div>
        <h1 class="page-title"><?php echo e($page_title); ?></h1>
        <p class="page-subtitle"><?php echo isset($page_subtitle) ? e($page_subtitle) : 'Artee VPN Administration Console'; ?></p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="status-badge status-online">
        <span class="status-dot"></span>
        VPN Online
      </div>
      <span class="topbar-time" id="clock"></span>
    </div>
  </header>

  <div class="content-pad">
