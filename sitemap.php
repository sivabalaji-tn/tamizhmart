<?php
/**
 * TamizhMart — Dynamic XML Sitemap
 * Accessible at: https://tamizhmart.in/sitemap.php
 */
require "config/db.php";
header("Content-Type: application/xml; charset=UTF-8");

$base = "https://tamizhmart.in";

$shops   = $conn->query("SELECT slug, updated_at FROM shops WHERE is_active=1 AND is_suspended=0");
$products = $conn->query("SELECT p.id, p.shop_id, p.updated_at, s.slug FROM products p JOIN shops s ON p.shop_id=s.id WHERE p.is_active=1 AND s.is_active=1 AND s.is_suspended=0");

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

// Platform home
echo "<url><loc>{$base}/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>\n";

// Each shop storefront
while ($s = $shops->fetch_assoc()) {
    $loc = $base . "/shop/index.php?shop=" . urlencode($s["slug"]);
    $mod = date("Y-m-d", strtotime($s["updated_at"] ?? "now"));
    echo "<url><loc>" . htmlspecialchars($loc) . "</loc><lastmod>{$mod}</lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>\n";

    $loc2 = $base . "/shop/products.php?shop=" . urlencode($s["slug"]);
    echo "<url><loc>" . htmlspecialchars($loc2) . "</loc><lastmod>{$mod}</lastmod><changefreq>daily</changefreq><priority>0.8</priority></url>\n";
}

// Each product page
while ($p = $products->fetch_assoc()) {
    $loc = $base . "/shop/product.php?shop=" . urlencode($p["slug"]) . "&id=" . (int)$p["id"];
    $mod = date("Y-m-d", strtotime($p["updated_at"] ?? "now"));
    echo "<url><loc>" . htmlspecialchars($loc) . "</loc><lastmod>{$mod}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>\n";
}

echo "</urlset>";

