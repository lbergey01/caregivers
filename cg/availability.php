<?php
// Caregiver availability editor.
//
//   /cg/availability.php                 -- edit your own (must be a linked caregiver)
//   /cg/availability.php?caregiver_id=N  -- edit caregiver N (admin/manager only)
//
// Renders a 7-day x 48-slot (30-min) grid plus a date-exception list.
// Save posts a JSON-encoded list of non-unknown cells; server coalesces
// contiguous same-status cells into rows in cg_caregiver_availability.

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }

global $db;

// --- resolve target caregiver ----------------------------------------------
$requested_id = isset($_GET['caregiver_id']) ? (int)$_GET['caregiver_id']
              : (isset($_POST['caregiver_id']) ? (int)$_POST['caregiver_id'] : 0);
$target_cg = null;
if ($requested_id > 0) {
    $target_cg = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$requested_id])->first();
} else {
    $target_cg = cg_currentCaregiver();
}

if (!$target_cg) {
    if ($requested_id > 0) die('Caregiver not found.');
    die('Your login is not linked to a caregiver record. Ask an admin to link it, or pass ?caregiver_id=N.');
}

if (!cg_canEditAvailability($target_cg->id)) {
    die('You do not have permission to edit this caregiver\'s availability.');
}

$is_self     = (function() use ($target_cg) {
    $me = cg_currentCaregiver();
    return $me && (int)$me->id === (int)$target_cg->id;
})();
$is_manager  = cg_isManager();

$msg = $err = '';

// --- POST handlers ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_weekly') {
            $json = $_POST['availability_json'] ?? '[]';
            $cells = json_decode($json, true);
            if (!is_array($cells)) throw new Exception('Bad payload.');

            // Each cell: {d:0..6, s:0..47, st:'preferred|available|unavailable'}
            $clean = [];
            foreach ($cells as $c) {
                $d = isset($c['d']) ? (int)$c['d'] : -1;
                $s = isset($c['s']) ? (int)$c['s'] : -1;
                $st = $c['st'] ?? '';
                if ($d < 0 || $d > 6)   throw new Exception('Bad day index.');
                if ($s < 0 || $s > 47)  throw new Exception('Bad slot index.');
                if (!in_array($st, ['preferred','available','unavailable'], true)) {
                    throw new Exception('Bad status.');
                }
                $clean[] = [$d, $s, $st];
            }

            // Coalesce contiguous same-status slots within each day into ranges.
            usort($clean, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $intervals = [];
            $cur = null;
            foreach ($clean as [$d, $s, $st]) {
                if ($cur && $cur['d'] === $d && $cur['st'] === $st && $cur['end'] === $s) {
                    $cur['end'] = $s + 1;
                } else {
                    if ($cur) $intervals[] = $cur;
                    $cur = ['d' => $d, 'start' => $s, 'end' => $s + 1, 'st' => $st];
                }
            }
            if ($cur) $intervals[] = $cur;

            $rows = [];
            foreach ($intervals as $iv) {
                $rows[] = [
                    'day_of_week' => $iv['d'],
                    'start_time'  => sprintf('%02d:%02d', intdiv($iv['start'] * 30, 60), ($iv['start'] * 30) % 60),
                    'end_time'    => sprintf('%02d:%02d', intdiv($iv['end']   * 30, 60), ($iv['end']   * 30) % 60),
                    'status'      => $iv['st'],
                ];
            }

            cg_setCaregiverAvailability($target_cg->id, $rows);
            $msg = 'Weekly availability saved.';
        } elseif ($action === 'add_exception') {
            cg_addAvailabilityException(
                $target_cg->id,
                trim($_POST['exc_date']   ?? ''),
                trim($_POST['exc_start']  ?? ''),
                trim($_POST['exc_end']    ?? ''),
                trim($_POST['exc_status'] ?? ''),
                trim($_POST['exc_reason'] ?? '')
            );
            $msg = 'Exception added.';
        } elseif ($action === 'delete_exception' && !empty($_POST['exc_id'])) {
            $exc = cg_getAvailabilityException((int)$_POST['exc_id']);
            if ($exc && (int)$exc->caregiver_id === (int)$target_cg->id) {
                cg_deleteAvailabilityException((int)$_POST['exc_id']);
                $msg = 'Exception removed.';
            } else {
                $err = 'Exception not found.';
            }
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// --- load current state for render -----------------------------------------
$weekly = cg_getCaregiverAvailability($target_cg->id);
$today_iso = date('Y-m-d');
$exceptions = cg_getAvailabilityExceptions($target_cg->id, $today_iso);

// Inflate the weekly ranges into per-cell status (slot index 0..47 per day).
// Anything not covered stays 'unknown'.
$cells = array_fill(0, 7, array_fill(0, 48, 'unknown'));
foreach ($weekly as $w) {
    $dow = (int)$w->day_of_week;
    $start_slot = (int)((strtotime('1970-01-01 ' . $w->start_time) - strtotime('1970-01-01 00:00:00')) / (30 * 60));
    // end_time of '24:00:00' arrives from MySQL as '24:00:00' literal? Actually MySQL
    // stores TIME with range up to 838:59:59 but '24:00:00' might come back as '24:00:00'.
    // Compute via H*2 + (M>=30?1:0). Safer than strtotime which mishandles 24:00.
    if (preg_match('/^(\d{1,2}):(\d{2})/', $w->end_time, $m)) {
        $end_slot = ((int)$m[1]) * 2 + ((int)$m[2] >= 30 ? 1 : 0);
        if ((int)$m[2] !== 0 && (int)$m[2] !== 30) {
            // shouldn't happen given we only write half-hour boundaries
            $end_slot = (int)ceil(((int)$m[1] * 60 + (int)$m[2]) / 30);
        }
    } else {
        $end_slot = 48;
    }
    $end_slot = max($start_slot + 1, min(48, $end_slot));
    for ($s = $start_slot; $s < $end_slot; $s++) {
        $cells[$dow][$s] = $w->status;
    }
}

$days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1 class="mb-1">Availability</h1>
  <p class="mb-2">
    <span class="cg-color-dot" style="background: <?= htmlspecialchars($target_cg->color) ?>"></span>
    <strong><?= htmlspecialchars($target_cg->name) ?></strong>
    <?php if (!$is_self): ?>
      <span class="badge bg-secondary ms-1">editing as admin</span>
    <?php endif; ?>
  </p>
  <p class="text-muted small">
    <a href="index.php">&larr; Calendar</a>
    <?php if ($is_manager): ?>
      &middot; <a href="admin_caregivers.php">Caregivers</a>
    <?php endif; ?>
  </p>

  <?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span>Weekly pattern</span>
      <div class="btn-group btn-group-sm av-paint-toolbar" role="group" aria-label="Paint status">
        <button type="button" class="btn btn-outline-success av-paint active" data-paint="available">Available</button>
        <button type="button" class="btn btn-outline-primary av-paint" data-paint="preferred">Preferred</button>
        <button type="button" class="btn btn-outline-danger  av-paint" data-paint="unavailable">Unavailable</button>
        <button type="button" class="btn btn-outline-secondary av-paint" data-paint="unknown">Clear</button>
      </div>
    </div>
    <div class="card-body p-2">
      <p class="text-muted small mb-2">
        Click a cell — or drag across multiple — to paint with the selected status.
        Blank (gray) means <em>unknown</em>; the heatmap will treat it as "no answer yet."
      </p>

      <form method="post" id="av-weekly-form">
        <input type="hidden" name="action" value="save_weekly">
        <input type="hidden" name="caregiver_id" value="<?= (int)$target_cg->id ?>">
        <input type="hidden" name="availability_json" id="av-payload" value="">

        <div class="av-grid-wrap">
          <table class="av-grid" aria-label="Weekly availability grid">
            <thead>
              <tr>
                <th class="av-time-col"></th>
                <?php foreach ($days as $di => $dn): ?>
                  <th class="av-day-head">
                    <div><?= $dn ?></div>
                    <div class="av-day-tools">
                      <button type="button" class="av-day-btn"   data-fill-day="<?= $di ?>" title="Paint whole day with selected">Fill</button>
                      <button type="button" class="av-day-btn"   data-clear-day="<?= $di ?>" title="Clear whole day">Clr</button>
                    </div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php for ($slot = 0; $slot < 48; $slot++):
                  $h = intdiv($slot, 2);
                  $m = ($slot % 2) * 30;
                  $label_h = $h === 0 ? '12a' : ($h < 12 ? $h.'a' : ($h === 12 ? '12p' : ($h - 12).'p'));
                  $show_label = ($slot % 2 === 0); // hour rows
              ?>
                <tr class="<?= $slot % 2 === 0 ? 'av-row-hour' : 'av-row-half' ?>">
                  <th class="av-time-col"><?= $show_label ? htmlspecialchars($label_h) : '' ?></th>
                  <?php for ($d = 0; $d < 7; $d++):
                      $st = $cells[$d][$slot];
                  ?>
                    <td class="av-cell av-cell-<?= $st ?>"
                        data-day="<?= $d ?>" data-slot="<?= $slot ?>" data-status="<?= $st ?>"></td>
                  <?php endfor; ?>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <small class="text-muted">
            <span class="av-key av-cell-available"></span> Available
            <span class="av-key av-cell-preferred ms-2"></span> Preferred
            <span class="av-key av-cell-unavailable ms-2"></span> Unavailable
            <span class="av-key av-cell-unknown ms-2 av-key-border"></span> Unknown
          </small>
          <button class="btn btn-primary">Save weekly pattern</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Date exceptions <small class="text-muted ms-2">(vacations, one-off pickups, blackouts)</small></div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="action" value="add_exception">
        <input type="hidden" name="caregiver_id" value="<?= (int)$target_cg->id ?>">
        <div class="col-md-2 col-12">
          <label class="form-label small mb-0">Date</label>
          <input type="date" name="exc_date" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2 col-6">
          <label class="form-label small mb-0">Start</label>
          <input type="time" name="exc_start" class="form-control form-control-sm" value="00:00" required>
        </div>
        <div class="col-md-2 col-6">
          <label class="form-label small mb-0">End</label>
          <input type="time" name="exc_end" class="form-control form-control-sm" value="23:30" required>
        </div>
        <div class="col-md-2 col-6">
          <label class="form-label small mb-0">Status</label>
          <select name="exc_status" class="form-select form-select-sm" required>
            <option value="unavailable">Unavailable</option>
            <option value="available">Available</option>
            <option value="preferred">Preferred</option>
          </select>
        </div>
        <div class="col-md-3 col-12">
          <label class="form-label small mb-0">Reason (optional)</label>
          <input type="text" name="exc_reason" class="form-control form-control-sm" placeholder="Vacation, doctor's appt…">
        </div>
        <div class="col-md-1 col-12">
          <button class="btn btn-sm btn-primary w-100">Add</button>
        </div>
      </form>

      <?php if (!$exceptions): ?>
        <p class="text-muted small mb-0">No upcoming exceptions.</p>
      <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr>
            <th>Date</th><th>Time</th><th>Status</th><th>Reason</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($exceptions as $e):
              // Strip seconds in the display.
              $st = preg_replace('/:00$/', '', $e->start_time);
              $en = preg_replace('/:00$/', '', $e->end_time);
          ?>
            <tr>
              <td><?= htmlspecialchars($e->specific_date) ?></td>
              <td><?= htmlspecialchars($st) ?>–<?= htmlspecialchars($en) ?></td>
              <td><span class="av-pill av-cell-<?= htmlspecialchars($e->status) ?>"><?= htmlspecialchars(ucfirst($e->status)) ?></span></td>
              <td><?= htmlspecialchars((string)$e->reason) ?></td>
              <td class="text-end">
                <form method="post" onsubmit="return confirm('Remove this exception?');" class="d-inline">
                  <input type="hidden" name="action" value="delete_exception">
                  <input type="hidden" name="caregiver_id" value="<?= (int)$target_cg->id ?>">
                  <input type="hidden" name="exc_id" value="<?= (int)$e->id ?>">
                  <button class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</main>

<style>
  .cg-color-dot {
    display: inline-block; width: 12px; height: 12px;
    border-radius: 50%; border: 1px solid rgba(0,0,0,0.15);
    margin-right: 6px; vertical-align: middle;
  }
  .av-grid-wrap { overflow-x: auto; }
  .av-grid {
    border-collapse: collapse;
    width: 100%;
    user-select: none;
    -webkit-user-select: none;
    table-layout: fixed;
  }
  .av-grid th, .av-grid td { border: 1px solid #e5e5e5; padding: 0; }
  .av-time-col { width: 44px; min-width: 44px; font-size: 11px; color: #888; text-align: right; padding: 0 4px; font-weight: normal; background: #fafafa; }
  .av-day-head { text-align: center; font-size: 13px; padding: 4px 2px; background: #f5f5f5; }
  .av-day-tools { font-size: 10px; margin-top: 2px; display: flex; gap: 2px; justify-content: center; }
  .av-day-btn { font-size: 10px; padding: 0 4px; border: 1px solid #ccc; background: #fff; border-radius: 3px; cursor: pointer; }
  .av-day-btn:hover { background: #f0f0f0; }
  .av-cell { height: 18px; cursor: pointer; touch-action: none; position: relative; }
  .av-row-hour .av-cell { border-top: 1px solid #cfcfcf; }
  .av-cell-unknown     { background: #f8f9fa; }
  .av-cell-available   { background: rgba(46, 204, 113, 0.55); }
  .av-cell-preferred   { background: rgba(52, 152, 219, 0.65); }
  .av-cell-unavailable { background: rgba(231, 76, 60, 0.55); }
  /* Selection preview while dragging — matches FullCalendar's range-select feel.
     Inset shadow shows which cells will be filled on release. */
  .av-cell.av-preview { box-shadow: inset 0 0 0 2px #2c3e50; z-index: 1; }
  .av-paint.active { background-color: rgba(0,0,0,0.06); }
  .av-key {
    display: inline-block; width: 14px; height: 14px; vertical-align: middle;
    border-radius: 2px; margin-right: 2px;
  }
  .av-key-border { border: 1px solid #ccc; }
  .av-pill {
    display: inline-block; padding: 2px 6px; border-radius: 3px;
    font-size: 12px; color: #fff;
  }
  .av-pill.av-cell-unknown { color: #555; border: 1px solid #ccc; }
  @media (max-width: 600px) {
    .av-day-tools { display: none; }   /* not enough room on phones */
    .av-cell { height: 22px; }          /* easier touch targets */
  }
</style>

<script>
(function() {
  const grid     = document.querySelector('.av-grid');
  const payload  = document.getElementById('av-payload');
  const form     = document.getElementById('av-weekly-form');
  const toolbar  = document.querySelector('.av-paint-toolbar');
  if (!grid || !form) return;

  let paintStatus = 'available';
  let anchor = null;   // {d, s} where pointerdown happened
  let hover  = null;   // {d, s} of the cell currently under the pointer

  function setCell(cell, status) {
    if (!cell) return;
    cell.dataset.status = status;
    cell.classList.remove('av-cell-available','av-cell-preferred','av-cell-unavailable','av-cell-unknown');
    cell.classList.add('av-cell-' + status);
  }

  function clearPreview() {
    grid.querySelectorAll('.av-cell.av-preview').forEach(c => c.classList.remove('av-preview'));
  }

  function refreshPreview() {
    clearPreview();
    if (!anchor || !hover) return;
    const d1 = Math.min(anchor.d, hover.d), d2 = Math.max(anchor.d, hover.d);
    const s1 = Math.min(anchor.s, hover.s), s2 = Math.max(anchor.s, hover.s);
    for (let d = d1; d <= d2; d++) {
      for (let s = s1; s <= s2; s++) {
        const cell = grid.querySelector('.av-cell[data-day="' + d + '"][data-slot="' + s + '"]');
        if (cell) cell.classList.add('av-preview');
      }
    }
  }

  function commit() {
    if (!anchor) return;
    grid.querySelectorAll('.av-cell.av-preview').forEach(c => {
      setCell(c, paintStatus);
      c.classList.remove('av-preview');
    });
    anchor = hover = null;
  }

  toolbar.addEventListener('click', e => {
    const b = e.target.closest('.av-paint');
    if (!b) return;
    toolbar.querySelectorAll('.av-paint').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    paintStatus = b.dataset.paint;
  });

  // Calendar-style range select: drag highlights a rectangle, release fills it.
  // Pointer events unify mouse + touch. We rely on document.elementFromPoint
  // because (a) touch implicitly captures the pointer to the first cell, so
  // pointerover never fires for neighbors, and (b) we need pointermove to
  // keep working even when the pointer leaves the grid.
  function cellAt(clientX, clientY) {
    const el = document.elementFromPoint(clientX, clientY);
    return el ? el.closest('.av-cell') : null;
  }

  grid.addEventListener('pointerdown', e => {
    const cell = e.target.closest('.av-cell');
    if (!cell) return;
    e.preventDefault();
    // Release implicit capture so subsequent pointermove events report the
    // element actually under the pointer, not the originating cell.
    if (e.target.releasePointerCapture) {
      try { e.target.releasePointerCapture(e.pointerId); } catch (_) {}
    }
    anchor = { d: +cell.dataset.day, s: +cell.dataset.slot };
    hover  = { ...anchor };
    refreshPreview();
  });
  document.addEventListener('pointermove', e => {
    if (!anchor) return;
    const cell = cellAt(e.clientX, e.clientY);
    if (!cell) return;
    const d = +cell.dataset.day, s = +cell.dataset.slot;
    if (hover && hover.d === d && hover.s === s) return; // unchanged
    hover = { d, s };
    refreshPreview();
  });
  document.addEventListener('pointerup',     commit);
  document.addEventListener('pointercancel', () => { clearPreview(); anchor = hover = null; });

  // Per-day fill/clear buttons.
  grid.addEventListener('click', e => {
    const fillBtn  = e.target.closest('[data-fill-day]');
    const clearBtn = e.target.closest('[data-clear-day]');
    if (fillBtn) {
      const d = fillBtn.dataset.fillDay;
      grid.querySelectorAll('.av-cell[data-day="' + d + '"]').forEach(c => setCell(c, paintStatus));
    } else if (clearBtn) {
      const d = clearBtn.dataset.clearDay;
      grid.querySelectorAll('.av-cell[data-day="' + d + '"]').forEach(c => setCell(c, 'unknown'));
    }
  });

  // Serialize on submit. Only non-unknown cells go to the server.
  form.addEventListener('submit', () => {
    const out = [];
    grid.querySelectorAll('.av-cell').forEach(c => {
      const st = c.dataset.status;
      if (st && st !== 'unknown') {
        out.push({ d: +c.dataset.day, s: +c.dataset.slot, st });
      }
    });
    payload.value = JSON.stringify(out);
  });
})();
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
