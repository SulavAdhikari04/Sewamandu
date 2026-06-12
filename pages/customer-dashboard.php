<?php
require_once '../components/SessionManager.php';
require_once '../components/Database.php';
require_once '../components/BookingStatus.php';

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
$sql = "SELECT b.id AS booking_id, b.provider_id, b.service_id, s.name AS service_name, u.username AS provider_name, b.service_date, b.status AS booking_status, r.id AS review_id, r.rating AS review_rating
        FROM bookings b
        LEFT JOIN services s ON b.service_id = s.id
        LEFT JOIN users u ON b.provider_id = u.id
        LEFT JOIN reviews r ON b.id = r.booking_id
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

$review_success_msg = isset($_GET['review_success']) ? "Review submitted successfully!" : "";
$review_error_msg = "";

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $booking_id = intval($_POST['booking_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    
    // Fetch booking to verify customer owns it and provider_id / service_id match
    $stmt = $conn->prepare("SELECT provider_id, service_id, status FROM bookings WHERE id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($provider_id, $service_id, $booking_status);
    $booking_exists = $stmt->fetch();
    $stmt->close();
    
    if (!$booking_exists) {
        $review_error_msg = "Booking not found.";
    } elseif ($booking_status !== 'completed') {
        $review_error_msg = "You can only review completed services.";
    } elseif ($rating < 1 || $rating > 5) {
        $review_error_msg = "Rating must be between 1 and 5 stars.";
    } else {
        // Check if already reviewed
        $stmt = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->store_result();
        $already_reviewed = $stmt->num_rows > 0;
        $stmt->close();
        
        if ($already_reviewed) {
            $review_error_msg = "You have already reviewed this booking.";
        } else {
            // Insert review
            $stmt = $conn->prepare("INSERT INTO reviews (booking_id, customer_id, provider_id, service_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiiis", $booking_id, $user_id, $provider_id, $service_id, $rating, $comment);
            if ($stmt->execute()) {
                $stmt->close();
                closeDBConnection($conn);
                header("Location: customer-dashboard.php?review_success=1#reviews");
                exit();
            } else {
                $review_error_msg = "Failed to submit review. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Fetch customer reviews from the database
$my_reviews = [];
$reviews_sql = "SELECT r.rating, r.comment, r.created_at, s.name AS service_name, u.username AS provider_name
                FROM reviews r
                LEFT JOIN services s ON r.service_id = s.id
                LEFT JOIN users u ON r.provider_id = u.id
                WHERE r.customer_id = ?
                ORDER BY r.created_at DESC";
$stmt = $conn->prepare($reviews_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
while ($row = $reviews_result->fetch_assoc()) {
    $my_reviews[] = $row;
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Dashboard - Sewamandu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/customer-dashboard.css">
  <link rel="stylesheet" href="../css/booking-status.css">
</head>
<body>
  <div class="layout">
    <div class="sidebar">
      <a href="customer-home.php"><h2>Sewa<span>mandu</span></h2></a>
      <nav>
        <ul>
          <li><a href="#overview"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
          <li><a href="#bookings"><i class="fas fa-calendar-check"></i> Bookings</a></li>
          <li><a href="#notifications"><i class="fas fa-bell"></i> Notifications</a></li>
          <li><a href="#reviews"><i class="fas fa-star"></i> My Reviews</a></li>
          <li><a href="#profile"><i class="fas fa-user"></i> Profile</a></li>
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

      <?php if ($review_success_msg): ?>
        <div class="review-alert review-alert-success" style="padding: 14px 20px; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 12px; color: #2e7d32; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px;">
          <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
          <span><?= htmlspecialchars($review_success_msg) ?></span>
        </div>
      <?php endif; ?>
      <?php if ($review_error_msg): ?>
        <div class="review-alert review-alert-error" style="padding: 14px 20px; background: #ffebee; border: 1px solid #ffcdd2; border-radius: 12px; color: #c62828; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px;">
          <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
          <span><?= htmlspecialchars($review_error_msg) ?></span>
        </div>
      <?php endif; ?>

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
            <tr><th>Service</th><th>Provider</th><th>Date</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($bookings as $booking): ?>
              <tr>
                <td><?= htmlspecialchars($booking['service_name']) ?></td>
                <td><?= htmlspecialchars($booking['provider_name']) ?></td>
                <td><?= htmlspecialchars($booking['service_date']) ?></td>
                <td>
                  <span class="<?= getBookingStatusBadgeClass($booking['booking_status']) ?>">
                    <?= htmlspecialchars(getBookingStatusLabel($booking['booking_status'])) ?>
                  </span>
                </td>
                <td>
                  <?php if ($booking['booking_status'] === 'completed'): ?>
                    <?php if (!empty($booking['review_id'])): ?>
                      <span class="reviewed-badge"><i class="fas fa-check-circle" style="color: var(--teal);"></i> Reviewed (<?= htmlspecialchars($booking['review_rating']) ?>★)</span>
                    <?php else: ?>
                      <button class="review-btn" 
                              data-booking-id="<?= $booking['booking_id'] ?>"
                              data-provider-name="<?= htmlspecialchars($booking['provider_name']) ?>"
                              data-service-name="<?= htmlspecialchars($booking['service_name']) ?>">
                        Review Provider
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="color: #888;">—</span>
                  <?php endif; ?>
                </td>
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
        
        <!-- Pending Reviews -->
        <?php
        $pending_reviews = [];
        foreach ($bookings as $b) {
            if ($b['booking_status'] === 'completed' && empty($b['review_id'])) {
                $pending_reviews[] = $b;
            }
        }
        ?>
        <?php if (!empty($pending_reviews)): ?>
          <div class="pending-reviews-container" style="margin-bottom: 30px;">
            <h4 style="color: var(--teal-deep); margin-bottom: 15px;"><i class="fas fa-clock" style="color: var(--accent);"></i> Pending Reviews</h4>
            <div class="pending-reviews-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
              <?php foreach ($pending_reviews as $pr): ?>
                <div class="pending-review-card" style="background: #fff; border: 1.5px solid #d8ece7; padding: 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                  <div>
                    <h5 style="margin: 0 0 8px; font-size: 1.1rem; color: var(--ink);"><?= htmlspecialchars($pr['service_name']) ?></h5>
                    <p style="margin: 0 0 6px; font-size: 0.9rem; color: #555;"><strong>Provider:</strong> <?= htmlspecialchars($pr['provider_name']) ?></p>
                    <p style="margin: 0 0 12px; font-size: 0.9rem; color: #555;"><strong>Date:</strong> <?= htmlspecialchars($pr['service_date']) ?></p>
                  </div>
                  <button class="review-btn" style="margin-top: 10px; width: 100%;"
                          data-booking-id="<?= $pr['booking_id'] ?>"
                          data-provider-name="<?= htmlspecialchars($pr['provider_name']) ?>"
                          data-service-name="<?= htmlspecialchars($pr['service_name']) ?>">
                    Write a Review
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Past Reviews -->
        <h4 style="color: var(--teal-deep); margin-bottom: 15px;"><i class="fas fa-history"></i> Past Reviews</h4>
        <?php if (empty($my_reviews)): ?>
          <p style="color: #666; font-style: italic;">You haven't submitted any reviews yet.</p>
        <?php else: ?>
        <table>
          <thead>
            <tr><th>Service</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($my_reviews as $review): ?>
              <tr>
                <td><?= htmlspecialchars($review['service_name']) ?></td>
                <td><?= htmlspecialchars($review['provider_name']) ?></td>
                <td>
                  <span style="color: #ff9800;">
                    <?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?>
                  </span>
                  (<?= htmlspecialchars($review['rating']) ?>/5)
                </td>
                <td><?= htmlspecialchars($review['comment']) ?></td>
                <td style="font-size: 0.85rem; color: #666;"><?= htmlspecialchars(date('M d, Y', strtotime($review['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
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

  <!-- Review Modal -->
  <div id="review-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(11, 31, 28, 0.65); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; animation: fadeIn 0.3s ease-out;">
    <div class="modal-content" style="background: #ffffff; width: 90%; max-width: 500px; border-radius: 24px; padding: clamp(24px, 4vw, 36px); box-shadow: 0 25px 50px rgba(0, 77, 64, 0.25); position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
      <span class="close-modal-btn" style="position: absolute; top: 20px; right: 24px; font-size: 1.8rem; cursor: pointer; color: #888; transition: color 0.2s;">&times;</span>
      <h3 style="margin: 0 0 10px; font-size: 1.4rem; color: var(--teal-deep);"><i class="fas fa-star-half-stroke" style="color: var(--accent);"></i> Review Service</h3>
      <p id="modal-service-provider" style="margin: 0 0 24px; font-size: 0.95rem; color: #555; background: #f4faf8; padding: 12px 16px; border-radius: 12px; border-left: 4px solid var(--teal);"></p>
      
      <form method="POST" action="customer-dashboard.php">
        <input type="hidden" name="booking_id" id="modal-booking-id" value="">
        
        <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: #46564f;">Your Rating:</label>
        <div class="star-rating" id="star-rating">
          <input type="radio" id="star5" name="rating" value="5" required />
          <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
          <input type="radio" id="star4" name="rating" value="4" />
          <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
          <input type="radio" id="star3" name="rating" value="3" />
          <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
          <input type="radio" id="star2" name="rating" value="2" />
          <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
          <input type="radio" id="star1" name="rating" value="1" />
          <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
        </div>

        <label for="review-comment" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: #46564f;">Feedback & Experience:</label>
        <textarea id="review-comment" name="comment" rows="4" placeholder="How was the service? What did you like or dislike?" style="width: 100%; padding: 14px; border: 1.5px solid #dbe7e3; border-radius: 12px; font-family: inherit; font-size: 0.95rem; background: #fff; resize: vertical; margin-bottom: 24px; outline: none; transition: border-color 0.4s var(--ease-soft);" required></textarea>

        <button type="submit" name="submit_review" class="review-submit-btn">Submit Review</button>
      </form>
    </div>
  </div>

  <footer class="footer">
    <div class="footer-container">
      <p>&copy; 2025 Sewamandu. All rights reserved.</p>
      <p>Need help? Contact <a href="mailto:support@sewamandu.com">support@sewamandu.com</a></p>
    </div>
  </footer>
</body>
<script>
document.getElementById('edit-btn').addEventListener('click', () => {
  document.getElementById('profile-view').style.display = 'none';
  document.getElementById('profile-form').style.display = 'block';
});

// Review Modal Handling
const modal = document.getElementById('review-modal');
const modalBookingId = document.getElementById('modal-booking-id');
const modalServiceProvider = document.getElementById('modal-service-provider');
const closeModalBtn = document.querySelector('.close-modal-btn');

const starRating = document.getElementById('star-rating');
const starInputs = starRating.querySelectorAll('input[type="radio"]');
const starLabels = starRating.querySelectorAll('label');

function updateStarHighlight(rating) {
  starLabels.forEach(label => {
    const value = parseInt(label.getAttribute('for').replace('star', ''), 10);
    label.classList.toggle('active', rating > 0 && value <= rating);
  });
}

function resetReviewForm() {
  starInputs.forEach(input => { input.checked = false; });
  document.getElementById('review-comment').value = '';
  updateStarHighlight(0);
}

starLabels.forEach(label => {
  label.addEventListener('mouseenter', () => {
    updateStarHighlight(parseInt(label.getAttribute('for').replace('star', ''), 10));
  });
  label.addEventListener('click', () => {
    updateStarHighlight(parseInt(label.getAttribute('for').replace('star', ''), 10));
  });
});

starRating.addEventListener('mouseleave', () => {
  const checked = starRating.querySelector('input[type="radio"]:checked');
  updateStarHighlight(checked ? parseInt(checked.value, 10) : 0);
});

starInputs.forEach(input => {
  input.addEventListener('change', () => {
    updateStarHighlight(parseInt(input.value, 10));
  });
});

document.querySelectorAll('.review-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const bookingId = btn.getAttribute('data-booking-id');
    const providerName = btn.getAttribute('data-provider-name');
    const serviceName = btn.getAttribute('data-service-name');

    resetReviewForm();
    modalBookingId.value = bookingId;
    modalServiceProvider.innerHTML = `<i class="fas fa-tools" style="color: var(--teal);"></i> <strong>${serviceName}</strong> by <strong>${providerName}</strong>`;

    modal.style.display = 'flex';
  });
});

function closeReviewModal() {
  modal.style.display = 'none';
  resetReviewForm();
}

closeModalBtn.addEventListener('click', closeReviewModal);

window.addEventListener('click', (e) => {
  if (e.target === modal) {
    closeReviewModal();
  }
});
</script>
</html>