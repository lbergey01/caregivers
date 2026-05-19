<?php
/**
 * pwsms — Passwordless SMS auth library
 * IP Access Control List (whitelist + blacklist)
 *
 * Table-backed anti-abuse counters. Limits (ip_max) live in
 * usersc/includes/rate_limits.php so the threshold can be edited without
 * DB changes — bumping ip_max immediately un-blocks any IP whose
 * failure_count is below the new value.
 *
 * Semantics:
 *   - Failure -> INSERT or atomic increment of failure_count
 *   - Success -> failure_count reset to 0 (auto-populated rows only)
 *   - Blocked when failure_count >= rate_limits[$action]['ip_max']
 *   - Whitelisted IPs are never recorded and never blocked.
 */

if (!defined('PWSMS_ACL_LOADED')) {
    define('PWSMS_ACL_LOADED', true);
}

/**
 * Resolve the request IP using RateLimit's proxy-aware helper if loaded.
 */
function pwsms_get_ip() {
    if (class_exists('RateLimit')) {
        try {
            $rl = new RateLimit();
            $ip = $rl->getRealIP();
            if ($ip) return $ip;
        } catch (Exception $e) { /* fall through */ }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Check whether $ip matches any whitelist entry. Supports single IPv4 and CIDR.
 * IPv6 is treated as not-whitelisted (CIDR math here is IPv4-only).
 */
function pwsms_is_whitelisted($ip = null) {
    global $db;
    pwsms_ensure_tables();

    if ($ip === null) $ip = pwsms_get_ip();
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;

    $ipLong = ip2long($ip);
    if ($ipLong === false) return false;

    $rows = $db->query("SELECT ip_address FROM pwsms_ip_whitelist")->results();
    foreach ($rows as $row) {
        $entry = trim($row->ip_address);
        if ($entry === '') continue;

        if (strpos($entry, '/') !== false) {
            list($subnet, $mask) = explode('/', $entry, 2);
            $mask = (int)$mask;
            if ($mask < 0 || $mask > 32) continue;
            if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
            $subnetLong = ip2long($subnet);
            if ($subnetLong === false) continue;
            $maskLong = $mask === 0 ? 0 : ~((1 << (32 - $mask)) - 1);
            if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                return true;
            }
        } elseif ($ip === $entry) {
            return true;
        }
    }
    return false;
}

/**
 * Returns true if this IP's failure_count for $action has reached the
 * configured ip_max in rate_limits.php.
 */
function pwsms_is_blacklisted($action, $ip = null) {
    global $db, $rateLimits;
    pwsms_ensure_tables();

    if ($ip === null) $ip = pwsms_get_ip();
    if (!$ip) return false;
    if (pwsms_is_whitelisted($ip)) return false;

    $ipMax = (int)($rateLimits[$action]['ip_max'] ?? 0);
    if ($ipMax <= 0) return false; // no limit configured = never blocked

    $row = $db->query(
        "SELECT failure_count FROM pwsms_ip_blacklist WHERE ip_address = ? AND action = ?",
        [$ip, $action]
    )->first();

    return $row && (int)$row->failure_count >= $ipMax;
}

/**
 * Atomic increment (or insert) of the failure counter for $ip + $action.
 */
function pwsms_record_failure($action, $ip = null) {
    global $db;
    pwsms_ensure_tables();

    if ($ip === null) $ip = pwsms_get_ip();
    if (!$ip) return false;
    if (pwsms_is_whitelisted($ip)) return false;

    $db->query(
        "INSERT INTO pwsms_ip_blacklist (ip_address, action, failure_count, first_failure_at, last_failure_at)
         VALUES (?, ?, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE failure_count = failure_count + 1, last_failure_at = NOW()",
        [$ip, $action]
    );
    return true;
}

/**
 * Reset the failure counter on a successful attempt. Manual blacklist entries
 * (description != NULL) are NOT reset — they represent admin decisions.
 */
function pwsms_clear_failures($action, $ip = null) {
    global $db;
    pwsms_ensure_tables();

    if ($ip === null) $ip = pwsms_get_ip();
    if (!$ip) return false;

    $db->query(
        "UPDATE pwsms_ip_blacklist
         SET failure_count = 0
         WHERE ip_address = ? AND action = ? AND description IS NULL",
        [$ip, $action]
    );
    return true;
}
