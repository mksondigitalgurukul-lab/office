<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM office_locations WHERE id = ?');
$stmt->execute([$id]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: index.php');
    exit;
}

$error  = '';
$values = [
    'location_name' => $location['location_name'],
    'ip_address'    => $location['ip_address'],
    'is_active'     => (bool) $location['is_active'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['location_name'] = trim($_POST['location_name'] ?? '');
    $values['ip_address']    = trim($_POST['ip_address'] ?? '');
    $values['is_active']     = isset($_POST['is_active']);

    if ($values['location_name'] === '' || $values['ip_address'] === '') {
        $error = 'Location name and IP address are required.';
    } elseif (!filter_var($values['ip_address'], FILTER_VALIDATE_IP)) {
        $error = 'Enter a valid IPv4 or IPv6 address.';
    } else {
        $stmt = $pdo->prepare('UPDATE office_locations SET location_name = ?, ip_address = ?, is_active = ? WHERE id = ?');
        $stmt->execute([$values['location_name'], $values['ip_address'], $values['is_active'] ? 1 : 0, $id]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Location updated.'];
        header('Location: index.php');
        exit;
    }
}
$pageTitle = 'Edit Office Location';
$activeNav = 'office-locations';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <h1>Edit Office Location</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:420px;">
    <form method="post" novalidate>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <div class="field">
        <label for="location_name">Location Name</label>
        <input type="text" id="location_name" name="location_name" required value="<?= h($values['location_name']) ?>">
      </div>
      <div class="field">
        <label for="ip_address">IP Address</label>
        <input type="text" id="ip_address" name="ip_address" required value="<?= h($values['ip_address']) ?>">
        <span class="field-hint">
          Your current IP is <strong><?= h(getClientIp()) ?></strong> —
          if you're on the office WiFi right now,
          <a href="#" onclick="document.getElementById('ip_address').value='<?= h(getClientIp()) ?>'; return false;">use this IP</a>.
        </span>
      </div>
      <div class="field">
        <label><input type="checkbox" name="is_active" <?= $values['is_active'] ? 'checked' : '' ?>> Active</label>
      </div>
      <button type="submit" class="btn">Save Changes</button>
      <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
