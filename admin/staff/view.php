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
    header('Location: index.php');
    exit;
}

$timing = getCurrentWorkTiming($id);

$stmt = $pdo->prepare(
    'SELECT h.*, a.name AS set_by_name
     FROM staff_work_time_history h
     LEFT JOIN admins a ON a.id = h.set_by
     WHERE h.staff_id = ?
     ORDER BY h.effective_from DESC, h.id DESC'
);
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$salary = getCurrentSalary($id);

$stmt = $pdo->prepare(
    'SELECT s.*, a.name AS set_by_name
     FROM staff_salary s
     LEFT JOIN admins a ON a.id = s.set_by
     WHERE s.staff_id = ?
     ORDER BY s.effective_from DESC, s.id DESC'
);
$stmt->execute([$id]);
$salaryHistory = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($staff['full_name']) ?> — <?= h(APP_NAME) ?></title>
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
  <a href="index.php"><strong>Staff</strong></a>
  <a href="../attendance/index.php">Attendance</a>
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
    <h1 style="margin:0;"><?= h($staff['full_name']) ?></h1>
    <div class="table-actions">
      <a href="../attendance/staff.php?id=<?= (int) $id ?>" class="btn btn-sm btn-secondary">Attendance History</a>
      <a href="../payout/index.php?staff_id=<?= (int) $id ?>" class="btn btn-sm btn-secondary">Payouts</a>
      <a href="edit.php?id=<?= (int) $id ?>" class="btn btn-sm btn-secondary">Edit</a>
      <a href="index.php" class="btn btn-sm btn-secondary">Back to list</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:16px;">
    <div class="field-row">
      <div><strong>Email:</strong> <?= h($staff['email']) ?></div>
      <div><strong>Phone:</strong> <?= h($staff['phone'] ?? '—') ?></div>
    </div>
    <div class="field-row" style="margin-top:10px;">
      <div><strong>Designation:</strong> <?= h($staff['designation'] ?? '—') ?></div>
      <div><strong>Department:</strong> <?= h($staff['department'] ?? '—') ?></div>
    </div>
    <div class="field-row" style="margin-top:10px;">
      <div><strong>Work Mode:</strong> <?= h($staff['work_mode']) ?></div>
      <div><strong>Status:</strong> <span class="badge badge-<?= $staff['status'] === 'active' ? 'active' : 'inactive' ?>"><?= h($staff['status']) ?></span></div>
    </div>
    <div class="field-row" style="margin-top:10px;">
      <div><strong>Joined:</strong> <?= h($staff['joined_date'] ?? '—') ?></div>
    </div>
  </div>

  <div class="toolbar">
    <h2 style="margin:0;">Work Timing</h2>
    <a href="set-timing.php?id=<?= (int) $id ?>" class="btn btn-sm">Set / Change Timing</a>
  </div>

  <div class="card" style="margin-bottom:16px;">
    <p style="margin:0;">
      Current: <strong><?= h($timing['start']) ?> – <?= h($timing['end']) ?></strong>
      (<?= $timing['source'] === 'override' ? 'custom, effective from ' . h($timing['effective_from']) : 'universal default' ?>)
    </p>
  </div>

  <h2>Timing History</h2>
  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr>
          <th>Effective From</th>
          <th>Start</th>
          <th>End</th>
          <th>Set By</th>
          <th>Recorded At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$history): ?>
          <tr><td colspan="5" style="color:var(--color-muted);">No overrides recorded — always used the universal default.</td></tr>
        <?php endif; ?>
        <?php foreach ($history as $row): ?>
          <tr>
            <td><?= h($row['effective_from']) ?></td>
            <?php if ($row['work_start_time'] !== null): ?>
              <td><?= h($row['work_start_time']) ?></td>
              <td><?= h($row['work_end_time']) ?></td>
            <?php else: ?>
              <td colspan="2" style="color:var(--color-muted);">(revert to universal default)</td>
            <?php endif; ?>
            <td><?= h($row['set_by_name'] ?? '—') ?></td>
            <td><?= h($row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="toolbar">
    <h2 style="margin:0;">Salary</h2>
    <a href="set-salary.php?id=<?= (int) $id ?>" class="btn btn-sm">Set / Change Salary</a>
  </div>

  <div class="card" style="margin-bottom:16px;">
    <p style="margin:0;">
      <?php if ($salary['amount'] !== null): ?>
        Current: <strong><?= h(number_format($salary['amount'], 2)) ?></strong> / month
        (effective from <?= h($salary['effective_from']) ?>)
      <?php else: ?>
        <span style="color:var(--color-muted);">No salary set yet — set one before generating a payout for this staff member.</span>
      <?php endif; ?>
    </p>
  </div>

  <h2>Salary History</h2>
  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Effective From</th><th>Monthly Salary</th><th>Set By</th><th>Recorded At</th></tr>
      </thead>
      <tbody>
        <?php if (!$salaryHistory): ?>
          <tr><td colspan="4" style="color:var(--color-muted);">No salary recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($salaryHistory as $row): ?>
          <tr>
            <td><?= h($row['effective_from']) ?></td>
            <td><?= h(number_format((float) $row['monthly_salary'], 2)) ?></td>
            <td><?= h($row['set_by_name'] ?? '—') ?></td>
            <td><?= h($row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
