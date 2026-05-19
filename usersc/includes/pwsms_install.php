<?php
/**
 * pwsms — Passwordless SMS auth library
 * Schema installer (idempotent). Auto-runs on first include of pwsms_auth.php.
 *
 * Tables created (host-portable, no app-specific names):
 *   page_access            - tokens, device-bound sessions
 *   pwsms_ip_whitelist     - trusted IPs (manual, optional)
 *   pwsms_ip_blacklist     - per-IP failure counters
 */

if (!function_exists('pwsms_ensure_tables')) {

function pwsms_ensure_tables() {
    global $db;
    static $checked = false;
    if ($checked) return;
    $checked = true;

    if (!$db->tableExists('page_access')) {
        $db->query("CREATE TABLE page_access (
            id INT(11) NOT NULL AUTO_INCREMENT,
            unique_id VARCHAR(50) NOT NULL,
            session_token VARCHAR(64) DEFAULT NULL,
            contact_info VARCHAR(255) NOT NULL,
            contact_type ENUM('email','phone') NOT NULL,
            first_access TIMESTAMP NULL DEFAULT NULL,
            last_access TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            device_type VARCHAR(20) DEFAULT NULL,
            operating_system VARCHAR(20) DEFAULT NULL,
            browser VARCHAR(20) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            access_count INT(11) DEFAULT 0,
            sms_expires_at TIMESTAMP NULL DEFAULT NULL,
            return_url TEXT DEFAULT NULL,
            allowed_pages LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(allowed_pages)),
            revoked_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_id (unique_id),
            KEY idx_page_access_unique_id (unique_id),
            KEY idx_page_access_session_token (session_token),
            KEY idx_page_access_active_expires (is_active, expires_at),
            KEY idx_page_access_session (unique_id, session_token, is_active, expires_at),
            KEY idx_page_access_token_lookup (session_token, is_active, expires_at),
            KEY idx_page_access_contact (contact_info, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    if (!$db->tableExists('pwsms_ip_whitelist')) {
        $db->query("CREATE TABLE pwsms_ip_whitelist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!$db->tableExists('pwsms_ip_blacklist')) {
        $db->query("CREATE TABLE pwsms_ip_blacklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            failure_count INT NOT NULL DEFAULT 0,
            first_failure_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_failure_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            description VARCHAR(255) NULL,
            UNIQUE KEY uk_ip_action (ip_address, action),
            INDEX idx_ip (ip_address),
            INDEX idx_last_failure (last_failure_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

}
