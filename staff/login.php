<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in? Skip straight to the dashboard.
if (currentStaff()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $stmt = getDB()->prepare('SELECT * FROM staff WHERE email = ?');
            $stmt->execute([$email]);
            $staff = $stmt->fetch();

            if ($staff && $staff['status'] === 'active' && password_verify($password, $staff['password_hash'])) {
                loginStaff($staff);
                header('Location: dashboard.php');
                exit;
            }

            $error = 'Invalid email or password.';
        } catch (PDOException $e) {
            $error = 'Could not connect to the database. Check config.php.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Login — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="center-screen">
  <div class="card card-narrow">
    <h1><?= h(APP_NAME) ?></h1>
    <h2 style="font-size:1rem; font-weight:500; color:var(--color-muted); margin-top:-8px;">Staff Login</h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn" style="width:100%;">Log In</button>
    </form>
  </div>
</div>
</body>
</html>
