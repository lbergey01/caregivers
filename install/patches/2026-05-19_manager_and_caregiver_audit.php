<?php
/**
 * Manager permission + caregiver audit log (2026-05-19)
 *
 * Adds a "Manager" permission level (between Admin and Caregiver) and an
 * audit table that records add/change/delete on cg_caregivers.
 *
 * Idempotent. Auto-invoked from cg_init.php.
 */

if (!function_exists('cg_patch_2026_05_19_manager_and_caregiver_audit')) {

function cg_patch_2026_05_19_manager_and_caregiver_audit() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;

    // --- Seed Manager permission. Idempotent via WHERE NOT EXISTS. ---
    $db->query(
        "INSERT INTO permissions (name, descrip)
         SELECT 'Manager', 'Can add/edit caregivers, edit any shift, and access SMS settings'
         WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'Manager')"
    );

    // --- cg_caregiver_audit: who changed what on a caregiver row, and when. ---
    if (!$db->tableExists('cg_caregiver_audit')) {
        $db->query(
            "CREATE TABLE cg_caregiver_audit (
                id                  INT(11)      NOT NULL AUTO_INCREMENT,
                caregiver_id        INT(11)      NULL,
                action              ENUM('insert','update','delete') NOT NULL,
                actor_user_id       INT(11)      NULL,
                actor_caregiver_id  INT(11)      NULL,
                actor_name          VARCHAR(120) NULL,
                before_json         TEXT         NULL,
                after_json          TEXT         NULL,
                created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_cg      (caregiver_id),
                KEY idx_created (created_at),
                KEY idx_actor   (actor_user_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

} // function_exists guard
