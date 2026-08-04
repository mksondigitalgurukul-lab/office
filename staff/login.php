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
<script>
(function(){var t=localStorage.getItem('theme');if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}document.documentElement.setAttribute('data-theme',t);})();
</script>
<title>Staff Login — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="center-screen">
  <div class="card card-narrow">
    <div class="auth-brand">
      <span class="brand-logo">D</span>
    </div>
    <h1 style="text-align:center;"><?= h(APP_NAME) ?></h1>
    <h2 style="text-align:center; font-size:0.95rem; font-weight:500; color:var(--color-text-muted); margin-top:-6px;">Staff Login</h2>

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
    <p style="text-align:center; margin:16px 0 0 0; font-size:0.82rem;"><a href="../admin/login.php">Admin login instead &rarr;</a></p>
  </div>
</div>
</body>
</html>
