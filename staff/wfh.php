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

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wfhDate = trim($_POST['wfh_date'] ?? '');
    $reason  = trim($_POST['reason'] ?? '');

    if (!DateTime::createFromFormat('Y-m-d', $wfhDate)) {
        $error = 'Enter a valid date.';
    } elseif ($reason === '') {
        $error = 'A reason is required.';
    } else {
        $stmt = $pdo->prepare('SELECT status FROM wfh_requests WHERE staff_id = ? AND wfh_date = ?');
        $stmt->execute([$staff['id'], $wfhDate]);
        $existingStatus = $stmt->fetchColumn();

        if ($existingStatus && in_array($existingStatus, ['pending', 'approved'], true)) {
            $error = "You already have a {$existingStatus} WFH request for that date.";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO wfh_requests (staff_id, wfh_date, reason, status, created_by_type, created_by_id)
                 VALUES (?, ?, ?, 'pending', 'staff', ?)
                 ON DUPLICATE KEY UPDATE
                   reason = VALUES(reason),
                   status = 'pending',
                   created_by_type = 'staff',
                   created_by_id = VALUES(created_by_id),
                   reviewed_by = NULL,
                   reviewed_at = NULL"
            );
            $stmt->execute([$staff['id'], $wfhDate, $reason, $staff['id']]);

            $success = 'WFH request submitted.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM wfh_requests WHERE staff_id = ? ORDER BY wfh_date DESC, id DESC');
$stmt->execute([$staff['id']]);
$history = $stmt->fetchAll();

$statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WFH — <?= h(APP_NAME) ?></title>
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
  <a href="dashboard.php">Dashboard</a>
  <a href="attendance.php">Attendance</a>
  <a href="leave.php">Leave</a>
  <a href="wfh.php"><strong>WFH</strong></a>
  <a href="payout.php">Payout</a>
  <a href="profile.php">Profile</a>
</nav>

<div class="container">
  <h1>Work From Home</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:480px; margin-bottom:16px;">
    <h2 style="margin-top:0;">Request a WFH Day</h2>
    <form method="post" novalidate>
      <div class="field">
        <label for="wfh_date">Date</label>
        <input type="date" id="wfh_date" name="wfh_date" required value="<?= h($_POST['wfh_date'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="reason">Reason</label>
        <textarea id="reason" name="reason" rows="3" required><?= h($_POST['reason'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn">Submit Request</button>
    </form>
    <p class="field-hint" style="margin-top:10px;">Once approved, checking in that day from off-office WiFi will count as a verified WFH day instead of being flagged.</p>
  </div>

  <h2>My WFH History</h2>
  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Date</th><th>Reason</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (!$history): ?>
          <tr><td colspan="3" style="color:var(--color-muted);">No WFH requests yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($history as $r): ?>
          <tr>
            <td><?= h($r['wfh_date']) ?></td>
            <td style="max-width:260px; white-space:pre-wrap;"><?= h($r['reason']) ?></td>
            <td><span class="badge badge-<?= $r['status'] === 'approved' ? 'active' : ($r['status'] === 'rejected' ? 'unverified' : 'inactive') ?>"><?= h($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
