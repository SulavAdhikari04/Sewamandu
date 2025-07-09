<?php
/**
 * Email Configuration for GharSewa using Gmail SMTP
 * This version sends real emails via Gmail SMTP
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Email configuration settings
define('EMAIL_FROM', 'officialgharsewa@gmail.com'); // Your Gmail address
define('EMAIL_FROM_NAME', 'GharSewa');
define('EMAIL_REPLY_TO', 'officialgharsewa@gmail.com');

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'officialgharsewa@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'kuugrcejbltwnhrh'); // Your Gmail app password
define('USE_SMTP', true);

/**
 * Send email using Gmail SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message body
 * @param string $html_message HTML version of the message (optional)
 * @return array Array with 'success' boolean and 'message' string
 */
function sendEmail($to, $subject, $message, $html_message = '') {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(EMAIL_REPLY_TO, EMAIL_FROM_NAME);
        
        // Content
        $mail->isHTML(!empty($html_message));
        $mail->Subject = $subject;
        
        if (!empty($html_message)) {
            $mail->Body = $html_message;
            $mail->AltBody = $message;
        } else {
            $mail->Body = $message;
        }
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Generate password reset email content
 * 
 * @param string $username User's username
 * @param string $reset_link Password reset link
 * @return array Array with 'text' and 'html' versions of the email
 */
function generatePasswordResetEmail($username, $reset_link) {
    $text_message = "Hello $username,\n\n";
    $text_message .= "You have requested to reset your password for your GharSewa account.\n\n";
    $text_message .= "Click the following link to reset your password:\n";
    $text_message .= $reset_link . "\n\n";
    $text_message .= "This link will remain valid until used.\n\n";
    $text_message .= "If you didn't request this password reset, please ignore this email.\n\n";
    $text_message .= "Best regards,\nGharSewa Team";
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset - GharSewa</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 20px;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
                position: relative;
            }
            
            .header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"50\" cy=\"50\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');
                opacity: 0.3;
            }
            
            .header h1 {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 10px;
                position: relative;
                z-index: 1;
            }
            
            .header h2 {
                font-size: 1.2rem;
                font-weight: 400;
                opacity: 0.9;
                position: relative;
                z-index: 1;
            }
            
            .content {
                padding: 40px 30px;
                background: white;
            }
            
            .greeting {
                font-size: 1.1rem;
                color: #333;
                margin-bottom: 20px;
                font-weight: 500;
            }
            
            .description {
                color: #666;
                margin-bottom: 30px;
                line-height: 1.7;
            }
            
            .button-container {
                text-align: center;
                margin: 30px 0;
            }
            
            .reset-button {
                display: inline-block;
                padding: 15px 30px;
                background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 1rem;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0, 121, 107, 0.3);
            }
            
            .reset-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 121, 107, 0.4);
            }
            
            .link-section {
                margin: 25px 0;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #00796b;
            }
            
            .link-label {
                font-weight: 600;
                color: #333;
                margin-bottom: 10px;
                display: block;
            }
            
            .reset-link {
                word-break: break-all;
                background: white;
                padding: 12px;
                border-radius: 6px;
                border: 1px solid #e1e5e9;
                font-family: 'Courier New', monospace;
                font-size: 0.9rem;
                color: #00796b;
            }
            
            .warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                font-size: 0.9rem;
            }
            
            .security-note {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                color: #0c5460;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                font-size: 0.9rem;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 25px 30px;
                text-align: center;
                color: #666;
                border-top: 1px solid #e9ecef;
            }
            
            .footer p {
                margin: 0;
                font-size: 0.9rem;
            }
            
            .footer .team-name {
                font-weight: 600;
                color: #00796b;
            }
            
            @media (max-width: 600px) {
                body {
                    padding: 10px;
                }
                
                .email-container {
                    border-radius: 8px;
                }
                
                .header {
                    padding: 25px 15px;
                }
                
                .header h1 {
                    font-size: 2rem;
                }
                
                .content {
                    padding: 25px 20px;
                }
                
                .reset-button {
                    padding: 12px 25px;
                    font-size: 0.9rem;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>GharSewa</h1>
                <h2>Password Reset Request</h2>
            </div>
            
            <div class='content'>
                <div class='greeting'>Hello $username,</div>
                
                <div class='description'>
                    You have requested to reset your password for your GharSewa account. 
                    We're here to help you get back into your account securely.
                </div>
                
                <div class='button-container'>
                    <a href='$reset_link' class='reset-button' style='color: white;'>Reset Password</a>
                </div>
                
                <div class='link-section'>
                    <span class='link-label'>Or copy and paste this link into your browser:</span>
                    <div class='reset-link'>$reset_link</div>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Important:</strong> This link will remain valid until used. 
                    For security reasons, please use it as soon as possible.
                </div>
                
                <div class='security-note'>
                    <strong>🔒 Security Notice:</strong> If you didn't request this password reset, 
                    please ignore this email. Your account security is our top priority.
                </div>
            </div>
            
            <div class='footer'>
                <p>Best regards,<br>
                <span class='team-name'>GharSewa Team</span></p>
            </div>
        </div>
    </body>
    </html>";
    
    return [
        'text' => $text_message,
        'html' => $html_message
    ];
}

/**
 * Generate password reset email subject
 * 
 * @return string Email subject
 */
function getPasswordResetSubject() {
    return "Password Reset Request - GharSewa";
}

/**
 * Test email configuration
 * 
 * @param string $test_email Email address to send test email to
 * @return array Array with 'success' boolean and 'message' string
 */
function testEmailConfiguration($test_email) {
    $subject = "Test Email - GharSewa";
    $text_message = "This is a test email to verify email functionality is working.\n\n";
    $text_message .= "If you received this email, the email configuration is working correctly.\n\n";
    $text_message .= "Best regards,\nGharSewa Team";
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Test Email - GharSewa</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 20px;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
                position: relative;
            }
            
            .header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"50\" cy=\"50\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');
                opacity: 0.3;
            }
            
            .header h1 {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 10px;
                position: relative;
                z-index: 1;
            }
            
            .header h2 {
                font-size: 1.2rem;
                font-weight: 400;
                opacity: 0.9;
                position: relative;
                z-index: 1;
            }
            
            .content {
                padding: 40px 30px;
                background: white;
            }
            
            .success-icon {
                text-align: center;
                font-size: 3rem;
                margin-bottom: 20px;
                color: #28a745;
            }
            
            .description {
                color: #666;
                margin-bottom: 20px;
                line-height: 1.7;
                text-align: center;
            }
            
            .status-box {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                text-align: center;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 25px 30px;
                text-align: center;
                color: #666;
                border-top: 1px solid #e9ecef;
            }
            
            .footer p {
                margin: 0;
                font-size: 0.9rem;
            }
            
            .footer .team-name {
                font-weight: 600;
                color: #00796b;
            }
            
            @media (max-width: 600px) {
                body {
                    padding: 10px;
                }
                
                .email-container {
                    border-radius: 8px;
                }
                
                .header {
                    padding: 25px 15px;
                }
                
                .header h1 {
                    font-size: 2rem;
                }
                
                .content {
                    padding: 25px 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>✅ Test Email</h1>
                <h2>Email Configuration Verified</h2>
            </div>
            
            <div class='content'>
                <div class='success-icon'>✅</div>
                
                <div class='description'>
                    This is a test email to verify that the email functionality is working correctly.
                </div>
                
                <div class='status-box'>
                    <strong>🎉 Success!</strong><br>
                    If you received this email, the email configuration is working perfectly.
                </div>
                
                <div class='description'>
                    Your GharSewa application is now ready to send password reset emails and other notifications.
                </div>
            </div>
            
            <div class='footer'>
                <p>Best regards,<br>
                <span class='team-name'>GharSewa Team</span></p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($test_email, $subject, $text_message, $html_message);
}

/**
 * Generate welcome email content
 * 
 * @param string $username User's username
 * @param string $role User's role (customer/provider/admin)
 * @return array Array with 'text' and 'html' versions of the email
 */
function generateWelcomeEmail($username, $role) {
    $role_display = ucfirst($role);
    $role_description = '';
    
    switch($role) {
        case 'customer':
            $role_description = 'You can now book services from our trusted providers and manage your bookings through your dashboard.';
            break;
        case 'provider':
            $role_description = 'Your account is pending approval. Once approved, you can start offering your services to customers.';
            break;
        case 'admin':
            $role_description = 'You have administrative access to manage the platform, users, and services.';
            break;
        default:
            $role_description = 'Welcome to GharSewa!';
    }
    
    $text_message = "Welcome to GharSewa, $username!\n\n";
    $text_message .= "Thank you for joining our community. Your account has been successfully created.\n\n";
    $text_message .= "Role: $role_display\n";
    $text_message .= "$role_description\n\n";
    $text_message .= "You can now log in to your account and start using our services.\n\n";
    $text_message .= "Best regards,\nGharSewa Team";
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Welcome to GharSewa</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 20px;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
                color: white;
                padding: 40px 20px;
                text-align: center;
                position: relative;
            }
            
            .header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"50\" cy=\"50\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>');
                opacity: 0.3;
            }
            
            .header h1 {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 10px;
                position: relative;
                z-index: 1;
            }
            
            .header h2 {
                font-size: 1.2rem;
                font-weight: 400;
                opacity: 0.9;
                position: relative;
                z-index: 1;
            }
            
            .welcome-icon {
                font-size: 4rem;
                margin-bottom: 20px;
                position: relative;
                z-index: 1;
            }
            
            .content {
                padding: 40px 30px;
                background: white;
            }
            
            .greeting {
                font-size: 1.3rem;
                color: #333;
                margin-bottom: 20px;
                font-weight: 600;
            }
            
            .description {
                color: #666;
                margin-bottom: 30px;
                line-height: 1.7;
            }
            
            .role-card {
                background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
                border: 1px solid #2196f3;
                border-radius: 8px;
                padding: 20px;
                margin: 25px 0;
                position: relative;
            }
            
            .role-card::before {
                content: '👤';
                position: absolute;
                top: -10px;
                left: 20px;
                background: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 1.2rem;
            }
            
            .role-title {
                font-weight: 600;
                color: #1976d2;
                margin-bottom: 10px;
                font-size: 1.1rem;
            }
            
            .role-description {
                color: #424242;
                line-height: 1.6;
            }
            
            .features-section {
                margin: 30px 0;
            }
            
            .features-title {
                font-weight: 600;
                color: #333;
                margin-bottom: 15px;
                font-size: 1.1rem;
            }
            
            .feature-list {
                list-style: none;
                padding: 0;
            }
            
            .feature-list li {
                padding: 8px 0;
                color: #666;
                position: relative;
                padding-left: 25px;
            }
            
            .feature-list li::before {
                content: '✅';
                position: absolute;
                left: 0;
                top: 8px;
            }
            
            .cta-section {
                text-align: center;
                margin: 30px 0;
                padding: 25px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            
            .cta-button {
                display: inline-block;
                padding: 12px 25px;
                background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                font-size: 0.95rem;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0, 121, 107, 0.3);
            }
            
            .cta-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 121, 107, 0.4);
            }
            
            .footer {
                background: #f8f9fa;
                padding: 25px 30px;
                text-align: center;
                color: #666;
                border-top: 1px solid #e9ecef;
            }
            
            .footer p {
                margin: 0;
                font-size: 0.9rem;
            }
            
            .footer .team-name {
                font-weight: 600;
                color: #00796b;
            }
            
            .footer .contact-info {
                margin-top: 10px;
                font-size: 0.8rem;
                color: #888;
            }
            
            @media (max-width: 600px) {
                body {
                    padding: 10px;
                }
                
                .email-container {
                    border-radius: 8px;
                }
                
                .header {
                    padding: 30px 15px;
                }
                
                .header h1 {
                    font-size: 2rem;
                }
                
                .content {
                    padding: 25px 20px;
                }
                
                .cta-button {
                    padding: 10px 20px;
                    font-size: 0.9rem;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <div class='welcome-icon'>🎉</div>
                <h1>Welcome to GharSewa!</h1>
                <h2>Your account has been successfully created</h2>
            </div>
            
            <div class='content'>
                <div class='greeting'>Hello $username,</div>
                
                <div class='description'>
                    Thank you for joining our community! We're excited to have you on board. 
                    Your account has been successfully created and you're now part of the GharSewa family.
                </div>
                
                <div class='role-card'>
                    <div class='role-title'>Your Role: $role_display</div>
                    <div class='role-description'>$role_description</div>
                </div>
                
                <div class='features-section'>
                    <div class='features-title'>What you can do now:</div>
                    <ul class='feature-list'>";
    
    if ($role === 'customer') {
        $html_message .= "
                        <li>Browse and book services from trusted providers</li>
                        <li>Manage your bookings and appointments</li>
                        <li>Rate and review service providers</li>
                        <li>Track your service history</li>";
    } elseif ($role === 'provider') {
        $html_message .= "
                        <li>Complete your profile and service details</li>
                        <li>Upload necessary documents for verification</li>
                        <li>Set your service rates and availability</li>
                        <li>Start accepting bookings once approved</li>";
    } else {
        $html_message .= "
                        <li>Manage platform users and services</li>
                        <li>Monitor system activities and reports</li>
                        <li>Handle provider verifications</li>
                        <li>Maintain platform security and quality</li>";
    }
    
    $html_message .= "
                    </ul>
                </div>
                
                <div class='cta-section'>
                    <p style='margin-bottom: 15px; color: #666;'>Ready to get started?</p>
                    <a href='http://localhost/gharsewa/pages/login.php' class='cta-button' style='color:white;' >Login to Your Account</a>
                </div>
            </div>
            
            <div class='footer'>
                <p>Best regards,<br>
                <span class='team-name'>GharSewa Team</span></p>
                <div class='contact-info'>
                    Need help? Contact us at support@gharsewa.com
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return [
        'text' => $text_message,
        'html' => $html_message
    ];
}

/**
 * Generate welcome email subject
 * 
 * @return string Email subject
 */
function getWelcomeEmailSubject() {
    return "Welcome to GharSewa! 🎉";
}

/**
 * Check if PHPMailer is available
 * 
 * @return bool True if PHPMailer is available, false otherwise
 */
function isEmailAvailable() {
    return class_exists('PHPMailer\PHPMailer\PHPMailer');
}
?> 