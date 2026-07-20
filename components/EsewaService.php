<?php
/**
 * eSewa ePay v2 Service Helper
 */

if (!defined('ESEWA_PRODUCT_CODE')) define('ESEWA_PRODUCT_CODE', 'EPAYTEST');
if (!defined('ESEWA_SECRET_KEY')) define('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q');
if (!defined('ESEWA_SANDBOX_REDIRECT_URL')) define('ESEWA_SANDBOX_REDIRECT_URL', 'https://rc-epay.esewa.com.np/api/epay/main/v2/form');
if (!defined('ESEWA_SANDBOX_STATUS_URL')) define('ESEWA_SANDBOX_STATUS_URL', 'https://rc-epay.esewa.com.np/api/epay/transaction/status/');

/**
 * Ensures the esewa_payments table is present in the database.
 */
function ensureEsewaPaymentsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS esewa_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        transaction_uuid VARCHAR(100) NOT NULL UNIQUE,
        amount DECIMAL(10, 2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'initiated', -- initiated, completed, failed
        esewa_ref_id VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
}

/**
 * Generates HMAC-SHA256 signature for eSewa ePay v2.
 */
function generateEsewaSignature($total_amount, $transaction_uuid, $product_code, $secret_key) {
    // Message format: total_amount=TOTAL_AMOUNT,transaction_uuid=TRANSACTION_UUID,product_code=PRODUCT_CODE
    $message = "total_amount=" . $total_amount . ",transaction_uuid=" . $transaction_uuid . ",product_code=" . $product_code;
    $s = hash_hmac('sha256', $message, $secret_key, true);
    return base64_encode($s);
}

/**
 * Performs server-to-server validation of payment status directly with eSewa.
 */
function verifyEsewaPaymentStatus($transaction_uuid, $total_amount, $product_code) {
    $url = ESEWA_SANDBOX_STATUS_URL . "?product_code=" . urlencode($product_code) . "&total_amount=" . urlencode($total_amount) . "&transaction_uuid=" . urlencode($transaction_uuid);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass certificate checks for local development/XAMPP
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] === 'COMPLETE') {
            return [
                'success' => true,
                'transaction_code' => $data['transaction_code'] ?? null
            ];
        }
    }
    return [
        'success' => false,
        'error' => $error ?: 'Verification returned non-successful response'
    ];
}
