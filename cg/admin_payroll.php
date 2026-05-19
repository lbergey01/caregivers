<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;

$cg_s     = cg_settings();
$ot_start = (int)($cg_s['ot_start_hour'] ?? 22);
$ot_end   = (int)($cg_s['ot_end_hour']   ?? 6);

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-d', strtotime('first day of previous month'));
$to    = $_GET['to']   ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;
if ($from > $to) [$from, $to] = [$to, $from];

$client_id_filter = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? (int)$_GET['client_id'] : null;
$include_unpayable = !empty($_GET['include_unpayable']);

$clients = cg_clientsAll(false);

// Pull caregivers (all of them — we'll filter unpayable in the loop so the
// admin can flip the toggle without re-fetching).
$caregivers_all = $db->query('SELECT * FROM cg_caregivers ORDER BY name')->results();
$cg_by_id = [];
foreach ($caregivers_all as $c) $cg_by_id[$c->id] = $c;

// Shifts that overlap the requested range, optionally filtered by client.
$range_start = $from . ' 00:00:00';
$range_end   = $to   . ' 23:59:59';
$params = [$range_start, $range_end];
$sql = 'SELECT s.*, cl.name AS client_name
          FROM cg_shifts s
          JOIN cg_clients cl ON cl.id = s.client_id
         WHERE s.end_dt   > ?
           AND s.start_dt < ?';
if ($client_id_filter !== null) { $sql .= ' AND s.client_id = ?'; $params[] = $client_id_filter; }
$sql .= ' ORDER BY s.caregiver_id, s.start_dt';
$shifts = $db->query($sql, $params)->results();

// Pre-load holidays so the inner loop is cheap.
$holidays = cg_holidaysInRange($from, $to);

// Aggregate per caregiver.
$by_cg = []; // caregiver_id => [name, totals, shifts[]]
$range_start_ts = strtotime($range_start);
$range_end_ts   = strtotime($range_end) + 1; // [start, end) for clipping

foreach ($shifts as $s) {
    $cg = $cg_by_id[$s->caregiver_id] ?? null;
    if (!$cg) continue;
    if (!$include_unpayable && !$cg->payable) continue;

    // Clip the shift to the requested date range so hours outside the range don't get counted.
    $clip = clone $s;
    $cs = max($range_start_ts, strtotime($s->start_dt));
    $ce = min($range_end_ts,   strtotime($s->end_dt));
    if ($ce <= $cs) continue;
    $clip->start_dt = date('Y-m-d H:i:s', $cs);
    $clip->end_dt   = date('Y-m-d H:i:s', $ce);

    $calc = cg_payrollComputeShift($clip, $cg, $holidays, $ot_start, $ot_end);

    if (!isset($by_cg[$cg->id])) {
        $by_cg[$cg->id] = [
            'caregiver'   => $cg,
            'shifts'      => [],
            'totals'      => ['regular'=>0,'overnight'=>0,'holiday'=>0,'hours'=>0,'pay'=>0],
        ];
    }
    $by_cg[$cg->id]['shifts'][] = [
        'shift'      => $clip,
        'orig_shift' => $s,
        'calc'       => $calc,
    ];
    $by_cg[$cg->id]['totals']['regular']   += $calc['hours']['regular'];
    $by_cg[$cg->id]['totals']['overnight'] += $calc['hours']['overnight'];
    $by_cg[$cg->id]['totals']['holiday']   += $calc['hours']['holiday'];
    $by_cg[$cg->id]['totals']['hours']     += $calc['total_hours'];
    $by_cg[$cg->id]['totals']['pay']       += $calc['total_pay'];
}

// Stable sort by caregiver name
uasort($by_cg, fn($a, $b) => strcmp($a['caregiver']->name, $b['caregiver']->name));

$grand_hours = 0; $grand_pay = 0; $any_rate_unknown = false;
foreach ($by_cg as $g) {
    $grand_hours += $g['totals']['hours'];
    $grand_pay   += $g['totals']['pay'];
    foreach ($g['shifts'] as $row) if (!$row['calc']['rate_known']) $any_rate_unknown = true;
}

function _hh($h) { return number_format($h, 2); }
function _mm($n) { return '$' . number_format($n, 2); }

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Payroll Report</h1>
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="index.php">Calendar</a></p>

  <form method="get" class="row g-2 align-items-end mb-4 no-print">
    <div class="col-md-2">
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" required>
    </div>
    <div class="col-md-2">
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Client</label>
      <select name="client_id" class="form-select">
        <option value="">All clients</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c->id ?>" <?= ($client_id_filter === (int)$c->id) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c->name) ?><?= $c->active ? '' : ' (inactive)' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" name="include_unpayable" id="include_unpayable" value="1" <?= $include_unpayable ? 'checked' : '' ?>>
        <label class="form-check-label" for="include_unpayable">Include unpayable caregivers</label>
      </div>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-primary flex-grow-1">Run report</button>
      <button class="btn btn-outline-secondary" type="button" onclick="window.print()" title="Print / save PDF">Print</button>
    </div>
  </form>

  <div class="alert alert-info no-print small">
    <strong><?= htmlspecialchars($from) ?></strong> through <strong><?= htmlspecialchars($to) ?></strong> &middot;
    overnight window <?= $ot_start ?>:00&ndash;<?= $ot_end ?>:00 &middot;
    <?= count($holidays) ?> holiday<?= count($holidays) === 1 ? '' : 's' ?> in range
    <?php if ($any_rate_unknown): ?>
      <br><span class="text-warning">⚠ Some caregivers don't have a rate set — their hours appear but $ totals are zero.</span>
    <?php endif; ?>
  </div>

  <?php if (!$by_cg): ?>
    <div class="alert alert-secondary">No shifts in range.</div>
  <?php else: ?>
    <?php foreach ($by_cg as $g):
        $cg = $g['caregiver'];
        $t  = $g['totals'];
    ?>
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <span class="badge" style="background: <?= htmlspecialchars($cg->color) ?>">&nbsp;</span>
            <strong><?= htmlspecialchars($cg->name) ?></strong>
            <?php if (!$cg->payable): ?><span class="badge bg-secondary">unpayable</span><?php endif; ?>
            <?php if ($cg->pay_rate === null): ?>
              <span class="badge bg-warning text-dark">no rate set</span>
            <?php else: ?>
              <span class="text-muted small">base <?= _mm((float)$cg->pay_rate) ?>/hr</span>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <div><strong><?= _hh($t['hours']) ?></strong> hours</div>
            <?php if ($cg->pay_rate !== null): ?>
              <div><strong><?= _mm($t['pay']) ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Client</th>
                <th>Start</th>
                <th>End</th>
                <th class="text-end">Reg hrs</th>
                <th class="text-end">OT hrs</th>
                <th class="text-end">Hol hrs</th>
                <th class="text-end">Total hrs</th>
                <th class="text-end">Pay</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($g['shifts'] as $row):
                  $s = $row['shift']; $c = $row['calc'];
              ?>
                <tr>
                  <td><?= date('D Y-m-d', strtotime($s->start_dt)) ?></td>
                  <td><?= htmlspecialchars($s->client_name) ?></td>
                  <td><?= date('g:i a', strtotime($s->start_dt)) ?></td>
                  <td><?= date('g:i a', strtotime($s->end_dt)) ?><?= date('Y-m-d', strtotime($s->end_dt)) !== date('Y-m-d', strtotime($s->start_dt)) ? ' (+1d)' : '' ?></td>
                  <td class="text-end"><?= $c['hours']['regular']   > 0 ? _hh($c['hours']['regular'])   : '' ?></td>
                  <td class="text-end"><?= $c['hours']['overnight'] > 0 ? _hh($c['hours']['overnight']) : '' ?></td>
                  <td class="text-end"><?= $c['hours']['holiday']   > 0 ? _hh($c['hours']['holiday'])   : '' ?></td>
                  <td class="text-end"><?= _hh($c['total_hours']) ?></td>
                  <td class="text-end"><?= $c['rate_known'] ? _mm($c['total_pay']) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="table-light">
                <th colspan="4" class="text-end">Subtotal</th>
                <th class="text-end"><?= _hh($t['regular']) ?></th>
                <th class="text-end"><?= _hh($t['overnight']) ?></th>
                <th class="text-end"><?= _hh($t['holiday']) ?></th>
                <th class="text-end"><?= _hh($t['hours']) ?></th>
                <th class="text-end"><?= ($cg->pay_rate !== null) ? _mm($t['pay']) : '—' ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="card">
      <div class="card-body d-flex justify-content-between">
        <strong>Grand total</strong>
        <div class="text-end">
          <div><strong><?= _hh($grand_hours) ?></strong> hours</div>
          <div><strong><?= _mm($grand_pay) ?></strong></div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<style>
  @media print {
    .no-print { display: none !important; }
    main      { max-width: 100% !important; }
    .card     { break-inside: avoid; }
    a[href]::after { content: ''; }
  }
</style>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
