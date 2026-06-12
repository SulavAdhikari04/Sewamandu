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

// Pre-select a service when arriving from a "Our Services" card (?service_id=)
if ($_SERVER["REQUEST_METHOD"] !== "POST" && isset($_GET['service_id'])) {
    $service_id = intval($_GET['service_id']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['show_providers'])) {
    $service_id = intval($_POST['service_id']);
    $service_date = $_POST['date'];
    $service_time = $_POST['time'] ?? '';
    $address = $_POST['address'];
    // Fetch providers for this service
    $stmt = $conn->prepare("SELECT u.id, u.username, sp.price, sp.availability FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE sp.service_id = ? AND sp.status = 'approved'");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (isProviderTimeSlotAvailable($conn, $row['id'], $service_date, $service_time)) {
            $providers[] = $row;
        }
    }
    if (count($providers) === 0) {
        $message = $no_providers_message;
        $message_type = 'error';
    }
    $show_providers = true;
    $stmt->close();
}

// Step 2: Handle booking with selected provider
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_with_provider'])) {
    $customer_id = $_SESSION['user_id']; // The logged-in customer
    $service_id = intval($_POST['service_id']);
    $provider_id = intval($_POST['provider_id']);
    $service_date = $_POST['date'];
    $service_time = $_POST['time'] ?? '';
    $address = $_POST['address'];
    $booking_date = date('Y-m-d');
    $status = 'pending_provider';

    if (!isProviderTimeSlotAvailable($conn, $provider_id, $service_date, $service_time)) {
        $message = 'This provider is already booked at the selected date and time. Please choose another provider or time slot.';
        $message_type = 'error';
        $show_providers = true;

        $stmt = $conn->prepare("SELECT u.id, u.username, sp.price, sp.availability FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE sp.service_id = ? AND sp.status = 'approved'");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (isProviderTimeSlotAvailable($conn, $row['id'], $service_date, $service_time)) {
                $providers[] = $row;
            }
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO bookings (customer_id, service_id, status, booking_date, service_date, service_time, address, provider_id, served) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)");
        $stmt->bind_param("iisssssi", $customer_id, $service_id, $status, $booking_date, $service_date, $service_time, $address, $provider_id);
        if ($stmt->execute()) {
            $message = "Booking request sent!";
            $message_type = 'success';
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
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
        <p><?= $show_providers ? 'Choose a provider to confirm your booking.' : 'Tell us what you need and when — we\'ll find the right expert.' ?></p>
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
                <button type="button" class="cal-btn ghost" id="calRemove">Remove</button>
                <button type="button" class="cal-btn solid" id="calDone">Done</button>
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
          <label for="address">Your Address</label>
          <textarea id="address" name="address" placeholder="Street, area, city…" required><?= htmlspecialchars($address) ?></textarea>
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
            <span class="provider-label">Select a Provider</span>
            <div class="provider-cards">
              <?php foreach ($providers as $provider): ?>
                <label class="provider-card">
                  <input type="radio" name="provider_id" value="<?= $provider['id'] ?>" required class="provider-radio">
                  <div class="provider-name"><?= htmlspecialchars($provider['username']) ?></div>
                  <div class="provider-price">Rs. <?= htmlspecialchars($provider['price']) ?></div>
                  <div class="provider-availability">Availability: <?= htmlspecialchars($provider['availability']) ?></div>
                </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" name="book_with_provider" class="auth-btn">Confirm Booking <i class="fas fa-check"></i></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function () {
      const MONTHS = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

      const parseISO = (s) => { const [y,m,d] = s.split('-').map(Number); return new Date(y, m-1, d); };
      const iso = (d) => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
      const stripTime = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
      const sameDay = (a,b) => a && b && a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate();

      /* ---------- Date picker (calendar) ---------- */
      const dp = document.getElementById('datePicker');
      const dateTrigger = document.getElementById('dateTrigger');
      const calPop = document.getElementById('calPop');
      const calTitle = document.getElementById('calTitle');
      const calDays = document.getElementById('calDays');
      const calPrev = document.getElementById('calPrev');
      const calNext = document.getElementById('calNext');
      const calRemove = document.getElementById('calRemove');
      const calDone = document.getElementById('calDone');
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
          cell.addEventListener('click', () => { pending = date; renderCal(); });
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
      calDone.addEventListener('click', () => {
        if (pending) {
          selected = new Date(pending);
          dateInput.value = iso(selected);
          dateValueText.textContent = selected.toLocaleDateString(undefined, { weekday:'short', day:'numeric', month:'short', year:'numeric' });
          dp.classList.add('filled');
        }
        closeCal();
      });

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