<?php
/**
 * TamizhMart — Invoice PDF
 * Usage: owner/invoice_pdf.php?order_id=7
 */
session_start();
require '../config/db.php';

// Auth check
if (empty($_SESSION['owner_id'])) {
    header('Location: login.php'); exit;
}

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) die('Invalid order ID');

$owner_id = (int)$_SESSION['owner_id'];

// ── Fetch order ───────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT o.*,
           u.name  AS customer_name,
           u.email AS customer_email,
           u.phone AS customer_phone,
           s.name    AS shop_name,
           s.logo    AS shop_logo,
           s.address AS shop_address,
           s.city    AS shop_city,
           s.state   AS shop_state,
           s.pincode AS shop_pincode
    FROM orders o
    JOIN users u  ON o.user_id  = u.id
    JOIN shops s  ON o.shop_id  = s.id
    WHERE o.id = ? AND s.owner_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $order_id, $owner_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) die('Order not found or access denied.');

$shop_id = (int)$order['shop_id'];

// ── Fetch shop settings (logo, gstin) ────────────────────────
$settings = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

// Logo comes from shops table (s.logo), not shop_settings
$shop_logo = $order['shop_logo'] ?? '';
$gstin     = $settings['gstin']  ?? '';

// ── Fetch order items ─────────────────────────────────────────
$stmt2 = $conn->prepare("
    SELECT oi.quantity, oi.price,
           p.name AS product_name,
           p.sku, p.hsn_code
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt2->bind_param('i', $order_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Helpers ───────────────────────────────────────────────────
function fmt($n) { return '₹' . number_format((float)$n, 2); }
function tax($net, $rate) { return round($net * $rate / 100, 2); }

// Read from shop settings
$tax_enabled = ($settings['tax_enabled'] ?? '0') === '1';
$cgst_rate   = $tax_enabled ? floatval($settings['cgst_rate'] ?? 9) : 0;
$sgst_rate   = $tax_enabled ? floatval($settings['sgst_rate'] ?? 9) : 0;
$tax_rate    = $cgst_rate;
$invoice_num   = 'TM-' . strtoupper(substr($order['shop_name'], 0, 3)) . '-' . date('y') . '-' . str_pad($order['shop_order_number'], 4, '0', STR_PAD_LEFT);
$order_date    = date('d.m.Y', strtotime($order['created_at']));

// Logo — stored in assets/uploads/logos/
$logo_src = '';
if ($shop_logo) {
    if (strpos($shop_logo, 'http') === 0) {
        $logo_src = $shop_logo; // external URL
    } else {
        $path = '../assets/uploads/logos/' . $shop_logo;
        if (file_exists($path)) $logo_src = $path;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= htmlspecialchars($invoice_num) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial,Helvetica,sans-serif; font-size:9pt; color:#000; background:#f0f0f0; }
.print-bar { text-align:center; padding:12px; background:#fff; border-bottom:1px solid #ddd; }
.print-bar button { padding:8px 22px; border:none; border-radius:5px; font-size:14px; cursor:pointer; margin:0 4px; }
.btn-print { background:#198754; color:#fff; }
.btn-back  { background:#6c757d; color:#fff; }
.page {
    width:148mm; min-height:210mm;
    margin:16px auto; padding:7mm 8mm 6mm;
    background:#fff;
    box-shadow:0 2px 12px rgba(0,0,0,0.15);
}
@media print {
    body { background:#fff; }
    .print-bar { display:none !important; }
    .page { margin:0; box-shadow:none; width:100%; }
    @page { size:A5 portrait; margin:5mm; }
}
/* Header */
.hdr { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; }
.shop-logo { width:22mm; height:22mm; object-fit:contain; border:1px solid #ccc; }
.logo-box  { width:22mm; height:22mm; background:#eee; border:1px solid #ccc; display:flex; align-items:center; justify-content:center; font-size:7pt; color:#999; text-align:center; }
.sold-by   { font-weight:bold; font-size:8pt; }
.shop-nm   { font-size:9pt; margin-top:1px; }
.shop-addr { font-size:7.5pt; color:#333; margin-top:1px; line-height:1.4; }
/* Title */
.inv-title { text-align:center; font-size:10pt; font-weight:bold; border-top:1.5px solid #000; border-bottom:1.5px solid #000; padding:3px 0; margin:4px 0 2px; }
.orig-label { text-align:right; font-size:7.5pt; color:#555; margin-bottom:3px; }
/* Address */
.addr-row { display:flex; gap:5px; margin-bottom:3px; }
.addr-box { flex:1; border:1px solid #ccc; padding:4px 5px; font-size:7.5pt; min-height:24mm; line-height:1.4; }
.addr-lbl { font-weight:bold; font-size:8pt; margin-bottom:2px; }
/* Meta */
.meta { width:100%; border-collapse:collapse; font-size:8pt; margin-bottom:3px; }
.meta td { padding:2px 4px; border:1px solid #ccc; }
.meta .lbl { font-weight:bold; white-space:nowrap; background:#f8f8f8; }
/* Items */
.items { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:4px; }
.items th, .items td { border:1px solid #000; padding:2px 3px; vertical-align:top; }
.items thead th { background:#000; color:#fff; text-align:center; font-size:7pt; }
.items .r { text-align:right; }
.items .c { text-align:center; }
.items .l { text-align:left; }
.items tbody tr:nth-child(even) { background:#fafafa; }
.subtotal td { font-weight:bold; background:#f0f0f0; border-top:2px solid #000; }
.grandtotal td { font-weight:bold; background:#000; color:#fff; border-top:2px solid #000; font-size:8.5pt; }
/* Tax summary */
.tax-sum { width:55%; margin-left:auto; margin-top:5px; border-collapse:collapse; font-size:7.5pt; }
.tax-sum th, .tax-sum td { border:1px solid #ccc; padding:2px 5px; }
.tax-sum th { background:#f0f0f0; }
.tax-sum .r { text-align:right; }
/* Footer */
.inv-foot { text-align:right; font-size:7pt; color:#888; margin-top:5px; border-top:1px solid #ddd; padding-top:3px; }
</style>
</head>
<body>

<div class="print-bar">
    <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
    <button class="btn-back" onclick="window.location.href='orders.php'">&#x2190; Back to Orders</button>
</div>

<div class="page">

    <!-- Header -->
    <div class="hdr">
        <div>
            <div class="sold-by">Sold By :</div>
            <div class="shop-nm"><?= htmlspecialchars($order['shop_name']) ?></div>
            <div class="shop-addr">
                <?= htmlspecialchars($order['shop_address'] ?? '') ?>
                <?php if ($order['shop_city']): ?>, <?= htmlspecialchars($order['shop_city']) ?><?php endif; ?>
                <?php if ($order['shop_state']): ?><br><?= htmlspecialchars($order['shop_state']) ?><?php endif; ?>
                <?php if ($order['shop_pincode']): ?> - <?= htmlspecialchars($order['shop_pincode']) ?><?php endif; ?>
                <?php if ($gstin): ?><br>GSTIN: <?= htmlspecialchars($gstin) ?><?php endif; ?>
            </div>
        </div>
        <?php if ($logo_src): ?>
        <img src="<?= $logo_src ?>" class="shop-logo" alt="Logo">
        <?php else: ?>
        <div class="logo-box">SHOP<br>LOGO</div>
        <?php endif; ?>
    </div>

    <!-- Title -->
    <div class="inv-title">Tax Invoice / Bill of Supply / Cash Memo</div>
    <div class="orig-label">(Original for Recipient)</div>

    <!-- Addresses -->
    <div class="addr-row">
        <div class="addr-box">
            <div class="addr-lbl">Billing Address :</div>
            <?= htmlspecialchars($order['customer_name']) ?><br>
            <?= nl2br(htmlspecialchars($order['address'] ?? '')) ?><br>
            IN
            <?php if ($order['customer_phone']): ?><br>Ph: <?= htmlspecialchars($order['customer_phone']) ?><?php endif; ?>
        </div>
        <div class="addr-box">
            <div class="addr-lbl">Shipping Address :</div>
            <?= htmlspecialchars($order['customer_name']) ?><br>
            <?= nl2br(htmlspecialchars($order['address'] ?? '')) ?><br>
            IN
        </div>
    </div>

    <!-- Order Meta -->
    <table class="meta">
        <tr>
            <td class="lbl">Order No:</td>
            <td>#<?= str_pad($order['shop_order_number'], 4, '0', STR_PAD_LEFT) ?></td>
            <td class="lbl">Invoice No:</td>
            <td><?= htmlspecialchars($invoice_num) ?></td>
        </tr>
        <tr>
            <td class="lbl">Order Date:</td>
            <td><?= $order_date ?></td>
            <td class="lbl">Invoice Date:</td>
            <td><?= $order_date ?></td>
        </tr>
        <tr>
            <td class="lbl">Payment:</td>
            <td><?= strtoupper($order['payment_method'] ?? 'COD') ?></td>
            <td class="lbl">Status:</td>
            <td><?= ucfirst($order['status'] ?? '') ?></td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items">
        <thead>
            <tr>
                <th style="width:4%">Sl.</th>
                <th style="<?= $tax_enabled ? 'width:28%' : 'width:42%' ?>;text-align:left;">Description</th>
                <th style="width:12%">Unit Price</th>
                <th style="width:5%">Qty</th>
                <th style="width:13%">Net Amt</th>
                <?php if ($tax_enabled): ?>
                <th style="width:8%">CGST <?= $cgst_rate ?>%</th>
                <th style="width:8%">SGST <?= $sgst_rate ?>%</th>
                <?php endif; ?>
                <th style="width:13%">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sl = 1; $g_net = 0; $g_cgst = 0; $g_sgst = 0; $g_total = 0;
        foreach ($items as $item):
            $up   = floatval($item['price']);
            $qty  = intval($item['quantity']);
            $net  = $up * $qty;
            $cgst = tax($net, $tax_rate);
            $sgst = tax($net, $tax_rate);
            $tot  = $net + $cgst + $sgst;
            $g_net += $net; $g_cgst += $cgst; $g_sgst += $sgst; $g_total += $tot;
        ?>
        <tr>
            <td class="c"><?= $sl++ ?></td>
            <td class="l">
                <?= htmlspecialchars($item['product_name']) ?>
                <?php if (!empty($item['sku'])): ?>
                <br><small style="color:#666">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                <?php endif; ?>
                <?php if (!empty($item['hsn_code'])): ?>
                <br><small style="color:#666">HSN: <?= htmlspecialchars($item['hsn_code']) ?></small>
                <?php endif; ?>
            </td>
            <td class="r"><?= fmt($up) ?></td>
            <td class="c"><?= $qty ?></td>
            <td class="r"><?= fmt($net) ?></td>
            <?php if ($tax_enabled): ?>
            <td class="r"><?= fmt($cgst) ?></td>
            <td class="r"><?= fmt($sgst) ?></td>
            <?php endif; ?>
            <td class="r"><?= fmt($tot) ?></td>
        </tr>
        <?php endforeach; ?>

        <?php
        $ship = floatval($order['shipping_fee'] ?? 0);
        if ($ship > 0):
            $sc = tax($ship, $tax_rate); $ss = tax($ship, $tax_rate); $st = $ship + $sc + $ss;
            $g_net += $ship; $g_cgst += $sc; $g_sgst += $ss; $g_total += $st;
        ?>
        <tr>
            <td class="c"><?= $sl++ ?></td>
            <td class="l">Shipping Charges</td>
            <td class="r"><?= fmt($ship) ?></td>
            <td class="c">1</td>
            <td class="r"><?= fmt($ship) ?></td>
            <td class="c"><?= $tax_rate ?>%</td>
            <td class="r"><?= fmt($sc) ?></td>
            <td class="r"><?= fmt($ss) ?></td>
            <td class="r"><?= fmt($st) ?></td>
        </tr>
        <?php endif; ?>

        <tr class="subtotal">
            <?php if ($tax_enabled): ?>
            <td colspan="4" style="text-align:right">Sub Total</td>
            <td class="r"><?= fmt($g_net) ?></td>
            <td class="r"><?= fmt($g_cgst) ?></td>
            <td class="r"><?= fmt($g_sgst) ?></td>
            <td class="r"><?= fmt($g_total) ?></td>
            <?php else: ?>
            <td colspan="4" style="text-align:right">Sub Total</td>
            <td class="r"><?= fmt($g_net) ?></td>
            <td class="r"><?= fmt($g_total) ?></td>
            <?php endif; ?>
        </tr>
        <tr class="grandtotal">
            <?php $grand_cols = $tax_enabled ? 7 : 5; ?>
            <td colspan="<?= $grand_cols ?>" style="text-align:right;padding-right:6px;">GRAND TOTAL</td>
            <td class="r"><?= fmt(floatval($order['total_amount'])) ?></td>
        </tr>
        </tbody>
    </table>

    <!-- Tax Summary — only shown when tax is enabled -->
    <?php if ($tax_enabled): ?>
    <table class="tax-sum">
        <tr><th style="text-align:left;">Tax</th><th class="r">Rate</th><th class="r">Amount</th></tr>
        <tr><td>CGST</td><td class="r"><?= $cgst_rate ?>%</td><td class="r"><?= fmt($g_cgst) ?></td></tr>
        <tr><td>SGST</td><td class="r"><?= $sgst_rate ?>%</td><td class="r"><?= fmt($g_sgst) ?></td></tr>
        <tr style="font-weight:bold;background:#f8f8f8;">
            <td>Total Tax</td><td class="r"><?= $cgst_rate+$sgst_rate ?>%</td><td class="r"><?= fmt($g_cgst+$g_sgst) ?></td>
        </tr>
    </table>
    <?php else: ?>
    <div style="font-size:7.5pt;color:#888;margin-top:5px;text-align:right;">No GST applicable</div>
    <?php endif; ?>

    <div class="inv-foot">Page 1 of 1 &nbsp;|&nbsp; Generated by TamizhMart &nbsp;|&nbsp; SM Tech</div>

</div>

<script>
if (new URLSearchParams(location.search).get('print') === '1') {
    window.onload = () => setTimeout(window.print, 400);
}
</script>
</body>
</html>