<?php
// Shifts API.
//   GET    ?client_id=&from=ISO&to=ISO        list shifts in range (returns events + gap background events)
//   POST   action=create  + client_id, caregiver_id, start_dt, end_dt, [notes]
//   POST   action=update  + id, caregiver_id, start_dt, end_dt, [notes]
//   POST   action=delete  + id
// Responses are JSON.

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

header('Content-Type: application/json');

function jerr($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if (!$user->isLoggedIn()) jerr(401, 'Login required.');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $client_id = (int)($_GET['client_id'] ?? cg_defaultClientId());
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to']   ?? '';
    if (!$client_id || !$from || !$to) jerr(400, 'client_id, from, to required.');

    // FullCalendar sends ISO timestamps; convert to MySQL DATETIME.
    $from_mysql = date('Y-m-d H:i:s', strtotime($from));
    $to_mysql   = date('Y-m-d H:i:s', strtotime($to));

    $shifts = cg_getShifts($client_id, $from_mysql, $to_mysql);

    $me_cg = cg_currentCaregiver();
    $is_admin = cg_isAdmin();

    $events = [];
    foreach ($shifts as $s) {
        $can_edit = $is_admin || ($me_cg && (int)$s->caregiver_id === (int)$me_cg->id);
        $events[] = [
            'id'          => (int)$s->id,
            'title'       => $s->caregiver_name,
            'start'       => date('c', strtotime($s->start_dt)),
            'end'         => date('c', strtotime($s->end_dt)),
            'backgroundColor' => $s->caregiver_color,
            'borderColor'     => $s->caregiver_color,
            'extendedProps' => [
                'kind'         => 'shift',
                'caregiver_id' => (int)$s->caregiver_id,
                'can_edit'     => $can_edit,
            ],
        ];
    }

    // Add yellow gap background events, day by day.
    $cursor = strtotime(date('Y-m-d 00:00:00', strtotime($from)));
    $end_ts = strtotime($to);
    while ($cursor < $end_ts) {
        $day_end = strtotime('+1 day', $cursor);
        $gaps = cg_computeGaps($shifts, $cursor, $day_end);
        foreach ($gaps as $g) {
            $events[] = [
                'start'   => date('c', $g[0]),
                'end'     => date('c', $g[1]),
                'display' => 'background',
                'backgroundColor' => '#f6e58d',
                'extendedProps' => ['kind' => 'gap'],
            ];
        }
        $cursor = $day_end;
    }

    echo json_encode($events);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $me_cg  = cg_currentCaregiver();
    $is_admin = cg_isAdmin();

    if (!$is_admin && !$me_cg) jerr(403, 'You are not linked to a caregiver record.');

    if ($action === 'create') {
        $client_id    = (int)($_POST['client_id'] ?? cg_defaultClientId());
        $caregiver_id = (int)$_POST['caregiver_id'];
        $start_dt     = $_POST['start_dt'];
        $end_dt       = $_POST['end_dt'];

        if (!$is_admin && $caregiver_id !== (int)$me_cg->id) {
            jerr(403, 'Caregivers may only schedule themselves.');
        }
        try {
            $id = cg_createShift($client_id, $caregiver_id, $start_dt, $end_dt, $user->data()->id);
            echo json_encode(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) {
            jerr(400, $e->getMessage());
        }
        exit;
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $shift = cg_getShift($id);
        if (!$shift) jerr(404, 'Shift not found.');
        if (!cg_canEditShift($shift)) jerr(403, 'Not allowed.');

        $caregiver_id = (int)$_POST['caregiver_id'];
        if (!$is_admin && $caregiver_id !== (int)$me_cg->id) {
            jerr(403, 'Caregivers may only schedule themselves.');
        }
        try {
            cg_updateShift($id, $caregiver_id, $_POST['start_dt'], $_POST['end_dt']);
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            jerr(400, $e->getMessage());
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $shift = cg_getShift($id);
        if (!$shift) jerr(404, 'Shift not found.');
        if (!cg_canEditShift($shift)) jerr(403, 'Not allowed.');
        cg_deleteShift($id);
        echo json_encode(['ok' => true]);
        exit;
    }

    jerr(400, 'Unknown action.');
}

jerr(405, 'Method not allowed.');
