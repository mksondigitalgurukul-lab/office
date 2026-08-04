<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('login.php');
$admin = currentAdmin();
$pdo   = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existingKeys = $pdo->query('SELECT setting_key FROM settings')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($existingKeys as $key) {
        if (array_key_exists($key, $_POST)) {
            setSetting($key, trim($_POST[$key]));
        }
    }

    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Settings saved.'];
    header('Location: settings.php');
    exit;
}

$settings = $pdo->query('SELECT * FROM settings ORDER BY setting_key')->fetchAll();

$labels = [
    'company_name'             => 'Company Name',
    'default_work_start_time'  => 'Default Work Start Time',
    'default_work_end_time'    => 'Default Work End Time',
    'timezone'                 => 'Timezone',
    'attendance_grace_minutes' => 'Attendance Grace Period (minutes)',
];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Settings';
$activeNav = 'settings';
$basePath  = '';
require __DIR__ . '/../includes/admin-header.php';
?>
  <h1>Settings</h1>
  <p style="color:var(--color-text-muted);">Universal app configuration, stored in the <code>settings</code> table.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:480px;">
    <form method="post" novalidate>
      <?php foreach ($settings as $s): ?>
        <?php
          $key   = $s['setting_key'];
          $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
          $type  = 'text';
          if ($key === 'attendance_grace_minutes') {
              $type = 'number';
          } elseif (substr($key, -5) === '_time') {
              $type = 'time';
          }
        ?>
        <div class="field">
          <label for="setting_<?= h($key) ?>"><?= h($label) ?></label>
          <input
            type="<?= h($type) ?>"
            id="setting_<?= h($key) ?>"
            name="<?= h($key) ?>"
            value="<?= h($s['setting_value']) ?>"
            <?= $type === 'number' ? 'min="0"' : '' ?>
          >
          <span class="field-hint"><code><?= h($key) ?></code> &middot; last updated <?= h($s['updated_at']) ?></span>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="btn">Save Settings</button>
    </form>
  </div>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
