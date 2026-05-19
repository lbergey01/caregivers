<?php
// Coverage summary API — used by month view to color each day cell.
// GET ?client_id=&from=YYYY-MM-DD&to=YYYY-MM-DD
// Returns { "YYYY-MM-DD": "full" | "partial" | "empty", ... }

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

header('Content-Type: application/json');

if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

$client_id = (int)($_GET['client_id'] ?? cg_defaultClientId());
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
if (!$client_id || !$from || !$to) {
    http_response_code(400);
    echo json_encode(['error' => 'client_id, from, to required.']);
    exit;
}

$from_ts = strtotime($from . ' 00:00:00');
$to_ts   = strtotime($to   . ' 00:00:00');
$shifts = cg_getShifts($client_id, date('Y-m-d H:i:s', $from_ts), date('Y-m-d H:i:s', $to_ts));

$out = [];
$cursor = $from_ts;
while ($cursor < $to_ts) {
    $day_end = strtotime('+1 day', $cursor);
    $out[date('Y-m-d', $cursor)] = cg_dayCoverageStatus($shifts, $cursor, $day_end);
    $cursor = $day_end;
}

echo json_encode($out);
