<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../components/SessionManager.php';
require_once '../components/Database.php';
require_once '../components/BookingStatus.php';
if (session_status() === PHP_SESSION_NONE) {
session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit();
}
$conn = getDBConnection();
$message = "";
$message_type = 'success';
$no_providers_message = "Unfortunately, there are no providers available for your chosen date, time, or service at the moment. We'd recommend selecting an alternative slot or contacting support for assistance.";

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
$service_id = $service_date = $service_time = $address = '';

// Pre-select a service when arriving from a "Our Services" card (?service_id=)
if ($_SERVER["REQUEST_METHOD"] !== "POST" && isset($_GET['service_id'])) {
    $service_id = intval($_GET['service_id']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['show_providers'])) {
    $service_id = intval($_POST['service_id']);
    $service_date = $_POST['date'];
    $service_time = $_POST['time'] ?? '';
    $address = $_POST['address'];
    // Fetch providers for this service
    $stmt = $conn->prepare("SELECT u.id, u.username, sp.price, sp.availability FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE sp.service_id = ? AND sp.status = 'approved'");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (isProviderTimeSlotAvailable($conn, $row['id'], $service_date, $service_time)) {
            $providers[] = $row;
        }
    }
    if (count($providers) === 0) {
        $message = $no_providers_message;
        $message_type = 'error';
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
    $service_time = $_POST['time'] ?? '';
    $address = $_POST['address'];
    $booking_date = date('Y-m-d');
    $status = 'pending_provider';

    if (!isProviderTimeSlotAvailable($conn, $provider_id, $service_date, $service_time)) {
        $message = 'This provider is already booked at the selected date and time. Please choose another provider or time slot.';
        $message_type = 'error';
        $show_providers = true;

        $stmt = $conn->prepare("SELECT u.id, u.username, sp.price, sp.availability FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE sp.service_id = ? AND sp.status = 'approved'");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (isProviderTimeSlotAvailable($conn, $row['id'], $service_date, $service_time)) {
                $providers[] = $row;
            }
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO bookings (customer_id, service_id, status, booking_date, service_date, service_time, address, provider_id, served) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)");
        $stmt->bind_param("iisssssi", $customer_id, $service_id, $status, $booking_date, $service_date, $service_time, $address, $provider_id);
        if ($stmt->execute()) {
            $message = "Booking request sent!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Service — Sewamandu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/auth.css" />
  <link rel="stylesheet" href="../css/booking.css" />
</head>
<body>
  <div class="book-bg" aria-hidden="true"></div>

  <div class="book-topbar">
    <a href="customer-home.php" class="brand">Sewa<span>mandu</span></a>
    <a href="customer-home.php" class="book-back"><i class="fas fa-arrow-left"></i> Back to home</a>
  </div>

  <div class="book-wrap">
    <div class="book-card">
      <div class="book-head">
        <span class="eyebrow"><?= $show_providers ? 'Step 2 of 2' : 'Step 1 of 2' ?></span>
        <h1>Book a Service</h1>
        <p><?= $show_providers ? 'Choose a provider to confirm your booking.' : 'Tell us what you need and when — we\'ll find the right expert.' ?></p>
      </div>

      <?php if ($message): ?>
        <div class="auth-msg <?= $message_type === 'error' ? 'error' : 'ok' ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <?php if (!$show_providers): ?>
      <form id="booking-form" method="POST" action="">
        <div class="auth-field">
          <label for="service">Select Service</label>
          <div class="input-shell">
            <select id="service" name="service_id" required>
              <option value="">— Choose a service —</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= $service['id'] ?>" <?= ($service_id == $service['id']) ? 'selected' : '' ?>><?= htmlspecialchars($service['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-screwdriver-wrench"></i>
          </div>
        </div>

        <div class="auth-field">
          <label for="date">Preferred Date</label>
          <div class="input-shell">
            <select id="date" name="date" required>
              <option value="">— Choose a date —</option>
              <?php foreach (getBookableDateOptions() as $opt): ?>
                <option value="<?= $opt['value'] ?>" <?= ($service_date === $opt['value']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($opt['caption'] . ' · ' . $opt['day'] . ' ' . $opt['month']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-calendar-day"></i>
          </div>
        </div>

        <div class="auth-field">
          <label for="time">Preferred Time</label>
          <?php
            // 30-minute slots from 8:00 AM to 6:00 PM, grouped by period.
            $time_periods = ['Morning' => [], 'Afternoon' => [], 'Evening' => []];
            for ($hour = 8; $hour <= 18; $hour++) {
              foreach ([0, 30] as $minute) {
                if ($hour === 18 && $minute > 0) break; // stop at 6:00 PM
                $time_value = sprintf('%02d:%02d', $hour, $minute);
                $slot = ['value' => $time_value, 'label' => date('g:i A', strtotime($time_value))];
                if ($hour < 12)       { $time_periods['Morning'][]   = $slot; }
                elseif ($hour < 17)   { $time_periods['Afternoon'][] = $slot; }
                else                  { $time_periods['Evening'][]   = $slot; }
              }
            }
          ?>
          <div class="input-shell">
            <select id="time" name="time" required>
              <option value="">— Choose a time —</option>
              <?php foreach ($time_periods as $period_name => $slots): ?>
                <?php if (empty($slots)) continue; ?>
                <optgroup label="<?= $period_name ?>">
                  <?php foreach ($slots as $slot): ?>
                    <option value="<?= $slot['value'] ?>" <?= ($service_time === $slot['value']) ? 'selected' : '' ?>><?= $slot['label'] ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-clock"></i>
          </div>
        </div>

        <div class="auth-field">
          <label for="address">Your Address</label>
          <textarea id="address" name="address" placeholder="Street, area, city…" required><?= htmlspecialchars($address) ?></textarea>
        </div>

        <button type="submit" name="show_providers" class="auth-btn">Show Providers <i class="fas fa-arrow-right"></i></button>
      </form>
      <?php elseif ($show_providers): ?>
        <?php if (count($providers) === 0): ?>
          <?php if (!$message): ?>
            <div class="auth-msg error"><?= htmlspecialchars($no_providers_message) ?></div>
          <?php endif; ?>
          <a href="book-service.php" class="auth-btn" style="text-decoration:none;"><i class="fas fa-arrow-left"></i> Try another slot</a>
        <?php else: ?>
          <form method="POST" action="">
            <input type="hidden" name="service_id" value="<?= $service_id ?>">
            <input type="hidden" name="date" value="<?= htmlspecialchars($service_date) ?>">
            <input type="hidden" name="time" value="<?= htmlspecialchars($service_time) ?>">
            <input type="hidden" name="address" value="<?= htmlspecialchars($address) ?>">
            <span class="provider-label">Select a Provider</span>
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
            <button type="submit" name="book_with_provider" class="auth-btn">Confirm Booking <i class="fas fa-check"></i></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>