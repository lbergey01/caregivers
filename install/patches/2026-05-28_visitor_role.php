<?php
/**
 * Visitor role (2026-05-28)
 *
 * Adds a "Visitor" permission and a `role` column on cg_caregivers so family
 * members (grandkids, friends) can coordinate visits via the calendar without
 * seeing private shift notes or being able to edit other caregivers' shifts.
 *
 * `role` is VARCHAR (not ENUM) so future roles like 'visitor_clinical' (a
 * grandchild RN/CNA who should see clinical notes) can be added by inserting
 * a new value plus seeding a matching permission — no schema migration.
 *
 * Idempotent. Auto-invoked from cg_init.php.
 */

if (!function_exists('cg_patch_2026_05_28_visitor_role')) {

function cg_patch_2026_05_28_visitor_role() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;

    // --- Seed Visitor permission. Idempotent via WHERE NOT EXISTS. ---
    $db->query(
        "INSERT INTO permissions (name, descrip)
         SELECT 'Visitor', 'Sees calendar and own notes only; can add/edit only own shifts'
         WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'Visitor')"
    );

    // --- Add role column to cg_caregivers. ---
    $col = $db->query("SHOW COLUMNS FROM cg_caregivers LIKE 'role'")->results();
    if (empty($col)) {
        $db->query("ALTER TABLE cg_caregivers
                    ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'caregiver' AFTER user_id");
    }
}

} // function_exists guard
