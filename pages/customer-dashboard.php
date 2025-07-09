<?php
require_once '../components/SessionManager.php';
require_once '../components/Database.php';

// Add  cookies
setcookie('customer_dashboard_visited', 'true', time() + (86400 * 30), "/");
setcookie('customer_preferred_service', 'plumbing', time() + (86400 * 30), "/");
setcookie('customer_user_id', $_SESSION['user_id'], time() + (86400 * 30), "/");

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();

$user_id = $_SESSION['user_id'];
$bookings = [];
$sql = "SELECT s.name AS service_name, u.username AS provider_name, b.service_date, b.status
        FROM bookings b
        LEFT JOIN services s ON b.service_id = s.id
        LEFT JOIN users u ON b.provider_id = u.id
        WHERE b.customer_id = ?
        ORDER BY b.service_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

// Handle profile update for customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $address = trim($_POST['address']);
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $profile_picture_path = '';
    // Fetch current profile picture if exists
    $stmt = $conn->prepare("SELECT profile_picture FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
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
    $stmt->bind_param("ssi", $username, $phone, $user_id);
    $stmt->execute();
    $stmt->close();
    // Update or insert profile
    $stmt = $conn->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $stmt = $conn->prepare("UPDATE user_profiles SET address = ?, profile_picture = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $address, $profile_picture_path, $user_id);
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, address, profile_picture) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $address, $profile_picture_path);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: customer-dashboard.php#profile");
    exit();
}
// Fetch customer profile from user_profiles
$profile = [ 'address' => '', 'profile_picture' => '' ];
$stmt = $conn->prepare("SELECT address, profile_picture FROM user_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $profile = $row;
}
$stmt->close();

// Fetch customer info from users table (ensure role is customer)
$user_info = ['username' => '', 'phone' => '', 'id' => $user_id];
$stmt = $conn->prepare("SELECT username, phone FROM users WHERE id = ? AND role = 'customer'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $phone);
if ($stmt->fetch()) {
    $user_info['username'] = $username;
    $user_info['phone'] = $phone;
}
$stmt->close();

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <meta charset="UTF-8">
  <title>Customer Dashboard - GharSewa</title>
  <link rel="stylesheet" href="../css/customer-dashboard.css">
</head>
<body>
  <div class="layout">
    <div class="sidebar">
      <a href="customer-home.php"> <h2>GharSewa</h2></a>
      <nav>
        <ul>
          <li><a href="#overview">Dashboard</a></li>
          <li><a href="#bookings">Bookings</a></li>
          <li><a href="#notifications">Notifications</a></li>
          <li><a href="#reviews">My Reviews</a></li>
          <li><a href="#profile">Profile</a></li>
        </ul>
      </nav>
      <div style="margin-top: 30px;">
        <a href="../components/Logout.php" class="logout-btn">Logout</a>
      </div>
    </div>

    <div class="main-content">
      <header>
        <h1>Welcome to Your Dashboard</h1>
      </header>

      <section id="overview">
        <h2>Dashboard Overview</h2>
        <div class="stats-grid">
          <!-- Dynamically load stats here. Remove mock data. -->
        </div>
      </section>

      <section id="bookings">
        <h3>Booking History</h3>
        <?php if (empty($bookings)): ?>
          <div class="no-bookings-message">
            <p>No booking history found. You haven't made any bookings yet.</p>
            <p style="font-size: 14px; margin-top: 10px; opacity: 0.8;">Start by exploring our services and making your first booking!</p>
          </div>
        <?php else: ?>
        <table>
          <thead>
            <tr><th>Service</th><th>Provider</th><th>Date</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($bookings as $booking): ?>
              <tr>
                <td><?= htmlspecialchars($booking['service_name']) ?></td>
                <td><?= htmlspecialchars($booking['provider_name']) ?></td>
                <td><?= htmlspecialchars($booking['service_date']) ?></td>
                <td><?= htmlspecialchars($booking['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </section>

      <section id="notifications">
        <h3>Notifications</h3>
        <table>
          <thead>
            <tr><th>Date</th><th>Message</th></tr>
          </thead>
          <tbody>
            <!-- Dynamically load notifications here.-->
          </tbody>
        </table>
      </section>

      <section id="reviews">
        <h3>My Reviews</h3>
        <table>
          <thead>
            <tr><th>Service</th><th>Provider</th><th>Rating</th><th>Comment</th></tr>
          </thead>
          <tbody>
            <!-- Dynamically load reviews here. -->
          </tbody>
        </table>
      </section>

      <section id="profile">
        <h3>My Profile</h3>
        <div id="profile-view">
          <p><strong>Username:</strong> <span id="view-username"><?= htmlspecialchars($user_info['username']) ?></span></p>
          <p><strong>Phone:</strong> <span id="view-phone"><?= htmlspecialchars($user_info['phone']) ?></span></p>
          <?php if (!empty($profile['profile_picture'])): ?>
            <img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="Profile Picture" style="max-width:100px; border-radius:50%; margin-bottom:10px;">
          <?php endif; ?>
          <p><strong>Address:</strong> <span id="view-address"><?= htmlspecialchars($profile['address']) ?></span></p>
          <button id="edit-btn">Edit Profile</button>
        </div>
        <form id="profile-form" method="POST" enctype="multipart/form-data" style="display: none;">
          <label for="username">Username:</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($user_info['username']) ?>" required>
          <label for="phone">Phone:</label>
          <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user_info['phone']) ?>" required>
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
document.getElementById('edit-btn').addEventListener('click', () => {
  document.getElementById('profile-view').style.display = 'none';
  document.getElementById('profile-form').style.display = 'block';
});

document.getElementById('profile-form').addEventListener('submit', function(e) {
  // Form will be submitted normally to PHP for processing
  // No need to prevent default or handle with JavaScript
});
</script>
</html>