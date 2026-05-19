<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isManager()) { die('Admin or manager only.'); }

global $db;
$is_admin = cg_isAdmin();

// Lightweight stats so the hub gives the admin at-a-glance state without a click.
$cg_count    = (int)$db->query('SELECT COUNT(*) AS n FROM cg_caregivers WHERE active = 1')->first()->n;
$cl_count    = (int)$db->query('SELECT COUNT(*) AS n FROM cg_clients   WHERE active = 1')->first()->n;
$hol_count   = (int)$db->query('SELECT COUNT(*) AS n FROM cg_holidays')->first()->n;
$audit_recent      = (int)$db->query('SELECT COUNT(*) AS n FROM cg_shift_audit     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->first()->n;
$cg_audit_recent   = (int)$db->query('SELECT COUNT(*) AS n FROM cg_caregiver_audit WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->first()->n;

// Each section has an `admin_only` flag. Managers see the rest.
$sections = [
    ['admin_caregivers.php',     'Caregivers',       "$cg_count active",          'Add or edit caregivers, link logins, set colors.',                false],
    ['admin_pay_rates.php',      'Pay Rates',        '',                          'Per-caregiver rate, payable flag, and overnight/holiday differentials.', true],
    ['admin_clients.php',        'Clients',          "$cl_count active",          'Manage the people receiving care.',                                true],
    ['admin_payroll.php',        'Payroll',          'Run report',                'Hours worked by date range, grouped by caregiver, with $ totals.', true],
    ['admin_holidays.php',       'Holidays',         "$hol_count defined",        'Dates that trigger each caregiver\'s holiday differential.',       true],
    ['admin_audit.php',          'Schedule Log',     "$audit_recent in 7 days",   'Who created, changed, or deleted each shift and when.',            true],
    ['admin_caregiver_audit.php','Caregiver Log',    "$cg_audit_recent in 7 days",'Who added, edited, or deactivated each caregiver and when.',       true],
    ['admin_settings.php',       'Settings',         '',                          $is_admin ? 'SMS, default client, overnight-window hours.' : 'SMS provider and credentials.', false],
];

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Admin</h1>
  <p><a href="index.php">&larr; Calendar</a> &middot; <a href="history.php">History</a></p>

  <?php if (!$is_admin): ?>
    <div class="alert alert-info small">Signed in as manager — pay rates, payroll, clients, holidays, and the activity logs are admin-only.</div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($sections as [$href, $title, $stat, $desc, $admin_only]):
        if ($admin_only && !$is_admin) continue;
    ?>
      <div class="col-md-6 col-lg-4">
        <a class="card h-100 text-decoration-none text-dark shadow-sm" href="<?= htmlspecialchars($href) ?>">
          <div class="card-body">
            <h5 class="card-title d-flex justify-content-between align-items-baseline mb-2">
              <span><?= htmlspecialchars($title) ?></span>
              <?php if ($stat !== ''): ?>
                <small class="text-muted fw-normal"><?= htmlspecialchars($stat) ?></small>
              <?php endif; ?>
            </h5>
            <p class="card-text small text-muted mb-0"><?= htmlspecialchars($desc) ?></p>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
