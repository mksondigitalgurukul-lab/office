<?php
/**
 * DB Tools — schema runner + database dashboard.
 *
 * This is the ONLY place schema changes should happen. Future prompts add
 * new numbered .sql files to this folder; this page picks them up, runs
 * CREATE TABLE IF NOT EXISTS statements that haven't run yet, and shows
 * the live structure of every table. Manual phpMyAdmin edits should be
 * avoided so this page (and CLAUDE.md) stay the source of truth.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

// Bootstrap exception: on a brand-new install there is no admin yet, so
// login is impossible until the admins table exists and create-admin.php
// has been run. This page stays open ONLY while that's true. The moment
// an admin account exists, it locks behind requireLogin() like every
// other admin page.
$adminCount = 0;
try {
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
} catch (PDOException $e) {
    $adminCount = 0; // admins table doesn't exist yet
}
$bootstrapping = $adminCount === 0;

if (!$bootstrapping) {
    requireLogin('../admin/login.php');
}
$admin = currentAdmin() ?? ['name' => 'Setup', 'email' => 'setup', 'role' => 'setup'];

$logFile = __DIR__ . '/schema_log.txt';

/**
 * Management key gating the "Admin Account" section (creating the first
 * admin, or resetting an existing one's password). Stored as plain text
 * in sql/key.txt, which is outside git (.gitignore) and blocked from
 * direct HTTP access (.htaccess) — only this page reads it server-side.
 * The first admin's creator chooses the key; it's then required for every
 * later admin password reset.
 */
function adminKeyPath(): string
{
    return __DIR__ . '/key.txt';
}

function adminKeyExists(): bool
{
    return is_file(adminKeyPath()) && trim((string) file_get_contents(adminKeyPath())) !== '';
}

function checkAdminKey(string $submitted): bool
{
    $stored = trim((string) @file_get_contents(adminKeyPath()));
    return $stored !== '' && hash_equals($stored, trim($submitted));
}

function storeAdminKey(string $key): void
{
    file_put_contents(adminKeyPath(), trim($key), LOCK_EX);
    @chmod(adminKeyPath(), 0600);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Strip `-- comment` lines and split a .sql file into individual statements. */
function splitStatements(string $sql): array
{
    $lines = array_filter(
        explode("\n", $sql),
        fn($line) => trim($line) !== '' && strpos(ltrim($line), '--') !== 0
    );
    $clean = implode("\n", $lines);

    $statements = array_map('trim', explode(';', $clean));
    return array_values(array_filter($statements, fn($s) => $s !== ''));
}

/** Table name(s) a .sql file's CREATE TABLE statements target (ignores commented-out stubs). */
function extractTableNames(string $sql): array
{
    $lines = array_filter(
        explode("\n", $sql),
        fn($line) => strpos(ltrim($line), '--') !== 0
    );
    $clean = implode("\n", $lines);

    preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?(\w+)`?/i', $clean, $matches);
    return $matches[1];
}

function runStatements(PDO $pdo, array $statements): array
{
    $ran = [];
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
        $ran[] = $stmt;
    }
    return $ran;
}

function logSchemaAction(string $logFile, string $admin, string $action, string $sql, string $result): void
{
    $line = sprintf(
        "[%s] %s | %s | RESULT: %s\nSQL: %s\n\n",
        date('Y-m-d H:i:s'),
        $admin,
        $action,
        $result,
        $sql
    );
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

$flash = null;

// --- Handle POST actions (re-run a file, or run an ad-hoc statement) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'rerun') {
        $file = basename($_POST['file'] ?? '');
        $path = __DIR__ . '/' . $file;

        if ($file !== '' && preg_match('/^\d+_[\w]+\.sql$/', $file) && is_file($path)) {
            try {
                $statements = splitStatements(file_get_contents($path));
                runStatements($pdo, $statements);
                logSchemaAction($logFile, $admin['email'], 'RE-RUN ' . $file, implode(";\n", $statements), 'OK');
                $flash = ['type' => 'success', 'text' => "Re-ran {$file} successfully."];
            } catch (PDOException $e) {
                logSchemaAction($logFile, $admin['email'], 'RE-RUN ' . $file, '', 'ERROR: ' . $e->getMessage());
                $flash = ['type' => 'error', 'text' => "Error re-running {$file}: " . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'error', 'text' => 'Invalid schema file.'];
        }
    } elseif ($action === 'alter') {
        $sql = trim($_POST['sql'] ?? '');

        if ($sql === '') {
            $flash = ['type' => 'error', 'text' => 'Enter a SQL statement to run.'];
        } else {
            try {
                $statements = splitStatements($sql);
                runStatements($pdo, $statements);
                logSchemaAction($logFile, $admin['email'], 'AD-HOC', $sql, 'OK');
                $flash = ['type' => 'success', 'text' => 'Statement executed successfully.'];
            } catch (PDOException $e) {
                logSchemaAction($logFile, $admin['email'], 'AD-HOC', $sql, 'ERROR: ' . $e->getMessage());
                $flash = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
            }
        }
    } elseif ($action === 'create_admin') {
        $name  = trim($_POST['admin_name'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $pass  = (string) ($_POST['admin_password'] ?? '');
        $key   = trim($_POST['admin_key'] ?? '');

        if ($adminCount > 0) {
            $flash = ['type' => 'error', 'text' => 'An admin already exists — use Update Password instead.'];
        } elseif ($name === '' || $email === '' || $pass === '' || $key === '') {
            $flash = ['type' => 'error', 'text' => 'Name, email, password, and key are all required.'];
        } elseif (strlen($pass) < 8) {
            $flash = ['type' => 'error', 'text' => 'Password must be at least 8 characters.'];
        } elseif (adminKeyExists() && !checkAdminKey($key)) {
            $flash = ['type' => 'error', 'text' => 'Invalid key.'];
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);

                if (!adminKeyExists()) {
                    storeAdminKey($key);
                }

                logSchemaAction($logFile, $email, 'CREATE ADMIN', "name={$name}; email={$email}", 'OK');
                $flash = ['type' => 'success', 'text' => "Admin '{$name}' created. You can now log in."];
            } catch (PDOException $e) {
                $flash = ['type' => 'error', 'text' => 'Could not create admin (email may already be in use).'];
            }
        }
    } elseif ($action === 'update_admin_password') {
        $targetId = (int) ($_POST['target_admin_id'] ?? 0);
        $newPass  = (string) ($_POST['new_password'] ?? '');
        $key      = trim($_POST['admin_key'] ?? '');

        if ($targetId <= 0 || $newPass === '' || $key === '') {
            $flash = ['type' => 'error', 'text' => 'Select an admin, enter a new password, and enter the key.'];
        } elseif (strlen($newPass) < 8) {
            $flash = ['type' => 'error', 'text' => 'Password must be at least 8 characters.'];
        } elseif (!adminKeyExists()) {
            $flash = ['type' => 'error', 'text' => 'No management key is configured yet. Create sql/key.txt on the server first.'];
        } elseif (!checkAdminKey($key)) {
            $flash = ['type' => 'error', 'text' => 'Invalid key.'];
        } else {
            $stmt = $pdo->prepare('SELECT email FROM admins WHERE id = ?');
            $stmt->execute([$targetId]);
            $targetEmail = $stmt->fetchColumn();

            if (!$targetEmail) {
                $flash = ['type' => 'error', 'text' => 'Admin not found.'];
            } else {
                $stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $targetId]);
                logSchemaAction($logFile, $admin['email'], 'UPDATE ADMIN PASSWORD', "target={$targetEmail}", 'OK');
                $flash = ['type' => 'success', 'text' => "Password updated for {$targetEmail}."];
            }
        }
    }
}

// --- Auto-run any schema file whose table doesn't exist yet ---
$sqlFiles = glob(__DIR__ . '/*.sql');
natsort($sqlFiles);

$fileReports = [];
foreach ($sqlFiles as $path) {
    $file    = basename($path);
    $content = file_get_contents($path);
    $tables  = extractTableNames($content);
    $primary = $tables[0] ?? null;

    $status = 'unknown';
    $error  = null;

    if ($primary === null) {
        $status = 'no-table';
    } elseif (tableExists($pdo, $primary)) {
        $status = 'exists';
    } else {
        try {
            runStatements($pdo, splitStatements($content));
            logSchemaAction($logFile, $admin['email'], 'AUTO-CREATE ' . $file, $content, 'OK');
            $status = 'created';
        } catch (PDOException $e) {
            $status = 'error';
            $error  = $e->getMessage();
            logSchemaAction($logFile, $admin['email'], 'AUTO-CREATE ' . $file, $content, 'ERROR: ' . $e->getMessage());
        }
    }

    $columns = [];
    if ($primary && tableExists($pdo, $primary)) {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $primary . '`');
        $columns = $stmt->fetchAll();
    }

    $fileReports[] = [
        'file'    => $file,
        'tables'  => $tables,
        'primary' => $primary,
        'status'  => $status,
        'error'   => $error,
        'content' => $content,
        'columns' => $columns,
    ];
}

// --- Admin Account section data (refreshed post-auto-create, since the
// admins table is guaranteed to exist by now even on a brand-new install) ---
$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
$adminsList = $adminCount > 0
    ? $pdo->query('SELECT id, name, email FROM admins ORDER BY name')->fetchAll()
    : [];

// --- Dashboard: every table currently in the database ---
$allTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$dashboard = [];
foreach ($allTables as $table) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    $cols  = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll();
    $dashboard[] = ['table' => $table, 'count' => $count, 'columns' => $cols];
}

$recentLog = '';
if (is_file($logFile)) {
    $lines = file($logFile);
    $recentLog = implode('', array_slice($lines, -60));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DB Tools — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><?= h(APP_NAME) ?></div>
  <div class="user-info">
    <?php if ($bootstrapping): ?>
      <span>Setup mode</span>
    <?php else: ?>
      <span><?= h($admin['name']) ?> (<?= h($admin['role']) ?>)</span>
      <a href="../admin/logout.php">Log out</a>
    <?php endif; ?>
  </div>
</div>
<nav class="nav">
  <a href="../admin/dashboard.php">Dashboard</a>
  <a href="../admin/staff/index.php">Staff</a>
  <a href="../admin/attendance.php">Attendance</a>
  <a href="../admin/leave.php">Leave</a>
  <a href="../admin/payout.php">Payout</a>
  <a href="../admin/reports.php">Reports</a>
  <a href="../admin/settings.php">Settings</a>
  <a href="index.php"><strong>DB Tools</strong></a>
</nav>

<div class="container">
  <h1>DB Tools</h1>
  <p style="color:var(--color-muted);">Schema changes belong here — add a new numbered <code>.sql</code> file to <code>/sql</code> for each future change, then reload this page.</p>

  <?php if ($bootstrapping): ?>
    <div class="alert alert-error">Setup mode: no admin account exists yet, so this page is open without login. Use it to create the schema below, then run <code>create-admin.php</code> at the project root. Once an admin exists, this page locks behind admin login.</div>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <h2>Schema Files</h2>
  <?php foreach ($fileReports as $report): ?>
    <div class="schema-file">
      <div class="file-header">
        <div>
          <strong><?= h($report['file']) ?></strong>
          <?php if ($report['status'] === 'created'): ?>
            <span class="badge badge-created">created just now</span>
          <?php elseif ($report['status'] === 'exists'): ?>
            <span class="badge badge-exists">table exists</span>
          <?php elseif ($report['status'] === 'error'): ?>
            <span class="badge badge-error">error</span>
          <?php endif; ?>
        </div>
        <form method="post" style="margin:0;">
          <input type="hidden" name="action" value="rerun">
          <input type="hidden" name="file" value="<?= h($report['file']) ?>">
          <button type="submit" class="btn btn-sm btn-secondary">Re-run</button>
        </form>
      </div>

      <?php if ($report['error']): ?>
        <div class="alert alert-error"><?= h($report['error']) ?></div>
      <?php endif; ?>

      <?php if ($report['columns']): ?>
        <div class="overflow-x">
          <table class="db-table">
            <thead>
              <tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>
            </thead>
            <tbody>
              <?php foreach ($report['columns'] as $col): ?>
                <tr>
                  <td><?= h($col['Field']) ?></td>
                  <td><?= h($col['Type']) ?></td>
                  <td><?= h($col['Null']) ?></td>
                  <td><?= h($col['Key']) ?></td>
                  <td><?= h($col['Default']) ?></td>
                  <td><?= h($col['Extra']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <details style="margin-top:10px;">
        <summary style="cursor:pointer; color:var(--color-muted); font-size:0.85rem;">View file contents</summary>
        <pre class="sql-source"><?= h($report['content']) ?></pre>
      </details>
    </div>
  <?php endforeach; ?>

  <h2>Admin Account</h2>
  <?php if ($adminCount === 0): ?>
    <p style="color:var(--color-muted);">No admin account exists yet. Create the first one here. The key you choose below becomes the management key required for every future admin password reset — pick one you'll remember, it isn't shown again.</p>
    <div class="card" style="max-width:480px;">
      <form method="post" novalidate>
        <input type="hidden" name="action" value="create_admin">
        <div class="field">
          <label for="admin_name">Name</label>
          <input type="text" id="admin_name" name="admin_name" required>
        </div>
        <div class="field">
          <label for="admin_email">Email</label>
          <input type="email" id="admin_email" name="admin_email" required>
        </div>
        <div class="field">
          <label for="admin_password">Password</label>
          <input type="password" id="admin_password" name="admin_password" required minlength="8">
        </div>
        <div class="field">
          <label for="admin_key">Management Key</label>
          <input type="password" id="admin_key" name="admin_key" required>
          <span class="field-hint">Remember this — it will be required later to reset any admin's password.</span>
        </div>
        <button type="submit" class="btn">Create Admin</button>
      </form>
    </div>
  <?php else: ?>
    <p style="color:var(--color-muted);">An admin already exists, so this section only resets an existing admin's password — protected by the management key set when the first admin was created.</p>
    <div class="card" style="max-width:480px;">
      <form method="post" novalidate>
        <input type="hidden" name="action" value="update_admin_password">
        <div class="field">
          <label for="target_admin_id">Admin</label>
          <select id="target_admin_id" name="target_admin_id" required>
            <?php foreach ($adminsList as $a): ?>
              <option value="<?= (int) $a['id'] ?>"><?= h($a['name']) ?> (<?= h($a['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required minlength="8">
        </div>
        <div class="field">
          <label for="admin_key_update">Management Key</label>
          <input type="password" id="admin_key_update" name="admin_key" required>
        </div>
        <button type="submit" class="btn">Update Password</button>
      </form>
    </div>
  <?php endif; ?>

  <h2>Database Dashboard</h2>
  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Table</th><th>Row Count</th><th>Columns</th></tr>
      </thead>
      <tbody>
        <?php foreach ($dashboard as $t): ?>
          <tr>
            <td><?= h($t['table']) ?></td>
            <td><?= (int) $t['count'] ?></td>
            <td><?= h(implode(', ', array_column($t['columns'], 'Field'))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2>Ad-hoc SQL</h2>
  <p style="color:var(--color-muted);">For manual fixes only (e.g. an ALTER TABLE). Every statement run here is logged below.</p>
  <form method="post" data-confirm="Run this SQL against the live database?">
    <input type="hidden" name="action" value="alter">
    <div class="field">
      <textarea name="sql" rows="4" placeholder="ALTER TABLE admins ADD COLUMN phone VARCHAR(20) NULL;" required></textarea>
    </div>
    <button type="submit" class="btn">Run SQL</button>
  </form>

  <h2>Schema Change Log</h2>
  <?php if ($recentLog): ?>
    <pre class="sql-source"><?= h($recentLog) ?></pre>
  <?php else: ?>
    <p style="color:var(--color-muted);">No manual changes logged yet.</p>
  <?php endif; ?>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
