<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaffLogin('login.php');
$staffSession = currentStaff();
$pdo = getDB();

$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
$stmt->execute([$staffSession['id']]);
$staff = $stmt->fetch();

if (!$staff || $staff['status'] !== 'active') {
    logoutStaff();
    header('Location: login.php');
    exit;
}

$timing = getCurrentWorkTiming($staff['id']);

$passwordError   = '';
$passwordSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($currentPassword, $staff['password_hash'])) {
        $passwordError = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $passwordError = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'New password and confirmation do not match.';
    } else {
        $stmt = $pdo->prepare('UPDATE staff SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $staff['id']]);
        $passwordSuccess = 'Password updated.';
    }
}

$pageTitle = 'Profile';
$activeNav = 'profile';
require __DIR__ . '/../includes/staff-header.php';
?>
  <h1>Profile</h1>

  <div class="card" style="max-width:520px; margin-bottom:20px;">
    <h2 style="margin-top:0;">My Details</h2>
    <div class="field-row">
      <div><strong>Name:</strong> <?= h($staff['full_name']) ?></div>
      <div><strong>Email:</strong> <?= h($staff['email']) ?></div>
    </div>
    <div class="field-row" style="margin-top:10px;">
      <div><strong>Phone:</strong> <?= h($staff['phone'] ?? '—') ?></div>
      <div><strong>Designation:</strong> <?= h($staff['designation'] ?? '—') ?></div>
    </div>
    <div class="field-row" style="margin-top:10px;">
      <div><strong>Department:</strong> <?= h($staff['department'] ?? '—') ?></div>
      <div><strong>Work Mode:</strong> <?= h($staff['work_mode']) ?></div>
    </div>
    <div class="field-row" style="margin-top:10px;">
      <div><strong>Joined:</strong> <?= h($staff['joined_date'] ?? '—') ?></div>
      <div>
        <strong>Work Timing:</strong> <?= h($timing['start']) ?> – <?= h($timing['end']) ?>
        (<?= $timing['source'] === 'override' ? 'custom' : 'universal default' ?>)
      </div>
    </div>
    <p style="margin:12px 0 0 0; color:var(--color-text-muted); font-size:0.82rem;">These details are managed by an admin — contact your office admin to update them.</p>
  </div>

  <div class="card" style="max-width:420px;">
    <h2 style="margin-top:0;">Change Password</h2>

    <?php if ($passwordError): ?>
      <div class="alert alert-error"><?= h($passwordError) ?></div>
    <?php endif; ?>
    <?php if ($passwordSuccess): ?>
      <div class="alert alert-success"><?= h($passwordSuccess) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="action" value="change_password">
      <div class="field">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>
      </div>
      <div class="field">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
      </div>
      <div class="field">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
      </div>
      <button type="submit" class="btn">Update Password</button>
    </form>
  </div>
<?php require __DIR__ . '/../includes/staff-footer.php'; ?>
