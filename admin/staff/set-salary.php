<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    header('Location: index.php');
    exit;
}

$error         = '';
$monthlySalary = '';
$effectiveFrom = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monthlySalary = trim($_POST['monthly_salary'] ?? '');
    $effectiveFrom = trim($_POST['effective_from'] ?? '');

    $effectiveFromValid = (bool) DateTime::createFromFormat('Y-m-d', $effectiveFrom);

    if (!is_numeric($monthlySalary) || (float) $monthlySalary <= 0) {
        $error = 'Enter a monthly salary greater than zero.';
    } elseif (!$effectiveFromValid) {
        $error = 'Enter a valid effective-from date.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO staff_salary (staff_id, monthly_salary, effective_from, set_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$id, (float) $monthlySalary, $effectiveFrom, $admin['id']]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Salary updated. A new history row was recorded; past rows were left untouched.'];
        header('Location: view.php?id=' . $id);
        exit;
    }
}
$pageTitle = 'Set Salary — ' . $staff['full_name'];
$activeNav = 'staff';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <h1>Set Salary — <?= h($staff['full_name']) ?></h1>
  <p style="color:var(--color-text-muted);">This always adds a new history row and never edits a past one — the same append-only pattern as work timing — so past payouts stay checkable against the salary that applied at the time.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post" novalidate>
      <input type="hidden" name="id" value="<?= (int) $id ?>">

      <div class="field">
        <label for="monthly_salary">Monthly Salary</label>
        <input type="text" id="monthly_salary" name="monthly_salary" required inputmode="decimal" value="<?= h($monthlySalary) ?>" placeholder="50000.00">
      </div>

      <div class="field">
        <label for="effective_from">Effective From</label>
        <input type="date" id="effective_from" name="effective_from" required value="<?= h($effectiveFrom) ?>">
        <span class="field-hint">Can be a future date — it only becomes "current" once that date arrives.</span>
      </div>

      <button type="submit" class="btn">Save Salary</button>
      <a href="view.php?id=<?= (int) $id ?>" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
