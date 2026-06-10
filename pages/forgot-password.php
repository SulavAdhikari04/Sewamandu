<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../components/Database.php';
require_once '../components/EmailConfig_Gmail.php';

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (!$email) {
        $message = "Please enter your email address.";
        $message_type = "error";
    } else {
        $conn = getDBConnection();
        if ($conn->connect_error) {
            $message = "Database connection failed. Please try again.";
            $message_type = "error";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id, $username);
                $stmt->fetch();
                
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                
                // Store reset token in database (no expiration)
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ? WHERE id = ?");
                $update_stmt->bind_param("si", $reset_token, $user_id);
                
                if ($update_stmt->execute()) {
                    // Send email with reset link
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $reset_token;
                    
                    $to = $email;
                    $subject = getPasswordResetSubject();
                    $email_content = generatePasswordResetEmail($username, $reset_link);
                    
                    $email_result = sendEmail($to, $subject, $email_content['text'], $email_content['html']);
                    
                    if ($email_result['success']) {
                        $message = "Password reset link has been sent to your email address.";
                        $message_type = "success";
                    } else {
                        $message = "Failed to send email: " . $email_result['message'];
                        $message_type = "error";
                    }
                } else {
                    $message = "Failed to process request. Please try again.";
                    $message_type = "error";
                }
                $update_stmt->close();
            } else {
                // Don't reveal if email exists or not for security
                $message = "If the email address exists in our system, a password reset link has been sent.";
                $message_type = "success";
            }
            $stmt->close();
            closeDBConnection($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Sewamandu</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/forgot-password.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Forgot Password</h1>
            <p>Don't worry, we'll help you reset it</p>
        </div>
        
        <div class="content">
            <div class="description">
                <p>Enter your email address below and we'll send you a secure link to reset your password.</p>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="forgotForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                    </div>
                </div>
                
                <button type="submit" class="btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i>
                    <span class="btn-text">Send Reset Link</span>
                    <i class="fas fa-spinner loading" id="loadingIcon"></i>
                </button>
            </form>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>
        </div>
        
        <div class="footer">
            <p>Need help? <a href="mailto:officialsewamandu@gmail.com">Contact Support</a></p>
        </div>
    </div>

    <script>
        document.getElementById('forgotForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const loadingIcon = document.getElementById('loadingIcon');
            
            // Show loading state
            btnText.style.display = 'none';
            loadingIcon.classList.add('show');
            submitBtn.disabled = true;
        });
    </script>
</body>
</html> 