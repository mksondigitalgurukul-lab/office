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

// Simple combined "upcoming" list: holidays (company-wide) + this staff
// member's own approved leave/WFH, soonest first.
$upcoming = [];

$stmt = $pdo->prepare('SELECT holiday_date, name FROM holidays WHERE holiday_date >= ? ORDER BY holiday_date ASC LIMIT 10');
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

$passwordError   = '';
$passwordSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($currentPassword, $staff['password_hash'])) {
        $passwordError = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $passwordError = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'New password and confirmation do not match.';
    } else {
        $stmt = $pdo->prepare('UPDATE staff SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $staff['id']]);
        $passwordSuccess = 'Password updated.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — <?= h(APP_NAME) ?></title>
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
  <a href="attendance.php">Attendance</a>
  <a href="leave.php">Leave</a>
  <a href="wfh.php">WFH</a>
  <a href="payout.php">Payout</a>
  <a href="profile.php">Profile</a>
</nav>

<div class="container">
  <div class="welcome-box">
    <h1>Welcome, <?= h($staff['full_name']) ?></h1>
    <p><strong>Work Mode:</strong> <?= h($staff['work_mode']) ?></p>
    <p>
      <strong>Work Timing:</strong> <?= h($timing['start']) ?> – <?= h($timing['end']) ?>
      (<?= $timing['source'] === 'override' ? 'custom' : 'universal default' ?>)
    </p>
  </div>

  <h2>Upcoming</h2>
  <div class="card" style="margin-bottom:16px;">
    <?php if (!$upcoming): ?>
      <p style="margin:0; color:var(--color-muted);">No upcoming holidays, approved leave, or approved WFH days.</p>
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

  <div class="card" style="max-width:420px;">
    <h2>Change Password</h2>

    <?php if ($passwordError): ?>
      <div class="alert alert-error"><?= h($passwordError) ?></div>
    <?php endif; ?>
    <?php if ($passwordSuccess): ?>
      <div class="alert alert-success"><?= h($passwordSuccess) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="action" value="change_password">
      <div class="field">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>
      </div>
      <div class="field">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
      </div>
      <div class="field">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
      </div>
      <button type="submit" class="btn">Update Password</button>
    </form>
  </div>
</div>
</body>
</html>
