<?php
/**
 * Caregiver availability (2026-05-19)
 *
 * Adds the supply-side schedule layer: which times each caregiver is
 * `preferred`, `available`, or `unavailable` to work. Anything not covered
 * by a row is treated as `unknown` (no obligation to fill in the whole week).
 *
 *   cg_caregiver_availability           recurring weekly pattern
 *   cg_caregiver_availability_exception date-specific overrides (vacations,
 *                                       one-off pickups)
 *
 * Ranges are stored as (day_of_week, start_time, end_time) or
 * (specific_date, start_time, end_time) — not enumerated 30-min cells — so
 * the table stays small. Overnight blocks split into two rows so each row
 * stays inside a single day.
 *
 * Idempotent. Auto-invoked from cg_init.php.
 */

if (!function_exists('cg_patch_2026_05_19_availability')) {

function cg_patch_2026_05_19_availability() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;

    if (!$db->tableExists('cg_caregiver_availability')) {
        $db->query(
            "CREATE TABLE cg_caregiver_availability (
                id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                caregiver_id INT NOT NULL,
                day_of_week  TINYINT NOT NULL,
                start_time   TIME NOT NULL,
                end_time     TIME NOT NULL,
                status       ENUM('preferred','available','unavailable') NOT NULL DEFAULT 'available',
                notes        VARCHAR(255) NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_cg_av_caregiver (caregiver_id, day_of_week)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    if (!$db->tableExists('cg_caregiver_availability_exception')) {
        $db->query(
            "CREATE TABLE cg_caregiver_availability_exception (
                id            INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                caregiver_id  INT NOT NULL,
                specific_date DATE NOT NULL,
                start_time    TIME NOT NULL,
                end_time      TIME NOT NULL,
                status        ENUM('preferred','available','unavailable') NOT NULL,
                reason        VARCHAR(255) NULL,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_cg_av_exc_caregiver_date (caregiver_id, specific_date)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

} // function_exists guard
