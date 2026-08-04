<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin('../login.php');
$admin = currentAdmin();
$pdo   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE office_locations SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Location updated.'];
    header('Location: index.php');
    exit;
}

$locations = $pdo->query('SELECT * FROM office_locations ORDER BY location_name')->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = 'Office Locations';
$activeNav = 'office-locations';
$basePath  = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
  <div class="toolbar">
    <h1 style="margin:0;">Office Locations</h1>
    <a href="add.php" class="btn">+ Add Location</a>
  </div>

  <p style="color:var(--color-text-muted);">Active locations' IP addresses are what staff check-ins are compared against to set <code>work_location = 'office_verified'</code>.</p>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['text']) ?></div>
  <?php endif; ?>

  <div class="overflow-x">
    <table class="db-table">
      <thead>
        <tr><th>Location</th><th>IP Address</th><th>Status</th><th>Added</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$locations): ?>
          <tr><td colspan="5" style="color:var(--color-text-muted);">No office locations yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($locations as $loc): ?>
          <tr>
            <td><?= h($loc['location_name']) ?></td>
            <td><?= h($loc['ip_address']) ?></td>
            <td><span class="badge badge-<?= badgeVariant($loc['is_active'] ? 'active' : 'inactive') ?>"><?= $loc['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td><?= h($loc['created_at']) ?></td>
            <td class="table-actions">
              <a href="edit.php?id=<?= (int) $loc['id'] ?>">Edit</a>
              <form method="post" style="display:inline;" data-confirm="<?= $loc['is_active'] ? 'Deactivate' : 'Activate' ?> this location?">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $loc['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary"><?= $loc['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
