<?php
// Compressed day list — server-rendered row list for the per-day toggle in week view.
// GET ?client_id=&date=YYYY-MM-DD
// Returns [{kind:'shift'|'gap', start_hm:'HH:MM', end_hm:'HH:MM', label, color, shift_id?}]

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

header('Content-Type: application/json');

if (!$user->isLoggedIn()) { http_response_code(401); echo '[]'; exit; }

$client_id = (int)($_GET['client_id'] ?? cg_defaultClientId());
$date = $_GET['date'] ?? date('Y-m-d');
$day_start = strtotime($date . ' 00:00:00');
$day_end   = strtotime('+1 day', $day_start);

$shifts = cg_getShifts($client_id,
    date('Y-m-d H:i:s', $day_start),
    date('Y-m-d H:i:s', $day_end));

$rows = cg_dayList($shifts, $day_start, $day_end);
foreach ($rows as &$r) {
    $r['start_hm'] = date('g:i A', $r['start_ts']);
    $r['end_hm']   = date('g:i A', $r['end_ts']);
    unset($r['start_ts'], $r['end_ts']);
}
echo json_encode($rows);
