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

$today = date('Y-m-d');

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthStart = new DateTime($month . '-01');
$monthEnd   = new DateTime($monthStart->format('Y-m-t'));
$prevMonth  = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth  = (clone $monthStart)->modify('+1 month')->format('Y-m');

$startStr = $monthStart->format('Y-m-d');
$endStr   = $monthEnd->format('Y-m-d');

$stmt = $pdo->prepare('SELECT holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ?');
$stmt->execute([$startStr, $endStr]);
$holidaysByDate = [];
foreach ($stmt->fetchAll() as $h) {
    $holidaysByDate[$h['holiday_date']] = $h['name'];
}

$stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date BETWEEN ? AND ?');
$stmt->execute([$staff['id'], $startStr, $endStr]);
$attendanceByDate = [];
foreach ($stmt->fetchAll() as $a) {
    $attendanceByDate[$a['attendance_date']] = $a;
}

$stmt = $pdo->prepare('SELECT * FROM attendance_sessions WHERE staff_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY id');
$stmt->execute([$staff['id'], $startStr, $endStr]);
$sessionsByDate = [];
foreach ($stmt->fetchAll() as $s) {
    $sessionsByDate[$s['attendance_date']][] = $s;
}

$statusLabels = ['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half Day', 'absent' => 'Absent', 'on_leave' => 'On Leave'];
$qualityLabels = [
    'below_target'   => 'Below Target',
    'on_target'      => 'On Target',
    'great_work'     => 'Great Work',
    'excellent_work' => 'Excellent Work',
];

$rows = [];
$monthlyWorkedSeconds = 0;
$cursor = clone $monthStart;
while ($cursor <= $monthEnd) {
    $dateStr = $cursor->format('Y-m-d');
    if ($dateStr <= $today) {
        $attendance = $attendanceByDate[$dateStr] ?? null;
        $sessions   = $sessionsByDate[$dateStr] ?? [];

        // Legacy days (before attendance_sessions existed) have no session
        // rows yet — fall back to the summary row as a single session.
        if (!$sessions && $attendance && $attendance['check_in_time'] !== null) {
            $sessions = [[
                'check_in_time'  => $attendance['check_in_time'],
                'check_out_time' => $attendance['check_out_time'],
                'recheckin_reason' => null,
            ]];
        }

        $workedSeconds = totalWorkedSeconds($sessions);
        $monthlyWorkedSeconds += $workedSeconds;

        $quality = null;
        if ($attendance && in_array($attendance['status'], ['present', 'late'], true)) {
            $timing = getCurrentWorkTiming($staff['id'], $dateStr);
            $scheduledSeconds = strtotime($timing['end']) - strtotime($timing['start']);
            $quality = workQualityLabel($workedSeconds, $scheduledSeconds);
        }

        $rows[] = [
            'date'       => $dateStr,
            'day'        => $cursor->format('D'),
            'holiday'    => $holidaysByDate[$dateStr] ?? null,
            'attendance' => $attendance,
            'sessions'   => $sessions,
            'workedSeconds' => $workedSeconds,
            'quality'    => $quality,
        ];
    }
    $cursor->modify('+1 day');
}

$pageTitle = 'Work Report';
$activeNav = 'work-report';
require __DIR__ . '/../includes/staff-header.php';
?>
  <div class="toolbar">
    <h1 style="margin:0;">Work Report — <?= h($monthStart->format('F Y')) ?></h1>
    <div class="table-actions">
      <a href="work-report.php?month=<?= h($prevMonth) ?>" class="btn btn-sm btn-secondary">&larr; Previous</a>
      <a href="work-report.php?month=<?= h(date('Y-m')) ?>" class="btn btn-sm btn-secondary">This Month</a>
      <a href="work-report.php?month=<?= h($nextMonth) ?>" class="btn btn-sm btn-secondary">Next &rarr;</a>
    </div>
  </div>

  <p style="color:var(--color-text-muted);">Total worked this month so far: <strong><?= h(formatWorkedSeconds($monthlyWorkedSeconds)) ?></strong>.
    "Great Work"/"Excellent Work" mean you worked noticeably more than your scheduled hours that day; "Below Target" means noticeably less (but still over half a shift — under that, it's a Half Day). See <code>workQualityLabel()</code> in the codebase for the exact thresholds.</p>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Date</th><th>Day</th><th>Holiday</th><th>Status</th><th>Sessions</th><th>Worked</th><th>Quality</th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" style="color:var(--color-text-muted);">No days to show yet this month.</td></tr>
        <?php endif; ?>
        <?php foreach (array_reverse($rows) as $row): ?>
          <tr class="<?= $row['date'] === $today ? 'row-flag' : '' ?>">
            <td><?= h($row['date']) ?><?= $row['date'] === $today ? ' <strong>(Today)</strong>' : '' ?></td>
            <td><?= h($row['day']) ?></td>
            <td><?= $row['holiday'] ? h($row['holiday']) : '—' ?></td>
            <td>
              <?php if ($row['attendance']): ?>
                <span class="badge badge-<?= badgeVariant($row['attendance']['status']) ?>"><?= h($statusLabels[$row['attendance']['status']] ?? $row['attendance']['status']) ?></span>
              <?php else: ?>
                <span style="color:var(--color-text-muted);">No record</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($row['sessions']): ?>
                <?php foreach ($row['sessions'] as $s): ?>
                  <div><?= h($s['check_in_time']) ?> &ndash; <?= $s['check_out_time'] ? h($s['check_out_time']) : 'in progress' ?><?= !empty($s['recheckin_reason']) ? ' <span style="color:var(--color-text-muted);">(' . h($s['recheckin_reason']) . ')</span>' : '' ?></div>
                <?php endforeach; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td><?= $row['workedSeconds'] > 0 ? h(formatWorkedSeconds($row['workedSeconds'])) : '—' ?></td>
            <td>
              <?php if ($row['quality']): ?>
                <span class="badge badge-<?= badgeVariant($row['quality']) ?>"><?= h($qualityLabels[$row['quality']]) ?></span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
