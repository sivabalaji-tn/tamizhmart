<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
if (!isset($_SESSION['owner_id'])) { header("Location: login.php"); exit; }

$shop_id   = $_SESSION['shop_id'];
$format    = $_GET['format'] ?? 'html';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to   = $_GET['to']   ?? date('Y-m-d');

// Full shop data for branding
$shop_stmt = $conn->prepare("SELECT * FROM shops WHERE id=?");
$shop_stmt->bind_param("i", $shop_id);
$shop_stmt->execute();
$shop = $shop_stmt->get_result()->fetch_assoc();

// Shop settings
$settings_map = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

$orders = $conn->query("
    SELECT o.id, o.shop_order_number, u.name as customer, u.email, u.phone,
           o.total_amount, o.status, o.payment_method, o.address, o.notes,
           o.created_at,
           GROUP_CONCAT(CONCAT(p.name, ' x', oi.quantity, ' @\u20b9', oi.price) SEPARATOR ' | ') as items
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.shop_id = $shop_id
      AND DATE(o.created_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

$filename = 'orders_' . preg_replace('/[^a-zA-Z0-9]/', '_', $shop['name']) . '_' . $date_from . '_to_' . $date_to;

// ── CSV Export ─────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #','Customer','Email','Phone','Items','Total (INR)','Status','Payment','Address','Notes','Date']);
    while ($row = $orders->fetch_assoc()) {
        fputcsv($out, [
            str_pad($row['shop_order_number'] ?? $row['id'], 4, '0', STR_PAD_LEFT),
            $row['customer'], $row['email'], $row['phone'] ?? '',
            $row['items'], $row['total_amount'],
            ucfirst(str_replace('_',' ',$row['status'])),
            strtoupper($row['payment_method']),
            $row['address'], $row['notes'] ?? '',
            date('d M Y H:i', strtotime($row['created_at']))
        ]);
    }
    fclose($out);
    exit;
}

// ── HTML / Print View ──────────────────────────────────────────
$rows = [];
while ($row = $orders->fetch_assoc()) $rows[] = $row;
$total_revenue = array_sum(array_column($rows, 'total_amount'));
$delivered     = count(array_filter($rows, fn($r) => $r['status'] === 'delivered'));
$pending       = count(array_filter($rows, fn($r) => $r['status'] === 'pending'));
$cancelled     = count(array_filter($rows, fn($r) => $r['status'] === 'cancelled'));

// Brand colors
$brand_primary   = !empty($shop['theme_primary'])   ? $shop['theme_primary']   : '#c8a97e';
$brand_secondary = !empty($shop['theme_secondary'])  ? $shop['theme_secondary'] : '#8b6428';
$brand_font      = !empty($shop['theme_font'])       ? $shop['theme_font']      : 'Syne';

// Logo
$logo_src = '';
if (!empty($shop['logo'])) {
    $logo_src = strpos($shop['logo'], 'http') === 0
        ? htmlspecialchars($shop['logo'])
        : '../assets/uploads/logos/' . htmlspecialchars($shop['logo']);
}

// Status map
$status_styles = [
    'delivered'        => ['bg'=>'#d1fae5','color'=>'#065f46','label'=>'Delivered'],
    'pending'          => ['bg'=>'#fef3c7','color'=>'#92400e','label'=>'Pending'],
    'confirmed'        => ['bg'=>'#dbeafe','color'=>'#1e40af','label'=>'Confirmed'],
    'processing'       => ['bg'=>'#e0e7ff','color'=>'#3730a3','label'=>'Processing'],
    'out_for_delivery' => ['bg'=>'#ede9fe','color'=>'#5b21b6','label'=>'Out for Delivery'],
    'cancelled'        => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'Cancelled'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Report &mdash; <?= htmlspecialchars($shop['name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= urlencode($brand_font) ?>:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap">
<style>
/* ── Reset ───────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --brand:        <?= $brand_primary ?>;
    --brand2:       <?= $brand_secondary ?>;
    --brand-pale:   color-mix(in srgb, <?= $brand_primary ?> 8%,  #fff);
    --brand-light:  color-mix(in srgb, <?= $brand_primary ?> 15%, #fff);
    --brand-rule:   color-mix(in srgb, <?= $brand_primary ?> 22%, #fff);
    --font-display: '<?= $brand_font ?>', sans-serif;
    --text:         #1a1a1a;
    --muted:        #6b7280;
    --border:       #e8e4de;
    --row-alt:      #faf9f7;
}

body {
    font-family: 'Inter', sans-serif;
    color: var(--text);
    background: #edecea;
    min-height: 100vh;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* ── Top action bar (screen only) ───────────────────────────── */
.action-bar {
    background: #0f0d0a;
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    position: sticky;
    top: 0;
    z-index: 200;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.action-bar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.5);
    font-size: 12.5px;
    font-weight: 500;
    text-decoration: none;
    padding: 6px 12px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 7px;
    transition: all .2s;
    letter-spacing: .2px;
}
.back-link:hover {
    color: #fff;
    border-color: rgba(255,255,255,0.25);
    background: rgba(255,255,255,0.05);
}
.action-title {
    color: #fff;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 15px;
    letter-spacing: -.2px;
}
.action-sub {
    color: rgba(255,255,255,0.35);
    font-size: 11.5px;
    margin-top: 1px;
}
.action-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
}
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all .2s;
    letter-spacing: .1px;
}
.btn-action-primary {
    background: var(--brand);
    color: #fff;
    box-shadow: 0 2px 12px color-mix(in srgb, var(--brand) 35%, transparent);
}
.btn-action-primary:hover {
    filter: brightness(1.08);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 20px color-mix(in srgb, var(--brand) 45%, transparent);
}
.btn-action-ghost {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.7);
    border: 1px solid rgba(255,255,255,0.1);
}
.btn-action-ghost:hover {
    background: rgba(255,255,255,0.11);
    color: #fff;
}

/* ── Paper ───────────────────────────────────────────────────── */
.paper-outer {
    max-width: 1020px;
    margin: 36px auto;
    padding: 0 20px 64px;
}
.report {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.04), 0 12px 40px rgba(0,0,0,0.1);
}

/* ── Report header ───────────────────────────────────────────── */
.rpt-header {
    background: linear-gradient(130deg, var(--brand2) 0%, var(--brand) 100%);
    padding: 32px 40px;
    position: relative;
    overflow: hidden;
}
/* Diagonal stripe texture */
.rpt-header::before {
    content: '';
    position: absolute; inset: 0;
    background-image: repeating-linear-gradient(
        -45deg,
        rgba(255,255,255,0.03) 0px,
        rgba(255,255,255,0.03) 1px,
        transparent 1px,
        transparent 12px
    );
}
/* Decorative circle */
.rpt-header::after {
    content: '';
    position: absolute;
    width: 340px; height: 340px;
    border-radius: 50%;
    border: 60px solid rgba(255,255,255,0.05);
    top: -130px; right: -80px;
    pointer-events: none;
}

.rpt-header-inner {
    position: relative; z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
}

/* Left: logo + shop name */
.rpt-brand {
    display: flex;
    align-items: center;
    gap: 20px;
}
.rpt-logo {
    width: 60px; height: 60px;
    border-radius: 12px;
    background: rgba(255,255,255,0.18);
    border: 1.5px solid rgba(255,255,255,0.3);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.rpt-logo img { width: 100%; height: 100%; object-fit: cover; }
.rpt-logo-init {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 26px;
    color: #fff;
    letter-spacing: -1px;
}
.rpt-shop-name {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 24px;
    color: #fff;
    letter-spacing: -.5px;
    line-height: 1.1;
}
.rpt-shop-desc {
    font-size: 12.5px;
    color: rgba(255,255,255,0.65);
    margin-top: 5px;
    font-weight: 400;
}

/* Right: report info box */
.rpt-info-box {
    background: rgba(255,255,255,0.13);
    border: 1px solid rgba(255,255,255,0.22);
    border-radius: 10px;
    padding: 16px 22px;
    text-align: right;
    backdrop-filter: blur(8px);
}
.rpt-info-label {
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.5);
    margin-bottom: 6px;
}
.rpt-info-count {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 22px;
    color: #fff;
    letter-spacing: -.5px;
}
.rpt-info-period {
    font-size: 11.5px;
    color: rgba(255,255,255,0.6);
    margin-top: 5px;
    font-weight: 400;
}

/* ── Summary strip ───────────────────────────────────────────── */
.summary-strip {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    border-bottom: 1px solid var(--border);
}
.summary-cell {
    padding: 20px 18px;
    text-align: center;
    border-right: 1px solid var(--border);
}
.summary-cell:last-child { border-right: none; }
.summary-icon-wrap {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: var(--brand-pale);
    border: 1px solid var(--brand-rule);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 10px;
    color: var(--brand2);
    font-size: 15px;
}
.summary-val {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 19px;
    color: var(--text);
    letter-spacing: -.5px;
    line-height: 1;
}
.summary-lbl {
    font-size: 10.5px;
    color: var(--muted);
    margin-top: 4px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .6px;
}

/* ── Meta bar ────────────────────────────────────────────────── */
.meta-bar {
    background: var(--brand-pale);
    border-bottom: 1px solid var(--brand-rule);
    padding: 10px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.meta-group { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.meta-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--muted);
}
.meta-item i { font-size: 12px; color: var(--brand); }
.meta-item strong { color: #333; font-weight: 600; }
.meta-sep { width: 1px; height: 14px; background: var(--border); }
.meta-powered {
    font-size: 10.5px;
    color: #bbb;
    letter-spacing: .3px;
}

/* ── Table ───────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}
thead tr {
    background: var(--row-alt);
    border-bottom: 2px solid var(--brand-rule);
}
th {
    padding: 11px 14px;
    text-align: left;
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--muted);
    white-space: nowrap;
}
th:first-child { padding-left: 32px; }
th:last-child  { padding-right: 32px; }

tbody tr { border-bottom: 1px solid #f2efe9; }
tbody tr:last-child { border-bottom: none; }
tbody tr:nth-child(even) td { background: var(--row-alt); }
tbody tr:hover td { background: color-mix(in srgb, var(--brand) 4%, #fff); }

td {
    padding: 12px 14px;
    vertical-align: top;
    color: #2d2d2d;
    line-height: 1.45;
}
td:first-child { padding-left: 32px; }
td:last-child  { padding-right: 32px; }

.order-num {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 13.5px;
    color: var(--brand2);
}
.customer-name { font-weight: 600; font-size: 13px; }
.customer-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; }
.items-text    { color: #555; max-width: 200px; line-height: 1.55; font-size: 11.5px; }
.total-amt {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 13.5px;
    color: var(--text);
    white-space: nowrap;
}
.date-main { font-weight: 500; font-size: 12.5px; }
.date-time { font-size: 11px; color: var(--muted); margin-top: 2px; }

.badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 9px;
    border-radius: 4px;
    white-space: nowrap;
    letter-spacing: .3px;
    text-transform: uppercase;
}
.badge-online { background: #dbeafe; color: #1e40af; }
.badge-cod    { background: #fef3c7; color: #92400e; }
.badge-status { border-radius: 4px; }

/* ── Report footer ───────────────────────────────────────────── */
.rpt-footer {
    border-top: 1.5px solid var(--brand-rule);
    background: var(--brand-pale);
    padding: 18px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.footer-left { }
.footer-shop {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 14px;
    color: var(--brand2);
}
.footer-period { font-size: 11.5px; color: var(--muted); margin-top: 2px; }
.footer-right { text-align: right; }
.footer-total-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 3px;
}
.footer-total-val {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 20px;
    color: var(--brand2);
    letter-spacing: -.5px;
}

/* ── Empty state ─────────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 72px 32px;
}
.empty-state i { font-size: 40px; color: #d0ccc6; display: block; margin-bottom: 16px; }
.empty-state h3 {
    font-family: var(--font-display);
    font-size: 18px;
    color: #555;
    margin-bottom: 8px;
    font-weight: 700;
}
.empty-state p { font-size: 13px; color: var(--muted); }

/* ── Print ───────────────────────────────────────────────────── */
@media print {
    body { background: #fff !important; }
    .action-bar { display: none !important; }
    .paper-outer { margin: 0; padding: 0; max-width: 100%; }
    .report { box-shadow: none; border-radius: 0; }
    tbody tr:hover td { background: inherit !important; }
    tbody tr:nth-child(even) td { background: #faf9f7 !important; }
    .rpt-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .summary-icon-wrap { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page {
        size: A4 landscape;
        margin: 10mm 12mm;
    }
    thead { display: table-header-group; }
    tbody tr { page-break-inside: avoid; }
    .summary-strip { break-inside: avoid; }
}

@media (max-width: 720px) {
    .summary-strip { grid-template-columns: repeat(3, 1fr); }
    .summary-cell:nth-child(n+4) { border-top: 1px solid var(--border); }
    .paper-outer { padding: 0 8px 40px; }
    .rpt-header { padding: 24px 20px; }
    .meta-bar { padding: 10px 16px; }
    td:first-child, th:first-child { padding-left: 16px; }
    td:last-child,  th:last-child  { padding-right: 16px; }
    .rpt-footer { padding: 16px; }
}
</style>
</head>
<body>

<!-- Action bar (screen only) -->
<div class="action-bar">
    <div class="action-bar-left">
        <a href="orders.php" onclick="window.location.href='orders.php'; return false;" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Orders
        </a>
        <div>
            <div class="action-title">Sales Report</div>
            <div class="action-sub"><?= htmlspecialchars($shop['name']) ?> &nbsp;&middot;&nbsp; <?= date('d M Y', strtotime($date_from)) ?> &ndash; <?= date('d M Y', strtotime($date_to)) ?></div>
        </div>
    </div>
    <div class="action-buttons">
        <a href="?from=<?= $date_from ?>&to=<?= $date_to ?>&format=csv" class="btn-action btn-action-ghost">
            <i class="bi bi-download"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn-action btn-action-primary">
            <i class="bi bi-printer"></i> Print &middot; Save PDF
        </button>
    </div>
</div>

<!-- Paper -->
<div class="paper-outer">
<div class="report">

    <!-- Header -->
    <div class="rpt-header">
        <div class="rpt-header-inner">
            <div class="rpt-brand">
                <div class="rpt-logo">
                    <?php if ($logo_src): ?>
                    <img src="<?= $logo_src ?>" alt="<?= htmlspecialchars($shop['name']) ?>">
                    <?php else: ?>
                    <span class="rpt-logo-init"><?= mb_strtoupper(mb_substr($shop['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="rpt-shop-name"><?= htmlspecialchars($shop['name']) ?></div>
                    <?php if (!empty($shop['description'])): ?>
                    <div class="rpt-shop-desc"><?= htmlspecialchars($shop['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="rpt-info-box">
                <div class="rpt-info-label">Sales Report</div>
                <div class="rpt-info-count"><?= count($rows) ?> Order<?= count($rows) !== 1 ? 's' : '' ?></div>
                <div class="rpt-info-period"><?= date('d M Y', strtotime($date_from)) ?> &mdash; <?= date('d M Y', strtotime($date_to)) ?></div>
            </div>
        </div>
    </div>

    <!-- Summary strip -->
    <div class="summary-strip">
        <div class="summary-cell">
            <div class="summary-icon-wrap"><i class="bi bi-bag-check"></i></div>
            <div class="summary-val"><?= count($rows) ?></div>
            <div class="summary-lbl">Total Orders</div>
        </div>
        <div class="summary-cell">
            <div class="summary-icon-wrap"><i class="bi bi-currency-rupee"></i></div>
            <div class="summary-val">&#8377;<?= number_format($total_revenue, 0) ?></div>
            <div class="summary-lbl">Total Revenue</div>
        </div>
        <div class="summary-cell">
            <div class="summary-icon-wrap"><i class="bi bi-graph-up"></i></div>
            <div class="summary-val">&#8377;<?= count($rows) > 0 ? number_format($total_revenue / count($rows), 0) : '0' ?></div>
            <div class="summary-lbl">Avg. Order Value</div>
        </div>
        <div class="summary-cell">
            <div class="summary-icon-wrap"><i class="bi bi-check-circle"></i></div>
            <div class="summary-val"><?= $delivered ?></div>
            <div class="summary-lbl">Delivered</div>
        </div>
        <div class="summary-cell">
            <div class="summary-icon-wrap"><i class="bi bi-clock"></i></div>
            <div class="summary-val"><?= $pending ?></div>
            <div class="summary-lbl">Pending</div>
        </div>
    </div>

    <!-- Meta bar -->
    <div class="meta-bar">
        <div class="meta-group">
            <div class="meta-item">
                <i class="bi bi-calendar3"></i>
                <span><strong>Period:</strong> <?= date('d M Y', strtotime($date_from)) ?> to <?= date('d M Y', strtotime($date_to)) ?></span>
            </div>
            <div class="meta-sep"></div>
            <div class="meta-item">
                <i class="bi bi-clock-history"></i>
                <span><strong>Generated:</strong> <?= date('d M Y, h:i A') ?></span>
            </div>
            <?php if (!empty($settings_map['shop_phone'])): ?>
            <div class="meta-sep"></div>
            <div class="meta-item">
                <i class="bi bi-telephone"></i>
                <span><?= htmlspecialchars($settings_map['shop_phone']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <div class="meta-powered">TamizhMart</div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <?php if (empty($rows)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h3>No orders found</h3>
            <p>There are no orders for the selected date range.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order No.</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Address</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                $st = $status_styles[$row['status']] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>ucfirst($row['status'])];
                $is_online = strtolower($row['payment_method']) !== 'cod';
            ?>
            <tr>
                <td><span class="order-num">#<?= str_pad($row['shop_order_number'] ?? $row['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                <td>
                    <div class="customer-name"><?= htmlspecialchars($row['customer']) ?></div>
                    <div class="customer-sub"><?= htmlspecialchars($row['phone'] ?? $row['email']) ?></div>
                </td>
                <td><div class="items-text"><?= htmlspecialchars($row['items']) ?></div></td>
                <td><span class="total-amt">&#8377;<?= number_format($row['total_amount'], 2) ?></span></td>
                <td>
                    <span class="badge <?= $is_online ? 'badge-online' : 'badge-cod' ?>">
                        <?= $is_online ? 'Online' : 'COD' ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-status" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
                        <?= $st['label'] ?>
                    </span>
                </td>
                <td style="max-width:150px;font-size:11.5px;color:#666;"><?= htmlspecialchars($row['address']) ?></td>
                <td>
                    <div class="date-main"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                    <div class="date-time"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="rpt-footer">
        <div class="footer-left">
            <div class="footer-shop"><?= htmlspecialchars($shop['name']) ?></div>
            <div class="footer-period">Report period: <?= date('d M Y', strtotime($date_from)) ?> &ndash; <?= date('d M Y', strtotime($date_to)) ?></div>
        </div>
        <div class="footer-right">
            <div class="footer-total-label">Grand Total</div>
            <div class="footer-total-val">&#8377;<?= number_format($total_revenue, 2) ?></div>
        </div>
    </div>

</div><!-- .report -->
</div><!-- .paper-outer -->

</body>
</html>