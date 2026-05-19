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

jerr(400, 'Unknown action.');
