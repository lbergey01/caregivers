<?php
/**
 * Caregivers SMS settings seeder.
 *
 * Populates cg_settings rows used by usersc/includes/sms.php with values
 * borrowed from the VBS install (Android SMS gateway primary + VoIP.ms backup).
 *
 * Idempotent: rows that already contain a non-empty value are preserved, so
 * once an admin edits a credential via the settings UI, this seeder will not
 * clobber it on subsequent runs / re-deploys.
 *
 * Called once per request from cg_init.php.
 */

if (!function_exists('cg_seed_sms_settings')) {

function cg_seed_sms_settings() {
    global $db;
    static $checked = false;
    if ($checked) return;
    $checked = true;

    if (!$db->tableExists('cg_settings')) return;

    $seeds = [
        // Default to the local Android sms_gateway; voip.ms creds kept as backup.
        'sms_provider'     => 'private',

        // VoIP.ms (fallback provider — sourced from vbs_tmc.config)
        'sms_user_id'      => 'office@towamencinmennonite.org',
        'sms_pass'         => 'CvVzU9#3e7SiNFsr',
        'sms_did'          => '2153682450',

        // Local Android SMS gateway (primary)
        'sms_private_ip'   => '100.105.147.112',
        'sms_private_port' => '8080',
        'sms_private_user' => 'sms',
        'sms_private_pass' => 'GotMilk#01',
    ];

    foreach ($seeds as $k => $v) {
        // Only fills empty/null values; preserves anything an admin has set.
        $db->query(
            "INSERT INTO cg_settings (skey, sval) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE sval = IF(sval = '' OR sval IS NULL, VALUES(sval), sval)",
            [$k, $v]
        );
    }

    // One-time migration: flip the legacy default 'voipms' to 'private'. Any
    // other user-chosen value (e.g. admin re-selected 'voipms' from the UI)
    // is left alone — this only catches the stale install default.
    $db->query(
        "UPDATE cg_settings SET sval='private' WHERE skey='sms_provider' AND sval='voipms'"
    );
}

}
