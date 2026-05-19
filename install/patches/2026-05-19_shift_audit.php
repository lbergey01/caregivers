<?php
/**
 * Shift audit log (2026-05-19)
 *
 * Records every insert/update/delete on cg_shifts with actor + before/after
 * snapshots, so admins can see who scheduled/changed/cancelled a shift and when.
 *
 * Idempotent. Auto-invoked from cg_init.php.
 */

if (!function_exists('cg_patch_2026_05_19_shift_audit')) {

function cg_patch_2026_05_19_shift_audit() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;

    if (!$db->tableExists('cg_shift_audit')) {
        $db->query(
            "CREATE TABLE cg_shift_audit (
                id                  INT(11)      NOT NULL AUTO_INCREMENT,
                shift_id            INT(11)      NULL,
                action              ENUM('insert','update','delete') NOT NULL,
                actor_user_id       INT(11)      NULL,
                actor_caregiver_id  INT(11)      NULL,
                actor_name          VARCHAR(120) NULL,
                before_json         TEXT         NULL,
                after_json          TEXT         NULL,
                created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_shift   (shift_id),
                KEY idx_created (created_at),
                KEY idx_actor   (actor_user_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

} // function_exists guard
