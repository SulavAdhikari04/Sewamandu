<?php
/**
 * Two-factor authentication (2FA) helpers.
 * Default: disabled. When enabled, login requires an email OTP unless the device is trusted.
 */

require_once __DIR__ . '/OTP.php';

function ensureTwoFactorColumn($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0");
    $checked = true;
}

function isTwoFactorEnabled($conn, $userId) {
    ensureTwoFactorColumn($conn);
    $stmt = $conn->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($enabled);
    $found = $stmt->fetch();
    $stmt->close();
    return $found && (int) $enabled === 1;
}

function setTwoFactorEnabled($conn, $userId, $enabled) {
    ensureTwoFactorColumn($conn);
    $value = $enabled ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Whether login must go through the OTP step (2FA on and device not yet trusted).
 */
function userRequiresLoginOtp($conn, $userId) {
    return isTwoFactorEnabled($conn, $userId);
}

function maskPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) < 4) {
        return $phone;
    }
    return substr($phone, 0, 2) . str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -2);
}

/**
 * Send a login 2FA code to the user's registered email.
 * Phone/SMS can be wired here later when an SMS provider is available.
 */
function sendLoginVerificationCode($email, $name, $code) {
    return sendOtpEmail($email, $name, $code, 'complete your sign in');
}

function getLoginVerificationDestination($conn, $userId) {
    $stmt = $conn->prepare("SELECT email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($email, $phone);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found) {
        return null;
    }
    return [
        'email' => $email,
        'phone' => $phone,
        'channel' => 'email',
        'masked' => maskEmail($email),
    ];
}
