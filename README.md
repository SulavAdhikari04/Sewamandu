# Sewamandu - Home Services at Your Doorstep

Sewamandu is a web-based platform designed to connect residents of Kathmandu, Lalitpur, and Bhaktapur with reliable, verified home service professionals. From plumbing to electrical work, Sewamandu simplifies household maintenance with a few simple clicks.

## 🌟 Major Features

### 1. Multi-Role Dashboard System
Sewamandu offers specialized interfaces for three distinct user roles:
- **Customers**: Easily browse, book, and track services.
- **Service Providers**: Manage service offerings, track earnings, and receive bookings.
- **Admin**: Oversee the entire ecosystem, manage users, and approve/suspend providers.

### 2. Comprehensive Service Catalog
A wide range of home services are integrated, including:
- 🛠️ Plumbing & Electrical
- 🧼 Cleaning & Housekeeping
- 🪚 Carpentry & Home Renovation
- ❄️ AC Servicing & Appliance Repair
- 📦 Packers & Movers

### 3. Provider Verification & Safety
To ensure high-quality service and customer safety, all providers must upload verification documents during registration. Admin review and approval are required before a provider can begin accepting jobs.

### 4. Real-time Status Tracking
Customers can monitor their service requests from "Pending" to "In Progress" and finally "Completed," ensuring transparency throughout the process.

### 5. Email Notification System
Integrated with Gmail SMTP (via PHPMailer) for automatic account alerts, welcome messages, and status updates.

---

## 🔄 Core Workflows

### 📍 Customer Workflow
1. **Registration/Login**: Create a customer account.
2. **Explore Services**: Browse the catalog from the home page or dashboard.
3. **Booking**: Select a service, pick a date, and provide details.
4. **Management**: View and track your booking status directly from your dashboard.

### 📍 Service Provider Workflow
1. **Registration**: Select "Service Provider" role and **attach verification documents**.
2. **Approval**: Your account will be reviewed by the Sewamandu Admin.
3. **Availability**: Once "Active," manage your service list and readiness.
4. **Operations**: Manage incoming bookings and update service status for customers.

### 📍 Admin Workflow
1. **Dashboard Monitoring**: View platform-wide stats (total users, providers, bookings).
2. **User Management**: Search, edit, or delete user accounts.
3. **Provider Approval**: Review uploaded documents and activate/suspend provider accounts.

---

## 🏗️ Project Structure
```
sewamandu/
│
├── components/           # Reusable PHP components (Header, Footer, Sidebars, Database)
├── pages/                # Main application pages (home, login, dashboards, etc.)
├── css/                  # Custom CSS for all sections
├── images/               # Image assets
├── artifacts/            # Generated files and screenshots
├── vendor/               # PHPMailer and other dependencies
├── .gitignore
└── README.md
```

## 🚀 Installation & Setup
1. **Clone the Repo**: `git clone https://github.com/SulavAdhikari04/sewamandu.git`
2. **Environment**: Place in XAMPP `htdocs` or your web server root.
3. **Database**: 
   - Rename your MySQL database to `sewamandu`.
   - Update credentials in `components/Database.php`.
4. **Start**: Launch Apache and MySQL, then visit `http://localhost/sewamandu/pages/home.php`.

---
© 2025 Sewamandu. All rights reserved.
