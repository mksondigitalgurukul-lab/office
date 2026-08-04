<?php
/**
 * Shared staff sidebar chrome (opening half — pairs with staff-footer.php).
 *
 * Caller must set before requiring this file:
 *   $pageTitle  string  Page title (topbar + <title>)
 *   $activeNav  string  Nav item key to highlight (see $navItems below)
 *   $staff      array   The logged-in staff row (from `staff` table)
 *
 * All staff pages live at the same depth (/staff/*.php), so links are
 * always relative to that folder — no $basePath needed here.
 */

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'attendance', 'label' => 'Attendance', 'href' => 'attendance.php'],
    ['key' => 'leave', 'label' => 'Leave', 'href' => 'leave.php'],
    ['key' => 'wfh', 'label' => 'WFH', 'href' => 'wfh.php'],
    ['key' => 'payout', 'label' => 'Payout', 'href' => 'payout.php'],
    ['key' => 'profile', 'label' => 'Profile', 'href' => 'profile.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
(function(){var t=localStorage.getItem('theme');if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);})();
</script>
<title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <a href="dashboard.php" class="brand-mark">
        <span class="brand-logo">D</span>
        <span class="brand-name"><?= h(APP_NAME) ?></span>
      </a>
      <button type="button" class="sidebar-close no-print" id="sidebarClose" aria-label="Close menu">&times;</button>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-group">
        <div class="nav-group-label">Menu</div>
        <?php foreach ($navItems as $item): ?>
          <a href="<?= h($item['href']) ?>" class="nav-link<?= $activeNav === $item['key'] ? ' active' : '' ?>"><?= h($item['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </nav>
    <div class="sidebar-footer">
      <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4" y1="12" x2="2" y2="12"/><line x1="22" y1="12" x2="20" y2="12"/><line x1="19.07" y1="4.93" x2="17.66" y2="6.34"/><line x1="6.34" y1="17.66" x2="4.93" y2="19.07"/><line x1="19.07" y1="19.07" x2="17.66" y2="17.66"/><line x1="6.34" y1="6.34" x2="4.93" y2="4.93"/></svg>
        <svg class="icon-moon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        <span>Toggle theme</span>
      </button>
      <div class="sidebar-user">
        <div class="user-avatar"><?= h(strtoupper(substr($staff['full_name'], 0, 1))) ?></div>
        <div class="user-info">
          <div class="user-name"><?= h($staff['full_name']) ?></div>
          <div class="user-role"><?= h($staff['work_mode']) ?></div>
        </div>
      </div>
      <a href="logout.php" class="btn-logout">Log out</a>
    </div>
  </aside>
  <div class="sidebar-overlay no-print" id="sidebarOverlay"></div>

  <div class="app-main">
    <header class="topbar no-print">
      <button type="button" class="hamburger" id="hamburgerBtn" aria-label="Open menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <h1 class="page-title"><?= h($pageTitle) ?></h1>
    </header>
    <main class="app-content">
