<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/sms.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
// Managers may access SMS only; admins see everything.
if (!cg_isManager()) { die('Admin or manager only.'); }
$is_admin = cg_isAdmin();

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        // SMS keys — always settable by manager+admin.
        $sms_keys = ['sms_provider','sms_user_id','sms_pass','sms_did',
                     'sms_private_ip','sms_private_port','sms_private_user','sms_private_pass'];
        foreach ($sms_keys as $k) {
            if (isset($_POST[$k])) cg_setting_set($k, trim($_POST[$k]));
        }
        // Admin-only keys — silently ignore if a manager somehow posts them.
        if ($is_admin) {
            if (isset($_POST['default_client_id'])) {
                cg_setting_set('default_client_id', trim($_POST['default_client_id']));
            }
            if (isset($_POST['ot_start_hour'])) {
                cg_setting_set('ot_start_hour', max(0, min(23, (int)$_POST['ot_start_hour'])));
            }
            if (isset($_POST['ot_end_hour'])) {
                cg_setting_set('ot_end_hour', max(0, min(23, (int)$_POST['ot_end_hour'])));
            }
        }
        $msg = 'Settings saved.';
    } elseif ($action === 'test_sms') {
        try {
            // re-read settings after potential save in same request flow
            cg_settings(); // warm
            $resp = cg_sendSMS($_POST['test_to'] ?? '', $_POST['test_msg'] ?? 'Caregivers test');
            $msg  = 'SMS sent. Response: ' . htmlspecialchars(json_encode($resp));
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}

$s = cg_settings();
$clients = cg_clientsAll(false);

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main class="container my-4">
  <h1>Settings</h1>
  <p><a href="admin.php">&larr; Admin</a> &middot; <a href="index.php">Calendar</a></p>
  <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="save">

    <?php if ($is_admin): ?>
    <div class="card mb-3">
      <div class="card-header">General</div>
      <div class="card-body">
        <label class="form-label">Default client (used by main calendar)</label>
        <select name="default_client_id" class="form-select">
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c->id ?>" <?= ((int)($s['default_client_id'] ?? 0) === (int)$c->id) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c->name) ?><?= $c->active ? '' : ' (inactive)' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Payroll &mdash; overnight window</div>
      <div class="card-body">
        <p class="small text-muted mb-2">
          Hours falling inside this window get the per-caregiver overnight differential on the payroll report.
          Window wraps midnight when the start hour is later than the end hour (e.g. 22 &rarr; 6 covers 10 PM through 6 AM).
        </p>
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label">Start hour (0-23)</label>
            <input type="number" min="0" max="23" name="ot_start_hour" class="form-control"
                   value="<?= htmlspecialchars((string)($s['ot_start_hour'] ?? 22)) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">End hour (0-23)</label>
            <input type="number" min="0" max="23" name="ot_end_hour" class="form-control"
                   value="<?= htmlspecialchars((string)($s['ot_end_hour'] ?? 6)) ?>">
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-header">SMS</div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label">Provider</label>
          <select name="sms_provider" class="form-select">
            <option value="voipms"  <?= ($s['sms_provider'] ?? '') === 'voipms'  ? 'selected' : '' ?>>VoIP.ms (cloud)</option>
            <option value="private" <?= ($s['sms_provider'] ?? '') === 'private' ? 'selected' : '' ?>>Private server</option>
          </select>
        </div>

        <h6>VoIP.ms</h6>
        <div class="row g-2 mb-2">
          <div class="col-md-4"><input name="sms_user_id" class="form-control" placeholder="API username" value="<?= htmlspecialchars($s['sms_user_id'] ?? '') ?>"></div>
          <div class="col-md-4"><input name="sms_pass"    class="form-control" placeholder="API password" type="password" value="<?= htmlspecialchars($s['sms_pass'] ?? '') ?>"></div>
          <div class="col-md-4"><input name="sms_did"     class="form-control" placeholder="DID (10-digit)" value="<?= htmlspecialchars($s['sms_did'] ?? '') ?>"></div>
        </div>

        <h6>Private SMS server</h6>
        <div class="row g-2 mb-2">
          <div class="col-md-3"><input name="sms_private_ip"   class="form-control" placeholder="IP/host" value="<?= htmlspecialchars($s['sms_private_ip'] ?? '') ?>"></div>
          <div class="col-md-2"><input name="sms_private_port" class="form-control" placeholder="Port"    value="<?= htmlspecialchars($s['sms_private_port'] ?? '') ?>"></div>
          <div class="col-md-3"><input name="sms_private_user" class="form-control" placeholder="User"    value="<?= htmlspecialchars($s['sms_private_user'] ?? '') ?>"></div>
          <div class="col-md-4"><input name="sms_private_pass" class="form-control" placeholder="Pass"    type="password" value="<?= htmlspecialchars($s['sms_private_pass'] ?? '') ?>"></div>
        </div>
      </div>
    </div>
    <button class="btn btn-primary">Save settings</button>
  </form>

  <hr class="my-4">
  <form method="post" class="card">
    <input type="hidden" name="action" value="test_sms">
    <div class="card-header">Send test SMS</div>
    <div class="card-body row g-2">
      <div class="col-md-4"><input class="form-control" name="test_to"  placeholder="e.g. 2677180001" required></div>
      <div class="col-md-6"><input class="form-control" name="test_msg" placeholder="Message" value="Caregivers app test"></div>
      <div class="col-md-2"><button class="btn btn-secondary w-100">Send</button></div>
    </div>
  </form>
</main>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
