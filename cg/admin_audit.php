<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to    = $_GET['to']   ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;
if ($from > $to) [$from, $to] = [$to, $from];

$action_filter   = $_GET['action']      ?? '';
$caregiver_filter= isset($_GET['caregiver_id']) && $_GET['caregiver_id'] !== '' ? (int)$_GET['caregiver_id'] : null;
$shift_filter    = isset($_GET['shift_id'])     && $_GET['shift_id']     !== '' ? (int)$_GET['shift_id']     : null;

$where = ['a.created_at >= ?', 'a.created_at < ?'];
$args  = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
if (in_array($action_filter, ['insert','update','delete'], true)) {
    $where[] = 'a.action = ?'; $args[] = $action_filter;
}
if ($caregiver_filter !== null) {
    // Match either the actor caregiver OR the shift's assigned caregiver (before/after).
    // The JSON-extract path keeps the query simple; MySQL 5.7+ on most XAMPP installs supports JSON_EXTRACT.
    $where[] = '(a.actor_caregiver_id = ?
                 OR JSON_EXTRACT(a.before_json, "$.caregiver_id") = ?
                 OR JSON_EXTRACT(a.after_json,  "$.caregiver_id") = ?)';
    $args[] = $caregiver_filter; $args[] = $caregiver_filter; $args[] = $caregiver_filter;
}
if ($shift_filter !== null) {
    $where[] = 'a.shift_id = ?'; $args[] = $shift_filter;
}

$rows = $db->query(
    'SELECT a.*, u.username AS actor_username
       FROM cg_shift_audit a
       LEFT JOIN users u ON u.id = a.actor_user_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT 1000',
    $args
)->results();

$caregivers = $db->query('SELECT id, name FROM cg_caregivers ORDER BY name')->results();
$cg_name_by_id = [];
foreach ($caregivers as $c) $cg_name_by_id[(int)$c->id] = $c->name;

function _fmt_dt($mysql_dt) {
    if (!$mysql_dt) return '';
    return date('D Y-m-d g:i a', strtotime($mysql_dt));
}

// Render a colored badge for the action verb.
function _action_badge($a) {
    $css = ['insert' => 'bg-success', 'update' => 'bg-primary', 'delete' => 'bg-danger'][$a] ?? 'bg-secondary';
    $label = ['insert' => 'created', 'update' => 'edited',  'delete' => 'deleted'][$a] ?? $a;
    return '<span class="badge ' . $css . '">' . $label . '</span>';
}

// Build a short diff summary for an update row.
function _diff_summary($before, $after, $cg_name_by_id) {
    if (!$before || !$after) return '';
    $bits = [];
    if ((int)$before['caregiver_id'] !== (int)$after['caregiver_id']) {
        $bn = $cg_name_by_id[(int)$before['caregiver_id']] ?? ('#' . $before['caregiver_id']);
        $an = $cg_name_by_id[(int)$after['caregiver_id']]  ?? ('#' . $after['caregiver_id']);
        $bits[] = "caregiver: $bn → $an";
    }
    if ($before['start_dt'] !== $after['start_dt']) {
        $bits[] = 'start: ' . _fmt_dt($before['start_dt']) . ' → ' . _fmt_dt($after['start_dt']);
    }
    if ($before['end_dt']   !== $after['end_dt']) {
        $bits[] = 'end: '   . _fmt_dt($before['end_dt'])   . ' → ' . _fmt_dt($after['end_dt']);
    }
    return implode(' &middot; ', array_map('htmlspecialchars', $bits));
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Schedule Log</h1>
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="index.php">Calendar</a></p>

  <form method="get" class="row g-2 align-items-end mb-4">
    <div class="col-md-2">
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Action</label>
      <select name="action" class="form-select">
        <option value="">All</option>
        <option value="insert" <?= $action_filter === 'insert' ? 'selected' : '' ?>>Created</option>
        <option value="update" <?= $action_filter === 'update' ? 'selected' : '' ?>>Edited</option>
        <option value="delete" <?= $action_filter === 'delete' ? 'selected' : '' ?>>Deleted</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Caregiver</label>
      <select name="caregiver_id" class="form-select">
        <option value="">Anyone</option>
        <?php foreach ($caregivers as $c): ?>
          <option value="<?= $c->id ?>" <?= ($caregiver_filter === (int)$c->id) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c->name) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Shift #</label>
      <input type="number" name="shift_id" class="form-control" value="<?= $shift_filter !== null ? (int)$shift_filter : '' ?>" min="1">
    </div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Apply</button></div>
  </form>

  <?php if (!$rows): ?>
    <div class="alert alert-secondary">No log entries in range.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>When</th>
            <th>Action</th>
            <th>Actor</th>
            <th>Shift</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $before = $r->before_json ? json_decode($r->before_json, true) : null;
            $after  = $r->after_json  ? json_decode($r->after_json,  true) : null;
            $snap   = $after ?: $before; // for display when one side is null
            $shift_cg_name = $snap ? ($cg_name_by_id[(int)$snap['caregiver_id']] ?? ('#' . $snap['caregiver_id'])) : '';
        ?>
          <tr>
            <td class="text-nowrap"><?= _fmt_dt($r->created_at) ?></td>
            <td><?= _action_badge($r->action) ?></td>
            <td>
              <?= htmlspecialchars($r->actor_name ?: ($r->actor_username ?? '(unknown)')) ?>
              <?php if ($r->actor_caregiver_id): ?>
                <span class="badge bg-light text-dark border">caregiver</span>
              <?php elseif ($r->actor_user_id): ?>
                <span class="badge bg-light text-dark border">admin</span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <?php if ($r->shift_id): ?>
                <?php
                  // Deep link to the shift via the calendar's goto + shift param. Date pulled from the snapshot.
                  $goto_date = $snap ? date('Y-m-d', strtotime($snap['start_dt'])) : '';
                ?>
                <?php if ($goto_date && $r->action !== 'delete'): ?>
                  <a href="index.php?goto=<?= urlencode($goto_date) ?>&shift=<?= (int)$r->shift_id ?>">#<?= (int)$r->shift_id ?></a>
                <?php else: ?>
                  #<?= (int)$r->shift_id ?>
                <?php endif; ?>
                <?php if ($shift_cg_name): ?>
                  <small class="text-muted d-block"><?= htmlspecialchars($shift_cg_name) ?></small>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r->action === 'insert' && $after): ?>
                <span class="text-muted small">
                  <?= htmlspecialchars(_fmt_dt($after['start_dt'])) ?> &rarr; <?= htmlspecialchars(_fmt_dt($after['end_dt'])) ?>
                </span>
              <?php elseif ($r->action === 'delete' && $before): ?>
                <span class="text-muted small">
                  <?= htmlspecialchars(_fmt_dt($before['start_dt'])) ?> &rarr; <?= htmlspecialchars(_fmt_dt($before['end_dt'])) ?>
                </span>
              <?php else: ?>
                <span class="small"><?= _diff_summary($before, $after, $cg_name_by_id) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-muted small">Showing the latest <?= count($rows) ?> entries<?= count($rows) === 1000 ? ' (capped at 1000 — narrow the range to see older entries)' : '' ?>.</p>
  <?php endif; ?>
</main>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
