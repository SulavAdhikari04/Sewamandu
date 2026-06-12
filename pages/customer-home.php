<?php
require_once '../components/SessionManager.php';
require_once '../components/Database.php';

// Add cookies
setcookie('customer_home_visited', 'true', time() + (86400 * 30), "/");
setcookie('customer_user_id', $_SESSION['user_id'], time() + (86400 * 30), "/");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit();
}

$customerName = $_SESSION['username'] ?? 'there';
$initial = strtoupper(substr(trim($customerName), 0, 1)) ?: 'U';

// ---- Live content pulled from the database (with static fallbacks) ----
$serviceIcons = [
    'plumbing' => 'fas fa-wrench', 'electrical' => 'fas fa-bolt', 'cleaning' => 'fas fa-broom',
    'carpentry' => 'fas fa-hammer', 'housekeeping' => 'fas fa-soap', 'appliance repair' => 'fas fa-plug',
    'ac servicing' => 'fas fa-snowflake', 'computer support' => 'fas fa-laptop',
    'packers & movers' => 'fas fa-box', 'packers and movers' => 'fas fa-box',
    'home renovation' => 'fas fa-home', 'home shifting' => 'fas fa-truck',
    'painting' => 'fas fa-paint-brush', 'gardening' => 'fas fa-seedling',
];

$services = [
    ['name' => 'Plumbing'], ['name' => 'Electrical'], ['name' => 'Cleaning'],
    ['name' => 'Carpentry'], ['name' => 'Housekeeping'], ['name' => 'Appliance Repair'],
    ['name' => 'AC Servicing'], ['name' => 'Computer Support'],
    ['name' => 'Packers & Movers'], ['name' => 'Home Renovation'],
];

$stats = ['customers' => '10,000+', 'experts' => '100%', 'support' => '24/7'];

$conn = getDBConnectionSafe();
if ($conn) {
    if ($res = $conn->query("SELECT id, name FROM services ORDER BY name ASC")) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        if (!empty($rows)) { $services = $rows; }
        $res->free();
    }
    if ($res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")) {
        $c = (int) ($res->fetch_assoc()['c'] ?? 0);
        if ($c > 0) { $stats['customers'] = number_format($c) . '+'; }
        $res->free();
    }
    if ($res = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM service_providers WHERE status = 'approved'")) {
        $c = (int) ($res->fetch_assoc()['c'] ?? 0);
        if ($c > 0) { $stats['experts'] = number_format($c) . '+'; }
        $res->free();
    }
    closeDBConnection($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome back — Sewamandu</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/home-motion.css">
</head>
<body>

  <!-- ============ NAVBAR ============ -->
  <header class="cine-nav">
    <a href="#top" class="brand">Sewa<span>mandu</span></a>
    <nav>
      <a href="#services">Services</a>
      <a href="#how">How it works</a>
      <a href="#contact">Contact</a>
      <a href="book-service.php" class="nav-cta">Book a Service</a>
      <div class="profile-menu">
        <div class="profile-trigger" id="profile-icon">
          <span class="avatar"><?php echo htmlspecialchars($initial); ?></span>
          <i class="fas fa-chevron-down" style="font-size:0.75rem;"></i>
        </div>
        <div class="dropdown-tray" id="profile-tray">
          <a href="customer-dashboard.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
          <a href="book-service.php"><i class="fas fa-calendar-plus"></i> Book Service</a>
          <a href="../components/Logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
        </div>
      </div>
    </nav>
  </header>

  <!-- ============ HERO — layered parallax + scroll zoom ============ -->
  <section class="cine-hero" id="top">
    <div class="hero-layer hero-layer--bg"></div>
    <div class="hero-layer hero-layer--mid"></div>
    <div class="hero-layer hero-layer--vignette"></div>

    <div class="hero-chip c1"><i class="fas fa-wrench"></i> Plumbing</div>
    <div class="hero-chip c2"><i class="fas fa-bolt"></i> Electrical</div>
    <div class="hero-chip c3"><i class="fas fa-broom"></i> Cleaning</div>
    <div class="hero-chip c4"><i class="fas fa-snowflake"></i> AC Servicing</div>

    <div class="hero-content">
      <span class="hero-eyebrow">Welcome back, <?php echo htmlspecialchars($customerName); ?></span>
      <h1>What do you need<br><span class="grad">done today?</span></h1>
      <p>Book vetted plumbers, electricians, cleaners and more — in just a few taps. Your trusted experts are ready.</p>
      <a href="book-service.php" class="hero-cta">Book a Service <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="scroll-cue" aria-hidden="true"></div>
  </section>

  <!-- ============ DOORSTEP — full-width expand-on-scroll + layered cities ============ -->
  <section class="cine-section doorstep-cine">
    <div class="section-head reveal">
      <span class="kicker">At your doorstep</span>
      <h2>Service that reaches every corner of the valley</h2>
      <p>Fast, reliable home service delivery — right where you live.</p>
    </div>

    <div class="expand-stage" data-progress>
      <div class="expand-stage__img"></div>
      <div class="expand-stage__caption">
        <h3>One tap from the help you need</h3>
        <p>From temple towns to busy streets — our experts are minutes away.</p>
      </div>
    </div>

    <div class="city-row">
      <div class="city-card" data-parallax data-speed="0.06">
        <img src="../artifacts/kathmandu.jpg" alt="Kathmandu">
        <h4>Kathmandu</h4>
      </div>
      <div class="city-card" data-parallax data-speed="0.14">
        <img src="../artifacts/lalitpur.jpg" alt="Lalitpur">
        <h4>Lalitpur</h4>
      </div>
      <div class="city-card" data-parallax data-speed="0.10">
        <img src="../artifacts/bhaktapur.jpg" alt="Bhaktapur">
        <h4>Bhaktapur</h4>
      </div>
    </div>
  </section>

  <!-- ============ SERVICES — zoom-in reveal grid ============ -->
  <section class="cine-section bg-white" id="services">
    <div id="booking"></div>
    <div class="section-head reveal">
      <span class="kicker">What we do</span>
      <h2>Our Services</h2>
      <p>Pick a service and book a trusted professional in minutes.</p>
    </div>

    <div class="svc-grid stagger">
      <?php foreach ($services as $svc): ?>
        <?php
          $name = $svc['name'];
          $iconClass = $serviceIcons[strtolower(trim($name))] ?? 'fas fa-tools';
          $display = ucwords(strtolower(trim($name)));
          $bookHref = isset($svc['id'])
            ? 'book-service.php?service_id=' . (int) $svc['id']
            : 'book-service.php';
        ?>
<<<<<<< HEAD
        <a href="book-service.php" class="svc-card" style="text-decoration:none;">
          <div class="ic"><i class="<?php echo $iconClass; ?>"></i></div>
=======
        <a href="<?php echo $bookHref; ?>" class="svc-card" style="text-decoration:none;">
          <div class="ic"><?php echo $icon; ?></div>
>>>>>>> fd226d6 (Theme customer dashboard and pre-select service from home cards)
          <h4><?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?></h4>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ============ HOW IT WORKS — parallax step reveals ============ -->
  <section class="cine-section bg-cream" id="how">
    <div class="section-head reveal">
      <span class="kicker">Simple as 1·2·3</span>
      <h2>How It Works</h2>
      <p>Three effortless steps from search to service.</p>
    </div>

    <div class="steps-row stagger">
      <div class="step-card">
        <div class="step-num"><i class="fas fa-search"></i></div>
        <h4>Search Service</h4>
        <p>Browse trusted experts for any home need in seconds.</p>
      </div>
      <div class="step-card">
        <div class="step-num"><i class="fas fa-calendar-check"></i></div>
        <h4>Book Appointment</h4>
        <p>Pick a time that suits you — confirm in a tap.</p>
      </div>
      <div class="step-card">
        <div class="step-num"><i class="fas fa-check-circle"></i></div>
        <h4>Get Service</h4>
        <p>A vetted professional arrives and gets it done right.</p>
      </div>
    </div>
  </section>

  <!-- ============ CINEMATIC BANNER — full-bleed parallax ============ -->
  <section class="cine-banner">
    <div class="cine-banner__img" data-parallax data-speed="0.18"></div>
    <div class="cine-banner__inner reveal-zoom">
      <h2>Why thousands across the valley<br>choose Sewamandu</h2>
      <p>Verified experts. Transparent pricing. Round-the-clock support.</p>
      <div class="stat-strip">
        <div><div class="num"><?php echo htmlspecialchars($stats['customers']); ?></div><div class="lbl">Happy Customers</div></div>
        <div><div class="num"><?php echo htmlspecialchars($stats['experts']); ?></div><div class="lbl">Verified Experts</div></div>
        <div><div class="num"><?php echo htmlspecialchars($stats['support']); ?></div><div class="lbl">Booking &amp; Support</div></div>
      </div>
    </div>
  </section>

  <!-- ============ THE DIFFERENCE — reveal cards ============ -->
  <section class="cine-section bg-white">
    <div class="section-head reveal">
      <span class="kicker">Built on trust</span>
      <h2>The Sewamandu Difference</h2>
      <p>Every booking backed by standards you can count on.</p>
    </div>

    <div class="diff-grid stagger">
      <div class="diff-card"><div class="ic"><i class="fas fa-user-check"></i></div><h4>Verified Experts</h4><p>All professionals are thoroughly vetted and background-checked.</p></div>
      <div class="diff-card"><div class="ic"><i class="fas fa-file-invoice-dollar"></i></div><h4>Transparent Pricing</h4><p>No hidden charges — clear, upfront pricing every time.</p></div>
      <div class="diff-card"><div class="ic"><i class="fas fa-shield-alt"></i></div><h4>Quality Guarantee</h4><p>100% satisfaction guaranteed on every service.</p></div>
      <div class="diff-card"><div class="ic"><i class="fas fa-clock"></i></div><h4>24/7 Support</h4><p>Round-the-clock emergency support whenever you need it.</p></div>
      <div class="diff-card"><div class="ic"><i class="fas fa-umbrella"></i></div><h4>Insurance Coverage</h4><p>Full coverage for complete peace of mind.</p></div>
      <div class="diff-card"><div class="ic"><i class="fas fa-lock"></i></div><h4>Safety First</h4><p>Strict safety protocols followed on every visit.</p></div>
    </div>
  </section>

  <!-- ============ TESTIMONIALS ============ -->
  <section class="cine-section bg-cream">
    <div class="section-head reveal">
      <span class="kicker">Loved by locals</span>
      <h2>What Our Customers Say</h2>
    </div>

    <div class="quote-grid stagger">
      <div class="quote-card">
        <div class="mark">&ldquo;</div>
        <p>Great service and quick response! The plumber arrived on time and fixed everything perfectly.</p>
        <div class="who"><span class="av">A</span><div><b>Aayush</b><span>Kathmandu</span></div></div>
      </div>
      <div class="quote-card">
        <div class="mark">&ldquo;</div>
        <p>Highly recommend Sewamandu! Booking was effortless and the expert was incredibly professional.</p>
        <div class="who"><span class="av">P</span><div><b>Pratiksha</b><span>Lalitpur</span></div></div>
      </div>
    </div>
  </section>

  <!-- ============ FOOTER ============ -->
  <footer class="cine-footer" id="contact">
    <div class="cols">
      <div class="footer-col">
        <h4>Sewamandu</h4>
        <p>Reliable home services in Kathmandu, Lalitpur &amp; Bhaktapur — vetted experts, cinematic ease.</p>
      </div>
      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#services">Services</a></li>
          <li><a href="book-service.php">Book Now</a></li>
          <li><a href="customer-dashboard.php">Dashboard</a></li>
          <li><a href="../components/Logout.php">Logout</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact Us</h4>
        <p>Email: support@sewamandu.com</p>
        <p>Phone: +977-9800000000</p>
        <div class="social">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
    </div>
    <div class="copy">&copy; 2025 Sewamandu. All rights reserved.</div>
  </footer>

  <script src="../js/home-motion.js"></script>
  <script>
    const profileIcon = document.getElementById("profile-icon");
    const profileTray = document.getElementById("profile-tray");
    profileIcon.addEventListener("click", (e) => {
      e.stopPropagation();
      profileTray.classList.toggle("open");
    });
    window.addEventListener("click", (e) => {
      if (!e.target.closest(".profile-menu")) profileTray.classList.remove("open");
    });
  </script>
</body>
</html>
