# Caregivers App — Architecture

## Stack
- UserSpice 5.x (`users/init.php`, `$db = DB::getInstance()`, `$db->query(sql,[params])->results()`)
- MySQL at 192.168.1.95
- FullCalendar v6 (CDN) for month/week/day
- Bootstrap 5 (UserSpice default)
- Custom compressed-day list view (vanilla JS) toggled per-day in week view

## Tables (prefix `cg_`)
> UserSpice 5.x: core tables are **unprefixed** (`users`, `permissions`, `user_permission_matches`). The `$db_table_prefix = "uc_"` in `init.php` is for legacy compatibility only. New caregiver tables use `cg_` prefix to keep namespace clean.

- **cg_clients** — patients/care recipients
  - `id`, `name`, `notes`, `active`, `created_at`
- **cg_caregivers** — care providers (admin-managed; optional UserSpice link)
  - `id`, `name`, `phone`, `email`, `user_id` (NULL or FK to `uc_users.id`), `color` (#hex for calendar), `active`, `created_at`
- **cg_shifts** — coverage blocks
  - `id`, `client_id` (FK cg_clients), `caregiver_id` (FK cg_caregivers), `start_dt` DATETIME, `end_dt` DATETIME, `notes`, `created_by` (uc_users.id), `created_at`, `updated_at`
  - CHECK: `end_dt > start_dt` (enforced at app layer for MySQL <8.0.16 compat)
  - Shifts that cross midnight are stored as a SINGLE row; rendered as two visual blocks (FullCalendar handles this automatically).
- **cg_settings** — key/value app settings
  - `id`, `skey` VARCHAR(64) UNIQUE, `sval` TEXT
  - Keys: `sms_provider` (voipms|private), `sms_user_id`, `sms_pass`, `sms_did`, `sms_private_ip`, `sms_private_port`, `sms_private_user`, `sms_private_pass`, `default_client_id`, `ot_start_hour`, `ot_end_hour`
- **cg_caregivers (payroll columns, added 2026-05-19)** — `payable` (TINYINT), `pay_rate` (DECIMAL NULL), `diff_ot_mult`, `diff_ot_add`, `diff_hol_mult`, `diff_hol_add`
- **cg_holidays** — `hdate` DATE PK, `name`
- **cg_shift_audit** — `id, shift_id, action ENUM(insert/update/delete), actor_user_id, actor_caregiver_id, actor_name, before_json, after_json, created_at`. Every call to `cg_createShift`/`cg_updateShift`/`cg_deleteShift` writes one row via `cg_logShiftAudit`.

## Schema migrations
- DB schema changes are done via PHP files under `install/patches/`, auto-included from `cg_init.php`. Each patch defines an idempotent `cg_patch_*` function with a static guard. Re-runnable; first page-load on a fresh DB upgrades it. Do NOT apply schema changes by hand on the live DB only — always write the patch.

## Permission model (UserSpice permissions table = `uc_permissions`)
- Level 2 = Admin (built-in) — full CRUD on everything
- New level: **`caregiver`** (id assigned at install) — can:
  - SELECT all shifts (to see the schedule)
  - INSERT/UPDATE/DELETE only shifts where `caregiver_id` = the caregiver row whose `user_id` = themselves
- Permission gate function `cg_canEditShift($shift_row)` in `usersc/includes/cg_init.php`

## Files
```
/usersc/includes/
  cg_init.php          - bootstrap, helpers, permission, gap math
  sms.php              - port of vbs sendSMS()
  config.php           - $cg_config loaded from cg_settings table
/cg/
  index.php            - main calendar (FullCalendar)
  api/
    shifts.php         - GET (range) / POST (create) / PUT (edit) / DELETE
    coverage.php       - GET ?from=&to= returns day-by-day gap summary for month view
  admin_caregivers.php
  admin_clients.php
  admin_settings.php
```

## Gap-detection algorithm
1. Pull shifts where `end_dt > $day_start AND start_dt < $day_end` for a date.
2. Clip each shift to [day_start, day_end] (handles cross-midnight).
3. Sort by start.
4. Merge overlapping intervals into a single coverage union.
5. Subtract coverage union from [day_start, day_end] → list of gaps.
6. For week/day view: render gaps as yellow blocks (FullCalendar background events).
7. For month view: a day is GREEN if zero gaps, YELLOW if any gap, RED if no coverage at all.
8. Compressed list view: interleave shifts + `Available` rows for each gap, sorted by start_dt.

## Drag / resize
- Calendar is `editable: true` globally; each event from `api/shifts.php` carries its own `editable` flag set from `cg_canEditShift($shift)`. FullCalendar honors the per-event flag, so non-admin caregivers can only drag their own shifts.
- `eventDrop` and `eventResize` both POST `action=update` with the unchanged `caregiver_id` and the new start/end. Server enforces `cg_canEditShift` + the `caregiver_id` reassignment guard regardless.
- On API failure the JS calls `info.revert()` so the calendar visual rolls back.

## Cross-midnight rendering
- Stored as one row with `start_dt < end_dt` across the day boundary.
- FullCalendar v6 natively splits multi-day events across columns in week view.
- For the compressed list view, server-side splits the shift at midnight before building the day's list.
