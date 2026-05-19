<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $d = trim($_POST['hdate'] ?? '');
            $n = trim($_POST['name']  ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) throw new Exception('Date must be YYYY-MM-DD.');
            if ($n === '') throw new Exception('Name is required.');
            $db->query('INSERT INTO cg_holidays (hdate, name) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE name = VALUES(name)', [$d, $n]);
            $msg = 'Holiday saved.';
        } elseif ($action === 'delete' && !empty($_POST['hdate'])) {
            $db->query('DELETE FROM cg_holidays WHERE hdate = ?', [$_POST['hdate']]);
            $msg = 'Holiday removed.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$rows = $db->query('SELECT hdate, name FROM cg_holidays ORDER BY hdate DESC')->results();

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Holidays</h1>
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="index.php">Calendar</a></p>
  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">Add / update holiday</div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <div class="col-md-3">
          <label class="form-label">Date</label>
          <input type="date" name="hdate" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" placeholder="e.g. Thanksgiving" required>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Save</button></div>
      </form>
      <small class="text-muted">Hours worked on these dates get each caregiver's holiday differential on the payroll report.</small>
    </div>
  </div>

  <?php if (!$rows): ?>
    <p class="text-muted">No holidays defined.</p>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead><tr><th style="width:160px">Date</th><th>Name</th><th style="width:120px"></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r->hdate) ?></td>
          <td><?= htmlspecialchars($r->name) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Remove this holiday?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="hdate" value="<?= htmlspecialchars($r->hdate) ?>">
              <button class="btn btn-sm btn-outline-danger">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
