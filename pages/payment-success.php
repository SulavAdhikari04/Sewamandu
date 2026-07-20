<?php
require_once '../components/SessionManager.php';
require_once '../components/Database.php';
require_once '../components/EsewaService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$provider_user_id = $_SESSION['user_id'];

// Check for data param
if (!isset($_GET['data'])) {
    header('Location: payment-failure.php?error=Missing+transaction+payload');
    exit();
}

$decoded_json = base64_decode($_GET['data']);
if (!$decoded_json) {
    header('Location: payment-failure.php?error=Invalid+payload+format');
    exit();
}

$payload = json_decode($decoded_json, true);
if (!$payload || !isset($payload['transaction_uuid'], $payload['total_amount'], $payload['status'])) {
    header('Location: payment-failure.php?error=Invalid+transaction+data');
    exit();
}

if ($payload['status'] !== 'COMPLETE') {
    header('Location: payment-failure.php?error=Transaction+status+not+completed');
    exit();
}

$transaction_uuid = $payload['transaction_uuid'];
$total_amount = $payload['total_amount'];
$product_code = $payload['product_code'] ?? ESEWA_PRODUCT_CODE;

// Remove commas from amount format if any
$total_amount_clean = str_replace(',', '', $total_amount);
$total_amount_float = floatval($total_amount_clean);

// Check if transaction exists in database
$stmt = $conn->prepare("SELECT id, user_id, amount, status, esewa_ref_id FROM esewa_payments WHERE transaction_uuid = ?");
$stmt->bind_param("s", $transaction_uuid);
$stmt->execute();
$stmt->bind_result($tx_id, $tx_user_id, $tx_amount, $tx_status, $esewa_ref_id);

if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: payment-failure.php?error=Transaction+not+found+in+records');
    exit();
}
$stmt->close();

// Ensure the logged in user owns this transaction
if ($tx_user_id !== $provider_user_id) {
    header('Location: payment-failure.php?error=Unauthorized+transaction+access');
    exit();
}

$credited_amount = $tx_amount;

// Perform verification depending on database state
if ($tx_status === 'completed') {
    // Already processed, just show receipt details
    $final_ref_id = $esewa_ref_id;
} else if ($tx_status === 'failed') {
    header('Location: payment-failure.php?error=Transaction+was+already+marked+failed');
    exit();
} else {
    // Perform server-to-server validation directly with eSewa
    $verification = verifyEsewaPaymentStatus($transaction_uuid, $total_amount_clean, $product_code);
    if ($verification['success']) {
        $final_ref_id = $verification['transaction_code'];
        
        $conn->begin_transaction();
        try {
            // Update transaction status
            $update_tx = $conn->prepare("UPDATE esewa_payments SET status = 'completed', esewa_ref_id = ? WHERE id = ?");
            $update_tx->bind_param("si", $final_ref_id, $tx_id);
            $update_tx->execute();
            $update_tx->close();
            
            // Update user wallet balance
            $update_user = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $update_user->bind_param("di", $tx_amount, $provider_user_id);
            $update_user->execute();
            $update_user->close();
            
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            header('Location: payment-failure.php?error=Failed+to+update+account+balance');
            exit();
        }
    } else {
        // Mark transaction as failed in database
        $update_tx = $conn->prepare("UPDATE esewa_payments SET status = 'failed' WHERE id = ?");
        $update_tx->bind_param("i", $tx_id);
        $update_tx->execute();
        $update_tx->close();
        
        header('Location: payment-failure.php?error=' . urlencode($verification['error']));
        exit();
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Payment Successful - Sewamandu</title>
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
            <div class="payment-status-icon success">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h2 class="payment-status-title">Top Up Successful!</h2>
            <p class="payment-status-message">Your wallet has been successfully topped up via eSewa. The new balance is updated and ready to be used.</p>

            <div class="payment-receipt">
                <div class="receipt-row">
                    <span class="receipt-label">Refill Method</span>
                    <span class="receipt-value">eSewa ePay</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Transaction UUID</span>
                    <span class="receipt-value"><?php echo htmlspecialchars($transaction_uuid); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">eSewa Reference ID</span>
                    <span class="receipt-value"><?php echo htmlspecialchars($final_ref_id); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Top Up Amount</span>
                    <span class="receipt-value amount">Rs. <?php echo number_format($credited_amount, 2); ?></span>
                </div>
            </div>

            <div class="payment-actions">
                <a href="provider-dashboard.php#overview" class="payment-btn payment-btn-primary">
                    <i class="fa-solid fa-chart-line"></i> Go to Dashboard
                </a>
            </div>
        </div>
      </section>
    </div>
  </div>
</body>
</html>
