<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isManager()) { die('Admin or manager only.'); }

global $db;
$saved_id = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $user_id = (!empty($_POST['user_id'])) ? (int)$_POST['user_id'] : null;
    $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3788d8';
    $redirect_id = 0;

    $notes = trim((string)($_POST['notes'] ?? ''));
    if ($notes === '') $notes = null;

    if ($action === 'add') {
        $db->query('INSERT INTO cg_caregivers (name, phone, email, user_id, color, notes)
                    VALUES (?, ?, ?, ?, ?, ?)',
                   [trim($_POST['name']), trim($_POST['phone']), trim($_POST['email']),
                    $user_id, $color, $notes]);
        $redirect_id = (int)$db->lastId();
        if ($user_id) {
            $has = $db->query('SELECT 1 FROM user_permission_matches WHERE user_id=? AND permission_id=?',
                              [$user_id, CG_PERM_CAREGIVER])->count();
            if (!$has) {
                $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, ?)',
                           [$user_id, CG_PERM_CAREGIVER]);
            }
        }
        $fresh = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$redirect_id])->first();
        cg_logCaregiverAudit($redirect_id, 'insert', null, cg_caregiverSnapshot($fresh));
    } elseif ($action === 'update' && !empty($_POST['id'])) {
        $id         = (int)$_POST['id'];
        $before_row = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$id])->first();
        $before     = cg_caregiverSnapshot($before_row);

        $db->query('UPDATE cg_caregivers SET name=?, phone=?, email=?, user_id=?, color=?, active=?, notes=? WHERE id=?',
                   [trim($_POST['name']), trim($_POST['phone']), trim($_POST['email']),
                    $user_id, $color, !empty($_POST['active']) ? 1 : 0, $notes, $id]);
        if ($user_id) {
            $has = $db->query('SELECT 1 FROM user_permission_matches WHERE user_id=? AND permission_id=?',
                              [$user_id, CG_PERM_CAREGIVER])->count();
            if (!$has) {
                $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, ?)',
                           [$user_id, CG_PERM_CAREGIVER]);
            }
        }
        $after_row = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$id])->first();
        $after     = cg_caregiverSnapshot($after_row);
        if ($before !== $after) {
            cg_logCaregiverAudit($id, 'update', $before, $after);
        }
        $redirect_id = $id;
    }

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

// Which caregivers have any weekly-availability rows? Used to flag rows that
// still need a schedule entered.
$has_avail = [];
foreach ($db->query('SELECT DISTINCT caregiver_id FROM cg_caregiver_availability')->results() as $a) {
    $has_avail[(int)$a->caregiver_id] = true;
}

$is_admin = cg_isAdmin();

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Caregivers</h1>
  <p>
    <a href="admin.php">&larr; Admin</a> &middot;
    <a href="index.php">Calendar</a> &middot;
    <a href="admin_availability_overview.php">Availability overview</a>
    <?php if ($is_admin): ?>
      &middot; <a href="admin_pay_rates.php">Pay rates &amp; differentials</a>
    <?php endif; ?>
  </p>
  <?php if ($saved_id): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">Add Caregiver</div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add">
        <div class="col-md-5 col-12"><input name="name"  class="form-control" placeholder="Name" required></div>
        <div class="col-md-4 col-9"><input name="phone" class="form-control" placeholder="Phone"></div>
        <div class="col-md-3 col-3"><button class="btn btn-primary w-100">Add</button></div>
      </form>
      <small class="text-muted">Email, linked login, color, and active-state are set via the row's pencil button.</small>
    </div>
  </div>

  <table class="table table-striped align-middle cg-list">
    <thead>
      <tr>
        <th>Name</th>
        <th>Phone</th>
        <th class="text-end" style="width: 110px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $modal_id = 'cgModal' . (int)$r->id;
    ?>
      <tr id="cg-<?= $r->id ?>" class="<?= ($saved_id === (int)$r->id) ? 'cg-just-saved' : '' ?> <?= $r->active ? '' : 'opacity-50' ?>">
        <td>
          <span class="cg-color-dot" style="background: <?= htmlspecialchars($r->color) ?>"></span>
          <?= htmlspecialchars($r->name) ?>
          <?php if (!empty($r->notes)): ?>
            <span class="cg-note-flag" title="<?= htmlspecialchars($r->notes) ?>" aria-label="Has notes">📝</span>
          <?php endif; ?>
          <?php if ($r->active && empty($has_avail[(int)$r->id])): ?>
            <a href="availability.php?caregiver_id=<?= (int)$r->id ?>"
               class="badge bg-warning text-dark text-decoration-none ms-1"
               title="No weekly availability set — click to enter one">No schedule</a>
          <?php endif; ?>
          <?php if (!$r->active): ?><small class="text-muted ms-1">(inactive)</small><?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string)$r->phone) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary me-1"
             href="availability.php?caregiver_id=<?= (int)$r->id ?>"
             title="Edit availability" aria-label="Edit availability">🗓</a>
          <button type="button" class="btn btn-sm btn-outline-secondary"
                  data-bs-toggle="modal" data-bs-target="#<?= $modal_id ?>"
                  aria-label="Edit" title="Edit">
            <!-- Bootstrap pencil icon (inline SVG so we don't depend on Bootstrap Icons being loaded) -->
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
              <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
            </svg>
          </button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>

<!-- Edit modals (rendered outside the table so each form has clean boundaries) -->
<?php foreach ($rows as $r):
    $modal_id = 'cgModal' . (int)$r->id;
?>
  <div class="modal fade" id="<?= $modal_id ?>" tabindex="-1" aria-labelledby="<?= $modal_id ?>Label" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= $r->id ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="<?= $modal_id ?>Label">Edit caregiver</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars($r->name) ?>" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-7">
              <label class="form-label">Phone</label>
              <input class="form-control" name="phone" value="<?= htmlspecialchars((string)$r->phone) ?>">
            </div>
            <div class="col-5">
              <label class="form-label">Color</label>
              <input type="color" name="color" class="form-control form-control-color w-100" value="<?= htmlspecialchars($r->color) ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" value="<?= htmlspecialchars((string)$r->email) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Linked Login</label>
            <select name="user_id" class="form-select">
              <option value="">— No login —</option>
              <?php foreach ($us_users as $u): ?>
                <option value="<?= $u->id ?>" <?= ((int)$r->user_id === (int)$u->id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u->username) ?> (<?= htmlspecialchars(trim(($u->fname??'').' '.($u->lname??''))) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Linking a UserSpice user grants them the Caregiver permission.</small>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="active" value="1" id="active-<?= $r->id ?>" <?= $r->active ? 'checked' : '' ?>>
            <label class="form-check-label" for="active-<?= $r->id ?>">Active</label>
          </div>
          <div class="mb-1">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="3"
                      placeholder="Days they can work, restrictions, anything to remember…"><?= htmlspecialchars((string)$r->notes) ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<style>
  .cg-color-dot {
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 50%;
    border: 1px solid rgba(0,0,0,0.15);
    margin-right: 6px;
    vertical-align: middle;
  }
  .cg-note-flag {
    font-size: 14px;
    margin-left: 6px;
    cursor: help;        /* native tooltip from the title= attribute hints why */
  }
  @keyframes cgSavedFlash {
    0%   { box-shadow: inset 0 0 0 3px rgba(40, 167, 69, 0.55); }
    100% { box-shadow: inset 0 0 0 0   rgba(40, 167, 69, 0);    }
  }
  .cg-just-saved { animation: cgSavedFlash 1.4s ease-out 1; }
</style>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
