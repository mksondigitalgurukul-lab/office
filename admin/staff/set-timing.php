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

$error = '';
$mode           = 'default';
$startTime      = getSetting('default_work_start_time', '09:30');
$endTime        = getSetting('default_work_end_time', '18:30');
$effectiveFrom  = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode          = ($_POST['mode'] ?? 'default') === 'custom' ? 'custom' : 'default';
    $startTime     = trim($_POST['work_start_time'] ?? '');
    $endTime       = trim($_POST['work_end_time'] ?? '');
    $effectiveFrom = trim($_POST['effective_from'] ?? '');

    $effectiveFromValid = (bool) DateTime::createFromFormat('Y-m-d', $effectiveFrom);

    if (!$effectiveFromValid) {
        $error = 'Enter a valid effective-from date.';
    } elseif ($mode === 'custom' && ($startTime === '' || $endTime === '')) {
        $error = 'Enter both a start and end time, or choose "Use universal default".';
    } elseif ($mode === 'custom' && $startTime >= $endTime) {
        $error = 'Start time must be before end time.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO staff_work_time_history (staff_id, work_start_time, work_end_time, effective_from, set_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $mode === 'custom' ? $startTime : null,
            $mode === 'custom' ? $endTime : null,
            $effectiveFrom,
            $admin['id'],
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Work timing updated. A new history row was recorded; past rows were left untouched.'];
        header('Location: view.php?id=' . $id);
        exit;
    }
}
$pageTitle = 'Set Timing — ' . $staff['full_name'];
$activeNav = 'staff';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <h1>Set Work Timing — <?= h($staff['full_name']) ?></h1>
  <p style="color:var(--color-text-muted);">This always adds a new history row and never edits a past one, so historical attendance stays checkable against the timing that applied on that date.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post" novalidate>
      <input type="hidden" name="id" value="<?= (int) $id ?>">

      <div class="field">
        <label><input type="radio" name="mode" value="default" <?= $mode === 'default' ? 'checked' : '' ?> onclick="document.getElementById('customFields').style.display='none';"> Use universal default (<?= h(getSetting('default_work_start_time')) ?> – <?= h(getSetting('default_work_end_time')) ?>)</label>
      </div>
      <div class="field">
        <label><input type="radio" name="mode" value="custom" <?= $mode === 'custom' ? 'checked' : '' ?> onclick="document.getElementById('customFields').style.display='flex';"> Set custom hours</label>
      </div>

      <div id="customFields" class="field-row" style="display:<?= $mode === 'custom' ? 'flex' : 'none' ?>;">
        <div class="field">
          <label for="work_start_time">Start Time</label>
          <input type="time" id="work_start_time" name="work_start_time" value="<?= h($mode === 'custom' ? $startTime : '') ?>">
        </div>
        <div class="field">
          <label for="work_end_time">End Time</label>
          <input type="time" id="work_end_time" name="work_end_time" value="<?= h($mode === 'custom' ? $endTime : '') ?>">
        </div>
      </div>

      <div class="field">
        <label for="effective_from">Effective From</label>
        <input type="date" id="effective_from" name="effective_from" required value="<?= h($effectiveFrom) ?>">
        <span class="field-hint">Can be a future date — it only becomes "current" once that date arrives.</span>
      </div>

      <button type="submit" class="btn">Save Timing</button>
      <a href="view.php?id=<?= (int) $id ?>" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
