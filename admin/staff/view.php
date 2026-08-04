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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $newPassword = substr(bin2hex(random_bytes(6)), 0, 10);
    $stmt = $pdo->prepare('UPDATE staff SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);

    $_SESSION['flash'] = [
        'type' => 'success',
        'text' => "Password reset for {$staff['full_name']}. New password: {$newPassword} — shown once, copy it now and share it with them directly.",
    ];
    header('Location: view.php?id=' . $id);
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
$pageTitle = $staff['full_name'];
$activeNav = 'staff';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <div class="toolbar">
    <h1 style="margin:0;"><?= h($staff['full_name']) ?></h1>
    <div class="table-actions">
      <a href="../attendance/staff.php?id=<?= (int) $id ?>" class="btn btn-sm btn-secondary">Attendance History</a>
      <a href="../payout/index.php?staff_id=<?= (int) $id ?>" class="btn btn-sm btn-secondary">Payouts</a>
      <a href="edit.php?id=<?= (int) $id ?>" class="btn btn-sm btn-secondary">Edit</a>
      <form method="post" style="display:inline;" data-confirm="Reset <?= h($staff['full_name']) ?>'s password? A new one will be generated and shown here once — you'll need to share it with them directly.">
        <input type="hidden" name="action" value="reset_password">
        <button type="submit" class="btn btn-sm btn-secondary">Reset Password</button>
      </form>
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
      <div><strong>Status:</strong> <span class="badge badge-<?= badgeVariant($staff['status']) ?>"><?= h($staff['status']) ?></span></div>
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
          <tr><td colspan="5" style="color:var(--color-text-muted);">No overrides recorded — always used the universal default.</td></tr>
        <?php endif; ?>
        <?php foreach ($history as $row): ?>
          <tr>
            <td><?= h($row['effective_from']) ?></td>
            <?php if ($row['work_start_time'] !== null): ?>
              <td><?= h($row['work_start_time']) ?></td>
              <td><?= h($row['work_end_time']) ?></td>
            <?php else: ?>
              <td colspan="2" style="color:var(--color-text-muted);">(revert to universal default)</td>
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
        <span style="color:var(--color-text-muted);">No salary set yet — set one before generating a payout for this staff member.</span>
      <?php endif; ?>
    </p>
  </div>

  <h2>Salary History</h2>
  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Effective From</th><th>Monthly Salary</th><th>Reason</th><th>Set By</th><th>Recorded At</th></tr>
      </thead>
      <tbody>
        <?php if (!$salaryHistory): ?>
          <tr><td colspan="5" style="color:var(--color-text-muted);">No salary recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($salaryHistory as $row): ?>
          <tr>
            <td><?= h($row['effective_from']) ?></td>
            <td><?= h(number_format((float) $row['monthly_salary'], 2)) ?></td>
            <td><?= $row['reason'] !== '' ? h($row['reason']) : '—' ?></td>
            <td><?= h($row['set_by_name'] ?? '—') ?></td>
            <td><?= h($row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
