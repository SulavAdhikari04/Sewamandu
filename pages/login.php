<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../components/Database.php';
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
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $username, $hashed_password, $role);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                // Set session variables if needed
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                // Remember Me
                if (isset($_POST['remember'])) {
                    setcookie('user_id', $id, time() + (86400 * 30), "/"); // 30 days
                }
                // Redirect based on role
                if ($role === 'admin') {
                    header('Location: admin-dashboard.php');
                    exit();
                } elseif ($role === 'customer') {
                    header('Location: customer-home.php');
                    exit();
                } elseif ($role === 'provider') {
                    header('Location: provider-dashboard.php');
                    exit();
                } else {
                    $message = "Unknown user role.";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <meta charset="UTF-8">
  <title>Login - GharSewa</title>
  <link rel="stylesheet" href="../css/home.css">
</head>
<body>
  <header>
    <div class="container">
      <a href="home.php"></s><h1>GharSewa </h1></a>
    </div>
  </header>
  <section id="login" class="login-section">
    <h3>Login</h3>
    <form id="login-form" method="POST" action="">
      <label for="login-email">Email:</label>
      <input type="email" id="login-email" name="email" placeholder="Enter your email" required>

      <label for="login-password">Password:</label>
      <input type="password" id="login-password" name="password" placeholder="Enter password" required >

      <label><input type="checkbox" name="remember"> Remember Me</label>

      <button type="submit">Login</button>
    </form>
    <p style="color: <?= strpos($message, 'success') !== false ? 'green' : 'red' ?>; margin-top: 10px;">
      <?= htmlspecialchars($message) ?>
    </p>
    <p>Don't have an account? <a href="register.php">Register here</a></p>
    <p><a href="forgot-password.php">Forgot your password?</a></p>
  </section>
</body>
</html>