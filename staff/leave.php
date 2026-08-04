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

$leaveTypes = $pdo->query('SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name')->fetchAll();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveTypeId = (int) ($_POST['leave_type_id'] ?? 0);
    $fromDate    = trim($_POST['from_date'] ?? '');
    $toDate      = trim($_POST['to_date'] ?? '');
    $reason      = trim($_POST['reason'] ?? '');

    $fromValid = DateTime::createFromFormat('Y-m-d', $fromDate);
    $toValid   = DateTime::createFromFormat('Y-m-d', $toDate);

    $validTypeIds = array_map('intval', array_column($leaveTypes, 'id'));

    if (!in_array($leaveTypeId, $validTypeIds, true)) {
        $error = 'Choose a leave type.';
    } elseif (!$fromValid || !$toValid) {
        $error = 'Enter valid from/to dates.';
    } elseif ($toDate < $fromDate) {
        $error = 'To date must be on or after the from date.';
    } else {
        $daysCount = (strtotime($toDate) - strtotime($fromDate)) / 86400 + 1;

        $stmt = $pdo->prepare(
            'INSERT INTO leave_requests (staff_id, leave_type_id, from_date, to_date, days_count, reason, status, requested_by)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?)'
        );
        $stmt->execute([$staff['id'], $leaveTypeId, $fromDate, $toDate, $daysCount, $reason !== '' ? $reason : null, $staff['id']]);

        $success = 'Leave request submitted.';
    }
}

$stmt = $pdo->prepare(
    'SELECT lr.*, lt.name AS type_name
     FROM leave_requests lr
     JOIN leave_types lt ON lt.id = lr.leave_type_id
     WHERE lr.staff_id = ?
     ORDER BY lr.from_date DESC, lr.id DESC'
);
$stmt->execute([$staff['id']]);
$history = $stmt->fetchAll();

$currentYear = date('Y');
$stmt = $pdo->prepare(
    "SELECT lt.name AS type_name, COALESCE(SUM(lr.days_count), 0) AS approved_days
     FROM leave_types lt
     LEFT JOIN leave_requests lr ON lr.leave_type_id = lt.id AND lr.staff_id = ? AND lr.status = 'approved' AND YEAR(lr.from_date) = ?
     GROUP BY lt.id, lt.name
     ORDER BY lt.name"
);
$stmt->execute([$staff['id'], $currentYear]);
$balance = $stmt->fetchAll();

$statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$pageTitle = 'Leave';
$activeNav = 'leave';
require __DIR__ . '/../includes/staff-header.php';
?>
  <h1>Leave</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:480px; margin-bottom:16px;">
    <h2 style="margin-top:0;">Request Leave</h2>
    <form method="post" novalidate>
      <div class="field">
        <label for="leave_type_id">Leave Type</label>
        <select id="leave_type_id" name="leave_type_id" required>
          <?php foreach ($leaveTypes as $t): ?>
            <option value="<?= (int) $t['id'] ?>"><?= h($t['name']) ?> (<?= $t['is_paid'] ? 'paid' : 'unpaid' ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="from_date">From</label>
          <input type="date" id="from_date" name="from_date" required value="<?= h($_POST['from_date'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="to_date">To</label>
          <input type="date" id="to_date" name="to_date" required value="<?= h($_POST['to_date'] ?? '') ?>">
        </div>
      </div>
      <div class="field">
        <label for="reason">Reason</label>
        <textarea id="reason" name="reason" rows="3"><?= h($_POST['reason'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn">Submit Request</button>
    </form>
  </div>

  <h2>Leave Balance (<?= h($currentYear) ?>)</h2>
  <p style="color:var(--color-text-muted);">Approved days this year, per leave type. Simple count — no accrual rules.</p>
  <div class="overflow-x">
    <table class="db-table">
      <thead><tr><th>Leave Type</th><th>Approved Days</th></tr></thead>
      <tbody>
        <?php foreach ($balance as $b): ?>
          <tr><td><?= h($b['type_name']) ?></td><td><?= (int) $b['approved_days'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2>My Leave History</h2>
  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (!$history): ?>
          <tr><td colspan="6" style="color:var(--color-text-muted);">No leave requests yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($history as $r): ?>
          <tr>
            <td><?= h($r['type_name']) ?></td>
            <td><?= h($r['from_date']) ?></td>
            <td><?= h($r['to_date']) ?></td>
            <td><?= (int) $r['days_count'] ?></td>
            <td style="max-width:220px; white-space:pre-wrap;"><?= h($r['reason']) ?></td>
            <td><span class="badge badge-<?= badgeVariant($r['status']) ?>"><?= h($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
