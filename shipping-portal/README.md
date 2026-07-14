# Artee Fabrics & Home Shipping Portal - Installation Guide

An enterprise-grade web application built using PHP 8.3+, MySQL 8+, Bootstrap 5, and jQuery/AJAX for retail shipping request management.

---

## Directory Structure

```text
shipping-portal/
│
├── schema.sql                 # MySQL Database Schema script
├── seeder.php                 # Setup & Mock Data Seeder script
├── README.md                  # Installation & Operations Guide (This file)
│
├── src/                       # Core Server-Side Controllers & Helpers
│   ├── config.php             # System environment configurations
│   ├── db.php                 # Database PDO Connection handler
│   ├── functions.php          # Session, CSRF, Audit log, e-escaping helpers
│   ├── mail.php               # Standalone Socket SMTP Client with PHP fallback
│   ├── header.php             # Unified HTML Header and Navbar
│   └── footer.php             # Unified HTML Footer
│
├── public/                    # Public web root directory
│   ├── index.php              # Login entry point
│   ├── logout.php             # Logout handler
│   ├── dashboard.php          # Store User Dashboard
│   ├── admin_dashboard.php    # Logistics headquarters Dashboard
│   ├── request_create.php     # Store request submission form
│   ├── request_view.php       # Detail request view & Admin manager
│   ├── download_label.php     # Secure PDF download controller
│   ├── notifications.php      # Real-time AJAX notifications backend API
│   ├── notifications_center.php # Store notification center list
│   ├── css/
│   │   └── style.css          # Core CSS stylesheet (Dark Blue / White / Gold Accent)
│   └── js/
│       └── app.js             # Form validation & AJAX notification client script
│
└── secure_uploads/            # Secure directory holding PDF labels (with .htaccess deny rules)
```

---

## Prerequisites
* PHP 8.3 or higher.
* MySQL 8.0 or higher.
* Web server (Apache, Nginx, or standard cPanel/Plesk hosting setup).
* Write permissions enabled on the `/secure_uploads/` folder.

---

## Installation & Setup Instructions

### 1. Database Configuration
1. Log in to your hosting panel (e.g. cPanel) and open **MySQL Database Wizard**.
2. Create a new database named `artee_shipping` (or a custom name).
3. Create a database user and assign it to the database with **All Privileges**.
4. Keep the database name, username, and password ready.

### 2. Configure the Portal
Edit the configuration settings in `src/config.php`:
* **DB Connection**: Update the constants `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.
* **SMTP Settings**: Update `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, and `SMTP_PASS` to configure email routing.
* **APP URL**: Update `APP_URL` to match your deployment directory URL (e.g. `https://yourdomain.com/shipping-portal/public`).

### 3. Run the Database Seeder
1. Upload the project folder to your web server (e.g., `public_html/shipping-portal/`).
2. Open your web browser and navigate to:
   `http://yourdomain.com/shipping-portal/seeder.php`
3. This script will automatically create the database structure, seed 20 retail store addresses across the US, insert default system configurations, and setup the secure uploads directory protected with `.htaccess` rules.
4. **Security Notice**: Once seeded, please delete `seeder.php` from your web server to prevent unauthorized database resets.

---

## Default User Accounts

After running the seeder, the following login credentials will be active:

* **Super Admin**: 
  * Username: `admin`
  * Password: `admin123`
* **Logistics Admin**: 
  * Username: `logistics`
  * Password: `logistics123`
* **Store Users (20 Stores)**:
  * Username format: `store_[code]` (e.g. `store_atl` for Atlanta, `store_bos` for Boston, `store_chi` for Chicago, etc.)
  * Password: `store123`

---

## Security Policies & Best Practices

1. **Secure PDF Storage**: Label PDFs are uploaded directly to the `/secure_uploads/` folder. Access is restricted using a local `.htaccess` directive (`Deny from all`). Files are retrieved only by authenticated users via `download_label.php`, preventing direct URL access.
2. **Prepared Statements**: All database operations are completed using PDO prepared statements to completely eliminate SQL injection vectors.
3. **Cross-Site Scripting (XSS)**: User data is output using the `e()` helper function which translates special characters into HTML entities.
4. **CSRF Tokens**: Form submissions include a unique session-bound token (`csrf_token`) verified server-side on submission.
5. **Remember Me**: Persistent login state is secured via HTTPOnly and SameSite browser cookies.
6. **Freight Charge Business Rule**: Every request validation forces minimum customer charges of **$15.00** at the javascript UI level and double-checked at the backend PHP insertion level.
