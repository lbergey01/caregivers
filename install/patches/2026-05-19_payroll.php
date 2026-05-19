<?php
/**
 * Payroll feature schema patch (2026-05-19)
 *
 * Adds per-caregiver rate/payable flag + OT & holiday differentials, plus a
 * cg_holidays calendar and overnight-window settings.
 *
 * Idempotent — safe to run repeatedly. Auto-invoked from usersc/includes/cg_init.php
 * so any environment that opens a cg/ page picks up the patch with no manual step.
 */

if (!function_exists('cg_patch_2026_05_19_payroll')) {

function cg_patch_2026_05_19_payroll() {
    global $db;
    static $done = false;
    if ($done) return;
    $done = true;

    // --- cg_caregivers: add payable + rate + differential columns ---
    $cols = [];
    foreach ($db->query('SHOW COLUMNS FROM cg_caregivers')->results() as $c) {
        $cols[$c->Field] = true;
    }
    $adds = [
        'payable'       => "ADD COLUMN payable        TINYINT(1)   NOT NULL DEFAULT 1 AFTER active",
        'pay_rate'      => "ADD COLUMN pay_rate       DECIMAL(8,2) NULL              AFTER payable",
        'diff_ot_mult'  => "ADD COLUMN diff_ot_mult   DECIMAL(4,2) NULL              AFTER pay_rate",
        'diff_ot_add'   => "ADD COLUMN diff_ot_add    DECIMAL(8,2) NULL              AFTER diff_ot_mult",
        'diff_hol_mult' => "ADD COLUMN diff_hol_mult  DECIMAL(4,2) NULL              AFTER diff_ot_add",
        'diff_hol_add'  => "ADD COLUMN diff_hol_add   DECIMAL(8,2) NULL              AFTER diff_hol_mult",
    ];
    $pending = [];
    foreach ($adds as $name => $clause) {
        if (!isset($cols[$name])) $pending[] = $clause;
    }
    if ($pending) {
        $db->query('ALTER TABLE cg_caregivers ' . implode(', ', $pending));
    }

    // --- cg_holidays table ---
    if (!$db->tableExists('cg_holidays')) {
        $db->query(
            'CREATE TABLE cg_holidays (
                hdate DATE NOT NULL,
                name  VARCHAR(120) NOT NULL,
                PRIMARY KEY (hdate)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    // --- overnight-window settings ---
    $existing = [];
    foreach ($db->query("SELECT skey FROM cg_settings WHERE skey IN ('ot_start_hour','ot_end_hour')")->results() as $r) {
        $existing[$r->skey] = true;
    }
    if (!isset($existing['ot_start_hour'])) {
        $db->query("INSERT INTO cg_settings (skey, sval) VALUES ('ot_start_hour', '22')");
    }
    if (!isset($existing['ot_end_hour'])) {
        $db->query("INSERT INTO cg_settings (skey, sval) VALUES ('ot_end_hour', '6')");
    }
}

} // function_exists guard
