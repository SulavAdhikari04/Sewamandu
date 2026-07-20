<?php
require_once '../components/SessionManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit();
}

$error_msg = isset($_GET['error']) ? trim($_GET['error']) : 'The payment transaction was cancelled or could not be completed.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Payment Failed - Sewamandu</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/provider-dashboard.css" />
  <link rel="stylesheet" href="../css/payment.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="layout">
    <!-- Consistent Sidebar -->
    <div class="sidebar">
      <h2>Sewamandu</h2>
      <nav>
        <ul>
          <li><a href="provider-dashboard.php#overview">Dashboard</a></li>
          <li><a href="provider-dashboard.php#bookings">Bookings</a></li>
          <li><a href="provider-dashboard.php#services">My Services</a></li>
          <li><a href="provider-dashboard.php#customers">Customers</a></li>
          <li><a href="provider-dashboard.php#reviews">Reviews</a></li>
          <li><a href="provider-dashboard.php#profile">Profile</a></li>
        </ul>
      </nav>
      <div style="margin-top: 30px;">
        <a href="../components/Logout.php" class="logout-btn">Logout</a>
      </div>
    </div>

    <!-- Main Content Panel styled exactly like Dashboard -->
    <div class="main-content">
      <header>
        <h1 class="headhead">Wallet Refill Status</h1>
      </header>

      <section>
        <div class="payment-status-container" style="margin: 20px auto; border: none; box-shadow: none; padding: 20px 0;">
            <div class="payment-status-icon failure">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h2 class="payment-status-title">Top Up Failed</h2>
            <p class="payment-status-message" style="margin-bottom: 40px; max-width: 420px;"><?php echo htmlspecialchars($error_msg); ?></p>

            <div class="payment-actions">
                <a href="provider-dashboard.php#overview" class="payment-btn payment-btn-primary">
                    <i class="fa-solid fa-reply"></i> Return to Dashboard
                </a>
                <a href="#" onclick="window.history.back(); return false;" class="payment-btn payment-btn-secondary">
                    <i class="fa-solid fa-rotate-left"></i> Try Again
                </a>
            </div>
        </div>
      </section>
    </div>
  </div>
</body>
</html>
