<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }

$is_admin    = cg_isAdmin();
$is_manager  = cg_isManager(); // admin OR manager
$is_cg       = cg_isCaregiver();
$is_visitor  = cg_isVisitor();
$me_cg       = cg_currentCaregiver();
// Scheduling dropdown: caregivers only (plus the user's own row if they're a
// visitor) so a large visitor roster doesn't clutter the "+ Add Shift" picker.
$caregivers  = cg_caregiversForScheduling();
$clients     = cg_clientsAll(true);
$default_cid = cg_defaultClientId();

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<style>
  /* Month-cell coverage tint */
  .fc .day-cover-full     { background: rgba(46, 204, 113, 0.18); }
  .fc .day-cover-partial  { background: rgba(241, 196, 15, 0.18); }
  .fc .day-cover-empty    { background: rgba(231, 76, 60, 0.20); }

  /* Gap bg-event label */
  .fc-bg-event.gap-bg::after { content: 'GAP'; font-size: 10px; color: #7f6000; font-weight: 700; }

  /* Compressed list view */
  .cg-compressed { font-size: 13px; padding: 4px; }
  .cg-compressed .row-shift,
  .cg-compressed .row-gap { padding: 4px 6px; margin: 2px 0; border-radius: 4px; }
  .cg-compressed .row-gap   { background: #f6e58d; color: #7f6000; font-weight: 600; }
  .cg-compressed .row-shift { color: #fff; }

  .day-toggle-btn {
    font-size: 11px; padding: 2px 6px; margin-left: 6px;
    border: 1px solid #ccc; background: #fff; border-radius: 3px; cursor: pointer;
  }

  /* Space out adjacent buttons in the FullCalendar header (prev/next group
     and the month/week/day view-switch group). Default v6 styling glues them
     together as a connected button group, which is hard to hit on touch. */
  .fc .fc-button-group { gap: 6px; }
  .fc .fc-button-group > .fc-button {
    border-radius: var(--bs-border-radius, 0.375rem) !important;
    border-left-width: 1px !important;
  }

  /* Calendar with dedicated swipe lanes on either side. Lanes are real DOM
     elements (not just CSS hints) so they accept taps as well as swipes. */
  .cg-cal-wrap { display: flex; align-items: stretch; gap: 0; }
  .cg-cal-center { flex: 1 1 auto; min-width: 0; }
  .cg-swipe-lane {
    display: none;             /* hidden by default; toggled per view below */
    flex: 0 0 56px;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.08);
    color: #666;
    cursor: pointer;
    border-radius: 6px;
    margin: 0 4px;
    touch-action: pan-y;       /* lets vertical scroll through; horizontal stays for the tap */
  }
  .cg-swipe-lane:hover  { background: rgba(0,0,0,0.06); color: #333; }
  .cg-swipe-lane:active { background: rgba(0,0,0,0.10); }

  /* Lanes visible in day view; in week we still allow page-level swipe but
     don't need the lanes since each day already has its own column header.
     Toggled via .cg-day-view on the wrapper from datesSet. */
  .cg-cal-wrap.cg-day-view .cg-swipe-lane { display: flex; }

  @media (max-width: 768px) {
    .cg-cal-wrap.cg-day-view .cg-swipe-lane { flex-basis: 44px; }
  }

  /* Mobile tweaks */
  @media (max-width: 768px) {
    .fc .fc-toolbar.fc-header-toolbar { flex-wrap: wrap; gap: 6px; }
    .fc .fc-toolbar-title { font-size: 1.1em; }
    .fc-button { padding: 6px 10px !important; }
    /* Bigger hit target on time grid cells so single-tap works reliably */
    .fc-timegrid-slot { height: 2.2em !important; }
    .day-toggle-btn { font-size: 13px; padding: 6px 10px; }
  }
</style>

<main class="container-fluid my-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Care Schedule</h1>
    <div>
      <?php if (count($clients) > 1): ?>
        <select id="clientPicker" class="form-select form-select-sm d-inline-block w-auto">
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c->id ?>" <?= (int)$c->id === $default_cid ? 'selected' : '' ?>>
              <?= htmlspecialchars($c->name) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <?php if ($is_manager || $me_cg): ?>
        <button id="btnAddShift" class="btn btn-sm btn-primary">+ Add Shift</button>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="history.php">History</a>
      <?php if ($me_cg && !$is_manager && !$is_visitor): ?>
        <a class="btn btn-sm btn-outline-secondary" href="availability.php">My Availability</a>
      <?php endif; ?>
      <?php if ($is_manager): ?>
        <a class="btn btn-sm btn-outline-secondary" href="admin.php">Admin</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$is_manager && !$me_cg): ?>
    <div class="alert alert-warning">Your account isn't linked to a caregiver record yet, so you can view but not schedule. Ask an admin to link you on the Caregivers page.</div>
  <?php endif; ?>

  <div id="calWrap" class="cg-cal-wrap">
    <button type="button" class="cg-swipe-lane cg-swipe-prev" data-dir="prev" aria-label="Previous">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
        <path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/>
      </svg>
    </button>
    <div id="calendar" class="cg-cal-center"></div>
    <button type="button" class="cg-swipe-lane cg-swipe-next" data-dir="next" aria-label="Next">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
        <path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/>
      </svg>
    </button>
  </div>
</main>

<!-- Choice prompt shown when tapping an existing shift. Lets the user pick
     between opening the existing shift (edit or view-only, depending on perms)
     and creating an overlapping shift in the same time slot. -->
<div class="modal fade" id="shiftChoiceModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">What would you like to do?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3" id="choiceContext"></p>
        <div class="d-grid gap-2">
          <button type="button" id="btnChoiceExisting" class="btn btn-primary">Edit this shift</button>
          <button type="button" id="btnChoiceOverlap"  class="btn btn-outline-primary">Add an overlapping shift</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add / Edit Shift modal -->
<div class="modal fade" id="shiftModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="shiftForm">
        <div class="modal-header">
          <h5 class="modal-title" id="shiftModalTitle">Add Shift</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="f_id">
          <div class="mb-2">
            <label class="form-label">Caregiver</label>
            <select name="caregiver_id" id="f_caregiver" class="form-select" required>
              <?php foreach ($caregivers as $c): ?>
                <option value="<?= $c->id ?>" data-color="<?= htmlspecialchars($c->color) ?>"
                  <?= ($me_cg && (int)$c->id === (int)$me_cg->id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c->name) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (!$is_manager && $me_cg): ?>
              <small class="text-muted">You can only schedule yourself.</small>
            <?php endif; ?>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Start</label>
              <input type="datetime-local" name="start_dt" id="f_start" class="form-control" step="60" required>
            </div>
            <div class="col-6">
              <label class="form-label">End</label>
              <input type="datetime-local" name="end_dt" id="f_end" class="form-control" step="60" required>
            </div>
          </div>

          <div id="shiftFormErr" class="alert alert-danger d-none mt-2"></div>

          <!-- Shift action buttons sit above the notes area so the user doesn't
               have to scroll past a long shift-log to save/cancel/delete. -->
          <div class="d-flex justify-content-between align-items-center mt-3">
            <button type="button" class="btn btn-danger d-none" id="btnDelete">Delete</button>
            <div class="ms-auto">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </div>

          <div id="notesSection" class="mt-3 d-none">
            <label class="form-label mb-1">Shift log</label>
            <div id="notesList" class="mb-3"></div>

            <div id="noteComposer" class="border rounded p-2 bg-light">
              <textarea id="f_note_body" class="form-control" rows="3"
                        placeholder="Add a note — meds given, mood, incidents…"></textarea>

              <!-- Hidden file inputs; visible buttons trigger them -->
              <input type="file" id="f_note_photo" class="d-none"
                     accept="image/*" capture="environment" multiple>
              <input type="file" id="f_note_file"  class="d-none"
                     accept="image/*,application/pdf,text/plain" multiple>

              <div id="notePending" class="d-flex flex-wrap gap-2 mt-2"></div>

              <div class="d-flex gap-2 mt-2">
                <button type="button" id="btnNotePhoto"   class="btn btn-outline-primary btn-sm">📷 Photo</button>
                <button type="button" id="btnNoteAttach"  class="btn btn-outline-secondary btn-sm">📎 Attachment</button>
                <div class="ms-auto d-flex gap-2">
                  <button type="button" id="btnNotePost"   class="btn btn-outline-primary btn-sm">Save Note</button>
                  <button type="button" id="btnNoteNotify" class="btn btn-primary btn-sm">Save &amp; Notify</button>
                </div>
              </div>
              <div id="noteErr" class="text-danger small mt-1"></div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  // IS_ADMIN = "can act on anyone's shift" — true for admins AND managers.
  // The name is kept for historical reasons; the meaning is the broader permission.
  const IS_ADMIN   = <?= $is_manager ? 'true' : 'false' ?>;
  const IS_VISITOR = <?= $is_visitor ? 'true' : 'false' ?>;
  const ME_CG_ID   = <?= $me_cg ? (int)$me_cg->id : 'null' ?>;
  const CLIENT_ID  = <?= (int)$default_cid ?>;
  let currentClient = CLIENT_ID;

  const isNarrow = () => window.matchMedia('(max-width: 768px)').matches;

  const cal = new FullCalendar.Calendar(document.getElementById('calendar'), {
    initialView: isNarrow() ? 'timeGridDay' : 'timeGridWeek',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    // Tap a day name/number header in week or month → drill into that day
    navLinks: true,
    navLinkDayClick: 'timeGridDay',
    height: 'auto',
    nowIndicator: true,
    slotMinTime: '00:00:00',
    slotMaxTime: '24:00:00',
    allDaySlot: false,
    slotDuration: isNarrow() ? '01:00:00' : '00:30:00',
    selectable: (IS_ADMIN || ME_CG_ID !== null),
    selectMirror: true,
    selectLongPressDelay: 250,
    longPressDelay: 250,
    // Drag/resize is opt-in per-event: the API stamps each shift with `editable: can_edit`,
    // so caregivers can only drag their own shifts; admins can drag any.
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: true,
    events: function(info, success, fail) {
      fetch(`api/shifts.php?client_id=${currentClient}&from=${info.startStr}&to=${info.endStr}`)
        .then(r => r.json())
        .then(success)
        .catch(fail);
    },
    eventDidMount: function(info) {
      if (info.event.extendedProps.kind === 'gap') {
        info.el.classList.add('gap-bg');
      }
    },
    datesSet: function(info) {
      if (info.view.type === 'dayGridMonth') {
        paintMonthCells(info.startStr, info.endStr);
      }
      if (info.view.type === 'timeGridWeek') {
        installDayToggles();
      }
      // Show the dedicated tap-to-navigate lanes only in day view.
      document.getElementById('calWrap')
        .classList.toggle('cg-day-view', info.view.type === 'timeGridDay');
    },
    // Drag-to-select (desktop) creates an exact-range shift.
    select: function(info) {
      openShiftModal({ start: info.start, end: info.end });
      cal.unselect();
    },
    // Single tap/click:
    //  - In month view: drill into that day (day view)
    //  - In week/day view: open Add Shift modal pre-filled with that hour + 1hr default
    dateClick: function(info) {
      if (info.view.type === 'dayGridMonth') {
        cal.changeView('timeGridDay', info.date);
        return;
      }
      if (!IS_ADMIN && ME_CG_ID === null) return;
      const start = info.date;
      const end = new Date(start.getTime() + 60 * 60 * 1000);
      openShiftModal({ start, end });
    },
    eventClick: function(info) {
      if (info.event.extendedProps.kind !== 'shift') return;
      const ev = info.event;
      const canEdit = ev.extendedProps.can_edit;

      // Admin: always show the choice modal so they can pick edit vs. add overlap.
      // Non-admin tapping their own shift: edit directly (the common case).
      // Visitor tapping someone else's shift: overlap-only (notes are hidden
      // from visitors, so "View" would just show an empty shift).
      // Regular caregiver tapping someone else's: view vs. add overlap.
      if (IS_ADMIN) {
        openShiftChoice(ev, { canEdit: true });
      } else if (canEdit) {
        openExistingShift(ev);
      } else if (IS_VISITOR) {
        openShiftChoice(ev, { canEdit: false, hideExisting: true });
      } else if (ME_CG_ID !== null) {
        openShiftChoice(ev, { canEdit: false });
      } else {
        // No add or edit rights at all — fall back to read-only view.
        openExistingShift(ev);
      }
    },
    eventDrop:   function(info) { persistEventChange(info, 'Move'); },
    eventResize: function(info) { persistEventChange(info, 'Resize'); }
  });
  cal.render();

  // Swipe left/right anywhere on the calendar to navigate prev/next in the
  // current view. Listens on the wrapper (which includes the side lanes AND
  // the calendar body — including over event blocks). FullCalendar's own
  // drag/resize uses a long-press threshold (250 ms), so quick swipes don't
  // conflict with it.
  (function attachSwipe() {
    const wrap = document.getElementById('calWrap');
    if (!wrap) return;
    let sx = 0, sy = 0, st = 0, tracking = false;
    const MIN_DX   = 40;
    const MAX_DY   = 70;
    const MAX_TIME = 800;

    wrap.addEventListener('touchstart', e => {
      if (e.touches.length !== 1) return;
      const t = e.touches[0];
      sx = t.clientX; sy = t.clientY; st = Date.now();
      tracking = true;
    }, { passive: true });

    wrap.addEventListener('touchend', e => {
      if (!tracking) return;
      tracking = false;
      const t = e.changedTouches[0];
      const dx = t.clientX - sx;
      const dy = t.clientY - sy;
      if (Date.now() - st > MAX_TIME) return;
      if (Math.abs(dx) < MIN_DX) return;
      if (Math.abs(dy) > MAX_DY) return;
      if (dx < 0) cal.next(); else cal.prev();
    }, { passive: true });

    // Tap on a side lane is the primary, discoverable nav affordance.
    wrap.querySelectorAll('.cg-swipe-lane').forEach(lane => {
      lane.addEventListener('click', () => {
        if (lane.dataset.dir === 'next') cal.next(); else cal.prev();
      });
    });
  })();

  // Deep-link from history: index.php?goto=YYYY-MM-DD&shift=N
  (function deepLink() {
    const p = new URLSearchParams(window.location.search);
    const goto = p.get('goto'), shiftId = p.get('shift');
    if (!goto && !shiftId) return;
    if (goto) cal.gotoDate(goto);
    if (!shiftId) return;
    let opened = false;
    cal.on('eventsSet', () => {
      if (opened) return;
      const ev = cal.getEventById(shiftId);
      if (!ev) return;
      opened = true;
      openShiftModal({
        id:           ev.id,
        start:        ev.start,
        end:          ev.end,
        caregiver_id: ev.extendedProps.caregiver_id,
        can_edit:     ev.extendedProps.can_edit
      });
    });
  })();

  // "+ Add Shift" button — opens modal with sensible defaults.
  document.getElementById('btnAddShift')?.addEventListener('click', function() {
    const v = cal.view;
    let start;
    if (v.type === 'dayGridMonth') {
      start = new Date();
      start.setMinutes(0, 0, 0);
    } else {
      // Snap to next hour in the current view's range
      start = new Date(Math.max(Date.now(), v.currentStart.getTime()));
      start.setMinutes(0, 0, 0);
    }
    const end = new Date(start.getTime() + 60 * 60 * 1000);
    openShiftModal({ start, end });
  });

  // Re-layout if user rotates phone or resizes window across the breakpoint
  let wasNarrow = isNarrow();
  window.addEventListener('resize', () => {
    const n = isNarrow();
    if (n !== wasNarrow) {
      wasNarrow = n;
      cal.setOption('slotDuration', n ? '01:00:00' : '00:30:00');
    }
  });

  document.getElementById('clientPicker')?.addEventListener('change', function() {
    currentClient = parseInt(this.value, 10);
    cal.refetchEvents();
  });

  /* ---------- Modal ---------- */
  const modalEl  = document.getElementById('shiftModal');
  const modal    = new bootstrap.Modal(modalEl);
  const $        = (id) => document.getElementById(id);

  /* ---------- Edit-vs-Overlap choice modal ----------
   * Shown when tapping an existing shift event. Sets a single closure variable
   * so the two action buttons (registered once below) can read the active
   * event without re-binding handlers each call.
   */
  const choiceModalEl = document.getElementById('shiftChoiceModal');
  const choiceModal   = new bootstrap.Modal(choiceModalEl);
  let   _choiceEvent  = null;

  function openExistingShift(ev) {
    openShiftModal({
      id:           ev.id,
      start:        ev.start,
      end:          ev.end,
      caregiver_id: ev.extendedProps.caregiver_id,
      can_edit:     ev.extendedProps.can_edit,
      note_count:   ev.extendedProps.note_count || 0
    });
  }

  // opts = { canEdit: bool, hideExisting?: bool }
  // hideExisting=true (visitor on someone else's shift): only the "Add
  // overlapping" button is shown — no edit/view option since notes are hidden.
  function openShiftChoice(ev, opts) {
    _choiceEvent = ev;
    const cgName = ev.title || 'another caregiver';
    const fmt = d => d.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
    const range = `${fmt(ev.start)} – ${fmt(ev.end)}`;
    $('btnChoiceExisting').classList.toggle('d-none', !!opts.hideExisting);
    $('btnChoiceExisting').textContent = opts.canEdit ? 'Edit this shift' : 'View this shift';
    $('choiceContext').textContent =
      opts.canEdit
        ? `${cgName}: ${range}`
        : `This time slot has ${cgName}'s shift (${range}).`;
    choiceModal.show();
  }

  // Visitors don't get the Save & Notify button — server rejects notify from
  // them, so hiding it is purely UX clarity. Promote Save Note to primary so
  // they still have a clear default action.
  if (IS_VISITOR) {
    $('btnNoteNotify').classList.add('d-none');
    $('btnNotePost').classList.remove('btn-outline-primary');
    $('btnNotePost').classList.add('btn-primary');
  }

  $('btnChoiceExisting').addEventListener('click', () => {
    const ev = _choiceEvent; if (!ev) return;
    choiceModal.hide();
    openExistingShift(ev);
  });
  $('btnChoiceOverlap').addEventListener('click', () => {
    const ev = _choiceEvent; if (!ev) return;
    choiceModal.hide();
    // New shift pre-filled with the existing shift's time range. User adjusts
    // start/end (and caregiver, if admin) to whatever the overlapping activity
    // needs.
    openShiftModal({ start: ev.start, end: ev.end });
  });

  function toLocalInput(d) {
    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }
  function toMySQL(s) { return s.replace('T', ' ') + ':00'; }
  function dateToMySQL(d) {
    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} `
         + `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  // Persist a drag/resize. Confirms first (drag is easy to trigger by accident),
  // and on any server error reverts the visual change.
  function persistEventChange(info, verb) {
    const ev = info.event;
    if (!ev.end) { info.revert(); return; }   // safety: FC should always supply end
    const fmt = d => d.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
    const msg = `${verb} this shift?\n\n`
              + `From: ${fmt(info.oldEvent.start)} – ${fmt(info.oldEvent.end)}\n`
              + `To:   ${fmt(ev.start)} – ${fmt(ev.end)}`;
    if (!confirm(msg)) { info.revert(); return; }
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', ev.id);
    fd.append('client_id', currentClient);
    fd.append('caregiver_id', ev.extendedProps.caregiver_id);
    fd.append('start_dt', dateToMySQL(ev.start));
    fd.append('end_dt',   dateToMySQL(ev.end));
    fetch('api/shifts.php', { method: 'POST', body: fd })
      .then(r => r.json().then(j => ({ok: r.ok, body: j})))
      .then(({ok, body}) => {
        if (!ok) throw new Error(body.error || 'Save failed');
        // Refetch so yellow gap blocks (and month-cell tints) recompute.
        cal.refetchEvents();
        if (cal.view.type === 'dayGridMonth') {
          paintMonthCells(cal.view.currentStart.toISOString(), cal.view.currentEnd.toISOString());
        }
      })
      .catch(err => {
        info.revert();
        alert(err.message);
      });
  }

  function openShiftModal(opts) {
    $('shiftFormErr').classList.add('d-none');
    $('f_id').value = opts.id || '';
    $('f_start').value = toLocalInput(opts.start);
    $('f_end').value   = toLocalInput(opts.end);
    if (opts.caregiver_id) {
      $('f_caregiver').value = opts.caregiver_id;
    } else if (ME_CG_ID && !IS_ADMIN) {
      $('f_caregiver').value = ME_CG_ID;
    }
    $('f_caregiver').disabled = !IS_ADMIN && ME_CG_ID !== null;

    const editing = !!opts.id;
    document.getElementById('shiftModalTitle').textContent = editing ? 'Edit Shift' : 'Add Shift';
    const canEdit = !editing || opts.can_edit;
    // Shifts with notes can't be deleted (the shift log must not be orphaned).
    // Server enforces this too; hiding the button keeps users from a frustrating
    // round-trip when they already see "1 *" on the calendar event.
    const hasNotes = (opts.note_count || 0) > 0;
    $('btnDelete').classList.toggle('d-none', !editing || !canEdit || hasNotes);
    modalEl.querySelector('button[type="submit"]').classList.toggle('d-none', !canEdit);

    // Notes timeline: only shows after the shift exists. Visitors only see
    // notes on their own shifts (server enforces the same; this just hides UI).
    const isOwnShift = opts.caregiver_id && ME_CG_ID && (+opts.caregiver_id === ME_CG_ID);
    const canReadNotes = !IS_VISITOR || isOwnShift;
    setNotesShift(editing ? opts.id : null, canEdit, canReadNotes);

    modal.show();
  }

  /* ---------- Notes timeline + composer ---------- */
  let notesShiftId = null;
  let notesCanWrite = false;
  let pendingFiles = [];   // files queued in the composer for the next "Post note"

  // canRead=false hides the whole section (visitor opening someone else's
  // shift — they can't see those notes). canWrite=false hides only the
  // composer (regular caregiver viewing another's shift — read-only).
  function setNotesShift(shiftId, canWrite, canRead) {
    notesShiftId = shiftId;
    notesCanWrite = !!canWrite;
    pendingFiles = [];
    const sec = document.getElementById('notesSection');
    if (!shiftId || canRead === false) { sec.classList.add('d-none'); return; }
    sec.classList.remove('d-none');
    document.getElementById('f_note_body').value = '';
    document.getElementById('notePending').innerHTML = '';
    document.getElementById('noteErr').textContent = '';
    document.getElementById('noteComposer').style.display = canWrite ? '' : 'none';
    refreshNotesList();
  }

  function refreshNotesList() {
    const list = document.getElementById('notesList');
    list.innerHTML = '<div class="text-muted small">Loading…</div>';
    fetch(`api/notes.php?action=list&shift_id=${notesShiftId}`)
      .then(r => r.json())
      .then(rows => {
        list.innerHTML = '';
        if (!rows.length) {
          list.innerHTML = '<div class="text-muted small">No notes yet.</div>';
          return;
        }
        rows.forEach(n => list.appendChild(renderNote(n)));
      })
      .catch(() => { list.innerHTML = '<div class="text-danger small">Failed to load notes.</div>'; });
  }

  function fmtTs(dt) {
    if (!dt) return '';
    const d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
  }

  function renderNote(n) {
    const wrap = document.createElement('div');
    wrap.className = 'border rounded p-2 mb-2';
    wrap.dataset.noteId = n.id;

    const head = document.createElement('div');
    head.className = 'd-flex justify-content-between align-items-start mb-1';
    head.innerHTML =
      `<div class="small">
         <strong>${escapeHtml(n.author_name)}</strong>
         <span class="text-muted"> · ${escapeHtml(fmtTs(n.created_at))}</span>
         ${n.edited_at ? `<span class="text-muted fst-italic"> (edited ${escapeHtml(fmtTs(n.edited_at))})</span>` : ''}
       </div>`;
    const actions = document.createElement('div');
    if (n.can_edit) {
      const eb = document.createElement('button');
      eb.type = 'button'; eb.className = 'btn btn-link btn-sm p-0 me-2';
      eb.textContent = 'Edit';
      eb.addEventListener('click', () => editNoteInline(wrap, n));
      actions.appendChild(eb);
    }
    if (n.can_delete) {
      const db = document.createElement('button');
      db.type = 'button'; db.className = 'btn btn-link btn-sm p-0 text-danger';
      db.textContent = 'Delete';
      db.addEventListener('click', () => deleteNoteServer(n.id));
      actions.appendChild(db);
    }
    head.appendChild(actions);
    wrap.appendChild(head);

    const body = document.createElement('div');
    body.className = 'note-body';
    body.style.whiteSpace = 'pre-wrap';
    body.textContent = n.body;
    wrap.appendChild(body);

    if (n.attachments && n.attachments.length) {
      const strip = document.createElement('div');
      strip.className = 'd-flex flex-wrap gap-2 mt-2';
      n.attachments.forEach(a => strip.appendChild(renderAttachThumb(a, n.can_edit)));
      wrap.appendChild(strip);
    }
    return wrap;
  }

  function renderAttachThumb(a, canDel) {
    const isImg = /^image\//.test(a.mime);
    const wrap = document.createElement('div');
    wrap.className = 'position-relative';
    wrap.style.width = '88px';
    if (isImg) {
      const img = document.createElement('img');
      img.src = `api/attachment.php?action=get&id=${a.id}`;
      img.alt = a.orig_name; img.title = a.orig_name;
      img.style.cssText = 'width:88px;height:88px;object-fit:cover;border-radius:6px;cursor:pointer;';
      img.addEventListener('click', () => window.open(img.src, '_blank'));
      wrap.appendChild(img);
    } else {
      const link = document.createElement('a');
      link.href = `api/attachment.php?action=get&id=${a.id}`;
      link.target = '_blank';
      link.className = 'd-flex flex-column align-items-center justify-content-center text-decoration-none';
      link.style.cssText = 'width:88px;height:88px;border:1px solid #ccc;border-radius:6px;background:#f8f9fa;padding:4px;';
      link.innerHTML = '<div style="font-size:24px;">📎</div>'
                     + `<div class="small text-truncate" style="max-width:80px;">${escapeHtml(a.orig_name)}</div>`;
      wrap.appendChild(link);
    }
    if (canDel) {
      const x = document.createElement('button');
      x.type = 'button'; x.className = 'btn-close';
      x.setAttribute('aria-label', 'Delete attachment');
      x.style.cssText = 'position:absolute;top:2px;right:2px;background-color:#fff;border-radius:50%;opacity:0.85;';
      x.addEventListener('click', () => deleteAttach(a.id));
      wrap.appendChild(x);
    }
    return wrap;
  }

  function deleteAttach(id) {
    if (!confirm('Delete this attachment?')) return;
    const fd = new FormData();
    fd.append('action', 'delete'); fd.append('id', id);
    fetch('api/attachment.php', { method: 'POST', body: fd })
      .then(r => r.json().then(j => ({ok: r.ok, body: j})))
      .then(({ok, body}) => {
        if (!ok) throw new Error(body.error || 'Delete failed');
        refreshNotesList();
      })
      .catch(err => alert(err.message));
  }

  function editNoteInline(wrapEl, note) {
    const body = wrapEl.querySelector('.note-body');
    const original = note.body;
    const ta = document.createElement('textarea');
    ta.className = 'form-control mb-1';
    ta.rows = 4;
    ta.value = original;
    body.replaceWith(ta);
    const btnRow = document.createElement('div');
    btnRow.className = 'd-flex gap-2';
    btnRow.innerHTML =
      `<button type="button" class="btn btn-sm btn-primary">Save</button>
       <button type="button" class="btn btn-sm btn-secondary">Cancel</button>`;
    wrapEl.appendChild(btnRow);
    btnRow.querySelector('.btn-primary').addEventListener('click', () => {
      const fd = new FormData();
      fd.append('action', 'update'); fd.append('id', note.id); fd.append('body', ta.value);
      fetch('api/notes.php', { method: 'POST', body: fd })
        .then(r => r.json().then(j => ({ok: r.ok, body: j})))
        .then(({ok, body}) => {
          if (!ok) throw new Error(body.error || 'Save failed');
          refreshNotesList();
        })
        .catch(err => alert(err.message));
    });
    btnRow.querySelector('.btn-secondary').addEventListener('click', refreshNotesList);
  }

  function deleteNoteServer(id) {
    if (!confirm('Delete this note and all of its attachments?')) return;
    const fd = new FormData();
    fd.append('action', 'delete'); fd.append('id', id);
    fetch('api/notes.php', { method: 'POST', body: fd })
      .then(r => r.json().then(j => ({ok: r.ok, body: j})))
      .then(({ok, body}) => {
        if (!ok) throw new Error(body.error || 'Delete failed');
        refreshNotesList();
      })
      .catch(err => alert(err.message));
  }

  /* ---- Composer: two buttons, pending file strip, post ---- */

  document.getElementById('btnNotePhoto').addEventListener('click', () => {
    document.getElementById('f_note_photo').click();
  });
  document.getElementById('btnNoteAttach').addEventListener('click', () => {
    document.getElementById('f_note_file').click();
  });

  // Photo path: image-only, blur check
  document.getElementById('f_note_photo').addEventListener('change', async function(e) {
    const files = Array.from(e.target.files || []);
    e.target.value = '';
    for (const f of files) {
      if (!/^image\//.test(f.type)) { addPendingFile(f); continue; }
      const { variance, blurry } = await detectBlurClient(f);
      console.log(`[blur] ${f.name}: variance=${variance.toFixed(1)} threshold=${BLUR_THRESHOLD} ${blurry ? 'BLURRY' : 'ok'}`);
      if (blurry) {
        const ok = confirm(`"${f.name}" looks blurry (sharpness ${variance.toFixed(0)} / ${BLUR_THRESHOLD}).\n\nKeep it, or tap Cancel to retake?`);
        if (!ok) continue;
      }
      addPendingFile(f);
    }
  });

  // Attachment path: image+pdf+txt, no blur check
  document.getElementById('f_note_file').addEventListener('change', function(e) {
    Array.from(e.target.files || []).forEach(addPendingFile);
    e.target.value = '';
  });

  function addPendingFile(f) {
    pendingFiles.push(f);
    renderPending();
  }
  function removePending(idx) {
    pendingFiles.splice(idx, 1);
    renderPending();
  }
  function renderPending() {
    const host = document.getElementById('notePending');
    host.innerHTML = '';
    pendingFiles.forEach((f, idx) => {
      const wrap = document.createElement('div');
      wrap.className = 'position-relative';
      wrap.style.width = '70px';
      const isImg = /^image\//.test(f.type);
      if (isImg) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:6px;';
        img.onload = () => URL.revokeObjectURL(img.src);
        wrap.appendChild(img);
      } else {
        wrap.innerHTML =
          `<div style="width:70px;height:70px;border:1px solid #ccc;border-radius:6px;background:#fff;padding:2px;
                       display:flex;flex-direction:column;align-items:center;justify-content:center;">
             <div style="font-size:22px;">📎</div>
             <div class="small text-truncate" style="max-width:64px;">${escapeHtml(f.name)}</div>
           </div>`;
      }
      const x = document.createElement('button');
      x.type = 'button'; x.className = 'btn-close';
      x.setAttribute('aria-label', 'Remove');
      x.style.cssText = 'position:absolute;top:0;right:0;background-color:#fff;border-radius:50%;opacity:0.85;';
      x.addEventListener('click', () => removePending(idx));
      wrap.appendChild(x);
      host.appendChild(wrap);
    });
  }

  // Posts whatever is in the composer (textarea + pendingFiles) against `shiftId`,
  // then clears the composer. Throws on note-create failure; surfaces per-file
  // upload errors via the in-composer #noteErr element. Caller is responsible
  // for refreshNotesList() / closing the modal as appropriate.
  async function postPendingNote(shiftId) {
    const body = document.getElementById('f_note_body').value.trim();
    if (!body && !pendingFiles.length) return;

    // Empty-body notes get a placeholder so the timestamp still has meaning.
    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('shift_id', shiftId);
    fd.append('body', body || '(attachment)');
    const cr = await fetch('api/notes.php', { method: 'POST', body: fd })
      .then(r => r.json().then(j => ({ok: r.ok, body: j})));
    if (!cr.ok) throw new Error(cr.body.error || 'Failed to post note');
    const noteId = cr.body.id;

    // Upload each pending file in sequence (sequential keeps mobile data sane)
    for (const f of pendingFiles) {
      const ufd = new FormData();
      ufd.append('action', 'upload');
      ufd.append('note_id', noteId);
      ufd.append('file', f);
      const ur = await fetch('api/attachment.php', { method: 'POST', body: ufd })
        .then(r => r.json().then(j => ({ok: r.ok, body: j})));
      if (!ur.ok) setNoteFeedback(`Some files failed: ${ur.body.error || 'upload error'}`, 'error');
    }
    document.getElementById('f_note_body').value = '';
    pendingFiles = [];
    renderPending();
  }

  // Sets noteErr to error/success styling. The element ships with .text-danger
  // baked in, so both Save Note and Save & Notify need to flip it explicitly.
  function setNoteFeedback(msg, mode /* 'error' | 'success' | 'clear' */) {
    const el = document.getElementById('noteErr');
    el.textContent = msg || '';
    el.classList.toggle('text-danger',  mode === 'error');
    el.classList.toggle('text-success', mode === 'success');
  }

  document.getElementById('btnNotePost').addEventListener('click', async function() {
    setNoteFeedback('', 'clear');
    const body = document.getElementById('f_note_body').value.trim();
    if (!body && !pendingFiles.length) {
      setNoteFeedback('Add text or at least one photo/attachment.', 'error');
      return;
    }
    this.disabled = true;
    try {
      await postPendingNote(notesShiftId);
      refreshNotesList();
    } catch (e) {
      setNoteFeedback(e.message, 'error');
    } finally {
      this.disabled = false;
    }
  });

  // Save & Notify: post the note, then SMS-blast everyone with the Notify
  // permission. Note save and notify are independent — if the SMS step fails,
  // the note is still saved (we just surface the error).
  document.getElementById('btnNoteNotify').addEventListener('click', async function() {
    setNoteFeedback('', 'clear');
    const body = document.getElementById('f_note_body').value.trim();
    if (!body && !pendingFiles.length) {
      setNoteFeedback('Add text or at least one photo/attachment.', 'error');
      return;
    }
    const others = [document.getElementById('btnNotePost')];
    this.disabled = true;
    others.forEach(b => b.disabled = true);
    try {
      await postPendingNote(notesShiftId);
      refreshNotesList();
      const fd = new FormData();
      fd.append('action', 'notify');
      fd.append('shift_id', notesShiftId);
      const nr = await fetch('api/notes.php', { method: 'POST', body: fd })
        .then(r => r.json().then(j => ({ok: r.ok, body: j})));
      if (!nr.ok) throw new Error(nr.body.error || 'Notify failed');
      const { sent, recipients, failed, skipped } = nr.body;
      let summary = `Sent to ${sent}/${recipients}.`;
      if (skipped && skipped.length) summary += ` No phone: ${skipped.join(', ')}.`;
      if (failed && failed.length)   summary += ` Failed: ${failed.join('; ')}.`;
      const clean = !failed?.length && !skipped?.length && sent > 0;
      setNoteFeedback(summary, clean ? 'success' : 'error');
    } catch (e) {
      setNoteFeedback(e.message, 'error');
    } finally {
      this.disabled = false;
      others.forEach(b => b.disabled = false);
    }
  });

  // ---- Blur detection (pure JS, Laplacian variance) ----
  // Threshold is in the 0–255 grayscale domain. ~100 is a sensible default;
  // anything well below is visibly blurry. Tune by watching the console log.
  const BLUR_THRESHOLD = 100;

  function detectBlurClient(file) {
    return new Promise(resolve => {
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => {
        try {
          const MAX = 800;
          const r = Math.min(MAX / img.width, MAX / img.height, 1);
          const w = Math.max(2, Math.round(img.width  * r));
          const h = Math.max(2, Math.round(img.height * r));
          const cv = document.createElement('canvas');
          cv.width = w; cv.height = h;
          const ctx = cv.getContext('2d');
          ctx.drawImage(img, 0, 0, w, h);
          const data = ctx.getImageData(0, 0, w, h).data;

          const gray = new Float32Array(w * h);
          for (let i = 0, j = 0; i < data.length; i += 4, j++) {
            gray[j] = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
          }
          // 3x3 Laplacian: 4*center - 4-neighbors
          let sum = 0, n = 0;
          const lap = new Float32Array(w * h);
          for (let y = 1; y < h - 1; y++) {
            for (let x = 1; x < w - 1; x++) {
              const i = y * w + x;
              const v = 4 * gray[i] - gray[i-1] - gray[i+1] - gray[i-w] - gray[i+w];
              lap[i] = v;
              sum += v;
              n++;
            }
          }
          const mean = sum / n;
          let sqsum = 0;
          for (let y = 1; y < h - 1; y++) {
            for (let x = 1; x < w - 1; x++) {
              const d = lap[y * w + x] - mean;
              sqsum += d * d;
            }
          }
          const variance = sqsum / n;
          URL.revokeObjectURL(url);
          resolve({ variance, blurry: variance < BLUR_THRESHOLD });
        } catch (e) {
          URL.revokeObjectURL(url);
          resolve({ variance: 0, blurry: false, error: e.message });
        }
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve({ variance: 0, blurry: false, error: 'load' }); };
      img.src = url;
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }

  document.getElementById('shiftForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = $('f_id').value;
    const errEl = document.getElementById('shiftFormErr');
    errEl.classList.add('d-none');

    const fd = new FormData();
    fd.append('action', id ? 'update' : 'create');
    if (id) fd.append('id', id);
    fd.append('client_id', currentClient);
    fd.append('caregiver_id', $('f_caregiver').value);
    fd.append('start_dt', toMySQL($('f_start').value));
    fd.append('end_dt',   toMySQL($('f_end').value));

    try {
      const sr = await fetch('api/shifts.php', { method: 'POST', body: fd })
        .then(r => r.json().then(j => ({ok: r.ok, body: j})));
      if (!sr.ok) throw new Error(sr.body.error || 'Save failed');

      cal.refetchEvents();
      if (cal.view.type === 'dayGridMonth') paintMonthCells(cal.view.currentStart.toISOString(), cal.view.currentEnd.toISOString());

      // If the user typed in the note composer (or queued attachments) and hit
      // Save instead of Save Note, post it as part of Save so nothing is lost.
      // notesShiftId is only set when the composer is visible (i.e. editing an
      // existing shift), so this branch never fires for a brand-new shift.
      const shiftId = id || sr.body.id;
      const hasPendingNote = !!document.getElementById('f_note_body').value.trim() || pendingFiles.length > 0;
      if (hasPendingNote && notesShiftId === shiftId) {
        try {
          await postPendingNote(shiftId);
        } catch (noteErr) {
          // Shift is saved; surface the note failure in the composer and keep
          // the modal open so the user can retry without retyping.
          setNoteFeedback(noteErr.message, 'error');
          refreshNotesList();
          return;
        }
      }

      // After a new shift is created, stay in the modal and switch to edit mode
      // so the user can immediately start posting notes/photos. The user just
      // created this shift, so it's theirs — notes are both readable and writable.
      if (!id && sr.body.id) {
        $('f_id').value = sr.body.id;
        document.getElementById('shiftModalTitle').textContent = 'Edit Shift';
        $('btnDelete').classList.remove('d-none');
        setNotesShift(sr.body.id, true, true);
      } else {
        modal.hide();
      }
    } catch (err) {
      errEl.textContent = err.message;
      errEl.classList.remove('d-none');
    }
  });

  document.getElementById('btnDelete').addEventListener('click', function() {
    if (!confirm('Delete this shift?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', $('f_id').value);
    fetch('api/shifts.php', { method: 'POST', body: fd })
      .then(r => r.json().then(j => ({ok: r.ok, body: j})))
      .then(({ok, body}) => {
        if (!ok) throw new Error(body.error || 'Delete failed');
        modal.hide();
        cal.refetchEvents();
      })
      .catch(err => {
        const e = document.getElementById('shiftFormErr');
        e.textContent = err.message; e.classList.remove('d-none');
      });
  });

  /* ---------- Month-cell coverage coloring ---------- */
  function paintMonthCells(startISO, endISO) {
    const from = startISO.slice(0, 10);
    const to   = endISO.slice(0, 10);
    fetch(`api/coverage.php?client_id=${currentClient}&from=${from}&to=${to}`)
      .then(r => r.json())
      .then(map => {
        document.querySelectorAll('.fc-daygrid-day').forEach(cell => {
          const d = cell.getAttribute('data-date');
          cell.classList.remove('day-cover-full','day-cover-partial','day-cover-empty');
          if (map[d]) cell.classList.add('day-cover-' + map[d]);
        });
      });
  }

  /* ---------- Per-day "compressed" toggle in week view ---------- */
  function installDayToggles() {
    document.querySelectorAll('.fc-col-header-cell').forEach(headerCell => {
      if (headerCell.querySelector('.day-toggle-btn')) return;
      const dateAttr = headerCell.getAttribute('data-date');
      if (!dateAttr) return;
      const btn = document.createElement('button');
      btn.className = 'day-toggle-btn';
      btn.textContent = 'list';
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDayCompressed(dateAttr, btn);
      });
      headerCell.querySelector('.fc-col-header-cell-cushion')?.appendChild(btn);
    });
  }

  function toggleDayCompressed(date, btn) {
    const cols = document.querySelectorAll(`.fc-timegrid-col[data-date="${date}"]`);
    if (!cols.length) return;
    const col = cols[0];
    const existing = col.querySelector('.cg-compressed');
    if (existing) {
      existing.remove();
      col.style.position = '';
      btn.textContent = 'list';
      return;
    }
    fetch(`api/day_list.php?client_id=${currentClient}&date=${date}`)
      .then(r => r.json())
      .then(rows => {
        const div = document.createElement('div');
        div.className = 'cg-compressed';
        div.style.position = 'absolute';
        div.style.inset = '0';
        div.style.background = '#fff';
        div.style.zIndex = '5';
        div.style.overflow = 'auto';
        rows.forEach(r => {
          const row = document.createElement('div');
          row.className = r.kind === 'gap' ? 'row-gap' : 'row-shift';
          if (r.kind === 'shift') row.style.background = r.color;
          row.textContent = `${r.label} (${r.start_hm} – ${r.end_hm})`;
          div.appendChild(row);
        });
        col.style.position = 'relative';
        col.appendChild(div);
        btn.textContent = 'timeline';
      });
  }
})();
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
