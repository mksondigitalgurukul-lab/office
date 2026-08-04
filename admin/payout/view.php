<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT p.*, s.full_name, s.designation, s.department, ga.name AS generated_by_name, fa.name AS forgiven_by_name
     FROM payouts p
     JOIN staff s ON s.id = p.staff_id
     LEFT JOIN admins ga ON ga.id = p.generated_by
     LEFT JOIN admins fa ON fa.id = p.forgiven_by
     WHERE p.id = ?'
);
$stmt->execute([$id]);
$payout = $stmt->fetch();

if (!$payout) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $forgivenAmount = $payout['forgiven_amount'] !== null ? (float) $payout['forgiven_amount'] : null;

    if ($action === 'update_bonus' && $payout['status'] === 'draft') {
        $bonus = trim($_POST['bonus'] ?? '0');
        if (!is_numeric($bonus) || (float) $bonus < 0) {
            $error = 'Enter a bonus of 0 or more.';
        } else {
            $netPayout = round((float) $payout['base_salary'] - effectiveDeduction((float) $payout['unpaid_deduction'], $forgivenAmount) + (float) $bonus, 2);
            $stmt = $pdo->prepare('UPDATE payouts SET bonus = ?, net_payout = ? WHERE id = ?');
            $stmt->execute([(float) $bonus, $netPayout, $id]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Bonus updated.'];
            header('Location: view.php?id=' . $id);
            exit;
        }
    } elseif ($action === 'forgive_deduction' && $payout['status'] === 'draft') {
        $remaining = effectiveDeduction((float) $payout['unpaid_deduction'], $forgivenAmount);
        if ($remaining <= 0) {
            $error = 'There is no remaining unpaid deduction to forgive.';
        } else {
            $newForgivenAmount = (float) $payout['unpaid_deduction'];
            $netPayout = round((float) $payout['base_salary'] - 0 + (float) $payout['bonus'], 2);
            $stmt = $pdo->prepare('UPDATE payouts SET forgiven_amount = ?, forgiven_by = ?, forgiven_at = NOW(), net_payout = ? WHERE id = ?');
            $stmt->execute([$newForgivenAmount, $admin['id'], $netPayout, $id]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Unpaid deduction forgiven.'];
            header('Location: view.php?id=' . $id);
            exit;
        }
    } elseif ($action === 'unforgive_deduction' && $payout['status'] === 'draft') {
        $netPayout = round((float) $payout['base_salary'] - (float) $payout['unpaid_deduction'] + (float) $payout['bonus'], 2);
        $stmt = $pdo->prepare('UPDATE payouts SET forgiven_amount = NULL, forgiven_by = NULL, forgiven_at = NULL, net_payout = ? WHERE id = ?');
        $stmt->execute([$netPayout, $id]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Forgiveness reverted — full deduction re-applied.'];
        header('Location: view.php?id=' . $id);
        exit;
    } elseif ($action === 'finalize' && $payout['status'] === 'draft') {
        $stmt = $pdo->prepare("UPDATE payouts SET status = 'finalized' WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Payout finalized.'];
        header('Location: view.php?id=' . $id);
        exit;
    } elseif ($action === 'mark_paid' && $payout['status'] === 'finalized') {
        $stmt = $pdo->prepare("UPDATE payouts SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'finalized'");
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Payout marked as paid.'];
        header('Location: view.php?id=' . $id);
        exit;
    } elseif ($action === 'regenerate') {
        $figures = computePayoutFigures((int) $payout['staff_id'], $payout['month']);
        if ($figures === null) {
            $error = 'Cannot regenerate — this staff member no longer has a salary set.';
        } else {
            $bonus     = (float) $payout['bonus'];
            $netPayout = round($figures['base_salary'] - effectiveDeduction($figures['unpaid_deduction'], $forgivenAmount) + $bonus, 2);
            $stmt = $pdo->prepare(
                "UPDATE payouts SET
                    base_salary = ?, present_days = ?, absent_days = ?, on_leave_days = ?,
                    wfh_days = ?, half_days = ?, unpaid_deduction = ?, net_payout = ?,
                    status = 'draft', paid_at = NULL, generated_by = ?, generated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([
                $figures['base_salary'], $figures['present_days'], $figures['absent_days'],
                $figures['on_leave_days'], $figures['wfh_days'], $figures['half_days'],
                $figures['unpaid_deduction'], $netPayout, $admin['id'], $id,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Payout regenerated from current attendance/leave/salary data and reset to draft. Bonus and any forgiven deduction were kept.'];
            header('Location: view.php?id=' . $id);
            exit;
        }
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusLabels = ['draft' => 'Draft', 'finalized' => 'Finalized', 'paid' => 'Paid'];
$companyName  = getSetting('company_name', APP_NAME);
$pageTitle = 'Payout — ' . $payout['full_name'] . ' — ' . $payout['month'];
$activeNav = 'payout';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <div class="toolbar no-print">
    <h1 style="margin:0;">Payout — <?= h($payout['full_name']) ?> — <?= h($payout['month']) ?></h1>
    <div class="table-actions">
      <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">Print / Save as PDF</button>
      <a href="index.php" class="btn btn-sm btn-secondary">Back to list</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error no-print"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?> no-print"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <div class="card payslip" style="margin-bottom:16px;">
    <div class="toolbar" style="margin-bottom:16px;">
      <div>
        <h2 style="margin:0 0 4px 0;"><?= h($companyName) ?></h2>
        <p style="margin:0; color:var(--color-text-muted);">Payslip for <?= h($payout['month']) ?></p>
      </div>
      <span class="badge badge-<?= badgeVariant($payout['status']) ?>" style="font-size:0.9rem;"><?= h($statusLabels[$payout['status']] ?? $payout['status']) ?></span>
    </div>

    <div class="field-row" style="margin-bottom:16px;">
      <div><strong>Staff:</strong> <?= h($payout['full_name']) ?></div>
      <div><strong>Designation:</strong> <?= h($payout['designation'] ?? '—') ?></div>
      <div><strong>Department:</strong> <?= h($payout['department'] ?? '—') ?></div>
    </div>

    <table class="db-table" style="margin-bottom:16px;">
      <tbody>
        <tr><td>Base Salary</td><td><?= h(number_format((float) $payout['base_salary'], 2)) ?></td></tr>
        <tr><td>Present Days (incl. late)</td><td><?= (int) $payout['present_days'] ?></td></tr>
        <tr><td>Half Days</td><td><?= (int) $payout['half_days'] ?></td></tr>
        <tr><td>Absent Days</td><td><?= (int) $payout['absent_days'] ?></td></tr>
        <tr><td>On Leave Days</td><td><?= (int) $payout['on_leave_days'] ?></td></tr>
        <tr><td>WFH Days (of the above)</td><td><?= (int) $payout['wfh_days'] ?></td></tr>
        <tr>
          <td>Unpaid Deduction</td>
          <td>
            <?php if ($payout['forgiven_amount'] !== null): ?>
              <span style="text-decoration:line-through; color:var(--color-text-muted);">&minus; <?= h(number_format((float) $payout['unpaid_deduction'], 2)) ?></span>
              <span class="badge badge-success">Forgiven</span>
              <div style="margin-top:6px; padding:8px 10px; background:var(--color-success-soft); border-radius:var(--radius-sm); font-size:0.85rem;">
                <strong><?= h(number_format((float) $payout['forgiven_amount'], 2)) ?></strong> forgiven by <?= h($payout['forgiven_by_name'] ?? '—') ?> on <?= h($payout['forgiven_at']) ?>
              </div>
            <?php else: ?>
              &minus; <?= h(number_format((float) $payout['unpaid_deduction'], 2)) ?>
            <?php endif; ?>
          </td>
        </tr>
        <tr><td>Bonus</td><td>+ <?= h(number_format((float) $payout['bonus'], 2)) ?></td></tr>
        <tr><td><strong>Net Payout</strong></td><td><strong><?= h(number_format((float) $payout['net_payout'], 2)) ?></strong></td></tr>
      </tbody>
    </table>

    <p style="margin:0; color:var(--color-text-muted); font-size:0.85rem;">
      Generated by <?= h($payout['generated_by_name'] ?? '—') ?> on <?= h($payout['generated_at']) ?>
      <?php if ($payout['paid_at']): ?>
        &middot; Paid on <?= h($payout['paid_at']) ?>
      <?php endif; ?>
    </p>
  </div>

  <?php if ($payout['status'] === 'draft'): ?>
    <div class="card no-print" style="max-width:420px; margin-bottom:16px;">
      <h2 style="margin-top:0;">Adjust Bonus</h2>
      <form method="post" novalidate>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="action" value="update_bonus">
        <div class="field">
          <label for="bonus">Bonus</label>
          <input type="text" id="bonus" name="bonus" inputmode="decimal" value="<?= h(number_format((float) $payout['bonus'], 2, '.', '')) ?>">
        </div>
        <button type="submit" class="btn">Update Bonus</button>
      </form>
    </div>

    <div class="card no-print" style="max-width:420px; margin-bottom:16px;">
      <h2 style="margin-top:0;">Unpaid Deduction</h2>
      <?php if ($payout['forgiven_amount'] !== null): ?>
        <p style="color:var(--color-text-muted);">Forgiven — <?= h(number_format((float) $payout['forgiven_amount'], 2)) ?> by <?= h($payout['forgiven_by_name'] ?? '—') ?>.</p>
        <form method="post" data-confirm="Revert forgiveness and re-apply the full unpaid deduction?">
          <input type="hidden" name="id" value="<?= (int) $id ?>">
          <input type="hidden" name="action" value="unforgive_deduction">
          <button type="submit" class="btn btn-secondary">Un-forgive</button>
        </form>
      <?php elseif ((float) $payout['unpaid_deduction'] > 0): ?>
        <p style="color:var(--color-text-muted);">Waive the <?= h(number_format((float) $payout['unpaid_deduction'], 2)) ?> unpaid deduction for this payout — net payout goes up by that amount. Recorded against your admin account.</p>
        <form method="post" data-confirm="Forgive the full unpaid deduction for this payout?">
          <input type="hidden" name="id" value="<?= (int) $id ?>">
          <input type="hidden" name="action" value="forgive_deduction">
          <button type="submit" class="btn">Forgive Deduction</button>
        </form>
      <?php else: ?>
        <p style="color:var(--color-text-muted); margin:0;">No unpaid deduction on this payout.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card no-print" style="max-width:420px;">
    <h2 style="margin-top:0;">Actions</h2>
    <div class="table-actions" style="flex-wrap:wrap;">
      <?php if ($payout['status'] === 'draft'): ?>
        <form method="post" data-confirm="Finalize this payout? It will be locked from recalculation until explicitly regenerated.">
          <input type="hidden" name="id" value="<?= (int) $id ?>">
          <input type="hidden" name="action" value="finalize">
          <button type="submit" class="btn">Finalize</button>
        </form>
      <?php endif; ?>
      <?php if ($payout['status'] === 'finalized'): ?>
        <form method="post" data-confirm="Mark this payout as paid?">
          <input type="hidden" name="id" value="<?= (int) $id ?>">
          <input type="hidden" name="action" value="mark_paid">
          <button type="submit" class="btn">Mark as Paid</button>
        </form>
      <?php endif; ?>
      <form method="post" data-confirm="Regenerate from current attendance/leave/salary data? This resets status to draft (bonus is kept).">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="action" value="regenerate">
        <button type="submit" class="btn btn-secondary">Regenerate</button>
      </form>
    </div>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
