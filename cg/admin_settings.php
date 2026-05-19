<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/sms.php';

if (!$user->isLoggedIn()) { Redirect::to($us_url_root . 'users/login.php'); die(); }
if (!cg_isAdmin()) { die('Admin only.'); }

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $keys = ['sms_provider','sms_user_id','sms_pass','sms_did',
                 'sms_private_ip','sms_private_port','sms_private_user','sms_private_pass',
                 'default_client_id'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) cg_setting_set($k, trim($_POST[$k]));
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
  <p><a href="index.php">&larr; Calendar</a> &middot; <a href="admin_caregivers.php">Caregivers</a> &middot; <a href="admin_clients.php">Clients</a></p>
  <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="save">

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
