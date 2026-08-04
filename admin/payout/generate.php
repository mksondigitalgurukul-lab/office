<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$error = '';
$month = $_POST['month'] ?? date('Y-m');
$staffId = (int) ($_POST['staff_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $error = 'Choose a valid month.';
    } else {
        if ($staffId > 0) {
            $stmt = $pdo->prepare("SELECT id, full_name FROM staff WHERE id = ? AND status = 'active'");
            $stmt->execute([$staffId]);
            $targets = $stmt->fetchAll();
        } else {
            $targets = $pdo->query("SELECT id, full_name FROM staff WHERE status = 'active' ORDER BY full_name")->fetchAll();
        }

        $generated    = [];
        $skippedLocked = [];
        $skippedNoSalary = [];

        foreach ($targets as $t) {
            $stmt = $pdo->prepare('SELECT status, bonus, forgiven_amount FROM payouts WHERE staff_id = ? AND month = ?');
            $stmt->execute([$t['id'], $month]);
            $existing = $stmt->fetch();

            if ($existing && in_array($existing['status'], ['finalized', 'paid'], true)) {
                $skippedLocked[] = $t['full_name'];
                continue;
            }

            $figures = computePayoutFigures((int) $t['id'], $month);
            if ($figures === null) {
                $skippedNoSalary[] = $t['full_name'];
                continue;
            }

            $bonus          = $existing ? (float) $existing['bonus'] : 0.0;
            $forgivenAmount = $existing && $existing['forgiven_amount'] !== null ? (float) $existing['forgiven_amount'] : null;
            $netPayout      = round($figures['base_salary'] - effectiveDeduction($figures['unpaid_deduction'], $forgivenAmount) + $bonus, 2);

            $stmt = $pdo->prepare(
                'INSERT INTO payouts (staff_id, month, base_salary, present_days, absent_days, on_leave_days, wfh_days, half_days, unpaid_deduction, bonus, net_payout, status, generated_by, generated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', ?, NOW())
                 ON DUPLICATE KEY UPDATE
                   base_salary = VALUES(base_salary),
                   present_days = VALUES(present_days),
                   absent_days = VALUES(absent_days),
                   on_leave_days = VALUES(on_leave_days),
                   wfh_days = VALUES(wfh_days),
                   half_days = VALUES(half_days),
                   unpaid_deduction = VALUES(unpaid_deduction),
                   bonus = VALUES(bonus),
                   net_payout = VALUES(net_payout),
                   status = \'draft\',
                   generated_by = VALUES(generated_by),
                   generated_at = NOW()'
            );
            $stmt->execute([
                $t['id'], $month, $figures['base_salary'],
                $figures['present_days'], $figures['absent_days'], $figures['on_leave_days'],
                $figures['wfh_days'], $figures['half_days'],
                $figures['unpaid_deduction'], $bonus, $netPayout, $admin['id'],
            ]);

            $generated[] = $t['full_name'];
        }

        $parts = [];
        if ($generated) {
            $parts[] = count($generated) . ' generated (' . implode(', ', $generated) . ')';
        }
        if ($skippedLocked) {
            $parts[] = count($skippedLocked) . ' skipped, already finalized/paid (' . implode(', ', $skippedLocked) . ')';
        }
        if ($skippedNoSalary) {
            $parts[] = count($skippedNoSalary) . ' skipped, no salary set (' . implode(', ', $skippedNoSalary) . ')';
        }

        $_SESSION['flash'] = [
            'type' => $generated ? 'success' : 'error',
            'text' => $parts ? implode('. ', $parts) . '.' : 'No active staff to generate for.',
        ];
        header('Location: index.php?month=' . urlencode($month));
        exit;
    }
}

$staffList = $pdo->query("SELECT id, full_name FROM staff WHERE status = 'active' ORDER BY full_name")->fetchAll();
$pageTitle = 'Generate Payout';
$activeNav = 'payout';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <h1>Generate Payout</h1>
  <p style="color:var(--color-text-muted);">Calculates base salary, attendance-based deduction, and net payout as a <strong>draft</strong> for review. Staff with no salary set are skipped; payouts already finalized or paid are never overwritten here — use "Regenerate" on that payout's page instead.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:420px;">
    <form method="post" novalidate>
      <div class="field">
        <label for="month">Month</label>
        <input type="month" id="month" name="month" required value="<?= h($month) ?>">
      </div>
      <div class="field">
        <label for="staff_id">Staff</label>
        <select id="staff_id" name="staff_id">
          <option value="0">All active staff</option>
          <?php foreach ($staffList as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= $staffId === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn">Generate</button>
      <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
