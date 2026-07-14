<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../components/Database.php';
require_once '../components/EmailConfig_Gmail.php';
require_once '../components/OTP.php';
require_once '../components/StringHelpers.php';
// Database connection
$conn = getDBConnection();

$message = "";
$error = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = formatDisplayName($_POST['name'] ?? '');
    $email = trim($_POST['email']);
    $email=strtolower($email);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm-password'];
    $role = $_POST['role'];

    // Only for providers: handle file upload
    $fileData = null;
    $fileName = null;
    if ($role === 'provider' && isset($_FILES['provider_document']) && $_FILES['provider_document']['error'] === UPLOAD_ERR_OK) {
        $fileData = file_get_contents($_FILES['provider_document']['tmp_name']);
        $fileName = $_FILES['provider_document']['name'];
    }

    if (!preg_match("/^[a-z._%+-]+@[a-z.-]+\.[a-z]{2,}$/", $email)) {
       $error[] = 'Invalid email format.';
   }

    if (!$name || !$email || !$phone || !$password || !$confirmPassword || !$role) {
        $message = "Please fill in all fields.";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
    } else {
        // Check for duplicate email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = "Email already registered.";
            $stmt->close();
        } else {
            $stmt->close();
            // Hash password and stash everything until the email OTP is verified
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $payload = [
                'name'      => $name,
                'email'     => $email,
                'phone'     => $phone,
                'password'  => $hashed_password,
                'role'      => $role,
                'file_data' => $fileData,
                'file_name' => $fileName,
            ];
            $code = startOtpSession('register', $email, $name, $payload);
            $send = sendOtpEmail($email, $name, $code, 'complete your registration');
            if (!empty($send['success'])) {
                closeDBConnection($conn);
                header('Location: verify-otp.php');
                exit();
            } else {
                unset($_SESSION['pending_otp']);
                $message = "Could not send verification code. Please try again.";
            }
        }
    }
}
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Sewamandu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/auth.css" />
  <link rel="stylesheet" href="../css/form-utils.css" />
</head>
<body>
  <div class="auth-wrap">

    <!-- Cinematic image panel -->
    <aside class="auth-aside">
      <div class="auth-aside__img" aria-hidden="true"></div>
      <a href="home.php" class="auth-back"><i class="fas fa-arrow-left"></i> Back to home</a>

      <a href="home.php" class="auth-brand">Sewa<span>mandu</span></a>

      <div class="auth-aside__copy">
        <span class="eyebrow">Join us</span>
        <h2>Create your account &amp;<br><span class="grad">get help in minutes</span></h2>
        <p>Sign up as a customer to book trusted experts, or as a provider to grow your business across the valley.</p>
      </div>

      <div class="auth-trust">
        <div><div class="num">10,000+</div><div class="lbl">Happy Customers</div></div>
        <div><div class="num">100%</div><div class="lbl">Verified Experts</div></div>
        <div><div class="num">24/7</div><div class="lbl">Booking &amp; Support</div></div>
      </div>
    </aside>

    <!-- Glass register form -->
    <main class="auth-main">
      <div class="auth-card auth-card--wide">
        <h3>Create account</h3>
        <p class="sub">It only takes a minute to get started.</p>

        <?php if (!empty($error)): ?>
          <?php foreach ($error as $err): ?>
            <div class="auth-msg error"><?= htmlspecialchars($err) ?></div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
          <div class="auth-msg <?= stripos($message, 'success') !== false ? 'ok' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form id="register-form" method="POST" action="" enctype="multipart/form-data">
          <div class="auth-field">
            <label for="name">Full Name</label>
            <div class="input-shell">
              <input type="text" id="name" name="name" placeholder="Enter your name" data-capitalize="words" autocomplete="name" required />
              <i class="fas fa-user"></i>
            </div>
          </div>

          <div class="auth-field">
            <label for="email">Email</label>
            <div class="input-shell">
              <input type="email" id="email" name="email" placeholder="you@example.com" pattern="[a-zA-Z._%+\-]+@[a-zA-Z.\-]+\.[a-zA-Z]{2,}" title="Enter a valid email (e.g. you@example.com)" required />
              <i class="fas fa-envelope"></i>
            </div>
          </div>

          <div class="auth-field">
            <label for="phone">Phone Number</label>
            <div class="input-shell">
              <input type="text" id="phone" name="phone" placeholder="97/98XXXXXXXX" pattern="9[78][0-9]{8}" title="Phone number must start with 97 or 98 and be 10 digits long" required />
              <i class="fas fa-phone"></i>
            </div>
          </div>

          <div class="auth-field">
            <label for="password">Password</label>
            <div class="input-shell">
              <input type="password" id="password" name="password" placeholder="Create a password" required minlength="8" />
              <i class="fas fa-lock"></i>
            </div>
          </div>

          <div class="auth-field">
            <label for="confirm-password">Confirm Password</label>
            <div class="input-shell">
              <input type="password" id="confirm-password" name="confirm-password" placeholder="Re-enter password" required minlength="8" />
              <i class="fas fa-lock"></i>
            </div>
          </div>

          <div class="auth-field">
            <label for="role">I am a</label>
            <div class="input-shell">
              <select id="role" name="role" required>
                <option value="">Select your role</option>
                <option value="customer">Customer</option>
                <option value="provider">Service Provider</option>
                <!-- <option value="admin">Admin</option> --> //add this option only if you want to add admin role
              </select>
              <i class="fas fa-id-badge"></i>
            </div>
          </div>

          <div class="auth-file" id="provider-doc-upload" style="display:none;">
            <label class="file-label" for="provider_document">Attach verification document (for providers)</label>
            <input type="file" id="provider_document" name="provider_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
          </div>

          <button type="submit" class="auth-btn" style="margin-top:6px;">Register <i class="fas fa-arrow-right"></i></button>
        </form>

        <div class="auth-foot">
          <div class="divider">Already a member?</div>
          Already have an account? <a href="login.php">Sign in here</a>
        </div>
      </div>
    </main>

  </div>

  <script src="../js/auto-capitalize.js"></script>
  <script>
  document.getElementById('role').addEventListener('change', function() {
    var docUpload = document.getElementById('provider-doc-upload');
    docUpload.style.display = (this.value === 'provider') ? 'block' : 'none';
  });
  </script>
</body>
</html>