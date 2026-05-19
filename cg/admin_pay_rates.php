<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

global $db;
$saved_id = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && !empty($_POST['id'])) {
    // Blank string => NULL, otherwise float. Lets the admin clear a rate
    // (which falls back to base on the report) without typing 0.
    $nf = function ($v) {
        $v = trim((string)$v);
        return ($v === '') ? null : (float)$v;
    };
    $id       = (int)$_POST['id'];
    $payable  = !empty($_POST['payable']) ? 1 : 0;
    $pay_rate = $nf($_POST['pay_rate']      ?? '');
    $ot_mult  = $nf($_POST['diff_ot_mult']  ?? '');
    $ot_add   = $nf($_POST['diff_ot_add']   ?? '');
    $hol_mult = $nf($_POST['diff_hol_mult'] ?? '');
    $hol_add  = $nf($_POST['diff_hol_add']  ?? '');

    $db->query('UPDATE cg_caregivers
                   SET payable=?, pay_rate=?, diff_ot_mult=?, diff_ot_add=?,
                       diff_hol_mult=?, diff_hol_add=?
                 WHERE id=?',
               [$payable, $pay_rate, $ot_mult, $ot_add, $hol_mult, $hol_add, $id]);

    header('Location: admin_pay_rates.php?saved=' . $id . '#cg-' . $id);
    exit;
}

$rows = $db->query(
    'SELECT id, name, color, active, payable, pay_rate,
            diff_ot_mult, diff_ot_add, diff_hol_mult, diff_hol_add
       FROM cg_caregivers
      ORDER BY active DESC, name'
)->results();

// Trim trailing zeros for cleaner display of stored decimals (e.g. "1.50" => "1.5").
function _dec($v) {
    if ($v === null) return '';
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Pay Rates</h1>
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="admin_caregivers.php">Caregivers</a> &middot; <a href="admin_payroll.php">Payroll report</a></p>

  <?php if ($saved_id): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

  <div class="card mb-3">
    <div class="card-body small text-muted">
      <p class="mb-1">
        Pay formula on the payroll report:
        <code>effective_rate = base_rate &times; multiplier + add_on</code>.
        Multiplier defaults to 1.0, add-on to 0 &mdash; so leaving the differential blank means &ldquo;same as base rate.&rdquo;
      </p>
      <p class="mb-0">
        Holiday hours take precedence over overnight hours when they overlap. A blank <strong>Rate</strong> shows hours only (no $ total).
        Holidays are defined on the <a href="admin_holidays.php">Holidays</a> tab; the overnight window is set on the <a href="admin_settings.php">Settings</a> tab.
      </p>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Name</th>
          <th class="text-center">Payable</th>
          <th>Rate $/hr</th>
          <th>Overnight ×</th>
          <th>Overnight +$/hr</th>
          <th>Holiday ×</th>
          <th>Holiday +$/hr</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr id="cg-<?= $r->id ?>" class="<?= ($saved_id === (int)$r->id) ? 'cg-just-saved' : '' ?> <?= $r->active ? '' : 'opacity-50' ?>">
          <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $r->id ?>">
            <td>
              <span class="badge me-1" style="background: <?= htmlspecialchars($r->color) ?>">&nbsp;</span>
              <?= htmlspecialchars($r->name) ?>
              <?php if (!$r->active): ?><small class="text-muted">(inactive)</small><?php endif; ?>
            </td>
            <td class="text-center">
              <input type="checkbox" class="form-check-input" name="payable" value="1" <?= $r->payable ? 'checked' : '' ?>>
            </td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="pay_rate"
                       style="width: 100px" value="<?= htmlspecialchars(_dec($r->pay_rate)) ?>"
                       placeholder="(unset)"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_ot_mult"
                       style="width: 90px" value="<?= htmlspecialchars(_dec($r->diff_ot_mult)) ?>"
                       placeholder="1.0"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_ot_add"
                       style="width: 90px" value="<?= htmlspecialchars(_dec($r->diff_ot_add)) ?>"
                       placeholder="0"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_hol_mult"
                       style="width: 90px" value="<?= htmlspecialchars(_dec($r->diff_hol_mult)) ?>"
                       placeholder="1.0"></td>
            <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="diff_hol_add"
                       style="width: 90px" value="<?= htmlspecialchars(_dec($r->diff_hol_add)) ?>"
                       placeholder="0"></td>
            <td><button class="btn btn-sm btn-primary">Save</button></td>
          </form>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<style>
  @keyframes cgSavedFlash {
    0%   { box-shadow: inset 0 0 0 3px rgba(40, 167, 69, 0.55); }
    100% { box-shadow: inset 0 0 0 0   rgba(40, 167, 69, 0);    }
  }
  .cg-just-saved { animation: cgSavedFlash 1.4s ease-out 1; }
</style>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
