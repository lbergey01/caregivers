<?php
// Caregivers app bootstrap. Include AFTER users/init.php.
// Provides: settings loader, permission helpers, shift CRUD, gap math.

if (!defined('CG_PERM_CAREGIVER')) {
    define('CG_PERM_CAREGIVER', 3);   // permissions.id seeded by install_cg_schema.sql
    define('CG_PERM_ADMIN', 2);
}

// Idempotent SMS-settings seed. First page-load on a fresh install populates
// cg_settings; subsequent runs no-op (function uses a static guard + the
// underlying SQL preserves any non-empty values).
require_once __DIR__ . '/cg_seed_sms.php';
cg_seed_sms_settings();

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
    if (cg_isAdmin()) return true;
    $me = cg_currentCaregiver();
    if (!$me) return false;
    return (int)$shift->caregiver_id === (int)$me->id;
}

/* ---------- shift CRUD ---------- */

function cg_getShifts($client_id, $from_dt, $to_dt) {
    global $db;
    return $db->query(
        'SELECT s.*, c.name AS caregiver_name, c.color AS caregiver_color
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
    return $db->lastId();
}

function cg_updateShift($id, $caregiver_id, $start_dt, $end_dt) {
    global $db;
    if (strtotime($end_dt) <= strtotime($start_dt)) {
        throw new Exception('End time must be after start time.');
    }
    $db->query(
        'UPDATE cg_shifts SET caregiver_id = ?, start_dt = ?, end_dt = ? WHERE id = ?',
        [$caregiver_id, $start_dt, $end_dt, $id]
    );
}

function cg_deleteShift($id) {
    global $db;
    // Cascading cleanup: delete attachments on disk + DB rows + notes
    $atts = $db->query('SELECT id, shift_id, filename FROM cg_shift_attachments WHERE shift_id = ?', [$id])->results();
    global $abs_us_root, $us_url_root;
    $root = $abs_us_root . $us_url_root . 'usersc/uploads/cg_shifts';
    foreach ($atts as $a) {
        $p = $root . '/' . (int)$a->shift_id . '/' . basename($a->filename);
        if (is_file($p)) @unlink($p);
    }
    $db->query('DELETE FROM cg_shift_attachments WHERE shift_id = ?', [$id]);
    $db->query('DELETE FROM cg_shift_notes       WHERE shift_id = ?', [$id]);
    $db->query('DELETE FROM cg_shifts            WHERE id       = ?', [$id]);
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
            $rows[] = [
                'kind'     => 'shift',
                'start_ts' => $a,
                'end_ts'   => $b,
                'label'    => $s->caregiver_name,
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
