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

$timing = getCurrentWorkTiming($staff['id']);
$today  = date('Y-m-d');

$holidayName = getHolidayName($today);
$stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?');
$stmt->execute([$staff['id'], $today]);
$todayRow = $stmt->fetch();

$statusLabels = ['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half Day', 'absent' => 'Absent', 'on_leave' => 'On Leave'];
if ($holidayName) {
    $todayStatusLabel = 'Holiday';
    $todayStatusVariant = 'info';
} elseif (!$todayRow || $todayRow['check_in_time'] === null) {
    $todayStatusLabel = 'Not Checked In';
    $todayStatusVariant = 'neutral';
} else {
    $todayStatusLabel = $statusLabels[$todayRow['status']] ?? $todayRow['status'];
    $todayStatusVariant = badgeVariant($todayRow['status']);
}

// Simple combined "upcoming" list: holidays (company-wide) + this staff
// member's own approved leave/WFH, soonest first.
$upcoming = [];

// Weekly Sundays are excluded here — once a year's worth are generated
// (see admin/holidays/index.php), they'd otherwise flood this list and
// crowd out named holidays and this staff member's own leave/WFH.
$stmt = $pdo->prepare('SELECT holiday_date, name FROM holidays WHERE holiday_date >= ? AND DAYOFWEEK(holiday_date) <> 1 ORDER BY holiday_date ASC LIMIT 10');
$stmt->execute([$today]);
foreach ($stmt->fetchAll() as $h) {
    $upcoming[] = ['date' => $h['holiday_date'], 'type' => 'Holiday', 'label' => $h['name']];
}

$stmt = $pdo->prepare(
    "SELECT lr.from_date, lr.to_date, lt.name AS type_name
     FROM leave_requests lr
     JOIN leave_types lt ON lt.id = lr.leave_type_id
     WHERE lr.staff_id = ? AND lr.status = 'approved' AND lr.to_date >= ?
     ORDER BY lr.from_date ASC LIMIT 10"
);
$stmt->execute([$staff['id'], $today]);
foreach ($stmt->fetchAll() as $l) {
    $dateLabel = $l['from_date'] === $l['to_date'] ? $l['from_date'] : ($l['from_date'] . ' to ' . $l['to_date']);
    $upcoming[] = ['date' => $l['from_date'], 'type' => 'Leave', 'label' => $l['type_name'] . ' leave (' . $dateLabel . ')'];
}

$stmt = $pdo->prepare("SELECT wfh_date FROM wfh_requests WHERE staff_id = ? AND status = 'approved' AND wfh_date >= ? ORDER BY wfh_date ASC LIMIT 10");
$stmt->execute([$staff['id'], $today]);
foreach ($stmt->fetchAll() as $w) {
    $upcoming[] = ['date' => $w['wfh_date'], 'type' => 'WFH', 'label' => 'Approved WFH'];
}

usort($upcoming, fn($a, $b) => $a['date'] <=> $b['date']);
$upcoming = array_slice($upcoming, 0, 10);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/staff-header.php';
?>
  <div class="welcome-box">
    <h1>Welcome, <?= h($staff['full_name']) ?></h1>
    <p><?= h(date('l, j F Y', strtotime($today))) ?></p>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Today's Status</div>
      <div><span class="badge badge-<?= h($todayStatusVariant) ?>" style="font-size:0.85rem;"><?= h($todayStatusLabel) ?></span></div>
      <?php if ($todayRow && $todayRow['check_in_time']): ?>
        <div class="stat-sub">Checked in at <?= h($todayRow['check_in_time']) ?></div>
      <?php endif; ?>
    </div>
    <div class="stat-card accent-primary">
      <div class="stat-label">Work Mode</div>
      <div class="stat-value" style="font-size:1.2rem; text-transform:capitalize;"><?= h($staff['work_mode']) ?></div>
    </div>
    <div class="stat-card accent-accent">
      <div class="stat-label">Work Timing</div>
      <div class="stat-value" style="font-size:1.2rem;"><?= h($timing['start']) ?> – <?= h($timing['end']) ?></div>
      <div class="stat-sub"><?= $timing['source'] === 'override' ? 'Custom hours' : 'Universal default' ?></div>
    </div>
    <div class="stat-card accent-info">
      <div class="stat-label">Upcoming</div>
      <div class="stat-value"><?= count($upcoming) ?></div>
      <div class="stat-sub">Holidays, leave &amp; WFH ahead</div>
    </div>
  </div>

  <div class="quick-links" style="margin-bottom:20px;">
    <a href="attendance.php" class="quick-link">Check In / Out</a>
    <a href="calendar.php" class="quick-link">View Calendar</a>
    <a href="work-report.php" class="quick-link">Work Report</a>
    <a href="leave.php" class="quick-link">Request Leave</a>
    <a href="wfh.php" class="quick-link">Request WFH</a>
    <a href="profile.php" class="quick-link">Change Password</a>
  </div>

  <h2>Upcoming</h2>
  <div class="card">
    <?php if (!$upcoming): ?>
      <p style="margin:0; color:var(--color-text-muted);">No upcoming holidays, approved leave, or approved WFH days.</p>
    <?php else: ?>
      <table class="db-table">
        <thead><tr><th>Date</th><th>Type</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($upcoming as $u): ?>
            <tr>
              <td><?= h($u['date']) ?></td>
              <td><?= h($u['type']) ?></td>
              <td><?= h($u['label']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
