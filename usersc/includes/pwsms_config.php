<?php
/**
 * pwsms — Passwordless SMS auth library
 * HOST CONFIG (caregivers integration)
 *
 * This is the ONLY file a host application customizes. Everything in
 * pwsms_auth.php / pwsms_acl.php / pwsms_error_pages.php stays generic.
 *
 * To port this library to another app, rewrite the three closures below
 * (phone_lookup, sms_send, on_auth_success) and the branding strings.
 */

if (!function_exists('pwsms_config')) {

/**
 * Returns the host config array. First call builds it; subsequent calls
 * return the cached copy. Library code reads values via pwsms_cfg($key).
 */
function pwsms_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $cfg = [

        /* ---------- Branding ---------- */
        'site_name'         => '24/7 Care Scheduling',
        'login_page_path'   => 'secure_login.php',   // relative to $us_url_root
        'landing_page'      => 'cg/index.php',       // where to drop user after auth

        /* ---------- Token / session lifetimes ---------- */
        'sms_expiry_minutes' => 5,
        'session_days'       => 120,

        /* ---------- Host-specific hooks ----------
         * These three closures are the only thing that varies per host app.
         */

        // Given a normalized 10-digit phone, return a user_id (int) or null.
        // Receives the digits-only phone (no formatting, no leading 1).
        //
        // Strips non-digits from the DB column on the fly so any storage format
        // matches: "5555551234", "555-555-1234", "(555) 555-1234", "+1 555-555-1234".
        // Also accepts DB rows that include a leading 1 country code.
        'phone_lookup' => function ($phone) {
            global $db;
            $row = $db->query(
                "SELECT user_id FROM cg_caregivers
                 WHERE REGEXP_REPLACE(phone, '[^0-9]', '') IN (?, ?)
                   AND active = 1",
                [$phone, '1' . $phone]
            )->first();
            return $row && $row->user_id ? (int)$row->user_id : null;
        },

        // Send the SMS. Receives normalized phone + message text.
        // Throws on failure; return value is logged but not inspected.
        'sms_send' => function ($phone, $message) {
            return cg_sendSMS($phone, $message);
        },

        // Called once after a token+device validates. Use it to start a
        // host-level session. Receives (user_id, phone).
        'on_auth_success' => function ($user_id, $phone) {
            // Start a UserSpice session for this user — every $user->isLoggedIn()
            // check downstream just works.
            Session::put(Config::get('session/session_name'), (int)$user_id);
        },
    ];

    return $cfg;
}

function pwsms_cfg($key, $default = null) {
    $cfg = pwsms_config();
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

/**
 * Normalize a phone string to digits-only, stripped of an optional US country
 * code. Returns null if it doesn't look like a 10-digit number.
 */
function pwsms_normalize_phone($raw) {
    $digits = preg_replace('/\D/', '', (string)$raw);
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    return strlen($digits) === 10 ? $digits : null;
}

}
