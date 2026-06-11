<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
     <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <meta charset="UTF-8">
  <title>Sewamandu - Home Services at Your Doorstep</title>
  <link rel="stylesheet" href="../css/home.css">
</head>
<body>
  <header>
    <div class="container">
      <h1>Sewamandu</h1>
      <nav>
        <a href="#services">Services</a>
        <a href="#booking">Book Now</a>
        <a href="#contact">Contact</a>
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="<?php 
            if ($_SESSION['role'] === 'admin') echo 'admin-dashboard.php';
            elseif ($_SESSION['role'] === 'customer') echo 'customer-dashboard.php';
            else echo 'provider-dashboard.php';
          ?>" class="btn-link">Dashboard</a>
          <a href="../components/Logout.php" class="btn-link">Logout</a>
        <?php else: ?>
          <a href="login.php" class="btn-link">Login</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <section class="hero">
    <h2>Reliable Home Services in Kathmandu</h2>
    <p>Book plumbers, electricians, cleaners & more with just a few clicks.</p>
    <a href="<?php echo isset($_SESSION['user_id']) ? ($_SESSION['role'] === 'customer' ? 'book-service.php' : 'customer-home.php') : 'login.php'; ?>" class="cta-btn">Book a Service</a>
  </section>

  <section class="doorstep">
    <h3>Services at Your Doorstep</h3>
    <p style="text-align:center;">Fast and reliable home service delivery — right where you live.</p>
    <div class="doorstep-cards">
      <div class="doorstep-card">
        <img src="../artifacts/kathmandu.jpg" alt="Kathmandu">
        <h4>Kathmandu</h4>
      </div>
      <div class="doorstep-card">
        <img src="../artifacts/lalitpur.jpg" alt="Lalitpur">
        <h4>Lalitpur</h4>
      </div>
      <div class="doorstep-card">
        <img src="../artifacts/bhaktapur.jpg" alt="Bhaktapur">
        <h4>Bhaktapur</h4>
      </div>
    </div>
  </section>

  <section id="services" class="services">
    <div id="booking"></div>
    <h3>Our Services</h3>
    <div class="service-list">
      <div class="card">🛠️ Plumbing</div>
      <div class="card">💡 Electrical</div>
      <div class="card">🧼 Cleaning</div>
      <div class="card">🪚 Carpentry</div>
      <div class="card">🧽 Housekeeping</div>
      <div class="card">🔌 Appliance Repair</div>
      <div class="card">❄️ AC Servicing</div>
      <div class="card">🖥️ Computer Support</div>
      <div class="card">📦 Packers & Movers</div>
      <div class="card">🏠 Home Renovation</div>
    </div>
  </section>

  <section class="how-it-works">
    <h2>How It Works</h2>
    <div class="steps">
      <div class="step"><i class="fas fa-search"></i><br>Search Service</div>
      <div class="step"><i class="fas fa-calendar-check"></i><br>Book Appointment</div>
      <div class="step"><i class="fas fa-check-circle"></i><br>Get Service</div>
    </div>
  </section>

  <section class="stats">
    <h2>Why Choose Sewamandu?</h2>
    <div class="stat-cards">
      <div class="stat"><i class="fas fa-user-check"></i><br>100% Verified Experts</div>
      <div class="stat"><i class="fas fa-clock"></i><br>24/7 Booking</div>
      <div class="stat"><i class="fas fa-star"></i><br>10,000+ Happy Customers</div>
    </div>
  </section>

  <section class="stats">
    <h2>The Sewamandu Difference</h2>
    <div class="stat-cards extended">
      <div class="stat">
        <i class="fas fa-user-check fa-lg"></i>
        <h4>Verified Experts</h4>
        <p>All professionals thoroughly vetted</p>
      </div>
      <div class="stat">
        <i class="fas fa-file-invoice-dollar fa-lg"></i>
        <h4>Transparent Pricing</h4>
        <p>No hidden charges, clear pricing</p>
      </div>
      <div class="stat">
        <i class="fas fa-shield-alt fa-lg"></i>
        <h4>Quality Guarantee</h4>
        <p>100% satisfaction guaranteed</p>
      </div>
      <div class="stat">
        <i class="fas fa-clock fa-lg"></i>
        <h4>24/7 Support</h4>
        <p>Round-the-clock emergency support</p>
      </div>
      <div class="stat">
        <i class="fas fa-umbrella fa-lg"></i>
        <h4>Insurance Coverage</h4>
        <p>Full coverage for peace of mind</p>
      </div>
      <div class="stat">
        <i class="fas fa-lock fa-lg"></i>
        <h4>Safety First</h4>
        <p>Strict safety protocols followed</p>
      </div>
    </div>
  </section>


  <section class="testimonials">
    <h3>What Our Customers Say</h3>
    <div class="card">"Great service and quick response!" - Aayush</div>
    <div class="card">"Highly recommend Sewamandu!" - Pratiksha</div>
  </section>

  <footer id="contact" class="footer">
    <div class="footer-container">
      <div class="footer-column">
        <h4>Sewamandu</h4>
        <p>Reliable home services in Kathmandu, Lalitpur & Bhaktapur.</p>
      </div>
      <div class="footer-column">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#services">Services</a></li>
          <li><a href="#booking">Book Now</a></li>
          <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="<?php 
              if ($_SESSION['role'] === 'admin') echo 'admin-dashboard.php';
              elseif ($_SESSION['role'] === 'customer') echo 'customer-dashboard.php';
              else echo 'provider-dashboard.php';
            ?>">Dashboard</a></li>
            <li><a href="../components/Logout.php">Logout</a></li>
          <?php else: ?>
            <li><a href="login.php">Login</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="footer-column">
        <h4>Contact Us</h4>
        <p>Email: officialsewamandu@gmail.com</p>
        <p>Phone: +977-9800000000</p>
        <div class="social-icons">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-twitter"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2025 Sewamandu. All rights reserved.</p>
    </div>
  </footer>
</body>
</html>