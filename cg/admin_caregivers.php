<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;
$msg = '';
$saved_id = isset($_GET['saved']) ? (int)$_GET['saved'] : 0; // set on PRG return so we can flash + scroll

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (!empty($_POST['user_id'])) ? (int)$_POST['user_id'] : null;
    $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3788d8';

    // Nullable decimals: blank string => NULL, otherwise cast to float.
    $nf = function ($v) {
        $v = trim((string)$v);
        return ($v === '') ? null : (float)$v;
    };
    $payable    = !empty($_POST['payable']) ? 1 : 0;
    $pay_rate   = $nf($_POST['pay_rate']      ?? '');
    $ot_mult    = $nf($_POST['diff_ot_mult']  ?? '');
    $ot_add     = $nf($_POST['diff_ot_add']   ?? '');
    $hol_mult   = $nf($_POST['diff_hol_mult'] ?? '');
    $hol_add    = $nf($_POST['diff_hol_add']  ?? '');

    $redirect_id = 0;
    if ($action === 'add') {
        $db->query('INSERT INTO cg_caregivers
                      (name, phone, email, user_id, color, payable, pay_rate,
                       diff_ot_mult, diff_ot_add, diff_hol_mult, diff_hol_add)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                   [trim($_POST['name']), trim($_POST['phone']), trim($_POST['email']),
                    $user_id, $color, $payable, $pay_rate,
                    $ot_mult, $ot_add, $hol_mult, $hol_add]);
        $redirect_id = (int)$db->lastId();
        // If linked to a user, grant Caregiver permission if not already
        if ($user_id) {
            $has = $db->query('SELECT 1 FROM user_permission_matches WHERE user_id=? AND permission_id=?',
                              [$user_id, CG_PERM_CAREGIVER])->count();
            if (!$has) {
                $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, ?)',
                           [$user_id, CG_PERM_CAREGIVER]);
            }
        }
        $msg = 'Caregiver added.';
    } elseif ($action === 'update' && !empty($_POST['id'])) {
        $db->query('UPDATE cg_caregivers
                       SET name=?, phone=?, email=?, user_id=?, color=?, active=?,
                           payable=?, pay_rate=?, diff_ot_mult=?, diff_ot_add=?,
                           diff_hol_mult=?, diff_hol_add=?
                     WHERE id=?',
                   [trim($_POST['name']), trim($_POST['phone']), trim($_POST['email']),
                    $user_id, $color, !empty($_POST['active']) ? 1 : 0,
                    $payable, $pay_rate, $ot_mult, $ot_add, $hol_mult, $hol_add,
                    (int)$_POST['id']]);
        if ($user_id) {
            $has = $db->query('SELECT 1 FROM user_permission_matches WHERE user_id=? AND permission_id=?',
                              [$user_id, CG_PERM_CAREGIVER])->count();
            if (!$has) {
                $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, ?)',
                           [$user_id, CG_PERM_CAREGIVER]);
            }
        }
        $msg = 'Caregiver updated.';
        $redirect_id = (int)$_POST['id'];
    }

    // Post-Redirect-Get with an anchor so the page reloads scrolled to the row
    // we just saved instead of jumping to the top.
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
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="index.php">Calendar</a></p>
  <?php if ($saved_id): ?>
    <div class="alert alert-success">Saved.</div>
  <?php elseif ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

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
      <small class="text-muted">Linking a UserSpice user automatically grants them the Caregiver permission so they can self-schedule.</small>
    </div>
  </div>

  <?php foreach ($rows as $r): ?>
    <div id="cg-<?= $r->id ?>" class="card mb-3 <?= $r->active ? '' : 'opacity-50' ?> <?= ($saved_id === (int)$r->id) ? 'cg-just-saved' : '' ?>">
      <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= $r->id ?>">

          <div class="col-md-3">
            <label class="form-label form-label-sm mb-0">Name</label>
            <input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars($r->name) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Phone</label>
            <input class="form-control form-control-sm" name="phone" value="<?= htmlspecialchars((string)$r->phone) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-0">Email</label>
            <input class="form-control form-control-sm" name="email" value="<?= htmlspecialchars((string)$r->email) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Login</label>
            <select name="user_id" class="form-select form-select-sm">
              <option value="">— No login —</option>
              <?php foreach ($us_users as $u): ?>
                <option value="<?= $u->id ?>" <?= ((int)$r->user_id === (int)$u->id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u->username) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label form-label-sm mb-0">Color</label>
            <input type="color" name="color" class="form-control form-control-color form-control-sm" value="<?= htmlspecialchars($r->color) ?>">
          </div>
          <div class="col-md-1 d-flex flex-column">
            <small class="text-muted">Flags</small>
            <div class="form-check form-check-inline">
              <input type="checkbox" class="form-check-input" name="active" value="1" id="active-<?= $r->id ?>" <?= $r->active ? 'checked' : '' ?>>
              <label class="form-check-label small" for="active-<?= $r->id ?>">Active</label>
            </div>
            <div class="form-check form-check-inline">
              <input type="checkbox" class="form-check-input" name="payable" value="1" id="payable-<?= $r->id ?>" <?= $r->payable ? 'checked' : '' ?>>
              <label class="form-check-label small" for="payable-<?= $r->id ?>">Payable</label>
            </div>
          </div>

          <div class="col-12"><hr class="my-1"></div>

          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Rate $/hr</label>
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="pay_rate"
                   value="<?= $r->pay_rate !== null ? htmlspecialchars(rtrim(rtrim(number_format((float)$r->pay_rate, 2, '.', ''),'0'),'.')) : '' ?>"
                   placeholder="(unset)">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">OT multiplier</label>
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_ot_mult"
                   value="<?= $r->diff_ot_mult !== null ? htmlspecialchars((string)(float)$r->diff_ot_mult) : '' ?>"
                   placeholder="e.g. 1.5">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">OT add $/hr</label>
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_ot_add"
                   value="<?= $r->diff_ot_add !== null ? htmlspecialchars((string)(float)$r->diff_ot_add) : '' ?>"
                   placeholder="e.g. 2">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Holiday multiplier</label>
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_hol_mult"
                   value="<?= $r->diff_hol_mult !== null ? htmlspecialchars((string)(float)$r->diff_hol_mult) : '' ?>"
                   placeholder="e.g. 2">
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-0">Holiday add $/hr</label>
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_hol_add"
                   value="<?= $r->diff_hol_add !== null ? htmlspecialchars((string)(float)$r->diff_hol_add) : '' ?>"
                   placeholder="">
          </div>
          <div class="col-md-2 text-end">
            <button class="btn btn-sm btn-primary w-100">Save</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="text-muted small">
    Pay math: <code>effective_rate = base × multiplier + add</code>. Leave a field blank to use the base rate.
    A blank rate means the report shows hours only — no $ total. Holiday hours take precedence over overnight hours when they overlap.
  </p>
</main>

<style>
  /* Brief highlight on the row we just saved, since the page reload otherwise
     looks identical to before and the user might wonder if it took. */
  @keyframes cgSavedFlash {
    0%   { box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.55); }
    100% { box-shadow: 0 0 0 0   rgba(40, 167, 69, 0);    }
  }
  .cg-just-saved { animation: cgSavedFlash 1.4s ease-out 1; }
</style>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
