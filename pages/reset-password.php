<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../components/Database.php';

$message = "";
$message_type = "";
$token_valid = false;
$token = "";

// Check if token is provided
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    if (empty($token)) {
        $message = "Invalid reset link.";
        $message_type = "error";
    } else {
        $conn = getDBConnection();
        if ($conn->connect_error) {
            $message = "Database connection failed. Please try again.";
            $message_type = "error";
        } else {
            // Check if token exists (no expiration check)
            $stmt = $conn->prepare("SELECT id, username FROM users WHERE reset_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows === 1) {
                $token_valid = true;
                $stmt->bind_result($user_id, $username);
                $stmt->fetch();
            } else {
                $message = "Invalid reset link. Please request a new password reset.";
                $message_type = "error";
            }
            $stmt->close();
            closeDBConnection($conn);
        }
    }
} else {
    $message = "Invalid reset link.";
    $message_type = "error";
}

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!$password || !$confirm_password) {
        $message = "Please fill in all fields.";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } else {
        $conn = getDBConnection();
        if ($conn->connect_error) {
            $message = "Database connection failed. Please try again.";
            $message_type = "error";
        } else {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
            $update_stmt->bind_param("ss", $hashed_password, $token);
            
            if ($update_stmt->execute()) {
                $message = "Password has been reset successfully! You can now log in with your new password.";
                $message_type = "success";
                $token_valid = false; // Hide the form after successful reset
            } else {
                $message = "Failed to reset password. Please try again.";
                $message_type = "error";
            }
            $update_stmt->close();
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
    <title>Reset Password — Sewamandu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        <span class="eyebrow">Almost there</span>
        <h2>Set a new password &amp;<br><span class="grad">secure your account</span></h2>
        <p>Choose a strong password you'll remember. Once updated, you can sign straight back in.</p>
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
        <h3>Reset Password</h3>
        <p class="sub">Enter and confirm your new password below.</p>

        <?php if ($message): ?>
          <div class="auth-msg <?= $message_type === 'success' ? 'ok' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($token_valid): ?>
          <form method="POST" action="">
            <div class="auth-field">
              <label for="password">New Password</label>
              <div class="input-shell">
                <input type="password" id="password" name="password" placeholder="Enter new password" required>
                <i class="fas fa-lock"></i>
              </div>
              <div class="field-hint">Password must be at least 6 characters long.</div>
            </div>

            <div class="auth-field">
              <label for="confirm_password">Confirm New Password</label>
              <div class="input-shell">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                <i class="fas fa-lock"></i>
              </div>
            </div>

            <button type="submit" class="auth-btn">Reset Password <i class="fas fa-arrow-right"></i></button>
          </form>
        <?php endif; ?>

        <div class="auth-foot">
          <a href="login.php"><i class="fas fa-arrow-left"></i> Back to login</a>
        </div>
      </div>
    </main>

  </div>
</body>
</html> 