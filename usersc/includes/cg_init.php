<?php
// Caregivers app bootstrap. Include AFTER users/init.php.
// Provides: settings loader, permission helpers, shift CRUD, gap math.

if (!defined('CG_PERM_CAREGIVER')) {
    define('CG_PERM_CAREGIVER', 3);   // permissions.id seeded by install_cg_schema.sql
    define('CG_PERM_ADMIN', 2);
    define('CG_PERM_MANAGER', 4);     // seeded by install/patches/2026-05-19_manager_and_caregiver_audit.php
}

// Idempotent SMS-settings seed. First page-load on a fresh install populates
// cg_settings; subsequent runs no-op (function uses a static guard + the
// underlying SQL preserves any non-empty values).
require_once __DIR__ . '/cg_seed_sms.php';
cg_seed_sms_settings();

// Idempotent schema patches. Each file defines one function and a static guard;
// re-running is a no-op once the column/table/seed is in place.
require_once dirname(__DIR__, 2) . '/install/patches/2026-05-19_payroll.php';
cg_patch_2026_05_19_payroll();
require_once dirname(__DIR__, 2) . '/install/patches/2026-05-19_shift_audit.php';
cg_patch_2026_05_19_shift_audit();
require_once dirname(__DIR__, 2) . '/install/patches/2026-05-19_manager_and_caregiver_audit.php';
cg_patch_2026_05_19_manager_and_caregiver_audit();

// Auto-revive UserSpice session from a still-valid pwsms cookie. Skipped when
// the user is already password-logged-in. Lets a returning caregiver land on
// any cg/ page without re-doing SMS verification.
if (!isset($user) || !$user->isLoggedIn()) {
    require_once __DIR__ . '/pwsms_auth.php';
    pwsms_revive_from_cookie();
}

/* ---------- settings ---------- */

function cg_settings() {
    static $cache = null;
    if ($cache !== null) return $cache;
    global $db;
    $rows = $db->query('SELECT skey, sval FROM cg_settings')->results();
    $cache = [];
    foreach ($rows as $r) $cache[$r->skey] = $r->sval;
    return $cache;
}

function cg_setting_set($key, $val) {
    global $db;
    $db->query('INSERT INTO cg_settings (skey, sval) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE sval = VALUES(sval)', [$key, $val]);
}

/* ---------- permission helpers ---------- */

function cg_isAdmin() {
    global $user;
    if (!$user || !$user->isLoggedIn()) return false;
    return hasPerm([CG_PERM_ADMIN]);
}

function cg_isCaregiver() {
    global $user;
    if (!$user || !$user->isLoggedIn()) return false;
    return hasPerm([CG_PERM_CAREGIVER]);
}

// Manager: a delegated admin who can manage caregivers, SMS settings, and any
// shift, but does NOT see pay rates, payroll, clients, holidays, or the
// audit/log pages. Admins also satisfy isManager.
function cg_isManager() {
    global $user;
    if (!$user || !$user->isLoggedIn()) return false;
    return hasPerm([CG_PERM_ADMIN, CG_PERM_MANAGER]);
}

// Caregiver row linked to currently logged-in user, or null.
function cg_currentCaregiver() {
    global $user, $db;
    if (!$user || !$user->isLoggedIn()) return null;
    $row = $db->query('SELECT * FROM cg_caregivers WHERE user_id = ? AND active = 1',
                      [$user->data()->id])->first();
    return $row ?: null;
}

// Can the current user edit a given shift?
function cg_canEditShift($shift) {
    if (cg_isManager()) return true;   // covers admin + manager
    $me = cg_currentCaregiver();
    if (!$me) return false;
    return (int)$shift->caregiver_id === (int)$me->id;
}

/* ---------- shift audit log ---------- */

// Snapshot fields written to before/after_json. Keep narrow — only the user-visible scheduling fields.
function cg_shiftSnapshot($shift) {
    if (!$shift) return null;
    return [
        'client_id'    => (int)$shift->client_id,
        'caregiver_id' => (int)$shift->caregiver_id,
        'start_dt'     => $shift->start_dt,
        'end_dt'       => $shift->end_dt,
    ];
}

// Record one row. $action ∈ {insert,update,delete}. $before/$after are arrays (or null) per cg_shiftSnapshot.
// Actor identity is sniffed from the active UserSpice session + linked caregiver row.
function cg_logShiftAudit($shift_id, $action, $before, $after) {
    global $db, $user;
    $actor_user_id = $actor_cg_id = null;
    $actor_name = null;
    if (isset($user) && $user->isLoggedIn()) {
        $actor_user_id = (int)$user->data()->id;
        $actor_name    = trim(($user->data()->fname ?? '') . ' ' . ($user->data()->lname ?? ''));
        if ($actor_name === '') $actor_name = $user->data()->username ?? null;
        $cg = cg_currentCaregiver();
        if ($cg) {
            $actor_cg_id = (int)$cg->id;
            // Prefer caregiver display name when available — that's what other users see on the calendar.
            $actor_name  = $cg->name;
        }
    }
    $db->query(
        'INSERT INTO cg_shift_audit
           (shift_id, action, actor_user_id, actor_caregiver_id, actor_name, before_json, after_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $shift_id,
            $action,
            $actor_user_id,
            $actor_cg_id,
            $actor_name,
            $before !== null ? json_encode($before) : null,
            $after  !== null ? json_encode($after)  : null,
        ]
    );
}

/* ---------- caregiver audit log ---------- */

// Subset of cg_caregivers fields written to the audit log. Excludes created_at + id.
function cg_caregiverSnapshot($cg) {
    if (!$cg) return null;
    return [
        'name'          => $cg->name,
        'phone'         => $cg->phone,
        'email'         => $cg->email,
        'user_id'       => $cg->user_id !== null ? (int)$cg->user_id : null,
        'color'         => $cg->color,
        'active'        => (int)$cg->active,
        'payable'       => isset($cg->payable)        ? (int)$cg->payable        : null,
        'pay_rate'      => isset($cg->pay_rate)        ? $cg->pay_rate            : null,
        'diff_ot_mult'  => isset($cg->diff_ot_mult)    ? $cg->diff_ot_mult        : null,
        'diff_ot_add'   => isset($cg->diff_ot_add)     ? $cg->diff_ot_add         : null,
        'diff_hol_mult' => isset($cg->diff_hol_mult)   ? $cg->diff_hol_mult       : null,
        'diff_hol_add'  => isset($cg->diff_hol_add)    ? $cg->diff_hol_add        : null,
    ];
}

function cg_logCaregiverAudit($caregiver_id, $action, $before, $after) {
    global $db, $user;
    $actor_user_id = $actor_cg_id = null;
    $actor_name = null;
    if (isset($user) && $user->isLoggedIn()) {
        $actor_user_id = (int)$user->data()->id;
        $actor_name    = trim(($user->data()->fname ?? '') . ' ' . ($user->data()->lname ?? ''));
        if ($actor_name === '') $actor_name = $user->data()->username ?? null;
        $cg = cg_currentCaregiver();
        if ($cg) {
            $actor_cg_id = (int)$cg->id;
            $actor_name  = $cg->name;
        }
    }
    $db->query(
        'INSERT INTO cg_caregiver_audit
           (caregiver_id, action, actor_user_id, actor_caregiver_id, actor_name, before_json, after_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $caregiver_id,
            $action,
            $actor_user_id,
            $actor_cg_id,
            $actor_name,
            $before !== null ? json_encode($before) : null,
            $after  !== null ? json_encode($after)  : null,
        ]
    );
}

/* ---------- shift CRUD ---------- */

function cg_getShifts($client_id, $from_dt, $to_dt) {
    global $db;
    // note_count surfaced as an asterisk on the calendar event so admins can
    // see at-a-glance which shifts have written notes attached.
    return $db->query(
        'SELECT s.*, c.name AS caregiver_name, c.color AS caregiver_color,
                (SELECT COUNT(*) FROM cg_shift_notes n WHERE n.shift_id = s.id) AS note_count
         FROM cg_shifts s
         JOIN cg_caregivers c ON c.id = s.caregiver_id
         WHERE s.client_id = ?
           AND s.end_dt   > ?
           AND s.start_dt < ?
         ORDER BY s.start_dt',
        [$client_id, $from_dt, $to_dt]
    )->results();
}

function cg_getShift($id) {
    global $db;
    return $db->query('SELECT * FROM cg_shifts WHERE id = ?', [$id])->first();
}

function cg_createShift($client_id, $caregiver_id, $start_dt, $end_dt, $created_by) {
    global $db;
    if (strtotime($end_dt) <= strtotime($start_dt)) {
        throw new Exception('End time must be after start time.');
    }
    $db->query(
        'INSERT INTO cg_shifts (client_id, caregiver_id, start_dt, end_dt, created_by)
         VALUES (?, ?, ?, ?, ?)',
        [$client_id, $caregiver_id, $start_dt, $end_dt, $created_by]
    );
    $id = $db->lastId();
    cg_logShiftAudit($id, 'insert', null, [
        'client_id'    => (int)$client_id,
        'caregiver_id' => (int)$caregiver_id,
        'start_dt'     => $start_dt,
        'end_dt'       => $end_dt,
    ]);
    return $id;
}

function cg_updateShift($id, $caregiver_id, $start_dt, $end_dt) {
    global $db;
    if (strtotime($end_dt) <= strtotime($start_dt)) {
        throw new Exception('End time must be after start time.');
    }
    $before = cg_shiftSnapshot(cg_getShift($id));
    $db->query(
        'UPDATE cg_shifts SET caregiver_id = ?, start_dt = ?, end_dt = ? WHERE id = ?',
        [$caregiver_id, $start_dt, $end_dt, $id]
    );
    $after = cg_shiftSnapshot(cg_getShift($id));
    // Skip the audit row when nothing actually changed (drag that landed at the same minute).
    if ($before !== $after) {
        cg_logShiftAudit($id, 'update', $before, $after);
    }
}

function cg_deleteShift($id) {
    global $db;
    // Shifts with notes are protected — the shift log is the historical record
    // and we must not orphan it. The UI hides the Delete button in this case
    // but the server is the source of truth.
    $note_count = (int)$db->query('SELECT COUNT(*) AS n FROM cg_shift_notes WHERE shift_id = ?', [$id])->first()->n;
    if ($note_count > 0) {
        throw new Exception('Cannot delete a shift that has notes. Remove the notes first or just edit the shift.');
    }
    $before = cg_shiftSnapshot(cg_getShift($id));
    // Cascading cleanup: attachments on disk + DB rows (notes are guaranteed empty by the check above)
    $atts = $db->query('SELECT id, shift_id, filename FROM cg_shift_attachments WHERE shift_id = ?', [$id])->results();
    global $abs_us_root, $us_url_root;
    $root = $abs_us_root . $us_url_root . 'usersc/uploads/cg_shifts';
    foreach ($atts as $a) {
        $p = $root . '/' . (int)$a->shift_id . '/' . basename($a->filename);
        if (is_file($p)) @unlink($p);
    }
    $db->query('DELETE FROM cg_shift_attachments WHERE shift_id = ?', [$id]);
    $db->query('DELETE FROM cg_shifts            WHERE id       = ?', [$id]);
    cg_logShiftAudit($id, 'delete', $before, null);
}

/* ---------- Note CRUD ---------- */

function cg_listNotes($shift_id) {
    global $db;
    return $db->query(
        'SELECT n.*, c.name AS author_name, u.username AS author_username
           FROM cg_shift_notes n
           LEFT JOIN cg_caregivers c ON c.id = n.author_caregiver_id
           LEFT JOIN users         u ON u.id = n.author_user_id
          WHERE n.shift_id = ?
          ORDER BY n.created_at ASC, n.id ASC',
        [$shift_id]
    )->results();
}

function cg_getNote($id) {
    global $db;
    return $db->query('SELECT * FROM cg_shift_notes WHERE id = ?', [$id])->first();
}

function cg_createNote($shift_id, $body, $author_user_id, $author_caregiver_id) {
    global $db;
    $body = trim($body);
    if ($body === '') throw new Exception('Note body cannot be empty.');
    $db->query(
        'INSERT INTO cg_shift_notes (shift_id, body, author_user_id, author_caregiver_id)
         VALUES (?, ?, ?, ?)',
        [$shift_id, $body, $author_user_id, $author_caregiver_id]
    );
    return $db->lastId();
}

function cg_updateNote($id, $body, $editor_user_id) {
    global $db;
    $body = trim($body);
    if ($body === '') throw new Exception('Note body cannot be empty.');
    $db->query(
        'UPDATE cg_shift_notes SET body = ?, edited_at = NOW(), edited_by = ? WHERE id = ?',
        [$body, $editor_user_id, $id]
    );
}

function cg_deleteNote($id) {
    global $db;
    global $abs_us_root, $us_url_root;
    $atts = $db->query('SELECT id, shift_id, filename FROM cg_shift_attachments WHERE note_id = ?', [$id])->results();
    $root = $abs_us_root . $us_url_root . 'usersc/uploads/cg_shifts';
    foreach ($atts as $a) {
        $p = $root . '/' . (int)$a->shift_id . '/' . basename($a->filename);
        if (is_file($p)) @unlink($p);
    }
    $db->query('DELETE FROM cg_shift_attachments WHERE note_id = ?', [$id]);
    $db->query('DELETE FROM cg_shift_notes       WHERE id      = ?', [$id]);
}

// Author-edit window: the note's shift is current-ish, OR the note was just posted.
// Tweak the buffers here if caregivers complain about the window being too tight.
if (!defined('CG_NOTE_SHIFT_BUFFER_SEC')) define('CG_NOTE_SHIFT_BUFFER_SEC', 3600);    // 1 hour pre/post-shift
if (!defined('CG_NOTE_RECENT_GRACE_SEC')) define('CG_NOTE_RECENT_GRACE_SEC', 4 * 3600); // 4 hours after posting

function cg_noteEditWindowOpen($note) {
    if (!$note) return false;
    $now = time();
    if (($now - strtotime($note->created_at)) <= CG_NOTE_RECENT_GRACE_SEC) return true;
    $shift = cg_getShift($note->shift_id);
    if (!$shift) return false;
    $start = strtotime($shift->start_dt) - CG_NOTE_SHIFT_BUFFER_SEC;
    $end   = strtotime($shift->end_dt)   + CG_NOTE_SHIFT_BUFFER_SEC;
    return $now >= $start && $now <= $end;
}

// Authors can edit/delete their own while the window is open. Admins always.
function cg_canEditNote($note) {
    if (!$note) return false;
    if (cg_isAdmin()) return true;
    global $user;
    if (!$user || !$user->isLoggedIn()) return false;
    if ((int)$note->author_user_id !== (int)$user->data()->id) return false;
    return cg_noteEditWindowOpen($note);
}
function cg_canDeleteNote($note) {
    if (!$note) return false;
    if (cg_isAdmin()) return true;
    global $user;
    if (!$user || !$user->isLoggedIn()) return false;
    if ((int)$note->author_user_id !== (int)$user->data()->id) return false;
    return cg_noteEditWindowOpen($note);
}

/* ---------- gap math ----------
 * Inputs are DATETIME strings (Y-m-d H:i:s) or unix ints; output is unix ints.
 * Returns array of [start_ts, end_ts] gap intervals within [day_start, day_end].
 */

function cg_computeGaps($shifts, $day_start_ts, $day_end_ts) {
    // 1. Clip each shift to the day window.
    $intervals = [];
    foreach ($shifts as $s) {
        $a = max($day_start_ts, strtotime($s->start_dt));
        $b = min($day_end_ts,   strtotime($s->end_dt));
        if ($b > $a) $intervals[] = [$a, $b];
    }
    if (empty($intervals)) {
        return [[$day_start_ts, $day_end_ts]];
    }
    // 2. Sort by start and merge overlaps.
    usort($intervals, fn($x, $y) => $x[0] <=> $y[0]);
    $merged = [array_shift($intervals)];
    foreach ($intervals as $iv) {
        $last =& $merged[count($merged) - 1];
        if ($iv[0] <= $last[1]) {
            if ($iv[1] > $last[1]) $last[1] = $iv[1];
        } else {
            $merged[] = $iv;
        }
        unset($last);
    }
    // 3. Subtract merged coverage from [day_start, day_end].
    $gaps = [];
    $cursor = $day_start_ts;
    foreach ($merged as $iv) {
        if ($iv[0] > $cursor) $gaps[] = [$cursor, $iv[0]];
        $cursor = max($cursor, $iv[1]);
    }
    if ($cursor < $day_end_ts) $gaps[] = [$cursor, $day_end_ts];
    return $gaps;
}

// Build the "compressed list" rows for a single day: shifts + Available gap rows, time-sorted.
function cg_dayList($shifts, $day_start_ts, $day_end_ts) {
    $rows = [];
    foreach ($shifts as $s) {
        $a = max($day_start_ts, strtotime($s->start_dt));
        $b = min($day_end_ts,   strtotime($s->end_dt));
        if ($b > $a) {
            $note_count = (int)($s->note_count ?? 0);
            $rows[] = [
                'kind'     => 'shift',
                'start_ts' => $a,
                'end_ts'   => $b,
                'label'    => $s->caregiver_name . ($note_count > 0 ? ' *' : ''),
                'color'    => $s->caregiver_color,
                'shift_id' => $s->id,
            ];
        }
    }
    foreach (cg_computeGaps($shifts, $day_start_ts, $day_end_ts) as $g) {
        $rows[] = [
            'kind'     => 'gap',
            'start_ts' => $g[0],
            'end_ts'   => $g[1],
            'label'    => 'Available',
            'color'    => '#f1c40f',
        ];
    }
    usort($rows, fn($a, $b) => $a['start_ts'] <=> $b['start_ts']);
    return $rows;
}

// Day-level coverage status for month view: 'full' | 'partial' | 'empty'
function cg_dayCoverageStatus($shifts, $day_start_ts, $day_end_ts) {
    $gaps = cg_computeGaps($shifts, $day_start_ts, $day_end_ts);
    if (empty($gaps)) return 'full';
    $total_gap = 0;
    foreach ($gaps as $g) $total_gap += $g[1] - $g[0];
    return ($total_gap >= $day_end_ts - $day_start_ts) ? 'empty' : 'partial';
}

/* ---------- payroll ----------
 * Splits a shift into per-second buckets {regular, overnight, holiday},
 * applies the caregiver's base rate + differentials, and returns:
 *   ['hours' => ['regular'=>..,'overnight'=>..,'holiday'=>..],
 *    'pay'   => ['regular'=>..,'overnight'=>..,'holiday'=>..],
 *    'total_hours', 'total_pay', 'rate_known' (bool)]
 *
 * Bucket precedence: a holiday hour stays "holiday" even if it falls in the
 * overnight window. Differential math: effective_rate = base * mult + add,
 * with mult defaulting to 1.0 and add to 0.
 *
 * $holidays is a flat set: ['YYYY-MM-DD' => true, ...]
 * $ot_start_hour / $ot_end_hour bound the overnight window. If start > end
 * (e.g. 22..6), the window wraps midnight.
 */

function cg_payrollComputeShift($shift, $caregiver, $holidays, $ot_start_hour, $ot_end_hour) {
    $start = strtotime($shift->start_dt);
    $end   = strtotime($shift->end_dt);
    if ($end <= $start) {
        return ['hours'=>['regular'=>0,'overnight'=>0,'holiday'=>0],
                'pay'  =>['regular'=>0,'overnight'=>0,'holiday'=>0],
                'total_hours'=>0,'total_pay'=>0,'rate_known'=>($caregiver->pay_rate !== null)];
    }
    $sec = ['regular'=>0, 'overnight'=>0, 'holiday'=>0];
    // Walk minute-by-minute. Cheap (60 ticks/hour) and exact for hour-boundary differentials.
    for ($t = $start; $t < $end; $t += 60) {
        $date = date('Y-m-d', $t);
        $hour = (int)date('G', $t);
        $is_holiday = !empty($holidays[$date]);
        $is_overnight = cg_hourInOvernight($hour, $ot_start_hour, $ot_end_hour);
        if ($is_holiday)         $sec['holiday']++;
        elseif ($is_overnight)   $sec['overnight']++;
        else                     $sec['regular']++;
    }
    // Convert minutes to hours
    foreach ($sec as $k => $v) $sec[$k] = $v / 60.0;

    $base = ($caregiver->pay_rate !== null) ? (float)$caregiver->pay_rate : null;
    $ot_mult  = ($caregiver->diff_ot_mult  !== null) ? (float)$caregiver->diff_ot_mult  : 1.0;
    $ot_add   = ($caregiver->diff_ot_add   !== null) ? (float)$caregiver->diff_ot_add   : 0.0;
    $hol_mult = ($caregiver->diff_hol_mult !== null) ? (float)$caregiver->diff_hol_mult : 1.0;
    $hol_add  = ($caregiver->diff_hol_add  !== null) ? (float)$caregiver->diff_hol_add  : 0.0;

    $pay = ['regular'=>0, 'overnight'=>0, 'holiday'=>0];
    if ($base !== null) {
        $pay['regular']   = $sec['regular']   * $base;
        $pay['overnight'] = $sec['overnight'] * ($base * $ot_mult  + $ot_add);
        $pay['holiday']   = $sec['holiday']   * ($base * $hol_mult + $hol_add);
    }
    return [
        'hours'       => $sec,
        'pay'         => $pay,
        'total_hours' => $sec['regular'] + $sec['overnight'] + $sec['holiday'],
        'total_pay'   => $pay['regular'] + $pay['overnight'] + $pay['holiday'],
        'rate_known'  => ($base !== null),
    ];
}

function cg_hourInOvernight($hour, $ot_start_hour, $ot_end_hour) {
    $ot_start_hour = (int)$ot_start_hour;
    $ot_end_hour   = (int)$ot_end_hour;
    if ($ot_start_hour === $ot_end_hour) return false; // no overnight window
    if ($ot_start_hour < $ot_end_hour) {
        return $hour >= $ot_start_hour && $hour < $ot_end_hour;
    }
    // Wraps midnight, e.g. 22..6
    return $hour >= $ot_start_hour || $hour < $ot_end_hour;
}

function cg_holidaysInRange($from_date, $to_date) {
    global $db;
    $rows = $db->query(
        'SELECT hdate, name FROM cg_holidays WHERE hdate BETWEEN ? AND ?',
        [$from_date, $to_date]
    )->results();
    $out = [];
    foreach ($rows as $r) $out[$r->hdate] = $r->name;
    return $out;
}

/* ---------- misc ---------- */

function cg_defaultClientId() {
    $s = cg_settings();
    return (int)($s['default_client_id'] ?? 0);
}

function cg_caregiversAll($activeOnly = true) {
    global $db;
    $sql = 'SELECT * FROM cg_caregivers' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY name';
    return $db->query($sql)->results();
}

function cg_clientsAll($activeOnly = true) {
    global $db;
    $sql = 'SELECT * FROM cg_clients' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY name';
    return $db->query($sql)->results();
}
