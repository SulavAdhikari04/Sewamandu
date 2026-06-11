<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../components/AssetPath.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sewamandu - Home Services at Your Doorstep</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(asset('css/home.css')); ?>">
</head>
<body>
  <header id="main-header">
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
    <div class="hero-content">
      <h2>Reliable Home Services in Kathmandu</h2>
      <p>Book plumbers, electricians, cleaners &amp; more with just a few clicks.</p>
      <a href="<?php echo isset($_SESSION['user_id']) ? ($_SESSION['role'] === 'customer' ? 'book-service.php' : 'customer-home.php') : 'login.php'; ?>" class="cta-btn">
        Book a Service <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </section>

  <section class="doorstep">
    <div class="section-header reveal">
      <h3 class="section-title">Services at Your Doorstep</h3>
      <p class="section-subtitle">Fast and reliable home service delivery — right where you live.</p>
    </div>
    <div class="doorstep-cards">
      <div class="doorstep-card reveal reveal-delay-1">
        <div class="img-wrap">
          <img src="<?php echo htmlspecialchars(asset('artifacts/kathmandu.jpg')); ?>" alt="Kathmandu">
        </div>
        <h4>Kathmandu</h4>
      </div>
      <div class="doorstep-card reveal reveal-delay-2">
        <div class="img-wrap">
          <img src="<?php echo htmlspecialchars(asset('artifacts/lalitpur.jpg')); ?>" alt="Lalitpur">
        </div>
        <h4>Lalitpur</h4>
      </div>
      <div class="doorstep-card reveal reveal-delay-3">
        <div class="img-wrap">
          <img src="<?php echo htmlspecialchars(asset('artifacts/bhaktapur.jpg')); ?>" alt="Bhaktapur">
        </div>
        <h4>Bhaktapur</h4>
      </div>
    </div>
  </section>

  <section id="services" class="services">
    <div id="booking"></div>
    <div class="section-header reveal">
      <h3 class="section-title">Our Services</h3>
      <p class="section-subtitle">Professional help for every corner of your home.</p>
    </div>
    <div class="service-list">
      <div class="card reveal reveal-delay-1" data-service="plumbing">
        <span class="service-icon"><i class="fas fa-wrench"></i></span>
        <span class="service-name">Plumbing</span>
      </div>
      <div class="card reveal reveal-delay-1" data-service="electrical">
        <span class="service-icon"><i class="fas fa-bolt"></i></span>
        <span class="service-name">Electrical</span>
      </div>
      <div class="card reveal reveal-delay-2" data-service="cleaning">
        <span class="service-icon"><i class="fas fa-spray-can-sparkles"></i></span>
        <span class="service-name">Cleaning</span>
      </div>
      <div class="card reveal reveal-delay-2" data-service="carpentry">
        <span class="service-icon"><i class="fas fa-hammer"></i></span>
        <span class="service-name">Carpentry</span>
      </div>
      <div class="card reveal reveal-delay-3" data-service="housekeeping">
        <span class="service-icon"><i class="fas fa-broom"></i></span>
        <span class="service-name">Housekeeping</span>
      </div>
      <div class="card reveal reveal-delay-3" data-service="appliance">
        <span class="service-icon"><i class="fas fa-plug"></i></span>
        <span class="service-name">Appliance Repair</span>
      </div>
      <div class="card reveal reveal-delay-4" data-service="ac">
        <span class="service-icon"><i class="fas fa-snowflake"></i></span>
        <span class="service-name">AC Servicing</span>
      </div>
      <div class="card reveal reveal-delay-4" data-service="computer">
        <span class="service-icon"><i class="fas fa-laptop"></i></span>
        <span class="service-name">Computer Support</span>
      </div>
      <div class="card reveal reveal-delay-5" data-service="movers">
        <span class="service-icon"><i class="fas fa-box-open"></i></span>
        <span class="service-name">Packers &amp; Movers</span>
      </div>
      <div class="card reveal reveal-delay-5" data-service="renovation">
        <span class="service-icon"><i class="fas fa-house-chimney"></i></span>
        <span class="service-name">Home Renovation</span>
      </div>
    </div>
  </section>

  <section class="how-it-works">
    <div class="section-header reveal">
      <h2 class="section-title">How It Works</h2>
      <p class="section-subtitle">Three simple steps to get your home serviced.</p>
    </div>
    <div class="steps">
      <div class="step reveal reveal-delay-1" data-step="1">
        <i class="fas fa-search"></i>
        Search Service
      </div>
      <div class="step reveal reveal-delay-2" data-step="2">
        <i class="fas fa-calendar-check"></i>
        Book Appointment
      </div>
      <div class="step reveal reveal-delay-3" data-step="3">
        <i class="fas fa-check-circle"></i>
        Get Service
      </div>
    </div>
  </section>

  <section class="stats stats--why">
    <div class="section-header reveal">
      <h2 class="section-title section-title--light">Why Choose Sewamandu?</h2>
    </div>
    <div class="stat-cards">
      <div class="stat reveal reveal-delay-1">
        <i class="fas fa-user-check"></i>
        100% Verified Experts
      </div>
      <div class="stat reveal reveal-delay-2">
        <i class="fas fa-clock"></i>
        24/7 Booking
      </div>
      <div class="stat reveal reveal-delay-3">
        <i class="fas fa-star"></i>
        10,000+ Happy Customers
      </div>
    </div>
  </section>

  <section class="stats stats--difference">
    <div class="section-header reveal">
      <h2 class="section-title section-title--light">The Sewamandu Difference</h2>
    </div>
    <div class="stat-cards extended">
      <div class="stat reveal reveal-delay-1">
        <i class="fas fa-user-check"></i>
        <h4>Verified Experts</h4>
        <p>All professionals thoroughly vetted</p>
      </div>
      <div class="stat reveal reveal-delay-1">
        <i class="fas fa-file-invoice-dollar"></i>
        <h4>Transparent Pricing</h4>
        <p>No hidden charges, clear pricing</p>
      </div>
      <div class="stat reveal reveal-delay-2">
        <i class="fas fa-shield-alt"></i>
        <h4>Quality Guarantee</h4>
        <p>100% satisfaction guaranteed</p>
      </div>
      <div class="stat reveal reveal-delay-2">
        <i class="fas fa-clock"></i>
        <h4>24/7 Support</h4>
        <p>Round-the-clock emergency support</p>
      </div>
      <div class="stat reveal reveal-delay-3">
        <i class="fas fa-umbrella"></i>
        <h4>Insurance Coverage</h4>
        <p>Full coverage for peace of mind</p>
      </div>
      <div class="stat reveal reveal-delay-3">
        <i class="fas fa-lock"></i>
        <h4>Safety First</h4>
        <p>Strict safety protocols followed</p>
      </div>
    </div>
  </section>

  <section class="testimonials">
    <div class="section-header reveal">
      <h3 class="section-title">What Our Customers Say</h3>
    </div>
    <div class="testimonial-grid">
      <div class="card reveal reveal-delay-1">
        <div class="testimonial-stars">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">Great service and quick response!</p>
        <span class="testimonial-author">— Aayush</span>
      </div>
      <div class="card reveal reveal-delay-2">
        <div class="testimonial-stars">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
        <p class="testimonial-text">Highly recommend Sewamandu!</p>
        <span class="testimonial-author">— Pratiksha</span>
      </div>
    </div>
  </section>

  <footer id="contact" class="footer">
    <div class="footer-container">
      <div class="footer-column">
        <h4>Sewamandu</h4>
        <p>Reliable home services in Kathmandu, Lalitpur &amp; Bhaktapur.</p>
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
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2025 Sewamandu. All rights reserved.</p>
    </div>
  </footer>

  <script>
    const header = document.getElementById('main-header');
    window.addEventListener('scroll', () => {
      header.classList.toggle('scrolled', window.scrollY > 20);
    });

    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    reveals.forEach(el => observer.observe(el));
  </script>
</body>
</html>
