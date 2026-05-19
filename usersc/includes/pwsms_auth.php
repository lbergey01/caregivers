<?php
/**
 * pwsms — Passwordless SMS auth library
 *
 * Generic core. Host-specific behavior lives entirely in pwsms_config.php
 * (phone_lookup, sms_send, on_auth_success closures + branding strings).
 *
 * Public API:
 *   pwsms_require_auth($pageCode=null, $options=[])  Entry point on protected pages
 *   pwsms_generate_token($phone, $returnUrl=null, $pageCode=null, $options=[])
 *                                                    Mint a token (called by login form)
 *   pwsms_log($action, $uniqueId, $contact, $extra=[])  Audit-log helper
 *
 * Required globals: $db, $us_url_root (from UserSpice init.php)
 */

require_once __DIR__ . '/pwsms_install.php';
require_once __DIR__ . '/pwsms_config.php';
require_once __DIR__ . '/pwsms_acl.php';
require_once __DIR__ . '/pwsms_error_pages.php';

if (!defined('PWSMS_LOADED')) {
    define('PWSMS_LOADED', true);

    // Token in the URL — don't leak it to third parties via Referer.
    if (!headers_sent()) {
        header('Referrer-Policy: same-origin');
    }

    pwsms_ensure_tables();
}

/* ============================================================
 * Main entry point
 * ============================================================ */

/**
 * Call this from any page that needs SMS-gated access.
 *
 * Returns ['authorized' => true, 'user_data' => [...]] on success and starts a
 * host session via the on_auth_success hook. On failure, renders an error page
 * or redirects to the login form and exits.
 *
 * @param string|null $pageCode  Optional per-page permission code
 * @param array       $options   Override defaults (sms_expiry_minutes, session_days, etc.)
 */
function pwsms_require_auth($pageCode = null, $options = []) {
    $defaults = [
        'sms_expiry_minutes' => pwsms_cfg('sms_expiry_minutes', 5),
        'session_days'       => pwsms_cfg('session_days', 120),
        'login_page'         => pwsms_cfg('login_page_path', 'secure_login.php'),
    ];
    $options = array_merge($defaults, $options);

    // Accept 'ln' as short alias for 'uniqueid' (SMS-friendly).
    $uniqueId = $_GET['uniqueid'] ?? ($_GET['ln'] ?? '');

    if (!empty($uniqueId)) {
        return pwsms_handle_token_access($uniqueId, $pageCode, $options);
    }

    // No token in URL — fall through to existing-cookie path.
    $existingSession = pwsms_check_existing_session($pageCode);
    if ($existingSession['valid']) {
        return $existingSession;
    }

    pwsms_redirect_to_login($pageCode, $options);
}

/* ============================================================
 * Token validation flow
 * ============================================================ */

function pwsms_handle_token_access($uniqueId, $pageCode, $options) {
    global $db;

    // Revoke-access POST handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_access'])) {
        return pwsms_handle_revoke($uniqueId);
    }

    // Per-IP brute-force defense on token validation
    if (pwsms_is_blacklisted('auth_token_failure')) {
        pwsms_log('rate_limited', $uniqueId, 'unknown');
        pwsms_show_error('rate_limited');
    }

    $pageAccess = $db->query("SELECT * FROM page_access WHERE unique_id = ? AND is_active = 1", [$uniqueId])->first();

    if (!$pageAccess) {
        pwsms_record_failure('auth_token_failure');
        pwsms_log('auth_token_failed', $uniqueId, 'unknown', ['reason' => 'no_row_or_inactive']);
        return pwsms_handle_invalid_token($uniqueId);
    }

    if (!pwsms_has_page_access($pageAccess, $pageCode)) {
        pwsms_record_failure('auth_token_failure');
        pwsms_log('auth_token_failed', $uniqueId, $pageAccess->contact_info, ['reason' => 'insufficient_permissions']);
        return ['authorized' => false, 'reason' => 'insufficient_permissions'];
    }

    $expirationResult = pwsms_check_expiration($pageAccess);
    if (!$expirationResult['valid']) {
        pwsms_record_failure('auth_token_failure');
        pwsms_log('auth_token_failed', $uniqueId, $pageAccess->contact_info, ['reason' => 'expired']);
        return $expirationResult;
    }

    // First-time access on this token? Show device-registration prompt.
    if (empty($pageAccess->session_token)) {
        return pwsms_handle_device_registration($uniqueId, $pageAccess, $options);
    }

    // Subsequent access on this token — validate against the cookie.
    return pwsms_validate_existing_token_session($uniqueId, $pageAccess, $options);
}

function pwsms_handle_device_registration($uniqueId, $pageAccess, $options) {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_device'])) {
        $sessionToken = bin2hex(random_bytes(32));

        $deviceType = $_POST['device_type'] ?? 'Unknown';
        $operatingSystem = $_POST['operating_system'] ?? 'Unknown';
        $browser = $_POST['browser'] ?? 'Unknown';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $sessionExpiresAt = date('Y-m-d H:i:s', strtotime('+' . $options['session_days'] . ' days'));

        $db->query(
            "UPDATE page_access
             SET session_token = ?, first_access = NOW(), device_type = ?, operating_system = ?,
                 browser = ?, ip_address = ?, expires_at = ?
             WHERE unique_id = ?",
            [$sessionToken, $deviceType, $operatingSystem, $browser, $ipAddress, $sessionExpiresAt, $uniqueId]
        );

        pwsms_log('device_registered', $uniqueId, $pageAccess->contact_info, [
            'device_type' => $deviceType,
            'operating_system' => $operatingSystem,
            'browser' => $browser,
            'ip_address' => $ipAddress
        ]);

        $cookieData = [
            'token' => $sessionToken,
            'contact' => $pageAccess->contact_info,
            'contact_type' => $pageAccess->contact_type,
            'unique_id' => $uniqueId,
        ];
        pwsms_set_cookie($cookieData, $options['session_days']);

        // Redirect to clear the POST and let the GET branch validate the new cookie.
        header("Location: " . pwsms_current_url());
        exit;
    }

    include __DIR__ . '/pwsms_device_registration.php';
    exit;
}

function pwsms_validate_existing_token_session($uniqueId, $pageAccess, $options) {
    global $db;

    $cookieData = pwsms_get_cookie_data();
    $cookieToken = $cookieData ? ($cookieData['token'] ?? '') : '';

    if ($cookieToken !== $pageAccess->session_token || !$cookieData || ($cookieData['unique_id'] ?? '') !== $uniqueId) {
        pwsms_record_failure('auth_token_failure');
        pwsms_log('auth_token_failed', $uniqueId, $pageAccess->contact_info, ['reason' => 'session_mismatch']);
        pwsms_show_error('session_mismatch');
    }

    pwsms_slide_session($uniqueId, $cookieData, $options['session_days']);
    pwsms_clear_failures('auth_token_failure');
    pwsms_log('access_granted', $uniqueId, $pageAccess->contact_info);

    // Bridge to host session. Phone is the contact_info; ask host to start
    // whatever session it cares about.
    $hook = pwsms_cfg('on_auth_success');
    if (is_callable($hook)) {
        $userId = pwsms_resolve_user_id($pageAccess->contact_info);
        if ($userId !== null) {
            $hook($userId, $pageAccess->contact_info);
        }
    }

    return [
        'authorized' => true,
        'user_data' => [
            'contact_info' => $pageAccess->contact_info,
            'contact_type' => $pageAccess->contact_type,
            'unique_id' => $uniqueId,
            'device_info' => [
                'type' => $pageAccess->device_type,
                'os' => $pageAccess->operating_system,
                'browser' => $pageAccess->browser,
            ],
        ],
    ];
}

/**
 * No-token path: did the visitor arrive with a still-valid cookie alone?
 * If yes, redirect to the same URL with the uniqueid attached so the
 * normal token path takes over (and the on_auth_success hook fires).
 */
function pwsms_check_existing_session($pageCode) {
    global $db;

    $cookieData = pwsms_get_cookie_data();
    if (!$cookieData || empty($cookieData['token'])) {
        return ['valid' => false];
    }

    $validSession = $db->query(
        "SELECT * FROM page_access
         WHERE session_token = ? AND is_active = 1
           AND (expires_at IS NULL OR expires_at > NOW())",
        [$cookieData['token']]
    )->first();

    if (!$validSession || !pwsms_has_page_access($validSession, $pageCode)) {
        return ['valid' => false];
    }

    $currentUrl = pwsms_current_url();
    $separator = strpos($currentUrl, '?') !== false ? '&' : '?';
    header("Location: " . $currentUrl . $separator . "uniqueid=" . $validSession->unique_id);
    exit;
}

/* ============================================================
 * Helpers
 * ============================================================ */

function pwsms_has_page_access($pageAccess, $pageCode) {
    if ($pageCode === null) return true;
    if (empty($pageAccess->allowed_pages)) return true;

    $allowedPages = json_decode($pageAccess->allowed_pages, true) ?: explode(',', $pageAccess->allowed_pages);
    if (!in_array($pageCode, $allowedPages, true)) {
        pwsms_record_failure('auth_token_failure');
        pwsms_show_error('insufficient_permissions');
    }
    return true;
}

function pwsms_check_expiration($pageAccess) {
    $expirationTime = empty($pageAccess->session_token)
        ? $pageAccess->sms_expires_at
        : $pageAccess->expires_at;

    if ($expirationTime && strtotime($expirationTime) < time()) {
        pwsms_record_failure('auth_token_failure');
        pwsms_show_error('expired', [
            'is_registered' => !empty($pageAccess->session_token),
            'pageAccess' => $pageAccess,
        ]);
    }
    return ['valid' => true];
}

function pwsms_update_last_access($uniqueId) {
    global $db;
    try {
        $db->query(
            "UPDATE page_access SET last_access = NOW(), access_count = access_count + 1 WHERE unique_id = ?",
            [$uniqueId]
        );
    } catch (Exception $e) {
        error_log("pwsms: failed to update last_access: " . $e->getMessage());
    }
}

/**
 * Slide the session expiry forward and refresh the cookie's max-age.
 * Called on every successful authorization so an active session keeps
 * extending its 120-day window.
 */
function pwsms_slide_session($uniqueId, $cookieData, $sessionDays) {
    global $db;
    $newExpiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$sessionDays . ' days'));
    try {
        $db->query(
            "UPDATE page_access
             SET last_access = NOW(), access_count = access_count + 1, expires_at = ?
             WHERE unique_id = ?",
            [$newExpiresAt, $uniqueId]
        );
    } catch (Exception $e) {
        error_log("pwsms: slide_session UPDATE failed: " . $e->getMessage());
    }
    if ($cookieData) {
        pwsms_set_cookie($cookieData, $sessionDays);
    }
}

/**
 * Non-redirecting cookie revive. Call this on entry points where we want a
 * returning user with a still-valid pwsms cookie to be transparently logged
 * back in — no SMS link needed.
 *
 * Returns true if a session was revived (host hook fired).
 */
function pwsms_revive_from_cookie() {
    global $db;

    $cookieData = pwsms_get_cookie_data();
    if (!$cookieData || empty($cookieData['token'])) {
        return false;
    }

    $row = $db->query(
        "SELECT * FROM page_access
         WHERE session_token = ? AND is_active = 1
           AND (expires_at IS NULL OR expires_at > NOW())",
        [$cookieData['token']]
    )->first();

    if (!$row) {
        return false;
    }

    $hook = pwsms_cfg('on_auth_success');
    if (!is_callable($hook)) return false;

    $userId = pwsms_resolve_user_id($row->contact_info);
    if ($userId === null) {
        return false;
    }

    pwsms_slide_session($row->unique_id, $cookieData, pwsms_cfg('session_days', 120));
    pwsms_clear_failures('auth_token_failure');
    pwsms_log('access_granted', $row->unique_id, $row->contact_info, ['source' => 'cookie_revive']);

    $hook($userId, $row->contact_info);
    return true;
}

function pwsms_resolve_user_id($phone) {
    $hook = pwsms_cfg('phone_lookup');
    if (!is_callable($hook)) return null;
    $normalized = pwsms_normalize_phone($phone) ?: $phone;
    return $hook($normalized);
}

/* ---------- Cookie management ---------- */

function pwsms_get_cookie_data() {
    if (!isset($_COOKIE['secure_session_data'])) return null;
    $decoded = base64_decode($_COOKIE['secure_session_data']);
    return json_decode($decoded, true);
}

function pwsms_set_cookie($cookieData, $extensionDays = 120) {
    $cookieExpiry = time() + ($extensionDays * 86400);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('secure_session_data', base64_encode(json_encode($cookieData)), [
        'expires'  => $cookieExpiry,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* ---------- Misc ---------- */

function pwsms_current_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function pwsms_redirect_to_login($pageCode, $options) {
    global $us_url_root;
    $currentUrl = pwsms_current_url();
    $loginUrl = $us_url_root . $options['login_page'] . '?return=' . urlencode($currentUrl);
    if ($pageCode) {
        $loginUrl .= '&page=' . urlencode($pageCode);
    }
    header("Location: " . $loginUrl);
    exit;
}

/* ---------- Token mint (called by secure_login.php) ---------- */

function pwsms_generate_token($contactInfo, $returnUrl = null, $pageCode = null, $options = []) {
    global $db;

    $defaults = ['sms_expiry_minutes' => pwsms_cfg('sms_expiry_minutes', 5)];
    $options = array_merge($defaults, $options);

    do {
        $uniqueId = bin2hex(random_bytes(4));
        $exists = $db->query("SELECT id FROM page_access WHERE unique_id = ?", [$uniqueId])->first();
    } while ($exists);

    $smsExpiresAt = date('Y-m-d H:i:s', strtotime('+' . $options['sms_expiry_minutes'] . ' minutes'));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $allowedPages = $pageCode ? json_encode([$pageCode]) : null;

    $db->query(
        "INSERT INTO page_access (unique_id, contact_info, contact_type, expires_at, sms_expires_at, ip_address, return_url, allowed_pages)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$uniqueId, $contactInfo, 'phone', $smsExpiresAt, $smsExpiresAt, $ipAddress, $returnUrl, $allowedPages]
    );

    pwsms_log('sms_token_generated', $uniqueId, $contactInfo, [
        'return_url' => $returnUrl,
        'page_code' => $pageCode,
    ]);

    return $uniqueId;
}

/* ---------- Error / revoke handlers ---------- */

function pwsms_handle_revoke($uniqueId) {
    global $db;
    $pageAccess = $db->query("SELECT * FROM page_access WHERE unique_id = ?", [$uniqueId])->first();
    pwsms_log('access_revoked', $uniqueId, $pageAccess ? $pageAccess->contact_info : 'Unknown');

    $db->query("UPDATE page_access SET is_active = 0, session_token = NULL, revoked_at = NOW() WHERE unique_id = ?", [$uniqueId]);

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('secure_session_data', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    pwsms_show_success('access_revoked', 'Your secure access has been successfully revoked. This link and any saved sessions have been permanently disabled for security.');
}

function pwsms_handle_invalid_token($uniqueId) {
    global $db;
    $revoked = $db->query("SELECT * FROM page_access WHERE unique_id = ? AND is_active = 0", [$uniqueId])->first();
    if ($revoked) {
        pwsms_show_error('access_revoked');
    }
    pwsms_show_error('invalid_link');
}

/* ---------- Logging ---------- */

function pwsms_log($action, $uniqueId, $contactInfo, $extraData = []) {
    if (!function_exists('logger')) return;
    $logData = array_merge([
        'unique_id' => $uniqueId,
        'contact_info' => $contactInfo,
        'action' => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    ], $extraData);
    logger(1, "pwsms_" . $action, json_encode($logData));
}
