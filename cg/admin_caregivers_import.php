<?php
/**
 * Bulk caregiver / visitor import.
 *
 * CSV columns: name, phone, email, role, color
 *   - name + phone required; the rest are optional.
 *   - role  defaults to 'caregiver'; 'visitor' for family/friends.
 *   - color defaults to a deterministic pick from a fixed palette (hash of name).
 *
 * Two-step UX:
 *   1) GET — show upload form (file input + paste-in textarea).
 *   2) POST action=preview — parse, validate, show what would happen per row.
 *   3) POST action=commit  — parse again, actually create/update users, perms,
 *      and cg_caregivers rows. Idempotent on normalized phone (re-uploading a
 *      sheet updates existing rows rather than duplicating).
 *
 * pwsms integration: each imported row gets a UserSpice user account with
 * username = digits-only phone. pwsms_config phone_lookup matches on that
 * format, so login-by-SMS works immediately after import.
 */

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isManager()) { die('Admin or manager only.'); }

global $db;

/* ---------- helpers ---------- */

// Deterministic color pick from a small palette — re-importing the same name
// gives the same color, so visitors don't see their chip change between runs.
function imp_default_color($name) {
    static $palette = [
        '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#1abc9c',
        '#3498db', '#9b59b6', '#34495e', '#16a085', '#27ae60',
        '#2980b9', '#8e44ad', '#d35400', '#c0392b', '#7f8c8d',
    ];
    $i = abs(crc32($name)) % count($palette);
    return $palette[$i];
}

function imp_split_name($name) {
    $parts = preg_split('/\s+/', trim((string)$name), 2);
    return [$parts[0] ?? '', $parts[1] ?? ''];
}

// Parse a raw CSV string into validated rows. Returns array of:
//   ['ok' => bool, 'msg' => '', 'data' => ['name'=>, 'phone10'=>, 'phone_fmt'=>,
//                                          'email'=>, 'role'=>, 'color'=>]]
function imp_parse_csv($raw) {
    $raw = str_replace(["\r\n", "\r"], "\n", (string)$raw);
    $fh = fopen('php://memory', 'r+');
    fwrite($fh, $raw);
    rewind($fh);

    $rows = [];
    $header = null;
    while (($cols = fgetcsv($fh)) !== false) {
        if ($cols === [null] || $cols === [''] || count(array_filter($cols, fn($c) => trim((string)$c) !== '')) === 0) continue;
        if ($header === null) {
            // Normalize header: lowercase, trim, replace spaces with underscores.
            $header = array_map(fn($h) => strtolower(trim((string)$h)), $cols);
            continue;
        }
        $r = [];
        foreach ($header as $i => $key) {
            $r[$key] = trim((string)($cols[$i] ?? ''));
        }
        $rows[] = imp_validate_row($r);
    }
    fclose($fh);
    return $rows;
}

function imp_validate_row($r) {
    $name  = $r['name'] ?? '';
    $phone = $r['phone'] ?? '';
    $email = $r['email'] ?? '';
    $role  = strtolower($r['role'] ?? '') === 'visitor' ? 'visitor' : 'caregiver';
    $color = $r['color'] ?? '';

    if ($name === '')  return ['ok' => false, 'msg' => 'Missing name.',  'data' => $r];

    $phone10 = pwsms_normalize_phone($phone);
    if ($phone10 === null) return ['ok' => false, 'msg' => 'Phone must be 10 digits.', 'data' => $r];

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = imp_default_color($name);
    }

    $phone_fmt = substr($phone10, 0, 3) . '-' . substr($phone10, 3, 3) . '-' . substr($phone10, 6);

    return ['ok' => true, 'msg' => '', 'data' => [
        'name'      => $name,
        'phone10'   => $phone10,
        'phone_fmt' => $phone_fmt,
        'email'     => $email,
        'role'      => $role,
        'color'     => $color,
    ]];
}

// Compute the action this row would take WITHOUT performing it.
// Returns: ['action' => 'insert'|'update'|'noop', 'cg_id' => int|null, 'user_id' => int|null]
function imp_lookup($data) {
    global $db;
    // cg_caregivers lookup by normalized phone (any stored format).
    $cg = $db->query(
        "SELECT * FROM cg_caregivers
         WHERE REGEXP_REPLACE(phone, '[^0-9]', '') IN (?, ?)
         ORDER BY active DESC, id ASC LIMIT 1",
        [$data['phone10'], '1' . $data['phone10']]
    )->first();

    // users lookup: by username = digits, or by linked user_id if cg row has one.
    $u = null;
    if ($cg && $cg->user_id) {
        $u = $db->query('SELECT id FROM users WHERE id = ?', [(int)$cg->user_id])->first();
    }
    if (!$u) {
        $u = $db->query('SELECT id FROM users WHERE username = ? LIMIT 1', [$data['phone10']])->first();
    }

    return [
        'action'  => $cg ? 'update' : 'insert',
        'cg_id'   => $cg ? (int)$cg->id : null,
        'user_id' => $u  ? (int)$u->id  : null,
    ];
}

// Perform the row's actions. Returns same shape as imp_lookup() with a
// 'msg' summary. Throws on hard DB errors so the caller can roll up per-row.
function imp_apply($data) {
    global $db;
    $look = imp_lookup($data);
    $user_id = $look['user_id'];

    [$fname, $lname] = imp_split_name($data['name']);

    // 1) Ensure a UserSpice user exists. Login is via pwsms (phone), so the
    //    password is a random throwaway — kept non-null in case some auth
    //    code path tries to read it.
    if (!$user_id) {
        $email = $data['email'] !== '' ? $data['email'] : $data['phone10'] . '@noreply.invalid';
        $pw    = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 10]);
        $db->query(
            'INSERT INTO users
              (username, fname, lname, email, password, permissions, active,
               email_verified, language, created, join_date, oauth_tos_accepted)
             VALUES (?, ?, ?, ?, ?, 1, 1, 1, ?, ?, ?, 1)',
            [
                $data['phone10'], $fname, $lname, $email, $pw,
                'en-US', date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
            ]
        );
        $user_id = (int)$db->lastId();
        // Base "User" permission so they show up in UserSpice lists.
        $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, 1)', [$user_id]);
    } else {
        // User exists — keep name/email fresh if CSV provided them.
        if ($data['email'] !== '') {
            $db->query('UPDATE users SET fname=?, lname=?, email=? WHERE id=?',
                       [$fname, $lname, $data['email'], $user_id]);
        } else {
            $db->query('UPDATE users SET fname=?, lname=? WHERE id=?', [$fname, $lname, $user_id]);
        }
    }

    // 2) Upsert the cg_caregivers row.
    if ($look['cg_id']) {
        $before = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$look['cg_id']])->first();
        $db->query(
            'UPDATE cg_caregivers
                SET name=?, phone=?, email=?, user_id=?, role=?, color=?, active=1
              WHERE id = ?',
            [
                $data['name'], $data['phone_fmt'],
                $data['email'] !== '' ? $data['email'] : ($before->email ?? ''),
                $user_id, $data['role'], $data['color'], $look['cg_id'],
            ]
        );
        $after = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$look['cg_id']])->first();
        cg_logCaregiverAudit($look['cg_id'], 'update', cg_caregiverSnapshot($before), cg_caregiverSnapshot($after));
        $cg_id = $look['cg_id'];
        $verb  = 'updated';
    } else {
        $db->query(
            'INSERT INTO cg_caregivers (name, phone, email, user_id, role, color, active)
             VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$data['name'], $data['phone_fmt'], $data['email'], $user_id, $data['role'], $data['color']]
        );
        $cg_id = (int)$db->lastId();
        $fresh = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$cg_id])->first();
        cg_logCaregiverAudit($cg_id, 'insert', null, cg_caregiverSnapshot($fresh));
        $verb = 'created';
    }

    // 3) Sync role permission on the linked user.
    cg_syncRolePermission($user_id, $data['role']);

    return [
        'action'  => $verb,
        'cg_id'   => $cg_id,
        'user_id' => $user_id,
        'msg'     => "{$verb} ({$data['role']})",
    ];
}

/* ---------- request handling ---------- */

$action = $_POST['action'] ?? '';
$mode   = 'upload';   // upload | preview | result
$rows   = [];
$raw    = '';
$results = [];
$error  = '';

if ($action === 'preview' || $action === 'commit') {
    // Accept either a pasted textarea or an uploaded file.
    if (!empty($_FILES['csvfile']['tmp_name']) && is_uploaded_file($_FILES['csvfile']['tmp_name'])) {
        $raw = file_get_contents($_FILES['csvfile']['tmp_name']);
    } else {
        $raw = (string)($_POST['csvtext'] ?? '');
    }
    if (trim($raw) === '') {
        $error = 'No CSV provided.';
    } else {
        $rows = imp_parse_csv($raw);
        if (!$rows) $error = 'No data rows found. The first row must be a header (name,phone,…).';
    }
}

if ($action === 'preview' && !$error) {
    $mode = 'preview';
}

if ($action === 'commit' && !$error) {
    $mode = 'result';
    foreach ($rows as $idx => $row) {
        if (!$row['ok']) {
            $results[$idx] = ['action' => 'skipped', 'msg' => 'Validation: ' . $row['msg'], 'cg_id' => null, 'user_id' => null];
            continue;
        }
        try {
            $results[$idx] = imp_apply($row['data']);
        } catch (Throwable $e) {
            $results[$idx] = ['action' => 'error', 'msg' => $e->getMessage(), 'cg_id' => null, 'user_id' => null];
        }
    }
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Bulk import</h1>
  <p>
    <a href="admin_caregivers.php">&larr; Caregivers</a> &middot;
    <a href="admin.php">Admin</a>
  </p>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($mode === 'upload'): ?>
    <div class="card mb-3">
      <div class="card-header">CSV format</div>
      <div class="card-body">
        <p class="small text-muted mb-2">First row is the header. <code>name</code> and <code>phone</code> are required; the rest are optional.</p>
        <pre class="bg-light p-2 small mb-2"><code>name,phone,email,role,color
Sarah Smith,2675551234,sarah@example.com,visitor,#ff5733
Mike Johnson,267-555-9876,,visitor,
Becki Hartzell,2675554321,becki@example.com,caregiver,</code></pre>
        <ul class="small text-muted mb-0">
          <li><b>phone</b> — any format; normalized to 10 digits. A leading <code>1</code> is stripped.</li>
          <li><b>role</b> — <code>caregiver</code> (default) or <code>visitor</code>.</li>
          <li><b>color</b> — <code>#rrggbb</code>; blank picks a stable color from a built-in palette.</li>
          <li>Idempotent: re-uploading the same sheet updates rows (matched by phone) rather than duplicating.</li>
        </ul>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="card">
      <input type="hidden" name="action" value="preview">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label"><b>Upload CSV file</b></label>
          <input type="file" name="csvfile" accept=".csv,text/csv" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">… or paste CSV</label>
          <textarea name="csvtext" class="form-control font-monospace" rows="6"
                    placeholder="name,phone,email,role,color"></textarea>
        </div>
      </div>
      <div class="card-footer text-end">
        <button class="btn btn-primary">Preview</button>
      </div>
    </form>

  <?php elseif ($mode === 'preview'): ?>
    <div class="alert alert-info small">
      Review the planned actions below. Nothing has been committed yet. Click <b>Commit import</b> to apply.
    </div>
    <table class="table table-sm align-middle">
      <thead><tr>
        <th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Role</th><th>Color</th><th>Action</th><th>Note</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r):
          if (!$r['ok']) {
              $action_label = 'skip';
              $note = $r['msg'];
              $d = $r['data'];
          } else {
              $d = $r['data'];
              $look = imp_lookup($d);
              $action_label = $look['action'];
              $note = $look['cg_id']
                  ? 'matches existing #' . $look['cg_id'] . ($look['user_id'] ? ' (user '.$look['user_id'].')' : ' (no linked user)')
                  : ($look['user_id'] ? 'new cg row, links existing user '.$look['user_id'] : 'new cg row + new user');
          }
      ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($d['name'] ?? '') ?></td>
          <td><?= htmlspecialchars($d['phone_fmt'] ?? ($d['phone'] ?? '')) ?></td>
          <td><?= htmlspecialchars($d['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($d['role'] ?? '') ?></td>
          <td>
            <?php if (!empty($d['color'])): ?>
              <span class="d-inline-block" style="width:14px;height:14px;background:<?= htmlspecialchars($d['color']) ?>;border:1px solid #ccc;border-radius:50%;vertical-align:middle"></span>
              <small class="text-muted"><?= htmlspecialchars($d['color']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php $cls = ['insert'=>'success','update'=>'warning','skip'=>'secondary'][$action_label] ?? 'secondary'; ?>
            <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($action_label) ?></span>
          </td>
          <td class="small text-muted"><?= htmlspecialchars($note) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post">
      <input type="hidden" name="action" value="commit">
      <input type="hidden" name="csvtext" value="<?= htmlspecialchars($raw) ?>">
      <div class="d-flex justify-content-between">
        <a href="admin_caregivers_import.php" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary">Commit import</button>
      </div>
    </form>

  <?php elseif ($mode === 'result'): ?>
    <?php
      $count = ['created'=>0, 'updated'=>0, 'skipped'=>0, 'error'=>0];
      foreach ($results as $r) $count[$r['action']] = ($count[$r['action']] ?? 0) + 1;
    ?>
    <div class="alert alert-success">
      Import complete:
      <b><?= $count['created'] ?></b> created,
      <b><?= $count['updated'] ?></b> updated,
      <b><?= $count['skipped'] ?></b> skipped,
      <b><?= $count['error'] ?></b> errored.
    </div>
    <table class="table table-sm align-middle">
      <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Action</th><th>Result</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r):
          $res = $results[$i] ?? ['action'=>'?', 'msg'=>'', 'cg_id'=>null];
          $d = $r['data'];
          $cls = ['created'=>'success','updated'=>'warning','skipped'=>'secondary','error'=>'danger'][$res['action']] ?? 'secondary';
      ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td>
            <?php if (!empty($res['cg_id'])): ?>
              <a href="admin_caregivers.php#cg-<?= (int)$res['cg_id'] ?>"><?= htmlspecialchars($d['name'] ?? '') ?></a>
            <?php else: ?>
              <?= htmlspecialchars($d['name'] ?? '') ?>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($d['phone_fmt'] ?? ($d['phone'] ?? '')) ?></td>
          <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($res['action']) ?></span></td>
          <td class="small text-muted"><?= htmlspecialchars($res['msg']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><a class="btn btn-outline-secondary" href="admin_caregivers_import.php">Import another</a>
       <a class="btn btn-outline-primary" href="admin_caregivers.php">Back to caregivers</a></p>
  <?php endif; ?>
</main>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
