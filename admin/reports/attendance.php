<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$staffId = (int) ($_GET['staff_id'] ?? 0);
$range   = $_GET['range'] ?? '7days';
$today   = date('Y-m-d');

switch ($range) {
    case 'month':
        $from = date('Y-m-01');
        $to   = date('Y-m-t');
        break;
    case 'year':
        $from = date('Y-01-01');
        $to   = date('Y-12-31');
        break;
    case 'custom':
        $from = $_GET['from'] ?? $today;
        $to   = $_GET['to'] ?? $today;
        if (!DateTime::createFromFormat('Y-m-d', $from)) {
            $from = $today;
        }
        if (!DateTime::createFromFormat('Y-m-d', $to)) {
            $to = $today;
        }
        break;
    case '7days':
    default:
        $range = '7days';
        $from  = date('Y-m-d', strtotime('-6 days'));
        $to    = $today;
        break;
}
if ($to < $from) {
    [$from, $to] = [$to, $from];
}

$summarySql = "SELECT s.id, s.full_name, s.department,
        SUM(a.status = 'present') AS present_days,
        SUM(a.status = 'late') AS late_days,
        SUM(a.status = 'absent') AS absent_days,
        SUM(a.status = 'on_leave') AS on_leave_days,
        SUM(a.work_location = 'wfh') AS wfh_days,
        SUM(a.status = 'half_day') AS half_days
     FROM staff s
     LEFT JOIN attendance a ON a.staff_id = s.id AND a.attendance_date BETWEEN ? AND ?
     WHERE s.status = 'active'" . ($staffId > 0 ? ' AND s.id = ?' : '') . '
     GROUP BY s.id, s.full_name, s.department
     ORDER BY s.full_name';

$params = [$from, $to];
if ($staffId > 0) {
    $params[] = $staffId;
}

$stmt = $pdo->prepare($summarySql);
$stmt->execute($params);
$summary = $stmt->fetchAll();

foreach ($summary as &$row) {
    foreach (['present_days', 'late_days', 'absent_days', 'on_leave_days', 'wfh_days', 'half_days'] as $col) {
        $row[$col] = (int) $row[$col];
    }
    $row['worked_days'] = $row['present_days'] + $row['late_days'] + $row['half_days'];
}
unset($row);

$dailyLog = [];
if ($staffId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC');
    $stmt->execute([$staffId, $from, $to]);
    $dailyLog = $stmt->fetchAll();
}

// --- CSV export ---
if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="attendance-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');

    if ($staffId > 0 && $dailyLog) {
        fputcsv($out, ['Date', 'Check In', 'Check Out', 'Work Location', 'Status', 'Notes']);
        foreach ($dailyLog as $row) {
            fputcsv($out, [$row['attendance_date'], $row['check_in_time'], $row['check_out_time'], $row['work_location'], $row['status'], $row['notes']]);
        }
    } else {
        fputcsv($out, ['Staff', 'Department', 'Present', 'Late', 'Absent', 'On Leave', 'WFH', 'Half Day', 'Worked Days']);
        foreach ($summary as $row) {
            fputcsv($out, [$row['full_name'], $row['department'], $row['present_days'], $row['late_days'], $row['absent_days'], $row['on_leave_days'], $row['wfh_days'], $row['half_days'], $row['worked_days']]);
        }
    }

    fclose($out);
    exit;
}

$staffList = $pdo->query("SELECT id, full_name FROM staff WHERE status = 'active' ORDER BY full_name")->fetchAll();

$statusLabels = ['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half Day', 'absent' => 'Absent', 'on_leave' => 'On Leave'];
$locationLabels = ['office_verified' => 'Office (verified)', 'office_manual' => 'Office (manual)', 'wfh' => 'WFH', 'unverified' => 'Unverified'];

$qs = http_build_query(['staff_id' => $staffId, 'range' => $range, 'from' => $from, 'to' => $to]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Report — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><?= h(APP_NAME) ?></div>
  <div class="user-info">
    <span><?= h($admin['name']) ?> (<?= h($admin['role']) ?>)</span>
    <a href="../logout.php">Log out</a>
  </div>
</div>
<nav class="nav">
  <a href="../dashboard.php">Dashboard</a>
  <a href="../staff/index.php">Staff</a>
  <a href="../attendance/index.php">Attendance</a>
  <a href="../leave/index.php">Leave</a>
  <a href="../wfh/index.php">WFH</a>
  <a href="../leave-types/index.php">Leave Types</a>
  <a href="../office-locations/index.php">Office Locations</a>
  <a href="../payout/index.php">Payout</a>
  <a href="attendance.php"><strong>Reports</strong></a>
  <a href="../settings.php">Settings</a>
  <a href="../../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <div class="toolbar">
    <h1 style="margin:0;">Attendance Report</h1>
    <a href="attendance.php?<?= h($qs) ?>&amp;format=csv" class="btn btn-sm">Export CSV</a>
  </div>

  <form method="get" class="filter-bar">
    <div class="field">
      <label for="staff_id">Staff</label>
      <select id="staff_id" name="staff_id">
        <option value="0">All active staff</option>
        <?php foreach ($staffList as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="range">Range</label>
      <select id="range" name="range" onchange="document.getElementById('customRange').style.display = this.value === 'custom' ? 'flex' : 'none';">
        <option value="7days" <?= $range === '7days' ? 'selected' : '' ?>>Last 7 days</option>
        <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>This month</option>
        <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>This year</option>
        <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom</option>
      </select>
    </div>
    <div id="customRange" class="field-row" style="display:<?= $range === 'custom' ? 'flex' : 'none' ?>; gap:10px;">
      <div class="field">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?= h($from) ?>">
      </div>
      <div class="field">
        <label for="to">To</label>
        <input type="date" id="to" name="to" value="<?= h($to) ?>">
      </div>
    </div>
    <div class="field" style="min-width:auto;">
      <button type="submit" class="btn btn-sm">Apply</button>
    </div>
  </form>

  <p style="color:var(--color-muted);">Showing <?= h($from) ?> to <?= h($to) ?>.</p>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr>
          <th>Staff</th><th>Department</th><th>Present</th><th>Late</th><th>Absent</th>
          <th>On Leave</th><th>WFH</th><th>Half Day</th><th>Worked Days</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$summary): ?>
          <tr><td colspan="9" style="color:var(--color-muted);">No data for these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($summary as $row): ?>
          <tr>
            <td><?= h($row['full_name']) ?></td>
            <td><?= h($row['department']) ?></td>
            <td><?= $row['present_days'] ?></td>
            <td><?= $row['late_days'] ?></td>
            <td><?= $row['absent_days'] ?></td>
            <td><?= $row['on_leave_days'] ?></td>
            <td><?= $row['wfh_days'] ?></td>
            <td><?= $row['half_days'] ?></td>
            <td><strong><?= $row['worked_days'] ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($staffId > 0): ?>
    <h2>Day-by-Day Log</h2>
    <div class="overflow-x">
      <table class="db-table">
        <thead>
          <tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Location</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if (!$dailyLog): ?>
            <tr><td colspan="5" style="color:var(--color-muted);">No attendance records in this range.</td></tr>
          <?php endif; ?>
          <?php foreach ($dailyLog as $row): ?>
            <tr class="<?= $row['status'] === 'late' || $row['work_location'] === 'unverified' ? 'row-flag' : '' ?>">
              <td><?= h($row['attendance_date']) ?></td>
              <td><?= h($row['check_in_time'] ?? '—') ?></td>
              <td><?= h($row['check_out_time'] ?? '—') ?></td>
              <td><span class="badge badge-<?= h($row['work_location']) ?>"><?= h($locationLabels[$row['work_location']] ?? $row['work_location']) ?></span></td>
              <td><span class="badge badge-<?= h($row['status']) ?>"><?= h($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
