<div align="center">

<img src="assets/uploads/logos/logo_6a6449d0143b7.png" alt="TamizhMart Logo" width="96" height="96" style="border-radius:20px"/>

# TamizhMart 🛍️

**White-Label Multi-Tenant E-Commerce Platform**

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![PWA Ready](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=flat-square&logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps/)
[![Razorpay](https://img.shields.io/badge/Razorpay-Integrated-02042B?style=flat-square&logo=razorpay&logoColor=white)](https://razorpay.com)
[![Google OAuth](https://img.shields.io/badge/Google-OAuth-4285F4?style=flat-square&logo=google&logoColor=white)](https://developers.google.com/identity)
[![License](https://img.shields.io/badge/License-Source_Available-red?style=flat-square)](#-license--usage)
[![Made in](https://img.shields.io/badge/Made_in-Tamil_Nadu_🇮🇳-orange?style=flat-square)](https://github.com/sivabalaji-tn)

<br/>

> *One codebase. Infinite shops. Zero frameworks. Pure PHP.*

**Built by [@sivabalaji-tn](https://github.com/sivabalaji-tn) — Tamil Nadu, India 🇮🇳**
*Powered by coffee, curiosity, and very late nights ☕🌙*

---

[Features](#-features) · [Tech Stack](#-tech-stack) · [Installation](#-installation) · [Project Structure](#-project-structure) · [License](#-license--usage)

</div>

---

## 📖 What is TamizhMart?

TamizhMart is a **complete, production-ready, white-label e-commerce platform** where each shop owner gets their own fully branded storefront — custom colors, fonts, logo, products, orders — all powered by a single codebase.

Think of it as *Shopify built from scratch*, but with zero monthly fees, zero npm installs, and all the code is yours.

```
One platform → Multiple shops → Each shop: fully custom branded
```

---

## ⚠️ License & Usage

This project is **source-available** — you can read it, learn from it, run it locally, and build on top of it.

| | Action |
|---|---|
| ✅ | Fork for personal/learning use |
| ✅ | Study and learn from the code |
| ✅ | Contribute improvements via PR |
| ❌ | Redistribute or resell as your own |
| ❌ | Use commercially without permission |
| ❌ | Remove README or author credits |

> 📩 For licensing, permissions, or collaborations — contact **Sivabalaji** first. He will know. 👀

*© 2025–2026 Sivabalaji. All rights reserved.*

---

## ✨ Features

### 🛒 Customer Storefront

Everything a customer needs for a great shopping experience — per shop, fully themed.

| Feature | Details |
|---|---|
| **Dynamic Home Page** | Hero banner, promo strip, category grid, featured products, sale highlights |
| **Product Listing** | Multi-filter by category, price range slider, sort by price/newest/popular, pagination |
| **Product Detail** | Full image gallery, quantity selector, stock status, related products |
| **Real-time Cart** | AJAX-powered live cart updates, quantity changes, line totals, persistent sessions |
| **Checkout** | Delivery address form, order notes, COD + **Razorpay online payment**, animated success screen |
| **Order Receipt** | Downloadable order receipt page per order |
| **Order History** | Accordion-style order cards with live animated status timeline |
| **Customer Profile** | Edit name, phone, address, change password, order stats |
| **Product Search** | Real-time search across products within a shop |
| **Promo Popups** | Timed promotional popups with image, CTA button, and scheduling |

### 🔐 Authentication

| Feature | Details |
|---|---|
| **Customer Login** | Per-shop branded login with custom colors and logo |
| **Customer Register** | Per-shop registration with validation |
| **Google OAuth** | One-click Sign In / Register via Google Account |
| **Forgot Password** | 6-digit OTP sent via email → OTP verification page → New password form |
| **OTP Email** | Branded HTML email with shop name, 6-digit code (10-min expiry, single-use) |
| **Password Reset** | Live strength indicator, show/hide toggle, confirm match validation |
| **Shop Owner Login** | Separate owner authentication portal |
| **Session Security** | `SameSite=Lax` cookies, session binding per shop, secure redirects |

### 🧑‍💼 Shop Owner Dashboard

A full-featured business management dashboard for each shop owner.

| Feature | Details |
|---|---|
| **Analytics** | Revenue charts (daily/weekly/monthly), order trends, top products, customer growth stats (Chart.js) |
| **Orders** | View, filter by status/date, update order status with one click, print orders |
| **Order Export** | Export orders to CSV with filters |
| **Invoice PDF** | Generate and download printable PDF invoice per order |
| **Products** | Add/edit/delete products with images or URL, pricing, stock, discounts |
| **Bulk Upload** | Upload multiple products at once via CSV |
| **Product Sorting** | Drag-and-drop product sort order |
| **Categories** | Create/edit categories with optional banner images |
| **Popups & Offers** | Schedule promotional popups with image, CTA, start/end dates, active toggle |
| **Store Settings** | Shop name, slug, description, logo, banner, city, announcement bar, phone, address |
| **Theme Customizer** | Live-preview color picker + 8 Google Fonts + 8 quick theme presets |
| **Social Links** | WhatsApp, Instagram, Facebook, X (Twitter), YouTube, Website |
| **Customer List** | View all registered customers for the shop |
| **5-Step Setup Wizard** | Guided onboarding — name → logo → theme → first product → launch |
| **Transactional Emails** | Order confirmation emails with branded HTML template via PHPMailer + Gmail SMTP |

### 👑 Super Admin Command Center

A dedicated super-admin panel to manage the entire platform.

| Feature | Details |
|---|---|
| **Command Console** | Platform-wide live stats — total shops, orders, revenue, active users |
| **All Merchant Shops** | View, search, suspend, activate, and manage every shop |
| **Shop Owners** | Full owner account management |
| **Customer Base** | Platform-wide customer overview |
| **Global Transactions** | View all orders across all shops with filters |
| **Tier Plans** | Create and manage subscription plans with price, duration, limits, commission % |
| **Subscriptions & Billing** | Assign plans to shops, track trial/active/grace/suspended status, renew, extend, suspend/restore |
| **Commission Tracking** | Per-subscription-period commission — resets correctly on renewal |
| **Collect Commission** | One-click settlement with note, logged to permanent audit trail |
| **Audit & Commission Logs** | 3-tab audit trail — Subscription History · Commission Collections · Per-Order Log |
| **Global Settings** | Platform name, city, tagline, maintenance mode toggle |
| **Maintenance Mode** | One-click platform lockdown banner across all storefronts |

### 📱 Progressive Web App (PWA)

| Feature | Status |
|---|---|
| Dynamic Web App Manifest (per shop, themed) | ✅ |
| Service Worker offline caching | ✅ |
| Offline fallback page | ✅ |
| Smart install banner (auto-shown after 4s) | ✅ |
| iOS Apple Web App meta tags | ✅ |
| Background Sync scaffold | 🔧 Ready |
| Push Notifications scaffold | 🔧 Ready |

### 💳 Payments

| Gateway | Status |
|---|---|
| **Cash on Delivery (COD)** | ✅ Built-in |
| **Razorpay Online Payment** | ✅ Integrated |

> ⚠️ Razorpay requires HTTPS in production. Use COD on localhost for testing.

---

## 🏗️ Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.x — procedural, no frameworks, no drama |
| **Database** | MySQL 8.0 with prepared statements throughout |
| **Frontend** | Bootstrap 5.3 + Bootstrap Icons + Vanilla CSS variables |
| **Charts** | Chart.js 4.x (owner analytics) |
| **Fonts** | Google Fonts — dynamic per shop theme |
| **Email** | PHPMailer 6.x + Gmail SMTP |
| **OAuth** | Google Identity OAuth 2.0 |
| **Payments** | Razorpay PHP SDK |
| **PWA** | Service Worker + Web App Manifest |
| **Dev Environment** | Docker (PHP 8.x + MySQL 8 + phpMyAdmin) |

> No Node.js. No `node_modules` folder heavier than your soul. 🙏

---

## 📁 Project Structure

```
tamizhmart/
│
├── index.php                    # Platform landing — shop directory + slug routing
├── .htaccess                    # Apache security, caching, HTTPS redirect, URL rewriting
├── mainfest.php                 # Dynamic PWA manifest (per-shop branded)
├── sw.js                        # Service Worker (offline cache)
├── offline.php                  # Offline fallback page
├── 404.php                      # Custom 404 error page
├── tamizhmart_db.sql            # ← Run this first. Always first.
│
├── config/
│   └── db.php                   # DB connection + session config (auto-detects local vs prod)
│
├── auth/                        # Customer authentication
│   ├── login.php                # Per-shop themed login + Google OAuth
│   ├── register.php             # Customer sign up (per shop)
│   ├── logout.php
│   ├── forgot_password.php      # Email entry → OTP via PHPMailer
│   ├── verify_otp.php           # 6-box OTP entry, auto-advance, countdown
│   ├── reset_password.php       # New password + strength bar
│   ├── google_oauth_init.php    # OAuth URL generator
│   └── google_callback.php      # OAuth callback handler
│
├── owner/                       # Shop owner dashboard
│   ├── login.php / register.php / logout.php
│   ├── setup.php                # 5-step onboarding wizard
│   ├── dashboard.php            # Analytics + revenue charts
│   ├── orders.php               # Order management
│   ├── export_orders.php        # CSV export
│   ├── invoice_pdf.php          # PDF invoice generator
│   ├── products.php             # Product CRUD
│   ├── bulk_upload.php          # CSV bulk product import
│   ├── sort_products.php        # Drag-and-drop sort
│   ├── categories.php           # Category management
│   ├── popups.php               # Promo popup scheduler
│   ├── settings.php             # Shop settings
│   ├── theme.php                # Live theme customizer
│   ├── social.php               # Social links
│   ├── analytics.php            # Detailed analytics
│   └── customers.php            # Customer list
│
├── shop/                        # Customer-facing storefront
│   ├── index.php                # Home — hero, categories, products
│   ├── products.php             # Product listing + filters
│   ├── product.php              # Product detail
│   ├── cart.php                 # Cart page
│   ├── cart_action.php          # AJAX cart handler
│   ├── checkout.php             # Checkout + payment
│   ├── razorpay_create_order.php / razorpay_verify.php
│   ├── reciept.php              # Order receipt
│   ├── orders.php               # Order history
│   └── profile.php              # Customer profile
│
├── superadmin/                  # Platform super admin
│   ├── dashboard.php            # Command console
│   ├── shops.php                # All shops
│   ├── owners.php / customers.php / orders.php
│   ├── plans.php                # Subscription tiers
│   ├── subscriptions.php        # Billing + commission collect
│   ├── commission_logs.php      # Full audit trail
│   └── settings.php             # Platform settings
│
└── assets/
    ├── js/pwa.js                # SW registration + install prompt
    ├── icons/                   # PWA icons (72–512px)
    └── uploads/
        ├── logos/
        ├── banners/
        ├── products/
        └── popups/
```

---

## 🗄️ Database Tables

| Table | Purpose |
|---|---|
| `owners` | Shop owner accounts |
| `shops` | Shop config — slug, theme, logo, banner, city |
| `shop_settings` | Key-value — phone, social links, setup status |
| `users` | Customer accounts (per `shop_id`) |
| `categories` | Product categories |
| `products` | Products with pricing, stock, discount |
| `cart` | Active cart (session + user bound) |
| `orders` | Placed orders with status, total |
| `order_items` | Line items per order |
| `popups` | Promotional popup scheduler |
| `plans` | Subscription tier definitions |
| `shop_subscriptions` | Per-shop billing history (trial/active/grace/suspended/completed) |
| `commission_log` | Per-order platform commission (collected/pending) |
| `commission_collections` | Commission settlement audit log |
| `password_resets` | OTP codes for password reset (10-min expiry) |
| `platform_settings` | Global config (name, city, maintenance mode) |
| `super_admins` | Super admin accounts |

---

## 🚀 Installation

### Option A — Docker (Recommended)

```bash
git clone https://github.com/sivabalaji-tn/tamizhmart.git
cd tamizhmart
docker-compose up -d
```

- App: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081` (root / root)

Import `tamizhmart_db.sql` via phpMyAdmin → Done. 🎉

---

### Option B — XAMPP / Local PHP

**Step 1** — Place files in `C:/xampp/htdocs/tamizhmart/`

**Step 2** — Create database `tamizhmart_db` → import `tamizhmart_db.sql`

**Step 3** — Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tamizhmart_db');
```

**Step 4** — Create upload folders:
```bash
mkdir -p assets/uploads/{logos,banners,products,popups}
mkdir -p assets/icons
```

**Step 5** — Open `http://localhost/tamizhmart/` 🚀

---

## 🔑 First-Time Setup

1. Visit the landing page → **Register Your Shop**
2. Login → Auto-redirected to the **5-Step Setup Wizard**

| Step | Action |
|---|---|
| 1 | Shop name, slug, description |
| 2 | Upload logo |
| 3 | Pick theme colors + font |
| 4 | Add first category + product |
| 5 | Announcement bar + phone → Launch! |

Shop goes live at: `http://localhost:8080/?shop=YOUR_SLUG`

**Super Admin:** `http://localhost:8080/superadmin/login.php`

---

## 🎨 Dynamic Theme System

Every shop has its own theme in the database. The owner picks colors from a live preview picker — the entire storefront updates instantly. No redeploy. No cache clear.

```css
:root {
  --primary:   /* owner's brand color */;
  --secondary: /* accent */;
  --bg:        /* background */;
  --font:      /* Google Font */;
}
```

**8 Quick Presets:** `Gold & Dark` · `Ocean Blue` · `Forest Green` · `Sunset` · `Rose Gold` · `Purple Night` · `Charcoal` · `Classic Red`

---

## 🔒 Security

| Layer | Implementation |
|---|---|
| SQL Injection | Prepared statements everywhere |
| Passwords | bcrypt via `password_hash()` |
| Sessions | `SameSite=Lax`, shop-scoped binding |
| File Uploads | Extension whitelist + MIME check + size limit |
| OTP | `hash_equals()` timing-safe, 10-min expiry, single-use |
| .htaccess | Blocks config/sql/env direct access |
| Google OAuth | State parameter validation, server-side token exchange |

---

## 💰 Subscription & Commission System

```
Shop Owner → pays platform subscription → gets plan with commission %
Every completed order → logs commission_amount to commission_log
Per subscription period → commission tracked in isolation
Admin collects → settlement logged in commission_collections
```

**3-tab Audit Log:**
- 📋 **Subscription History** — all activations, renewals, completed periods
- 💵 **Commission Collections** — every settlement with amount, note, who collected
- 🧾 **Per-Order Commission** — full breakdown per order (pending vs collected)

---

## 📧 Email System

Powered by **PHPMailer + Gmail SMTP**:

| Email | Trigger |
|---|---|
| Order Confirmation | Customer places order |
| Welcome Email | New shop owner registers |
| OTP Password Reset | Customer requests forgot password |

```php
// In shop/includes/notifications.php
$mail->Username = 'your@gmail.com';
$mail->Password = 'xxxx xxxx xxxx xxxx'; // Gmail App Password
```

> Generate App Password: Google Account → Security → 2FA → App Passwords

---

## 🐛 Troubleshooting

| Problem | Fix |
|---|---|
| Blank page | Check PHP error log. Enable `display_errors` temporarily |
| "Headers already sent" | BOM in PHP file — open in hex editor, first 3 bytes must be `3C 3F 70` |
| Images not uploading | `chmod 755 assets/uploads/*` |
| Can't login | Clear browser cookies. Session mismatch. |
| DB connection failed | Use `localhost` not `localhost:3306` in DB_HOST |
| Google OAuth broken | Verify redirect URI in Google Console matches domain exactly |
| OTP email not sending | Regenerate Gmail App Password |
| Razorpay not working | Needs HTTPS — use COD on localhost |

---

## 🚢 Production Deployment

1. Upload via FTP / Git to server
2. Create MySQL DB → import `tamizhmart_db.sql`
3. Update `config/db.php` with production credentials
4. Enable HTTPS (Let's Encrypt via cPanel — free)
5. Ensure `RewriteBase /` in `.htaccess`
6. `chmod 755 assets/uploads/*`
7. Set Gmail App Password in `shop/includes/notifications.php` + `auth/forgot_password.php`
8. Add Razorpay live keys in checkout files

---

## ⭐ Support the Project

If TamizhMart saved you time, money, or sanity:
- Drop a **⭐ on GitHub**
- Open an **Issue** if you find a bug
- Contact Sivabalaji before using commercially


---

<div align="center">

**Built with ❤️ in Tamil Nadu, India**

*TamizhMart v2.0 — August 2026*

[![GitHub](https://img.shields.io/badge/GitHub-sivabalaji--tn-181717?style=flat-square&logo=github)](https://github.com/sivabalaji-tn)

*© 2025–2026 Sivabalaji. All rights reserved.*

</div>
