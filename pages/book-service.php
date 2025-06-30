<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../components/SessionManager.php';
require_once '../components/Database.php';
if (session_status() === PHP_SESSION_NONE) {
session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit();
}
$conn = getDBConnection();
$message = "";

// Add cookies
setcookie('book_service_visited', 'true', time() + (86400 * 30), "/");
setcookie('book_service_count', ($_COOKIE['book_service_count'] ?? 0) + 1, time() + (86400 * 30), "/");
setcookie('book_service_user_id', $_SESSION['user_id'], time() + (86400 * 30), "/");

// Fetch services for dropdown
$services = [];
$result = $conn->query("SELECT id, name FROM services");
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

// Step 1: Show providers for selected service
$providers = [];
$show_providers = false;
$service_id = $service_date = $address = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['show_providers'])) {
    $service_id = intval($_POST['service_id']);
    $service_date = $_POST['date'];
    $address = $_POST['address'];
    // Fetch providers for this service
    $stmt = $conn->prepare("SELECT u.id, u.username, sp.price, sp.availability FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE sp.service_id = ? AND sp.status = 'approved'");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $providers[] = $row;
    }
    $show_providers = true;
    $stmt->close();
}

// Step 2: Handle booking with selected provider
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_with_provider'])) {
    $customer_id = $_SESSION['user_id']; // The logged-in customer
    $service_id = intval($_POST['service_id']);
    $provider_id = intval($_POST['provider_id']);
    $service_date = $_POST['date'];
    $address = $_POST['address'];
    $booking_date = date('Y-m-d');
    $status = 'pending_provider';

    $stmt = $conn->prepare("INSERT INTO bookings (customer_id, service_id, status, booking_date, service_date, address, provider_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssi", $customer_id, $service_id, $status, $booking_date, $service_date, $address, $provider_id);
    if ($stmt->execute()) {
        $message = "Booking request sent!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Book a Service - GharSewa</title>
  <link rel="stylesheet" href="../css/booking-style.css" />
</head>
<body>
  <a href="customer-home.php" class="text-logo">GharSewa</a>
  <div class="container">
    <h1>Book a Service</h1>
    <?php if ($message): ?>
      <p style="color: green; margin-top: 10px;">
        <?= htmlspecialchars($message) ?>
      </p>
    <?php endif; ?>
    <?php if (!$show_providers): ?>
    <form id="booking-form" method="POST" action="">
      <label for="service">Select Service:</label>
      <select id="service" name="service_id" required>
        <option value="">--Choose--</option>
        <?php foreach ($services as $service): ?>
          <option value="<?= $service['id'] ?>" <?= ($service_id == $service['id']) ? 'selected' : '' ?>><?= htmlspecialchars($service['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="date">Preferred Date:</label>
      <input type="date" id="date" name="date" value="<?= htmlspecialchars($service_date) ?>" required />

      <label for="address">Your Address:</label>
      <textarea id="address" name="address" required><?= htmlspecialchars($address) ?></textarea>

      <button type="submit" name="show_providers">Show Providers</button>
    </form>
    <?php elseif ($show_providers): ?>
      <?php if (count($providers) === 0): ?>
        <p>No providers available for this service.</p>
      <?php else: ?>
        <form method="POST" action="">
          <input type="hidden" name="service_id" value="<?= $service_id ?>">
          <input type="hidden" name="date" value="<?= htmlspecialchars($service_date) ?>">
          <input type="hidden" name="address" value="<?= htmlspecialchars($address) ?>">
          <label style="font-size: 1.2em; font-weight: 600; margin-bottom: 10px; display: block;">Select a Provider:</label>
          <div class="provider-cards">
            <?php foreach ($providers as $provider): ?>
              <label class="provider-card">
                <input type="radio" name="provider_id" value="<?= $provider['id'] ?>" required class="provider-radio">
                <div class="provider-name"><?= htmlspecialchars($provider['username']) ?></div>
                <div class="provider-price">Rs. <?= htmlspecialchars($provider['price']) ?></div>
                <div class="provider-availability">Availability: <?= htmlspecialchars($provider['availability']) ?></div>
              </label>
            <?php endforeach; ?>
          </div>
          <button type="submit" name="book_with_provider">Book</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>