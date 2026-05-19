<?php
// Caregivers SMS layer. Ported from c:\xampp\htdocs\vbs\includes\sms_voip_ms.php.
// Adapted to load creds from the cg_settings table instead of $vbs_config.
// Public API: cg_sendSMS($to, $message)  ->  array (provider response)

if (!class_exists('CgVoipMsSms')) {
class CgVoipMsSms {
    private $username;
    private $password;
    private $api_url = 'https://voip.ms/api/v1/rest.php';

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    public function sendSms($did, $dst, $message) {
        return $this->makeRequest([
            'api_username' => $this->username,
            'api_password' => $this->password,
            'method'       => 'sendSMS',
            'did'          => $did,
            'dst'          => $dst,
            'message'      => $message,
        ]);
    }

    private function makeRequest($params) {
        $url = $this->api_url . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Caregivers-PHP-Client/1.0');
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
        if ($http_code !== 200) {
            throw new Exception('HTTP Error: ' . $http_code);
        }
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }
        return $decoded;
    }
}
}

function cg_sendSMS($to, $message) {
    $cfg = cg_settings();
    $cell = preg_replace('/\D/', '', $to);
    if (strlen($cell) === 11 && substr($cell, 0, 1) === '1') {
        $cell = substr($cell, 1);
    }
    $dest = '1' . $cell;

    $provider = $cfg['sms_provider'] ?? 'voipms';

    if ($provider === 'private') {
        return cg_sendSMSPrivate($dest, $message, $cfg);
    }

    if (empty($cfg['sms_user_id']) || empty($cfg['sms_pass']) || empty($cfg['sms_did'])) {
        throw new Exception('VoIP.ms SMS not configured. Set credentials on the Settings page.');
    }

    $sms = new CgVoipMsSms($cfg['sms_user_id'], $cfg['sms_pass']);
    return $sms->sendSms($cfg['sms_did'], $dest, $message);
}

function cg_sendSMSPrivate($to, $message, $cfg) {
    if (empty($cfg['sms_private_ip']) || empty($cfg['sms_private_port'])) {
        throw new Exception('Private SMS server not configured.');
    }
    $url = "http://{$cfg['sms_private_ip']}:{$cfg['sms_private_port']}/message";
    $payload = json_encode([
        'phoneNumbers' => ['+' . $to],
        'textMessage'  => ['text' => $message],
    ]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    if (!empty($cfg['sms_private_user'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $cfg['sms_private_user'] . ':' . $cfg['sms_private_pass']);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    if ($error) {
        throw new Exception("Private SMS cURL Error: $error");
    }
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        $decoded = ['raw_response' => $response];
    }
    $decoded['http_code'] = $http_code;
    return $decoded;
}
