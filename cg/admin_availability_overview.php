<?php
// Availability heatmap overview. Admin/manager only.
//
//   /cg/admin_availability_overview.php
//     ?week=YYYY-MM-DD    -- anchor Sunday; defaults to current week
//     ?cgs=1,2,5          -- caregiver IDs to include; default = all active
//
// Renders a 7-day x 48-slot grid where each cell's color intensity reflects
// how many of the selected caregivers are available (or preferred) for that
// 30-minute window. Click a cell to see who's available, preferred, or
// blocked.
//
// This is Phase 2 of the availability layer — Phase 3 will subtract shifts
// already booked from the available pool.

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isManager()) { die('Admin or manager only.'); }

global $db;

// --- resolve params ---------------------------------------------------------
$week_param = $_GET['week'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_param)) $week_param = date('Y-m-d');
$week_start = cg_weekStartSunday($week_param);
$week_end   = date('Y-m-d', strtotime("$week_start +6 days"));

$prev_week = date('Y-m-d', strtotime("$week_start -7 days"));
$next_week = date('Y-m-d', strtotime("$week_start +7 days"));
$this_week = cg_weekStartSunday(date('Y-m-d'));

$all_cgs = $db->query(
    'SELECT id, name, color FROM cg_caregivers WHERE active = 1 ORDER BY name'
)->results();

if (isset($_GET['cgs'])) {
    $selected_ids = [];
    foreach (explode(',', (string)$_GET['cgs']) as $bit) {
        $id = (int)trim($bit);
        if ($id > 0) $selected_ids[] = $id;
    }
} else {
    // Default: every active caregiver.
    $selected_ids = array_map(fn($c) => (int)$c->id, $all_cgs);
}
$selected_set = array_flip($selected_ids);

// Filter selected IDs to ones that actually exist + are active (in case of
// stale URLs after a caregiver was deactivated).
$valid_ids = array_map(fn($c) => (int)$c->id, $all_cgs);
$selected_ids = array_values(array_intersect($selected_ids, $valid_ids));

// --- build the matrix ------------------------------------------------------
$built = cg_buildAvailabilityMatrix($selected_ids, $week_start);
$matrix = $built['matrix'];
$perCg  = $built['perCg'];

$total = count($selected_ids);
$cg_meta = [];
foreach ($all_cgs as $c) {
    $cg_meta[(int)$c->id] = ['id' => (int)$c->id, 'name' => $c->name, 'color' => $c->color];
}

$days_short = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$day_dates = [];
for ($d = 0; $d < 7; $d++) $day_dates[$d] = date('M j', strtotime("$week_start +$d days"));

// JSON payload for drill-down. Trim per-caregiver statuses to first-letter
// codes (p/a/u/k) to keep the payload compact for big caregiver lists.
$compact = ['p'=>'p','a'=>'a','u'=>'u','k'=>'k',
            'preferred'=>'p','available'=>'a','unavailable'=>'u','unknown'=>'k'];
$perCg_compact = [];
foreach ($perCg as $cg_id => $by_day) {
    $rows = [];
    foreach ($by_day as $d => $slots) {
        $line = '';
        foreach ($slots as $st) $line .= $compact[$st] ?? 'k';
        $rows[$d] = $line;  // 48-char string per day
    }
    $perCg_compact[$cg_id] = $rows;
}

$page_payload = [
    'total'    => $total,
    'matrix'   => $matrix,
    'perCg'    => $perCg_compact,
    'cgMeta'   => $cg_meta,
    'selected' => $selected_ids,
    'dayDates' => $day_dates,
];

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container-fluid my-3">
  <h1 class="mb-1">Availability Overview</h1>
  <p class="text-muted small mb-3">
    <a href="admin.php">&larr; Admin</a> &middot;
    <a href="index.php">Calendar</a> &middot;
    <a href="admin_caregivers.php">Caregivers</a>
  </p>

  <form method="get" id="ov-form" class="card mb-3">
    <div class="card-body py-2">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <div class="btn-group btn-group-sm" role="group">
          <a class="btn btn-outline-secondary" href="?week=<?= htmlspecialchars($prev_week) ?>&cgs=<?= htmlspecialchars(implode(',', $selected_ids)) ?>">&laquo; Prev</a>
          <a class="btn btn-outline-secondary <?= $week_start === $this_week ? 'active' : '' ?>"
             href="?week=<?= htmlspecialchars($this_week) ?>&cgs=<?= htmlspecialchars(implode(',', $selected_ids)) ?>">This week</a>
          <a class="btn btn-outline-secondary" href="?week=<?= htmlspecialchars($next_week) ?>&cgs=<?= htmlspecialchars(implode(',', $selected_ids)) ?>">Next &raquo;</a>
        </div>
        <div class="text-muted small">
          Week of <strong><?= htmlspecialchars(date('M j, Y', strtotime($week_start))) ?></strong>
          – <?= htmlspecialchars(date('M j, Y', strtotime($week_end))) ?>
        </div>
        <input type="hidden" name="week" value="<?= htmlspecialchars($week_start) ?>">
        <div class="ms-auto small text-muted">
          Showing <strong><?= (int)$total ?></strong> of <?= count($all_cgs) ?> active caregiver<?= count($all_cgs) === 1 ? '' : 's' ?>
        </div>
      </div>

      <details>
        <summary class="small text-muted" style="cursor:pointer">Caregiver filter</summary>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="ov-all">Select all</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="ov-none">Clear</button>
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </div>
        <div class="mt-2 row row-cols-2 row-cols-md-3 row-cols-lg-4 g-1">
          <?php foreach ($all_cgs as $c):
              $checked = isset($selected_set[(int)$c->id]) ? 'checked' : '';
          ?>
            <div class="col">
              <label class="d-flex align-items-center gap-1 small">
                <input class="form-check-input ov-cg-check" type="checkbox"
                       name="cgs[]" value="<?= (int)$c->id ?>" <?= $checked ?>>
                <span class="cg-color-dot" style="background: <?= htmlspecialchars($c->color) ?>"></span>
                <span><?= htmlspecialchars($c->name) ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </details>
    </div>
  </form>

  <?php if ($total === 0): ?>
    <div class="alert alert-warning">
      No caregivers selected. Pick at least one in the filter above.
    </div>
  <?php else: ?>
    <div class="row g-3">
      <div class="col-lg-9">
        <div class="card">
          <div class="card-body p-2">
            <div class="ov-grid-wrap">
              <table class="ov-grid" aria-label="Availability heatmap">
                <thead>
                  <tr>
                    <th class="ov-time-col"></th>
                    <?php for ($d = 0; $d < 7; $d++): ?>
                      <th class="ov-day-head">
                        <div><?= $days_short[$d] ?></div>
                        <small class="text-muted"><?= htmlspecialchars($day_dates[$d]) ?></small>
                      </th>
                    <?php endfor; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php for ($slot = 0; $slot < 48; $slot++):
                      $h = intdiv($slot, 2);
                      $m = ($slot % 2) * 30;
                      $label_h = $h === 0 ? '12a' : ($h < 12 ? $h.'a' : ($h === 12 ? '12p' : ($h - 12).'p'));
                      $show_label = ($slot % 2 === 0);
                  ?>
                    <tr class="<?= $slot % 2 === 0 ? 'ov-row-hour' : 'ov-row-half' ?>">
                      <th class="ov-time-col"><?= $show_label ? htmlspecialchars($label_h) : '' ?></th>
                      <?php for ($d = 0; $d < 7; $d++):
                          $cell = $matrix[$d][$slot];
                          $supply = $cell['p'] + $cell['a'];   // green pool
                          $blocked = $cell['u'];
                          // Intensity: 0..1 of how much of the selected roster is available.
                          $intensity = $total > 0 ? ($supply / $total) : 0;
                          // Pure red tint when no one's available AND someone has explicitly said "no";
                          // gray when everyone's "unknown" (no answer yet).
                          if ($supply === 0 && $blocked > 0) {
                              $class = 'ov-cell-blocked';
                          } elseif ($supply === 0) {
                              $class = 'ov-cell-empty';
                          } else {
                              $class = 'ov-cell-supply';
                          }
                      ?>
                        <td class="ov-cell <?= $class ?>"
                            data-day="<?= $d ?>" data-slot="<?= $slot ?>"
                            style="<?= $class === 'ov-cell-supply' ? '--ov-i:'.number_format($intensity, 3, '.', '') : '' ?>"
                            title="<?= $supply ?> available / <?= $total ?>"
                        ><?php if ($supply > 0): ?><span class="ov-num"><?= $supply ?></span><?php endif; ?></td>
                      <?php endfor; ?>
                    </tr>
                  <?php endfor; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center mt-2 small text-muted gap-3">
          <span class="d-flex align-items-center gap-1"><span class="ov-key ov-cell-empty"></span>0 available (no answer)</span>
          <span class="d-flex align-items-center gap-1"><span class="ov-key ov-cell-blocked"></span>0 available (someone blocked)</span>
          <span class="d-flex align-items-center gap-1"><span class="ov-key ov-cell-supply" style="--ov-i:0.25"></span>some</span>
          <span class="d-flex align-items-center gap-1"><span class="ov-key ov-cell-supply" style="--ov-i:1"></span>all selected</span>
          <span class="ms-auto">Click any cell to see who.</span>
        </div>
      </div>

      <div class="col-lg-3">
        <div class="card ov-drill" id="ov-drill">
          <div class="card-header py-2 small">
            <strong id="ov-drill-when">Click a cell</strong>
            <button type="button" class="btn-close float-end" id="ov-drill-close" aria-label="Close" style="display:none"></button>
          </div>
          <div class="card-body p-2 small">
            <div id="ov-drill-empty" class="text-muted">No cell selected.</div>
            <div id="ov-drill-content" style="display:none">
              <div class="mb-2">
                <span class="badge bg-success" id="ov-drill-avail-count">0</span> available
                <span class="badge bg-primary ms-1" id="ov-drill-pref-count">0</span> preferred
                <span class="badge bg-danger ms-1" id="ov-drill-unav-count">0</span> blocked
                <span class="badge bg-secondary ms-1" id="ov-drill-unkn-count">0</span> unknown
              </div>
              <ul class="list-unstyled mb-0" id="ov-drill-list"></ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<style>
  .cg-color-dot { display: inline-block; width: 10px; height: 10px;
    border-radius: 50%; border: 1px solid rgba(0,0,0,0.15); vertical-align: middle; }
  .ov-grid-wrap { overflow-x: auto; }
  .ov-grid {
    border-collapse: collapse; width: 100%;
    table-layout: fixed; font-size: 11px;
    user-select: none; -webkit-user-select: none;
  }
  .ov-grid th, .ov-grid td { border: 1px solid #e5e5e5; padding: 0; }
  .ov-time-col { width: 44px; min-width: 44px; font-size: 11px; color: #888;
    text-align: right; padding: 0 4px; font-weight: normal; background: #fafafa; }
  .ov-day-head { text-align: center; font-size: 12px; padding: 4px 2px; background: #f5f5f5; }
  .ov-cell { height: 18px; cursor: pointer; text-align: center; vertical-align: middle;
    color: #fff; transition: outline 0.1s; }
  .ov-row-hour .ov-cell { border-top: 1px solid #cfcfcf; }
  /* Heatmap: green tint scaled by --ov-i in [0..1]. Caps at ~0.85 so even
     "all available" stays visually distinct from a saturated full-green. */
  .ov-cell-supply  { background: rgba(46, 204, 113, calc(0.15 + 0.7 * var(--ov-i, 0))); color: #1f3a2a; }
  .ov-cell-empty   { background: #f8f9fa; color: #aaa; }
  .ov-cell-blocked { background: rgba(231, 76, 60, 0.45); color: #fff; }
  .ov-cell.ov-cell-selected { outline: 2px solid #2c3e50; outline-offset: -2px; z-index: 1; position: relative; }
  .ov-num { font-weight: 600; font-size: 10px; }
  .ov-key { display: inline-block; width: 14px; height: 14px; border: 1px solid rgba(0,0,0,0.1); border-radius: 2px; }
  .ov-drill { position: sticky; top: 8px; }
  .ov-pill { display: inline-block; width: 10px; height: 10px; border-radius: 2px;
    border: 1px solid rgba(0,0,0,0.1); margin-right: 4px; vertical-align: middle; }
  .ov-pill-p { background: rgba(52, 152, 219, 0.75); }
  .ov-pill-a { background: rgba(46, 204, 113, 0.7); }
  .ov-pill-u { background: rgba(231, 76, 60, 0.7); }
  .ov-pill-k { background: #e0e0e0; }
  @media (max-width: 992px) {
    .ov-drill { position: static; }
  }
</style>

<script>
(function() {
  const data = <?= json_encode($page_payload, JSON_UNESCAPED_SLASHES) ?>;
  if (!data || data.total === 0) return;

  const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const grid    = document.querySelector('.ov-grid');
  const drill   = document.getElementById('ov-drill');
  const empty   = document.getElementById('ov-drill-empty');
  const content = document.getElementById('ov-drill-content');
  const closeBtn = document.getElementById('ov-drill-close');
  if (!grid) return;

  function slotLabel(s) {
    const h = Math.floor(s / 2), m = (s % 2) ? '30' : '00';
    const h12 = h === 0 ? 12 : (h <= 12 ? h : h - 12);
    return h12 + ':' + m + (h < 12 ? 'a' : 'p');
  }

  function fmtRange(s) {
    const end = s + 1;
    return slotLabel(s) + '–' + slotLabel(end);
  }

  function selectCell(td) {
    grid.querySelectorAll('.ov-cell.ov-cell-selected').forEach(c => c.classList.remove('ov-cell-selected'));
    td.classList.add('ov-cell-selected');

    const d = +td.dataset.day, s = +td.dataset.slot;
    document.getElementById('ov-drill-when').textContent =
      days[d] + ' ' + (data.dayDates[d] || '') + ' · ' + fmtRange(s);
    closeBtn.style.display = '';

    // Gather per-caregiver status by reading the compact per-day status string.
    const buckets = { p: [], a: [], u: [], k: [] };
    data.selected.forEach(cgId => {
      const row = data.perCg[cgId];
      if (!row) return;
      const line = row[d] || '';
      const ch = line.charAt(s) || 'k';
      buckets[ch].push(cgId);
    });
    document.getElementById('ov-drill-pref-count').textContent  = buckets.p.length;
    document.getElementById('ov-drill-avail-count').textContent = buckets.a.length;
    document.getElementById('ov-drill-unav-count').textContent  = buckets.u.length;
    document.getElementById('ov-drill-unkn-count').textContent  = buckets.k.length;

    const list = document.getElementById('ov-drill-list');
    list.innerHTML = '';
    // Render preferred + available first (the actionable supply), then blocked, then unknown.
    const order = ['p', 'a', 'u', 'k'];
    const label = { p: 'Preferred', a: 'Available', u: 'Blocked', k: 'No answer' };
    order.forEach(key => {
      if (!buckets[key].length) return;
      const head = document.createElement('li');
      head.className = 'mt-1 text-muted small';
      head.textContent = label[key] + ' (' + buckets[key].length + ')';
      list.appendChild(head);
      buckets[key].forEach(cgId => {
        const cg = data.cgMeta[cgId];
        if (!cg) return;
        const li = document.createElement('li');
        li.className = 'ms-2';
        li.innerHTML = '<span class="ov-pill ov-pill-' + key + '"></span>' +
          '<span class="cg-color-dot" style="background:' + (cg.color || '#888') + '"></span> ' +
          (cg.name || '?');
        list.appendChild(li);
      });
    });

    empty.style.display = 'none';
    content.style.display = '';
  }

  grid.addEventListener('click', e => {
    const td = e.target.closest('.ov-cell');
    if (td) selectCell(td);
  });

  closeBtn.addEventListener('click', () => {
    grid.querySelectorAll('.ov-cell.ov-cell-selected').forEach(c => c.classList.remove('ov-cell-selected'));
    document.getElementById('ov-drill-when').textContent = 'Click a cell';
    closeBtn.style.display = 'none';
    empty.style.display = '';
    content.style.display = 'none';
  });

  // "Select all" / "Clear" wire-up on the filter dropdown.
  const allBtn  = document.getElementById('ov-all');
  const noneBtn = document.getElementById('ov-none');
  if (allBtn) {
    allBtn.addEventListener('click', () => {
      document.querySelectorAll('.ov-cg-check').forEach(c => c.checked = true);
    });
  }
  if (noneBtn) {
    noneBtn.addEventListener('click', () => {
      document.querySelectorAll('.ov-cg-check').forEach(c => c.checked = false);
    });
  }

  // Convert the cgs[]=X&cgs[]=Y form submission into a comma-joined ?cgs=...
  // so the URL stays clean and shareable.
  const form = document.getElementById('ov-form');
  if (form) {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const ids = [];
      document.querySelectorAll('.ov-cg-check:checked').forEach(c => ids.push(c.value));
      const url = new URL(window.location.href);
      url.searchParams.set('week', form.querySelector('input[name=week]').value);
      url.searchParams.set('cgs',  ids.join(','));
      window.location.href = url.toString();
    });
  }
})();
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
