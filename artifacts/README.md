# Sewamandu

Sewamandu is a web-based platform for booking reliable home services in Kathmandu, Lalitpur, and Bhaktapur. It connects customers with verified service providers for a variety of household needs, including plumbing, electrical work, cleaning, carpentry, appliance repair, and more.

## Features
- Book home services online with ease
- Services available: Plumbing, Electrical, Cleaning, Carpentry, Housekeeping, Appliance Repair, AC Servicing, Computer Support, Packers & Movers, Home Renovation
- Separate dashboards for Admin, Customers, and Service Providers
- User registration and login system
- Real-time booking management and status tracking
- Provider verification and transparent pricing
- 24/7 support and emergency services
- Customer reviews and ratings

## Component-Based Project Structure
```
sewamandu/
│
├── components/           # Reusable PHP components (header, footer, sidebars, alerts)
│   ├── BookingStatus.php
│   ├── Header.php
│   ├── Database.php
│   ├── EmailConfig.php
│   ├── Logout.php
│   └── SessionManager.php
│
├── pages/                # Main pages (each as a component)
│   ├── home.php
│   ├── login.php
│   ├── register.php
│   ├── customer-home.php
│   ├── customer-dashboard.php
│   ├── provider-dashboard.php
│   ├── admin-dashboard.php
│   └── book-service.php
│
├── css/                  # All CSS files
│   └── ...
│
├── images/               # All images
│   └── ...
│
├── artifacts/            # (If needed, for generated files or reports)
│
├── .gitignore
└── README.md
```

### Components
- **Header.php**: Site header, navigation, and HTML head section.
- **Footer.php**: Site footer and closing tags.
- **SidebarCustomer.php**: Sidebar for customer dashboard.
- **SidebarProvider.php**: Sidebar for provider dashboard.
- **SidebarAdmin.php**: Sidebar for admin dashboard.
- **Alert.php**: For displaying alert messages.

### Usage Example
To use a component in a page:
```php
<?php include '../components/Header.php'; ?>
<!-- Page content here -->
<?php include '../components/Footer.php'; ?>
```

## Installation
1. Clone the repository:
   ```bash
   git clone https://github.com/SulavAdhikari04/Sewamandu.git
   ```
2. Place the project in your XAMPP `htdocs` directory (or your web server root).
3. Import the required MySQL database (see `register.php` and other PHP files for table structure).
4. Update database credentials in PHP files if needed.
5. Start Apache and MySQL from XAMPP.
6. Access the app at `http://localhost/sewamandu/pages/home.php`.

## Contact
- Email: support@sewamandu.com
- Phone: +977-9800000000

---
© 2025 Sewamandu. All rights reserved. 