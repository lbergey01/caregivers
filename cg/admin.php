<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;

// Lightweight stats so the hub gives the admin at-a-glance state without a click.
$cg_count    = (int)$db->query('SELECT COUNT(*) AS n FROM cg_caregivers WHERE active = 1')->first()->n;
$cl_count    = (int)$db->query('SELECT COUNT(*) AS n FROM cg_clients   WHERE active = 1')->first()->n;
$hol_count   = (int)$db->query('SELECT COUNT(*) AS n FROM cg_holidays')->first()->n;
$audit_recent = (int)$db->query('SELECT COUNT(*) AS n FROM cg_shift_audit WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->first()->n;

$sections = [
    ['admin_caregivers.php', 'Caregivers',  "$cg_count active",     'Add or edit caregivers, link logins, set pay rates and differentials.'],
    ['admin_clients.php',    'Clients',     "$cl_count active",     'Manage the people receiving care.'],
    ['admin_payroll.php',    'Payroll',     'Run report',           'Hours worked by date range, grouped by caregiver, with $ totals.'],
    ['admin_holidays.php',   'Holidays',    "$hol_count defined",   'Dates that trigger each caregiver\'s holiday differential.'],
    ['admin_audit.php',      'Schedule Log',"$audit_recent in 7 days", 'Who created, changed, or deleted each shift and when.'],
    ['admin_settings.php',   'Settings',    '',                     'SMS, default client, overnight-window hours.'],
];

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Admin</h1>
  <p><a href="index.php">&larr; Calendar</a> &middot; <a href="history.php">History</a></p>

  <div class="row g-3">
    <?php foreach ($sections as [$href, $title, $stat, $desc]): ?>
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
