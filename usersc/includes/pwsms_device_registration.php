<?php
/**
 * pwsms — Device Registration page.
 * Shown on first click of an SMS link. Captures device fingerprint and mints
 * the long-lived session_token + cookie.
 *
 * Expects $pageAccess in scope (from pwsms_handle_device_registration()).
 */
$siteName = pwsms_cfg('site_name', 'Site');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Device Registration | <?= htmlspecialchars($siteName) ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 400px; width: 100%; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
        h1 { color: #333; margin-bottom: 20px; }
        .info { color: #1976d2; background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: left; }
        .button { width: 100%; padding: 14px; background-color: #4CAF50; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; transition: background-color 0.3s; }
        .button:hover { background-color: #45a049; }
        .contact-info { background-color: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
        .expiry-info { font-size: 12px; color: #666; margin-top: 15px; }
    </style>
    <script>
        function detectDevice() {
            var ua = navigator.userAgent;
            document.getElementById('device_type').value = /Mobile|Android|iPhone|iPad/.test(ua) ? 'Mobile' : 'Desktop';
            document.getElementById('operating_system').value =
                ua.indexOf('Windows') !== -1 ? 'Windows' :
                ua.indexOf('Mac') !== -1 ? 'macOS' :
                ua.indexOf('Android') !== -1 ? 'Android' :
                (ua.indexOf('iPhone') !== -1 || ua.indexOf('iPad') !== -1) ? 'iOS' :
                ua.indexOf('Linux') !== -1 ? 'Linux' : 'Unknown';
            document.getElementById('browser').value =
                (ua.indexOf('Chrome') !== -1 && ua.indexOf('Edge') === -1) ? 'Chrome' :
                ua.indexOf('Firefox') !== -1 ? 'Firefox' :
                (ua.indexOf('Safari') !== -1 && ua.indexOf('Chrome') === -1) ? 'Safari' :
                ua.indexOf('Edge') !== -1 ? 'Edge' :
                ua.indexOf('Opera') !== -1 ? 'Opera' : 'Unknown';
        }
        window.onload = detectDevice;
    </script>
</head>
<body>
    <div class="container">
        <h1>🔐 Secure Access</h1>

        <div class="contact-info">
            Contact: <?= htmlspecialchars($pageAccess->contact_info) ?>
        </div>

        <div class="info">
            <strong>Security Notice:</strong><br>
            This secure link will be bound to your current device/browser for security.
            Only click "Register This Device" if you're the intended recipient and want to
            use this device for ongoing access to <?= htmlspecialchars($siteName) ?>.
        </div>

        <form method="POST">
            <input type="hidden" id="device_type" name="device_type" value="">
            <input type="hidden" id="operating_system" name="operating_system" value="">
            <input type="hidden" id="browser" name="browser" value="">

            <button type="submit" name="register_device" class="button">
                🔒 Register This Device
            </button>
        </form>

        <div class="expiry-info">
            <?php
            $expiryTime = strtotime($pageAccess->sms_expires_at);
            $minutesLeft = max(0, ($expiryTime - time()) / 60);
            echo $minutesLeft > 0
                ? "This link expires in " . ceil($minutesLeft) . " minutes"
                : "This link has expired";
            ?>
        </div>
    </div>
</body>
</html>
