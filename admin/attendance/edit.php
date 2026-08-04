<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$staffId = (int) ($_GET['staff_id'] ?? $_POST['staff_id'] ?? 0);
$stmt    = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    header('Location: ../staff/index.php');
    exit;
}

$date = $_GET['date'] ?? $_POST['attendance_date'] ?? date('Y-m-d');
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    $date = date('Y-m-d');
}

$stmt = $pdo->prepare('SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?');
$stmt->execute([$staffId, $date]);
$existing = $stmt->fetch();

$error  = '';
$values = [
    'attendance_date' => $date,
    'check_in_time'   => $existing['check_in_time']  ?? '',
    'check_out_time'  => $existing['check_out_time'] ?? '',
    'work_location'   => $existing['work_location']  ?? 'office_manual',
    'status'          => $existing['status']         ?? 'present',
];
$existingNotes = $existing['notes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['attendance_date'] = $_POST['attendance_date'] ?? $date;
    $values['check_in_time']   = trim($_POST['check_in_time'] ?? '');
    $values['check_out_time']  = trim($_POST['check_out_time'] ?? '');
    $values['work_location']   = $_POST['work_location'] ?? '';
    $values['status']          = $_POST['status'] ?? '';
    $reason                    = trim($_POST['reason'] ?? '');

    $validLocations = ['office_verified', 'office_manual', 'wfh', 'unverified'];
    $validStatuses  = ['present', 'late', 'half_day', 'absent'];

    if (!DateTime::createFromFormat('Y-m-d', $values['attendance_date'])) {
        $error = 'Enter a valid date.';
    } elseif (!in_array($values['work_location'], $validLocations, true)) {
        $error = 'Invalid work location.';
    } elseif (!in_array($values['status'], $validStatuses, true)) {
        $error = 'Invalid status.';
    } elseif ($values['check_in_time'] !== '' && $values['check_out_time'] !== '' && $values['check_out_time'] < $values['check_in_time']) {
        $error = 'Check-out time must be after check-in time.';
    } else {
        $stamp   = '[Manually edited by ' . $admin['name'] . ' on ' . date('Y-m-d H:i') . ']' . ($reason !== '' ? ' ' . $reason : '');
        $notes   = trim(($existingNotes !== '' ? $existingNotes . "\n" : '') . $stamp);

        $stmt = $pdo->prepare(
            'INSERT INTO attendance (staff_id, attendance_date, check_in_time, check_out_time, check_in_ip, check_out_ip, work_location, status, notes)
             VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               check_in_time = VALUES(check_in_time),
               check_out_time = VALUES(check_out_time),
               work_location = VALUES(work_location),
               status = VALUES(status),
               notes = VALUES(notes)'
        );
        $stmt->execute([
            $staffId,
            $values['attendance_date'],
            $values['check_in_time'] !== '' ? $values['check_in_time'] : null,
            $values['check_out_time'] !== '' ? $values['check_out_time'] : null,
            $values['work_location'],
            $values['status'],
            $notes,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Attendance record saved for ' . $values['attendance_date'] . '.'];
        header('Location: staff.php?id=' . $staffId);
        exit;
    }
}
$pageTitle = 'Edit Attendance — ' . $staff['full_name'];
$activeNav = 'attendance';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <h1>Manual Attendance Entry — <?= h($staff['full_name']) ?></h1>
  <p style="color:var(--color-text-muted);"><?= $existing ? 'Editing an existing record.' : 'No record exists for this date yet — this will create one.' ?> Every save is logged in the notes below with your name and the time.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:520px;">
    <form method="post" novalidate>
      <input type="hidden" name="staff_id" value="<?= (int) $staffId ?>">

      <div class="field">
        <label for="attendance_date">Date</label>
        <input type="date" id="attendance_date" name="attendance_date" required value="<?= h($values['attendance_date']) ?>">
      </div>

      <div class="field-row">
        <div class="field">
          <label for="check_in_time">Check-In Time</label>
          <input type="time" id="check_in_time" name="check_in_time" value="<?= h($values['check_in_time']) ?>">
        </div>
        <div class="field">
          <label for="check_out_time">Check-Out Time</label>
          <input type="time" id="check_out_time" name="check_out_time" value="<?= h($values['check_out_time']) ?>">
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="work_location">Work Location</label>
          <select id="work_location" name="work_location">
            <option value="office_verified" <?= $values['work_location'] === 'office_verified' ? 'selected' : '' ?>>Office (verified)</option>
            <option value="office_manual" <?= $values['work_location'] === 'office_manual' ? 'selected' : '' ?>>Office (manual)</option>
            <option value="wfh" <?= $values['work_location'] === 'wfh' ? 'selected' : '' ?>>WFH</option>
            <option value="unverified" <?= $values['work_location'] === 'unverified' ? 'selected' : '' ?>>Unverified</option>
          </select>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="present" <?= $values['status'] === 'present' ? 'selected' : '' ?>>Present</option>
            <option value="late" <?= $values['status'] === 'late' ? 'selected' : '' ?>>Late</option>
            <option value="half_day" <?= $values['status'] === 'half_day' ? 'selected' : '' ?>>Half Day</option>
            <option value="absent" <?= $values['status'] === 'absent' ? 'selected' : '' ?>>Absent</option>
          </select>
        </div>
      </div>

      <?php if ($existingNotes !== ''): ?>
        <div class="field">
          <label>Existing Notes</label>
          <pre class="sql-source" style="white-space:pre-wrap;"><?= h($existingNotes) ?></pre>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="reason">Add a Note (optional)</label>
        <textarea id="reason" name="reason" rows="2" placeholder="Reason for this manual entry..."></textarea>
      </div>

      <button type="submit" class="btn">Save</button>
      <a href="staff.php?id=<?= (int) $staffId ?>" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
