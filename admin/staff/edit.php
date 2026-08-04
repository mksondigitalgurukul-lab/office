<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    header('Location: index.php');
    exit;
}

$error  = '';
$values = [
    'full_name'   => $staff['full_name'],
    'email'       => $staff['email'],
    'phone'       => $staff['phone'],
    'designation' => $staff['designation'],
    'department'  => $staff['department'],
    'work_mode'   => $staff['work_mode'],
    'joined_date' => $staff['joined_date'],
    'status'      => $staff['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = trim($_POST[$key] ?? '');
    }

    if ($values['full_name'] === '' || $values['email'] === '') {
        $error = 'Name and email are required.';
    } elseif (!in_array($values['work_mode'], ['office', 'wfh', 'hybrid'], true)) {
        $error = 'Invalid work mode.';
    } elseif (!in_array($values['status'], ['active', 'inactive'], true)) {
        $error = 'Invalid status.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE staff SET full_name = ?, email = ?, phone = ?, designation = ?, department = ?, work_mode = ?, joined_date = ?, status = ? WHERE id = ?'
            );
            $stmt->execute([
                $values['full_name'],
                $values['email'],
                $values['phone'] !== '' ? $values['phone'] : null,
                $values['designation'] !== '' ? $values['designation'] : null,
                $values['department'] !== '' ? $values['department'] : null,
                $values['work_mode'],
                $values['joined_date'] !== '' ? $values['joined_date'] : null,
                $values['status'],
                $id,
            ]);

            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Staff updated.'];
            header('Location: view.php?id=' . $id);
            exit;
        } catch (PDOException $e) {
            $error = 'Could not update staff (email may already be in use).';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Staff — <?= h(APP_NAME) ?></title>
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
  <h1>Edit Staff</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post" novalidate>
      <input type="hidden" name="id" value="<?= (int) $id ?>">

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

      <div class="field-row">
        <div class="field">
          <label for="work_mode">Work Mode</label>
          <select id="work_mode" name="work_mode">
            <option value="office" <?= $values['work_mode'] === 'office' ? 'selected' : '' ?>>Office</option>
            <option value="wfh" <?= $values['work_mode'] === 'wfh' ? 'selected' : '' ?>>WFH</option>
            <option value="hybrid" <?= $values['work_mode'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
          </select>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="active" <?= $values['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $values['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
          <span class="field-hint">Setting to Inactive is a soft delete — the record is kept.</span>
        </div>
      </div>

      <button type="submit" class="btn">Save Changes</button>
      <a href="view.php?id=<?= (int) $id ?>" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
