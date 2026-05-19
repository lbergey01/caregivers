<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }   // managers can edit but not view the log

global $db;

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to    = $_GET['to']   ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;
if ($from > $to) [$from, $to] = [$to, $from];

$action_filter      = $_GET['action']       ?? '';
$caregiver_filter   = isset($_GET['caregiver_id']) && $_GET['caregiver_id'] !== '' ? (int)$_GET['caregiver_id'] : null;

$where = ['a.created_at >= ?', 'a.created_at < ?'];
$args  = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
if (in_array($action_filter, ['insert','update','delete'], true)) {
    $where[] = 'a.action = ?'; $args[] = $action_filter;
}
if ($caregiver_filter !== null) {
    $where[] = 'a.caregiver_id = ?'; $args[] = $caregiver_filter;
}

$rows = $db->query(
    'SELECT a.*, u.username AS actor_username, c.name AS target_name
       FROM cg_caregiver_audit a
       LEFT JOIN users         u ON u.id = a.actor_user_id
       LEFT JOIN cg_caregivers c ON c.id = a.caregiver_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT 1000',
    $args
)->results();

$caregivers = $db->query('SELECT id, name FROM cg_caregivers ORDER BY name')->results();

function _fmt_dt($mysql_dt) {
    if (!$mysql_dt) return '';
    return date('D Y-m-d g:i a', strtotime($mysql_dt));
}
function _action_badge($a) {
    $css   = ['insert' => 'bg-success', 'update' => 'bg-primary', 'delete' => 'bg-danger'][$a] ?? 'bg-secondary';
    $label = ['insert' => 'added',      'update' => 'edited',   'delete' => 'deleted'][$a] ?? $a;
    return '<span class="badge ' . $css . '">' . $label . '</span>';
}

// Build a short human diff for an update row. Skips unchanged fields and pretty-prints
// the keys we know about.
function _diff_summary($before, $after) {
    if (!$before || !$after) return '';
    $labels = [
        'name'    => 'name',
        'phone'   => 'phone',
        'email'   => 'email',
        'user_id' => 'linked login',
        'color'   => 'color',
        'active'  => 'active',
        'payable' => 'payable',
        'pay_rate'      => 'rate',
        'diff_ot_mult'  => 'OT mult',
        'diff_ot_add'   => 'OT add',
        'diff_hol_mult' => 'Holiday mult',
        'diff_hol_add'  => 'Holiday add',
    ];
    $bits = [];
    foreach ($labels as $k => $label) {
        $b = $before[$k] ?? null;
        $a = $after[$k]  ?? null;
        if ($b === $a) continue;
        $bits[] = htmlspecialchars($label . ': ' . _fmt_audit_val($b) . ' → ' . _fmt_audit_val($a));
    }
    return implode(' &middot; ', $bits);
}
function _fmt_audit_val($v) {
    if ($v === null || $v === '') return '(blank)';
    if ($v === 0  || $v === '0')  return 'no';
    if ($v === 1  || $v === '1')  return 'yes';
    return (string)$v;
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Caregiver Log</h1>
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="admin_caregivers.php">Caregivers</a> &middot; <a href="admin_audit.php">Schedule Log</a></p>

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
        <option value="insert" <?= $action_filter === 'insert' ? 'selected' : '' ?>>Added</option>
        <option value="update" <?= $action_filter === 'update' ? 'selected' : '' ?>>Edited</option>
        <option value="delete" <?= $action_filter === 'delete' ? 'selected' : '' ?>>Deleted</option>
      </select>
    </div>
    <div class="col-md-4">
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
    <div class="col-md-2"><button class="btn btn-primary w-100">Apply</button></div>
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
            <th>Caregiver</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $before = $r->before_json ? json_decode($r->before_json, true) : null;
            $after  = $r->after_json  ? json_decode($r->after_json,  true) : null;
            $snap   = $after ?: $before;
            $target_name = $r->target_name
                ?? ($snap['name'] ?? ('#' . (int)$r->caregiver_id));
        ?>
          <tr>
            <td class="text-nowrap"><?= _fmt_dt($r->created_at) ?></td>
            <td><?= _action_badge($r->action) ?></td>
            <td>
              <?= htmlspecialchars($r->actor_name ?: ($r->actor_username ?? '(unknown)')) ?>
              <?php if ($r->actor_caregiver_id): ?>
                <span class="badge bg-light text-dark border">caregiver</span>
              <?php elseif ($r->actor_user_id): ?>
                <span class="badge bg-light text-dark border">admin/mgr</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)$target_name) ?></td>
            <td>
              <?php if ($r->action === 'insert' && $after): ?>
                <span class="text-muted small">
                  name=<?= htmlspecialchars($after['name'] ?? '') ?><?php
                    if (!empty($after['phone'])) echo ' &middot; phone=' . htmlspecialchars($after['phone']);
                    if (!empty($after['email'])) echo ' &middot; email=' . htmlspecialchars($after['email']);
                  ?>
                </span>
              <?php elseif ($r->action === 'delete' && $before): ?>
                <span class="text-muted small">deleted</span>
              <?php else: ?>
                <span class="small"><?= _diff_summary($before, $after) ?></span>
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
