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

$view = $_GET['view'] ?? 'month';
if (!in_array($view, ['week', 'month', 'year'], true)) {
    $view = 'month';
}

$anchor = $_GET['date'] ?? $today;
if (!DateTime::createFromFormat('Y-m-d', $anchor)) {
    $anchor = $today;
}
$anchorDt = new DateTime($anchor);

if ($view === 'week') {
    $dow   = (int) $anchorDt->format('N'); // 1 (Mon) .. 7 (Sun)
    $start = (clone $anchorDt)->modify('-' . ($dow - 1) . ' days');
    $end   = (clone $start)->modify('+6 days');
    $prevAnchor = (clone $start)->modify('-7 days')->format('Y-m-d');
    $nextAnchor = (clone $start)->modify('+7 days')->format('Y-m-d');
    $label = 'Week of ' . $start->format('j M Y') . ' – ' . $end->format('j M Y');
} elseif ($view === 'year') {
    $year  = (int) $anchorDt->format('Y');
    $start = new DateTime($year . '-01-01');
    $end   = new DateTime($year . '-12-31');
    $prevAnchor = ($year - 1) . '-01-01';
    $nextAnchor = ($year + 1) . '-01-01';
    $label = (string) $year;
} else {
    $start = new DateTime($anchorDt->format('Y-m-01'));
    $end   = new DateTime($anchorDt->format('Y-m-t'));
    $prevAnchor = (clone $start)->modify('-1 month')->format('Y-m-d');
    $nextAnchor = (clone $start)->modify('+1 month')->format('Y-m-d');
    $label = $start->format('F Y');
}

$startStr = $start->format('Y-m-d');
$endStr   = $end->format('Y-m-d');

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

$stmt = $pdo->prepare(
    "SELECT lr.from_date, lr.to_date, lr.status, lt.name AS type_name
     FROM leave_requests lr
     JOIN leave_types lt ON lt.id = lr.leave_type_id
     WHERE lr.staff_id = ? AND lr.status IN ('pending','approved') AND lr.from_date <= ? AND lr.to_date >= ?"
);
$stmt->execute([$staff['id'], $endStr, $startStr]);
$leaveByDate = [];
foreach ($stmt->fetchAll() as $lr) {
    $d = new DateTime(max($lr['from_date'], $startStr));
    $rangeEnd = new DateTime(min($lr['to_date'], $endStr));
    while ($d <= $rangeEnd) {
        $leaveByDate[$d->format('Y-m-d')] = ['type' => $lr['type_name'], 'status' => $lr['status']];
        $d->modify('+1 day');
    }
}

$stmt = $pdo->prepare("SELECT wfh_date, status FROM wfh_requests WHERE staff_id = ? AND status IN ('pending','approved') AND wfh_date BETWEEN ? AND ?");
$stmt->execute([$staff['id'], $startStr, $endStr]);
$wfhByDate = [];
foreach ($stmt->fetchAll() as $w) {
    $wfhByDate[$w['wfh_date']] = $w['status'];
}

$statusLabels = ['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half Day', 'absent' => 'Absent', 'on_leave' => 'On Leave'];

/** Builds one row of calendar data for a single date. */
function buildCalendarRow(string $dateStr, array $staff, string $today, array $holidaysByDate, array $attendanceByDate, array $leaveByDate, array $wfhByDate, array $statusLabels): array
{
    $row = [
        'date'    => $dateStr,
        'day'     => date('D', strtotime($dateStr)),
        'holiday' => $holidaysByDate[$dateStr] ?? null,
        'leave'   => $leaveByDate[$dateStr] ?? null,
        'wfh'     => $wfhByDate[$dateStr] ?? null,
        'attendance' => $attendanceByDate[$dateStr] ?? null,
        'isToday' => $dateStr === $today,
        'isPast'  => $dateStr < $today,
        'isFuture' => $dateStr > $today,
        'beforeJoined' => $staff['joined_date'] && $dateStr < $staff['joined_date'],
    ];
    $row['workedHours'] = $row['attendance']
        ? formatWorkedHours($row['attendance']['check_in_time'], $row['attendance']['check_out_time'])
        : null;
    return $row;
}

$rows = [];
$cursor = clone $start;
while ($cursor <= $end) {
    $rows[] = buildCalendarRow($cursor->format('Y-m-d'), $staff, $today, $holidaysByDate, $attendanceByDate, $leaveByDate, $wfhByDate, $statusLabels);
    $cursor->modify('+1 day');
}

$pageTitle = 'Calendar';
$activeNav = 'calendar';
require __DIR__ . '/../includes/staff-header.php';

/** Renders one calendar table for a list of rows (shared by month/week view and each month block in year view). */
function renderCalendarTable(array $rows, array $statusLabels): void
{
    ?>
    <div class="overflow-x">
      <table class="db-table">
        <thead>
          <tr><th>Date</th><th>Day</th><th>Holiday</th><th>Leave / WFH</th><th>Attendance</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr class="<?= $row['isToday'] ? 'row-flag' : '' ?>">
              <td><?= h($row['date']) ?><?= $row['isToday'] ? ' <strong>(Today)</strong>' : '' ?></td>
              <td><?= h($row['day']) ?></td>
              <td><?= $row['holiday'] ? h($row['holiday']) : '—' ?></td>
              <td>
                <?php if ($row['leave']): ?>
                  <?= h($row['leave']['type']) ?> <span class="badge badge-<?= badgeVariant($row['leave']['status']) ?>"><?= h(ucfirst($row['leave']['status'])) ?></span>
                <?php elseif ($row['wfh']): ?>
                  WFH <span class="badge badge-<?= badgeVariant($row['wfh']) ?>"><?= h(ucfirst($row['wfh'])) ?></span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['attendance']): ?>
                  <span class="badge badge-<?= badgeVariant($row['attendance']['status']) ?>"><?= h($statusLabels[$row['attendance']['status']] ?? $row['attendance']['status']) ?></span>
                  <?php if ($row['workedHours']): ?>
                    <span style="color:var(--color-text-muted);">— <?= h($row['workedHours']) ?></span>
                  <?php endif; ?>
                <?php elseif ($row['beforeJoined']): ?>
                  —
                <?php elseif ($row['holiday']): ?>
                  <span style="color:var(--color-text-muted);">Not worked</span>
                <?php elseif ($row['isPast']): ?>
                  <span style="color:var(--color-text-muted);">No record</span>
                <?php elseif ($row['isFuture']): ?>
                  <span style="color:var(--color-text-muted);">Working day</span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}
?>
  <div class="toolbar">
    <h1 style="margin:0;">Calendar — <?= h($label) ?></h1>
    <div class="table-actions">
      <a href="calendar.php?view=<?= h($view) ?>&amp;date=<?= h($prevAnchor) ?>" class="btn btn-sm btn-secondary">&larr; Previous</a>
      <a href="calendar.php?view=<?= h($view) ?>&amp;date=<?= h($today) ?>" class="btn btn-sm btn-secondary">Today</a>
      <a href="calendar.php?view=<?= h($view) ?>&amp;date=<?= h($nextAnchor) ?>" class="btn btn-sm btn-secondary">Next &rarr;</a>
    </div>
  </div>

  <div class="filter-bar">
    <div class="field" style="min-width:auto;">
      <a href="calendar.php?view=week&amp;date=<?= h($anchor) ?>" class="btn btn-sm <?= $view === 'week' ? '' : 'btn-secondary' ?>">Week</a>
      <a href="calendar.php?view=month&amp;date=<?= h($anchor) ?>" class="btn btn-sm <?= $view === 'month' ? '' : 'btn-secondary' ?>">Month</a>
      <a href="calendar.php?view=year&amp;date=<?= h($anchor) ?>" class="btn btn-sm <?= $view === 'year' ? '' : 'btn-secondary' ?>">Year</a>
    </div>
  </div>

  <?php if ($view === 'year'): ?>
    <?php
    $byMonth = [];
    foreach ($rows as $row) {
        $byMonth[substr($row['date'], 0, 7)][] = $row;
    }
    ?>
    <?php foreach ($byMonth as $monthKey => $monthRows): ?>
      <?php $isCurrentMonth = $monthKey === date('Y-m'); ?>
      <details <?= $isCurrentMonth ? 'open' : '' ?> style="margin-bottom:12px;">
        <summary style="cursor:pointer; font-weight:600; padding:8px 0;"><?= h(date('F', strtotime($monthKey . '-01'))) ?></summary>
        <?php renderCalendarTable($monthRows, $statusLabels); ?>
      </details>
    <?php endforeach; ?>
  <?php else: ?>
    <?php renderCalendarTable($rows, $statusLabels); ?>
  <?php endif; ?>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
