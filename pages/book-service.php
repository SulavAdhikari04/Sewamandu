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
// Ensure the availability toggle column exists (MariaDB supports IF NOT EXISTS)
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_available TINYINT(1) NOT NULL DEFAULT 1");
ensureBookingLocationColumns($conn);
ensureBookingGroupColumn($conn);

function nominatimRequest($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Sewamandu/1.0 (local booking app)',
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }
        return json_decode($body, true);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Sewamandu/1.0 (local booking app)\r\nAccept: application/json\r\n",
            'timeout' => 8,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return null;
    }
    return json_decode($body, true);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'place_search') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode([]);
        closeDBConnection($conn);
        exit();
    }
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $q,
        'format' => 'json',
        'addressdetails' => 1,
        'limit' => 6,
        'countrycodes' => 'np',
    ]);
    $data = nominatimRequest($url);
    $results = [];
    if (is_array($data)) {
        foreach ($data as $row) {
            $results[] = [
                'label' => $row['display_name'] ?? '',
                'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
                'lng' => isset($row['lon']) ? (float) $row['lon'] : null,
            ];
        }
    }
    echo json_encode($results);
    closeDBConnection($conn);
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'reverse_geocode') {
    header('Content-Type: application/json');
    $coords = parseBookingCoordinates($_GET['lat'] ?? null, $_GET['lng'] ?? null);
    if (!$coords) {
        echo json_encode(['error' => 'Invalid coordinates.']);
        closeDBConnection($conn);
        exit();
    }
    $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
        'lat' => $coords['latitude'],
        'lon' => $coords['longitude'],
        'format' => 'json',
    ]);
    $data = nominatimRequest($url);
    echo json_encode([
        'label' => is_array($data) ? ($data['display_name'] ?? '') : '',
    ]);
    closeDBConnection($conn);
    exit();
}

function enrichProvidersWithStats($conn, array &$providers) {
    if (empty($providers)) {
        return;
    }
    $ids = array_map('intval', array_column($providers, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $index = [];

    foreach ($providers as $i => $provider) {
        $providers[$i]['avg_rating'] = 0;
        $providers[$i]['review_count'] = 0;
        $providers[$i]['completed_services'] = 0;
        $index[(int) $provider['id']] = $i;
    }

    $sql = "SELECT provider_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
            FROM reviews WHERE provider_id IN ($placeholders) GROUP BY provider_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $i = $index[(int) $row['provider_id']];
        $providers[$i]['avg_rating'] = round((float) $row['avg_rating'], 1);
        $providers[$i]['review_count'] = (int) $row['review_count'];
    }
    $stmt->close();

    $sql = "SELECT provider_id, COUNT(*) AS completed_services
            FROM bookings WHERE provider_id IN ($placeholders) AND status = 'completed' GROUP BY provider_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $i = $index[(int) $row['provider_id']];
        $providers[$i]['completed_services'] = (int) $row['completed_services'];
    }
    $stmt->close();
}

function fetchAvailableProviders($conn, $service_id, $service_date, $service_time) {
    $providers = [];
    $stmt = $conn->prepare(
        "SELECT u.id, u.username, sp.price, sp.availability
         FROM service_providers sp
         JOIN users u ON sp.user_id = u.id
         WHERE sp.service_id = ? AND sp.status = 'approved' AND u.is_available = 1"
    );
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (isProviderTimeSlotAvailable($conn, $row['id'], $service_date, $service_time)) {
            $providers[] = $row;
        }
    }
    $stmt->close();
    enrichProvidersWithStats($conn, $providers);
    return $providers;
}

function renderStarRatingHtml($avg_rating, $review_count) {
    if ($review_count <= 0) {
        return '<span class="provider-rating-none">No ratings yet</span>';
    }
    $filled = max(0, min(5, (int) round($avg_rating)));
    $stars = str_repeat('<i class="fas fa-star"></i>', $filled)
        . str_repeat('<i class="far fa-star"></i>', 5 - $filled);
    return '<span class="provider-stars" aria-label="' . htmlspecialchars((string) $avg_rating) . ' out of 5 stars">'
        . $stars . '</span><span class="provider-rating-value">' . number_format($avg_rating, 1) . '</span>';
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'provider_profile') {
    header('Content-Type: application/json');
    $provider_id = intval($_GET['provider_id'] ?? 0);
    if ($provider_id <= 0) {
        echo json_encode(['error' => 'Invalid provider.']);
        closeDBConnection($conn);
        exit();
    }

    $profile = [
        'username' => '',
        'phone' => '',
        'address' => '',
        'profile_picture' => '',
        'avg_rating' => 0,
        'review_count' => 0,
        'completed_services' => 0,
        'reviews' => [],
    ];

    $stmt = $conn->prepare("SELECT username, phone FROM users WHERE id = ? AND role = 'provider'");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['error' => 'Provider not found.']);
        closeDBConnection($conn);
        exit();
    }

    $profile['username'] = $user['username'];
    $profile['phone'] = $user['phone'] ?? '';

    $stmt = $conn->prepare("SELECT address, profile_picture FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $profile['address'] = $row['address'] ?? '';
        $profile['profile_picture'] = $row['profile_picture'] ?? '';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE provider_id = ?");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $profile['avg_rating'] = round((float) ($row['avg_rating'] ?? 0), 1);
        $profile['review_count'] = (int) ($row['review_count'] ?? 0);
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS completed_services FROM bookings WHERE provider_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $profile['completed_services'] = (int) ($row['completed_services'] ?? 0);
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT r.rating, r.comment, r.created_at,
                CASE WHEN r.show_name = 1 THEN u.username ELSE 'Anonymous' END AS customer_name,
                s.name AS service_name
         FROM reviews r
         LEFT JOIN users u ON r.customer_id = u.id
         LEFT JOIN services s ON r.service_id = s.id
         WHERE r.provider_id = ?
         ORDER BY r.created_at DESC
         LIMIT 20"
    );
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $profile['reviews'][] = $row;
    }
    $stmt->close();

    echo json_encode($profile);
    closeDBConnection($conn);
    exit();
}

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
$latitude = $longitude = $location_label = '';

// Pre-select a service when arriving from a "Our Services" card (?service_id=)
if ($_SERVER["REQUEST_METHOD"] !== "POST" && isset($_GET['service_id'])) {
    $service_id = intval($_GET['service_id']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['show_providers'])) {
    $service_id = intval($_POST['service_id']);
    $service_date = $_POST['date'];
    $service_time = $_POST['time'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $location_label = trim($_POST['location_label'] ?? '');
    $coords = parseBookingCoordinates($latitude, $longitude);

    if (!$coords) {
        $message = 'Please pin your service location on the map or search for a place.';
        $message_type = 'error';
    } else {
        $latitude = (string) $coords['latitude'];
        $longitude = (string) $coords['longitude'];
        $providers = fetchAvailableProviders($conn, $service_id, $service_date, $service_time);
        if (count($providers) === 0) {
            $message = $no_providers_message;
            $message_type = 'error';
        }
        $show_providers = true;
    }
}

// Step 2: Handle booking with one or more selected providers (first to accept wins)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_with_provider'])) {
    $customer_id = $_SESSION['user_id'];
    $service_id = intval($_POST['service_id']);
    $raw_providers = $_POST['provider_ids'] ?? ($_POST['provider_id'] ?? []);
    if (!is_array($raw_providers)) {
        $raw_providers = [$raw_providers];
    }
    $provider_ids = array_values(array_unique(array_filter(array_map('intval', $raw_providers))));
    $service_date = $_POST['date'];
    $service_time = $_POST['time'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $location_label = trim($_POST['location_label'] ?? '');
    $coords = parseBookingCoordinates($latitude, $longitude);
    $booking_date = date('Y-m-d');
    $status = 'pending_provider';

    if (!$coords) {
        $message = 'Service location is missing. Please go back and pin a location on the map.';
        $message_type = 'error';
    } elseif (count($provider_ids) === 0) {
        $message = 'Please select at least one provider.';
        $message_type = 'error';
        $show_providers = true;
        $providers = fetchAvailableProviders($conn, $service_id, $service_date, $service_time);
    } else {
        $available = [];
        foreach ($provider_ids as $provider_id) {
            if (isProviderTimeSlotAvailable($conn, $provider_id, $service_date, $service_time)) {
                $available[] = $provider_id;
            }
        }

        if (count($available) === 0) {
            $message = 'None of the selected providers are available at this date and time. Please choose other providers or another time slot.';
            $message_type = 'error';
            $show_providers = true;
            $providers = fetchAvailableProviders($conn, $service_id, $service_date, $service_time);
        } else {
            $lat_val = $coords['latitude'];
            $lng_val = $coords['longitude'];
            $group_id = createBookingGroupId();
            $stmt = $conn->prepare(
                "INSERT INTO bookings (customer_id, service_id, status, booking_date, service_date, service_time, address, latitude, longitude, location_label, provider_id, booking_group_id, served)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"
            );
            $inserted = 0;
            $insert_error = '';
            if ($stmt) {
                foreach ($available as $provider_id) {
                    $stmt->bind_param(
                        "iisssssddsis",
                        $customer_id,
                        $service_id,
                        $status,
                        $booking_date,
                        $service_date,
                        $service_time,
                        $address,
                        $lat_val,
                        $lng_val,
                        $location_label,
                        $provider_id,
                        $group_id
                    );
                    if ($stmt->execute()) {
                        $inserted++;
                    } else {
                        $insert_error = $stmt->error;
                    }
                }
                $stmt->close();
            }

            if ($inserted > 0) {
                $count = $inserted;
                $message = $count === 1
                    ? 'Booking request sent!'
                    : "Booking request sent to {$count} providers. The first to accept will get the job.";
                $message_type = 'success';
            } else {
                $message = 'Error: ' . ($insert_error ?: 'Could not create booking.');
                $message_type = 'error';
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
  <title>Book a Service — Sewamandu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/auth.css" />
  <link rel="stylesheet" href="../css/booking.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        <p><?= $show_providers ? 'Select one or more providers. The first to accept gets the booking.' : 'Tell us what you need and when — we\'ll find the right expert.' ?></p>
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

        <?php
          $cal_min   = date('Y-m-d');
          $cal_max   = date('Y-m-d', strtotime('+30 days'));
          $date_text = $service_date ? date('D, j M Y', strtotime($service_date)) : '— Choose a date —';
        ?>
        <div class="auth-field">
          <label>Preferred Date</label>
          <div class="picker" id="datePicker" data-min="<?= $cal_min ?>" data-max="<?= $cal_max ?>">
            <button type="button" class="picker-trigger" id="dateTrigger" aria-haspopup="dialog" aria-expanded="false">
              <i class="fas fa-calendar-day picker-ic"></i>
              <span class="picker-text">
                <span class="picker-label">Select a day</span>
                <span class="picker-value" id="dateValueText"><?= htmlspecialchars($date_text) ?></span>
              </span>
              <i class="fas fa-chevron-down picker-caret"></i>
            </button>
            <div class="picker-pop cal-pop" id="calPop" hidden>
              <div class="cal-head">
                <button type="button" class="cal-nav" id="calPrev" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                <span class="cal-title" id="calTitle"></span>
                <button type="button" class="cal-nav" id="calNext" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
              </div>
              <div class="cal-grid cal-dow"><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span></div>
              <div class="cal-grid cal-days" id="calDays"></div>
              <div class="cal-actions">
                <button type="button" class="cal-btn ghost" id="calRemove">Clear</button>
              </div>
            </div>
          </div>
          <input type="hidden" name="date" id="dateInput" value="<?= htmlspecialchars($service_date) ?>">
        </div>

        <?php
          // 30-minute slots from 8:00 AM to 6:00 PM (flat scrollable list).
          $time_slots = [];
          for ($hour = 8; $hour <= 18; $hour++) {
            foreach ([0, 30] as $minute) {
              if ($hour === 18 && $minute > 0) break; // stop at 6:00 PM
              $time_value = sprintf('%02d:%02d', $hour, $minute);
              $time_slots[] = ['value' => $time_value, 'label' => date('g:i A', strtotime($time_value))];
            }
          }
          $time_text = $service_time ? date('g:i A', strtotime($service_time)) : '— Choose a time —';
        ?>
        <div class="auth-field">
          <label>Preferred Time</label>
          <div class="picker" id="timePicker">
            <button type="button" class="picker-trigger" id="timeTrigger" aria-haspopup="listbox" aria-expanded="false">
              <i class="fas fa-clock picker-ic"></i>
              <span class="picker-text">
                <span class="picker-label">Start with</span>
                <span class="picker-value" id="timeValueText"><?= htmlspecialchars($time_text) ?></span>
              </span>
              <i class="fas fa-chevron-down picker-caret"></i>
            </button>
            <div class="picker-pop time-pop" id="timePop" hidden>
              <ul class="time-list" id="timeList" role="listbox">
                <?php foreach ($time_slots as $slot): ?>
                  <li class="time-opt<?= ($service_time === $slot['value']) ? ' selected' : '' ?>" role="option" data-value="<?= $slot['value'] ?>" data-label="<?= $slot['label'] ?>"><?= $slot['label'] ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
          <input type="hidden" name="time" id="timeInput" value="<?= htmlspecialchars($service_time) ?>">
        </div>

        <div class="auth-field">
          <label for="location-search">Pin your location</label>
          <div class="location-picker">
            <div class="location-search-row">
              <div class="location-search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="location-search" placeholder="Search an area or landmark…" autocomplete="off" value="<?= htmlspecialchars($location_label) ?>">
                <ul id="location-suggestions" class="location-suggestions" hidden></ul>
              </div>
              <button type="button" id="use-my-location" class="location-gps-btn" title="Use my current location">
                <i class="fas fa-location-crosshairs"></i>
              </button>
            </div>
            <div id="booking-map" class="booking-map" aria-label="Map to pin service location"></div>
            <p class="location-hint">Search, use GPS, or tap the map to place the pin. Drag to adjust.</p>
            <p id="location-selected" class="location-selected<?= $location_label || ($latitude && $longitude) ? '' : ' empty' ?>">
              <?php if ($location_label): ?>
                <i class="fas fa-map-pin"></i> <?= htmlspecialchars($location_label) ?>
              <?php elseif ($latitude && $longitude): ?>
                <i class="fas fa-map-pin"></i> <?= htmlspecialchars(number_format((float)$latitude, 5) . ', ' . number_format((float)$longitude, 5)) ?>
              <?php else: ?>
                No location pinned yet
              <?php endif; ?>
            </p>
            <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($latitude) ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($longitude) ?>">
            <input type="hidden" name="location_label" id="location_label" value="<?= htmlspecialchars($location_label) ?>">
          </div>
        </div>

        <div class="auth-field">
          <label for="address">Address details <span class="optional-label">(landmarks, flat, gate)</span></label>
          <textarea id="address" name="address" placeholder="e.g. Near city mall, 3rd floor, blue gate"><?= htmlspecialchars($address) ?></textarea>
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
            <input type="hidden" name="latitude" value="<?= htmlspecialchars($latitude) ?>">
            <input type="hidden" name="longitude" value="<?= htmlspecialchars($longitude) ?>">
            <input type="hidden" name="location_label" value="<?= htmlspecialchars($location_label) ?>">
            <?php if ($location_label || ($latitude && $longitude)): ?>
              <p class="location-confirm">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($location_label ?: ($latitude . ', ' . $longitude)) ?>
                <?php if ($address): ?>
                  <span>· <?= htmlspecialchars($address) ?></span>
                <?php endif; ?>
              </p>
            <?php endif; ?>
            <span class="provider-label">Select Providers <small>(choose one or more)</small></span>
            <div class="provider-cards">
              <?php foreach ($providers as $provider): ?>
                <div class="provider-card">
                  <input type="checkbox" name="provider_ids[]" value="<?= $provider['id'] ?>" class="provider-radio" id="provider-<?= (int) $provider['id'] ?>">
                  <div class="provider-name"><?= htmlspecialchars($provider['username']) ?></div>
                  <div class="provider-rating">
                    <?= renderStarRatingHtml($provider['avg_rating'], $provider['review_count']) ?>
                    <?php if ($provider['review_count'] > 0): ?>
                      <span class="provider-review-count">(<?= (int) $provider['review_count'] ?> review<?= $provider['review_count'] === 1 ? '' : 's' ?>)</span>
                    <?php endif; ?>
                  </div>
                  <div class="provider-price">Rs. <?= htmlspecialchars($provider['price']) ?></div>
                  <div class="provider-availability">Availability: <?= htmlspecialchars($provider['availability']) ?></div>
                  <div class="provider-completed"><i class="fas fa-check-circle"></i> <?= (int) $provider['completed_services'] ?> service<?= $provider['completed_services'] === 1 ? '' : 's' ?> completed</div>
                  <button type="button" class="provider-view-btn" data-provider-id="<?= (int) $provider['id'] ?>">
                    <i class="fas fa-eye"></i> View Profile
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="submit" name="book_with_provider" class="auth-btn" id="confirm-booking-btn">Send Booking Request <i class="fas fa-check"></i></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div id="provider-profile-modal" class="provider-modal" hidden>
    <div class="provider-modal-backdrop" data-close-modal></div>
    <div class="provider-modal-panel" role="dialog" aria-labelledby="provider-modal-title" aria-modal="true">
      <button type="button" class="provider-modal-close" data-close-modal aria-label="Close profile">&times;</button>
      <div id="provider-modal-loading" class="provider-modal-loading">
        <i class="fas fa-spinner fa-spin"></i> Loading profile…
      </div>
      <div id="provider-modal-content" hidden>
        <div class="provider-modal-header">
          <div class="provider-modal-avatar" id="provider-modal-avatar"></div>
          <div>
            <h2 id="provider-modal-title"></h2>
            <div class="provider-modal-rating" id="provider-modal-rating"></div>
            <p class="provider-modal-meta" id="provider-modal-completed"></p>
          </div>
        </div>
        <div class="provider-modal-details">
          <p id="provider-modal-phone"></p>
          <p id="provider-modal-address"></p>
        </div>
        <h3 class="provider-modal-reviews-title">Past Reviews</h3>
        <div id="provider-modal-reviews" class="provider-modal-reviews"></div>
      </div>
      <p id="provider-modal-error" class="provider-modal-error" hidden></p>
    </div>
  </div>

  <script>
    (function () {
      const mapEl = document.getElementById('booking-map');
      if (!mapEl || typeof L === 'undefined') return;

      const latInput = document.getElementById('latitude');
      const lngInput = document.getElementById('longitude');
      const labelInput = document.getElementById('location_label');
      const searchInput = document.getElementById('location-search');
      const suggestionsEl = document.getElementById('location-suggestions');
      const selectedEl = document.getElementById('location-selected');
      const gpsBtn = document.getElementById('use-my-location');
      const form = document.getElementById('booking-form');

      const defaultCenter = [27.7172, 85.3240];
      const startLat = parseFloat(latInput.value);
      const startLng = parseFloat(lngInput.value);
      const hasStart = Number.isFinite(startLat) && Number.isFinite(startLng);

      const map = L.map(mapEl).setView(hasStart ? [startLat, startLng] : defaultCenter, hasStart ? 16 : 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);

      let marker = null;
      let searchTimer = null;
      let reverseTimer = null;
      let suppressSearch = false;

      function setSelectedText(text) {
        if (!selectedEl) return;
        if (text) {
          selectedEl.classList.remove('empty');
          selectedEl.textContent = '';
          const icon = document.createElement('i');
          icon.className = 'fas fa-map-pin';
          selectedEl.appendChild(icon);
          selectedEl.appendChild(document.createTextNode(' ' + text));
        } else {
          selectedEl.classList.add('empty');
          selectedEl.textContent = 'No location pinned yet';
        }
      }

      function setPin(lat, lng, label, skipReverse) {
        latInput.value = Number(lat).toFixed(7);
        lngInput.value = Number(lng).toFixed(7);
        if (typeof label === 'string') {
          labelInput.value = label;
          if (searchInput && label) {
            suppressSearch = true;
            searchInput.value = label;
            suppressSearch = false;
          }
          setSelectedText(label || (Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5)));
        } else {
          labelInput.value = '';
          setSelectedText(Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5));
        }

        if (marker) {
          marker.setLatLng([lat, lng]);
        } else {
          marker = L.marker([lat, lng], { draggable: true }).addTo(map);
          marker.on('dragend', function (e) {
            const p = e.target.getLatLng();
            setPin(p.lat, p.lng, null, false);
          });
        }
        map.setView([lat, lng], Math.max(map.getZoom(), 16));

        if (!skipReverse && !label) {
          clearTimeout(reverseTimer);
          reverseTimer = setTimeout(function () {
            fetch('book-service.php?ajax=reverse_geocode&lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng))
              .then(function (r) { return r.json(); })
              .then(function (data) {
                if (data && data.label) {
                  labelInput.value = data.label;
                  setSelectedText(data.label);
                  if (searchInput) {
                    suppressSearch = true;
                    searchInput.value = data.label;
                    suppressSearch = false;
                  }
                }
              })
              .catch(function () {});
          }, 400);
        }
      }

      if (hasStart) {
        setPin(startLat, startLng, labelInput.value || null, true);
      }

      map.on('click', function (e) {
        setPin(e.latlng.lat, e.latlng.lng, null, false);
        if (suggestionsEl) suggestionsEl.hidden = true;
      });

      if (gpsBtn) {
        gpsBtn.addEventListener('click', function () {
          if (!navigator.geolocation) {
            alert('Geolocation is not supported by this browser.');
            return;
          }
          gpsBtn.disabled = true;
          navigator.geolocation.getCurrentPosition(
            function (pos) {
              gpsBtn.disabled = false;
              setPin(pos.coords.latitude, pos.coords.longitude, null, false);
            },
            function () {
              gpsBtn.disabled = false;
              alert('Could not get your location. Allow location access or pin the map manually.');
            },
            { enableHighAccuracy: true, timeout: 10000 }
          );
        });
      }

      function hideSuggestions() {
        if (!suggestionsEl) return;
        suggestionsEl.hidden = true;
        suggestionsEl.innerHTML = '';
      }

      function showSuggestions(items) {
        if (!suggestionsEl) return;
        suggestionsEl.innerHTML = '';
        if (!items.length) {
          hideSuggestions();
          return;
        }
        items.forEach(function (item) {
          const li = document.createElement('li');
          li.textContent = item.label;
          li.addEventListener('click', function () {
            if (item.lat == null || item.lng == null) return;
            setPin(item.lat, item.lng, item.label, true);
            hideSuggestions();
          });
          suggestionsEl.appendChild(li);
        });
        suggestionsEl.hidden = false;
      }

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          if (suppressSearch) return;
          const q = searchInput.value.trim();
          clearTimeout(searchTimer);
          if (q.length < 2) {
            hideSuggestions();
            return;
          }
          searchTimer = setTimeout(function () {
            fetch('book-service.php?ajax=place_search&q=' + encodeURIComponent(q))
              .then(function (r) { return r.json(); })
              .then(function (data) {
                showSuggestions(Array.isArray(data) ? data : []);
              })
              .catch(function () { hideSuggestions(); });
          }, 350);
        });

        searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') hideSuggestions();
        });
      }

      document.addEventListener('click', function (e) {
        if (!e.target.closest('.location-search-wrap')) hideSuggestions();
      });

      if (form) {
        form.addEventListener('submit', function (e) {
          if (!latInput.value || !lngInput.value) {
            e.preventDefault();
            alert('Please pin your service location on the map or search for a place.');
            mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
      }

      setTimeout(function () { map.invalidateSize(); }, 100);
    })();

    (function () {
      const modal = document.getElementById('provider-profile-modal');
      if (!modal) return;

      const loading = document.getElementById('provider-modal-loading');
      const content = document.getElementById('provider-modal-content');
      const errorEl = document.getElementById('provider-modal-error');
      const titleEl = document.getElementById('provider-modal-title');
      const avatarEl = document.getElementById('provider-modal-avatar');
      const ratingEl = document.getElementById('provider-modal-rating');
      const completedEl = document.getElementById('provider-modal-completed');
      const phoneEl = document.getElementById('provider-modal-phone');
      const addressEl = document.getElementById('provider-modal-address');
      const reviewsEl = document.getElementById('provider-modal-reviews');

      function escapeHtml(value) {
        return String(value ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function starsHtml(avg, count) {
        if (!count) return '<span class="provider-rating-none">No ratings yet</span>';
        const filled = Math.max(0, Math.min(5, Math.round(avg)));
        let html = '<span class="provider-stars">';
        for (let i = 1; i <= 5; i++) {
          html += i <= filled
            ? '<i class="fas fa-star"></i>'
            : '<i class="far fa-star"></i>';
        }
        html += '</span><span class="provider-rating-value">' + avg.toFixed(1) + '</span>';
        html += ' <span class="provider-review-count">(' + count + ' review' + (count === 1 ? '' : 's') + ')</span>';
        return html;
      }

      function formatDate(value) {
        if (!value) return '';
        const d = new Date(value.replace(' ', 'T'));
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
      }

      function openModal() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
      }

      function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
      }

      function showLoading() {
        loading.hidden = false;
        content.hidden = true;
        errorEl.hidden = true;
      }

      function showError(message) {
        loading.hidden = true;
        content.hidden = true;
        errorEl.hidden = false;
        errorEl.textContent = message;
      }

      function showProfile(data) {
        loading.hidden = true;
        errorEl.hidden = true;
        content.hidden = false;

        titleEl.textContent = data.username || 'Provider';
        ratingEl.innerHTML = starsHtml(Number(data.avg_rating) || 0, Number(data.review_count) || 0);
        const completed = Number(data.completed_services) || 0;
        completedEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + completed + ' service' + (completed === 1 ? '' : 's') + ' completed';

        if (data.profile_picture) {
          const img = document.createElement('img');
          img.src = data.profile_picture;
          img.alt = (data.username || 'Provider') + ' profile photo';
          avatarEl.textContent = '';
          avatarEl.appendChild(img);
        } else {
          avatarEl.textContent = (data.username || 'P').charAt(0).toUpperCase();
        }

        phoneEl.innerHTML = data.phone
          ? '<i class="fas fa-phone"></i> ' + escapeHtml(data.phone)
          : '<span class="provider-modal-muted">Phone not provided</span>';
        addressEl.innerHTML = data.address
          ? '<i class="fas fa-location-dot"></i> ' + escapeHtml(data.address)
          : '<span class="provider-modal-muted">Address not provided</span>';

        reviewsEl.innerHTML = '';
        if (!data.reviews || !data.reviews.length) {
          reviewsEl.innerHTML = '<p class="provider-modal-muted">No reviews yet.</p>';
          return;
        }

        data.reviews.forEach((review) => {
          const card = document.createElement('article');
          card.className = 'provider-review-card';
          const rating = Number(review.rating) || 0;
          let stars = '';
          for (let i = 1; i <= 5; i++) {
            stars += i <= rating ? '★' : '☆';
          }
          card.innerHTML =
            '<div class="provider-review-top">' +
              '<strong>' + escapeHtml(review.customer_name || 'Anonymous') + '</strong>' +
              '<span class="provider-review-stars">' + stars + '</span>' +
            '</div>' +
            '<div class="provider-review-service">' + escapeHtml(review.service_name || 'Service') + ' · ' + escapeHtml(formatDate(review.created_at)) + '</div>' +
            '<p class="provider-review-comment">' + escapeHtml(review.comment || 'No comment provided.') + '</p>';
          reviewsEl.appendChild(card);
        });
      }

      async function loadProfile(providerId) {
        showLoading();
        openModal();
        try {
          const res = await fetch('book-service.php?ajax=provider_profile&provider_id=' + encodeURIComponent(providerId));
          const data = await res.json();
          if (data.error) {
            showError(data.error);
            return;
          }
          showProfile(data);
        } catch (err) {
          showError('Could not load provider profile. Please try again.');
        }
      }

      document.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.provider-view-btn');
        if (viewBtn) {
          e.preventDefault();
          e.stopPropagation();
          loadProfile(viewBtn.dataset.providerId);
          return;
        }

        const card = e.target.closest('.provider-card');
        if (card && !e.target.closest('.provider-view-btn')) {
          const input = card.querySelector('.provider-radio');
          if (input) {
            input.checked = !input.checked;
          }
        }
      });

      const providerForm = document.querySelector('form[method="POST"] #confirm-booking-btn')
        ? document.querySelector('#confirm-booking-btn').closest('form')
        : null;
      if (providerForm) {
        providerForm.addEventListener('submit', (e) => {
          const checked = providerForm.querySelectorAll('input[name="provider_ids[]"]:checked');
          if (checked.length === 0) {
            e.preventDefault();
            alert('Please select at least one provider.');
          }
        });
      }

      modal.querySelectorAll('[data-close-modal]').forEach((el) => {
        el.addEventListener('click', closeModal);
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
      });
    })();

    (function () {
      const MONTHS = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

      const parseISO = (s) => { const [y,m,d] = s.split('-').map(Number); return new Date(y, m-1, d); };
      const iso = (d) => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
      const stripTime = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
      const sameDay = (a,b) => a && b && a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate();

      /* ---------- Date picker (calendar) ---------- */
      const dp = document.getElementById('datePicker');
      if (!dp) return;

      const dateTrigger = document.getElementById('dateTrigger');
      const calPop = document.getElementById('calPop');
      const calTitle = document.getElementById('calTitle');
      const calDays = document.getElementById('calDays');
      const calPrev = document.getElementById('calPrev');
      const calNext = document.getElementById('calNext');
      const calRemove = document.getElementById('calRemove');
      const dateInput = document.getElementById('dateInput');
      const dateValueText = document.getElementById('dateValueText');

      const minDate = parseISO(dp.dataset.min);
      const maxDate = parseISO(dp.dataset.max);
      let selected = dateInput.value ? parseISO(dateInput.value) : null;
      let pending  = selected ? new Date(selected) : null;
      let view = new Date(selected || minDate);
      view.setDate(1);

      function renderCal() {
        calTitle.textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
        calDays.innerHTML = '';
        const firstDow = (new Date(view.getFullYear(), view.getMonth(), 1).getDay() + 6) % 7; // Mon = 0
        const daysInMonth = new Date(view.getFullYear(), view.getMonth()+1, 0).getDate();
        const today = stripTime(new Date());

        for (let i = 0; i < firstDow; i++) {
          const sp = document.createElement('span');
          sp.className = 'cal-day empty';
          calDays.appendChild(sp);
        }
        for (let d = 1; d <= daysInMonth; d++) {
          const cell = document.createElement('button');
          cell.type = 'button';
          cell.className = 'cal-day';
          cell.textContent = d;
          const date = new Date(view.getFullYear(), view.getMonth(), d);
          if (date < minDate || date > maxDate) { cell.disabled = true; cell.classList.add('disabled'); }
          if (sameDay(date, today)) cell.classList.add('today');
          if (pending && sameDay(date, pending)) cell.classList.add('sel');
          cell.addEventListener('click', () => { commitDate(date); });
          calDays.appendChild(cell);
        }
        calPrev.disabled = new Date(view.getFullYear(), view.getMonth(), 0) < minDate;
        calNext.disabled = new Date(view.getFullYear(), view.getMonth()+1, 1) > maxDate;
      }

      function openCal() { closeTime(); calPop.hidden = false; dateTrigger.setAttribute('aria-expanded','true'); dp.classList.add('open'); renderCal(); }
      function closeCal() { calPop.hidden = true; dateTrigger.setAttribute('aria-expanded','false'); dp.classList.remove('open'); }

      dateTrigger.addEventListener('click', (e) => { e.stopPropagation(); calPop.hidden ? openCal() : closeCal(); });
      calPrev.addEventListener('click', () => { view.setMonth(view.getMonth()-1); renderCal(); });
      calNext.addEventListener('click', () => { view.setMonth(view.getMonth()+1); renderCal(); });
      calRemove.addEventListener('click', () => {
        pending = null; selected = null; dateInput.value = '';
        dateValueText.textContent = '— Choose a date —';
        dp.classList.remove('filled'); closeCal();
      });
      function commitDate(date) {
        pending = date;
        selected = new Date(date);
        dateInput.value = iso(selected);
        dateValueText.textContent = selected.toLocaleDateString(undefined, { weekday:'short', day:'numeric', month:'short', year:'numeric' });
        dp.classList.add('filled');
        closeCal();
      }

      /* ---------- Time picker (scroll list) ---------- */
      const tp = document.getElementById('timePicker');
      const timeTrigger = document.getElementById('timeTrigger');
      const timePop = document.getElementById('timePop');
      const timeList = document.getElementById('timeList');
      const timeInput = document.getElementById('timeInput');
      const timeValueText = document.getElementById('timeValueText');

      function openTime() {
        closeCal();
        timePop.hidden = false; timeTrigger.setAttribute('aria-expanded','true'); tp.classList.add('open');
        const sel = timeList.querySelector('.time-opt.selected');
        if (sel) sel.scrollIntoView({ block: 'center' });
      }
      function closeTime() { timePop.hidden = true; timeTrigger.setAttribute('aria-expanded','false'); tp.classList.remove('open'); }

      timeTrigger.addEventListener('click', (e) => { e.stopPropagation(); timePop.hidden ? openTime() : closeTime(); });
      timeList.querySelectorAll('.time-opt').forEach((li) => {
        li.addEventListener('click', () => {
          timeList.querySelectorAll('.time-opt').forEach((x) => x.classList.remove('selected'));
          li.classList.add('selected');
          timeInput.value = li.dataset.value;
          timeValueText.textContent = li.dataset.label;
          tp.classList.add('filled');
          closeTime();
        });
      });

      document.addEventListener('click', (e) => {
        if (!dp.contains(e.target)) closeCal();
        if (!tp.contains(e.target)) closeTime();
      });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeCal(); closeTime(); } });

      if (dateInput.value) dp.classList.add('filled');
      if (timeInput.value) tp.classList.add('filled');

      const form = document.getElementById('booking-form');
      if (form) {
        form.addEventListener('submit', (e) => {
          if (!dateInput.value) { e.preventDefault(); openCal(); }
          else if (!timeInput.value) { e.preventDefault(); openTime(); }
        });
      }
    })();
  </script>
</body>
</html>