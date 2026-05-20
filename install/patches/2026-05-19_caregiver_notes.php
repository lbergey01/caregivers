<?php
/**
 * Caregiver notes (2026-05-19)
 *
 * Adds a free-form `notes` TEXT column to cg_caregivers — used for things like
 * "dates they can work", restrictions, or any other persistent caregiver note.
 *
 * Idempotent. Auto-invoked from cg_init.php.
 */

if (!function_exists('cg_patch_2026_05_19_caregiver_notes')) {

function cg_patch_2026_05_19_caregiver_notes() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;

    $has = false;
    foreach ($db->query('SHOW COLUMNS FROM cg_caregivers')->results() as $c) {
        if ($c->Field === 'notes') { $has = true; break; }
    }
    if (!$has) {
        $db->query('ALTER TABLE cg_caregivers ADD COLUMN notes TEXT NULL AFTER diff_hol_add');
    }
}

} // function_exists guard
