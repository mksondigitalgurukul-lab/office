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
$isSunday    = (int) date('N', strtotime($today)) === 7;
$blocked     = $holidayName && !$isSunday;
$extraWork   = $holidayName && $isSunday;

$stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?');
$stmt->execute([$staff['id'], $today]);
$todayRow = $stmt->fetch();

$error   = '';
$warning = '';
$success = '';

if (!$blocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
            } elseif (hasApprovedWfh($staff['id'], $today)) {
                $location = 'wfh';
            } else {
                $location = 'unverified';
                $warning  = "You don't appear to be on office WiFi — this check-in will be flagged for admin review.";
            }

            $status = computeCheckInStatus($timing['start'], $now, getAttendanceGraceMinutes());
            $notes  = $extraWork ? 'Extra work — checked in on a Sunday (' . $holidayName . ').' : null;

            if ($todayRow) {
                $stmt = $pdo->prepare(
                    'UPDATE attendance SET check_in_time = ?, check_in_ip = ?, work_location = ?, status = ?, notes = ? WHERE id = ?'
                );
                $stmt->execute([$now, $ip, $location, $status, $notes ?? $todayRow['notes'], $todayRow['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO attendance (staff_id, attendance_date, check_in_time, check_in_ip, work_location, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$staff['id'], $today, $now, $ip, $location, $status, $notes]);
            }

            $success = $extraWork ? 'Checked in at ' . $now . ' — logged as extra Sunday work.' : 'Checked in at ' . $now . '.';
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
    'on_leave' => 'On Leave',
];
$locationLabels = [
    'office_verified' => 'Office (WiFi verified)',
    'office_manual'   => 'Office (manual)',
    'wfh'              => 'Work From Home',
    'unverified'       => 'Unverified location',
];
$pageTitle = 'Attendance';
$activeNav = 'attendance';
require __DIR__ . '/../includes/staff-header.php';
?>
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
    <?php if ($blocked): ?>
      <p style="margin:0;"><strong>Holiday today</strong> — <?= h($holidayName) ?>. No check-in required.</p>
    <?php else: ?>
      <?php if ($extraWork): ?>
        <p><strong>Sunday</strong> — default day off (<?= h($holidayName) ?>). You can still check in below if you're working today; it'll be logged as extra work.</p>
      <?php endif; ?>
      <?php if (!$todayRow || $todayRow['check_in_time'] === null): ?>
      <p>Not checked in yet.</p>
      <form method="post">
        <input type="hidden" name="action" value="check_in">
        <button type="submit" class="btn"><?= $extraWork ? 'Check In (Extra Work)' : 'Check In' ?></button>
      </form>
    <?php else: ?>
      <p>
        Checked in at <strong><?= h($todayRow['check_in_time']) ?></strong>
        (<?= h($locationLabels[$todayRow['work_location']] ?? $todayRow['work_location']) ?>)
        — <span class="badge badge-<?= badgeVariant($todayRow['status']) ?>"><?= h($statusLabels[$todayRow['status']] ?? $todayRow['status']) ?></span>
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
    <?php endif; ?>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
