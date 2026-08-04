<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$month   = $_GET['month'] ?? date('Y-m');
$status  = $_GET['status'] ?? '';
$staffId = (int) ($_GET['staff_id'] ?? 0);

$where  = ['1=1'];
$params = [];

if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $where[]  = 'p.month = ?';
    $params[] = $month;
}
if (in_array($status, ['draft', 'finalized', 'paid'], true)) {
    $where[]  = 'p.status = ?';
    $params[] = $status;
}
if ($staffId > 0) {
    $where[]  = 'p.staff_id = ?';
    $params[] = $staffId;
}

$sql = 'SELECT p.*, s.full_name
        FROM payouts p
        JOIN staff s ON s.id = p.staff_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.month DESC, s.full_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payouts = $stmt->fetchAll();

$totals = ['base_salary' => 0, 'unpaid_deduction' => 0, 'bonus' => 0, 'net_payout' => 0];
foreach ($payouts as $p) {
    $totals['base_salary']      += (float) $p['base_salary'];
    $totals['unpaid_deduction'] += (float) $p['unpaid_deduction'];
    $totals['bonus']            += (float) $p['bonus'];
    $totals['net_payout']       += (float) $p['net_payout'];
}

$staffList = $pdo->query("SELECT id, full_name FROM staff ORDER BY full_name")->fetchAll();

$statusLabels = ['draft' => 'Draft', 'finalized' => 'Finalized', 'paid' => 'Paid'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = 'Payout';
$activeNav = 'payout';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <div class="toolbar">
    <h1 style="margin:0;">Payout</h1>
    <a href="generate.php" class="btn">Generate Payout</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <form method="get" class="filter-bar">
    <div class="field">
      <label for="month">Month</label>
      <input type="month" id="month" name="month" value="<?= h($month) ?>">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="finalized" <?= $status === 'finalized' ? 'selected' : '' ?>>Finalized</option>
        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
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
    <div class="field" style="min-width:auto;">
      <button type="submit" class="btn btn-sm">Filter</button>
      <a href="index.php" class="btn btn-sm btn-secondary">Reset</a>
    </div>
  </form>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr>
          <th>Staff</th><th>Month</th><th>Base Salary</th><th>Deduction</th>
          <th>Bonus</th><th>Net Payout</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$payouts): ?>
          <tr><td colspan="8" style="color:var(--color-text-muted);">No payouts match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($payouts as $p): ?>
          <tr>
            <td><?= h($p['full_name']) ?></td>
            <td><?= h($p['month']) ?></td>
            <td><?= h(number_format((float) $p['base_salary'], 2)) ?></td>
            <td><?= h(number_format((float) $p['unpaid_deduction'], 2)) ?></td>
            <td><?= h(number_format((float) $p['bonus'], 2)) ?></td>
            <td><strong><?= h(number_format((float) $p['net_payout'], 2)) ?></strong></td>
            <td><span class="badge badge-<?= badgeVariant($p['status']) ?>"><?= h($statusLabels[$p['status']] ?? $p['status']) ?></span></td>
            <td><a href="view.php?id=<?= (int) $p['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($payouts): ?>
        <tfoot>
          <tr>
            <td colspan="2"><strong>Totals</strong></td>
            <td><strong><?= h(number_format($totals['base_salary'], 2)) ?></strong></td>
            <td><strong><?= h(number_format($totals['unpaid_deduction'], 2)) ?></strong></td>
            <td><strong><?= h(number_format($totals['bonus'], 2)) ?></strong></td>
            <td><strong><?= h(number_format($totals['net_payout'], 2)) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
