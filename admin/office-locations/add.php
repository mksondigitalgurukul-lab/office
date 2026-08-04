<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$error  = '';
$values = ['location_name' => '', 'ip_address' => '', 'is_active' => true];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['location_name'] = trim($_POST['location_name'] ?? '');
    $values['ip_address']    = trim($_POST['ip_address'] ?? '');
    $values['is_active']     = isset($_POST['is_active']);

    if ($values['location_name'] === '' || $values['ip_address'] === '') {
        $error = 'Location name and IP address are required.';
    } elseif (!filter_var($values['ip_address'], FILTER_VALIDATE_IP)) {
        $error = 'Enter a valid IPv4 or IPv6 address.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO office_locations (location_name, ip_address, is_active) VALUES (?, ?, ?)');
        $stmt->execute([$values['location_name'], $values['ip_address'], $values['is_active'] ? 1 : 0]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Location added.'];
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Office Location — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><?= h(APP_NAME) ?></div>
  <div class="user-info">
    <span><?= h($admin['name']) ?> (<?= h($admin['role']) ?>)</span>
    <a href="../logout.php">Log out</a>
  </div>
</div>
<nav class="nav">
  <a href="../dashboard.php">Dashboard</a>
  <a href="../staff/index.php">Staff</a>
  <a href="../attendance/index.php">Attendance</a>
  <a href="../leave/index.php">Leave</a>
  <a href="../wfh/index.php">WFH</a>
  <a href="index.php"><strong>Office Locations</strong></a>
  <a href="../payout.php">Payout</a>
  <a href="../reports.php">Reports</a>
  <a href="../settings.php">Settings</a>
  <a href="../../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <h1>Add Office Location</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:420px;">
    <form method="post" novalidate>
      <div class="field">
        <label for="location_name">Location Name</label>
        <input type="text" id="location_name" name="location_name" required value="<?= h($values['location_name']) ?>">
      </div>
      <div class="field">
        <label for="ip_address">IP Address</label>
        <input type="text" id="ip_address" name="ip_address" required value="<?= h($values['ip_address']) ?>" placeholder="203.0.113.10">
        <span class="field-hint">The public IP this office's WiFi shows to the internet.</span>
      </div>
      <div class="field">
        <label><input type="checkbox" name="is_active" <?= $values['is_active'] ? 'checked' : '' ?>> Active</label>
      </div>
      <button type="submit" class="btn">Add Location</button>
      <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
