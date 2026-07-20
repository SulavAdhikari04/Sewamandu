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

// Check if request is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['topup_amount'])) {
    header('Location: provider-dashboard.php');
    exit();
}

$provider_user_id = $_SESSION['user_id'];
$amount = floatval($_POST['topup_amount']);

if ($amount < 10) {
    $msg = 'Minimum top up amount is Rs. 10.';
    header('Location: provider-dashboard.php?booking_msg=' . urlencode($msg) . '#overview');
    exit();
}

// Generate unique transaction UUID
$transaction_uuid = 'tx-' . $provider_user_id . '-' . time() . '-' . rand(1000, 9999);

// Create initiated transaction in database
ensureEsewaPaymentsTable($conn);
$stmt = $conn->prepare("INSERT INTO esewa_payments (user_id, transaction_uuid, amount, status) VALUES (?, ?, ?, 'initiated')");
$stmt->bind_param("isd", $provider_user_id, $transaction_uuid, $amount);
$stmt->execute();
$stmt->close();
closeDBConnection($conn);

// Prepare UAT Form fields
$product_code = ESEWA_PRODUCT_CODE;
$secret_key = ESEWA_SECRET_KEY;
$amount_formatted = number_format($amount, 2, '.', ''); // Ensure 2 decimal places and no commas

// Construct redirect URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$success_url = $protocol . $host . $base_dir . "/payment-success.php";
$failure_url = $protocol . $host . $base_dir . "/payment-failure.php";

// Generate eSewa HMAC Signature
$signature = generateEsewaSignature($amount_formatted, $transaction_uuid, $product_code, $secret_key);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to eSewa...</title>
    <link rel="stylesheet" href="../css/provider-dashboard.css">
    <link rel="stylesheet" href="../css/payment.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="background: radial-gradient(110% 80% at 100% 0%, rgba(0, 137, 123, 0.07), transparent 55%), #eef4f2; font-family: 'Poppins', sans-serif;">
    <div class="payment-status-container" style="margin-top: 100px;">
        <div class="payment-loading">
            <div class="payment-spinner"></div>
            <h2 class="payment-status-title">Connecting to eSewa</h2>
            <p class="payment-status-message">Please do not refresh or close this window. We are redirecting you to complete the payment of Rs. <?php echo number_format($amount, 2); ?>.</p>
        </div>
    </div>

    <!-- Hidden form for eSewa POST Submission -->
    <form id="esewaForm" action="<?php echo ESEWA_SANDBOX_REDIRECT_URL; ?>" method="POST">
        <input type="hidden" id="amount" name="amount" value="<?php echo $amount_formatted; ?>" required>
        <input type="hidden" id="tax_amount" name="tax_amount" value="0" required>
        <input type="hidden" id="total_amount" name="total_amount" value="<?php echo $amount_formatted; ?>" required>
        <input type="hidden" id="transaction_uuid" name="transaction_uuid" value="<?php echo $transaction_uuid; ?>" required>
        <input type="hidden" id="product_code" name="product_code" value="<?php echo $product_code; ?>" required>
        <input type="hidden" id="product_service_charge" name="product_service_charge" value="0" required>
        <input type="hidden" id="product_delivery_charge" name="product_delivery_charge" value="0" required>
        <input type="hidden" id="success_url" name="success_url" value="<?php echo $success_url; ?>" required>
        <input type="hidden" id="failure_url" name="failure_url" value="<?php echo $failure_url; ?>" required>
        <input type="hidden" id="signed_field_names" name="signed_field_names" value="total_amount,transaction_uuid,product_code" required>
        <input type="hidden" id="signature" name="signature" value="<?php echo $signature; ?>" required>
    </form>

    <script type="text/javascript">
        // Submit the form automatically
        window.onload = function() {
            setTimeout(function() {
                document.getElementById('esewaForm').submit();
            }, 1000); // 1 second delay so they see the premium loader
        }
    </script>
</body>
</html>
