<?php
/**
 * secure_login.php — Passwordless SMS entry point.
 *
 * Two roles, depending on the request:
 *   1. POST  - phone submitted: validate, mint token, send SMS
 *   2. GET ?ln=<token>  - validate token, register device, set UserSpice
 *                        session via pwsms hook, redirect to landing_page
 *   3. GET (no token)   - show the phone-entry form
 *
 * The library lives in usersc/includes/pwsms_*.php. Host-specific glue is
 * in usersc/includes/pwsms_config.php.
 */
require_once 'users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/cg_init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/sms.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/pwsms_auth.php';

// Already password-logged-in? Skip the SMS dance entirely.
if ($user->isLoggedIn()) {
    header('Location: ' . $us_url_root . pwsms_cfg('landing_page', 'cg/index.php'));
    exit;
}

$siteName    = pwsms_cfg('site_name', 'Site');
$landingPage = $us_url_root . pwsms_cfg('landing_page', 'cg/index.php');

// ---- Branch 2: SMS-link click. Token in URL. ----
if (!empty($_GET['ln']) || !empty($_GET['uniqueid'])) {
    // pwsms handles device-reg prompts, expiry, error pages internally.
    // On success it fires on_auth_success (sets the UserSpice session)
    // and returns. We just redirect to the landing page.
    $auth = pwsms_require_auth();
    if (!empty($auth['authorized'])) {
        header('Location: ' . $landingPage);
        exit;
    }
    // If pwsms returned without exiting on a non-authorized result, surface it.
    require_once $abs_us_root . $us_url_root . 'usersc/includes/pwsms_error_pages.php';
    pwsms_show_error($auth['reason'] ?? 'invalid_link', $auth);
}

// ---- Branch 1: phone submitted. ----
$rateLimit = class_exists('RateLimit') ? new RateLimit() : null;
$error = null;
$successMessage = null;
$silentlyIgnored = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawContact = trim($_POST['contact'] ?? '');
    $isWhitelisted = pwsms_is_whitelisted();
    $cleanPhone = pwsms_normalize_phone($rawContact);

    if (!$cleanPhone) {
        $error = "Please enter a valid 10-digit phone number.";
    }

    // Per-IP rate limit on SMS sends (4/hour). Identifier-bound limit is
    // intentionally omitted — would let an attacker lock out a real caregiver
    // by submitting their number repeatedly.
    if (!$error && !$isWhitelisted && $rateLimit && !$rateLimit->check('sms_request')) {
        $error = "Too many SMS requests. Please wait before requesting another link.";
    }

    // Anti-enumeration tarpit: 5+ unknown-phone submissions from this IP -> silent drop.
    if (!$error && pwsms_is_blacklisted('invalid_phone_lookup')) {
        pwsms_log('blacklist_silent_ignore', '', $cleanPhone, ['action' => 'invalid_phone_lookup']);
        sleep(5);
        $silentlyIgnored = true;
    }

    if (!$error && !$silentlyIgnored) {
        $userId = pwsms_resolve_user_id($cleanPhone);

        if ($userId) {
            // Build SMS link. Single-tenant -> direct link to secure_login.php?ln=token.
            $uniqueId = pwsms_generate_token($cleanPhone, pwsms_cfg('landing_page', 'cg/index.php'));

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $secureLink = $protocol . $_SERVER['HTTP_HOST'] . $us_url_root . 'secure_login.php?ln=' . urlencode($uniqueId);

            $msg = $siteName . ': Your secure access link: ' . $secureLink
                 . ' (Expires in ' . pwsms_cfg('sms_expiry_minutes', 5) . ' min). Reply STOP to opt out.';

            try {
                $smsSend = pwsms_cfg('sms_send');
                if (is_callable($smsSend)) {
                    $smsSend($cleanPhone, $msg);
                }
            } catch (Exception $e) {
                pwsms_log('sms_send_failed', $uniqueId, $cleanPhone, ['error' => $e->getMessage()]);
                $error = "We couldn't send the SMS just now. Please try again in a moment.";
            }

            if (!$error) {
                if ($rateLimit) $rateLimit->record('sms_request', [], true);
                pwsms_clear_failures('invalid_phone_lookup');
                pwsms_log('valid_phone_submitted', $uniqueId, $cleanPhone);
                $successMessage = "A secure access link has been sent to your phone.";
            }
        } else {
            if (!$isWhitelisted) {
                sleep(5);
                pwsms_record_failure('invalid_phone_lookup');
            }
            pwsms_log('invalid_phone_submitted', '', $cleanPhone);
            $error = "This phone number is not registered. Please contact your administrator.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Secure Login | <?= htmlspecialchars($siteName) ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 400px; width: 100%; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; margin-bottom: 8px; }
        h3 { text-align: center; color: #555; margin: 0 0 24px 0; font-weight: normal; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="tel"] { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; box-sizing: border-box; }
        input[type="tel"]:focus { border-color: #4CAF50; outline: none; }
        button { width: 100%; padding: 14px; background-color: #4CAF50; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; transition: background-color 0.3s; }
        button:hover { background-color: #45a049; }
        .error { color: #d32f2f; background-color: #ffebee; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .success { color: #2e7d32; background-color: #e8f5e8; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .info { color: #1976d2; background-color: #e3f2fd; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .security-note { background-color: #fff3e0; border-left: 4px solid #f57c00; padding: 12px; margin-top: 20px; font-size: 12px; color: #f57c00; }
        .alt-login { text-align: center; margin-top: 18px; font-size: 13px; }
        .alt-login a { color: #1976d2; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($siteName) ?></h1>
        <h3>🔐 Secure Access</h3>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="success"><?= htmlspecialchars($successMessage) ?></div>
            <div class="info">
                <strong>Next Steps:</strong><br>
                1. Check your phone for the SMS message<br>
                2. Click the secure link in the message<br>
                3. Register your device on first use<br>
                4. Future visits won't need a new link for <?= (int)pwsms_cfg('session_days', 120) ?> days
            </div>
        <?php else: ?>
            <div class="info">
                Enter your registered phone number to receive a secure access link from
                <strong><?= htmlspecialchars($siteName) ?></strong>.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="contact">Phone Number:</label>
                    <input type="tel" id="contact" name="contact" required
                           placeholder="Enter your phone number"
                           value="<?= isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : '' ?>">
                </div>
                <button type="submit">📱 Text Me a Login Link</button>
                <p style="font-size: 11px; color: #888; margin-top: 10px;">
                    By clicking Send, you consent to receive a text message at this number.
                    Msg &amp; data rates may apply.
                </p>
            </form>

            <div class="security-note">
                <strong>Security Notice:</strong><br>
                SMS links expire in <?= (int)pwsms_cfg('sms_expiry_minutes', 5) ?> minutes.
                Device sessions slide for <?= (int)pwsms_cfg('session_days', 120) ?> days of activity.
            </div>

            <div class="alt-login">
                <a href="<?= htmlspecialchars($us_url_root) ?>users/login.php">Sign in with password instead</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
