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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <title>Reset Password - GharSewa</title>
    <link rel="stylesheet" href="../css/reset-password.css">
</head>
<body>
    <header>
        <div class="container">
            <a href="home.php"><h1>GharSewa</h1></a>
        </div>
    </header>
    
    <section class="reset-password-section">
        <h3>Reset Password</h3>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($token_valid): ?>
            <p>Please enter your new password below.</p>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required>
                    <div class="password-requirements">Password must be at least 6 characters long</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                </div>
                
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
        
        <div class="back-to-login">
            <a href="login.php">Back to Login</a>
        </div>
    </section>
</body>
</html> 