<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE leave_types SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Leave type updated.'];
        header('Location: index.php');
        exit;
    } elseif ($action === 'toggle_paid') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE leave_types SET is_paid = NOT is_paid WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Leave type updated.'];
        header('Location: index.php');
        exit;
    } elseif ($action === 'add') {
        $name   = trim($_POST['name'] ?? '');
        $isPaid = isset($_POST['is_paid']);

        if ($name === '') {
            $error = 'Enter a name.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO leave_types (name, is_paid, is_active) VALUES (?, ?, 1)');
                $stmt->execute([$name, $isPaid ? 1 : 0]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => "Leave type '{$name}' added."];
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Could not add leave type (name may already exist).';
            }
        }
    } elseif ($action === 'edit') {
        $id     = (int) ($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $isPaid = isset($_POST['is_paid']);

        if ($name === '') {
            $error = 'Enter a name.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE leave_types SET name = ?, is_paid = ? WHERE id = ?');
                $stmt->execute([$name, $isPaid ? 1 : 0, $id]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Leave type updated.'];
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Could not update leave type (name may already exist).';
            }
        }
    }
}

$leaveTypes = $pdo->query(
    "SELECT lt.*, COUNT(lr.id) AS request_count
     FROM leave_types lt
     LEFT JOIN leave_requests lr ON lr.leave_type_id = lt.id
     GROUP BY lt.id
     ORDER BY lt.name"
)->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = 'Leave Types';
$activeNav = 'leave-types';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <h1>Leave Types</h1>
  <p style="color:var(--color-text-muted);">Deactivating a type hides it from the staff-side request form but keeps existing requests intact — types already referenced by a leave request are never hard-deleted.</p>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Name</th><th>Paid</th><th>Status</th><th>Requests</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($leaveTypes as $t): ?>
          <tr>
            <td>
              <form method="post" style="display:flex; gap:6px;">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <?php if ($t['is_paid']): ?>
                  <input type="hidden" name="is_paid" value="1">
                <?php endif; ?>
                <input type="text" name="name" value="<?= h($t['name']) ?>" style="width:140px;">
                <button type="submit" class="btn btn-sm btn-secondary">Rename</button>
              </form>
            </td>
            <td>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="toggle_paid">
                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary"><?= $t['is_paid'] ? 'Paid' : 'Unpaid' ?></button>
              </form>
            </td>
            <td><span class="badge badge-<?= badgeVariant($t['is_active'] ? 'active' : 'inactive') ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td><?= (int) $t['request_count'] ?></td>
            <td class="table-actions">
              <form method="post" style="display:inline;" data-confirm="<?= $t['is_active'] ? 'Deactivate' : 'Activate' ?> this leave type?">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary"><?= $t['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2>Add Leave Type</h2>
  <div class="card" style="max-width:380px; margin-bottom:16px;">
    <form method="post" novalidate>
      <input type="hidden" name="action" value="add">
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required>
      </div>
      <div class="field">
        <label><input type="checkbox" name="is_paid" checked> Paid leave</label>
      </div>
      <button type="submit" class="btn">Add</button>
    </form>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
