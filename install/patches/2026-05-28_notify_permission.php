<?php
/**
 * Notify permission (2026-05-28)
 *
 * Seeds a "Notify" permission. Users who hold this permission receive an SMS
 * when a caregiver clicks "Save & Notify" on a shift note. Membership is
 * managed in the regular UserSpice permissions UI — assign it to whichever
 * managers/family members should be alerted.
 *
 * Idempotent. Auto-invoked from cg_init.php.
 */

if (!function_exists('cg_patch_2026_05_28_notify_permission')) {

function cg_patch_2026_05_28_notify_permission() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;

    $db->query(
        "INSERT INTO permissions (name, descrip)
         SELECT 'Notify', 'Receives an SMS when a caregiver posts a shift note via Save & Notify'
         WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'Notify')"
    );
}

} // function_exists guard
