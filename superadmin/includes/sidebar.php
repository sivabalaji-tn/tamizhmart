<?php
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: ../superadmin/login.php');
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
$total_shops = $conn->query('SELECT COUNT(*) FROM shops')->fetch_row()[0];
$total_orders = $conn->query('SELECT COUNT(*) FROM orders')->fetch_row()[0];
$pending_orders = $conn->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetch_row()[0];
$grace_count = $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='grace'")->fetch_row()[0] ?? 0;
$admin_name = $_SESSION['superadmin_name'] ?? 'Administrator';
$navigation = [
    'Workspace' => [
        ['dashboard.php', 'grid-1x2', 'Overview', null],
        ['shops.php', 'shop', 'Shops', $total_shops],
        ['owners.php', 'person-badge', 'Shop owners', null],
        ['customers.php', 'people', 'Customers', null],
        ['orders.php', 'box-seam', 'Orders', $pending_orders ?: null],
    ],
    'Finance' => [
        ['plans.php', 'layers', 'Plans', null],
        ['subscriptions.php', 'credit-card', 'Subscriptions', $grace_count ?: null],
        ['commission_logs.php', 'journal-text', 'Commission logs', null],
    ],
    'Administration' => [
        ['campaigns.php', 'envelope-paper', 'Email campaigns', null],
        ['audit_logs.php', 'clock-history', 'Audit trail', null],
        ['settings.php', 'sliders', 'Settings', null],
    ],
];
$default_titles = ['plans.php' => 'Subscription plans', 'subscriptions.php' => 'Subscriptions'];
$page_title = $page_title ?? $default_titles[$current_page] ?? 'Overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> &middot; TamizhMart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/admin.css?v=1" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="admin-workspace" data-page="<?= htmlspecialchars(pathinfo($current_page, PATHINFO_FILENAME)) ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="layout">
    <div class="sidebar-scrim" id="sidebarScrim" hidden></div>
    <aside class="sidebar" id="sidebar" aria-label="Administration navigation">
        <a class="sidebar-brand" href="dashboard.php">
            <span class="brand-icon"><i class="bi bi-bag-check" aria-hidden="true"></i></span>
            <span><span class="brand-text">TamizhMart</span><span class="brand-sub">Platform administration</span></span>
        </a>
        <button type="button" class="sidebar-close icon-button" aria-label="Close navigation" title="Close navigation"><i class="bi bi-x-lg"></i></button>
        <nav class="sidebar-nav" aria-label="Main">
            <?php foreach ($navigation as $group => $items): ?>
                <div class="nav-section-label"><?= $group ?></div>
                <?php foreach ($items as [$href, $icon, $label, $count]): ?>
                    <a href="<?= $href ?>" class="nav-item <?= $current_page === $href ? 'active' : '' ?>" <?= $current_page === $href ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><span><?= $label ?></span>
                        <?php if ($count !== null): ?><span class="nav-badge"><?= number_format($count) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="admin-avatar"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1))) ?></div>
            <div class="admin-identity"><div class="admin-name"><?= htmlspecialchars($admin_name) ?></div><div class="admin-role">Super administrator</div></div>
            <a href="logout.php" class="admin-logout icon-button" title="Sign out" aria-label="Sign out"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </aside>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-context">
                <button type="button" class="mobile-menu-btn icon-button" aria-label="Open navigation" title="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
                <span class="breadcrumb-root">Administration</span><i class="bi bi-chevron-right breadcrumb-divider" aria-hidden="true"></i>
                <span class="breadcrumb-current"><?= htmlspecialchars($page_title) ?></span>
            </div>
            <a href="../owner/login.php" class="portal-link" title="Open merchant portal"><i class="bi bi-box-arrow-up-right"></i><span>Merchant portal</span></a>
        </header>
        <?php
        $maint = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key='maintenance_mode'")->fetch_row()[0] ?? '0';
        if ($maint === '1'):
        ?>
        <div class="maintenance-bar"><i class="bi bi-exclamation-triangle"></i><span><strong>Maintenance mode is on.</strong> Public storefronts are temporarily unavailable.</span><a href="settings.php">Settings <i class="bi bi-arrow-right"></i></a></div>
        <?php endif; ?>
        <main class="page-body" id="main-content" tabindex="-1">
            <?php if (!in_array($current_page, ['plans.php', 'subscriptions.php', 'commission_logs.php'], true)): ?>
            <div class="page-header workspace-heading">
                <div><h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1><p class="page-sub"><?= htmlspecialchars($page_subtitle ?? 'Manage your platform') ?></p></div>
                <time class="page-date" datetime="<?= date('Y-m-d') ?>"><i class="bi bi-calendar3"></i><?= date('d M Y') ?></time>
            </div>
            <?php endif; ?>
