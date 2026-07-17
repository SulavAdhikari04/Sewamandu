<?php
/**
 * OTP (One-Time Password) helper for Sewamandu.
 * Generates 6-digit codes and emails them via the existing Gmail SMTP sender.
 */

require_once __DIR__ . '/EmailConfig_Gmail.php';

if (!defined('OTP_TTL_SECONDS')) {
    define('OTP_TTL_SECONDS', 600); // 10 minutes
}
if (!defined('OTP_MAX_ATTEMPTS')) {
    define('OTP_MAX_ATTEMPTS', 5);
}
// Set to true to require email OTP on registration. False = skip for local testing.
if (!defined('REQUIRE_REGISTER_OTP')) {
    define('REQUIRE_REGISTER_OTP', true);
}

/**
 * Generate a zero-padded 6-digit OTP code.
 */
function generateOtpCode() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send the OTP code to the given email address using a themed template.
 *
 * @param string $to      Recipient email address
 * @param string $name    Recipient name (for greeting)
 * @param string $otp      The 6-digit code
 * @param string $purpose Short label e.g. "complete your registration"
 * @return array           Result from sendEmail()
 */
function sendOtpEmail($to, $name, $otp, $purpose = 'verify your account') {
    $name = $name !== '' ? $name : 'there';
    $minutes = (int) round(OTP_TTL_SECONDS / 60);
    $subject = 'Your Sewamandu verification code: ' . $otp;

    $text  = "Hello $name,\n\n";
    $text .= "Use the verification code below to $purpose.\n\n";
    $text .= "Verification code: $otp\n\n";
    $text .= "This code will expire in $minutes minutes.\n";
    $text .= "If you didn't request this, you can safely ignore this email.\n\n";
    $text .= "Best regards,\nSewamandu Team";

    $html = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
    <body style='margin:0;padding:20px;background:#eef2f1;font-family:Segoe UI,Tahoma,sans-serif;color:#333;'>
        <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.1);'>
            <div style='background:linear-gradient(135deg,#00796b 0%,#004d40 100%);color:#fff;padding:34px 20px;text-align:center;'>
                <h1 style='margin:0;font-size:2rem;font-weight:700;'>Sewamandu</h1>
                <p style='margin:6px 0 0;font-size:1rem;opacity:0.9;'>Verification Code</p>
            </div>
            <div style='padding:40px 30px;'>
                <p style='font-size:1.05rem;font-weight:600;margin:0 0 14px;'>Hello $name,</p>
                <p style='color:#666;line-height:1.7;margin:0 0 26px;'>Use the verification code below to $purpose.</p>
                <div style='text-align:center;margin:0 0 26px;'>
                    <div style='display:inline-block;background:#e0f2f1;border:1px dashed #00796b;border-radius:10px;padding:18px 34px;'>
                        <span style='font-size:2.4rem;font-weight:700;letter-spacing:10px;color:#004d40;'>$otp</span>
                    </div>
                </div>
                <div style='background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:14px;border-radius:8px;font-size:0.9rem;'>
                    This code will expire in <strong>$minutes minutes</strong>. For your security, never share it with anyone.
                </div>
                <p style='color:#888;font-size:0.85rem;margin:22px 0 0;'>If you didn't request this, you can safely ignore this email.</p>
            </div>
            <div style='background:#f8f9fa;padding:22px 30px;text-align:center;color:#666;border-top:1px solid #e9ecef;'>
                <p style='margin:0;font-size:0.9rem;'>Best regards,<br><span style='font-weight:600;color:#00796b;'>Sewamandu Team</span></p>
            </div>
        </div>
    </body>
    </html>";

    return sendEmail($to, $subject, $text, $html);
}

/**
 * Store a freshly generated OTP for a given context in the session.
 *
 * @param string $context 'register' or 'login'
 * @param string $email   Email the code was sent to
 * @param string $name    Display name
 * @param array  $payload Context-specific data needed to finish the action
 * @return string         The generated code
 */
function startOtpSession($context, $email, $name, array $payload) {
    $code = generateOtpCode();
    $_SESSION['pending_otp'] = [
        'context'  => $context,
        'email'    => $email,
        'name'     => $name,
        'code'     => $code,
        'expires'  => time() + OTP_TTL_SECONDS,
        'attempts' => 0,
        'payload'  => $payload,
    ];
    return $code;
}

/**
 * Mask an email address for display, e.g. jo***@gmail.com
 */
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return $email;
    }
    $name = $parts[0];
    $visible = substr($name, 0, min(2, strlen($name)));
    return $visible . str_repeat('*', max(1, strlen($name) - strlen($visible))) . '@' . $parts[1];
}
