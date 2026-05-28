<?php
// Shift notes API.
//   GET    ?action=list&shift_id=N      -> [{id, body, author_name, created_at, edited_at, edited_by_name, can_edit, can_delete, attachments:[...]}, ...]
//   POST   action=create  shift_id, body
//   POST   action=update  id, body                 (author or admin)
//   POST   action=delete  id                       (admin only)

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

header('Content-Type: application/json');

function jerr($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if (!$user->isLoggedIn()) jerr(401, 'Login required.');

global $db;
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

if ($method === 'GET' && $action === 'recent') {
    // Time-windowed feed of notes across all shifts for a client.
    //   ?hours=N           rolling window, default 24
    //   ?from=...&to=...   explicit MySQL DATETIME bounds (admin only — caregivers are capped at 24h rolling)
    //   ?caregiver_id=N    optional filter (by shift's assigned caregiver)
    $client_id = (int)($_GET['client_id'] ?? cg_defaultClientId());
    $is_admin  = cg_isAdmin();

    $where  = ['s.client_id = ?'];
    $params = [$client_id];

    if ($is_admin && !empty($_GET['from']) && !empty($_GET['to'])) {
        $where[] = 'n.created_at BETWEEN ? AND ?';
        $params[] = $_GET['from'];
        $params[] = $_GET['to'];
    } else {
        // Default window: 7 days. Non-admin cap matches the default so the
        // unprivileged history view still loads the full week.
        $hours = max(1, (int)($_GET['hours'] ?? 168));
        if (!$is_admin) $hours = min($hours, 168);
        $since = date('Y-m-d H:i:s', time() - $hours * 3600);
        $where[] = 'n.created_at >= ?';
        $params[] = $since;
    }

    if (!empty($_GET['caregiver_id'])) {
        $where[] = 's.caregiver_id = ?';
        $params[] = (int)$_GET['caregiver_id'];
    }

    $sql = 'SELECT n.*,
                   s.start_dt   AS shift_start, s.end_dt AS shift_end,
                   sc.id        AS shift_caregiver_id,
                   sc.name      AS shift_caregiver_name,
                   sc.color     AS shift_caregiver_color,
                   an.name      AS author_name,
                   u.username   AS author_username
              FROM cg_shift_notes n
              JOIN cg_shifts s     ON s.id  = n.shift_id
              JOIN cg_caregivers sc ON sc.id = s.caregiver_id
              LEFT JOIN cg_caregivers an ON an.id = n.author_caregiver_id
              LEFT JOIN users u          ON u.id  = n.author_user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT 500';
    $notes = $db->query($sql, $params)->results();

    if (!$notes) { echo '[]'; exit; }

    $ids = array_map(fn($n) => (int)$n->id, $notes);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $atts = $db->query(
        "SELECT id, note_id, orig_name, mime, size_bytes
           FROM cg_shift_attachments
          WHERE note_id IN ($ph)
          ORDER BY uploaded_at ASC",
        $ids
    )->results();
    $byNote = [];
    foreach ($atts as $a) $byNote[(int)$a->note_id][] = $a;

    $out = [];
    foreach ($notes as $n) {
        $out[] = [
            'id'             => (int)$n->id,
            'shift_id'       => (int)$n->shift_id,
            'shift_start'    => $n->shift_start,
            'shift_end'      => $n->shift_end,
            'shift_caregiver_name'  => $n->shift_caregiver_name,
            'shift_caregiver_color' => $n->shift_caregiver_color,
            'body'           => $n->body,
            'author_name'    => $n->author_name ?: ($n->author_username ?: 'Unknown'),
            'created_at'     => $n->created_at,
            'edited_at'      => $n->edited_at,
            'can_edit'       => cg_canEditNote($n),
            'can_delete'     => cg_canDeleteNote($n),
            'attachments'    => $byNote[(int)$n->id] ?? [],
        ];
    }
    echo json_encode($out);
    exit;
}

if ($method === 'GET' && $action === 'list') {
    $shift_id = (int)($_GET['shift_id'] ?? 0);
    if (!$shift_id) jerr(400, 'shift_id required.');

    $notes = cg_listNotes($shift_id);
    if (!$notes) { echo '[]'; exit; }

    // Pull all attachments for these notes in one query
    $ids = array_map(fn($n) => (int)$n->id, $notes);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $atts = $db->query(
        "SELECT id, note_id, orig_name, mime, size_bytes
           FROM cg_shift_attachments
          WHERE note_id IN ($placeholders)
          ORDER BY uploaded_at ASC",
        $ids
    )->results();
    $byNote = [];
    foreach ($atts as $a) $byNote[(int)$a->note_id][] = $a;

    $out = [];
    foreach ($notes as $n) {
        $out[] = [
            'id'             => (int)$n->id,
            'body'           => $n->body,
            'author_name'    => $n->author_name ?: ($n->author_username ?: 'Unknown'),
            'created_at'     => $n->created_at,
            'edited_at'      => $n->edited_at,
            'can_edit'       => cg_canEditNote($n),
            'can_delete'     => cg_canDeleteNote($n),
            'attachments'    => $byNote[(int)$n->id] ?? [],
        ];
    }
    echo json_encode($out);
    exit;
}

if ($method !== 'POST') jerr(405, 'Method not allowed.');

$me_cg = cg_currentCaregiver();
$is_admin = cg_isAdmin();
if (!$is_admin && !$me_cg) jerr(403, 'You are not linked to a caregiver record.');

if ($action === 'create') {
    $shift_id = (int)($_POST['shift_id'] ?? 0);
    $body     = $_POST['body'] ?? '';
    $shift    = cg_getShift($shift_id);
    if (!$shift) jerr(404, 'Shift not found.');

    // Caregivers can add notes to shifts they own; admins can add to any.
    if (!$is_admin && (int)$shift->caregiver_id !== (int)$me_cg->id) {
        jerr(403, 'You can only add notes to your own shifts.');
    }

    try {
        $author_cg_id = $me_cg ? (int)$me_cg->id : null;
        // If admin who isn't a caregiver, fall back to the shift's caregiver id is wrong — leave as NULL.
        $id = cg_createNote($shift_id, $body, $user->data()->id, $author_cg_id);
        echo json_encode(['ok' => true, 'id' => $id]);
    } catch (Throwable $e) {
        jerr(400, $e->getMessage());
    }
    exit;
}

if ($action === 'update') {
    $id   = (int)($_POST['id'] ?? 0);
    $body = $_POST['body'] ?? '';
    $note = cg_getNote($id);
    if (!$note) jerr(404, 'Note not found.');
    if (!cg_canEditNote($note)) jerr(403, 'Not allowed to edit this note.');
    try {
        cg_updateNote($id, $body, $user->data()->id);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        jerr(400, $e->getMessage());
    }
    exit;
}

if ($action === 'delete') {
    $id   = (int)($_POST['id'] ?? 0);
    $note = cg_getNote($id);
    if (!$note) jerr(404, 'Note not found.');
    if (!cg_canDeleteNote($note)) jerr(403, 'Only admins can delete notes.');
    cg_deleteNote($id);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'notify') {
    // SMS-blast managers (anyone holding the "Notify" permission) that a note
    // was just posted on this shift. The caller is expected to have already
    // POST-ed the note via action=create; this endpoint only sends the alert.
    $shift_id = (int)($_POST['shift_id'] ?? 0);
    if (!$shift_id) jerr(400, 'shift_id required.');
    $shift = cg_getShift($shift_id);
    if (!$shift) jerr(404, 'Shift not found.');

    // Same gate as posting a note on this shift.
    if (!$is_admin && (!$me_cg || (int)$shift->caregiver_id !== (int)$me_cg->id)) {
        jerr(403, 'Not allowed.');
    }

    require_once $abs_us_root . $us_url_root . 'usersc/includes/sms.php';

    // Anyone with the Notify permission AND a linked, active caregiver row.
    // Excludes the current user — no point texting yourself.
    $recipients = $db->query(
        "SELECT c.id AS caregiver_id, c.user_id, c.name AS caregivername, c.phone, c.email
           FROM permissions p
           JOIN user_permission_matches m ON m.permission_id = p.id
           JOIN cg_caregivers c          ON c.user_id = m.user_id
          WHERE p.name = 'Notify' AND c.active = 1 AND c.user_id <> ?",
        [(int)$user->data()->id]
    )->results();

    // Absolute link back to the shift-log history page.
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $link  = $proto . $_SERVER['HTTP_HOST'] . $us_url_root . 'cg/history.php';

    $sender_name = $me_cg ? $me_cg->name
                : trim(($user->data()->fname ?? '') . ' ' . ($user->data()->lname ?? ''));
    if ($sender_name === '') $sender_name = $user->data()->username ?? 'Someone';
    $when = date('M j g:ia', strtotime($shift->start_dt));
    $msg  = "{$sender_name} posted a shift note ({$when}). View: {$link}";

    $sent = 0; $failed = []; $skipped = [];
    foreach ($recipients as $r) {
        $phone = preg_replace('/\D/', '', (string)$r->phone);
        if ($phone === '') { $skipped[] = $r->caregivername; continue; }
        try {
            cg_sendSMS($r->phone, $msg);
            $sent++;
        } catch (Throwable $e) {
            $failed[] = $r->caregivername . ': ' . $e->getMessage();
        }
    }
    echo json_encode([
        'ok'         => true,
        'recipients' => count($recipients),
        'sent'       => $sent,
        'failed'     => $failed,
        'skipped'    => $skipped,
    ]);
    exit;
}

jerr(400, 'Unknown action.');
