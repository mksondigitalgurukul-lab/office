<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$error  = '';
$values = [
    'full_name'   => '',
    'email'       => '',
    'phone'       => '',
    'designation' => '',
    'department'  => '',
    'work_mode'   => 'office',
    'joined_date' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = trim($_POST[$key] ?? '');
    }
    $password       = (string) ($_POST['password'] ?? '');
    $autoGenerate   = empty($password);

    if ($values['full_name'] === '' || $values['email'] === '') {
        $error = 'Name and email are required.';
    } elseif (!in_array($values['work_mode'], ['office', 'wfh', 'hybrid'], true)) {
        $error = 'Invalid work mode.';
    } elseif (!$autoGenerate && strlen($password) < 8) {
        $error = 'Password must be at least 8 characters (or leave blank to auto-generate one).';
    } else {
        if ($autoGenerate) {
            $password = substr(bin2hex(random_bytes(6)), 0, 10);
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO staff (full_name, email, password_hash, phone, designation, department, work_mode, joined_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $values['full_name'],
                $values['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $values['phone'] !== '' ? $values['phone'] : null,
                $values['designation'] !== '' ? $values['designation'] : null,
                $values['department'] !== '' ? $values['department'] : null,
                $values['work_mode'],
                $values['joined_date'] !== '' ? $values['joined_date'] : null,
                $admin['id'],
            ]);
            $newId = (int) $pdo->lastInsertId();

            $_SESSION['flash'] = [
                'type' => 'success',
                'text' => "Staff '{$values['full_name']}' created."
                    . ($autoGenerate ? " Auto-generated password: {$password} (shown once — save it now)." : ''),
            ];
            header('Location: view.php?id=' . $newId);
            exit;
        } catch (PDOException $e) {
            $error = 'Could not create staff (email may already be in use).';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Staff — <?= h(APP_NAME) ?></title>
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
  <a href="index.php"><strong>Staff</strong></a>
  <a href="../attendance/index.php">Attendance</a>
  <a href="../leave/index.php">Leave</a>
  <a href="../wfh/index.php">WFH</a>
  <a href="../office-locations/index.php">Office Locations</a>
  <a href="../payout.php">Payout</a>
  <a href="../reports.php">Reports</a>
  <a href="../settings.php">Settings</a>
  <a href="../../sql/index.php">DB Tools</a>
</nav>

<div class="container">
  <h1>Add Staff</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post" novalidate>
      <div class="field-row">
        <div class="field">
          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" required value="<?= h($values['full_name']) ?>">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?= h($values['email']) ?>">
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone" value="<?= h($values['phone']) ?>">
        </div>
        <div class="field">
          <label for="joined_date">Joined Date</label>
          <input type="date" id="joined_date" name="joined_date" value="<?= h($values['joined_date']) ?>">
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="designation">Designation</label>
          <input type="text" id="designation" name="designation" value="<?= h($values['designation']) ?>">
        </div>
        <div class="field">
          <label for="department">Department</label>
          <input type="text" id="department" name="department" value="<?= h($values['department']) ?>">
        </div>
      </div>

      <div class="field">
        <label for="work_mode">Work Mode</label>
        <select id="work_mode" name="work_mode">
          <option value="office" <?= $values['work_mode'] === 'office' ? 'selected' : '' ?>>Office</option>
          <option value="wfh" <?= $values['work_mode'] === 'wfh' ? 'selected' : '' ?>>WFH</option>
          <option value="hybrid" <?= $values['work_mode'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
        </select>
      </div>

      <div class="field">
        <label for="password">Initial Password</label>
        <input type="password" id="password" name="password" minlength="8">
        <span class="field-hint">Leave blank to auto-generate a password — it will be shown once after creation.</span>
      </div>

      <button type="submit" class="btn">Create Staff</button>
      <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
