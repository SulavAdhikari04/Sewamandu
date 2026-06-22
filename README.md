# Sewamandu - Home Services at Your Doorstep

Sewamandu is a web-based platform that connects residents of Kathmandu, Lalitpur, and Bhaktapur with verified home service professionals — plumbing, electrical, cleaning, and more — all bookable in a few clicks.

## 🧱 Tech Stack
- **Backend**: PHP (procedural, `mysqli` with prepared statements)
- **Database**: MySQL
- **Frontend**: HTML, CSS, vanilla JavaScript
- **Email**: Gmail SMTP via PHPMailer (Composer-managed)
- **Server**: Apache (via XAMPP) or PHP's built-in server

## 🌟 Major Features

### 1. Multi-Role Dashboard System
- **Customers**: Browse services, book providers, and track booking status.
- **Service Providers**: List services, set price/availability/service area, upload certificates, and mark bookings done/not done.
- **Admin**: Approve/reject providers and their service listings, manage users, and monitor platform-wide stats.

### 2. Authentication & Account Security
- Email/password registration and login with hashed passwords.
- **Email OTP verification** (6-digit code, 10-minute expiry, 5 max attempts) for registration and, when enabled, login.
- **Optional Two-Factor Authentication (2FA)** per user — login requires an email OTP unless the device is already trusted.
- **Trusted device** cookies (HMAC-signed, 1-year TTL) let returning users skip repeated 2FA prompts.
- **Forgot/Reset Password** flow via a securely generated token emailed to the user.
- Session management with idle timeout (2 hours) and periodic session ID regeneration for security.

### 3. Provider Verification & Certification
- Providers attach a verification document (PDF/image/doc) at registration; admin reviews and approves or rejects the account.
- Per-service certificates (PDF/JPEG/PNG) can be uploaded by providers and downloaded by both the provider and admin.

### 4. Service Catalog & Booking
- Services span categories like Plumbing, Electrical, Cleaning, Carpentry, AC/Appliance Repair, and Packers & Movers.
- Customers fill in service, date, and time, then see a list of providers actually available for that slot — each provider's profile modal shows their rating, review count, completed-service count, phone, and address.
- **Two-step availability check**: the slot is filtered to available providers up front, then rechecked right before saving — showing "no providers available" if none qualify, or "this provider is already booked" if the chosen one was just taken.
- Two customers can't book the same provider for the same slot.
- Clicking a service card on the home page jumps straight into the booking page with that service pre-selected.
- Booking lifecycle: `pending_provider` → `pending_admin`/`confirmed` → `completed` (or `denied` / `rejected_by_provider` / `rejected_by_admin`).
- Providers can mark a confirmed booking as completed or not completed.

### 5. Reviews & Ratings
- Customers can rate (1–5 stars) and comment on a provider once a booking is marked completed.
- Customers may optionally hide their name on a review (shown as "Anonymous" to the provider and in public listings).
- Providers see their reviews on their dashboard; admin can view all review logs platform-wide.

### 6. Provider Wallet & Commission
- Each provider has a wallet balance (top-up supported from the provider dashboard) intended for paying platform commission.

### 7. Admin Controls
- Approve or reject provider accounts and individual service listings.
- Delete users (cascading their bookings) or remove services.
- Dashboard view of pending verifications and platform-wide stats (users, services, active bookings).
- Full booking logs and review logs across the platform.

### 8. Email Notification System
Integrated with Gmail SMTP (via PHPMailer) for:
- Welcome emails on registration
- OTP/verification codes
- Password reset links

### 9. UX Polish
- Auto-capitalization on name fields.
- Star-rating UI and date-picker fixes on the booking form.

---

## 🔄 Core Workflows

### 📍 Customer Workflow
1. **Register/Login** — verify email via OTP.
2. **Browse Services** — from the home page or customer dashboard.
3. **Book** — choose a service, date, and available time slot.
4. **Track** — monitor booking status (Waiting for Provider → Confirmed → Completed) from the dashboard.

### 📍 Service Provider Workflow
1. **Register** as a Service Provider and attach a verification document.
2. **Wait for Admin Approval** of the account.
3. **List Services** — once active, add services with price, availability, service area, and optional certificate.
4. **Manage Bookings** — accept incoming bookings and mark them done/not done.

### 📍 Admin Workflow
1. **Monitor Dashboard** — view stats on users, providers, and bookings.
2. **Approve Providers** — review uploaded documents, approve or reject accounts.
3. **Approve Services** — review and approve/reject individual service listings.
4. **Manage Users** — search, review, or remove accounts as needed.

---

## 🧭 Known Limitations / Planned
- **Login via phone number** — not yet implemented; login is email-based only.
- **Location field in the booking form** — customers can't yet attach a specific address/location to a booking request.
- **5-booking commission gate** — the wallet exists, but a provider isn't yet capped at 5 active bookings until commission is paid.

---

## 🏗️ Project Structure
```
sewamandu/
│
├── components/           # Reusable PHP logic (Database, Session, OTP, 2FA, Trusted Device, Booking Status, Email, Logout)
├── pages/                # Application pages (home, login, register, dashboards, booking, password reset, OTP verification)
├── css/                  # Stylesheets per page/section
├── js/                   # Client-side scripts (booking form, input formatting, home page motion)
├── vendor/                # Composer dependencies (PHPMailer)
├── artifacts/             # Project screenshots and image assets
├── cleanup_web.php        # Admin-triggered cleanup of expired password reset tokens
├── composer.json / composer.lock
├── .gitignore
└── README.md
```

## 🚀 Installation & Setup
1. **Clone the repo**:
   ```
   git clone https://github.com/SulavAdhikari04/Sewamandu.git
   ```
2. **Place it** inside your XAMPP `htdocs` folder (or any Apache/PHP web root).
3. **Install dependencies**:
   ```
   composer install
   ```
4. **Database**:
   - Create a MySQL database named `sewamandu`.
   - Update connection settings in `components/Database.php` if your credentials differ from XAMPP defaults (`root` / no password on `127.0.0.1:3306`).
5. **Email (optional but required for OTP/reset emails)**:
   - Configure Gmail SMTP credentials in `components/EmailConfig_Gmail.php`.
6. **Start Apache and MySQL**, then visit:
   ```
   http://localhost/sewamandu/pages/home.php
   ```
## Contact
 Email: sulavadhikari69@gmail.com
 Phone: +977-9761610717
 
---
© 2025–2026 Sewamandu. All rights reserved.