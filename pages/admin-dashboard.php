<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../components/SessionManager.php';
require_once '../components/Database.php';
require_once '../components/BookingStatus.php';
require_once '../components/StringHelpers.php';

// Add cookies
setcookie('admin_dashboard_visited', 'true', time() + (86400 * 30), "/");
setcookie('admin_user_id', $_SESSION['user_id'], time() + (86400 * 30), "/");

if (session_status() === PHP_SESSION_NONE) {
session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';

// Handle add service form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $service_name = formatDisplayName($_POST['service_name'] ?? '');
    $service_desc = trim($_POST['service_desc']);
    if ($service_name && $service_desc) {
        // Check for duplicate service name
        $stmt = $conn->prepare("SELECT id FROM services WHERE name = ?");
        $stmt->bind_param("s", $service_name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = 'A service with this name already exists!';
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO services (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $service_name, $service_desc);
            if ($stmt->execute()) {
                $message = 'Service added successfully!';
            } else {
                $message = 'Error adding service: ' . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        $message = 'Please fill in all fields to add a service.';
    }
}

// Handle service deletion
if (isset($_POST['delete_service_id'])) {
    $delete_id = intval($_POST['delete_service_id']);
    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = 'Service deleted successfully!';
    } else {
        $message = 'Error deleting service: ' . $stmt->error;
    }
    $stmt->close();
}

// Handle user deletion
if (isset($_POST['delete_user_id'])) {
    $delete_user_id = intval($_POST['delete_user_id']);
    
    // First delete related bookings
    $stmt = $conn->prepare("DELETE FROM bookings WHERE customer_id = ? OR provider_id = ?");
    $stmt->bind_param("ii", $delete_user_id, $delete_user_id);
    $stmt->execute();
    $stmt->close();
    
    // Then delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $delete_user_id);
    if ($stmt->execute()) {
        $message = 'User and all related data deleted successfully!';
    } else {
        $message = 'Error deleting user: ' . $stmt->error;
    }
    $stmt->close();
}

// Handle service approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_provider_id'], $_POST['service_action'])) {
    $service_provider_id = intval($_POST['service_provider_id']);
    if ($_POST['service_action'] === 'approve') {
        $stmt = $conn->prepare("UPDATE service_providers SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $service_provider_id);
        if ($stmt->execute()) {
            $message = 'Service approved successfully!';
        } else {
            $message = 'Error: ' . $stmt->error;
        }
        $stmt->close();
    } elseif ($_POST['service_action'] === 'reject') {
        $stmt = $conn->prepare("UPDATE service_providers SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $service_provider_id);
        if ($stmt->execute()) {
            $message = 'Service rejected successfully!';
        } else {
            $message = 'Error: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle provider approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['user_action'])) {
    $user_id = intval($_POST['user_id']);
    if ($_POST['user_action'] === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = 'Provider approved successfully!';
        } else {
            $message = 'Error: ' . $stmt->error;
        }
        $stmt->close();
    } elseif ($_POST['user_action'] === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = 'Provider rejected successfully!';
        } else {
            $message = 'Error: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all bookings
// Fetch all services for listing
$services = [];
$result = $conn->query("SELECT id, name, description FROM services");
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

// Fetch all users for user management (including id and provider_document) - excluding only pending providers
$users = [];
$result = $conn->query("SELECT id, username, email, role, created_at, status, provider_document FROM users WHERE role != 'provider' OR status != 'pending'");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Fetch all bookings for status tracking
$all_approved_bookings = [];
$sql = "SELECT b.id AS booking_id, s.name AS service_name, u.username AS customer_name, p.username AS provider_name, b.service_date, b.status AS booking_status, r.rating AS review_rating, r.comment AS review_comment
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN users u ON b.customer_id = u.id
        JOIN users p ON b.provider_id = p.id
        LEFT JOIN reviews r ON b.id = r.booking_id
        ORDER BY b.service_date DESC, b.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_approved_bookings[] = $row;
}
$stmt->close();

// Dashboard stats
// Total users
$result = $conn->query("SELECT COUNT(*) AS total_users FROM users");
$stats = $result->fetch_assoc();
$total_users = $stats['total_users'];
// Total providers
$result = $conn->query("SELECT COUNT(*) AS total_providers FROM users WHERE role = 'provider'");
$stats = $result->fetch_assoc();
$total_providers = $stats['total_providers'];
// Total bookings
$result = $conn->query("SELECT COUNT(*) AS total_bookings FROM bookings");
$stats = $result->fetch_assoc();
$total_bookings = $stats['total_bookings'];
// Total services
$result = $conn->query("SELECT COUNT(*) AS total_services FROM services");
$stats = $result->fetch_assoc();
$total_services = $stats['total_services'];
// Pending provider verifications
$result = $conn->query("SELECT COUNT(*) AS pending_verifications FROM users WHERE role = 'provider' AND status = 'pending'");
$stats = $result->fetch_assoc();
$pending_verifications = $stats['pending_verifications'];

// Fetch pending providers for verification table
$pending_providers = [];
$result = $conn->query("SELECT id, username, email, created_at, provider_document FROM users WHERE role = 'provider' AND status = 'pending'");
while ($row = $result->fetch_assoc()) {
    $pending_providers[] = $row;
}

// Fetch pending services for admin review
$pending_services = [];
$sql = "SELECT sp.id as service_provider_id, sp.price, sp.availability, sp.service_area, sp.provider_certificate, 
               s.name as service_name, u.username as provider_name, u.email as provider_email
        FROM service_providers sp 
        JOIN services s ON sp.service_id = s.id 
        JOIN users u ON sp.user_id = u.id 
        WHERE sp.status = 'pending' OR sp.status IS NULL
        ORDER BY sp.id DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $pending_services[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <meta charset="UTF-8">
  <title>Admin Dashboard - Sewamandu</title>
  <link rel="stylesheet" href="../css/admin-dashboard.css?v=1.0.1">
  <link rel="stylesheet" href="../css/form-utils.css">
  <link rel="stylesheet" href="../css/booking-status.css">
</head>
<body>
  <div class="sidebar">
    <h2>Sewamandu</h2>
    <nav>
      <ul>
        <li><a href="#overview">Dashboard</a></li>
        <li><a href="#user">Users</a></li>
        <li><a href="#verify">Verifications</a></li>
        <li><a href="#services">Service Reviews</a></li>
        <li><a href="#booking">Bookings</a></li>
        <li><a href="#export">Export</a></li>
      </ul>
    </nav>
    <div style="margin-top: 30px;">
      <a href="../components/Logout.php" class="logout-btn">Logout</a>
    </div>
  </div>
  <div class="main-content">
  <header>
    <h1>Welcome, Admin </h1>
  </header>
  <div class="container">
  <h2>Dashboard Overview</h2>
  <div class="stats-grid">
    <div class="card">👤 Users<br><strong><?= $total_users ?></strong></div>
    <div class="card">🧑‍🔧 Providers<br><strong><?= $total_providers ?></strong></div>
    <div class="card">📦 Bookings<br><strong><?= $total_bookings ?></strong></div>
    <div class="card">✅ Active Services<br><strong><?= $total_services ?></strong></div>
    <div class="card">⏳ Pending Verifications<br><strong><?= $pending_verifications ?></strong></div>
  </div>

  <h3>User Management</h3>
  <table>
    <thead>
      <tr><th>Username</th><th>Email</th><th>Role</th><th>Registered At</th><th>Status</th><th>Document</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
      <tr>
        <td><?= htmlspecialchars($user['username']) ?></td>
        <td><?= htmlspecialchars($user['email']) ?></td>
        <td><?= htmlspecialchars($user['role']) ?></td>
        <td><?= htmlspecialchars($user['created_at']) ?></td>
        <td><?= ($user['role'] === 'customer' || $user['role'] === 'admin') ? 'active' : htmlspecialchars($user['status']) ?></td>
        <td>
          <?php if (!empty($user['provider_document'])): ?>
            <a href="download_document.php?user_id=<?= $user['id'] ?>" target="_blank">Download Document</a>
          <?php else: ?>
            No document
          <?php endif; ?>
        </td>
        <td>
          <?php if ($user['role'] === 'provider' && $user['status'] === 'pending'): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
              <button type="submit" name="user_action" value="approve">Approve</button>
              <button type="submit" name="user_action" value="reject">Reject</button>
            </form>
          <?php endif; ?>
          <form method="POST" style="display:inline; margin-left: 5px;">
            <input type="hidden" name="delete_user_id" value="<?= $user['id'] ?>">
            <button type="submit" onclick="return confirm('Are you sure you want to delete this user and all their data? This action cannot be undone.');" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3>Provider Verifications</h3>
  <table>
    <thead>
      <tr><th>Name</th><th>Email</th><th>Registered At</th><th>Documents</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($pending_providers as $provider): ?>
      <tr>
        <td><?= htmlspecialchars($provider['username']) ?></td>
        <td><?= htmlspecialchars($provider['email']) ?></td>
        <td><?= htmlspecialchars($provider['created_at']) ?></td>
        <td>
          <?php if (!empty($provider['provider_document'])): ?>
            <a href="download_document.php?user_id=<?= $provider['id'] ?>" target="_blank">Download Document</a>
          <?php else: ?>
            No document
          <?php endif; ?>
        </td>
        <td>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="user_id" value="<?= $provider['id'] ?>">
            <button type="submit" name="user_action" value="approve">Approve</button>
            <button type="submit" name="user_action" value="reject">Reject</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3 id="services">Service Reviews</h3>
  <table>
    <thead>
      <tr><th>Provider</th><th>Service</th><th>Price</th><th>Availability</th><th></th><th>Certificate</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($pending_services as $service): ?>
      <tr>
        <td>
          <?= htmlspecialchars($service['provider_name']) ?><br>
          <small><?= htmlspecialchars($service['provider_email']) ?></small>
        </td>
        <td><?= htmlspecialchars($service['service_name']) ?></td>
        <td>Rs. <?= htmlspecialchars($service['price']) ?></td>
        <td><?= htmlspecialchars($service['availability']) ?></td>
        <td><?= htmlspecialchars($service['service_area']) ?></td>
        <td>
          <?php if (!empty($service['provider_certificate'])): ?>
            <a href="download_service_certificate_admin.php?service_provider_id=<?= $service['service_provider_id'] ?>" target="_blank">Download Certificate</a>
          <?php else: ?>
            No certificate
          <?php endif; ?>
        </td>
        <td>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="service_provider_id" value="<?= $service['service_provider_id'] ?>">
            <button type="submit" name="service_action" value="approve" style="background-color: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Approve</button>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="service_provider_id" value="<?= $service['service_provider_id'] ?>">
            <button type="submit" name="service_action" value="reject" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Reject</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3>Service Management</h3>
  <form method="POST" action="" style="margin-bottom: 20px;">
    <input type="hidden" name="add_service" value="1">
    <label for="service_name">Service Name:</label>
    <input type="text" id="service_name" name="service_name" data-capitalize="words" required>
    <label for="service_desc">Description:</label>
    <input type="text" id="service_desc" name="service_desc" required>
    <button type="submit">Add Service</button>
  </form>
  <table>
    <thead>
      <tr><th>Name</th><th>Description</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($services as $service): ?>
      <tr>
        <td><?= htmlspecialchars($service['name']) ?></td>
        <td><?= htmlspecialchars($service['description']) ?></td>
        <td>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="delete_service_id" value="<?= $service['id'] ?>">
            <button type="submit" onclick="return confirm('Are you sure you want to delete this service?');">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3 id="booking">Booking Management</h3>
  <table>
    <thead>
      <tr><th>ID</th><th>Service</th><th>Customer</th><th>Provider</th><th>Date</th><th>Status</th><th>Reviews</th></tr>
    </thead>
    <tbody>
      <?php foreach ($all_approved_bookings as $row): ?>
      <tr>
        <td><?= (int) $row['booking_id'] ?></td>
        <td><?= htmlspecialchars($row['service_name']) ?></td>
        <td><?= htmlspecialchars($row['customer_name']) ?></td>
        <td><?= htmlspecialchars($row['provider_name']) ?></td>
        <td><?= htmlspecialchars($row['service_date']) ?></td>
        <td>
          <span class="<?= getBookingStatusBadgeClass($row['booking_status']) ?>">
            <?= htmlspecialchars(getBookingStatusLabel($row['booking_status'])) ?>
          </span>
        </td>
        <td>
          <?php if ($row['booking_status'] === 'completed' && isset($row['review_rating'])): ?>
            <button class="show-review-btn" 
                    data-customer="<?= htmlspecialchars($row['customer_name']) ?>"
                    data-rating="<?= (int) $row['review_rating'] ?>"
                    data-feedback="<?= htmlspecialchars($row['review_comment']) ?>">
              Show Review
            </button>
          <?php elseif ($row['booking_status'] === 'completed'): ?>
            <span style="color: #888; font-style: italic;">No Review</span>
          <?php else: ?>
            <span style="color: #888;">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- <h3>Data Export</h3>
  <button onclick="alert('Export functionality will be implemented soon!')">Download Providers CSV</button>
  <button onclick="alert('Export functionality will be implemented soon!')">Download Bookings CSV</button> -->
</div>
</div>

<!-- Review View Modal for Admin -->
<div id="admin-review-modal" class="modal">
  <div class="modal-content">
    <span class="close-modal-btn">&times;</span>
    <h3 style="margin: 0 0 16px; font-size: 1.4rem; color: #004d40;">
      🔍 Booking Review Details
    </h3>
    <div class="review-details-container">
      <p style="margin: 8px 0; font-size: 0.95rem; color: #555;">
        <strong>Customer:</strong> <span id="modal-customer-name">—</span>
      </p>
      <div style="margin: 16px 0 8px;">
        <strong style="display: block; margin-bottom: 6px; font-size: 0.95rem; color: #555;">Rating:</strong>
        <span id="modal-rating-stars" class="review-stars"></span>
        <span id="modal-rating-value" style="font-size: 0.9rem; color: #666; margin-left: 6px;"></span>
      </div>
      <div style="margin: 16px 0 8px;">
        <strong style="display: block; margin-bottom: 6px; font-size: 0.95rem; color: #555;">Feedback:</strong>
        <p id="modal-feedback-text" style="margin: 0; padding: 12px 16px; background: #f4faf8; border-radius: 8px; border-left: 4px solid #00796b; font-style: italic; color: #2d3b38; font-size: 0.95rem; line-height: 1.5;"></p>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('admin-review-modal');
  const closeModalBtn = modal.querySelector('.close-modal-btn');
  const customerNameElem = document.getElementById('modal-customer-name');
  const ratingStarsElem = document.getElementById('modal-rating-stars');
  const ratingValueElem = document.getElementById('modal-rating-value');
  const feedbackTextElem = document.getElementById('modal-feedback-text');

  // Handle clicking on Show Review button
  document.querySelectorAll('.show-review-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const customer = this.getAttribute('data-customer') || '—';
      const rating = parseInt(this.getAttribute('data-rating')) || 0;
      const feedback = this.getAttribute('data-feedback') || 'No comment provided.';

      customerNameElem.textContent = customer;
      
      // Render stars
      let starsHTML = '';
      for (let i = 1; i <= 5; i++) {
        starsHTML += i <= rating ? '★' : '☆';
      }
      ratingStarsElem.textContent = starsHTML;
      ratingValueElem.textContent = `(${rating}/5)`;

      feedbackTextElem.textContent = feedback;

      modal.style.display = 'flex';
    });
  });

  // Close modal when clicking close button
  closeModalBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });

  // Close modal when clicking outside of it
  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
});
</script>
<script src="../js/auto-capitalize.js"></script>

</body>
</html>
