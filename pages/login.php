<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../components/Database.php';
require_once '../components/OTP.php';
require_once '../components/TrustedDevice.php';
require_once '../components/TwoFactorAuth.php';
// Auto-login using cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    $conn = getDBConnection();
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $_COOKIE['user_id']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $username, $role);
            $stmt->fetch();
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
        }
        $stmt->close();
        closeDBConnection($conn);
    }
}
// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
if (isset($_GET['registered'])) {
    $message = "Registration successful! Please log in.";
}
if (isset($_GET['expired'])) {
    $message = "Your session has expired. Please log in again.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!$email || !$password) {
        $message = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $username, $hashed_password, $role, $user_email);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                $remember = isset($_POST['remember']);
                ensureTwoFactorColumn($conn);

                if (!userRequiresLoginOtp($conn, $id) || isDeviceTrustedForUser($id)) {
                    $stmt->close();
                    closeDBConnection($conn);
                    completeUserLogin($id, $username, $role, $remember);
                }

                $payload = [
                    'user_id'  => $id,
                    'username' => $username,
                    'role'     => $role,
                    'remember' => $remember ? 1 : 0,
                ];
                $code = startOtpSession('login', $user_email, $username, $payload);
                $send = sendLoginVerificationCode($user_email, $username, $code);
                if (!empty($send['success'])) {
                    $stmt->close();
                    closeDBConnection($conn);
                    header('Location: verify-otp.php');
                    exit();
                } else {
                    unset($_SESSION['pending_otp']);
                    $message = "Could not send verification code. Please try again.";
                }
            } else {
                $message = "Invalid email or password.";
            }
        } else {
            $message = "Invalid email or password.";
        }
        $stmt->close();
    }
}
closeDBConnection($conn);

// Treat "success"/"successful" messages as positive, everything else as an error.
$messageClass = (stripos($message, 'success') !== false) ? 'ok' : 'error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Sewamandu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/auth.css">
</head>
<body>
  <div class="auth-wrap">

    <!-- Cinematic image panel -->
    <aside class="auth-aside">
      <div class="auth-aside__img" aria-hidden="true"></div>
      <a href="home.php" class="auth-back"><i class="fas fa-arrow-left"></i> Back to home</a>

      <a href="home.php" class="auth-brand">Sewa<span>mandu</span></a>

      <div class="auth-aside__copy">
        <span class="eyebrow">Welcome back</span>
        <h2>Your trusted home services,<br><span class="grad">just a login away</span></h2>
        <p>Book vetted plumbers, electricians, cleaners and more across Kathmandu, Lalitpur &amp; Bhaktapur.</p>
      </div>

      <div class="auth-trust">
        <div><div class="num">10,000+</div><div class="lbl">Happy Customers</div></div>
        <div><div class="num">100%</div><div class="lbl">Verified Experts</div></div>
        <div><div class="num">24/7</div><div class="lbl">Booking &amp; Support</div></div>
      </div>
    </aside>

    <!-- Glass login form -->
    <main class="auth-main">
      <div class="auth-card">
        <h3>Login</h3>
        <p class="sub">Sign in to manage your bookings and services.</p>

        <?php if (!empty($message)): ?>
          <div class="auth-msg <?= $messageClass ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form id="login-form" method="POST" action="">
          <div class="auth-field">
            <label for="login-email">Email</label>
            <div class="input-shell">
              <input type="email" id="login-email" name="email" placeholder="you@example.com" required>
              <i class="fas fa-envelope"></i>
            </div>
          </div>

          <div class="auth-field">
            <label for="login-password">Password</label>
            <div class="input-shell">
              <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
              <i class="fas fa-lock"></i>
            </div>
          </div>

          <div class="auth-row">
            <label class="auth-remember"><input type="checkbox" name="remember"> Remember me</label>
            <a href="forgot-password.php">Forgot password?</a>
          </div>

          <button type="submit" class="auth-btn">Login <i class="fas fa-arrow-right"></i></button>
        </form>

        <div class="auth-foot">
          <div class="divider">New here?</div>
          Don't have an account? <a href="register.php">Create one</a>
        </div>
      </div>
    </main>

  </div>
</body>
</html>