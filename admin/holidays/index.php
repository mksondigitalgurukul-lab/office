<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date = trim($_POST['holiday_date'] ?? '');
        $name = trim($_POST['name'] ?? '');

        if (!DateTime::createFromFormat('Y-m-d', $date)) {
            $error = 'Enter a valid date.';
        } elseif ($name === '') {
            $error = 'Enter a name for the holiday.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO holidays (holiday_date, name, applies_to) VALUES (?, ?, 'all')");
            $stmt->execute([$date, $name]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => "Holiday '{$name}' added for {$date}."];
            header('Location: index.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id   = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM holidays WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Holiday deleted.'];
        header('Location: index.php');
        exit;
    } elseif ($action === 'generate_sundays') {
        $monthsAhead = (int) ($_POST['months_ahead'] ?? 12);

        if ($monthsAhead < 1 || $monthsAhead > 24) {
            $error = 'Enter a number of months between 1 and 24.';
        } else {
            $cursor = new DateTime('today');
            while ((int) $cursor->format('N') !== 7) {
                $cursor->modify('+1 day');
            }
            $end = (new DateTime('today'))->modify("+{$monthsAhead} months");

            $checkStmt  = $pdo->prepare('SELECT COUNT(*) FROM holidays WHERE holiday_date = ?');
            $insertStmt = $pdo->prepare("INSERT INTO holidays (holiday_date, name, applies_to) VALUES (?, 'Sunday', 'all')");

            $inserted = 0;
            while ($cursor <= $end) {
                $dateStr = $cursor->format('Y-m-d');
                $checkStmt->execute([$dateStr]);
                if ((int) $checkStmt->fetchColumn() === 0) {
                    $insertStmt->execute([$dateStr]);
                    $inserted++;
                }
                $cursor->modify('+7 days');
            }

            $_SESSION['flash'] = [
                'type' => 'success',
                'text' => "Generated {$inserted} Sunday holiday(s) over the next {$monthsAhead} month(s) (dates that already had a holiday were left untouched).",
            ];
            header('Location: index.php');
            exit;
        }
    }
}

$showAll = isset($_GET['all']);
$today   = date('Y-m-d');

if ($showAll) {
    $holidays = $pdo->query('SELECT * FROM holidays ORDER BY holiday_date ASC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM holidays WHERE holiday_date >= ? ORDER BY holiday_date ASC');
    $stmt->execute([$today]);
    $holidays = $stmt->fetchAll();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = 'Holidays';
$activeNav = 'holidays';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <div class="toolbar">
    <h1 style="margin:0;">Holidays</h1>
    <div class="table-actions">
      <?php if ($showAll): ?>
        <a href="index.php" class="btn btn-sm btn-secondary">Show upcoming only</a>
      <?php else: ?>
        <a href="index.php?all=1" class="btn btn-sm btn-secondary">Show all (incl. past)</a>
      <?php endif; ?>
    </div>
  </div>
  <p style="color:var(--color-text-muted);">Any date listed here blocks staff check-in company-wide for that day —
    see <code>getHolidayName()</code>. Sundays are the one exception: a staff member can still check in on a Sunday
    to log it as extra/voluntary work (see "Sunday extra work" note below), and <code>cron/mark-absent.php</code>
    never marks anyone absent on a holiday, Sunday included.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Date</th><th>Day</th><th>Name</th><th>Type</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$holidays): ?>
          <tr><td colspan="5" style="color:var(--color-text-muted);">No holidays <?= $showAll ? '' : 'upcoming' ?>.</td></tr>
        <?php endif; ?>
        <?php foreach ($holidays as $hRow): ?>
          <?php $isSunday = (int) date('N', strtotime($hRow['holiday_date'])) === 7; ?>
          <tr>
            <td><?= h($hRow['holiday_date']) ?></td>
            <td><?= h(date('l', strtotime($hRow['holiday_date']))) ?></td>
            <td><?= h($hRow['name']) ?></td>
            <td>
              <?php if ($isSunday): ?>
                <span class="badge badge-neutral">Weekly (Sunday)</span>
              <?php else: ?>
                <span class="badge badge-info">Company Holiday</span>
              <?php endif; ?>
            </td>
            <td class="table-actions">
              <form method="post" style="display:inline;" data-confirm="Delete the holiday '<?= h($hRow['name']) ?>' on <?= h($hRow['holiday_date']) ?>?">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $hRow['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2>Add a Holiday</h2>
  <div class="card" style="max-width:420px; margin-bottom:16px;">
    <form method="post" novalidate>
      <input type="hidden" name="action" value="add">
      <div class="field">
        <label for="holiday_date">Date</label>
        <input type="date" id="holiday_date" name="holiday_date" required>
      </div>
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required placeholder="e.g. Diwali, Independence Day">
      </div>
      <button type="submit" class="btn">Add Holiday</button>
    </form>
  </div>

  <h2>Generate Next Year's Sundays</h2>
  <p style="color:var(--color-text-muted);">Bulk-adds every Sunday from today onward as a default holiday, so you
    don't have to add all 52 by hand. Dates that already have a holiday (e.g. a named holiday that happens to fall
    on a Sunday) are left as-is — this never overwrites an existing entry.</p>
  <div class="card" style="max-width:420px;">
    <form method="post" novalidate>
      <input type="hidden" name="action" value="generate_sundays">
      <div class="field">
        <label for="months_ahead">Months ahead</label>
        <input type="number" id="months_ahead" name="months_ahead" min="1" max="24" value="12" required>
        <span class="field-hint">12 covers the next full year.</span>
      </div>
      <button type="submit" class="btn">Generate Sundays</button>
    </form>
  </div>

  <h2>Sunday extra work</h2>
  <p style="color:var(--color-text-muted);">Sundays are treated as a default day off (like any other holiday — no
    check-in required, and the daily absent-marker never penalizes anyone for not working). But unlike a named
    company holiday, a staff member <strong>can still check in on a Sunday</strong> from their Attendance page if
    they choose to work — it's logged as a normal attendance row (present/late/half-day, computed the same way as
    any other day) so it's visible in the admin attendance monitor and reports. It does <strong>not</strong>
    automatically add anything to that month's payout — if you want to pay extra for a worked Sunday, add it
    manually as a bonus when you generate that staff member's payout.</p>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
