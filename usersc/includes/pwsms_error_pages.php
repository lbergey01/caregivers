<?php
/**
 * pwsms — Passwordless SMS auth library
 * Error / success page templates.
 *
 * Branding pulled from pwsms_config(). Generic across host apps.
 */

function pwsms_show_error($reason, $data = []) {
    global $us_url_root;

    $siteName  = pwsms_cfg('site_name', 'Site');
    $loginPath = $us_url_root . pwsms_cfg('login_page_path', 'secure_login.php');

    $title = "Access Denied";
    $icon = "🚫";
    $message = "Access to this page is restricted.";
    $description = "";
    $actionButton = "";
    $additionalInfo = "";

    switch ($reason) {
        case 'invalid_link':
            $title = "Invalid Link";
            $icon = "🔗";
            $message = "This secure link is not valid or has been corrupted.";
            $description = "The link you're trying to access doesn't exist in our system or may have been mistyped.";
            $actionButton = '<a href="' . htmlspecialchars($loginPath) . '" class="button">Get New Access Link</a>';
            break;

        case 'access_revoked':
            $title = "Access Revoked";
            $icon = "🔒";
            $message = "This secure link has been permanently revoked.";
            $description = "This link was previously disabled for security reasons and is no longer valid.";
            $actionButton = '<a href="' . htmlspecialchars($loginPath) . '" class="button">Request New Access</a>';
            break;

        case 'expired':
            $isRegistered = $data['is_registered'] ?? false;
            $title = $isRegistered ? "Session Expired" : "Link Expired";
            $icon = "⏰";

            if ($isRegistered) {
                $message = "Your secure session has expired.";
                $description = "Your registered device session has expired due to inactivity. Please request a new secure access link.";
            } else {
                $message = "This SMS link has expired.";
                $description = "For security, SMS links expire after a few minutes. Please request a new link below.";
            }

            $actionButton = '<a href="' . htmlspecialchars($loginPath) . '" class="button">Get New Access Link</a>';

            if ($isRegistered && isset($data['pageAccess'])) {
                $uniqueId = $data['pageAccess']->unique_id;
                $additionalInfo = '
                <div class="revoke-section">
                    <h4>Security Option</h4>
                    <p><small>Permanently disable this link to prevent any future access attempts:</small></p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="uniqueid" value="' . htmlspecialchars($uniqueId) . '">
                        <button type="submit" name="revoke_access" class="button danger"
                                onclick="return confirm(\'Are you sure you want to permanently revoke this access link? This cannot be undone.\')">
                            🗑️ Revoke Access Permanently
                        </button>
                    </form>
                </div>';
            }
            break;

        case 'session_mismatch':
            $title = "Device Mismatch";
            $icon = "🔐";
            $message = "This secure link is bound to another device.";
            $description = "This link is already registered to a different browser or device. Please use the original device where you first accessed this link, or request a new link below.";
            $actionButton = '<a href="' . htmlspecialchars($loginPath) . '" class="button">Get New Access Link</a>';
            break;

        case 'insufficient_permissions':
            $title = "Access Denied";
            $icon = "⛔";
            $message = "You don't have permission to access this page.";
            $description = "Your account doesn't have the required permissions for this section of the site.";
            $actionButton = '<a href="' . htmlspecialchars($us_url_root) . '" class="button">Return Home</a>';
            break;

        case 'rate_limited':
            $title = "Too Many Attempts";
            $icon = "🛑";
            $message = "Too many failed access attempts from this network.";
            $description = "For security, this network has been temporarily blocked from validating links. Please wait a few minutes and try again, or request a fresh link below.";
            $actionButton = '<a href="' . htmlspecialchars($loginPath) . '" class="button">Request New Link</a>';
            break;

        default:
            $description = "Please try again or contact support if the problem persists.";
            $actionButton = '<a href="' . htmlspecialchars($loginPath) . '" class="button">Try Again</a>';
            break;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($siteName) ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .container { max-width: 500px; width: 100%; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
            .icon { font-size: 48px; margin-bottom: 20px; display: block; }
            h1 { color: #333; margin-bottom: 15px; font-size: 24px; }
            .message { color: #666; font-size: 18px; margin-bottom: 15px; font-weight: 500; }
            .description { color: #777; margin-bottom: 25px; line-height: 1.5; }
            .button { background-color: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 6px; text-decoration: none; display: inline-block; margin: 10px 5px; font-size: 16px; transition: background-color 0.3s; min-width: 150px; }
            .button:hover { background-color: #45a049; }
            .button.danger { background-color: #dc3545; min-width: auto; }
            .button.danger:hover { background-color: #c82333; }
            .revoke-section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; }
            .revoke-section h4 { color: #666; margin-bottom: 10px; }
            .help-section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 14px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon"><?= $icon ?></div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php if ($description): ?>
                <div class="description"><?= htmlspecialchars($description) ?></div>
            <?php endif; ?>
            <?= $actionButton ?>
            <?= $additionalInfo ?>
            <div class="help-section">
                <strong>Need Help?</strong><br>
                If you continue to have problems accessing <?= htmlspecialchars($siteName) ?>, please contact your administrator.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function pwsms_show_success($action, $message = "", $redirectUrl = null, $redirectDelay = 3) {
    global $us_url_root;
    $siteName  = pwsms_cfg('site_name', 'Site');
    $loginPath = $us_url_root . pwsms_cfg('login_page_path', 'secure_login.php');

    $title = "Success";
    $icon = "✅";

    switch ($action) {
        case 'access_revoked':
            $title = "Access Revoked";
            $icon = "🔒";
            $message = $message ?: "Your secure access has been successfully revoked.";
            $redirectUrl = $redirectUrl ?: $loginPath;
            break;
        case 'device_registered':
            $title = "Device Registered";
            $icon = "🔐";
            $message = $message ?: "Your device has been successfully registered for secure access.";
            break;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($siteName) ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .container { max-width: 400px; width: 100%; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
            .icon { font-size: 48px; margin-bottom: 20px; display: block; }
            h1 { color: #2e7d32; margin-bottom: 20px; }
            .success { color: #2e7d32; background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
            .button { width: 100%; padding: 14px; background-color: #4CAF50; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; transition: background-color 0.3s; text-decoration: none; display: inline-block; }
            .button:hover { background-color: #45a049; }
            .redirect-info { font-size: 14px; color: #666; margin-top: 15px; }
        </style>
        <?php if ($redirectUrl): ?>
        <script>setTimeout(function(){ window.location.href = '<?= htmlspecialchars($redirectUrl, ENT_QUOTES) ?>'; }, <?= (int)($redirectDelay * 1000) ?>);</script>
        <?php endif; ?>
    </head>
    <body>
        <div class="container">
            <div class="icon"><?= $icon ?></div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="success"><strong><?= htmlspecialchars($message) ?></strong></div>
            <?php if ($redirectUrl): ?>
                <div class="redirect-info">You will be redirected automatically in <?= (int)$redirectDelay ?> seconds...</div>
                <a href="<?= htmlspecialchars($redirectUrl) ?>" class="button">Continue Now</a>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
