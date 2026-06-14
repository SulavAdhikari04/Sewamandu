<?php
/**
 * Trusted device helpers — skip login OTP on devices that completed registration.
 */

if (!defined('TRUSTED_DEVICE_COOKIE')) {
    define('TRUSTED_DEVICE_COOKIE', 'sewamandu_trusted_device');
}
if (!defined('TRUSTED_DEVICE_TTL')) {
    define('TRUSTED_DEVICE_TTL', 86400 * 365);
}

function trustedDeviceSecret() {
    static $secret = null;
    if ($secret === null) {
        $secret = hash('sha256', __DIR__ . '/TrustedDevice.php');
    }
    return $secret;
}

function createTrustedDeviceValue($userId) {
    $issued = time();
    $payload = (int) $userId . ':' . $issued;
    $sig = hash_hmac('sha256', $payload, trustedDeviceSecret());
    return base64_encode($payload . ':' . $sig);
}

function parseTrustedDeviceValue($value) {
    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        return null;
    }

    $parts = explode(':', $decoded);
    if (count($parts) !== 3) {
        return null;
    }

    [$userId, $issued, $sig] = $parts;
    if (!ctype_digit($userId) || !ctype_digit($issued)) {
        return null;
    }

    $payload = $userId . ':' . $issued;
    if (!hash_equals(hash_hmac('sha256', $payload, trustedDeviceSecret()), $sig)) {
        return null;
    }

    if (time() - (int) $issued > TRUSTED_DEVICE_TTL) {
        return null;
    }

    return (int) $userId;
}

function isDeviceTrustedForUser($userId) {
    if (empty($_COOKIE[TRUSTED_DEVICE_COOKIE])) {
        return false;
    }

    $trustedUserId = parseTrustedDeviceValue($_COOKIE[TRUSTED_DEVICE_COOKIE]);
    return $trustedUserId === (int) $userId;
}

function trustDeviceForUser($userId) {
    $value = createTrustedDeviceValue($userId);
    setcookie(TRUSTED_DEVICE_COOKIE, $value, time() + TRUSTED_DEVICE_TTL, '/');
}

function completeUserLogin($userId, $username, $role, $remember = false) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;

    if ($remember) {
        setcookie('user_id', $userId, time() + (86400 * 30), '/');
    }

    if ($role === 'admin') {
        header('Location: admin-dashboard.php');
    } elseif ($role === 'provider') {
        header('Location: provider-dashboard.php');
    } else {
        header('Location: customer-home.php');
    }
    exit();
}
