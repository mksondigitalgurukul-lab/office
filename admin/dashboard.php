<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('login.php');
$admin = currentAdmin();
$pdo   = getDB();

$today       = date('Y-m-d');
$holidayName = getHolidayName($today);

$totalActiveStaff = (int) $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT
        SUM(status IN ('present', 'late')) AS present,
        SUM(status = 'absent') AS absent,
        SUM(status = 'on_leave') AS on_leave
     FROM attendance WHERE attendance_date = ?"
);
$stmt->execute([$today]);
$todayCounts = $stmt->fetch();
$presentToday  = (int) ($todayCounts['present'] ?? 0);
$absentToday   = (int) ($todayCounts['absent'] ?? 0);
$onLeaveToday  = (int) ($todayCounts['on_leave'] ?? 0);
$notRecorded   = max(0, $totalActiveStaff - $presentToday - $absentToday - $onLeaveToday);

$pendingLeave = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
$pendingWfh   = (int) $pdo->query("SELECT COUNT(*) FROM wfh_requests WHERE status = 'pending'")->fetchColumn();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$basePath  = '';
require __DIR__ . '/../includes/admin-header.php';
?>
  <div class="welcome-box">
    <h1>Welcome, <?= h($admin['name']) ?></h1>
    <p><?= h(date('l, j F Y', strtotime($today))) ?><?= $holidayName ? ' — Holiday: ' . h($holidayName) : '' ?></p>
  </div>

  <h2 style="margin-top:0;">Today's Attendance</h2>
  <div class="stat-grid">
    <div class="stat-card accent-primary">
      <div class="stat-label">Active Staff</div>
      <div class="stat-value"><?= $totalActiveStaff ?></div>
    </div>
    <div class="stat-card accent-success">
      <div class="stat-label">Present Today</div>
      <div class="stat-value"><?= $presentToday ?></div>
    </div>
    <div class="stat-card accent-danger">
      <div class="stat-label">Absent Today</div>
      <div class="stat-value"><?= $absentToday ?></div>
    </div>
    <div class="stat-card accent-info">
      <div class="stat-label">On Leave Today</div>
      <div class="stat-value"><?= $onLeaveToday ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Not Yet Recorded</div>
      <div class="stat-value"><?= $notRecorded ?></div>
    </div>
  </div>

  <h2>Needs Review</h2>
  <div class="stat-grid">
    <div class="stat-card accent-accent">
      <div class="stat-label">Pending Leave Requests</div>
      <div class="stat-value"><?= $pendingLeave ?></div>
      <div class="stat-sub"><a href="leave/index.php?status=pending">Review &rarr;</a></div>
    </div>
    <div class="stat-card accent-accent">
      <div class="stat-label">Pending WFH Requests</div>
      <div class="stat-value"><?= $pendingWfh ?></div>
      <div class="stat-sub"><a href="wfh/index.php?status=pending">Review &rarr;</a></div>
    </div>
  </div>

  <h2>Quick Links</h2>
  <div class="quick-links">
    <a href="staff/add.php" class="quick-link">+ Add Staff</a>
    <a href="attendance/index.php" class="quick-link">View Today's Attendance</a>
    <a href="holidays/index.php" class="quick-link">Manage Holidays</a>
    <a href="payout/generate.php" class="quick-link">Generate Payout</a>
    <a href="reports/attendance.php" class="quick-link">Attendance Reports</a>
    <a href="../sql/index.php" class="quick-link">DB Tools</a>
  </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
