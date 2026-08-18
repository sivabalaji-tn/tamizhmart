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

// ── Platform settings (city name, site name, etc.) ────────────
$ps = $conn->query("SELECT setting_key, setting_value FROM platform_settings");
$platform = [];
while ($r = $ps->fetch_assoc()) $platform[$r['setting_key']] = $r['setting_value'];
$site_name    = $platform['site_name']    ?? 'TamizhMart';
$site_city    = $platform['site_city']    ?? 'Your City';
$site_tagline = $platform['site_tagline'] ?? "Shop Local. Support $site_city.";

// ── Search & fetch shops ──────────────────────────────────────
$search = trim($_GET['q']    ?? '');
$city   = trim($_GET['city'] ?? '');

$where  = "s.is_active = 1 AND s.is_suspended = 0";
$params = []; $types = '';

if ($search !== '') {
    $where   .= " AND (s.name LIKE ? OR s.description LIKE ? OR s.city LIKE ? OR s.address LIKE ?)";
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'ssss';
}
if ($city !== '') {
    $where   .= " AND s.city = ?";
    $params[] = $city;
    $types   .= 's';
}

$sql = "SELECT s.id, s.name, s.slug, s.description, s.city, s.state, s.address,
               s.logo, s.banner, s.theme_primary,
               ss_phone.setting_value AS phone,
               (SELECT COUNT(*) FROM products p WHERE p.shop_id=s.id AND p.is_active=1) AS product_count,
               (SELECT COUNT(*) FROM orders o WHERE o.shop_id=s.id) AS order_count
        FROM shops s
        LEFT JOIN shop_settings ss_phone ON ss_phone.shop_id = s.id AND ss_phone.setting_key = 'phone'
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

// ── Cities for filter ─────────────────────────────────────────
$cities_q = $conn->query("SELECT DISTINCT city, COUNT(*) as cnt FROM shops WHERE is_active=1 AND is_suspended=0 AND city IS NOT NULL AND city != '' GROUP BY city ORDER BY cnt DESC");
$cities = $cities_q->fetch_all(MYSQLI_ASSOC);

// ── Platform stats ────────────────────────────────────────────
$total_shops    = $conn->query("SELECT COUNT(*) FROM shops WHERE is_active=1")->fetch_row()[0];
$total_products = $conn->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetch_row()[0];
$total_orders   = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$total_cities   = count($cities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($site_name) ?> — Shop Local in <?= htmlspecialchars($site_city) ?></title>
<meta name="description" content="Discover and shop from local stores in <?= htmlspecialchars($site_city) ?>. Fresh groceries, medicines, sweets and more delivered to your door.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
a,button{-webkit-tap-highlight-color:transparent;touch-action:manipulation;}
:root{
    --gold:#c8a97e;
    --gold-light:#e8c99e;
    --dark:#1a1208;
    --light:#faf7f2;
    --surface:#f4f0ea;
    --muted:rgba(26,18,8,0.5);
    --border:rgba(26,18,8,0.09);
    --radius:20px;
    --radius-sm:12px;
    --shadow-card:0 2px 16px rgba(26,18,8,0.07),0 8px 32px rgba(26,18,8,0.04);
    --shadow-hover:0 12px 48px rgba(26,18,8,0.14),0 2px 8px rgba(26,18,8,0.06);
}
html{scroll-behavior:smooth;}
body{font-family:'DM Sans',sans-serif;background:var(--surface);color:var(--dark);min-height:100vh;}
a{text-decoration:none;color:inherit;}
img{display:block;}

/* ─── Scrollbar ─── */
::-webkit-scrollbar{width:5px;}
::-webkit-scrollbar-track{background:var(--surface);}
::-webkit-scrollbar-thumb{background:var(--gold);border-radius:99px;opacity:.5;}

/* ════════════════════════════════════════════
   NAVBAR
════════════════════════════════════════════ */
.navbar{
    position:sticky;top:0;z-index:200;
    background:rgba(250,247,242,0.92);
    backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    padding:0 40px;
    display:flex;align-items:center;justify-content:space-between;
    height:66px;
    transition:box-shadow .3s;
}
.navbar.scrolled{box-shadow:0 4px 28px rgba(26,18,8,0.08);}
.nav-brand{
    font-family:'Syne',sans-serif;
    font-weight:900;font-size:22px;
    color:var(--dark);
    display:flex;align-items:center;gap:10px;
}
.nav-brand-icon{
    width:36px;height:36px;border-radius:10px;
    background:var(--dark);
    display:flex;align-items:center;justify-content:center;
    font-size:17px;color:var(--gold);
}
.nav-city-badge{
    display:inline-flex;align-items:center;gap:5px;
    background:rgba(200,169,126,0.13);
    border:1px solid rgba(200,169,126,0.28);
    color:#8b6428;
    padding:5px 13px;border-radius:99px;
    font-size:12.5px;font-weight:700;
    letter-spacing:.3px;
    animation:pulse-badge 3s ease infinite;
}
@keyframes pulse-badge{
    0%,100%{box-shadow:0 0 0 0 rgba(200,169,126,0);}
    50%{box-shadow:0 0 0 4px rgba(200,169,126,0.12);}
}
.nav-right{display:flex;align-items:center;gap:10px;}
.nav-register{
    display:flex;align-items:center;gap:7px;
    padding:10px 22px;border-radius:99px;
    font-size:13.5px;font-weight:700;
    background:var(--dark);color:var(--gold);
    border:none;cursor:pointer;transition:all .22s;
    box-shadow:0 4px 18px rgba(26,18,8,0.18);
}
.nav-register:hover{background:var(--gold);color:var(--dark);transform:translateY(-1px);box-shadow:0 8px 24px rgba(26,18,8,0.2);}

/* ════════════════════════════════════════════
   HERO
════════════════════════════════════════════ */
.hero{
    background:var(--dark);
    padding:88px 40px 0;
    text-align:center;
    position:relative;overflow:hidden;
    min-height:520px;
    display:flex;flex-direction:column;align-items:center;
}

/* animated orbs */
.hero-orb{
    position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;
    animation:orb-float 8s ease-in-out infinite alternate;
}
.hero-orb1{width:500px;height:500px;background:radial-gradient(circle,rgba(200,169,126,.18),transparent 70%);top:-160px;left:-100px;animation-delay:0s;}
.hero-orb2{width:400px;height:400px;background:radial-gradient(circle,rgba(200,169,126,.12),transparent 70%);top:-80px;right:-80px;animation-delay:-3s;}
.hero-orb3{width:300px;height:300px;background:radial-gradient(circle,rgba(200,169,126,.08),transparent 70%);bottom:60px;left:30%;animation-delay:-5s;}
@keyframes orb-float{from{transform:translateY(0) scale(1);}to{transform:translateY(-30px) scale(1.05);}}

/* particles */
.hero-particles{position:absolute;inset:0;overflow:hidden;pointer-events:none;}
.particle{
    position:absolute;width:2px;height:2px;border-radius:50%;
    background:var(--gold);opacity:0;
    animation:particle-rise var(--dur,6s) var(--delay,0s) ease-in infinite;
}
@keyframes particle-rise{
    0%{opacity:0;transform:translateY(0) scale(1);}
    10%{opacity:.6;}
    90%{opacity:.1;}
    100%{opacity:0;transform:translateY(-260px) scale(0.3);}
}

.hero-inner{position:relative;z-index:2;width:100%;max-width:820px;margin:0 auto;}

.hero-eyebrow{
    display:inline-flex;align-items:center;gap:8px;
    background:rgba(200,169,126,.1);
    border:1px solid rgba(200,169,126,.22);
    color:var(--gold);
    padding:7px 18px;border-radius:99px;
    font-size:13px;font-weight:700;letter-spacing:.6px;
    margin-bottom:30px;
    animation:fadeUp .7s ease both;
}
.city-pill{
    color:#fff;
    background:rgba(200,169,126,.18);
    border-radius:5px;padding:1px 9px;
    border:1px solid rgba(200,169,126,.3);
    font-weight:800;
}
.hero h1{
    font-family:'Syne',sans-serif;
    font-weight:900;
    font-size:clamp(36px,6.5vw,70px);
    line-height:1.04;letter-spacing:-2.5px;
    color:#fff;
    margin-bottom:22px;
    animation:fadeUp .7s .1s ease both;
}
.hero h1 .accent{
    background:linear-gradient(135deg,#c8a97e 20%,#f0d9b0 60%,#c8a97e 100%);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;
}
.hero-sub{
    font-size:clamp(15px,1.8vw,18px);
    color:rgba(255,255,255,.48);
    max-width:540px;margin:0 auto 48px;
    line-height:1.75;
    animation:fadeUp .7s .2s ease both;
}

/* ─── Search ─── */
.search-outer{
    max-width:640px;margin:0 auto;
    animation:fadeUp .7s .3s ease both;
}
.search-pill{
    display:flex;align-items:center;
    background:#fff;
    border-radius:99px;
    padding:7px 7px 7px 26px;
    box-shadow:0 16px 56px rgba(0,0,0,.4),0 0 0 1px rgba(200,169,126,.18);
    gap:10px;
}
.search-pill input{
    flex:1;border:none;outline:none;
    font-family:'DM Sans',sans-serif;
    font-size:15px;background:transparent;
    color:var(--dark);
}
.search-pill input::placeholder{color:rgba(26,18,8,.3);}
.search-pill button{
    background:var(--dark);color:var(--gold);
    border:none;border-radius:99px;
    padding:12px 28px;font-size:14px;
    font-weight:700;cursor:pointer;
    display:flex;align-items:center;gap:7px;
    transition:all .2s;white-space:nowrap;
    flex-shrink:0;
}
.search-pill button:hover{background:var(--gold);color:var(--dark);}

/* ─── Stats strip ─── */
.stats-strip{
    position:relative;z-index:2;
    display:flex;justify-content:center;
    border-top:1px solid rgba(200,169,126,.1);
    margin-top:56px;
    background:rgba(200,169,126,.04);
    width:100%;
}
.stat-box{
    flex:1;max-width:200px;
    text-align:center;
    padding:24px 12px;
    border-right:1px solid rgba(200,169,126,.08);
}
.stat-box:last-child{border-right:none;}
.stat-num{
    font-family:'Syne',sans-serif;
    font-weight:800;font-size:30px;
    color:var(--gold);line-height:1;
}
.stat-label{font-size:11px;color:rgba(255,255,255,.3);margin-top:5px;text-transform:uppercase;letter-spacing:.6px;}

/* ════════════════════════════════════════════
   MARQUEE
════════════════════════════════════════════ */
.marquee-strip{background:var(--gold);padding:12px 0;overflow:hidden;}
.marquee-track{
    display:flex;gap:48px;
    animation:marquee 24s linear infinite;
    white-space:nowrap;width:max-content;
}
.marquee-item{
    display:flex;align-items:center;gap:8px;
    font-size:12.5px;font-weight:700;
    color:var(--dark);text-transform:uppercase;letter-spacing:.6px;
}
.marquee-item i{font-size:10px;opacity:.6;}
@keyframes marquee{from{transform:translateX(0);}to{transform:translateX(-50%);}}

/* ════════════════════════════════════════════
   PAGE BODY
════════════════════════════════════════════ */
.page-body{max-width:1300px;margin:0 auto;padding:64px 32px 100px;}

/* ─── Section header ─── */
.sec-head{
    display:flex;align-items:flex-end;justify-content:space-between;
    flex-wrap:wrap;gap:12px;
    margin-bottom:32px;
}
.sec-tag{
    display:flex;align-items:center;gap:7px;
    font-size:11px;font-weight:700;letter-spacing:2px;
    text-transform:uppercase;color:var(--gold);
    margin-bottom:7px;
}
.sec-tag::before{content:'';width:22px;height:2px;background:var(--gold);border-radius:99px;}
.sec-title{
    font-family:'Syne',sans-serif;
    font-weight:900;font-size:clamp(22px,2.5vw,30px);
    letter-spacing:-.7px;
}
.sec-sub{font-size:13.5px;color:var(--muted);margin-top:4px;}

/* ─── City chips ─── */
.chips-row{
    display:flex;flex-wrap:wrap;gap:9px;
    margin-bottom:40px;
}
.chip{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 18px;border-radius:99px;
    font-size:13px;font-weight:600;
    border:1.5px solid var(--border);
    background:#fff;color:var(--dark);
    cursor:pointer;transition:all .18s;
    white-space:nowrap;
    box-shadow:0 1px 4px rgba(26,18,8,.05);
}
.chip:hover,.chip.on{
    background:var(--dark);color:var(--gold);
    border-color:var(--dark);
    box-shadow:0 4px 16px rgba(26,18,8,.15);
}
.chip-count{
    font-size:11px;font-weight:700;
    background:rgba(26,18,8,.07);
    padding:1px 7px;border-radius:99px;
}
.chip.on .chip-count{background:rgba(200,169,126,.22);}

/* ════════════════════════════════════════════
   SHOP CARDS
════════════════════════════════════════════ */
.shops-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(310px,1fr));
    gap:26px;
}
.shop-card{
    background:#fff;
    border-radius:var(--radius);
    overflow:hidden;
    box-shadow:var(--shadow-card);
    transition:transform .28s cubic-bezier(.34,1.56,.64,1),box-shadow .28s ease;
    display:flex;flex-direction:column;
    cursor:pointer;
    border:1px solid var(--border);
    position:relative;
}
.shop-card:hover{
    transform:translateY(-8px) scale(1.01);
    box-shadow:var(--shadow-hover);
}

/* card top / banner area */
.shop-card-top{
    height:152px;
    position:relative;overflow:hidden;
    display:flex;align-items:flex-end;
    padding:0 20px 20px;
    flex-shrink:0;
}
.shop-card-bg{position:absolute;inset:0;}
.shop-card-bg-pattern{
    position:absolute;inset:0;opacity:.055;
    background-image:radial-gradient(circle,#c8a97e 1px,transparent 1px);
    background-size:22px 22px;
}
.shop-card-glow{
    position:absolute;inset:0;
    background:radial-gradient(ellipse 80% 70% at 50% 120%,rgba(200,169,126,.3),transparent);
}
.shop-card-banner-img{
    position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
    transition:transform .4s ease;
}
.shop-card:hover .shop-card-banner-img{transform:scale(1.05);}
.shop-card-banner-scrim{
    position:absolute;inset:0;
    background:linear-gradient(175deg,rgba(0,0,0,.25) 0%,rgba(0,0,0,.55) 100%);
}

/* logo */
.shop-logo{
    position:relative;z-index:2;
    width:58px;height:58px;border-radius:15px;
    background:#fff;
    border:2.5px solid rgba(255,255,255,.85);
    overflow:hidden;display:flex;align-items:center;justify-content:center;
    box-shadow:0 6px 22px rgba(0,0,0,.28);
    flex-shrink:0;
    transition:transform .25s ease;
}
.shop-card:hover .shop-logo{transform:scale(1.06);}
.shop-logo img{width:100%;height:100%;object-fit:cover;}
.shop-logo-init{
    font-family:'Syne',sans-serif;
    font-weight:900;font-size:23px;
    color:var(--dark);
}

/* city tag */
.shop-tag{
    position:absolute;top:14px;right:14px;z-index:2;
    background:rgba(0,0,0,.38);
    backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.15);
    color:#fff;
    padding:4px 11px;border-radius:99px;
    font-size:11px;font-weight:700;
    display:flex;align-items:center;gap:5px;
}

/* card body */
.shop-card-body{
    padding:20px 20px 14px;
    flex:1;display:flex;flex-direction:column;
    gap:8px;
}
.shop-card-name{
    font-family:'Syne',sans-serif;
    font-weight:800;font-size:17.5px;
    line-height:1.2;
    color:var(--dark);
}
.shop-card-desc{
    font-size:13.5px;color:var(--muted);
    line-height:1.6;
    display:-webkit-box;-webkit-line-clamp:2;
    -webkit-box-orient:vertical;overflow:hidden;
    flex:1;
}
.shop-card-address{
    display:flex;align-items:flex-start;gap:6px;
    font-size:12.5px;color:rgba(26,18,8,.45);
    line-height:1.4;
    margin-top:2px;
}
.shop-card-address i{color:var(--gold);font-size:13px;flex-shrink:0;margin-top:1px;}

/* card footer */
.shop-card-footer{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 20px;
    border-top:1px solid var(--border);
    background:rgba(26,18,8,.018);
}
.shop-card-meta{
    display:flex;align-items:center;gap:12px;
    font-size:12px;color:var(--muted);
}
.shop-card-meta span{display:flex;align-items:center;gap:4px;}
.shop-visit-btn{
    display:flex;align-items:center;gap:6px;
    font-size:13px;font-weight:700;
    padding:7px 17px;border-radius:99px;
    background:var(--dark);color:var(--gold);
    transition:all .18s;
}
.shop-card:hover .shop-visit-btn{background:var(--gold);color:var(--dark);}

/* ─── Primary colour accent line on hover ─── */
.shop-card::after{
    content:'';position:absolute;bottom:0;left:0;right:0;height:3px;
    background:var(--gold);
    transform:scaleX(0);transform-origin:left;
    transition:transform .3s ease;
    border-radius:0 0 var(--radius) var(--radius);
}
.shop-card:hover::after{transform:scaleX(1);}

/* ════════════════════════════════════════════
   REGISTER CTA BANNER
════════════════════════════════════════════ */
.register-cta{
    background:var(--dark);
    border-radius:var(--radius);
    padding:64px 48px;
    text-align:center;
    position:relative;overflow:hidden;
    margin-top:72px;
}
.register-cta::before{
    content:'';position:absolute;inset:0;
    background:
        radial-gradient(ellipse 70% 60% at 50% -10%,rgba(200,169,126,.22) 0%,transparent 60%),
        radial-gradient(ellipse 40% 50% at 10% 90%,rgba(200,169,126,.07) 0%,transparent 55%),
        radial-gradient(ellipse 40% 50% at 90% 90%,rgba(200,169,126,.07) 0%,transparent 55%);
    pointer-events:none;
}
.register-cta h2{
    font-family:'Syne',sans-serif;
    font-weight:900;font-size:clamp(26px,4vw,40px);
    letter-spacing:-1.5px;
    color:#fff;
    margin-bottom:14px;
    position:relative;z-index:1;
    line-height:1.1;
}
.register-cta h2 span{
    background:linear-gradient(135deg,#c8a97e,#f0d9b0);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.register-cta p{
    font-size:16px;color:rgba(255,255,255,.45);
    max-width:460px;margin:0 auto 36px;
    line-height:1.7;
    position:relative;z-index:1;
}
.cta-btns{
    display:flex;justify-content:center;gap:14px;
    flex-wrap:wrap;
    position:relative;z-index:1;
}
.btn-gold{
    display:inline-flex;align-items:center;gap:8px;
    padding:14px 32px;border-radius:99px;
    font-size:15px;font-weight:700;
    background:var(--gold);color:var(--dark);
    transition:all .22s;
    box-shadow:0 8px 28px rgba(200,169,126,.35);
}
.btn-gold:hover{background:var(--gold-light);transform:translateY(-2px);box-shadow:0 14px 36px rgba(200,169,126,.4);color:var(--dark);}

/* ════════════════════════════════════════════
   HOW IT WORKS
════════════════════════════════════════════ */
.how-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:20px;
    margin-top:8px;
}
.how-card{
    background:#fff;
    border:1.5px solid var(--border);
    border-radius:18px;
    padding:28px 24px;
    transition:all .25s;
    position:relative;overflow:hidden;
}
.how-card:hover{border-color:rgba(200,169,126,.5);box-shadow:0 8px 32px rgba(26,18,8,.08);transform:translateY(-4px);}
.how-card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:linear-gradient(90deg,var(--gold),var(--gold-light));
    border-radius:18px 18px 0 0;
    transform:scaleX(0);transform-origin:left;transition:transform .25s;
}
.how-card:hover::before{transform:scaleX(1);}
.how-num{
    font-family:'Syne',sans-serif;font-weight:900;font-size:36px;
    color:rgba(200,169,126,.18);line-height:1;margin-bottom:14px;
}
.how-icon{
    width:48px;height:48px;border-radius:13px;
    background:rgba(200,169,126,.1);
    display:flex;align-items:center;justify-content:center;
    font-size:21px;color:var(--gold);
    margin-bottom:16px;
}
.how-title{font-family:'Syne',sans-serif;font-weight:800;font-size:16px;margin-bottom:7px;}
.how-desc{font-size:13.5px;color:var(--muted);line-height:1.6;}

/* ════════════════════════════════════════════
   EMPTY STATE
════════════════════════════════════════════ */
.empty-state{
    grid-column:1/-1;text-align:center;padding:80px 24px;
}
.empty-state .icon{font-size:60px;color:var(--gold);opacity:.35;margin-bottom:18px;}
.empty-state h3{font-family:'Syne',sans-serif;font-weight:800;font-size:22px;margin-bottom:10px;}
.empty-state p{font-size:14.5px;color:var(--muted);}

/* ════════════════════════════════════════════
   FOOTER
════════════════════════════════════════════ */
footer{
    background:#0d0903;
    padding:44px 40px 28px;
    color:rgba(255,255,255,.28);
}
.foot-top{
    display:flex;align-items:flex-start;justify-content:space-between;
    flex-wrap:wrap;gap:24px;
    padding-bottom:28px;
    border-bottom:1px solid rgba(255,255,255,.06);
    margin-bottom:22px;
}
.foot-brand{
    font-family:'Syne',sans-serif;font-weight:900;
    font-size:20px;color:var(--gold);
    display:flex;align-items:center;gap:9px;
    margin-bottom:8px;
}
.foot-tagline{font-size:13px;color:rgba(255,255,255,.25);max-width:220px;line-height:1.6;}
.foot-col-title{
    font-size:11.5px;font-weight:700;letter-spacing:1.2px;
    text-transform:uppercase;color:rgba(255,255,255,.2);
    margin-bottom:12px;
}
.foot-links{display:flex;flex-direction:column;gap:8px;}
.foot-links a{font-size:13.5px;color:rgba(255,255,255,.28);transition:color .15s;}
.foot-links a:hover{color:var(--gold);}
.foot-bottom{
    text-align:center;font-size:12.5px;
    display:flex;align-items:center;justify-content:center;
    gap:6px;flex-wrap:wrap;
}

/* ════════════════════════════════════════════
   ANIMATIONS
════════════════════════════════════════════ */
@keyframes fadeUp{from{opacity:0;transform:translateY(22px);}to{opacity:1;transform:translateY(0);}}
.anim{opacity:0;transform:translateY(18px);transition:opacity .55s ease,transform .55s ease;}
.anim.in{opacity:1;transform:translateY(0);}

/* ════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════ */
@media(max-width:960px){
    .shops-grid{grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:20px;}
    .page-body{padding:48px 24px 80px;}
    .register-cta{padding:48px 28px;}
}
@media(max-width:640px){
    .navbar{padding:0 18px;height:60px;}
    .hero{padding:56px 18px 0;}
    .hero h1{letter-spacing:-1.5px;}
    .nav-city-badge{display:none;}
    .search-pill{padding:5px 5px 5px 18px;}
    .search-pill button{padding:11px 20px;font-size:13px;}
    .stats-strip{display:grid;grid-template-columns:1fr 1fr;}
    .stat-box{border-bottom:1px solid rgba(200,169,126,.08);}
    .stat-box:nth-child(even){border-right:none;}
    .chips-row{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding-bottom:4px;}
    .chips-row::-webkit-scrollbar{display:none;}
    .chip{flex-shrink:0;}
    .shops-grid{grid-template-columns:1fr;gap:18px;}
    .page-body{padding:36px 18px 70px;}
    .how-grid{grid-template-columns:1fr 1fr;}
    .register-cta{padding:40px 20px;}
    .cta-btns{flex-direction:column;align-items:center;}
    .btn-gold{width:100%;max-width:300px;justify-content:center;}
    .foot-top{flex-direction:column;}
}
@media(max-width:400px){
    .hero h1{font-size:30px;}
    .how-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- ══ NAVBAR ══════════════════════════════════════════════════ -->
<nav class="navbar" id="mainNav">
    <div style="display:flex;align-items:center;gap:14px;">
        <div class="nav-brand">
            <div class="nav-brand-icon"><i class="bi bi-bag-heart-fill"></i></div>
            <?= htmlspecialchars($site_name) ?>
        </div>
        <span class="nav-city-badge">
            <i class="bi bi-geo-alt-fill"></i>
            <?= htmlspecialchars($site_city) ?>
        </span>
    </div>
    <div class="nav-right">
        <a href="auth/register.php" class="nav-register">
            <i class="bi bi-shop"></i>
            <span>Register Your Shop</span>
        </a>
    </div>
</nav>

<!-- ══ HERO ════════════════════════════════════════════════════ -->
<section class="hero">
    <!-- Animated orbs -->
    <div class="hero-orb hero-orb1"></div>
    <div class="hero-orb hero-orb2"></div>
    <div class="hero-orb hero-orb3"></div>

    <!-- Particles -->
    <div class="hero-particles" id="heroParticles"></div>

    <div class="hero-inner">
        <div class="hero-eyebrow">
            <i class="bi bi-geo-alt-fill"></i>
            Shopping in <span class="city-pill"><?= htmlspecialchars($site_city) ?></span>
        </div>

        <h1>
            Shop Local.<br>
            <span class="accent">Support <?= htmlspecialchars($site_city) ?>.</span>
        </h1>

        <p class="hero-sub">
            Discover stores from your neighbourhood — groceries, medicines, sweets, and more.
            Order online and get delivered right to your door.
        </p>

        <div class="search-outer">
            <form action="" method="GET">
                <div class="search-pill">
                    <i class="bi bi-search" style="color:rgba(26,18,8,.28);font-size:16px;flex-shrink:0;"></i>
                    <input type="text" name="q"
                        placeholder="Search shops, products, areas..."
                        value="<?= htmlspecialchars($search) ?>"
                        autocomplete="off">
                    <button type="submit">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="stats-strip">
        <div class="stat-box">
            <div class="stat-num"><?= $total_shops ?>+</div>
            <div class="stat-label">Local Shops</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?= $total_products ?>+</div>
            <div class="stat-label">Products</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?= $total_orders ?>+</div>
            <div class="stat-label">Orders</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?= $total_cities ?>+</div>
            <div class="stat-label">Areas</div>
        </div>
    </div>
</section>

<!-- ══ MARQUEE ══════════════════════════════════════════════════ -->
<div class="marquee-strip">
    <div class="marquee-track">
        <?php
        $tags = ['Groceries','Medicines','Sweets','Dairy','Dry Fruits','Snacks','Health & Wellness','Beverages','Rice & Grains','Personal Care','Chocolates','Ghee & Oils','Bakery','Stationery','Clothing'];
        $all  = array_merge($tags, $tags);
        foreach ($all as $t) echo "<div class='marquee-item'><i class='bi bi-dot'></i>" . htmlspecialchars($t) . "</div>";
        ?>
    </div>
</div>

<!-- ══ SHOPS SECTION ════════════════════════════════════════════ -->
<div class="page-body">

    <!-- Section header -->
    <div class="sec-head anim">
        <div>
            <div class="sec-tag">Discover</div>
            <div class="sec-title">
                <?php if ($search || $city): ?>
                    <?= $city ? htmlspecialchars($city) . ' Shops' : 'Search Results' ?>
                <?php else: ?>
                    Shops in <?= htmlspecialchars($site_city) ?>
                <?php endif; ?>
            </div>
            <div class="sec-sub">
                <?php if ($search || $city): ?>
                    <?= count($shops) ?> result<?= count($shops) != 1 ? 's' : '' ?>
                    <?= $search ? " for \"<strong>" . htmlspecialchars($search) . "</strong>\"" : '' ?>
                    &nbsp;—&nbsp; <a href="/" style="color:var(--gold);font-weight:600;">Clear</a>
                <?php else: ?>
                    <?= count($shops) ?> shop<?= count($shops) != 1 ? 's' : '' ?> available near you
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- City / area chips -->
    <?php if (!empty($cities)): ?>
    <div class="chips-row anim">
        <a href="/" class="chip <?= !$city ? 'on' : '' ?>">
            <i class="bi bi-grid-fill"></i> All Areas
            <span class="chip-count"><?= $total_shops ?></span>
        </a>
        <?php foreach ($cities as $c): ?>
        <a href="?city=<?= urlencode($c['city']) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           class="chip <?= $city === $c['city'] ? 'on' : '' ?>">
            <i class="bi bi-geo-alt"></i>
            <?= htmlspecialchars($c['city']) ?>
            <span class="chip-count"><?= $c['cnt'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Shop cards -->
    <div class="shops-grid">
        <?php if (empty($shops)): ?>
        <div class="empty-state">
            <div class="icon"><i class="bi bi-shop-window"></i></div>
            <h3>No shops found</h3>
            <p>Try a different area or search term.</p>
        </div>
        <?php else:
            $fallback_grads = [
                ['#1a1208','#2d1f0a'],
                ['#0a1628','#102040'],
                ['#1a0a1a','#2d0a2d'],
                ['#0a1a0a','#0f2a0f'],
                ['#1a0a0a','#2d1010'],
                ['#0a1a1a','#0f2a2a'],
            ];
            foreach ($shops as $i => $s):
                $logo    = $s['logo']    ?? null;
                $banner  = $s['banner']  ?? null;
                $desc    = trim($s['description'] ?? '');
                $sname   = $s['name'];
                $scity   = $s['city']    ?? null;
                $saddr   = $s['address'] ?? null;
                $initial = strtoupper(mb_substr($sname, 0, 1));
                $grad    = $fallback_grads[$i % count($fallback_grads)];
                $pcolor  = $s['theme_primary'] ?? '#c8a97e';
        ?>
        <a href="shop/index.php?shop=<?= urlencode($s['slug']) ?>" class="shop-card anim">

            <!-- Banner / card top -->
            <div class="shop-card-top">
                <?php if ($banner): ?>
                <img src="assets/uploads/banners/<?= htmlspecialchars($banner) ?>" class="shop-card-banner-img" alt="">
                <div class="shop-card-banner-scrim"></div>
                <?php else: ?>
                <div class="shop-card-bg" style="background:linear-gradient(135deg,<?= $grad[0] ?>,<?= $grad[1] ?>);"></div>
                <div class="shop-card-bg-pattern"></div>
                <div class="shop-card-glow"></div>
                <?php endif; ?>

                <?php if ($scity): ?>
                <div class="shop-tag"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($scity) ?></div>
                <?php endif; ?>

                <!-- Shop logo -->
                <div class="shop-logo">
                    <?php if ($logo): ?>
                    <img src="assets/uploads/logos/<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($sname) ?>">
                    <?php else: ?>
                    <div class="shop-logo-init" style="color:<?= htmlspecialchars($pcolor) ?>;"><?= $initial ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Body -->
            <div class="shop-card-body">
                <div class="shop-card-name"><?= htmlspecialchars($sname) ?></div>

                <?php if ($desc): ?>
                <div class="shop-card-desc"><?= htmlspecialchars($desc) ?></div>
                <?php else: ?>
                <div class="shop-card-desc" style="color:rgba(26,18,8,.3);font-style:italic;">Local shop in <?= htmlspecialchars($scity ?: $site_city) ?></div>
                <?php endif; ?>

                <?php if ($saddr): ?>
                <div class="shop-card-address">
                    <i class="bi bi-geo-alt-fill"></i>
                    <?= htmlspecialchars($saddr) ?>
                </div>
                <?php elseif ($scity): ?>
                <div class="shop-card-address">
                    <i class="bi bi-geo-alt"></i>
                    <?= htmlspecialchars($scity) ?><?= $s['state'] ? ', '.htmlspecialchars($s['state']) : '' ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="shop-card-footer">
                <div class="shop-card-meta">
                    <?php if ($s['product_count'] > 0): ?>
                    <span><i class="bi bi-box-seam"></i> <?= $s['product_count'] ?> products</span>
                    <?php endif; ?>
                    <?php if ($s['order_count'] > 0): ?>
                    <span><i class="bi bi-bag-check"></i> <?= $s['order_count'] ?>+ orders</span>
                    <?php endif; ?>
                </div>
                <div class="shop-visit-btn">
                    Visit <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </a>
        <?php endforeach; endif; ?>
    </div>

    <!-- ══ How it works ══ -->
    <div style="margin-top:80px;">
        <div class="sec-head anim">
            <div>
                <div class="sec-tag">Simple Steps</div>
                <div class="sec-title">How It Works</div>
                <div class="sec-sub">Shop from your favourite local stores in 3 easy steps</div>
            </div>
        </div>
        <div class="how-grid">
            <div class="how-card anim">
                <div class="how-num">01</div>
                <div class="how-icon"><i class="bi bi-search"></i></div>
                <div class="how-title">Find Your Shop</div>
                <div class="how-desc">Browse local shops in <?= htmlspecialchars($site_city) ?> by area, category, or search by name.</div>
            </div>
            <div class="how-card anim">
                <div class="how-num">02</div>
                <div class="how-icon"><i class="bi bi-bag-plus"></i></div>
                <div class="how-title">Add to Cart</div>
                <div class="how-desc">Pick your favourite products and add them to your cart with a single tap.</div>
            </div>
            <div class="how-card anim">
                <div class="how-num">03</div>
                <div class="how-icon"><i class="bi bi-truck"></i></div>
                <div class="how-title">Get Delivered</div>
                <div class="how-desc">Place your order and get it delivered right to your doorstep, fast and fresh.</div>
            </div>
            <div class="how-card anim">
                <div class="how-num">04</div>
                <div class="how-icon"><i class="bi bi-heart"></i></div>
                <div class="how-title">Support Local</div>
                <div class="how-desc">Every order directly supports a local business owner in your community.</div>
            </div>
        </div>
    </div>

    <!-- ══ Register CTA ══ -->
    <div class="register-cta anim">
        <h2>Own a Shop in <span><?= htmlspecialchars($site_city) ?></span>?<br>Start Selling Online Today.</h2>
        <p>Set up your branded online store in minutes. Reach customers across <?= htmlspecialchars($site_city) ?> and grow your business.</p>
        <div class="cta-btns">
            <a href="auth/register.php" class="btn-gold">
                <i class="bi bi-shop"></i> Register Your Shop — Free
            </a>
        </div>
    </div>

</div>

<!-- ══ FOOTER ══════════════════════════════════════════════════ -->
<footer>
    <div class="foot-top">
        <div>
            <div class="foot-brand">
                <i class="bi bi-bag-heart-fill"></i> <?= htmlspecialchars($site_name) ?>
            </div>
            <div class="foot-tagline">Shop local. Support <?= htmlspecialchars($site_city) ?>. Delivered to your door.</div>
        </div>
        <div>
            <div class="foot-col-title">For Shops</div>
            <div class="foot-links">
                <a href="auth/register.php"><i class="bi bi-shop" style="margin-right:6px;"></i>Register Your Shop</a>
            </div>
        </div>
        <div>
            <div class="foot-col-title">Platform</div>
            <div class="foot-links">
                <a href="#shops">Browse Shops</a>
            </div>
        </div>
    </div>
    <div class="foot-bottom">
        <span>Made with</span>
        <span style="color:#e74c3c;">♥</span>
        <span>by</span>
        <strong style="color:rgba(255,255,255,.5);">SM Tech</strong>
        <span style="margin:0 6px;">·</span>
        <span><?= htmlspecialchars($site_name) ?> © <?= date('Y') ?></span>
        <span style="margin:0 6px;">·</span>
        <span><?= htmlspecialchars($site_city) ?></span>
    </div>
</footer>

<script>
// ── Navbar scroll shadow ──
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 20);
}, {passive: true});

// ── Scroll-in animations ──
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('in'); });
}, {threshold: 0.12});
document.querySelectorAll('.anim').forEach(el => observer.observe(el));

// ── Hero particles ──
(function(){
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 32; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.cssText = `
            left:${Math.random()*100}%;
            bottom:${Math.random()*30}%;
            --dur:${5 + Math.random()*7}s;
            --delay:${Math.random()*8}s;
            width:${1+Math.random()*2}px;
            height:${1+Math.random()*2}px;
            opacity:0;
        `;
        container.appendChild(p);
    }
})();
</script>
</body>
</html>
