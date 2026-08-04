<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('login.php');
$admin = currentAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><?= h(APP_NAME) ?></div>
  <div class="user-info">
    <span><?= h($admin['name']) ?> (<?= h($admin['role']) ?>)</span>
    <a href="logout.php">Log out</a>
  </div>
</div>

<nav class="nav">
  <a href="staff/index.php">Staff</a>
  <a href="attendance/index.php">Attendance</a>
  <a href="leave/index.php">Leave</a>
  <a href="wfh/index.php">WFH</a>
  <a href="leave-types/index.php">Leave Types</a>
  <a href="office-locations/index.php">Office Locations</a>
  <a href="payout/index.php">Payout</a>
  <a href="reports/attendance.php">Reports</a>
  <a href="settings.php">Settings</a>
  <a href="../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <div class="welcome-box">
    <h1>Welcome, <?= h($admin['name']) ?></h1>
    <p>This is the <?= h(APP_NAME) ?> admin dashboard — use <a href="staff/index.php">Staff</a> to manage employees, <a href="payout/index.php">Payout</a> to generate monthly payouts, <a href="reports/attendance.php">Reports</a> for attendance summaries, or <a href="../sql/index.php">DB Tools</a> to inspect the current database schema.</p>
  </div>
</div>
</body>
</html>
