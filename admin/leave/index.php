<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    $id       = (int) ($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    if (in_array($decision, ['approved', 'rejected'], true)) {
        $stmt = $pdo->prepare(
            "UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$decision, $admin['id'], $id]);
        $flash = ['type' => 'success', 'text' => 'Leave request ' . $decision . '.'];
    }
}

$status  = $_GET['status'] ?? '';
$staffId = (int) ($_GET['staff_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

$where  = ['1=1'];
$params = [];

if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[]  = 'lr.status = ?';
    $params[] = $status;
}
if ($staffId > 0) {
    $where[]  = 'lr.staff_id = ?';
    $params[] = $staffId;
}
if ($dateFrom !== '' && DateTime::createFromFormat('Y-m-d', $dateFrom)) {
    $where[]  = 'lr.to_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '' && DateTime::createFromFormat('Y-m-d', $dateTo)) {
    $where[]  = 'lr.from_date <= ?';
    $params[] = $dateTo;
}

$sql = 'SELECT lr.*, s.full_name, lt.name AS type_name
        FROM leave_requests lr
        JOIN staff s ON s.id = lr.staff_id
        JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY lr.status = \'pending\' DESC, lr.from_date DESC, lr.id DESC';

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
<title>Leave Requests — <?= h(APP_NAME) ?></title>
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
  <a href="index.php"><strong>Leave</strong></a>
  <a href="../wfh/index.php">WFH</a>
  <a href="../leave-types/index.php">Leave Types</a>
  <a href="../office-locations/index.php">Office Locations</a>
  <a href="../payout/index.php">Payout</a>
  <a href="../reports/attendance.php">Reports</a>
  <a href="../settings.php">Settings</a>
  <a href="../../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <h1>Leave Requests</h1>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

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
      <label for="date_from">From</label>
      <input type="date" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
    </div>
    <div class="field">
      <label for="date_to">To</label>
      <input type="date" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
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
          <th>Staff</th><th>Type</th><th>From</th><th>To</th><th>Days</th>
          <th>Reason</th><th>Status</th><th>Reviewed</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$requests): ?>
          <tr><td colspan="9" style="color:var(--color-muted);">No leave requests match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
          <tr class="<?= $r['status'] === 'pending' ? 'row-flag' : '' ?>">
            <td><?= h($r['full_name']) ?></td>
            <td><?= h($r['type_name']) ?></td>
            <td><?= h($r['from_date']) ?></td>
            <td><?= h($r['to_date']) ?></td>
            <td><?= (int) $r['days_count'] ?></td>
            <td style="max-width:200px; white-space:pre-wrap;"><?= h($r['reason']) ?></td>
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
