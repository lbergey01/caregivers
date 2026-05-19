<?php
// Shift attachments API.
//   GET    ?action=list&shift_id=N            JSON of attachments for a shift (auth required)
//   GET    ?action=get&id=N                   stream the file inline (auth + permission)
//   POST   action=upload  multipart: file, shift_id          200 -> {ok:true, id, filename, mime}
//   POST   action=delete  id                                 200 -> {ok:true}

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

function jerr($code, $msg) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $msg]);
    exit;
}

if (!$user->isLoggedIn()) jerr(401, 'Login required.');

global $db;

$UPLOAD_ROOT = $abs_us_root . $us_url_root . 'usersc/uploads/cg_shifts';
$MAX_BYTES   = 15 * 1024 * 1024;  // 15 MB per file
$ALLOWED_MIME = [
    'image/jpeg' => 'jpg', 'image/png'  => 'png', 'image/gif' => 'gif',
    'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif',
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
];

function can_touch_shift($shift_id) {
    global $db;
    $s = $db->query('SELECT * FROM cg_shifts WHERE id = ?', [$shift_id])->first();
    if (!$s) return [false, null];
    return [cg_canEditShift($s), $s];
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    $shift_id = (int)$_GET['shift_id'];
    if (!$shift_id) jerr(400, 'shift_id required.');
    // Anyone logged in can list (they can see all shifts on the calendar).
    $rows = $db->query(
        'SELECT id, orig_name, mime, size_bytes, uploaded_at FROM cg_shift_attachments
          WHERE shift_id = ? ORDER BY uploaded_at',
        [$shift_id]
    )->results();
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

if ($method === 'GET' && $action === 'get') {
    $id = (int)$_GET['id'];
    $row = $db->query('SELECT a.*, s.id AS sid FROM cg_shift_attachments a
                       JOIN cg_shifts s ON s.id = a.shift_id
                       WHERE a.id = ?', [$id])->first();
    if (!$row) jerr(404, 'Not found.');
    // Any logged-in user can view an attachment they can see in the calendar.
    $path = $UPLOAD_ROOT . '/' . (int)$row->shift_id . '/' . basename($row->filename);
    if (!is_file($path)) jerr(404, 'File missing.');
    header('Content-Type: ' . $row->mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . rawurlencode($row->orig_name) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($method === 'POST' && $action === 'upload') {
    $note_id  = isset($_POST['note_id']) && $_POST['note_id'] !== '' ? (int)$_POST['note_id'] : null;
    $shift_id = (int)($_POST['shift_id'] ?? 0);

    if ($note_id) {
        $note = cg_getNote($note_id);
        if (!$note) jerr(404, 'Note not found.');
        if (!cg_canEditNote($note)) jerr(403, 'Not allowed to attach to this note.');
        $shift_id = (int)$note->shift_id;
    } else {
        if (!$shift_id) jerr(400, 'shift_id or note_id required.');
        list($ok, $shift) = can_touch_shift($shift_id);
        if (!$shift) jerr(404, 'Shift not found.');
        if (!$ok)    jerr(403, 'Not allowed to attach to this shift.');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jerr(400, 'No file or upload error.');
    }
    $f = $_FILES['file'];
    if ($f['size'] > $MAX_BYTES) jerr(413, 'File too large (max 15 MB).');

    // Determine mime by content sniff, not by client header.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']);
    if (!isset($ALLOWED_MIME[$mime])) jerr(415, 'Unsupported file type: ' . $mime);

    $ext = $ALLOWED_MIME[$mime];
    $dir = $UPLOAD_ROOT . '/' . $shift_id;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $stored_name = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = $dir . '/' . $stored_name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) jerr(500, 'Move failed.');

    $orig = substr(preg_replace('/[\r\n\t]+/', ' ', $f['name']), 0, 240);
    $db->query(
        'INSERT INTO cg_shift_attachments (shift_id, note_id, filename, orig_name, mime, size_bytes, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$shift_id, $note_id, $stored_name, $orig, $mime, (int)$f['size'], $user->data()->id]
    );
    $att_id = $db->lastId();
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true, 'id' => $att_id,
        'orig_name' => $orig, 'mime' => $mime, 'size_bytes' => (int)$f['size']
    ]);
    exit;
}

if ($method === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $row = $db->query('SELECT * FROM cg_shift_attachments WHERE id = ?', [$id])->first();
    if (!$row) jerr(404, 'Not found.');

    // Permission: if attached to a note, follow the note's edit policy;
    // otherwise (legacy shift-level attachments) follow the shift policy.
    $allowed = false;
    if ($row->note_id) {
        $note = cg_getNote($row->note_id);
        $allowed = $note && cg_canEditNote($note);
    } else {
        list($allowed, $shift) = can_touch_shift($row->shift_id);
    }
    if (!$allowed) jerr(403, 'Not allowed.');

    $path = $UPLOAD_ROOT . '/' . (int)$row->shift_id . '/' . basename($row->filename);
    if (is_file($path)) @unlink($path);
    $db->query('DELETE FROM cg_shift_attachments WHERE id = ?', [$id]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

jerr(400, 'Unknown action.');
