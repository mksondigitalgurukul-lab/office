<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaffLogin('login.php');
$staffSession = currentStaff();
$pdo = getDB();

$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$staffSession['id']]);
$staff = $stmt->fetch();

if (!$staff || $staff['status'] !== 'active') {
    logoutStaff();
    header('Location: login.php');
    exit;
}

$today       = date('Y-m-d');
$holidayName = getHolidayName($today);

$stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?');
$stmt->execute([$staff['id'], $today]);
$todayRow = $stmt->fetch();

$error   = '';
$warning = '';
$success = '';

if (!$holidayName && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ip     = getClientIp();
    $now    = date('H:i:s');
    $timing = getCurrentWorkTiming($staff['id'], $today);

    if ($action === 'check_in') {
        if ($todayRow && $todayRow['check_in_time'] !== null) {
            $error = 'You already checked in today.';
        } else {
            if (isOfficeIp($ip)) {
                $location = 'office_verified';
            } elseif ($staff['work_mode'] === 'wfh') {
                $location = 'wfh';
            } else {
                $location = 'unverified';
                $warning  = "You don't appear to be on office WiFi — this check-in will be flagged for admin review.";
            }

            $status = computeCheckInStatus($timing['start'], $now, getAttendanceGraceMinutes());

            if ($todayRow) {
                $stmt = $pdo->prepare(
                    'UPDATE attendance SET check_in_time = ?, check_in_ip = ?, work_location = ?, status = ? WHERE id = ?'
                );
                $stmt->execute([$now, $ip, $location, $status, $todayRow['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO attendance (staff_id, attendance_date, check_in_time, check_in_ip, work_location, status)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$staff['id'], $today, $now, $ip, $location, $status]);
            }

            $success = 'Checked in at ' . $now . '.';
            $stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?');
            $stmt->execute([$staff['id'], $today]);
            $todayRow = $stmt->fetch();
        }
    } elseif ($action === 'check_out') {
        if (!$todayRow || $todayRow['check_in_time'] === null) {
            $error = "You haven't checked in today.";
        } elseif ($todayRow['check_out_time'] !== null) {
            $error = 'You already checked out today.';
        } else {
            $status = $todayRow['status'];
            if (isHalfDay($timing['start'], $timing['end'], $todayRow['check_in_time'], $now)) {
                $status = 'half_day';
            }

            $stmt = $pdo->prepare('UPDATE attendance SET check_out_time = ?, check_out_ip = ?, status = ? WHERE id = ?');
            $stmt->execute([$now, $ip, $status, $todayRow['id']]);

            $success = 'Checked out at ' . $now . '.';
            $stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?');
            $stmt->execute([$staff['id'], $today]);
            $todayRow = $stmt->fetch();
        }
    }
}

$statusLabels = [
    'present'  => 'Present',
    'late'     => 'Late',
    'half_day' => 'Half Day',
    'absent'   => 'Absent',
];
$locationLabels = [
    'office_verified' => 'Office (WiFi verified)',
    'office_manual'   => 'Office (manual)',
    'wfh'              => 'Work From Home',
    'unverified'       => 'Unverified location',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><?= h(APP_NAME) ?></div>
  <div class="user-info">
    <span><?= h($staff['full_name']) ?></span>
    <a href="logout.php">Log out</a>
  </div>
</div>

<nav class="nav">
  <a href="dashboard.php">Dashboard</a>
  <a href="attendance.php"><strong>Attendance</strong></a>
  <a href="leave.php">Leave / WFH</a>
  <a href="payout.php">Payout</a>
  <a href="profile.php">Profile</a>
</nav>

<div class="container">
  <h1>Attendance — <?= h(date('l, j F Y', strtotime($today))) ?></h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($warning): ?>
    <div class="alert alert-error"><?= h($warning) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:480px;">
    <?php if ($holidayName): ?>
      <p style="margin:0;"><strong>Holiday today</strong> — <?= h($holidayName) ?>. No check-in required.</p>
    <?php elseif (!$todayRow || $todayRow['check_in_time'] === null): ?>
      <p>Not checked in yet.</p>
      <form method="post">
        <input type="hidden" name="action" value="check_in">
        <button type="submit" class="btn">Check In</button>
      </form>
    <?php else: ?>
      <p>
        Checked in at <strong><?= h($todayRow['check_in_time']) ?></strong>
        (<?= h($locationLabels[$todayRow['work_location']] ?? $todayRow['work_location']) ?>)
        — <span class="badge badge-<?= h($todayRow['status']) ?>"><?= h($statusLabels[$todayRow['status']] ?? $todayRow['status']) ?></span>
      </p>
      <?php if ($todayRow['check_out_time'] === null): ?>
        <form method="post">
          <input type="hidden" name="action" value="check_out">
          <button type="submit" class="btn">Check Out</button>
        </form>
      <?php else: ?>
        <p style="margin-bottom:0;">Checked out at <strong><?= h($todayRow['check_out_time']) ?></strong>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
