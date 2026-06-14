<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../components/Database.php';
require_once '../components/OTP.php';
require_once '../components/TrustedDevice.php';

// No pending verification -> back to login
if (!isset($_SESSION['pending_otp'])) {
    header('Location: login.php');
    exit();
}

$pending = $_SESSION['pending_otp'];
$context = $pending['context'];
$message = '';
$messageClass = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Resend a fresh code
    if (isset($_POST['resend'])) {
        $purpose = $context === 'register' ? 'complete your registration' : 'sign in to your account';
        $code = startOtpSession($context, $pending['email'], $pending['name'], $pending['payload']);
        $send = sendOtpEmail($pending['email'], $pending['name'], $code, $purpose);
        $pending = $_SESSION['pending_otp'];
        if (!empty($send['success'])) {
            $message = 'A new code has been sent to your email.';
            $messageClass = 'ok';
        } else {
            $message = 'Could not send the code. Please try again.';
        }
    } else {
        $entered = trim($_POST['otp'] ?? '');

        if (time() > $pending['expires']) {
            $message = 'This code has expired. Please request a new one.';
        } elseif ($pending['attempts'] >= OTP_MAX_ATTEMPTS) {
            $message = 'Too many incorrect attempts. Please request a new code.';
        } elseif ($entered === '' || !preg_match('/^\d{6}$/', $entered)) {
            $message = 'Please enter the 6-digit code.';
            $_SESSION['pending_otp']['attempts']++;
        } elseif (!hash_equals($pending['code'], $entered)) {
            $message = 'Incorrect code. Please try again.';
            $_SESSION['pending_otp']['attempts']++;
        } else {
            // Code is correct -> finish the action
            $conn = getDBConnection();

            if ($context === 'register') {
                $d = $pending['payload'];
                // Guard against a duplicate created in the meantime
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $d['email']);
                $stmt->execute();
                $stmt->store_result();
                $exists = $stmt->num_rows > 0;
                $stmt->close();

                if ($exists) {
                    unset($_SESSION['pending_otp']);
                    closeDBConnection($conn);
                    header('Location: login.php?registered=1');
                    exit();
                }

                if ($d['role'] === 'provider') {
                    $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, role, provider_document, provider_document_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param("sssssss", $d['name'], $d['email'], $d['phone'], $d['password'], $d['role'], $d['file_data'], $d['file_name']);
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("sssss", $d['name'], $d['email'], $d['phone'], $d['password'], $d['role']);
                }

                if ($stmt->execute()) {
                    $newUserId = (int) $conn->insert_id;
                    $stmt->close();
                    trustDeviceForUser($newUserId);
                    // Welcome email (best effort)
                    $welcome = generateWelcomeEmail($d['name'], $d['role']);
                    sendEmail($d['email'], getWelcomeEmailSubject(), $welcome['text'], $welcome['html']);
                    unset($_SESSION['pending_otp']);
                    closeDBConnection($conn);
                    header('Location: login.php?registered=1');
                    exit();
                } else {
                    $message = 'Error creating account: ' . $stmt->error;
                    $stmt->close();
                }
            } else { // login
                $d = $pending['payload'];
                unset($_SESSION['pending_otp']);
                closeDBConnection($conn);
                completeUserLogin($d['user_id'], $d['username'], $d['role'], !empty($d['remember']));
            }
            closeDBConnection($conn);
        }
    }
}

$masked = maskEmail($pending['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Code — Sewamandu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/auth.css">
  <style>
    .otp-input{letter-spacing:10px;text-align:center;font-size:1.3rem;font-weight:600;}
    .otp-resend{background:none;border:none;color:#00796b;font-weight:600;cursor:pointer;padding:0;font:inherit;}
    .otp-resend:hover{text-decoration:underline;}
    .otp-actions{display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:0.92rem;}
  </style>
</head>
<body>
  <div class="auth-wrap">
    <aside class="auth-aside">
      <div class="auth-aside__img" aria-hidden="true"></div>
      <a href="login.php" class="auth-back"><i class="fas fa-arrow-left"></i> Back to login</a>
      <a href="home.php" class="auth-brand">Sewa<span>mandu</span></a>
      <div class="auth-aside__copy">
        <span class="eyebrow">Almost there</span>
        <h2>Verify it's really you,<br><span class="grad">check your inbox</span></h2>
        <p>We sent a 6-digit verification code to your email to keep your account secure.</p>
      </div>
      <div class="auth-trust">
        <div><div class="num">10,000+</div><div class="lbl">Happy Customers</div></div>
        <div><div class="num">100%</div><div class="lbl">Verified Experts</div></div>
        <div><div class="num">24/7</div><div class="lbl">Booking &amp; Support</div></div>
      </div>
    </aside>

    <main class="auth-main">
      <div class="auth-card">
        <h3>Enter verification code</h3>
        <p class="sub">We emailed a 6-digit code to <strong><?= htmlspecialchars($masked) ?></strong>.</p>

        <?php if (!empty($message)): ?>
          <div class="auth-msg <?= $messageClass ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="auth-field">
            <label for="otp">Verification code</label>
            <div class="input-shell">
              <input type="text" id="otp" name="otp" class="otp-input" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="------" autocomplete="one-time-code" required>
              <i class="fas fa-shield-halved"></i>
            </div>
          </div>
          <button type="submit" class="auth-btn">Verify <i class="fas fa-arrow-right"></i></button>
        </form>

        <form method="POST" action="">
          <div class="otp-actions">
            <span>Didn't get the code?</span>
            <button type="submit" name="resend" value="1" class="otp-resend">Resend code</button>
          </div>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
