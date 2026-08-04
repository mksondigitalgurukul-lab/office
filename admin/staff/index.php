<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$status    = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$workMode  = $_GET['work_mode'] ?? '';
$search    = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}
if ($department !== '') {
    $where[]  = 'department = ?';
    $params[] = $department;
}
if ($workMode !== '' && in_array($workMode, ['office', 'wfh', 'hybrid'], true)) {
    $where[]  = 'work_mode = ?';
    $params[] = $workMode;
}
if ($search !== '') {
    $where[]  = '(full_name LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT * FROM staff';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY full_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffList = $stmt->fetchAll();

$departments = $pdo->query('SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department <> \'\' ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff — <?= h(APP_NAME) ?></title>
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
  <div class="toolbar">
    <h1 style="margin:0;">Staff</h1>
    <a href="add.php" class="btn">+ Add Staff</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <form method="get" class="filter-bar">
    <div class="field">
      <label for="q">Search</label>
      <input type="text" id="q" name="q" placeholder="Name or email" value="<?= h($search) ?>">
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div class="field">
      <label for="department">Department</label>
      <select id="department" name="department">
        <option value="">All</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= h($dept) ?>" <?= $department === $dept ? 'selected' : '' ?>><?= h($dept) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="work_mode">Work Mode</label>
      <select id="work_mode" name="work_mode">
        <option value="">All</option>
        <option value="office" <?= $workMode === 'office' ? 'selected' : '' ?>>Office</option>
        <option value="wfh" <?= $workMode === 'wfh' ? 'selected' : '' ?>>WFH</option>
        <option value="hybrid" <?= $workMode === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
      </select>
    </div>
    <div class="field" style="min-width:auto;">
      <button type="submit" class="btn btn-sm">Filter</button>
      <a href="index.php" class="btn btn-sm btn-secondary">Reset</a>
    </div>
  </form>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Designation</th>
          <th>Department</th>
          <th>Work Mode</th>
          <th>Status</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$staffList): ?>
          <tr><td colspan="8" style="color:var(--color-muted);">No staff found.</td></tr>
        <?php endif; ?>
        <?php foreach ($staffList as $s): ?>
          <tr>
            <td><?= h($s['full_name']) ?></td>
            <td><?= h($s['email']) ?></td>
            <td><?= h($s['designation']) ?></td>
            <td><?= h($s['department']) ?></td>
            <td><?= h($s['work_mode']) ?></td>
            <td><span class="badge badge-<?= $s['status'] === 'active' ? 'active' : 'inactive' ?>"><?= h($s['status']) ?></span></td>
            <td><?= h($s['joined_date']) ?></td>
            <td class="table-actions">
              <a href="view.php?id=<?= (int) $s['id'] ?>">View</a>
              <a href="edit.php?id=<?= (int) $s['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
