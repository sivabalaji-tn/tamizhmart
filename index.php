<?php
/**
 * TamizhMart — Platform Landing Page
 * Built by Sivabalaji | SM Tech
 */
session_start();
require 'config/db.php';

// ── Direct shop slug access ───────────────────────────────────
$slug = $_GET['shop'] ?? null;
if ($slug) {
    $st = $conn->prepare("SELECT slug FROM shops WHERE slug=? AND is_active=1 LIMIT 1");
    $st->bind_param('s', $slug);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if ($row) {
        $_SESSION['current_shop_slug'] = $row['slug'];
        header("Location: shop/index.php?shop=" . urlencode($row['slug']));
        exit;
    }
}

// ── Search & fetch shops ──────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$city   = trim($_GET['city'] ?? '');

$where  = "s.is_active = 1 AND s.is_suspended = 0";
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= " AND (s.name LIKE ? OR s.description LIKE ? OR s.city LIKE ?)";
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
if ($city !== '') {
    $where   .= " AND s.city = ?";
    $params[] = $city;
    $types   .= 's';
}

$sql = "SELECT s.id, s.name, s.slug, s.description, s.city, s.state,
               ss.setting_value AS logo
        FROM shops s
        LEFT JOIN shop_settings ss ON ss.shop_id = s.id AND ss.setting_key = 'logo'
        WHERE $where
        ORDER BY s.created_at DESC";

if ($types) {
    $st2 = $conn->prepare($sql);
    $st2->bind_param($types, ...$params);
    $st2->execute();
    $shops_result = $st2->get_result();
} else {
    $shops_result = $conn->query($sql);
}
$shops = $shops_result->fetch_all(MYSQLI_ASSOC);

// ── Fetch distinct cities for filter ─────────────────────────
$cities_q = $conn->query("SELECT DISTINCT city FROM shops WHERE is_active=1 AND is_suspended=0 AND city IS NOT NULL AND city != '' ORDER BY city");
$cities = [];
while ($r = $cities_q->fetch_row()) $cities[] = $r[0];

// ── Platform stats ────────────────────────────────────────────
$total_shops    = $conn->query("SELECT COUNT(*) FROM shops WHERE is_active=1")->fetch_row()[0];
$total_products = $conn->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetch_row()[0];
$total_orders   = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TamizhMart — Tamil Nadu's Local Shopping Platform</title>
<meta name="description" content="Discover and shop from local Tamil Nadu stores online. Fresh groceries, medicines, sweets and more.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ── Reset ───────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
/* Smooth tap on mobile */
a, button { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
:root {
    --gold:   #c8a97e;
    --dark:   #1a1208;
    --light:  #faf7f2;
    --muted:  rgba(26,18,8,0.5);
    --border: rgba(26,18,8,0.1);
    --radius: 16px;
    --radius-sm: 10px;
}
html { scroll-behavior: smooth; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--light);
    color: var(--dark);
    min-height: 100vh;
}
a { text-decoration: none; color: inherit; }
img { display: block; }

/* ── Navbar ──────────────────────────────── */
.navbar {
    position: sticky; top: 0; z-index: 100;
    background: rgba(250,247,242,0.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    display: flex; align-items: center;
    justify-content: space-between;
    height: 64px;
}
.nav-brand {
    font-family: 'Syne', sans-serif;
    font-weight: 900; font-size: 20px;
    color: var(--dark);
    display: flex; align-items: center; gap: 8px;
}
.nav-brand span { color: var(--gold); }
.nav-links { display: flex; align-items: center; gap: 8px; }
.nav-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 99px;
    font-size: 13.5px; font-weight: 600;
    transition: all 0.2s; cursor: pointer; border: none;
}
.nav-btn-ghost {
    background: transparent; color: var(--dark);
    border: 1.5px solid var(--border);
}
.nav-btn-ghost:hover { background: var(--dark); color: var(--light); border-color: var(--dark); }
.nav-btn-gold {
    background: var(--dark); color: var(--gold);
}
.nav-btn-gold:hover { background: var(--gold); color: var(--dark); }

/* ── Hero ────────────────────────────────── */
.hero {
    background: var(--dark);
    padding: 80px 24px 100px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(200,169,126,0.18) 0%, transparent 70%);
    pointer-events: none;
}
.hero-tag {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(200,169,126,0.15);
    border: 1px solid rgba(200,169,126,0.3);
    color: var(--gold);
    padding: 5px 14px; border-radius: 99px;
    font-size: 12.5px; font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 24px;
    animation: fadeUp 0.6s ease both;
}
.hero h1 {
    font-family: 'Syne', sans-serif;
    font-weight: 900; font-size: clamp(32px, 6vw, 60px);
    line-height: 1.08; letter-spacing: -1.5px;
    color: #fff;
    margin-bottom: 20px;
    animation: fadeUp 0.6s 0.1s ease both;
}
.hero h1 span { color: var(--gold); }
.hero p {
    font-size: clamp(14px, 2vw, 17px);
    color: rgba(255,255,255,0.55);
    max-width: 520px; margin: 0 auto 40px;
    line-height: 1.7;
    animation: fadeUp 0.6s 0.2s ease both;
}

/* ── Search bar ──────────────────────────── */
.search-wrap {
    max-width: 580px; margin: 0 auto;
    animation: fadeUp 0.6s 0.3s ease both;
}
.search-bar {
    display: flex; align-items: center;
    background: #fff;
    border-radius: 99px;
    padding: 6px 6px 6px 22px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
.search-bar input {
    flex: 1; border: none; outline: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px; background: transparent;
    color: var(--dark);
}
.search-bar input::placeholder { color: rgba(26,18,8,0.35); }
.search-bar button {
    background: var(--dark); color: var(--gold);
    border: none; border-radius: 99px;
    padding: 10px 22px; font-size: 14px;
    font-weight: 700; cursor: pointer;
    display: flex; align-items: center; gap: 6px;
    transition: all 0.2s; white-space: nowrap;
}
.search-bar button:hover { background: var(--gold); color: var(--dark); }

/* ── Stats ───────────────────────────────── */
.stats-bar {
    display: flex; justify-content: center;
    gap: 0;
    background: rgba(200,169,126,0.08);
    border-top: 1px solid rgba(200,169,126,0.15);
    border-bottom: 1px solid rgba(200,169,126,0.15);
    margin-top: 56px;
}
.stat-item {
    flex: 1; max-width: 200px;
    text-align: center;
    padding: 20px 16px;
    border-right: 1px solid rgba(200,169,126,0.15);
    animation: fadeUp 0.6s 0.4s ease both;
}
.stat-item:last-child { border-right: none; }
.stat-num {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: 28px;
    color: var(--gold);
}
.stat-label {
    font-size: 12px; color: rgba(255,255,255,0.4);
    margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px;
}

/* ── Section ─────────────────────────────── */
.section { padding: 64px 24px; max-width: 1200px; margin: 0 auto; }
.section-title {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: 26px;
    letter-spacing: -0.5px; margin-bottom: 6px;
}
.section-sub { font-size: 14px; color: var(--muted); margin-bottom: 32px; }

/* ── City filter ─────────────────────────── */
.city-filters {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-bottom: 32px;
}
.city-chip {
    padding: 6px 16px; border-radius: 99px;
    font-size: 13px; font-weight: 500;
    border: 1.5px solid var(--border);
    background: #fff; color: var(--dark);
    cursor: pointer; transition: all 0.15s;
    text-decoration: none; display: inline-block;
}
.city-chip:hover, .city-chip.active {
    background: var(--dark); color: var(--gold);
    border-color: var(--dark);
}

/* ── Shop grid ───────────────────────────── */
.shops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.shop-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none; color: inherit;
    display: flex; flex-direction: column;
}
.shop-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(26,18,8,0.1);
}
.shop-card-banner {
    height: 110px;
    background: linear-gradient(135deg, var(--dark), #2d1f0a);
    position: relative;
    display: flex; align-items: center; justify-content: center;
}
.shop-logo-wrap {
    position: absolute; bottom: -24px; left: 20px;
    width: 52px; height: 52px; border-radius: 12px;
    background: #fff; border: 2px solid var(--border);
    overflow: hidden; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.shop-logo-wrap img { width: 100%; height: 100%; object-fit: cover; }
.shop-logo-placeholder {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: 18px;
    color: var(--dark);
}
.shop-card-body {
    padding: 32px 20px 20px;
    flex: 1; display: flex; flex-direction: column;
}
.shop-name {
    font-family: 'Syne', sans-serif;
    font-weight: 800; font-size: 16px;
    margin-bottom: 4px;
}
.shop-desc {
    font-size: 13px; color: var(--muted);
    line-height: 1.5; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
    margin-bottom: 14px;
}
.shop-meta {
    display: flex; align-items: center;
    justify-content: space-between;
}
.shop-city {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12px; font-weight: 600;
    background: rgba(200,169,126,0.12);
    color: #8b6428; padding: 3px 10px;
    border-radius: 99px;
}
.shop-visit {
    display: flex; align-items: center; gap: 4px;
    font-size: 12.5px; font-weight: 700;
    color: var(--dark);
    padding: 6px 14px; border-radius: 99px;
    background: var(--light); border: 1.5px solid var(--border);
    transition: all 0.15s;
}
.shop-card:hover .shop-visit {
    background: var(--dark); color: var(--gold);
    border-color: var(--dark);
}

/* ── Marquee ─────────────────────────────── */
.marquee-wrap {
    background: var(--dark);
    padding: 14px 0; overflow: hidden;
    border-top: 1px solid rgba(200,169,126,0.15);
    border-bottom: 1px solid rgba(200,169,126,0.15);
}
.marquee-track {
    display: flex; gap: 48px;
    animation: marquee 20s linear infinite;
    white-space: nowrap; width: max-content;
}
.marquee-item {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600;
    color: rgba(255,255,255,0.5);
}
.marquee-item i { color: var(--gold); }
@keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }

/* ── Features ────────────────────────────── */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}
.feature-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 28px 24px;
    transition: transform 0.2s;
}
.feature-card:hover { transform: translateY(-3px); }
.feature-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: rgba(200,169,126,0.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: var(--gold);
    margin-bottom: 16px;
}
.feature-title {
    font-family: 'Syne', sans-serif;
    font-weight: 700; font-size: 15px;
    margin-bottom: 6px;
}
.feature-desc { font-size: 13px; color: var(--muted); line-height: 1.6; }

/* ── Empty state ─────────────────────────── */
.empty-state {
    text-align: center; padding: 60px 24px;
    grid-column: 1/-1;
}
.empty-state i { font-size: 48px; color: var(--gold); opacity: 0.5; margin-bottom: 16px; }
.empty-state h3 { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 18px; margin-bottom: 8px; }
.empty-state p { font-size: 14px; color: var(--muted); }

/* ── CTA ─────────────────────────────────── */
.cta-section {
    background: var(--dark);
    padding: 72px 24px;
    text-align: center;
    position: relative; overflow: hidden;
}
.cta-section::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 50% 100%, rgba(200,169,126,0.12) 0%, transparent 70%);
}
.cta-section h2 {
    font-family: 'Syne', sans-serif;
    font-weight: 900; font-size: clamp(24px, 4vw, 40px);
    color: #fff; letter-spacing: -0.8px; margin-bottom: 12px;
}
.cta-section h2 span { color: var(--gold); }
.cta-section p { color: rgba(255,255,255,0.45); font-size: 15px; margin-bottom: 32px; }
.cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.btn-gold {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--gold); color: var(--dark);
    padding: 13px 28px; border-radius: 99px;
    font-weight: 700; font-size: 14.5px;
    transition: all 0.2s;
}
.btn-gold:hover { background: #fff; }
.btn-outline {
    display: inline-flex; align-items: center; gap: 8px;
    background: transparent; color: rgba(255,255,255,0.7);
    padding: 13px 28px; border-radius: 99px;
    font-weight: 600; font-size: 14.5px;
    border: 1.5px solid rgba(255,255,255,0.2);
    transition: all 0.2s;
}
.btn-outline:hover { border-color: var(--gold); color: var(--gold); }

/* ── Footer ──────────────────────────────── */
footer {
    background: #0f0a04;
    padding: 40px 24px 24px;
    color: rgba(255,255,255,0.35);
}
.footer-top {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 16px;
    padding-bottom: 24px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    margin-bottom: 20px;
}
.footer-brand {
    font-family: 'Syne', sans-serif;
    font-weight: 900; font-size: 18px;
    color: var(--gold);
}
.footer-links { display: flex; gap: 20px; flex-wrap: wrap; }
.footer-links a {
    font-size: 13px; color: rgba(255,255,255,0.35);
    transition: color 0.15s;
}
.footer-links a:hover { color: var(--gold); }
.footer-bottom {
    text-align: center; font-size: 13px;
    display: flex; align-items: center; justify-content: center;
    gap: 6px; flex-wrap: wrap;
}
.footer-bottom .heart { color: #e74c3c; }

/* ── Animations ──────────────────────────── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ──────────────────────────── */
/* ══ MOBILE OPTIMIZATION ══════════════════ */

/* ── Tablet (max 900px) ─────────────────── */
@media (max-width: 900px) {
    .shops-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px;
    }
    .features-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }
    .hero { padding: 56px 20px 80px; }
    .section { padding: 48px 20px; }
}

/* ── Mobile (max 600px) ─────────────────── */
@media (max-width: 600px) {

    /* Navbar */
    .navbar { padding: 0 16px; height: 58px; }
    .nav-brand { font-size: 17px; }
    .nav-links .nav-btn-ghost { display: none; }
    .nav-btn-gold { padding: 7px 13px; font-size: 12.5px; }

    /* Hero */
    .hero { padding: 44px 16px 0; }
    .hero-tag { font-size: 11.5px; padding: 4px 12px; margin-bottom: 18px; }
    .hero p { font-size: 14px; margin-bottom: 28px; }

    /* Search */
    .search-bar { padding: 5px 5px 5px 16px; }
    .search-bar input { font-size: 14px; }
    .search-bar button { padding: 9px 16px; font-size: 13px; }
    .search-bar button i { display: none; }

    /* Stats */
    .stats-bar {
        display: grid;
        grid-template-columns: 1fr 1fr;
        margin-top: 36px;
    }
    .stat-item {
        border-right: 1px solid rgba(200,169,126,0.15);
        border-bottom: 1px solid rgba(200,169,126,0.15);
    }
    .stat-item:nth-child(2) { border-right: none; }
    .stat-item:nth-child(3) { border-bottom: none; }
    .stat-item:nth-child(4) { border-right: none; border-bottom: none; }
    .stat-num { font-size: 22px; }

    /* Section */
    .section { padding: 36px 16px; }
    .section-title { font-size: 22px; }

    /* City chips — horizontal scroll */
    .city-filters {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 4px;
        margin-bottom: 20px;
    }
    .city-filters::-webkit-scrollbar { display: none; }
    .city-chip { flex-shrink: 0; }

    /* Shop grid — single column */
    .shops-grid { grid-template-columns: 1fr; gap: 14px; }

    /* Shop card horizontal layout on mobile */
    .shop-card { flex-direction: row; height: 90px; }
    .shop-card-banner {
        width: 90px; min-width: 90px; height: 100%;
        border-radius: 0;
    }
    .shop-logo-wrap {
        position: static;
        width: 46px; height: 46px;
        border-radius: 10px;
    }
    .shop-card-body { padding: 14px 16px; justify-content: center; }
    .shop-name { font-size: 14.5px; margin-bottom: 2px; }
    .shop-desc { display: none; }
    .shop-meta { margin-top: 6px; }
    .shop-city { font-size: 11.5px; padding: 2px 8px; }
    .shop-visit { font-size: 12px; padding: 5px 12px; }

    /* Features — single column */
    .features-grid { grid-template-columns: 1fr; gap: 12px; }
    .feature-card { padding: 20px; display: flex; gap: 16px; align-items: flex-start; }
    .feature-icon { margin-bottom: 0; flex-shrink: 0; width: 42px; height: 42px; }
    .feature-title { font-size: 14px; margin-bottom: 3px; }
    .feature-desc { font-size: 12.5px; }

    /* CTA */
    .cta-section { padding: 52px 20px; }
    .cta-btns { flex-direction: column; align-items: center; }
    .btn-gold, .btn-outline { width: 100%; max-width: 280px; justify-content: center; }

    /* Footer */
    .footer-top { flex-direction: column; align-items: flex-start; gap: 12px; }
    .footer-links { gap: 14px; }
    .footer-links a { font-size: 12.5px; }
    .footer-bottom { font-size: 12px; }

    /* Marquee slower on mobile */
    .marquee-track { animation-duration: 28s; }
}

/* ── Small mobile (max 380px) ───────────── */
@media (max-width: 380px) {
    .hero h1 { font-size: 28px; letter-spacing: -1px; }
    .search-bar button span { display: none; }
    .nav-brand span { display: none; }
}
</style>
</head>
<body>

<!-- ══ NAVBAR ══════════════════════════════════════════════════ -->
<nav class="navbar">
    <div class="nav-brand">
        <i class="bi bi-bag-heart-fill" style="color:var(--gold);"></i>
        Tamizhmart
    </div>
    <div class="nav-links">
        <a href="owner/login.php" class="nav-btn nav-btn-ghost">
            <i class="bi bi-shop"></i> Owner Login
        </a>
        <a href="superadmin/login.php" class="nav-btn nav-btn-gold">
            <i class="bi bi-shield-lock"></i> Admin
        </a>
    </div>
</nav>

<!-- ══ HERO ════════════════════════════════════════════════════ -->
<section class="hero">
    <div class="hero-tag">
        <i class="bi bi-geo-alt-fill"></i> Tamil Nadu's Local Shopping Platform
    </div>
    <h1>Shop Local.<br><span>Support Tamil Nadu.</span></h1>
    <p>Discover stores from your city — groceries, medicines, sweets, and more. Support local businesses and get delivered to your door.</p>

    <div class="search-wrap">
        <form action="" method="GET">
            <div class="search-bar">
                <i class="bi bi-search" style="color:var(--muted);margin-right:10px;font-size:16px;"></i>
                <input type="text" name="q" placeholder="Search shops by name or city..."
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="submit"><i class="bi bi-search"></i> Search</button>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-num"><?= $total_shops ?>+</div>
            <div class="stat-label">Active Shops</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= $total_products ?>+</div>
            <div class="stat-label">Products</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= $total_orders ?>+</div>
            <div class="stat-label">Orders Delivered</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= count($cities) ?>+</div>
            <div class="stat-label">Cities</div>
        </div>
    </div>
</section>

<!-- ══ MARQUEE ══════════════════════════════════════════════════ -->
<div class="marquee-wrap">
    <div class="marquee-track">
        <?php
        $tags = ['Groceries','Medicines','Sweets','Dairy','Dry Fruits','Snacks','Health','Beverages','Rice & Grains','Personal Care','Chocolates','Ghee & Oils'];
        $all = array_merge($tags, $tags); // duplicate for seamless loop
        foreach ($all as $t) echo "<div class='marquee-item'><i class='bi bi-check-circle-fill'></i>$t</div>";
        ?>
    </div>
</div>

<!-- ══ SHOPS SECTION ════════════════════════════════════════════ -->
<div class="section">
    <div class="section-title">
        <?= $search || $city ? 'Search Results' : 'Browse Shops' ?>
    </div>
    <div class="section-sub">
        <?php if ($search || $city): ?>
            Found <?= count($shops) ?> shop<?= count($shops) != 1 ? 's' : '' ?>
            <?= $search ? " matching \"<strong>" . htmlspecialchars($search) . "</strong>\"" : '' ?>
            <?= $city ? " in <strong>" . htmlspecialchars($city) . "</strong>" : '' ?>
            — <a href="/" style="color:var(--gold);font-weight:600;">Clear filters</a>
        <?php else: ?>
            <?= count($shops) ?> shop<?= count($shops) != 1 ? 's' : '' ?> available across Tamil Nadu
        <?php endif; ?>
    </div>

    <!-- City filters -->
    <?php if (!empty($cities)): ?>
    <div class="city-filters">
        <a href="/" class="city-chip <?= !$city ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap"></i> All Cities
        </a>
        <?php foreach ($cities as $c): ?>
        <a href="?city=<?= urlencode($c) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
           class="city-chip <?= $city === $c ? 'active' : '' ?>">
            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($c) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Shop cards -->
    <div class="shops-grid">
        <?php if (empty($shops)): ?>
        <div class="empty-state">
            <i class="bi bi-shop-window"></i>
            <h3>No shops found</h3>
            <p>Try a different search term or city filter.</p>
        </div>
        <?php else: foreach ($shops as $s):
            $logo       = $s['logo'] ?? null;
            $city_name  = $s['city'] ?? null;
            $desc       = $s['description'] ?? '';
            $initial    = strtoupper(substr($s['name'], 0, 1));
        ?>
        <a href="shop/index.php?shop=<?= urlencode($s['slug']) ?>" class="shop-card">
            <div class="shop-card-banner">
                <!-- Decorative pattern -->
                <svg width="100%" height="100%" style="position:absolute;inset:0;opacity:0.06;" xmlns="http://www.w3.org/2000/svg">
                    <pattern id="p<?= $s['id'] ?>" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
                        <circle cx="12" cy="12" r="2" fill="#c8a97e"/>
                    </pattern>
                    <rect width="100%" height="100%" fill="url(#p<?= $s['id'] ?>)"/>
                </svg>
                <div class="shop-logo-wrap">
                    <?php if ($logo): ?>
                    <img src="assets/uploads/logos/<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($s['name']) ?>">
                    <?php else: ?>
                    <div class="shop-logo-placeholder"><?= $initial ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="shop-card-body">
                <div class="shop-name"><?= htmlspecialchars($s['name']) ?></div>
                <div class="shop-desc">
                    <?= $desc ? htmlspecialchars($desc) : 'Fresh local products available for delivery.' ?>
                </div>
                <div class="shop-meta">
                    <?php if ($city_name): ?>
                    <span class="shop-city"><i class="bi bi-geo-alt-fill"></i><?= htmlspecialchars($city_name) ?></span>
                    <?php else: ?>
                    <span class="shop-city"><i class="bi bi-shop"></i>Tamil Nadu</span>
                    <?php endif; ?>
                    <span class="shop-visit">Visit <i class="bi bi-arrow-right"></i></span>
                </div>
            </div>
        </a>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- ══ FEATURES ════════════════════════════════════════════════ -->
<div style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
<div class="section">
    <div class="section-title" style="text-align:center;">Why TamizhMart?</div>
    <div class="section-sub" style="text-align:center;">Built for Tamil Nadu's local businesses and shoppers</div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-shop-window"></i></div>
            <div class="feature-title">Local Shops Online</div>
            <div class="feature-desc">Your favourite neighbourhood stores — now online. Order from shops in your city.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-truck"></i></div>
            <div class="feature-title">Cash on Delivery</div>
            <div class="feature-desc">Pay when your order arrives at your doorstep. No advance payment needed.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-credit-card"></i></div>
            <div class="feature-title">Online Payment</div>
            <div class="feature-desc">Pay instantly with UPI, cards, net banking, or wallets via Razorpay.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-bell"></i></div>
            <div class="feature-title">Order Notifications</div>
            <div class="feature-desc">Get email confirmation instantly when your order is placed. Stay updated.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-phone"></i></div>
            <div class="feature-title">Works on Mobile</div>
            <div class="feature-desc">Fully PWA-enabled. Install as an app on your phone. Works offline too.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-heart"></i></div>
            <div class="feature-title">Support Local</div>
            <div class="feature-desc">Every order supports a local Tamil Nadu business owner and their family.</div>
        </div>
    </div>
</div>
</div>

<!-- ══ CTA ═════════════════════════════════════════════════════ -->
<section class="cta-section">
    <h2>Own a Shop?<br><span>Start Selling Online Today.</span></h2>
    <p>Set up your online store in minutes. No technical skills needed.</p>
    <div class="cta-btns">
        <a href="owner/register.php" class="btn-gold">
            <i class="bi bi-shop"></i> Register Your Shop
        </a>
        <a href="owner/login.php" class="btn-outline">
            <i class="bi bi-box-arrow-in-right"></i> Owner Login
        </a>
    </div>
</section>

<!-- ══ FOOTER ══════════════════════════════════════════════════ -->
<footer>
    <div class="footer-top">
        <div class="footer-brand">
            <i class="bi bi-bag-heart-fill"></i> TamizhMart
        </div>
        <div class="footer-links">
            <a href="owner/register.php">Register Shop</a>
            <a href="owner/login.php">Owner Login</a>
            <a href="superadmin/login.php">Super Admin</a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>Made with</span>
        <span class="heart">♥</span>
        <span>by</span>
        <strong style="color:rgba(255,255,255,0.6);">SM Tech</strong>
        <span style="margin:0 6px;">·</span>
        <span>TamizhMart © <?= date('Y') ?></span>
        <span style="margin:0 6px;">·</span>
        <span>Tamil Nadu, India</span>
    </div>
</footer>

</body>
</html>