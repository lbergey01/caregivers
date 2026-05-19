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

### Notifications (deferred)
v1 has SMS plumbing + email available but no triggers are wired. Future idea (user): admin alert when a shift edit shortens coverage or creates a gap. Hooks would live in `cg_updateShift` / `cg_deleteShift` after computing pre/post coverage diff.

### To use the app
1. Log in as the UserSpice admin.
2. Go to `/caregivers/cg/admin_caregivers.php` — add caregivers. Linking a UserSpice user grants them the Caregiver permission automatically.
3. Optionally rename the auto-created client at `/caregivers/cg/admin_clients.php`.
4. Add SMS creds at `/caregivers/cg/admin_settings.php` (only needed if/when notification triggers are added).
5. Open `/caregivers/cg/index.php` — drag/click on the week timeline to add a shift.
