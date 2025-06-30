<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../components/SessionManager.php';
require_once '../components/Database.php';
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

// Add demonstration cookies
setcookie('provider_dashboard_visited', 'true', time() + (86400 * 30), "/");
setcookie('provider_user_id', $_SESSION['user_id'], time() + (86400 * 30), "/");

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
    $username = trim($_POST['username']);
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
    header("Location: provider-dashboard.php#profile");
    exit();
}

// Handle marking booking as done or not done
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['done_booking_id'])) {
    $booking_id = intval($_POST['done_booking_id']);
    // Mark as served in a new table or update a field (here, let's use a 'served' field in bookings)
    $stmt = $conn->prepare("UPDATE bookings SET served = 1 WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Booking marked as served!';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_done_booking_id'])) {
    $booking_id = intval($_POST['not_done_booking_id']);
    $stmt = $conn->prepare("UPDATE bookings SET served = 0 WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Booking marked as not served!';
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

// Total Earnings (sum of price for confirmed bookings)
$result = $conn->prepare("SELECT COALESCE(SUM(sp.price),0) FROM bookings b JOIN service_providers sp ON b.provider_id = sp.user_id AND b.service_id = sp.service_id WHERE b.provider_id = ? AND b.status = 'confirmed' AND sp.status = 'approved'");
$result->bind_param("i", $provider_user_id);
$result->execute();
$result->bind_result($total_earnings);
$result->fetch();
$result->close();

// Fetch only booking requests from customers (pending_provider) for this provider
$customer_requests = [];
$sql = "SELECT b.id AS booking_id, u.username AS customer_name, s.name AS service_name, b.service_date, b.status
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        WHERE b.provider_id = ? AND b.status = 'pending_provider'
        ORDER BY b.service_date DESC";
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
$sql = "SELECT b.id AS booking_id, u.username AS customer_name, s.name AS service_name, b.service_date, b.status, b.served
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

// Fetch provider info from users table
$provider_info = ['username' => '', 'phone' => '', 'id' => $provider_user_id];
$stmt = $conn->prepare("SELECT username, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$stmt->bind_result($username, $phone);
if ($stmt->fetch()) {
    $provider_info['username'] = $username;
    $provider_info['phone'] = $phone;
}
$stmt->close();

// Fetch customers served (served=1)
$customers_served = [];
$sql = "SELECT u.username, u.email, s.name AS service_name FROM bookings b JOIN users u ON b.customer_id = u.id JOIN services s ON b.service_id = s.id WHERE b.provider_id = ? AND b.served = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $customers_served[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Provider Dashboard - GharSewa</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/provider-dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="layout">
    <div class="sidebar">
      <h2>GharSewa</h2>
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
      </header>

      <section id="overview">
        <h2>Dashboard Overview</h2>
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
            <tr><th>Customer</th><th>Service</th><th>Date</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($customer_requests as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['customer_name']) ?></td>
              <td><?= htmlspecialchars($row['service_name']) ?></td>
              <td><?= htmlspecialchars($row['service_date']) ?></td>
              <td>
                <span class="status-badge status-pending-provider">Waiting for Provider</span>
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
              <th>Certificate</th>
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
                  <?php
                    $status = $row['status'];
                    $statusClass = '';
                    switch ($status) {
                      case 'pending_provider':
                        $statusClass = 'status-badge status-pending-provider';
                        $statusText = 'Waiting for Provider';
                        break;
                      case 'pending_admin':
                        $statusClass = 'status-badge status-pending-admin';
                        $statusText = 'Waiting for Admin';
                        break;
                      case 'confirmed':
                        $statusClass = 'status-badge status-confirmed';
                        $statusText = 'Confirmed';
                        break;
                      case 'rejected_by_provider':
                        $statusClass = 'status-badge status-rejected';
                        $statusText = 'Rejected by Provider';
                        break;
                      case 'rejected_by_admin':
                        $statusClass = 'status-badge status-rejected';
                        $statusText = 'Rejected by Admin';
                        break;
                      default:
                        $statusClass = 'status-badge';
                        $statusText = ucfirst($status);
                    }
                  ?>
                  <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                  <?php if ($status === 'confirmed' && (!isset($row['served']) || $row['served'] === null)): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="done_booking_id" value="<?= $row['booking_id'] ?>">
                      <button type="submit">Done</button>
                    </form>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="not_done_booking_id" value="<?= $row['booking_id'] ?>">
                      <button type="submit">Not Done</button>
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
        <table>
          <thead>
            <tr><th>Customer</th><th>Service</th><th>Rating</th><th>Comment</th></tr>
          </thead>
          <tbody>
            <!-- Dynamically load reviews here. Remove mock data. -->
          </tbody>
        </table>
      </section>

      <section id="profile">
        <h3>My Profile</h3>
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
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($provider_info['username']) ?>" required>
          <label for="phone">Phone:</label>
          <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($provider_info['phone']) ?>" required>
          <label for="address">Address:</label>
          <input type="text" id="address" name="address" value="<?= htmlspecialchars($profile['address']) ?>" required>
          <label for="profile_picture">Profile Picture:</label>
          <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
          <button type="submit" name="save_profile">Save Changes</button>
        </form>
      </section>
    </div>
  </div>
  <footer class="footer">
    <div class="footer-container">
      <p>&copy; 2025 GharSewa. All rights reserved.</p>
      <p>Need help? Contact <a href="mailto:support@gharsewa.com">support@gharsewa.com</a></p>
    </div>
  </footer>
</body>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    const name = localStorage.getItem('providerName') || "Ramesh Thapa";
    const email = localStorage.getItem('providerEmail') || "ramesh@example.com";
    const phone = localStorage.getItem('providerPhone') || "9801000000";

    document.getElementById('view-name').textContent = name;
    document.getElementById('view-email').textContent = email;
    document.getElementById('view-phone').textContent = phone;

    document.getElementById('name').value = name;
    document.getElementById('email').value = email;
    document.getElementById('phone').value = phone;
  });

  document.getElementById('edit-btn').addEventListener('click', () => {
    document.getElementById('profile-view').style.display = 'none';
    document.getElementById('profile-form').style.display = 'block';
  });

  document.getElementById('profile-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;

    localStorage.setItem('providerName', name);
    localStorage.setItem('providerEmail', email);
    localStorage.setItem('providerPhone', phone);

    document.getElementById('view-name').textContent = name;
    document.getElementById('view-email').textContent = email;
    document.getElementById('view-phone').textContent = phone;

    document.getElementById('profile-form').style.display = 'none';
    document.getElementById('profile-view').style.display = 'block';
    alert('Profile updated!');
  });
</script>
</html>

