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

function loadToday(PDO $pdo, int $staffId, string $today): array
{
    $stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?');
    $stmt->execute([$staffId, $today]);
    $todayRow = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM attendance_sessions WHERE staff_id = ? AND attendance_date = ? ORDER BY id');
    $stmt->execute([$staffId, $today]);
    $sessions = $stmt->fetchAll();

    $openSession = null;
    foreach ($sessions as $s) {
        if ($s['check_in_time'] !== null && $s['check_out_time'] === null) {
            $openSession = $s;
        }
    }

    return [$todayRow, $sessions, $openSession];
}

[$todayRow, $todaySessions, $openSession] = loadToday($pdo, $staff['id'], $today);
$isFirstSessionToday = count($todaySessions) === 0;

$error   = '';
$warning = '';
$success = '';

if (!$blocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ip     = getClientIp();
    $now    = date('H:i:s');
    $timing = getCurrentWorkTiming($staff['id'], $today);

    if ($action === 'check_in') {
        if ($openSession) {
            $error = 'You already checked in today.';
        } elseif ($isFirstSessionToday) {
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
                $attendanceId = $todayRow['id'];
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO attendance (staff_id, attendance_date, check_in_time, check_in_ip, work_location, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$staff['id'], $today, $now, $ip, $location, $status, $notes]);
                $attendanceId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare(
                'INSERT INTO attendance_sessions (attendance_id, staff_id, attendance_date, check_in_time, check_in_ip)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$attendanceId, $staff['id'], $today, $now, $ip]);

            $success = $extraWork ? 'Checked in at ' . $now . ' — logged as extra Sunday work.' : 'Checked in at ' . $now . '.';
        } else {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') {
                $error = 'Enter a reason for checking in again today.';
            } else {
                if (!isOfficeIp($ip) && $staff['work_mode'] !== 'wfh' && !hasApprovedWfh($staff['id'], $today)) {
                    $warning = "You don't appear to be on office WiFi for this check-in.";
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO attendance_sessions (attendance_id, staff_id, attendance_date, check_in_time, check_in_ip, recheckin_reason)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$todayRow['id'], $staff['id'], $today, $now, $ip, $reason]);

                $stampedNote = '[Re-checked-in at ' . $now . '] ' . $reason;
                $newNotes    = trim(($todayRow['notes'] !== null && $todayRow['notes'] !== '' ? $todayRow['notes'] . "\n" : '') . $stampedNote);
                $stmt = $pdo->prepare('UPDATE attendance SET check_out_time = NULL, notes = ? WHERE id = ?');
                $stmt->execute([$newNotes, $todayRow['id']]);

                $success = 'Checked in again at ' . $now . '.';
            }
        }
    } elseif ($action === 'check_out') {
        if (!$openSession) {
            $error = $isFirstSessionToday
                ? "You haven't checked in today."
                : 'You\'ve already checked out. Use "Check In Again" below if you\'re resuming work today.';
        } else {
            $stmt = $pdo->prepare('UPDATE attendance_sessions SET check_out_time = ?, check_out_ip = ? WHERE id = ?');
            $stmt->execute([$now, $ip, $openSession['id']]);

            $stmt = $pdo->prepare('SELECT * FROM attendance_sessions WHERE staff_id = ? AND attendance_date = ?');
            $stmt->execute([$staff['id'], $today]);
            $allSessions = $stmt->fetchAll();
            $workedSeconds = totalWorkedSeconds($allSessions);

            $scheduledSeconds = strtotime($timing['end']) - strtotime($timing['start']);
            $status = $todayRow['status'];
            if ($scheduledSeconds > 0 && $workedSeconds < ($scheduledSeconds / 2)) {
                $status = 'half_day';
            }

            $stmt = $pdo->prepare('UPDATE attendance SET check_out_time = ?, check_out_ip = ?, status = ? WHERE id = ?');
            $stmt->execute([$now, $ip, $status, $todayRow['id']]);

            $success = 'Checked out at ' . $now . '.';
        }
    }

    [$todayRow, $todaySessions, $openSession] = loadToday($pdo, $staff['id'], $today);
    $isFirstSessionToday = count($todaySessions) === 0;
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
$todayWorkedSeconds = totalWorkedSeconds($todaySessions);
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

      <?php if ($todayRow): ?>
        <p>
          Today's status:
          <span class="badge badge-<?= badgeVariant($todayRow['status']) ?>"><?= h($statusLabels[$todayRow['status']] ?? $todayRow['status']) ?></span>
          (<?= h($locationLabels[$todayRow['work_location']] ?? $todayRow['work_location']) ?>)
          <?php if ($todayWorkedSeconds > 0): ?>
            — <?= h(formatWorkedSeconds($todayWorkedSeconds)) ?> worked so far
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if ($openSession): ?>
        <p>Checked in at <strong><?= h($openSession['check_in_time']) ?></strong>.</p>
        <form method="post">
          <input type="hidden" name="action" value="check_out">
          <button type="submit" class="btn">Check Out</button>
        </form>
      <?php elseif ($isFirstSessionToday): ?>
        <p>Not checked in yet.</p>
        <form method="post">
          <input type="hidden" name="action" value="check_in">
          <button type="submit" class="btn"><?= $extraWork ? 'Check In (Extra Work)' : 'Check In' ?></button>
        </form>
      <?php else: ?>
        <p>Checked out — resuming work today?</p>
        <form method="post">
          <input type="hidden" name="action" value="check_in">
          <div class="field">
            <label for="reason">Reason for checking in again</label>
            <textarea id="reason" name="reason" rows="2" required placeholder="e.g. plan changed, resuming work"></textarea>
          </div>
          <button type="submit" class="btn">Check In Again</button>
        </form>
      <?php endif; ?>

      <?php if ($todaySessions): ?>
        <h3 style="margin-bottom:8px;">Today's Sessions</h3>
        <div class="overflow-x">
          <table class="db-table">
            <thead><tr><th>#</th><th>Check In</th><th>Check Out</th><th>Reason</th></tr></thead>
            <tbody>
              <?php foreach ($todaySessions as $i => $s): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= h($s['check_in_time']) ?></td>
                  <td><?= $s['check_out_time'] ? h($s['check_out_time']) : 'In progress' ?></td>
                  <td><?= $s['recheckin_reason'] ? h($s['recheckin_reason']) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
