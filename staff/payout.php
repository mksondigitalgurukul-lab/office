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

$stmt = $pdo->prepare('SELECT * FROM payouts WHERE staff_id = ? ORDER BY month DESC');
$stmt->execute([$staff['id']]);
$payouts = $stmt->fetchAll();

$statusLabels = ['draft' => 'Draft', 'finalized' => 'Finalized', 'paid' => 'Paid'];

$pageTitle = 'Payout';
$activeNav = 'payout';
require __DIR__ . '/../includes/staff-header.php';
?>
  <h1>Payout</h1>
  <p style="color:var(--color-text-muted);">Your own monthly payout history. Draft figures may still change before an admin finalizes them.</p>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Month</th><th>Base Salary</th><th>Deduction</th><th>Bonus</th><th>Net Payout</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (!$payouts): ?>
          <tr><td colspan="6" style="color:var(--color-text-muted);">No payouts recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($payouts as $p): ?>
          <tr>
            <td><?= h($p['month']) ?></td>
            <td><?= h(number_format((float) $p['base_salary'], 2)) ?></td>
            <td><?= h(number_format((float) $p['unpaid_deduction'], 2)) ?></td>
            <td><?= h(number_format((float) $p['bonus'], 2)) ?></td>
            <td><strong><?= h(number_format((float) $p['net_payout'], 2)) ?></strong></td>
            <td><span class="badge badge-<?= badgeVariant($p['status']) ?>"><?= h($statusLabels[$p['status']] ?? $p['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
