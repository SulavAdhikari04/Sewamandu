<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../components/SessionManager.php';
require_once '../components/Database.php';
require_once '../components/BookingStatus.php';
require_once '../components/StringHelpers.php';
require_once '../components/TwoFactorAuth.php';
$all_services = [];
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
$message = '';

// Ensure the availability toggle column exists (MariaDB supports IF NOT EXISTS)
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_available TINYINT(1) NOT NULL DEFAULT 1");
// Ensure the wallet balance column exists
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00");
ensureTwoFactorColumn($conn);

// Handle 2FA toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_2fa'])) {
    $enable = $_POST['toggle_2fa'] === 'on';
    setTwoFactorEnabled($conn, $provider_user_id, $enable);
    $msg = $enable
        ? 'Two-factor authentication has been enabled.'
        : 'Two-factor authentication has been disabled.';
    header('Location: provider-dashboard.php?security_msg=' . urlencode($msg) . '#profile');
    exit();
}

// Add demonstration cookies
setcookie('provider_dashboard_visited', 'true', time() + (86400 * 30), "/");
setcookie('provider_user_id', $_SESSION['user_id'], time() + (86400 * 30), "/");

// Handle availability ON/OFF toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $is_available = ($_POST['toggle_availability'] === 'on') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET is_available = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_available, $provider_user_id);
    $stmt->execute();
    $stmt->close();
    $msg = $is_available ? 'You are now available for services.' : 'You are now unavailable. Customers will not see you for bookings.';
    header('Location: provider-dashboard.php?booking_msg=' . urlencode($msg) . '#overview');
    exit();
}

// Handle wallet top up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet_topup'])) {
    $topup_amount = floatval($_POST['topup_amount']);
    if ($topup_amount > 0) {
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $topup_amount, $provider_user_id);
        $stmt->execute();
        $stmt->close();
        $msg = 'Wallet topped up with Rs. ' . number_format($topup_amount, 2) . '.';
    } else {
        $msg = 'Invalid top up amount.';
    }
    header('Location: provider-dashboard.php?booking_msg=' . urlencode($msg) . '#overview');
    exit();
}

// Handle Approve/Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['action'])) {
    $booking_id = intval($_POST['booking_id']);
    if ($_POST['action'] === 'approve') {
        // Only update if current status is 'pending_provider'
        $stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->bind_result($current_status);
        $stmt->fetch();
        $stmt->close();
        if ($current_status === 'pending_provider') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'pending_admin' WHERE id = ?");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Booking approved and sent to admin.';
        }
    } elseif ($_POST['action'] === 'reject') {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'rejected_by_provider' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Booking rejected.';
    }
}

// Handle add service for provider
if (isset($_POST['add_service_id'])) {
    $service_id = intval($_POST['add_service_id']);
    $price = floatval($_POST['service_price']);
    $availability = trim($_POST['availability']);
    $provider_certificate = null;
    
    // Set service_area to the selected service's name
    $service_area = '';
    foreach ($all_services as $service) {
        if ($service['id'] == $service_id) {
            $service_area = $service['name'];
            break;
        }
    }
    
    // Handle certificate file upload
    if (isset($_FILES['provider_certificate']) && $_FILES['provider_certificate']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['provider_certificate']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $provider_certificate = file_get_contents($_FILES['provider_certificate']['tmp_name']);
        } else {
            $message = 'Invalid file type. Please upload PDF, JPEG, or PNG files only.';
            $stmt->close();
            // Continue to prevent the service from being added
        }
    }
    
    // Check if already added
    $stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ? AND service_id = ?");
    $stmt->bind_param("ii", $provider_user_id, $service_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $message = 'You already offer this service!';
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO service_providers (user_id, service_id, price, availability, service_area, provider_certificate) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $provider_user_id, $service_id, $price, $availability, $service_area, $provider_certificate);
        if ($stmt->execute()) {
            $message = 'Service added to your offerings!';
        } else {
            $message = 'Error adding service: ' . $stmt->error;
        }
    }
    $stmt->close();
}

// Handle remove service for provider
if (isset($_POST['remove_service_id'])) {
    $remove_service_id = intval($_POST['remove_service_id']);
    $stmt = $conn->prepare("DELETE FROM service_providers WHERE user_id = ? AND service_id = ?");
    $stmt->bind_param("ii", $provider_user_id, $remove_service_id);
    if ($stmt->execute()) {
        $message = 'Service removed from your offerings!';
    } else {
        $message = 'Error removing service: ' . $stmt->error;
    }
    $stmt->close();
}

// Handle profile update for provider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $address = trim($_POST['address']);
    $username = formatDisplayName($_POST['username'] ?? '');
    $phone = trim($_POST['phone']);
    $profile_picture_path = '';
    // Fetch current profile picture if exists
    $stmt = $conn->prepare("SELECT profile_picture FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $provider_user_id);
    $stmt->execute();
    $stmt->bind_result($current_pic);
    if ($stmt->fetch()) {
        $profile_picture_path = $current_pic;
    }
    $stmt->close();
    // Handle file upload if a new picture is provided
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir);
        $filename = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            $profile_picture_path = $target_file;
        }
    }
    // Update users table for username and phone
    $stmt = $conn->prepare("UPDATE users SET username = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $username, $phone, $provider_user_id);
    if (!$stmt->execute()) {
        echo '<div style="color:red;">SQL Error (users update): ' . $stmt->error . '</div>';
    }
    $stmt->close();
    // Update or insert profile
    $stmt = $conn->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $provider_user_id);
    if (!$stmt->execute()) {
        echo '<div style="color:red;">SQL Error (profile select): ' . $stmt->error . '</div>';
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $stmt = $conn->prepare("UPDATE user_profiles SET address = ?, profile_picture = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $address, $profile_picture_path, $provider_user_id);
        if (!$stmt->execute()) {
            echo '<div style="color:red;">SQL Error (profile update): ' . $stmt->error . '</div>';
        }
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, address, profile_picture) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $provider_user_id, $address, $profile_picture_path);
        if (!$stmt->execute()) {
            echo '<div style="color:red;">SQL Error (profile insert): ' . $stmt->error . '</div>';
        }
    }
    $stmt->close();
    header('Location: provider-dashboard.php?booking_msg=' . urlencode('Profile updated successfully.') . '#profile');
    exit();
}

// Handle marking booking as done or not done
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['completion_booking_id'], $_POST['completion_action'])) {
    $booking_id = intval($_POST['completion_booking_id']);
    $completion_action = $_POST['completion_action'];
    $completed = $completion_action === 'done';

    if ($booking_id > 0 && ($completion_action === 'done' || $completion_action === 'not_done')) {
        $result = updateProviderBookingCompletion($conn, $booking_id, $provider_user_id, $completed);
        $message = $result['message'];
        header('Location: provider-dashboard.php?booking_msg=' . urlencode($message) . '#accepted-bookings');
        exit();
    }

    $message = 'Invalid booking action.';
}

if (isset($_GET['booking_msg'])) {
    $message = $_GET['booking_msg'];
}

// Fetch provider profile from user_profiles
$profile = [ 'address' => '', 'profile_picture' => '' ];
$stmt = $conn->prepare("SELECT address, profile_picture FROM user_profiles WHERE user_id = ?");
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $profile = $row;
}
$stmt->close();

// Fetch all available services
$result = $conn->query("SELECT id, name FROM services");
while ($row = $result->fetch_assoc()) {
    $all_services[] = $row;
}

// Fetch provider's services with price, availability, service_area, and certificate
$my_services = [];
$sql = "SELECT s.name, sp.price, sp.service_id, sp.availability, sp.service_area, sp.provider_certificate FROM services s JOIN service_providers sp ON s.id = sp.service_id WHERE sp.user_id = ? AND sp.status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $my_services[] = $row;
}
$stmt->close();

// Dashboard stats for provider
// Bookings Received
$result = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ?");
$result->bind_param("i", $provider_user_id);
$result->execute();
$result->bind_result($bookings_received);
$result->fetch();
$result->close();

// Services Offered
$services_offered = count($my_services);

// Pending Requests
$result = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending_provider'");
$result->bind_param("i", $provider_user_id);
$result->execute();
$result->bind_result($pending_requests);
$result->fetch();
$result->close();

// Total Earnings (sum of price for completed bookings)
$result = $conn->prepare("SELECT COALESCE(SUM(sp.price),0) FROM bookings b JOIN service_providers sp ON b.provider_id = sp.user_id AND b.service_id = sp.service_id WHERE b.provider_id = ? AND b.status = 'completed' AND sp.status = 'approved'");
$result->bind_param("i", $provider_user_id);
$result->execute();
$result->bind_result($total_earnings);
$result->fetch();
$result->close();

// Fetch only booking requests from customers (pending_provider) for this provider
$customer_requests = [];
$sql = "SELECT b.id AS booking_id, u.username AS customer_name, s.name AS service_name, b.service_date, b.service_time, b.status
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        WHERE b.provider_id = ? AND b.status = 'pending_provider'
        ORDER BY b.service_date DESC, b.service_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $customer_requests[] = $row;
}
$stmt->close();

// Fetch all bookings for this provider (any status)
$accepted_bookings = [];
$sql = "SELECT b.id AS booking_id, u.username AS customer_name, s.name AS service_name, b.service_date, b.status AS booking_status, b.served
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        WHERE b.provider_id = ?
        ORDER BY b.service_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $accepted_bookings[] = $row;
}
$stmt->close();

function formatBookingTime($service_time) {
    if (empty($service_time)) {
        return '—';
    }
    $timestamp = strtotime($service_time);
    return $timestamp ? date('g:i A', $timestamp) : $service_time;
}

// Fetch provider info from users table
$provider_info = ['username' => '', 'phone' => '', 'email' => '', 'two_factor_enabled' => 0, 'id' => $provider_user_id];
$is_available = 1;
$wallet_balance = 0.00;
$stmt = $conn->prepare("SELECT username, phone, email, two_factor_enabled, is_available, wallet_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$stmt->bind_result($username, $phone, $email, $two_factor_enabled, $availability_flag, $balance_value);
if ($stmt->fetch()) {
    $provider_info['username'] = $username;
    $provider_info['phone'] = $phone;
    $provider_info['email'] = $email;
    $provider_info['two_factor_enabled'] = (int) $two_factor_enabled;
    $is_available = (int)$availability_flag;
    $wallet_balance = (float)$balance_value;
}
$stmt->close();

$security_msg = isset($_GET['security_msg']) ? trim($_GET['security_msg']) : '';
$masked_account_email = $provider_info['email'] !== '' ? maskEmail($provider_info['email']) : '';
$two_factor_on = $provider_info['two_factor_enabled'] === 1;

// Fetch customers served (served=1)
$customers_served = [];
$sql = "SELECT u.username, u.email, s.name AS service_name FROM bookings b JOIN users u ON b.customer_id = u.id JOIN services s ON b.service_id = s.id WHERE b.provider_id = ? AND b.status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $customers_served[] = $row;
}
$stmt->close();

// Fetch reviews for this provider
$provider_reviews = [];
$reviews_sql = "SELECT r.rating, r.comment, r.created_at, r.show_name,
                    CASE WHEN r.show_name = 1 THEN u.username ELSE 'Anonymous' END AS customer_name,
                    s.name AS service_name
                FROM reviews r
                LEFT JOIN users u ON r.customer_id = u.id
                LEFT JOIN services s ON r.service_id = s.id
                WHERE r.provider_id = ?
                ORDER BY r.created_at DESC";
$stmt = $conn->prepare($reviews_sql);
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
while ($row = $reviews_result->fetch_assoc()) {
    $provider_reviews[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Provider Dashboard - Sewamandu</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/provider-dashboard.css" />
  <link rel="stylesheet" href="../css/form-utils.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="layout">
    <div class="sidebar">
      <h2>Sewamandu</h2>
      <nav>
        <ul>
          <li><a href="#overview">Dashboard</a></li>
          <li><a href="#bookings">Bookings</a></li>
          <li><a href="#services">My Services</a></li>
          <li><a href="#customers">Customers</a></li>
          <li><a href="#reviews">Reviews</a></li>
          <li><a href="#profile">Profile</a></li>
        </ul>
      </nav>
      <div style="margin-top: 30px;">
        <a href="../components/Logout.php" class="logout-btn">Logout</a>
      </div>
    </div>

    <div class="main-content">
      <header>
        <h1 class= "headhead">Welcome, Provider</h1>
        <?php if ($message): ?>
          <p style="color: <?= strpos($message, 'Could not') === false && strpos($message, 'Invalid') === false ? 'green' : 'red' ?>; margin-top: 10px;">
            <?= htmlspecialchars($message) ?>
          </p>
        <?php endif; ?>
      </header>

      <section id="overview">
        <h2>Dashboard Overview</h2>

        <div class="availability-panel <?= $is_available ? 'is-on' : 'is-off' ?>">
          <div class="availability-info">
            <span class="availability-label">Service Availability</span>
            <span class="availability-status">
              <?= $is_available ? 'You are currently AVAILABLE for new bookings.' : 'You are currently OFFLINE. Customers cannot book you.' ?>
            </span>
          </div>
          <form method="POST" action="provider-dashboard.php#overview" class="availability-form">
            <input type="hidden" name="toggle_availability" value="<?= $is_available ? 'off' : 'on' ?>">
            <label class="switch">
              <input type="checkbox" <?= $is_available ? 'checked' : '' ?> onchange="this.form.submit()">
              <span class="slider"></span>
            </label>
            <span class="switch-text"><?= $is_available ? 'ON' : 'OFF' ?></span>
          </form>
        </div>

        <div class="wallet-bar">
          <div class="wallet-bar-head">
            <div class="wallet-bar-left">
              <h2 class="wallet-bar-title">Wallet</h2>
              <span class="wallet-bar-sub">Total Balance</span>
            </div>
            <div class="wallet-bar-right">
              <div class="wallet-bar-amount-row">
                <span class="wallet-bar-amount" id="walletAmount" data-balance="Rs.<?= number_format($wallet_balance, 2) ?>">Rs.<?= number_format($wallet_balance, 2) ?></span>
                <span class="wallet-eye" onclick="toggleWalletBalance()" title="Show / hide balance">
                  <i class="fa-regular fa-eye" id="walletEyeIcon"></i>
                </span>
              </div>
              <a href="#" class="wallet-bar-topup" onclick="openWalletModal(event)">
                <i class="fa-solid fa-arrow-up"></i> Top Up
              </a>
            </div>
          </div>
        </div>

        <div class="wallet-modal-overlay" id="walletModal">
          <div class="wallet-modal">
            <button type="button" class="wallet-modal-close" onclick="closeWalletModal()" aria-label="Close">
              <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="wm-balance-card">
              <div class="wm-balance-top">
                <div class="wm-coin"><i class="fa-solid fa-coins"></i></div>
                <span class="wm-balance-title">Balance</span>
                <span class="wm-help" title="Your available wallet balance">?</span>
              </div>
              <div class="wm-balance-amount">Rs.<?= number_format($wallet_balance, 2) ?></div>
              <div class="wm-balance-sub">
                <?= $wallet_balance > 0 ? 'Available for withdrawal' : 'Top up to get requests' ?>
              </div>
              <button type="button" class="wm-topup-btn">Top up</button>
            </div>

            <div class="wm-row">
              <div class="wm-row-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
              <span class="wm-row-label">Payment methods</span>
              <span class="wm-row-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            </div>

            <div class="wm-qr-card">
              <div class="wm-qr-head">
                <div class="wm-qr-icon"><i class="fa-solid fa-qrcode"></i></div>
                <span class="wm-qr-title">QR payment</span>
                <span class="wm-qr-badge">New</span>
              </div>
              <div class="wm-qr-desc">Get paid directly to your linked wallet (eSewa / Khalti).</div>
              <button type="button" class="wm-link-btn">Link Wallet</button>
            </div>
          </div>
        </div>
        <script>
          function openWalletModal(e) {
            if (e) e.preventDefault();
            document.getElementById('walletModal').classList.add('open');
          }
          function closeWalletModal() {
            document.getElementById('walletModal').classList.remove('open');
          }
          document.addEventListener('click', function (e) {
            var modal = document.getElementById('walletModal');
            if (e.target === modal) closeWalletModal();
          });
          document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeWalletModal();
          });
          function toggleWalletBalance() {
            var amount = document.getElementById('walletAmount');
            var icon = document.getElementById('walletEyeIcon');
            if (amount.dataset.hidden === '1') {
              amount.textContent = amount.dataset.balance;
              amount.dataset.hidden = '0';
              icon.className = 'fa-regular fa-eye';
            } else {
              amount.textContent = 'Rs.••••';
              amount.dataset.hidden = '1';
              icon.className = 'fa-regular fa-eye-slash';
            }
          }
        </script>

        <div class="stats-grid">
          <div class="card">
            <div class="card-title">Bookings Received</div>
            <div class="card-value"><?= $bookings_received ?></div>
          </div>
          <div class="card">
            <div class="card-title">Services Offered</div>
            <div class="card-value"><?= $services_offered ?></div>
          </div>
          <div class="card">
            <div class="card-title">Pending Requests</div>
            <div class="card-value"><?= $pending_requests ?></div>
          </div>
          <div class="card">
            <div class="card-title">Total Earnings</div>
            <div class="card-value">Rs. <?= number_format($total_earnings, 2) ?></div>
          </div>
        </div>
      </section>

      <section id="bookings">
        <h3>Manage Bookings</h3>
        <table>
          <thead>
            <tr><th>Customer</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($customer_requests as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['customer_name']) ?></td>
              <td><?= htmlspecialchars($row['service_name']) ?></td>
              <td><?= htmlspecialchars($row['service_date']) ?></td>
              <td><?= htmlspecialchars(formatBookingTime($row['service_time'])) ?></td>
              <td>
                <span class="<?= getBookingStatusBadgeClass($row['status']) ?>"><?= htmlspecialchars(getBookingStatusLabel($row['status'])) ?></span>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?= $row['booking_id'] ?>">
                  <button type="submit" name="action" value="approve">Approve</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?= $row['booking_id'] ?>">
                  <button type="submit" name="action" value="reject">Reject</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <section id="services">
        <h3>My Services</h3>
        <form method="POST" action="" enctype="multipart/form-data" style="margin-bottom: 20px;">
          <label for="add_service_id">Add a Service:</label>
          <select id="add_service_id" name="add_service_id" required>
            <option value="">Select Service</option>
            <?php foreach ($all_services as $service): ?>
              <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="service_price">Price:</label>
          <input type="number" step="0.01" id="service_price" name="service_price" required>
          <label for="availability">Availability:</label>
          <input type="text" id="availability" name="availability" placeholder="e.g. Mon-Fri, 9am-5pm" required>
          <label for="provider_certificate">Certificate (PDF, JPEG, PNG):</label>
          <input type="file" id="provider_certificate" name="provider_certificate" accept=".pdf,.jpg,.jpeg,.png">
          <button type="submit">Add Service</button>
        </form>
        <table class="services-table">
          <thead>
            <tr>
              <th>Service</th>
              <th>Price</th>
              <th>Availability</th>
              <th></th>
              <th> </th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($my_services as $service): ?>
            <tr>
              <td><?= htmlspecialchars($service['name']) ?></td>
              <td>Rs. <?= htmlspecialchars($service['price']) ?></td>
              <td><?= htmlspecialchars($service['availability']) ?></td>
              <td><?= htmlspecialchars($service['service_area']) ?></td>
              <td>
                <?php if (!empty($service['provider_certificate'])): ?>
                  <a href="download_service_certificate.php?service_id=<?= $service['service_id'] ?>" target="_blank">Download Certificate</a>
                <?php else: ?>
                  No certificate
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="remove_service_id" value="<?= $service['service_id'] ?>">
                  <button type="submit" onclick="return confirm('Are you sure you want to remove this service?');">Remove</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <section id="customers">
        <h3>Customers Served</h3>
        <table>
          <thead>
            <tr><th>Name</th><th>Email</th><th>Service</th></tr>
          </thead>
          <tbody>
            <?php foreach ($customers_served as $served): ?>
              <tr>
                <td><?= htmlspecialchars($served['username']) ?></td>
                <td><?= htmlspecialchars($served['email']) ?></td>
                <td><?= htmlspecialchars($served['service_name']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <section id="accepted-bookings">
        <h3>Bookings</h3>
        <table>
          <thead>
            <tr><th>Customer</th><th>Service</th><th>Date</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($accepted_bookings as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= htmlspecialchars($row['service_name']) ?></td>
                <td><?= htmlspecialchars($row['service_date']) ?></td>
                <td>
                  <?php $status = $row['booking_status']; ?>
                  <span class="<?= getBookingStatusBadgeClass($status) ?>"><?= htmlspecialchars(getBookingStatusLabel($status)) ?></span>
                  <?php if ($status === 'confirmed'): ?>
                    <form method="POST" action="provider-dashboard.php#accepted-bookings" style="display:inline;">
                      <input type="hidden" name="completion_booking_id" value="<?= $row['booking_id'] ?>">
                      <button type="submit" name="completion_action" value="done">Done</button>
                      <button type="submit" name="completion_action" value="not_done">Not Done</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <section id="reviews">
        <h3>Customer Reviews</h3>
        <?php if (empty($provider_reviews)): ?>
          <p style="color: #666; font-style: italic;">No reviews received yet.</p>
        <?php else: ?>
        <table>
          <thead>
            <tr><th>Customer</th><th>Service</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($provider_reviews as $row): ?>
              <tr>
                <td>
                  <?php if (!empty($row['show_name'])): ?>
                    <?= htmlspecialchars($row['customer_name']) ?>
                  <?php else: ?>
                    <span style="color: #888; font-style: italic;"><i class="fas fa-user-secret"></i> Anonymous</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['service_name']) ?></td>
                <td>
                  <span style="color: #ff9800;">
                    <?= str_repeat('★', $row['rating']) . str_repeat('☆', 5 - $row['rating']) ?>
                  </span>
                  (<?= htmlspecialchars($row['rating']) ?>/5)
                </td>
                <td><?= htmlspecialchars($row['comment']) ?></td>
                <td style="font-size: 0.85rem; color: #666;"><?= htmlspecialchars(date('M d, Y', strtotime($row['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </section>

      <section id="profile">
        <h3>My Profile</h3>
        <?php if ($security_msg !== ''): ?>
          <div class="security-alert"><?= htmlspecialchars($security_msg) ?></div>
        <?php endif; ?>
        <div id="profile-view">
          <p><strong>Username:</strong> <span id="view-username"><?= htmlspecialchars($provider_info['username']) ?></span></p>
          <p><strong>Phone:</strong> <span id="view-phone"><?= htmlspecialchars($provider_info['phone']) ?></span></p>
          <?php if (!empty($profile['profile_picture'])): ?>
            <img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="Profile Picture" style="max-width:100px; border-radius:50%; margin-bottom:10px;">
          <?php endif; ?>
          <p><strong>Address:</strong> <span id="view-address"><?= htmlspecialchars($profile['address']) ?></span></p>
          <button id="edit-btn">Edit Profile</button>
        </div>
        <form id="profile-form" method="POST" enctype="multipart/form-data" style="display: none;">
          <label for="username">Username:</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($provider_info['username']) ?>" data-capitalize="words" autocomplete="name" required>
          <label for="phone">Phone:</label>
          <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($provider_info['phone']) ?>" required>
          <label for="address">Address:</label>
          <input type="text" id="address" name="address" value="<?= htmlspecialchars($profile['address']) ?>" required>
          <label for="profile_picture">Profile Picture:</label>
          <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
          <button type="submit" name="save_profile">Save Changes</button>
        </form>

        <div class="security-panel">
          <div class="security-panel__head">
            <div>
              <h4>Two-Factor Authentication</h4>
              <p class="security-panel__desc">
                Add an extra layer of security. When enabled, a verification code is sent to your registered email on sign-in from new devices.
              </p>
              <?php if ($two_factor_on && $masked_account_email !== ''): ?>
                <p class="security-panel__meta">Codes will be sent to <strong><?= htmlspecialchars($masked_account_email) ?></strong></p>
              <?php endif; ?>
            </div>
            <form method="POST" action="provider-dashboard.php#profile" class="security-panel__form">
              <input type="hidden" name="toggle_2fa" value="<?= $two_factor_on ? 'off' : 'on' ?>">
              <label class="switch" title="Toggle two-factor authentication">
                <input type="checkbox" <?= $two_factor_on ? 'checked' : '' ?> onchange="this.form.submit()">
                <span class="slider"></span>
              </label>
              <span class="switch-text"><?= $two_factor_on ? 'Enabled' : 'Disabled' ?></span>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
  <footer class="footer">
    <div class="footer-container">
      <p>&copy; 2025 Sewamandu. All rights reserved.</p>
      <p>Need help? Contact <a href="mailto:support@sewamandu.com">support@sewamandu.com</a></p>
    </div>
  </footer>
</body>
<script src="../js/auto-capitalize.js"></script>
<script>
  document.getElementById('edit-btn').addEventListener('click', () => {
    document.getElementById('profile-view').style.display = 'none';
    document.getElementById('profile-form').style.display = 'block';
  });
</script>
</html>

