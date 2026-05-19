<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $db->query('INSERT INTO cg_clients (name, notes) VALUES (?, ?)',
                   [trim($_POST['name']), trim($_POST['notes'])]);
        $msg = 'Client added.';
    } elseif ($action === 'update' && !empty($_POST['id'])) {
        $db->query('UPDATE cg_clients SET name=?, notes=?, active=? WHERE id=?',
                   [trim($_POST['name']), trim($_POST['notes']),
                    !empty($_POST['active']) ? 1 : 0, (int)$_POST['id']]);
        $msg = 'Client updated.';
    } elseif ($action === 'set_default' && !empty($_POST['id'])) {
        cg_setting_set('default_client_id', (int)$_POST['id']);
        $msg = 'Default client set.';
    }
}

$clients = $db->query('SELECT * FROM cg_clients ORDER BY active DESC, name')->results();
$default_id = cg_defaultClientId();

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Clients</h1>
  <p><a href="index.php">&larr; Calendar</a> &middot; <a href="admin_caregivers.php">Caregivers</a> &middot; <a href="admin_settings.php">Settings</a></p>
  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">Add Client</div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add">
        <div class="col-md-4"><input name="name" class="form-control" placeholder="Client name" required></div>
        <div class="col-md-6"><input name="notes" class="form-control" placeholder="Notes (optional)"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
      </form>
    </div>
  </div>

  <table class="table table-striped align-middle">
    <thead><tr><th>Name</th><th>Notes</th><th>Active</th><th>Default</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr>
        <form method="post">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= $c->id ?>">
          <td><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars($c->name) ?>"></td>
          <td><input class="form-control form-control-sm" name="notes" value="<?= htmlspecialchars((string)$c->notes) ?>"></td>
          <td><input type="checkbox" name="active" value="1" <?= $c->active ? 'checked' : '' ?>></td>
          <td>
            <?php if ((int)$c->id === $default_id): ?>
              <span class="badge bg-success">Default</span>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" type="submit"
                      formaction="" name="action" value="set_default">Set default</button>
            <?php endif; ?>
          </td>
          <td><button class="btn btn-sm btn-primary">Save</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
