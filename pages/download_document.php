<?php
require_once '../components/Database.php';
$conn = getDBConnection();
$user_id = intval($_GET['user_id']);
$stmt = $conn->prepare("SELECT provider_document, provider_document_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($fileData, $fileName);
$stmt->fetch();
$stmt->close();
$conn->close();

if ($fileData) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo $fileData;
    exit;
} else {
    echo "No document found.";
} 