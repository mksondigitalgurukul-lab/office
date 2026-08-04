<?php
/**
 * One-time setup script: creates the first admin account.
 *
 * Run this once after importing the database schema (via sql/index.php),
 * then delete this file or restrict access to it — it refuses to run
 * again once at least one admin already exists.
 *
 * Usage (browser): visit create-admin.php and fill in the form.
 * Usage (CLI):      php create-admin.php "Full Name" email@example.com "password"
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$isCli = (PHP_SAPI === 'cli');
$error = '';
$success = false;

try {
    $existingCount = (int) getDB()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
} catch (PDOException $e) {
    $existingCount = null; // admins table doesn't exist yet
}

if ($existingCount === null) {
    $message = "The 'admins' table does not exist yet. Run the schema first via sql/index.php, then reload this page.";
    if ($isCli) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
} elseif ($existingCount > 0) {
    $message = 'An admin account already exists. This script is blocked for security. Delete create-admin.php or remove server access to it.';
    if ($isCli) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
} else {
    $message = null;

    if ($isCli) {
        $name     = $argv[1] ?? null;
        $email    = $argv[2] ?? null;
        $password = $argv[3] ?? null;

        if (!$name || !$email || !$password) {
            fwrite(STDERR, "Usage: php create-admin.php \"Full Name\" email@example.com \"password\"\n");
            exit(1);
        }

        $stmt = getDB()->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
        echo "Admin '{$name}' <{$email}> created. You can now log in at /admin/login.php\n";
        exit(0);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                $stmt = getDB()->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
                $success = true;
            } catch (PDOException $e) {
                $error = 'Could not create admin (email may already be in use).';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create First Admin — Digital Ali Pro OMS</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="center-screen">
  <div class="card card-narrow">
    <h1>Create First Admin</h1>

    <?php if (!empty($message)): ?>
      <div class="alert alert-error"><?= h($message) ?></div>
    <?php elseif ($success): ?>
      <div class="alert alert-success">Admin account created. You can now <a href="admin/login.php">log in</a>. Please delete or block create-admin.php now.</div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <div class="field">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" required value="<?= h($_POST['name'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="8">
        </div>
        <button type="submit" class="btn" style="width:100%;">Create Admin</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
