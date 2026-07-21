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
    <title>Forgot Password — Sewamandu</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/auth.css">
</head>
<body>
  <div class="auth-wrap">

    <!-- Cinematic image panel -->
    <aside class="auth-aside">
      <div class="auth-aside__img" aria-hidden="true"></div>
      <a href="login.php" class="auth-back"><i class="fas fa-arrow-left"></i> Back to login</a>

      <a href="home.php" class="auth-brand">Sewa<span>mandu</span></a>

      <div class="auth-aside__copy">
        <span class="eyebrow">Account recovery</span>
        <h2>Forgot your password?<br><span class="grad">we'll help you reset it</span></h2>
        <p>Enter your email and we'll send a secure link to set a new password — you'll be back in no time.</p>
      </div>

      <div class="auth-trust">
        <div><div class="num">10,000+</div><div class="lbl">Happy Customers</div></div>
        <div><div class="num">100%</div><div class="lbl">Verified Experts</div></div>
        <div><div class="num">24/7</div><div class="lbl">Booking &amp; Support</div></div>
      </div>
    </aside>

    <!-- Glass form -->
    <main class="auth-main">
      <div class="auth-card">
        <h3>Reset password</h3>
        <p class="sub">Enter your email address and we'll send you a secure reset link.</p>

        <?php if ($message): ?>
          <div class="auth-msg <?= $message_type === 'success' ? 'ok' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="forgotForm">
          <div class="auth-field">
            <label for="email">Email Address</label>
            <div class="input-shell">
              <input type="email" id="email" name="email" placeholder="you@example.com" required>
              <i class="fas fa-envelope"></i>
            </div>
          </div>

          <button type="submit" class="auth-btn" id="submitBtn">
            <span class="btn-text">Send Reset Link</span>
            <i class="fas fa-paper-plane"></i>
            <span class="btn-spin" id="loadingIcon"></span>
          </button>
        </form>

        <div class="auth-foot">
          <div class="divider">Remembered it?</div>
          <a href="login.php"><i class="fas fa-arrow-left"></i> Back to login</a>
          <p style="margin-top:14px;">Need help? <a href="mailto:sulavadhikari69@gmail.com">Contact Support</a></p>
        </div>
      </div>
    </main>

  </div>

  <script>
    document.getElementById('forgotForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        const btnText = submitBtn.querySelector('.btn-text');
        const loadingIcon = document.getElementById('loadingIcon');
        btnText.style.display = 'none';
        loadingIcon.classList.add('show');
        submitBtn.disabled = true;
    });
  </script>
</body>
</html> 