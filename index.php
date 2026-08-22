<?php
/**
 * TamizhMart — Platform Landing Page (Tailwind CSS)
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

// ── Platform settings ─────────────────────────────────────────
$ps = $conn->query("SELECT setting_key, setting_value FROM platform_settings");
$platform = [];
while ($r = $ps->fetch_assoc()) $platform[$r['setting_key']] = $r['setting_value'];
$site_name    = $platform['site_name'] ?? 'TamizhMart';
$site_city    = $platform['site_city'] ?? 'Your City';
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

$fallback_grads = [
    ['#1a1208','#2d1f0a'],['#0a1628','#102040'],['#1a0a1a','#2d0a2d'],
    ['#0a1a0a','#0f2a0f'],['#1a0a0a','#2d1010'],['#0a1a1a','#0f2a2a'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($site_name) ?> — Shop Local in <?= htmlspecialchars($site_city) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/mart-removebg-preview.png">
<meta name="description" content="Discover and shop from local stores in <?= htmlspecialchars($site_city) ?>. Fresh groceries, medicines, sweets and more delivered to your door.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        gold: '#c8a97e',
        'gold-light': '#e8c99e',
        dark: '#1a1208',
        surface: '#f4f0ea',
        light: '#faf7f2',
      },
      fontFamily: {
        syne: ['Syne', 'sans-serif'],
        dm: ['DM Sans', 'sans-serif'],
      },
      boxShadow: {
        'card': '0 2px 16px rgba(26,18,8,0.07), 0 8px 32px rgba(26,18,8,0.04)',
        'hover': '0 12px 48px rgba(26,18,8,0.14), 0 2px 8px rgba(26,18,8,0.06)',
        'gold': '0 8px 28px rgba(200,169,126,0.35)',
        'nav': '0 4px 18px rgba(26,18,8,0.18)',
      },
    }
  }
}
</script>
<style>
  *,*::before,*::after{box-sizing:border-box;}
  a,button{-webkit-tap-highlight-color:transparent;touch-action:manipulation;}
  html{scroll-behavior:smooth;}
  body{font-family:'DM Sans',sans-serif;}
  a{text-decoration:none;color:inherit;}
  img{display:block;}
  ::-webkit-scrollbar{width:5px;}
  ::-webkit-scrollbar-track{background:#f4f0ea;}
  ::-webkit-scrollbar-thumb{background:#c8a97e;border-radius:99px;}

  @keyframes marquee{from{transform:translateX(0);}to{transform:translateX(-50%);}}
  @keyframes orb-float{from{transform:translateY(0) scale(1);}to{transform:translateY(-30px) scale(1.05);}}
  @keyframes pulse-badge{0%,100%{box-shadow:0 0 0 0 rgba(200,169,126,0);}50%{box-shadow:0 0 0 4px rgba(200,169,126,0.12);}}
  @keyframes fadeUp{from{opacity:0;transform:translateY(22px);}to{opacity:1;transform:translateY(0);}}
  @keyframes particle-rise{0%{opacity:0;transform:translateY(0);}10%{opacity:.6;}90%{opacity:.1;}100%{opacity:0;transform:translateY(-260px) scale(0.3);}}

  .marquee-track{display:flex;gap:48px;animation:marquee 24s linear infinite;white-space:nowrap;width:max-content;}
  .hero-orb{position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;animation:orb-float 8s ease-in-out infinite alternate;}
  .hero-orb1{width:500px;height:500px;background:radial-gradient(circle,rgba(200,169,126,.18),transparent 70%);top:-160px;left:-100px;}
  .hero-orb2{width:400px;height:400px;background:radial-gradient(circle,rgba(200,169,126,.12),transparent 70%);top:-80px;right:-80px;animation-delay:-3s;}
  .hero-orb3{width:300px;height:300px;background:radial-gradient(circle,rgba(200,169,126,.08),transparent 70%);bottom:60px;left:30%;animation-delay:-5s;}
  .particle{position:absolute;border-radius:50%;background:#c8a97e;opacity:0;animation:particle-rise var(--dur,6s) var(--delay,0s) ease-in infinite;}
  .city-badge{animation:pulse-badge 3s ease infinite;}
  .anim{opacity:0;transform:translateY(18px);transition:opacity .55s ease,transform .55s ease;}
  .anim.in{opacity:1;transform:translateY(0);}

  .shop-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:#c8a97e;transform:scaleX(0);transform-origin:left;transition:transform .3s ease;border-radius:0 0 20px 20px;}
  .shop-card:hover::after{transform:scaleX(1);}
  .shop-card:hover .shop-banner-img{transform:scale(1.05);}
  .shop-card:hover .shop-logo-wrap{transform:scale(1.06);}
  .shop-card:hover .shop-visit-btn{background:#c8a97e !important;color:#1a1208 !important;}

  .how-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#c8a97e,#e8c99e);border-radius:18px 18px 0 0;transform:scaleX(0);transform-origin:left;transition:transform .25s;}
  .how-card:hover::before{transform:scaleX(1);}

  .chips-scroll{display:flex;flex-wrap:wrap;gap:9px;}
  @media(max-width:640px){
    .chips-scroll{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding-bottom:4px;}
    .chips-scroll::-webkit-scrollbar{display:none;}
    .stat-box:nth-child(2){border-right-width:0;}
  }
</style>
</head>
<body class="bg-surface text-dark min-h-screen font-dm">

<!-- NAVBAR -->
<nav id="mainNav" class="sticky top-0 z-50 bg-light/90 backdrop-blur-xl border-b border-dark/[0.09] px-5 sm:px-10 flex items-center justify-between h-[66px] transition-shadow duration-300">
  <div class="flex items-center gap-3 min-w-0">
    <a href="/" class="flex items-center gap-2.5 font-syne font-black text-xl sm:text-[22px] text-dark flex-shrink-0">
      <div class="w-9 h-9 rounded-[10px] bg-dark flex items-center justify-center text-gold text-[17px] flex-shrink-0">
        <i class="bi bi-bag-heart-fill"></i>
      </div>
      <span><?= htmlspecialchars($site_name) ?></span>
    </a>
    <span class="city-badge hidden sm:inline-flex items-center gap-1.5 bg-gold/[0.13] border border-gold/[0.28] text-[#8b6428] px-3 py-1 rounded-full text-xs font-bold tracking-wide flex-shrink-0">
      <i class="bi bi-geo-alt-fill"></i>
      <?= htmlspecialchars($site_city) ?>
    </span>
  </div>
  <a href="owner/register.php"
     class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-5 py-2 sm:py-2.5 rounded-full bg-dark text-gold font-bold text-xs sm:text-[13.5px] flex-shrink-0 whitespace-nowrap shadow-nav transition-all duration-200 hover:bg-gold hover:text-dark hover:-translate-y-px">
    <i class="bi bi-shop"></i>
    <span class="hidden sm:inline">Register Your Shop</span>
    <span class="sm:hidden">Register</span>
  </a>
</nav>

<!-- HERO -->
<section class="relative bg-dark overflow-hidden flex flex-col items-center pt-20 sm:pt-24 min-h-[520px]">
  <div class="hero-orb hero-orb1"></div>
  <div class="hero-orb hero-orb2"></div>
  <div class="hero-orb hero-orb3"></div>
  <div class="absolute inset-0 overflow-hidden pointer-events-none" id="heroParticles"></div>

  <div class="relative z-10 w-full max-w-3xl mx-auto px-5 text-center">
    <div class="inline-flex items-center gap-2 bg-gold/10 border border-gold/20 text-gold px-4 py-1.5 rounded-full text-[13px] font-bold tracking-wide mb-7"
         style="animation:fadeUp .7s ease both;">
      <i class="bi bi-geo-alt-fill"></i>
      Shopping in <span class="text-white bg-gold/20 border border-gold/30 rounded px-2 py-0.5 font-black ml-1"><?= htmlspecialchars($site_city) ?></span>
    </div>

    <h1 class="font-syne font-black text-white leading-[1.04] tracking-tight mb-5"
        style="font-size:clamp(36px,6.5vw,70px);letter-spacing:-2.5px;animation:fadeUp .7s .1s ease both;">
      Shop Local.<br>
      <span style="background:linear-gradient(135deg,#c8a97e 20%,#f0d9b0 60%,#c8a97e 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
        Support <?= htmlspecialchars($site_city) ?>.
      </span>
    </h1>

    <p class="text-white/50 max-w-[540px] mx-auto mb-12 leading-[1.75]"
       style="font-size:clamp(15px,1.8vw,18px);animation:fadeUp .7s .2s ease both;">
      Discover stores from your neighbourhood — groceries, medicines, sweets, and more.
      Order online and get delivered right to your door.
    </p>

    <div class="max-w-[640px] mx-auto" style="animation:fadeUp .7s .3s ease both;">
      <form action="" method="GET">
        <div class="flex items-center bg-white rounded-full px-5 py-1.5 gap-3 shadow-[0_16px_56px_rgba(0,0,0,.4),0_0_0_1px_rgba(200,169,126,.18)]">
          <i class="bi bi-search text-dark/30 text-base flex-shrink-0"></i>
          <input type="text" name="q"
                 placeholder="Search shops, products, areas..."
                 value="<?= htmlspecialchars($search) ?>"
                 autocomplete="off"
                 class="flex-1 border-none outline-none bg-transparent text-dark text-[15px] py-2 min-w-0 placeholder:text-dark/30">
          <button type="submit"
                  class="flex-shrink-0 flex items-center gap-2 bg-dark text-gold font-bold text-sm rounded-full px-5 py-2.5 transition-all duration-200 hover:bg-gold hover:text-dark whitespace-nowrap">
            <i class="bi bi-search"></i> <span class="hidden sm:inline">Search</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="relative z-10 w-full mt-14 grid grid-cols-2 sm:grid-cols-4 border-t border-gold/10 bg-gold/[0.04]">
    <?php
    $stats = [[$total_shops,'Local Shops'],[$total_products,'Products'],[$total_orders,'Orders'],[$total_cities,'Areas']];
    foreach ($stats as $i => [$num, $label]):
    ?>
    <div class="stat-box text-center py-6 border-r border-gold/[0.08] <?= $i===3?'border-r-0':'' ?>">
      <div class="font-syne font-black text-gold text-3xl leading-none"><?= $num ?>+</div>
      <div class="text-[11px] text-white/30 mt-1 uppercase tracking-wide"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- MARQUEE -->
<div class="bg-gold overflow-hidden py-3">
  <div class="marquee-track">
    <?php
    $tags = ['Groceries','Medicines','Sweets','Dairy','Dry Fruits','Snacks','Health & Wellness','Beverages','Rice & Grains','Personal Care','Chocolates','Ghee & Oils','Bakery','Stationery','Clothing'];
    foreach (array_merge($tags,$tags) as $t):
    ?>
    <div class="flex items-center gap-2 text-xs font-bold text-dark uppercase tracking-wide">
      <i class="bi bi-dot text-dark/50 text-base"></i><?= htmlspecialchars($t) ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- SHOPS SECTION -->
<div class="max-w-[1300px] mx-auto px-5 sm:px-8 py-16 pb-24">

  <!-- Section header -->
  <div class="mb-8 anim">
    <div class="flex items-center gap-2 text-[11px] font-bold tracking-[2px] uppercase text-gold mb-2">
      <span class="inline-block w-5 h-0.5 bg-gold rounded-full"></span>Discover
    </div>
    <div class="font-syne font-black tracking-tight" style="font-size:clamp(22px,2.5vw,30px);">
      <?php if ($search || $city): ?>
        <?= $city ? htmlspecialchars($city).' Shops' : 'Search Results' ?>
      <?php else: ?>
        Shops in <?= htmlspecialchars($site_city) ?>
      <?php endif; ?>
    </div>
    <div class="text-sm text-dark/50 mt-1">
      <?php if ($search || $city): ?>
        <?= count($shops) ?> result<?= count($shops)!=1?'s':'' ?>
        <?= $search ? " for \"<strong>".htmlspecialchars($search)."</strong>\"" : '' ?>
        &nbsp;—&nbsp; <a href="/" class="text-gold font-semibold">Clear</a>
      <?php else: ?>
        <?= count($shops) ?> shop<?= count($shops)!=1?'s':'' ?> available near you
      <?php endif; ?>
    </div>
  </div>

  <!-- City chips -->
  <?php if (!empty($cities)): ?>
  <div class="chips-scroll mb-10 anim">
    <a href="/"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold border-[1.5px] flex-shrink-0 transition-all duration-150 shadow-sm
              <?= !$city ? 'bg-dark text-gold border-dark' : 'bg-white text-dark border-dark/10 hover:bg-dark hover:text-gold hover:border-dark' ?>">
      <i class="bi bi-grid-fill text-xs"></i> All Areas
      <span class="text-[11px] font-bold px-2 py-0.5 rounded-full <?= !$city?'bg-gold/25':'bg-dark/[0.07]' ?>"><?= $total_shops ?></span>
    </a>
    <?php foreach ($cities as $c): ?>
    <a href="?city=<?= urlencode($c['city']) ?><?= $search?'&q='.urlencode($search):'' ?>"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold border-[1.5px] flex-shrink-0 transition-all duration-150 shadow-sm
              <?= $city===$c['city'] ? 'bg-dark text-gold border-dark' : 'bg-white text-dark border-dark/10 hover:bg-dark hover:text-gold hover:border-dark' ?>">
      <i class="bi bi-geo-alt text-xs"></i><?= htmlspecialchars($c['city']) ?>
      <span class="text-[11px] font-bold px-2 py-0.5 rounded-full <?= $city===$c['city']?'bg-gold/25':'bg-dark/[0.07]' ?>"><?= $c['cnt'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Shop cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($shops)): ?>
    <div class="col-span-full text-center py-20">
      <div class="text-6xl text-gold/40 mb-4"><i class="bi bi-shop-window"></i></div>
      <h3 class="font-syne font-black text-xl mb-2">No shops found</h3>
      <p class="text-sm text-dark/50">Try a different area or search term.</p>
    </div>
    <?php else:
      foreach ($shops as $i => $s):
        $logo   = $s['logo']   ?? null;
        $banner = $s['banner'] ?? null;
        $desc   = trim($s['description'] ?? '');
        $sname  = $s['name'];
        $scity  = $s['city']   ?? null;
        $saddr  = $s['address']?? null;
        $initial= strtoupper(mb_substr($sname,0,1));
        $grad   = $fallback_grads[$i % count($fallback_grads)];
        $pcolor = $s['theme_primary'] ?? '#c8a97e';
    ?>
    <a href="shop/index.php?shop=<?= urlencode($s['slug']) ?>"
       class="shop-card relative flex flex-col bg-white rounded-[20px] overflow-hidden border border-dark/[0.09] shadow-card transition-all duration-[280ms] hover:-translate-y-2 hover:scale-[1.01] hover:shadow-hover">

      <!-- Banner top -->
      <div class="relative overflow-hidden flex items-end p-5 flex-shrink-0" style="height:152px;">
        <?php if ($banner): ?>
          <img src="assets/uploads/banners/<?= htmlspecialchars($banner) ?>"
               class="shop-banner-img absolute inset-0 w-full h-full object-cover transition-transform duration-300" alt="">
          <div class="absolute inset-0" style="background:linear-gradient(175deg,rgba(0,0,0,.25),rgba(0,0,0,.55));"></div>
        <?php else: ?>
          <div class="absolute inset-0" style="background:linear-gradient(135deg,<?= $grad[0] ?>,<?= $grad[1] ?>);"></div>
          <div class="absolute inset-0" style="background-image:radial-gradient(circle,#c8a97e 1px,transparent 1px);background-size:22px 22px;opacity:.055;"></div>
          <div class="absolute inset-0" style="background:radial-gradient(ellipse 80% 70% at 50% 120%,rgba(200,169,126,.3),transparent);"></div>
        <?php endif; ?>

        <?php if ($scity): ?>
        <div class="absolute top-3.5 right-3.5 z-10 flex items-center gap-1 bg-black/40 backdrop-blur-md border border-white/15 text-white px-2.5 py-1 rounded-full text-[11px] font-bold">
          <i class="bi bi-geo-alt-fill text-[10px]"></i><?= htmlspecialchars($scity) ?>
        </div>
        <?php endif; ?>

        <div class="shop-logo-wrap relative z-10 w-[58px] h-[58px] rounded-[15px] bg-white border-[2.5px] border-white/85 overflow-hidden flex items-center justify-center flex-shrink-0 shadow-[0_6px_22px_rgba(0,0,0,.28)] transition-transform duration-200">
          <?php if ($logo): ?>
            <img src="assets/uploads/logos/<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($sname) ?>" class="w-full h-full object-cover">
          <?php else: ?>
            <span class="font-syne font-black text-[23px]" style="color:<?= htmlspecialchars($pcolor) ?>;"><?= $initial ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Body -->
      <div class="flex flex-col gap-2 px-5 py-5 flex-1">
        <div class="font-syne font-black text-[17.5px] leading-tight"><?= htmlspecialchars($sname) ?></div>
        <?php if ($desc): ?>
        <div class="text-[13.5px] text-dark/50 leading-relaxed flex-1" style="-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;">
          <?= htmlspecialchars($desc) ?>
        </div>
        <?php else: ?>
        <div class="text-[13.5px] italic text-dark/30 flex-1">Local shop in <?= htmlspecialchars($scity ?: $site_city) ?></div>
        <?php endif; ?>
        <?php if ($saddr): ?>
        <div class="flex items-start gap-1.5 text-[12.5px] text-dark/45 leading-snug mt-1">
          <i class="bi bi-geo-alt-fill text-gold flex-shrink-0 mt-0.5"></i><?= htmlspecialchars($saddr) ?>
        </div>
        <?php elseif ($scity): ?>
        <div class="flex items-center gap-1.5 text-[12.5px] text-dark/45 mt-1">
          <i class="bi bi-geo-alt text-gold"></i><?= htmlspecialchars($scity) ?><?= $s['state'] ? ', '.htmlspecialchars($s['state']) : '' ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between px-5 py-3 border-t border-dark/[0.09] bg-dark/[0.018]">
        <div class="flex items-center gap-3 text-[12px] text-dark/50">
          <?php if ($s['product_count']>0): ?><span class="flex items-center gap-1"><i class="bi bi-box-seam"></i><?= $s['product_count'] ?> products</span><?php endif; ?>
          <?php if ($s['order_count']>0):   ?><span class="flex items-center gap-1"><i class="bi bi-bag-check"></i><?= $s['order_count'] ?>+ orders</span><?php endif; ?>
        </div>
        <div class="shop-visit-btn flex items-center gap-1.5 text-[13px] font-bold px-4 py-1.5 rounded-full bg-dark text-gold transition-all duration-150">
          Visit <i class="bi bi-arrow-right"></i>
        </div>
      </div>
    </a>
    <?php endforeach; endif; ?>
  </div>

  <!-- How It Works -->
  <div class="mt-24">
    <div class="mb-8 anim">
      <div class="flex items-center gap-2 text-[11px] font-bold tracking-[2px] uppercase text-gold mb-2">
        <span class="inline-block w-5 h-0.5 bg-gold rounded-full"></span>Simple Steps
      </div>
      <div class="font-syne font-black tracking-tight" style="font-size:clamp(22px,2.5vw,30px);">How It Works</div>
      <div class="text-sm text-dark/50 mt-1">Shop from your favourite local stores in 3 easy steps</div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php
      $steps = [
        ['01','bi-search',   'Find Your Shop', "Browse local shops in {$site_city} by area, category, or search by name."],
        ['02','bi-bag-plus', 'Add to Cart',    'Pick your favourite products and add them to your cart with a single tap.'],
        ['03','bi-truck',    'Get Delivered',  'Place your order and get it delivered right to your doorstep, fast and fresh.'],
        ['04','bi-heart',    'Support Local',  'Every order directly supports a local business owner in your community.'],
      ];
      foreach ($steps as $step):
      ?>
      <div class="how-card relative bg-white border-[1.5px] border-dark/10 rounded-[18px] p-7 overflow-hidden transition-all duration-200 hover:border-gold/50 hover:shadow-lg hover:-translate-y-1 anim">
        <div class="font-syne font-black text-4xl text-gold/20 leading-none mb-3"><?= $step[0] ?></div>
        <div class="w-12 h-12 rounded-[13px] bg-gold/10 flex items-center justify-center text-gold text-xl mb-4">
          <i class="bi <?= $step[1] ?>"></i>
        </div>
        <div class="font-syne font-black text-base mb-2"><?= $step[2] ?></div>
        <div class="text-[13.5px] text-dark/50 leading-relaxed"><?= htmlspecialchars($step[3]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Register CTA -->
  <div class="relative bg-dark rounded-[20px] overflow-hidden px-8 sm:px-14 py-16 text-center mt-20 anim">
    <div class="absolute inset-0 pointer-events-none"
         style="background:radial-gradient(ellipse 70% 60% at 50% -10%,rgba(200,169,126,.22),transparent 60%),radial-gradient(ellipse 40% 50% at 10% 90%,rgba(200,169,126,.07),transparent 55%),radial-gradient(ellipse 40% 50% at 90% 90%,rgba(200,169,126,.07),transparent 55%);"></div>
    <div class="relative z-10">
      <h2 class="font-syne font-black text-white leading-tight mb-4" style="font-size:clamp(26px,4vw,40px);letter-spacing:-1.5px;">
        Own a Shop in
        <span style="background:linear-gradient(135deg,#c8a97e,#f0d9b0);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"><?= htmlspecialchars($site_city) ?></span>?<br>
        Start Selling Online Today.
      </h2>
      <p class="text-white/45 max-w-md mx-auto mb-9 text-base leading-relaxed">
        Set up your branded online store in minutes. Reach customers across <?= htmlspecialchars($site_city) ?> and grow your business.
      </p>
      <a href="owner/register.php"
         class="inline-flex items-center gap-2 px-8 py-4 rounded-full font-bold text-[15px] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl"
         style="background:#c8a97e;color:#1a1208;box-shadow:0 8px 28px rgba(200,169,126,.35);">
        <i class="bi bi-shop"></i> Register Your Shop — Free
      </a>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer class="bg-[#0d0903] px-8 sm:px-10 pt-11 pb-7">
  <div class="flex flex-wrap items-start justify-between gap-6 pb-7 border-b border-white/[0.06] mb-6">
    <div>
      <div class="font-syne font-black text-gold text-xl flex items-center gap-2 mb-2">
        <i class="bi bi-bag-heart-fill"></i><?= htmlspecialchars($site_name) ?>
      </div>
      <p class="text-[13px] text-white/25 max-w-[220px] leading-relaxed">
        Shop local. Support <?= htmlspecialchars($site_city) ?>. Delivered to your door.
      </p>
    </div>
    <div>
      <div class="text-[11.5px] font-bold tracking-wider uppercase text-white/20 mb-3">For Shops</div>
      <a href="owner/register.php" class="text-[13.5px] text-white/30 hover:text-gold transition-colors block">
        <i class="bi bi-shop mr-1.5"></i>Register Your Shop
      </a>
    </div>
    <div>
      <div class="text-[11.5px] font-bold tracking-wider uppercase text-white/20 mb-3">Platform</div>
      <a href="#" class="text-[13.5px] text-white/30 hover:text-gold transition-colors block">Browse Shops</a>
    </div>
  </div>
  <div class="text-center text-[12.5px] flex items-center justify-center gap-1.5 flex-wrap text-white/30">
    <span>Made with</span><span class="text-red-400">♥</span><span>by</span>
    <strong class="text-white/50">SM Tech</strong><span class="mx-1">·</span>
    <span><?= htmlspecialchars($site_name) ?> © <?= date('Y') ?></span>
    <span class="mx-1">·</span><span><?= htmlspecialchars($site_city) ?></span>
  </div>
</footer>

<script>
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('shadow-[0_4px_28px_rgba(26,18,8,0.08)]', window.scrollY > 20);
}, { passive: true });

const observer = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
}, { threshold: 0.1 });
document.querySelectorAll('.anim').forEach(el => observer.observe(el));

(function() {
  const c = document.getElementById('heroParticles');
  if (!c) return;
  for (let i = 0; i < 32; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const s = 1 + Math.random() * 2;
    p.style.cssText = `left:${Math.random()*100}%;bottom:${Math.random()*30}%;width:${s}px;height:${s}px;--dur:${5+Math.random()*7}s;--delay:${Math.random()*8}s;`;
    c.appendChild(p);
  }
})();
</script>
</body>
</html>
