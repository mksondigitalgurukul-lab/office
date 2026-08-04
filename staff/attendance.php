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

    $openSession       = null;
    $lastClosedSession = null;
    $lunchTakenToday   = false;
    foreach ($sessions as $s) {
        if ($s['check_in_time'] !== null && $s['check_out_time'] === null) {
            $openSession = $s;
        }
        if ($s['check_out_time'] !== null) {
            $lastClosedSession = $s;
        }
        if ($s['break_type'] === 'lunch') {
            $lunchTakenToday = true;
        }
    }
    $onLunchBreak = !$openSession && $lastClosedSession !== null && $lastClosedSession['break_type'] === 'lunch';

    return [$todayRow, $sessions, $openSession, $lastClosedSession, $lunchTakenToday, $onLunchBreak];
}

[$todayRow, $todaySessions, $openSession, $lastClosedSession, $lunchTakenToday, $onLunchBreak] = loadToday($pdo, $staff['id'], $today);
$isFirstSessionToday = count($todaySessions) === 0;
$lunchWarningMinutes = (int) getSetting('lunch_warning_minutes', '60');
$lunchWindowStart     = getSetting('lunch_window_start_time', '12:00:00');
$lunchWindowEnd       = getSetting('lunch_window_end_time', '15:00:00');
$inLunchWindow        = date('H:i:s') >= $lunchWindowStart && date('H:i:s') <= $lunchWindowEnd;

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
    } elseif ($action === 'lunch_start') {
        if (!$openSession) {
            $error = "You're not currently checked in.";
        } elseif ($lunchTakenToday) {
            $error = "You've already taken your lunch break today.";
        } elseif (!$inLunchWindow) {
            $error = 'Lunch Start is only available between ' . date('g:i A', strtotime($lunchWindowStart)) . ' and ' . date('g:i A', strtotime($lunchWindowEnd)) . '.';
        } else {
            $stmt = $pdo->prepare('UPDATE attendance_sessions SET check_out_time = ?, check_out_ip = ?, break_type = ? WHERE id = ?');
            $stmt->execute([$now, $ip, 'lunch', $openSession['id']]);

            $stampedNote = '[Lunch started at ' . $now . ']';
            $newNotes    = trim(($todayRow['notes'] !== null && $todayRow['notes'] !== '' ? $todayRow['notes'] . "\n" : '') . $stampedNote);
            $stmt = $pdo->prepare('UPDATE attendance SET check_out_time = ?, check_out_ip = ?, notes = ? WHERE id = ?');
            $stmt->execute([$now, $ip, $newNotes, $todayRow['id']]);

            $success = 'Lunch started at ' . $now . '.';
        }
    } elseif ($action === 'lunch_over') {
        if (!$onLunchBreak) {
            $error = "You're not currently on a lunch break.";
        } else {
            if (!isOfficeIp($ip) && $staff['work_mode'] !== 'wfh' && !hasApprovedWfh($staff['id'], $today)) {
                $warning = "You don't appear to be on office WiFi for this check-in.";
            }

            $stmt = $pdo->prepare(
                'INSERT INTO attendance_sessions (attendance_id, staff_id, attendance_date, check_in_time, check_in_ip)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$todayRow['id'], $staff['id'], $today, $now, $ip]);

            $lunchMinutes = (int) round(max(0, strtotime($now) - strtotime($lastClosedSession['check_out_time'])) / 60);
            $overLimit    = $lunchMinutes > $lunchWarningMinutes;

            $stampedNote = '[Lunch ended at ' . $now . ' — ' . $lunchMinutes . 'm]' . ($overLimit ? ' — over the ' . $lunchWarningMinutes . 'm limit' : '');
            $newNotes    = trim(($todayRow['notes'] !== null && $todayRow['notes'] !== '' ? $todayRow['notes'] . "\n" : '') . $stampedNote);
            $stmt = $pdo->prepare('UPDATE attendance SET check_out_time = NULL, notes = ? WHERE id = ?');
            $stmt->execute([$newNotes, $todayRow['id']]);

            if ($overLimit) {
                $warning = trim(($warning !== '' ? $warning . ' ' : '') . "Your lunch break was {$lunchMinutes}m, longer than the {$lunchWarningMinutes}m limit.");
            }

            $success = 'Lunch ended at ' . $now . ' (' . $lunchMinutes . 'm break).';
        }
    } elseif ($action === 'check_out') {
        if ($openSession) {
            $stmt = $pdo->prepare('UPDATE attendance_sessions SET check_out_time = ?, check_out_ip = ? WHERE id = ?');
            $stmt->execute([$now, $ip, $openSession['id']]);

            $stmt = $pdo->prepare('SELECT * FROM attendance_sessions WHERE staff_id = ? AND attendance_date = ?');
            $stmt->execute([$staff['id'], $today]);
            $allSessions   = $stmt->fetchAll();
            $workedSeconds = totalWorkedSeconds($allSessions);

            $scheduledSeconds = strtotime($timing['end']) - strtotime($timing['start']);
            $status = $todayRow['status'];
            if ($scheduledSeconds > 0 && $workedSeconds < ($scheduledSeconds / 2)) {
                $status = 'half_day';
            }

            $stmt = $pdo->prepare('UPDATE attendance SET check_out_time = ?, check_out_ip = ?, status = ? WHERE id = ?');
            $stmt->execute([$now, $ip, $status, $todayRow['id']]);

            $success = 'Checked out at ' . $now . '.';
        } elseif ($onLunchBreak) {
            // Ending the day directly from a lunch break — no open session
            // to close, just finalize status against whatever's been
            // worked so far and leave a note explaining why.
            $stmt = $pdo->prepare('SELECT * FROM attendance_sessions WHERE staff_id = ? AND attendance_date = ?');
            $stmt->execute([$staff['id'], $today]);
            $allSessions   = $stmt->fetchAll();
            $workedSeconds = totalWorkedSeconds($allSessions);

            $scheduledSeconds = strtotime($timing['end']) - strtotime($timing['start']);
            $status = $todayRow['status'];
            if ($scheduledSeconds > 0 && $workedSeconds < ($scheduledSeconds / 2)) {
                $status = 'half_day';
            }

            $stampedNote = '[Ended day directly from lunch break at ' . $now . ']';
            $newNotes    = trim(($todayRow['notes'] !== null && $todayRow['notes'] !== '' ? $todayRow['notes'] . "\n" : '') . $stampedNote);
            $stmt = $pdo->prepare('UPDATE attendance SET status = ?, notes = ? WHERE id = ?');
            $stmt->execute([$status, $newNotes, $todayRow['id']]);

            $success = 'Checked out at ' . $now . ' (ended day from lunch break).';
        } else {
            $error = $isFirstSessionToday
                ? "You haven't checked in today."
                : 'You\'ve already checked out. Use "Check In Again" below if you\'re resuming work today.';
        }
    }

    [$todayRow, $todaySessions, $openSession, $lastClosedSession, $lunchTakenToday, $onLunchBreak] = loadToday($pdo, $staff['id'], $today);
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

$lunchElapsedMinutes = null;
if ($onLunchBreak) {
    $lunchElapsedMinutes = (int) round(max(0, strtotime(date('H:i:s')) - strtotime($lastClosedSession['check_out_time'])) / 60);
}

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
        <div class="table-actions">
          <form method="post">
            <input type="hidden" name="action" value="check_out">
            <button type="submit" class="btn">Check Out</button>
          </form>
          <?php if (!$lunchTakenToday && $inLunchWindow): ?>
            <form method="post">
              <input type="hidden" name="action" value="lunch_start">
              <button type="submit" class="btn btn-secondary">Lunch Start</button>
            </form>
          <?php elseif (!$lunchTakenToday): ?>
            <span style="color:var(--color-text-muted); align-self:center;">Lunch Start available <?= h(date('g:i A', strtotime($lunchWindowStart))) ?>&ndash;<?= h(date('g:i A', strtotime($lunchWindowEnd))) ?></span>
          <?php endif; ?>
        </div>
      <?php elseif ($onLunchBreak): ?>
        <p>
          On lunch break since <strong><?= h($lastClosedSession['check_out_time']) ?></strong>
          — <?= (int) $lunchElapsedMinutes ?>m so far
          <?php if ($lunchElapsedMinutes > $lunchWarningMinutes): ?>
            <span class="badge badge-warning">Over the <?= (int) $lunchWarningMinutes ?>m limit</span>
          <?php endif; ?>
        </p>
        <div class="table-actions">
          <form method="post">
            <input type="hidden" name="action" value="lunch_over">
            <button type="submit" class="btn">Lunch Over</button>
          </form>
          <form method="post">
            <input type="hidden" name="action" value="check_out">
            <button type="submit" class="btn btn-secondary">Check Out (end day)</button>
          </form>
        </div>
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
                <?php if ($s['break_type'] === 'lunch'): ?>
                  <tr>
                    <td></td>
                    <td colspan="3" style="color:var(--color-text-muted); font-style:italic;">
                      <?php if (isset($todaySessions[$i + 1])): ?>
                        Lunch break — <?= (int) round(max(0, strtotime($todaySessions[$i + 1]['check_in_time']) - strtotime($s['check_out_time'])) / 60) ?>m
                      <?php else: ?>
                        Lunch break — ongoing (<?= (int) $lunchElapsedMinutes ?>m so far)
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
