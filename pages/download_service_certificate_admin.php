<?php
require_once '../components/SessionManager.php';
require_once '../components/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

if (!isset($_GET['service_provider_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Service Provider ID is required');
}

$conn = getDBConnection();
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$service_provider_id = intval($_GET['service_provider_id']);

// Fetch the certificate for the specific service provider
$stmt = $conn->prepare("SELECT provider_certificate FROM service_providers WHERE id = ?");
$stmt->bind_param("i", $service_provider_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $certificate_data = $row['provider_certificate'];
    
    if (!empty($certificate_data)) {
        // Set appropriate headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="service_certificate_' . $service_provider_id . '.pdf"');
        header('Content-Length: ' . strlen($certificate_data));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        // Output the certificate data
        echo $certificate_data;
    } else {
        header('HTTP/1.1 404 Not Found');
        echo 'Certificate not found';
    }
} else {
    header('HTTP/1.1 404 Not Found');
    echo 'Service not found';
}

$stmt->close();
$conn->close();
?> 