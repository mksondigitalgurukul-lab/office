<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$flash = null;
$assignError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'review') {
        $id       = (int) ($_POST['id'] ?? 0);
        $decision = $_POST['decision'] ?? '';

        if (in_array($decision, ['approved', 'rejected'], true)) {
            $stmt = $pdo->prepare(
                "UPDATE wfh_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$decision, $admin['id'], $id]);
            $flash = ['type' => 'success', 'text' => 'WFH request ' . $decision . '.'];
        }
    } elseif ($action === 'assign') {
        $assignStaffId = (int) ($_POST['staff_id'] ?? 0);
        $wfhDate       = trim($_POST['wfh_date'] ?? '');
        $reason        = trim($_POST['reason'] ?? '');

        $stmt = $pdo->prepare('SELECT id FROM staff WHERE id = ? AND status = \'active\'');
        $stmt->execute([$assignStaffId]);

        if (!$stmt->fetchColumn()) {
            $assignError = 'Choose a valid active staff member.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $wfhDate)) {
            $assignError = 'Enter a valid date.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO wfh_requests (staff_id, wfh_date, reason, status, created_by_type, created_by_id, reviewed_by, reviewed_at)
                 VALUES (?, ?, ?, 'approved', 'admin', ?, NULL, NULL)
                 ON DUPLICATE KEY UPDATE
                   reason = VALUES(reason),
                   status = 'approved',
                   created_by_type = 'admin',
                   created_by_id = VALUES(created_by_id),
                   reviewed_by = NULL,
                   reviewed_at = NULL"
            );
            $stmt->execute([$assignStaffId, $wfhDate, $reason !== '' ? $reason : null, $admin['id']]);
            $flash = ['type' => 'success', 'text' => 'WFH day assigned and auto-approved.'];
        }
    }
}

$status  = $_GET['status'] ?? '';
$staffId = (int) ($_GET['staff_id'] ?? 0);
$date    = $_GET['date'] ?? '';

$where  = ['1=1'];
$params = [];

if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[]  = 'w.status = ?';
    $params[] = $status;
}
if ($staffId > 0) {
    $where[]  = 'w.staff_id = ?';
    $params[] = $staffId;
}
if ($date !== '' && DateTime::createFromFormat('Y-m-d', $date)) {
    $where[]  = 'w.wfh_date = ?';
    $params[] = $date;
}

$sql = 'SELECT w.*, s.full_name
        FROM wfh_requests w
        JOIN staff s ON s.id = w.staff_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY w.status = \'pending\' DESC, w.wfh_date DESC, w.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$staffList = $pdo->query("SELECT id, full_name FROM staff WHERE status = 'active' ORDER BY full_name")->fetchAll();

$statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WFH Requests — <?= h(APP_NAME) ?></title>
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
  <a href="index.php"><strong>WFH</strong></a>
  <a href="../leave-types/index.php">Leave Types</a>
  <a href="../office-locations/index.php">Office Locations</a>
  <a href="../payout/index.php">Payout</a>
  <a href="../reports/attendance.php">Reports</a>
  <a href="../settings.php">Settings</a>
  <a href="../../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <h1>WFH Requests</h1>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <h2>Assign a WFH Day</h2>
  <p style="color:var(--color-muted);">Directly assign a WFH day to any staff member — auto-approved immediately, no review needed.</p>
  <?php if ($assignError): ?>
    <div class="alert alert-error"><?= h($assignError) ?></div>
  <?php endif; ?>
  <div class="card" style="max-width:520px; margin-bottom:16px;">
    <form method="post" novalidate>
      <input type="hidden" name="action" value="assign">
      <div class="field-row">
        <div class="field">
          <label for="assign_staff_id">Staff</label>
          <select id="assign_staff_id" name="staff_id" required>
            <?php foreach ($staffList as $s): ?>
              <option value="<?= (int) $s['id'] ?>"><?= h($s['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="assign_wfh_date">Date</label>
          <input type="date" id="assign_wfh_date" name="wfh_date" required>
        </div>
      </div>
      <div class="field">
        <label for="assign_reason">Note (optional)</label>
        <textarea id="assign_reason" name="reason" rows="2"></textarea>
      </div>
      <button type="submit" class="btn">Assign &amp; Approve</button>
    </form>
  </div>

  <h2>All WFH Requests</h2>
  <form method="get" class="filter-bar">
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>
    <div class="field">
      <label for="staff_id">Staff</label>
      <select id="staff_id" name="staff_id">
        <option value="">All</option>
        <?php foreach ($staffList as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="date">Date</label>
      <input type="date" id="date" name="date" value="<?= h($date) ?>">
    </div>
    <div class="field" style="min-width:auto;">
      <button type="submit" class="btn btn-sm">Filter</button>
      <a href="index.php" class="btn btn-sm btn-secondary">Reset</a>
    </div>
  </form>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr>
          <th>Staff</th><th>Date</th><th>Reason</th><th>Origin</th>
          <th>Status</th><th>Reviewed</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$requests): ?>
          <tr><td colspan="7" style="color:var(--color-muted);">No WFH requests match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
          <tr class="<?= $r['status'] === 'pending' ? 'row-flag' : '' ?>">
            <td><?= h($r['full_name']) ?></td>
            <td><?= h($r['wfh_date']) ?></td>
            <td style="max-width:200px; white-space:pre-wrap;"><?= h($r['reason']) ?></td>
            <td><?= $r['created_by_type'] === 'admin' ? 'Admin-assigned' : 'Staff request' ?></td>
            <td><span class="badge badge-<?= $r['status'] === 'approved' ? 'active' : ($r['status'] === 'rejected' ? 'unverified' : 'inactive') ?>"><?= h($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
            <td><?= h($r['reviewed_at'] ?? '—') ?></td>
            <td class="table-actions">
              <?php if ($r['status'] === 'pending'): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="review">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input type="hidden" name="decision" value="approved">
                  <button type="submit" class="btn btn-sm">Approve</button>
                </form>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="review">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input type="hidden" name="decision" value="rejected">
                  <button type="submit" class="btn btn-sm btn-secondary">Reject</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
