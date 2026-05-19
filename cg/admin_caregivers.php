<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;
$msg = '';
$saved_id = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (!empty($_POST['user_id'])) ? (int)$_POST['user_id'] : null;
    $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3788d8';
    $redirect_id = 0;

    if ($action === 'add') {
        $db->query('INSERT INTO cg_caregivers (name, phone, email, user_id, color)
                    VALUES (?, ?, ?, ?, ?)',
                   [trim($_POST['name']), trim($_POST['phone']), trim($_POST['email']),
                    $user_id, $color]);
        $redirect_id = (int)$db->lastId();
        if ($user_id) {
            $has = $db->query('SELECT 1 FROM user_permission_matches WHERE user_id=? AND permission_id=?',
                              [$user_id, CG_PERM_CAREGIVER])->count();
            if (!$has) {
                $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, ?)',
                           [$user_id, CG_PERM_CAREGIVER]);
            }
        }
    } elseif ($action === 'update' && !empty($_POST['id'])) {
        $db->query('UPDATE cg_caregivers SET name=?, phone=?, email=?, user_id=?, color=?, active=? WHERE id=?',
                   [trim($_POST['name']), trim($_POST['phone']), trim($_POST['email']),
                    $user_id, $color, !empty($_POST['active']) ? 1 : 0, (int)$_POST['id']]);
        if ($user_id) {
            $has = $db->query('SELECT 1 FROM user_permission_matches WHERE user_id=? AND permission_id=?',
                              [$user_id, CG_PERM_CAREGIVER])->count();
            if (!$has) {
                $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, ?)',
                           [$user_id, CG_PERM_CAREGIVER]);
            }
        }
        $redirect_id = (int)$_POST['id'];
    }

    // PRG with anchor so the page reloads scrolled to the row we just saved.
    if ($redirect_id) {
        header('Location: admin_caregivers.php?saved=' . $redirect_id . '#cg-' . $redirect_id);
        exit;
    }
}

$rows = $db->query(
    'SELECT c.*, u.username, u.fname, u.lname
       FROM cg_caregivers c LEFT JOIN users u ON u.id = c.user_id
       ORDER BY c.active DESC, c.name'
)->results();

$us_users = $db->query('SELECT id, username, fname, lname FROM users WHERE active = 1 ORDER BY username')->results();

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Caregivers</h1>
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="index.php">Calendar</a> &middot; <a href="admin_pay_rates.php">Pay rates &amp; differentials</a></p>
  <?php if ($saved_id): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">Add Caregiver</div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add">
        <div class="col-md-3"><input name="name"  class="form-control" placeholder="Name" required></div>
        <div class="col-md-2"><input name="phone" class="form-control" placeholder="Phone"></div>
        <div class="col-md-3"><input name="email" class="form-control" placeholder="Email"></div>
        <div class="col-md-2">
          <select name="user_id" class="form-select">
            <option value="">— No login —</option>
            <?php foreach ($us_users as $u): ?>
              <option value="<?= $u->id ?>"><?= htmlspecialchars($u->username) ?> (<?= htmlspecialchars(trim(($u->fname??'').' '.($u->lname??''))) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1"><input type="color" name="color" class="form-control form-control-color" value="#3788d8"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div>
      </form>
      <small class="text-muted">Linking a UserSpice user automatically grants them the Caregiver permission so they can self-schedule. Set pay rates on the <a href="admin_pay_rates.php">Pay rates</a> tab.</small>
    </div>
  </div>

  <table class="table table-striped align-middle">
    <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Linked Login</th><th>Color</th><th>Active</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr id="cg-<?= $r->id ?>" class="<?= ($saved_id === (int)$r->id) ? 'cg-just-saved' : '' ?>">
        <form method="post">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= $r->id ?>">
          <td><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars($r->name) ?>" required></td>
          <td><input class="form-control form-control-sm" name="phone" value="<?= htmlspecialchars((string)$r->phone) ?>"></td>
          <td><input class="form-control form-control-sm" name="email" value="<?= htmlspecialchars((string)$r->email) ?>"></td>
          <td>
            <select name="user_id" class="form-select form-select-sm">
              <option value="">— No login —</option>
              <?php foreach ($us_users as $u): ?>
                <option value="<?= $u->id ?>" <?= ((int)$r->user_id === (int)$u->id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u->username) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="color" name="color" class="form-control form-control-color form-control-sm" value="<?= htmlspecialchars($r->color) ?>"></td>
          <td><input type="checkbox" name="active" value="1" <?= $r->active ? 'checked' : '' ?>></td>
          <td><button class="btn btn-sm btn-primary">Save</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>

<style>
  /* Brief highlight on the row we just saved. */
  @keyframes cgSavedFlash {
    0%   { box-shadow: inset 0 0 0 3px rgba(40, 167, 69, 0.55); }
    100% { box-shadow: inset 0 0 0 0   rgba(40, 167, 69, 0);    }
  }
  .cg-just-saved { animation: cgSavedFlash 1.4s ease-out 1; }
</style>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
