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
  <a href="staff.php">Staff</a>
  <a href="attendance.php">Attendance</a>
  <a href="leave.php">Leave</a>
  <a href="payout.php">Payout</a>
  <a href="reports.php">Reports</a>
  <a href="settings.php">Settings</a>
  <a href="../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <div class="welcome-box">
    <h1>Welcome, <?= h($admin['name']) ?></h1>
    <p>This is the <?= h(APP_NAME) ?> admin dashboard. Staff, attendance, leave, payout and reports modules will be added in upcoming builds — use <a href="../sql/index.php">DB Tools</a> to inspect the current database schema.</p>
  </div>
</div>
</body>
</html>
