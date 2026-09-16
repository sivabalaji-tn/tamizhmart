<?php
// ── This script is made by Siva Balaji sms ──────────────────────
// Protect all handler pages — redirect to login if not authenticated
if (!isset($_SESSION['handler_id'])) {
    header('Location: ../handler/login.php');
    exit;
}

$handler_id      = (int)$_SESSION['handler_id'];
$handler_shop_id = (int)$_SESSION['handler_shop_id'];
$handler_name    = $_SESSION['handler_name'] ?? 'Handler';

// Fetch shop info for branding
$shop_stmt = $conn->prepare('SELECT * FROM shops WHERE id = ?');
$shop_stmt->bind_param('i', $handler_shop_id);
$shop_stmt->execute();
$shop = $shop_stmt->get_result()->fetch_assoc();

// Pending order count for badge
$pc_q = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE shop_id = ? AND status = 'pending'");
$pc_q->bind_param('i', $handler_shop_id);
$pc_q->execute();
$pending_badge = $pc_q->get_result()->fetch_assoc()['c'];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Handler Console' ?> &mdash; <?= htmlspecialchars($shop['name'] ?? 'TamizhMart') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 248px;
            --sidebar-bg: #1E293B;
            --sidebar-text: #E2E8F0;
            --sidebar-muted: #94A3B8;
            --sidebar-muted-hover: #CBD5E1;
            --sidebar-active-bg: #0F172A;
            --sidebar-active-text: #FFFFFF;
            --sidebar-border: rgba(255,255,255,0.08);
            --topbar-bg: #FFFFFF;
            --topbar-border: #E2E8F0;
            --body-bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --card-border: #E2E8F0;
            --card-hover-border: #CBD5E1;
            --text-primary: #1E293B;
            --text-secondary: #475569;
            --text-muted: #64748B;
            --text-faint: #94A3B8;
            --primary: #2563EB;
            --primary-hover: #1D4ED8;
            --handler-accent: #7C3AED;
            --handler-accent-hover: #6D28D9;
            --action-orange: #F97316;
            --action-orange-hover: #EA580C;
            --success: #10B981;
            --success-bg: #ECFDF5;
            --success-border: #A7F3D0;
            --success-text: #047857;
            --danger: #EF4444;
            --danger-bg: #FEF2F2;
            --danger-border: #FECACA;
            --danger-text: #B91C1C;
            --warning: #F59E0B;
            --warning-bg: #FFFBEB;
            --warning-border: #FDE68A;
            --warning-text: #B45309;
            --info: #0EA5E9;
            --info-bg: #F0F9FF;
            --info-border: #BAE6FD;
            --info-text: #0369A1;
            --radius: 10px;
            --radius-sm: 6px;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-card: 0 1px 3px 0 rgba(0,0,0,0.04), 0 1px 2px -1px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.06), 0 2px 4px -2px rgba(0,0,0,0.04);
            --transition: all 0.15s ease-in-out;
        }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-primary);
            background: var(--body-bg);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #F1F5F9; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

        /* ─── Layout ─── */
        .layout { display: flex; min-height: 100vh; }

        /* ─── Sidebar ─── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            transition: transform 0.25s ease;
            box-shadow: 2px 0 8px rgba(0,0,0,0.06);
        }
        .sidebar-brand {
            padding: 16px 18px;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: 11px;
            background: #0F172A;
        }
        .brand-icon {
            width: 34px; height: 34px;
            background: var(--handler-accent);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #FFFFFF;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(124,58,237,0.35);
        }
        .brand-texts { overflow: hidden; }
        .brand-name {
            font-weight: 700; font-size: 14px;
            letter-spacing: -0.2px; color: #FFFFFF;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .brand-sub {
            font-size: 10px; color: #94A3B8;
            font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.8px; margin-top: 1px;
        }

        .sidebar-nav { padding: 12px 10px; flex: 1; overflow-y: auto; }
        .nav-section-label {
            font-size: 10px; font-weight: 800;
            letter-spacing: 1.5px; text-transform: uppercase;
            color: #64748B;
            padding: 0 10px;
            margin: 14px 0 5px;
        }
        .nav-section-label:first-child { margin-top: 2px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            color: var(--sidebar-muted);
            text-decoration: none;
            font-size: 13px; font-weight: 500;
            transition: var(--transition);
            position: relative;
            margin-bottom: 2px;
        }
        .nav-item i { font-size: 15px; flex-shrink: 0; color: #64748B; transition: var(--transition); }
        .nav-item:hover { color: var(--sidebar-muted-hover); background: rgba(255,255,255,0.05); }
        .nav-item:hover i { color: #94A3B8; }
        .nav-item.active { color: #FFFFFF; background: var(--sidebar-active-bg); font-weight: 600; box-shadow: inset 3px 0 0 var(--handler-accent); }
        .nav-item.active i { color: #A78BFA; }
        .nav-badge {
            margin-left: auto;
            background: var(--danger);
            color: #FFFFFF;
            font-size: 10.5px; font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
        }

        .sidebar-footer {
            padding: 12px 10px;
            border-top: 1px solid var(--sidebar-border);
            background: #0F172A;
        }
        .handler-card {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--sidebar-border);
        }
        .handler-avatar {
            width: 32px; height: 32px;
            border-radius: 6px;
            background: var(--handler-accent);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; color: #FFFFFF;
            flex-shrink: 0;
        }
        .handler-info { overflow: hidden; flex: 1; }
        .handler-name { font-size: 12.5px; font-weight: 600; color: #F8FAFC; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .handler-role { font-size: 10.5px; color: #94A3B8; }
        .handler-logout {
            color: #94A3B8; font-size: 15px;
            text-decoration: none; transition: color 0.15s;
            padding: 4px; border-radius: 4px;
        }
        .handler-logout:hover { color: var(--danger); background: rgba(239,68,68,0.1); }

        /* ─── Main content ─── */
        .main-content {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: var(--body-bg);
        }

        /* Topbar */
        .topbar {
            padding: 14px 24px;
            border-bottom: 1px solid var(--topbar-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--topbar-bg);
            position: sticky; top: 0; z-index: 50;
            box-shadow: var(--shadow-sm);
        }
        .topbar-left h1 { font-weight: 700; font-size: 18px; letter-spacing: -0.3px; color: var(--text-primary); margin: 0; }
        .topbar-left p { font-size: 12px; color: var(--text-muted); margin-top: 1px; margin-bottom: 0; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }

        .mobile-menu-btn {
            display: none;
            background: #FFFFFF; border: 1px solid #CBD5E1;
            color: var(--text-primary); border-radius: var(--radius-sm);
            padding: 6px 10px; cursor: pointer; font-size: 16px;
        }

        .topbar-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 12.5px; font-weight: 600;
            border: 1px solid;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }
        .topbar-btn-ghost { background: #FFFFFF; border-color: #CBD5E1; color: var(--text-secondary); }
        .topbar-btn-ghost:hover { background: #F8FAFC; color: var(--text-primary); border-color: #94A3B8; }
        .topbar-btn-primary { background: var(--primary); border-color: var(--primary); color: #FFFFFF; }
        .topbar-btn-primary:hover { background: var(--primary-hover); color: #FFFFFF; }

        /* Page body */
        .page-body { padding: 22px 24px; flex: 1; }

        /* ─── Cards ─── */
        .card-glass {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-card);
            transition: var(--transition);
        }
        .card-glass:hover { border-color: var(--card-hover-border); }

        /* ─── Stat cards ─── */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 16px 18px;
            position: relative;
            box-shadow: var(--shadow-card);
            transition: var(--transition);
        }
        .stat-card:hover { border-color: #CBD5E1; transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .stat-card .stat-icon { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 17px; margin-bottom: 10px; }
        .stat-card .stat-value { font-weight: 800; font-size: 22px; letter-spacing: -0.5px; color: var(--text-primary); line-height: 1.1; }
        .stat-card .stat-label { font-size: 11.5px; font-weight: 500; color: var(--text-muted); margin-top: 3px; }

        /* ─── Table ─── */
        .table-glass { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-glass thead tr th {
            padding: 10px 14px;
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.5px; text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--card-border);
            background: #F8FAFC;
            white-space: nowrap;
        }
        .table-glass tbody tr td {
            padding: 12px 14px;
            font-size: 13px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
            color: var(--text-primary);
        }
        .table-glass tbody tr:last-child td { border-bottom: none; }
        .table-glass tbody tr:hover td { background: #F8FAFC; }

        /* ─── Status pills ─── */
        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 4px;
            font-size: 11.5px; font-weight: 600; white-space: nowrap;
            border: 1px solid transparent;
        }
        .status-pill::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
        .pill-pending    { background: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); }
        .pill-pending::before { background: var(--warning-text); }
        .pill-confirmed  { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .pill-confirmed::before { background: #047857; }
        .pill-processing { background: var(--info-bg); color: var(--info-text); border-color: var(--info-border); }
        .pill-processing::before { background: var(--info-text); }
        .pill-out_for_delivery { background: #F5F3FF; color: #6D28D9; border-color: #DDD6FE; }
        .pill-out_for_delivery::before { background: #6D28D9; }
        .pill-delivered  { background: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
        .pill-delivered::before { background: var(--success-text); }
        .pill-cancelled  { background: var(--danger-bg); color: var(--danger-text); border-color: var(--danger-border); }
        .pill-cancelled::before { background: var(--danger-text); }

        /* ─── Form controls ─── */
        .form-label-custom { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; }
        .input-custom {
            width: 100%;
            padding: 9px 12px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 13.5px; outline: none;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            font-family: inherit;
        }
        .input-custom::placeholder { color: var(--text-faint); }
        .input-custom:focus { border-color: var(--handler-accent); box-shadow: 0 0 0 3px rgba(124,58,237,0.12); }
        select.input-custom option { background: #FFFFFF; color: var(--text-primary); }

        /* ─── Buttons ─── */
        .btn-primary-custom {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px;
            background: var(--primary);
            border: 1px solid var(--primary);
            border-radius: var(--radius-sm);
            color: #FFFFFF;
            font-weight: 600; font-size: 13px;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            font-family: inherit;
        }
        .btn-primary-custom:hover { background: var(--primary-hover); color: #FFFFFF; border-color: var(--primary-hover); }

        .btn-handler-custom {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px;
            background: var(--handler-accent);
            border: 1px solid var(--handler-accent);
            border-radius: var(--radius-sm);
            color: #FFFFFF;
            font-weight: 600; font-size: 13px;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            font-family: inherit;
        }
        .btn-handler-custom:hover { background: var(--handler-accent-hover); color: #FFFFFF; }

        .btn-success-custom {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px;
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            border-radius: var(--radius-sm);
            color: var(--success-text); font-size: 13px; font-weight: 600;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            font-family: inherit;
        }
        .btn-success-custom:hover { background: #D1FAE5; color: var(--success-text); }

        .btn-ghost-custom {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 13px; font-weight: 500;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            font-family: inherit;
        }
        .btn-ghost-custom:hover { background: #F8FAFC; color: var(--text-primary); border-color: #94A3B8; }

        .btn-orange-custom {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px;
            background: var(--action-orange);
            border: 1px solid var(--action-orange);
            border-radius: var(--radius-sm);
            color: #FFFFFF; font-weight: 600; font-size: 13px;
            cursor: pointer; text-decoration: none;
            transition: var(--transition);
            font-family: inherit;
        }
        .btn-orange-custom:hover { background: var(--action-orange-hover); color: #FFFFFF; }

        /* ─── Alert ─── */
        .alert-flash {
            padding: 12px 16px; border-radius: var(--radius-sm);
            font-size: 13px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-flash-success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success-text); }
        .alert-flash-error   { background: var(--danger-bg);  border: 1px solid var(--danger-border);  color: var(--danger-text); }
        .alert-flash-info    { background: var(--info-bg);    border: 1px solid var(--info-border);    color: var(--info-text); }

        /* ─── Modal ─── */
        .modal-backdrop-custom {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(15,23,42,0.6);
            backdrop-filter: blur(3px);
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            opacity: 0; pointer-events: none;
            transition: opacity 0.2s;
        }
        .modal-backdrop-custom.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 24px;
            width: 100%; max-width: 480px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            max-height: 90vh; overflow-y: auto;
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--card-border);
        }
        .modal-title { font-weight: 700; font-size: 16px; color: var(--text-primary); }
        .modal-close {
            background: none; border: none;
            color: var(--text-muted); font-size: 18px;
            cursor: pointer; padding: 4px;
            border-radius: 4px; transition: color 0.15s;
        }
        .modal-close:hover { color: var(--text-primary); background: #F1F5F9; }

        /* ─── Section heading ─── */
        .section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .section-title { font-weight: 700; font-size: 15px; letter-spacing: -0.2px; color: var(--text-primary); }
        .section-sub { font-size: 12px; color: var(--text-muted); margin-top: 1px; }

        /* ─── Empty state ─── */
        .empty-state { text-align: center; padding: 48px 24px; color: var(--text-muted); }
        .empty-state i { font-size: 38px; margin-bottom: 12px; color: var(--text-faint); display: block; }
        .empty-state h4 { font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; }
        .empty-state p { font-size: 13px; }

        /* ─── Animate in ─── */
        .animate-in { animation: fadeUp 0.3s ease both; }
        .d1 { animation-delay: 0.04s; }
        .d2 { animation-delay: 0.08s; }
        .d3 { animation-delay: 0.12s; }
        .d4 { animation-delay: 0.16s; }
        .d5 { animation-delay: 0.20s; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* ─── Responsive ─── */
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: block; }
            .page-body { padding: 16px; }
            .topbar { padding: 12px 16px; }
        }
    </style>
</head>
<body>
<div class="layout">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-person-badge-fill"></i></div>
        <div class="brand-texts">
            <div class="brand-name"><?= htmlspecialchars($shop['name'] ?? 'Shop') ?></div>
            <div class="brand-sub">Handler Console</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Operations</div>
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <a href="orders.php" class="nav-item <?= $current_page === 'orders.php' ? 'active' : '' ?>">
            <i class="bi bi-bag-check-fill"></i> Orders
            <?php if ($pending_badge > 0): ?>
            <span class="nav-badge"><?= $pending_badge ?></span>
            <?php endif; ?>
        </a>
        <a href="orders.php?status=out_for_delivery" class="nav-item <?= ($current_page === 'orders.php' && ($_GET['status'] ?? '') === 'out_for_delivery') ? 'active' : '' ?>">
            <i class="bi bi-truck"></i> Out for Delivery
        </a>

        <div class="nav-section-label">Finance</div>
        <a href="cod_wallet.php" class="nav-item <?= $current_page === 'cod_wallet.php' ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> COD Wallet
        </a>
        <a href="activity_log.php" class="nav-item <?= $current_page === 'activity_log.php' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> Activity Log
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="handler-card">
            <div class="handler-avatar"><?= strtoupper(substr($handler_name, 0, 1)) ?></div>
            <div class="handler-info">
                <div class="handler-name"><?= htmlspecialchars($handler_name) ?></div>
                <div class="handler-role">Delivery Handler</div>
            </div>
            <a href="logout.php" class="handler-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</aside>

<!-- Main -->
<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <div class="topbar-left">
                <h1><?= $page_title ?? 'Handler Console' ?></h1>
                <p><?= $page_subtitle ?? date('l, F j, Y') ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <?php if (isset($topbar_action_label)): ?>
            <button class="topbar-btn topbar-btn-primary" onclick="<?= $topbar_action_onclick ?? '' ?>">
                <i class="bi bi-<?= $topbar_action_icon ?? 'plus' ?>"></i> <?= $topbar_action_label ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <!-- Page content starts here -->
    <div class="page-body">
