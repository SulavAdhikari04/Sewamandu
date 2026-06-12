<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../components/Database.php';

// Resolve role-aware dashboard target (kept from original logic)
$dashboardLink = 'login.php';
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        $dashboardLink = 'admin-dashboard.php';
    } elseif ($_SESSION['role'] === 'customer') {
        $dashboardLink = 'customer-dashboard.php';
    } else {
        $dashboardLink = 'provider-dashboard.php';
    }
}

// Where the primary "Book" CTA should point
$bookLink = isset($_SESSION['user_id'])
    ? ($_SESSION['role'] === 'customer' ? 'book-service.php' : 'customer-home.php')
    : 'login.php';

// ---- Live content pulled from the database (with static fallbacks) ----

// Emoji icon map so DB-driven services still get a nice icon.
$serviceIcons = [
    'plumbing' => '🛠️', 'electrical' => '💡', 'cleaning' => '🧼',
    'carpentry' => '🪚', 'housekeeping' => '🧽', 'appliance repair' => '🔌',
    'ac servicing' => '❄️', 'computer support' => '🖥️',
    'packers & movers' => '📦', 'packers and movers' => '📦',
    'home renovation' => '🏠', 'home shifting' => '🚚',
    'painting' => '🎨', 'gardening' => '🌿',
];

// Fallback service set (used when DB is unavailable or empty)
$services = [
    ['name' => 'Plumbing'], ['name' => 'Electrical'], ['name' => 'Cleaning'],
    ['name' => 'Carpentry'], ['name' => 'Housekeeping'], ['name' => 'Appliance Repair'],
    ['name' => 'AC Servicing'], ['name' => 'Computer Support'],
    ['name' => 'Packers & Movers'], ['name' => 'Home Renovation'],
];

// Marketing fallbacks for the stat strip
$stats = [
    'customers' => '10,000+',
    'experts'   => '100%',
    'support'   => '24/7',
];

$conn = getDBConnectionSafe();
if ($conn) {
    if ($res = $conn->query("SELECT name FROM services ORDER BY name ASC")) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        if (!empty($rows)) {
            $services = $rows;
        }
        $res->free();
    }

    if ($res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")) {
        $c = (int) ($res->fetch_assoc()['c'] ?? 0);
        if ($c > 0) {
            $stats['customers'] = number_format($c) . '+';
        }
        $res->free();
    }

    if ($res = $conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM service_providers WHERE status = 'approved'")) {
        $c = (int) ($res->fetch_assoc()['c'] ?? 0);
        if ($c > 0) {
            $stats['experts'] = number_format($c) . '+';
        }
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
  <title>Sewamandu — Home Services, Cinematically Simple</title>
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?php echo $dashboardLink; ?>">Dashboard</a>
        <a href="../components/Logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
      <?php endif; ?>
      <a href="<?php echo $bookLink; ?>" class="nav-cta">Book a Service</a>
    </nav>
  </header>

  <!-- ============ HERO — layered parallax + scroll zoom ============ -->
  <section class="cine-hero" id="top">
    <div class="hero-layer hero-layer--bg"></div>
    <div class="hero-layer hero-layer--mid"></div>
    <div class="hero-layer hero-layer--vignette"></div>

    <!-- ambient floating service chips (foreground depth) -->
    <div class="hero-chip c1"><i class="fas fa-wrench"></i> Plumbing</div>
    <div class="hero-chip c2"><i class="fas fa-bolt"></i> Electrical</div>
    <div class="hero-chip c3"><i class="fas fa-broom"></i> Cleaning</div>
    <div class="hero-chip c4"><i class="fas fa-snowflake"></i> AC Servicing</div>

    <div class="hero-content">
      <span class="hero-eyebrow">Kathmandu · Lalitpur · Bhaktapur</span>
      <h1>Reliable home services,<br><span class="grad">right at your doorstep</span></h1>
      <p>Book vetted plumbers, electricians, cleaners and more — in just a few taps. Trusted experts, transparent pricing, cinematic ease.</p>
      <a href="<?php echo $bookLink; ?>" class="hero-cta">Book a Service <i class="fas fa-arrow-right"></i></a>
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

    <!-- expand-on-scroll showcase: grows from inset+rounded to full-bleed -->
    <div class="expand-stage" data-progress>
      <div class="expand-stage__img"></div>
      <div class="expand-stage__caption">
        <h3>One tap from the help you need</h3>
        <p>From temple towns to busy streets — our experts are minutes away.</p>
      </div>
    </div>

    <!-- layered city cards, each drifting at its own parallax speed -->
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
      <p>Everything your home needs, handled by trusted professionals.</p>
    </div>

    <div class="svc-grid stagger">
      <?php foreach ($services as $svc): ?>
        <?php
          $name = $svc['name'];
          $icon = $serviceIcons[strtolower(trim($name))] ?? '🧰';
          $display = ucwords(strtolower(trim($name)));
        ?>
        <div class="svc-card">
          <div class="ic"><?php echo $icon; ?></div>
          <h4><?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?></h4>
        </div>
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
          <li><a href="#booking">Book Now</a></li>
          <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="<?php echo $dashboardLink; ?>">Dashboard</a></li>
            <li><a href="../components/Logout.php">Logout</a></li>
          <?php else: ?>
            <li><a href="login.php">Login</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact Us</h4>
        <p>Email: officialsewamandu@gmail.com</p>
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
</body>
</html>
