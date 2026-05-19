# Daily Log

## 2026-05-18 — Initial build

### What was built
- Fresh UserSpice 5.x install primed at `c:\xampp\htdocs\caregivers`.
- New `cg_*` tables + Caregiver permission (id=3) via `install_cg_schema.sql`.
- Helpers: `usersc/includes/cg_init.php`, `usersc/includes/sms.php` (ported from VBS).
- Pages: `cg/index.php` (FullCalendar v6), `cg/admin_caregivers.php`, `cg/admin_clients.php`, `cg/admin_settings.php`.
- APIs: `cg/api/shifts.php`, `cg/api/coverage.php`, `cg/api/day_list.php`.
- Root `index.php` redirects logged-in users to `/cg/`.

### Quirks
- **UserSpice 5.x tables are UNPREFIXED.** `init.php` still has `$db_table_prefix = "uc_"` but that is for legacy queries only — real tables are `users`, `permissions`, `user_permission_matches`. Original assumption (`uc_users`) failed.
- **Permission API is `hasPerm([id, id, ...])`**, not `checkPermission()`. Found in `users/helpers/permissions.php:605`.
- **Email function**: global `email($to, $subject, $body, $opts, $attachment)` in `users/helpers/helpers.php:158`. Reads SMTP config from `email` table.
- **`$db->lastId()`** returns the last insert id.
- **FullCalendar v6 CDN**: `https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js` works without a build step.
- **Cross-midnight shifts**: stored as one row with `start_dt < end_dt` spanning the boundary; FullCalendar splits them visually in week view. Server-side `cg_dayList()` re-clips each shift to the day before emitting list rows.
- **Per-day "compressed" toggle**: implemented by injecting an absolute-positioned overlay div into the `.fc-timegrid-col` for that day — toggled via a button added to the header cell in `datesSet`.

### Mobile UX (2026-05-18 follow-up)
- **Tap a day in month view → drill into day view** (`dateClick` + `cal.changeView('timeGridDay', date)`). Works on both touch and mouse.
- **Tap an empty time slot in week/day view → open Add Shift modal** with that hour as start + 1-hour default end. Sidesteps the awkward long-press-then-drag select on phones.
- **Drag-to-select still works** for desktop power users (FullCalendar `select` callback).
- **Always-visible "+ Add Shift" button** in the toolbar — opens modal with snap-to-hour defaults. The reliable escape hatch on any device.
- On `<= 768 px`: initial view = `timeGridDay`, `timeGridWeek` button hidden, `slotDuration` = 1 hr, slot height bumped, header buttons enlarged. View flips dynamically on rotate.
- `selectLongPressDelay` and `longPressDelay` lowered to 250 ms (default is 1000 ms) — keeps drag-select feasible on touch without feeling sluggish.

### Mobile + attachments (2026-05-18 follow-up)
- **Week view restored on mobile** — toolbar is the same on all screen sizes; only `slotDuration` changes (1 hr on phone, 30 min on desktop).
- **Tap a day header (day name/number) → drill into day view** via FullCalendar `navLinks: true` + `navLinkDayClick: 'timeGridDay'`. Works in both month and week views.
- Tapping an empty time slot still opens the Add Shift modal (preserved from earlier mobile work).

### Attachments
- New table `cg_shift_attachments` (id, shift_id, filename, orig_name, mime, size_bytes, uploaded_by, uploaded_at).
- Storage: `usersc/uploads/cg_shifts/<shift_id>/<random>.<ext>` with `.htaccess` deny-all so files cannot be hot-linked.
- Files served by `cg/api/attachment.php?action=get&id=N` after login check.
- Whitelist: jpeg/png/gif/webp/heic/heif, PDF, plain text. Max 15 MB per file. Mime is sniffed by `finfo`, not trusted from client.
- Upload via plain `<input type="file" accept="image/*,application/pdf" capture="environment" multiple>` — triggers the device's native camera/gallery picker. No custom MediaDevices code (kept it simple; the VBS `camera_unified.php` reference uses OpenCV for face/blur checks, which is overkill for shift evidence photos).
- Permission: only the shift's owner caregiver or an admin can upload/delete (`cg_canEditShift($shift)`). Any logged-in user can view (mirrors the read-all calendar policy).
- UX: attachment section is hidden until the shift exists. New shifts → after first save, the modal switches to edit mode and reveals the attachment picker so the user can immediately add photos without re-opening anything.

### Notes + blur (2026-05-18 follow-up 3)
- **Notes** are stored in a `TEXT` column (≈64 KB) — effectively unlimited. The modal textarea is `rows="5"` with an explicit "no length limit" hint so users know they can write substantial entries.
- **Blur detection**: implemented client-side in pure JS (Laplacian-variance method, same approach as the VBS OpenCV version but ~50 LOC, no library). Threshold = 100 in the 0-255 grayscale domain. Operates on a downsized 800px copy for speed, before upload. On a blurry photo it shows a `confirm()` with the variance score so the user can either upload anyway or cancel and retake.
- No face detection (per user — caregiver photos are evidence/log photos, not ID shots).
- The threshold is a single constant (`BLUR_THRESHOLD = 100`) at the top of the blur block in `cg/index.php`. Bump it up if too many sharp photos get flagged, down if blurry ones slip through. Variance for each photo is logged to the console for easy tuning.

### Time-stamped notes + per-note attachments (2026-05-18 follow-up 4)
- **Schema change**: `cg_shifts.notes` column dropped. New table `cg_shift_notes` (id, shift_id, body, author_user_id, author_caregiver_id, created_at, edited_at, edited_by). `cg_shift_attachments` got a nullable `note_id` so attachments live under notes now.
- **Permissions**: author can edit their own notes; admin can edit/delete anyone's. `cg_canEditNote`/`cg_canDeleteNote` in `cg_init.php`. Attachments inherit the parent note's edit policy.
- **Cascade delete**: deleting a shift removes its notes, attachments, and on-disk files. Deleting a note removes its attached files. Implemented in `cg_deleteShift` / `cg_deleteNote`.
- **API**: new `cg/api/notes.php` (list/create/update/delete). `cg/api/attachment.php` now accepts `note_id` on upload and uses the note's permission if present.
- **Modal UI**: removed the single-textarea notes field. After a shift exists, a vertical timeline of notes appears (oldest at top, each with author • timestamp • "edited at X" badge if modified). Below is a composer with:
  - Body textarea
  - **📷 Photo** button → triggers hidden image-only `<input capture="environment">` → runs blur check → adds to a pending strip
  - **📎 Attachment** button → triggers hidden `image/* + pdf + txt` input → adds to pending strip (no blur check; allows gallery + docs)
  - Pending strip shows thumbnails with X to remove before posting
  - Post note → creates the note row → uploads each pending file with `note_id` set → refreshes timeline
- **Author tracking**: every note row stores both the UserSpice user_id (for editor-permission checks) and the linked caregiver record (so the displayed name is the caregiver's name as shown on the schedule; survives if the caregiver's user link changes later).
- **Empty-body notes** allowed (set to `(attachment)` placeholder) when only files are posted, so timestamp + author still have meaning.

### Edit-window + history feed (2026-05-19)

**Time-gated edit window** (caregivers can no longer rewrite history)
- `cg_noteEditWindowOpen($note)` returns true if:
  - The note was created within `CG_NOTE_RECENT_GRACE_SEC` (default 4 h) — typo grace, OR
  - The note's shift is "current-ish" — `[shift.start_dt - CG_NOTE_SHIFT_BUFFER_SEC, shift.end_dt + CG_NOTE_SHIFT_BUFFER_SEC]` covers now (default 1 h pre/post buffer)
- `cg_canEditNote` and `cg_canDeleteNote` both now gate on this window for the note's author. Admin still has unrestricted access.
- Both buffers are tweakable constants near the top of the note helpers section in `cg_init.php`.
- Verified against the live DB: old note on old shift → closed; just-posted note on old shift → open (grace); note on currently-active shift → open; planning note on future shift → open (grace).

**History feed**
- New `cg/api/notes.php?action=recent` returns notes across all shifts for a client, joined with shift + caregiver info.
  - `hours=N` rolling window (default 24). Capped to 24 for non-admin.
  - Admin may pass `from=…&to=…` MySQL DATETIME for explicit ranges, plus `caregiver_id=N` to filter by the shift's assigned caregiver.
  - `LIMIT 500` ceiling.
- New page `cg/history.php` — newest-first list with author chip, shift caregiver chip, timestamp, "edited" badge, attachments thumbnail strip, and a "View shift" link.
  - Caregivers see only "last 24 hours" with no controls (per requirement).
  - Admin sees a from/to + caregiver dropdown.
- Deep-link wired: `cg/index.php?goto=YYYY-MM-DD&shift=N` navigates the calendar to that date and pops the shift modal once events finish loading.
- History link added to the calendar header for everyone.

### Drag-to-edit shifts (2026-05-19)
- Calendar is now `editable: true`, but the API stamps each shift event with `editable: $can_edit`. FullCalendar's per-event flag wins, so caregivers can only drag/resize their own shifts; admins can drag any. Gap background events are non-interactive (FullCalendar's `display:'background'` rule).
- `eventDrop` and `eventResize` both call `persistEventChange(info)` in `cg/index.php`, which POSTs `action=update` with the existing `caregiver_id` and new MySQL-formatted start/end. On any server error it calls `info.revert()` and alerts.
- Refetches events after a successful drag so gap blocks + month-cell tints recompute. Brief flash is intentional — visual coverage stays correct.
- No API changes beyond adding the per-event `editable` flag — `cg_canEditShift` already enforces the permission server-side, and the `caregiver_id` reassignment guard in `shifts.php` blocks any non-admin who tries to move someone else's shift via crafted POST.

### Notifications (deferred)
v1 has SMS plumbing + email available but no triggers are wired. Future idea (user): admin alert when a shift edit shortens coverage or creates a gap. Hooks would live in `cg_updateShift` / `cg_deleteShift` after computing pre/post coverage diff.

### Payroll report (2026-05-19)
- Schema patch lives at `install/patches/2026-05-19_payroll.php` and auto-runs on first include from `usersc/includes/cg_init.php`. Idempotent (column-presence + tableExists checks). DB changes go through PHP, not raw SQL files, so other environments self-update on first load.
- New `cg_caregivers` columns: `payable` (TINYINT default 1), `pay_rate` (DECIMAL nullable), `diff_ot_mult`/`diff_ot_add`, `diff_hol_mult`/`diff_hol_add`. Pay formula: `effective_rate = base * mult + add`. Mult defaults to 1.0, add to 0. Multiplier applies first, then add.
- New `cg_holidays(hdate PK, name)` table. Hours on holiday dates use the holiday differential (precedence over overnight when they overlap).
- New `cg_settings` keys: `ot_start_hour` (default 22), `ot_end_hour` (default 6). When start > end, the window wraps midnight.
- `cg_payrollComputeShift()` in `cg_init.php` walks the shift minute-by-minute (cheap, 60 ticks/hr) and buckets time into regular/overnight/holiday. Caller is responsible for clipping the shift to the requested date range first.
- `cg/admin_payroll.php` is the admin report: from/to + client filter + include-unpayable toggle, per-caregiver card with shift detail + subtotals, grand total, print-friendly stylesheet.
- `cg/admin_holidays.php` manages the holiday calendar (admin CRUD).
- **Quirk caught the hard way**: do NOT name a local variable `$settings` inside any page that includes UserSpice's `users/includes/template/prep.php`. UserSpice's `$settings` is a global object set in `init.php`, and prep.php does `$settings->template = ...` in its template-fallback path. A local array named `$settings` shadows it and prep.php fatals with "Attempt to assign property template on array". Use a different name (e.g. `$cg_s`) for app-local settings dicts.

### Admin hub + shift audit log (2026-05-19)
- Calendar header collapsed: the 5 admin buttons (Caregivers/Clients/Payroll/Holidays/Settings) are replaced by a single `Admin` button that opens `cg/admin.php` — a card-grid hub with stats per section.
- Admin pages now navigate back via `← Admin` instead of listing every sibling — keeps the breadcrumb consistent and scales as new admin pages are added.
- New table `cg_shift_audit` (id, shift_id, action ENUM(insert/update/delete), actor_user_id, actor_caregiver_id, actor_name snapshot, before_json, after_json, created_at). Patch in `install/patches/2026-05-19_shift_audit.php`, auto-included.
- Audit logging is centralized in `cg_logShiftAudit($shift_id, $action, $before, $after)` in `cg_init.php`. Hooked directly into `cg_createShift` / `cg_updateShift` / `cg_deleteShift`, so every code path that calls those helpers (API, admin pages, drag/drop) is covered without per-call boilerplate.
- `cg_shiftSnapshot($shift)` produces the snapshot dict written into JSON (`client_id`, `caregiver_id`, `start_dt`, `end_dt`). Narrow on purpose — notes/attachments are not scheduling fields.
- Actor name is captured at write time (preferring the linked caregiver display name) so the log stays readable after a user/caregiver record is renamed.
- Update audits skip the row when before === after — drags that land at the same minute don't pollute the log.
- `cg/admin_audit.php`: from/to + action + caregiver + shift-id filters, badge-colored action verbs, deep-link to the shift's date/modal for non-delete rows, diff summary for updates. Capped to 1000 rows per page; uses JSON_EXTRACT on the snapshot columns for the caregiver filter (works on MariaDB 10.2+/MySQL 5.7+).

### To use the app
1. Log in as the UserSpice admin.
2. Go to `/caregivers/cg/admin_caregivers.php` — add caregivers. Linking a UserSpice user grants them the Caregiver permission automatically.
3. Optionally rename the auto-created client at `/caregivers/cg/admin_clients.php`.
4. Add SMS creds at `/caregivers/cg/admin_settings.php` (only needed if/when notification triggers are added).
5. Open `/caregivers/cg/index.php` — drag/click on the week timeline to add a shift.
