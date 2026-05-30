<?php
// Invite Visitor API — POST {name, phone}
//   Any logged-in user (admin, caregiver, visitor) can invite.
//   Creates a UserSpice user + cg_caregivers row (role=visitor, active=1) if
//   the phone is new, then sends an SMS login link. If the phone is already
//   on file we just re-send the link (the original invite may have been
//   ignored), repairing a missing user_id or inactive row in the process.
//
// Mirrors the well-trodden bulk-import path in admin_caregivers_import.php
// for the UserSpice-user + cg_caregivers + permission sync side, then reuses
// pwsms_generate_token + pwsms_cfg('sms_send') the same way secure_login.php
// does for the SMS itself.

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/sms.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/pwsms_auth.php';

header('Content-Type: application/json');

function jerr($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if (!$user->isLoggedIn())                jerr(401, 'Login required.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr(405, 'POST required.');

global $db;

$name  = trim((string)($_POST['name']  ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));

if ($name === '')                       jerr(400, 'Name is required.');
$phone10 = pwsms_normalize_phone($phone);
if ($phone10 === null)                  jerr(400, 'Phone must be 10 digits.');

$phone_fmt = substr($phone10, 0, 3) . '-' . substr($phone10, 3, 3) . '-' . substr($phone10, 6);

// Deterministic color from a small palette — same approach as the bulk import,
// so a re-invite of the same name keeps the same calendar chip color.
$palette = [
    '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#1abc9c',
    '#3498db', '#9b59b6', '#34495e', '#16a085', '#27ae60',
    '#2980b9', '#8e44ad', '#d35400', '#c0392b', '#7f8c8d',
];
$color = $palette[abs(crc32($name)) % count($palette)];

$parts = preg_split('/\s+/', $name, 2);
$fname = $parts[0] ?? '';
$lname = $parts[1] ?? '';
$email = $phone10 . '@noreply.invalid';   // generic — pwsms login is by phone

// Existing-row lookup by normalized phone (matches phone_lookup's regex so
// the post-invite SMS click will find the same row).
$existing = $db->query(
    "SELECT * FROM cg_caregivers
     WHERE REGEXP_REPLACE(phone, '[^0-9]', '') IN (?, ?)
     ORDER BY active DESC, id ASC LIMIT 1",
    [$phone10, '1' . $phone10]
)->first();

$cg_id   = null;
$user_id = null;
$role    = 'visitor';

if ($existing) {
    $cg_id   = (int)$existing->id;
    $user_id = $existing->user_id ? (int)$existing->user_id : null;
    // Don't downgrade an existing caregiver to visitor just because someone
    // mistyped them into the invite form — re-send the link under their
    // current role.
    $role    = $existing->role ?: 'visitor';
}

// If no linked UserSpice user yet, see if one exists by username = phone10
// (the bulk-import convention) and link to it; otherwise mint a fresh one.
if (!$user_id) {
    $u = $db->query('SELECT id FROM users WHERE username = ? LIMIT 1', [$phone10])->first();
    if ($u) {
        $user_id = (int)$u->id;
    } else {
        $pw = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 10]);
        $db->query(
            'INSERT INTO users
              (username, fname, lname, email, password, permissions, active,
               email_verified, language, created, join_date, oauth_tos_accepted)
             VALUES (?, ?, ?, ?, ?, 1, 1, 1, ?, ?, ?, 1)',
            [
                $phone10, $fname, $lname, $email, $pw,
                'en-US', date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
            ]
        );
        $user_id = (int)$db->lastId();
        $db->query('INSERT INTO user_permission_matches (user_id, permission_id) VALUES (?, 1)', [$user_id]);
    }
}

// Upsert the cg_caregivers row.
if ($cg_id) {
    $before = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$cg_id])->first();
    $db->query(
        'UPDATE cg_caregivers
            SET user_id = ?, active = 1
          WHERE id = ?',
        [$user_id, $cg_id]
    );
    $after = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$cg_id])->first();
    if (cg_caregiverSnapshot($before) !== cg_caregiverSnapshot($after)) {
        cg_logCaregiverAudit($cg_id, 'update', cg_caregiverSnapshot($before), cg_caregiverSnapshot($after));
    }
} else {
    $db->query(
        'INSERT INTO cg_caregivers (name, phone, email, user_id, role, color, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)',
        [$name, $phone_fmt, $email, $user_id, $role, $color]
    );
    $cg_id = (int)$db->lastId();
    $fresh = $db->query('SELECT * FROM cg_caregivers WHERE id = ?', [$cg_id])->first();
    cg_logCaregiverAudit($cg_id, 'insert', null, cg_caregiverSnapshot($fresh));
}

cg_syncRolePermission($user_id, $role);

// Mint the SMS login token and send it. Mirrors secure_login.php's send path.
try {
    $uniqueId = pwsms_generate_token($phone10, pwsms_cfg('landing_page', 'cg/index.php'));

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $secureLink = $protocol . $_SERVER['HTTP_HOST'] . $us_url_root . 'secure_login.php?ln=' . urlencode($uniqueId);

    $siteName = isset($settings->site_name) && $settings->site_name !== ''
        ? $settings->site_name
        : pwsms_cfg('site_name', 'the schedule');

    $inviterName = trim(($user->data()->fname ?? '') . ' ' . ($user->data()->lname ?? ''));
    if ($inviterName === '') $inviterName = $user->data()->username ?? 'A friend';

    $expMin = (int)pwsms_cfg('sms_expiry_minutes', 5);
    $msg = $inviterName . ' invited you to the ' . $siteName . ' visitor schedule: '
         . $secureLink . ' (Expires in ' . $expMin . ' min). Reply STOP to opt out.';

    $smsSend = pwsms_cfg('sms_send');
    if (!is_callable($smsSend)) throw new Exception('SMS sender not configured.');
    $smsSend($phone10, $msg);

    pwsms_log('visitor_invite_sent', $uniqueId, $phone10, [
        'cg_id'        => $cg_id,
        'user_id'      => $user_id,
        'inviter_uid'  => (int)$user->data()->id,
    ]);
} catch (Throwable $e) {
    // The visitor row + user account were saved; surface the SMS failure so
    // the inviter knows to try again or contact the admin.
    jerr(502, "Visitor saved, but SMS failed: " . $e->getMessage());
}

echo json_encode([
    'ok'      => true,
    'cg_id'   => $cg_id,
    'user_id' => $user_id,
    'name'    => $name,
    'phone'   => $phone_fmt,
    'message' => 'Invite sent to ' . $phone_fmt . '.',
]);
