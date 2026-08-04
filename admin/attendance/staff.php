<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$id   = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    header('Location: ../staff/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? ORDER BY attendance_date DESC LIMIT 60');
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$statusLabels = [
    'present'  => 'Present',
    'late'     => 'Late',
    'half_day' => 'Half Day',
    'absent'   => 'Absent',
    'on_leave' => 'On Leave',
];
$locationLabels = [
    'office_verified' => 'Office (verified)',
    'office_manual'   => 'Office (manual)',
    'wfh'              => 'WFH',
    'unverified'       => 'Unverified',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance — <?= h($staff['full_name']) ?> — <?= h(APP_NAME) ?></title>
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
  <a href="index.php"><strong>Attendance</strong></a>
  <a href="../leave/index.php">Leave</a>
  <a href="../wfh/index.php">WFH</a>
  <a href="../leave-types/index.php">Leave Types</a>
  <a href="../office-locations/index.php">Office Locations</a>
  <a href="../payout/index.php">Payout</a>
  <a href="../reports/attendance.php">Reports</a>
  <a href="../settings.php">Settings</a>
  <a href="../../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <div class="toolbar">
    <h1 style="margin:0;">Attendance — <?= h($staff['full_name']) ?></h1>
    <div class="table-actions">
      <a href="edit.php?staff_id=<?= (int) $id ?>" class="btn btn-sm">Add / Edit Entry</a>
      <a href="../staff/view.php?id=<?= (int) $id ?>" class="btn btn-sm btn-secondary">Staff Profile</a>
      <a href="index.php" class="btn btn-sm btn-secondary">Back to Attendance</a>
    </div>
  </div>

  <p style="color:var(--color-muted);">Showing the most recent 60 records.</p>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Check In</th>
          <th>Check Out</th>
          <th>Location</th>
          <th>Status</th>
          <th>Notes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$history): ?>
          <tr><td colspan="7" style="color:var(--color-muted);">No attendance records yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($history as $row): ?>
          <tr class="<?= $row['status'] === 'late' || $row['work_location'] === 'unverified' ? 'row-flag' : '' ?>">
            <td><?= h($row['attendance_date']) ?></td>
            <td><?= h($row['check_in_time'] ?? '—') ?></td>
            <td><?= h($row['check_out_time'] ?? '—') ?></td>
            <td><span class="badge badge-<?= h($row['work_location']) ?>"><?= h($locationLabels[$row['work_location']] ?? $row['work_location']) ?></span></td>
            <td><span class="badge badge-<?= h($row['status']) ?>"><?= h($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
            <td style="max-width:240px; white-space:pre-wrap;"><?= h($row['notes']) ?></td>
            <td><a href="edit.php?staff_id=<?= (int) $id ?>&amp;date=<?= h($row['attendance_date']) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
