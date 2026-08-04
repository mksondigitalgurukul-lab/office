<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in? Skip straight to the dashboard.
if (currentAdmin()) {
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
            $stmt = getDB()->prepare('SELECT * FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                loginAdmin($admin);
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
<title>Login — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="center-screen">
  <div class="card card-narrow">
    <h1><?= h(APP_NAME) ?></h1>
    <h2 style="font-size:1rem; font-weight:500; color:var(--color-muted); margin-top:-8px;">Admin Login</h2>

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
