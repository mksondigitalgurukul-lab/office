<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$date = $_GET['date'] ?? date('Y-m-d');
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    $date = date('Y-m-d');
}

$department = $_GET['department'] ?? '';
$workMode   = $_GET['work_mode'] ?? '';

$where  = ["s.status = 'active'"];
$params = [':date' => $date];

if ($department !== '') {
    $where[]              = 's.department = :department';
    $params[':department'] = $department;
}
if ($workMode !== '' && in_array($workMode, ['office', 'wfh', 'hybrid'], true)) {
    $where[]             = 's.work_mode = :work_mode';
    $params[':work_mode'] = $workMode;
}

$sql = 'SELECT s.id, s.full_name, s.department, s.work_mode,
               a.check_in_time, a.check_out_time, a.work_location, a.status
        FROM staff s
        LEFT JOIN attendance a ON a.staff_id = s.id AND a.attendance_date = :date
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY s.full_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$departments = $pdo->query('SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department <> \'\' ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);

$holidayName = getHolidayName($date);

$statusLabels = [
    'present'  => 'Present',
    'late'     => 'Late',
    'half_day' => 'Half Day',
    'absent'   => 'Absent',
    'on_leave' => 'On Leave',
];
$locationLabels = [
    'office_verified' => 'Office (verified)',
    'office_manual'   => 'Office (manual)',
    'wfh'              => 'WFH',
    'unverified'       => 'Unverified',
];
$pageTitle = 'Attendance';
$activeNav = 'attendance';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <h1>Attendance</h1>

  <?php if ($holidayName): ?>
    <div class="alert alert-success">Holiday: <?= h($holidayName) ?> — no check-ins expected today.</div>
  <?php endif; ?>

  <form method="get" class="filter-bar">
    <div class="field">
      <label for="date">Date</label>
      <input type="date" id="date" name="date" value="<?= h($date) ?>">
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
      <a href="index.php" class="btn btn-sm btn-secondary">Today</a>
    </div>
  </form>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Department</th>
          <th>Work Mode</th>
          <th>Check In</th>
          <th>Check Out</th>
          <th>Location</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" style="color:var(--color-text-muted);">No active staff match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <?php $flag = $r['status'] === 'late' || $r['work_location'] === 'unverified'; ?>
          <tr class="<?= $flag ? 'row-flag' : '' ?>">
            <td><?= h($r['full_name']) ?></td>
            <td><?= h($r['department']) ?></td>
            <td><?= h($r['work_mode']) ?></td>
            <td><?= h($r['check_in_time'] ?? '—') ?></td>
            <td><?= h($r['check_out_time'] ?? '—') ?></td>
            <td>
              <?php if ($r['work_location']): ?>
                <span class="badge badge-<?= badgeVariant($r['work_location']) ?>"><?= h($locationLabels[$r['work_location']] ?? $r['work_location']) ?></span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status']): ?>
                <span class="badge badge-<?= badgeVariant($r['status']) ?>"><?= h($statusLabels[$r['status']] ?? $r['status']) ?></span>
              <?php else: ?>
                <span style="color:var(--color-text-muted);">No record</span>
              <?php endif; ?>
            </td>
            <td class="table-actions">
              <a href="staff.php?id=<?= (int) $r['id'] ?>">History</a>
              <a href="edit.php?staff_id=<?= (int) $r['id'] ?>&amp;date=<?= h($date) ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
