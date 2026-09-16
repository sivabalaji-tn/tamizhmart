<?php
// ── This script is made by Siva Balaji sms ──────────────────────
// Handler Invoice — adapted from owner/invoice_pdf.php
session_start();
require '../config/db.php';

if (empty($_SESSION['handler_id'])) {
    header('Location: login.php'); exit;
}

$order_id        = intval($_GET['order_id'] ?? 0);
$handler_shop_id = (int)$_SESSION['handler_shop_id'];
if (!$order_id) die('Invalid order ID');

// Fetch order — verify it belongs to handler's shop
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
    WHERE o.id = ? AND o.shop_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $order_id, $handler_shop_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) die('Order not found or access denied.');

$shop_id = (int)$order['shop_id'];

// Fetch shop settings
$settings = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

$shop_logo   = $order['shop_logo'] ?? '';
$gstin       = $settings['gstin']  ?? '';

// Fetch order items
$stmt2 = $conn->prepare("
    SELECT oi.quantity, oi.price, p.name AS product_name, p.sku, p.hsn_code
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt2->bind_param('i', $order_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

function fmt($n) { return '₹' . number_format((float)$n, 2); }
function tax($net, $rate) { return round($net * $rate / 100, 2); }

$tax_enabled   = ($settings['tax_enabled'] ?? '0') === '1';
$cgst_rate     = $tax_enabled ? floatval($settings['cgst_rate'] ?? 9) : 0;
$sgst_rate     = $tax_enabled ? floatval($settings['sgst_rate'] ?? 9) : 0;
$invoice_num   = 'TM-' . strtoupper(substr($order['shop_name'], 0, 3)) . '-' . date('y') . '-' . str_pad($order['shop_order_number'], 4, '0', STR_PAD_LEFT);
$order_date    = date('d.m.Y', strtotime($order['created_at']));

$logo_src = '';
if ($shop_logo) {
    if (strpos($shop_logo, 'http') === 0) { $logo_src = $shop_logo; }
    else { $path = '../assets/uploads/logos/' . $shop_logo; if (file_exists($path)) $logo_src = $path; }
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
.print-bar { text-align:center; padding:12px; background:#fff; border-bottom:1px solid #ddd; display:flex; align-items:center; justify-content:center; gap:10px; }
.print-bar button { padding:8px 22px; border:none; border-radius:5px; font-size:14px; cursor:pointer; }
.btn-print  { background:#198754; color:#fff; }
.btn-back   { background:#6c757d; color:#fff; }
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
.hdr { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; }
.shop-logo { width:22mm; height:22mm; object-fit:contain; border:1px solid #ccc; }
.logo-box  { width:22mm; height:22mm; background:#eee; border:1px solid #ccc; display:flex; align-items:center; justify-content:center; font-size:7pt; color:#999; text-align:center; }
.sold-by   { font-weight:bold; font-size:8pt; }
.shop-nm   { font-size:9pt; margin-top:1px; }
.shop-addr { font-size:7.5pt; color:#333; margin-top:1px; line-height:1.4; }
.inv-title { text-align:center; font-size:13pt; font-weight:bold; margin:4px 0 2px; text-transform:uppercase; letter-spacing:1px; }
.divider   { border:none; border-top:1.5px solid #000; margin:3px 0; }
.thin-div  { border:none; border-top:0.5px solid #ccc; margin:2px 0; }
.meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:2mm; font-size:7.5pt; margin:3px 0; }
.meta-label { color:#555; font-size:7pt; }
.meta-val   { font-weight:bold; }
.bill-section { display:grid; grid-template-columns:1fr 1fr; gap:3mm; margin:3mm 0; font-size:7.5pt; }
.bill-box   { border:0.5px solid #ccc; border-radius:3px; padding:2mm; }
.bill-title { font-weight:bold; font-size:7pt; text-transform:uppercase; color:#555; margin-bottom:2px; }
table.items { width:100%; border-collapse:collapse; margin:3mm 0; font-size:7.5pt; }
table.items th { background:#f5f5f5; font-weight:bold; font-size:7pt; text-transform:uppercase; padding:2mm 1.5mm; border:0.5px solid #ddd; text-align:left; }
table.items td { padding:2mm 1.5mm; border:0.5px solid #eee; vertical-align:top; }
table.items tr:nth-child(even) td { background:#fafafa; }
.right { text-align:right; }
.center { text-align:center; }
.totals { margin-top:2mm; }
.totals table { width:100%; border-collapse:collapse; }
.totals td { padding:1.5mm 1.5mm; font-size:8pt; }
.totals .label { text-align:right; color:#555; padding-right:3mm; }
.totals .value { text-align:right; font-weight:bold; min-width:22mm; }
.total-row td { font-size:10pt; font-weight:bold; border-top:1.5px solid #000; }
.footer-note { text-align:center; font-size:7pt; color:#666; margin-top:4mm; line-height:1.5; }
.badge-cod  { background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; padding:1px 5px; border-radius:3px; font-size:7pt; font-weight:bold; }
.badge-paid { background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; padding:1px 5px; border-radius:3px; font-size:7pt; font-weight:bold; }
</style>
</head>
<body>
<div class="print-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
    <button class="btn-back"  onclick="window.close()">✕ Close</button>
</div>

<div class="page">
    <!-- Header -->
    <div class="hdr">
        <div>
            <div class="sold-by">Sold by</div>
            <div class="shop-nm"><?= htmlspecialchars($order['shop_name']) ?></div>
            <div class="shop-addr">
                <?= htmlspecialchars($order['shop_address'] ?? '') ?>
                <?php if ($order['shop_city']): ?><br><?= htmlspecialchars($order['shop_city']) ?><?php endif; ?>
                <?php if ($order['shop_state']): ?>, <?= htmlspecialchars($order['shop_state']) ?><?php endif; ?>
                <?php if ($order['shop_pincode']): ?> – <?= htmlspecialchars($order['shop_pincode']) ?><?php endif; ?>
                <?php if ($gstin): ?><br>GSTIN: <?= htmlspecialchars($gstin) ?><?php endif; ?>
            </div>
        </div>
        <div>
            <?php if ($logo_src): ?>
            <img src="<?= htmlspecialchars($logo_src) ?>" class="shop-logo" alt="Logo">
            <?php else: ?>
            <div class="logo-box">No Logo</div>
            <?php endif; ?>
        </div>
    </div>

    <hr class="divider">
    <div class="inv-title">Tax Invoice</div>
    <hr class="divider">

    <div class="meta-grid">
        <div><div class="meta-label">Invoice No.</div><div class="meta-val"><?= htmlspecialchars($invoice_num) ?></div></div>
        <div><div class="meta-label">Invoice Date</div><div class="meta-val"><?= $order_date ?></div></div>
        <div><div class="meta-label">Order No.</div><div class="meta-val">#<?= str_pad($order['shop_order_number'], 4, '0', STR_PAD_LEFT) ?></div></div>
        <div><div class="meta-label">Payment</div><div class="meta-val">
            <?php if ($order['payment_method'] === 'cod'): ?>
            <span class="badge-cod">Cash on Delivery</span>
            <?php else: ?>
            <span class="badge-paid">Online — Paid</span>
            <?php endif; ?>
        </div></div>
    </div>

    <hr class="thin-div">

    <div class="bill-section">
        <div class="bill-box">
            <div class="bill-title">Bill To</div>
            <div style="font-weight:bold;"><?= htmlspecialchars($order['customer_name']) ?></div>
            <?php if ($order['customer_phone']): ?><div><?= htmlspecialchars($order['customer_phone']) ?></div><?php endif; ?>
            <?php if ($order['customer_email']): ?><div style="font-size:7pt;color:#555;"><?= htmlspecialchars($order['customer_email']) ?></div><?php endif; ?>
        </div>
        <div class="bill-box">
            <div class="bill-title">Ship To</div>
            <div style="line-height:1.45;"><?= nl2br(htmlspecialchars($order['address'])) ?></div>
        </div>
    </div>

    <hr class="thin-div">

    <!-- Items table -->
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <?php if ($tax_enabled): ?>
                <th class="right">HSN</th>
                <th class="right">Rate</th>
                <?php endif; ?>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <?php if ($tax_enabled): ?>
                <th class="right">CGST <?= $cgst_rate ?>%</th>
                <th class="right">SGST <?= $sgst_rate ?>%</th>
                <?php endif; ?>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $subtotal = 0; $total_cgst = 0; $total_sgst = 0;
        foreach ($items as $i => $item):
            $line = $item['price'] * $item['quantity'];
            $base = $tax_enabled ? round($line / (1 + ($cgst_rate + $sgst_rate) / 100), 2) : $line;
            $c    = $tax_enabled ? tax($base, $cgst_rate) : 0;
            $s    = $tax_enabled ? tax($base, $sgst_rate) : 0;
            $subtotal   += $line;
            $total_cgst += $c;
            $total_sgst += $s;
        ?>
        <tr>
            <td class="center"><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($item['product_name']) ?><?php if ($item['sku']): ?> <span style="color:#999;font-size:6.5pt;">[<?= htmlspecialchars($item['sku']) ?>]</span><?php endif; ?></td>
            <?php if ($tax_enabled): ?>
            <td class="right"><?= htmlspecialchars($item['hsn_code'] ?? '') ?></td>
            <td class="right"><?= fmt($item['price']) ?></td>
            <?php endif; ?>
            <td class="center"><?= $item['quantity'] ?></td>
            <td class="right"><?= fmt($item['price']) ?></td>
            <?php if ($tax_enabled): ?>
            <td class="right"><?= fmt($c) ?></td>
            <td class="right"><?= fmt($s) ?></td>
            <?php endif; ?>
            <td class="right"><?= fmt($line) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        <table>
            <?php if ($tax_enabled): ?>
            <tr><td class="label">Subtotal (excl. tax)</td><td class="value"><?= fmt($subtotal - $total_cgst - $total_sgst) ?></td></tr>
            <tr><td class="label">CGST (<?= $cgst_rate ?>%)</td><td class="value"><?= fmt($total_cgst) ?></td></tr>
            <tr><td class="label">SGST (<?= $sgst_rate ?>%)</td><td class="value"><?= fmt($total_sgst) ?></td></tr>
            <?php endif; ?>
            <?php if ($order['shipping_fee'] > 0): ?>
            <tr><td class="label">Shipping</td><td class="value"><?= fmt($order['shipping_fee']) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td class="label" style="font-size:10pt;">Grand Total</td><td class="value"><?= fmt($order['total_amount']) ?></td></tr>
        </table>
    </div>

    <?php if ($order['notes']): ?>
    <div style="margin-top:4mm;padding:2mm;border:0.5px solid #ddd;border-radius:3px;font-size:7.5pt;">
        <strong>Note:</strong> <?= htmlspecialchars($order['notes']) ?>
    </div>
    <?php endif; ?>

    <div class="footer-note">
        Thank you for shopping with us!<br>
        <?= htmlspecialchars($order['shop_name']) ?> · This is a computer-generated invoice.
    </div>
</div>
</body>
</html>
