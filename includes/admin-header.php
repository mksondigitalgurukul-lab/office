<?php
/**
 * Shared admin sidebar chrome (opening half — pairs with admin-footer.php).
 *
 * Caller must set before requiring this file:
 *   $pageTitle  string  Page title (topbar + <title>)
 *   $activeNav  string  Nav item key to highlight (see $navItems below)
 *   $basePath   string  Relative path back to /admin/ — '' from admin/*.php,
 *                       '../' from admin/*\/*.php
 *   $admin      array   currentAdmin() result (already required by every page)
 */

$assetPath = $basePath . '../';

$navItems = [
    'Overview' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => $basePath . 'dashboard.php'],
    ],
    'People' => [
        ['key' => 'staff', 'label' => 'Staff', 'href' => $basePath . 'staff/index.php'],
        ['key' => 'attendance', 'label' => 'Attendance', 'href' => $basePath . 'attendance/index.php'],
        ['key' => 'leave', 'label' => 'Leave', 'href' => $basePath . 'leave/index.php'],
        ['key' => 'wfh', 'label' => 'WFH', 'href' => $basePath . 'wfh/index.php'],
    ],
    'Money' => [
        ['key' => 'payout', 'label' => 'Payout', 'href' => $basePath . 'payout/index.php'],
        ['key' => 'reports', 'label' => 'Reports', 'href' => $basePath . 'reports/attendance.php'],
    ],
    'Admin' => [
        ['key' => 'leave-types', 'label' => 'Leave Types', 'href' => $basePath . 'leave-types/index.php'],
        ['key' => 'holidays', 'label' => 'Holidays', 'href' => $basePath . 'holidays/index.php'],
        ['key' => 'office-locations', 'label' => 'Office Locations', 'href' => $basePath . 'office-locations/index.php'],
        ['key' => 'settings', 'label' => 'Settings', 'href' => $basePath . 'settings.php'],
        ['key' => 'db-tools', 'label' => 'DB Tools', 'href' => $assetPath . 'sql/index.php'],
    ],
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
<link rel="stylesheet" href="<?= h($assetPath) ?>assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <a href="<?= h($basePath) ?>dashboard.php" class="brand-mark">
        <span class="brand-logo">D</span>
        <span class="brand-name"><?= h(APP_NAME) ?></span>
      </a>
      <button type="button" class="sidebar-close no-print" id="sidebarClose" aria-label="Close menu">&times;</button>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($navItems as $groupLabel => $items): ?>
        <div class="nav-group">
          <div class="nav-group-label"><?= h($groupLabel) ?></div>
          <?php foreach ($items as $item): ?>
            <a href="<?= h($item['href']) ?>" class="nav-link<?= $activeNav === $item['key'] ? ' active' : '' ?>"><?= h($item['label']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4" y1="12" x2="2" y2="12"/><line x1="22" y1="12" x2="20" y2="12"/><line x1="19.07" y1="4.93" x2="17.66" y2="6.34"/><line x1="6.34" y1="17.66" x2="4.93" y2="19.07"/><line x1="19.07" y1="19.07" x2="17.66" y2="17.66"/><line x1="6.34" y1="6.34" x2="4.93" y2="4.93"/></svg>
        <svg class="icon-moon" viewBox="0 0 24 24" fill="currentColor"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        <span>Toggle theme</span>
      </button>
      <div class="sidebar-user">
        <div class="user-avatar"><?= h(strtoupper(substr($admin['name'], 0, 1))) ?></div>
        <div class="user-info">
          <div class="user-name"><?= h($admin['name']) ?></div>
          <div class="user-role"><?= h($admin['role']) ?></div>
        </div>
      </div>
      <a href="<?= h($basePath) ?>logout.php" class="btn-logout">Log out</a>
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
<?php if (empty($bootstrapping)): ?>
  <?php $absentSyncResult = syncAbsences(); ?>
  <?php if ($absentSyncResult): ?>
    <div class="alert alert-success no-print">
      Attendance sync: marked <?= (int) $absentSyncResult['marked'] ?> absent, <?= (int) $absentSyncResult['on_leave'] ?> on leave, for <?= h($absentSyncResult['from']) ?> to <?= h($absentSyncResult['to']) ?> (no cron job needed — this runs automatically when an admin visits any page).
    </div>
  <?php endif; ?>
<?php endif; ?>
