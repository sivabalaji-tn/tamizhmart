<?php
// Protect all super admin pages
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../superadmin/login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);

// Platform stats for sidebar
$total_shops    = $conn->query("SELECT COUNT(*) FROM shops")->fetch_row()[0];
$total_orders   = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$pending_orders = $conn->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Super Admin' ?> &mdash; TamizhMart Enterprise Command</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Syne:wght@700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --sidebar-w:    264px;
            --sidebar-bg:   #090d16;
            --body-bg:      #0b0f19;
            --card-bg:      rgba(15, 23, 42, 0.65);
            --card-border:  rgba(59, 130, 246, 0.12);
            --card-hover:   rgba(59, 130, 246, 0.2);
            --accent:       #3b82f6; /* Enterprise Cobalt */
            --accent-bright:#60a5fa;
            --accent-dark:  #1d4ed8;
            --accent-glow:  rgba(59, 130, 246, 0.12);
            --cyan-neon:    #06b6d4;
            --cyan-glow:    rgba(6, 182, 212, 0.15);
            --text:         #f1f5f9;
            --muted:        #94a3b8;
            --muted2:       #64748b;
            --success:      #10b981;
            --danger:       #ef4444;
            --warning:      #f59e0b;
            --info:         #0ea5e9;
            --success-dim:  rgba(16, 185, 129, 0.12);
            --danger-dim:   rgba(239, 68, 68, 0.12);
            --warning-dim:  rgba(245, 158, 11, 0.12);
            --info-dim:     rgba(14, 165, 233, 0.12);
            --radius:       12px;
            --radius-sm:    8px;
            --transition:   all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html, body { height:100%; font-family:'Plus Jakarta Sans', sans-serif; color:var(--text); background:var(--body-bg); overflow-x:hidden; }
        ::-webkit-scrollbar { width:6px; height:6px; }
        ::-webkit-scrollbar-track { background: var(--body-bg); }
        ::-webkit-scrollbar-thumb { background: rgba(59, 130, 246, 0.25); border-radius:99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(59, 130, 246, 0.4); }

        /* ── Grid Pattern Background Overlay ── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: 
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(59, 130, 246, 0.08), transparent),
                linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
        }

        /* ── Layout ── */
        .layout { display:flex; min-height:100vh; position: relative; z-index: 1; }

        /* ── Sidebar ── */
        .sidebar {
            width:var(--sidebar-w); min-height:100vh;
            background:var(--sidebar-bg);
            border-right:1px solid rgba(59, 130, 246, 0.12);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; z-index:100;
            transition:transform 0.3s ease;
            box-shadow: 4px 0 24px rgba(0,0,0,0.4);
        }
        .sidebar-brand {
            padding:20px 20px 18px;
            border-bottom:1px solid rgba(59, 130, 246, 0.1);
            display:flex; align-items:center; gap:12px;
            background: rgba(15, 23, 42, 0.4);
        }
        .brand-icon {
            width:40px; height:40px; border-radius:10px;
            background:linear-gradient(135deg, #2563eb, #0284c7);
            display:flex; align-items:center; justify-content:center;
            font-size:20px; flex-shrink:0; color: #fff;
            box-shadow:0 0 20px rgba(37, 99, 235, 0.4), inset 0 1px 1px rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .brand-text { font-family:'Syne',sans-serif; font-weight:800; font-size:16px; line-height:1.2; letter-spacing: -0.3px; color:#fff; }
        .brand-sub  { font-size:10px; color:var(--cyan-neon); font-weight:700; letter-spacing:1.5px; text-transform:uppercase; margin-top:2px; font-family:'JetBrains Mono', monospace; }

        /* Command Center System Status Badge */
        .sa-badge {
            margin:14px 16px;
            padding:9px 12px;
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.1), rgba(6, 182, 212, 0.05));
            border:1px solid rgba(59, 130, 246, 0.2);
            border-radius:var(--radius-sm);
            display:flex; align-items:center; justify-content:space-between;
            font-size:11.5px; font-weight:600; color:#e2e8f0;
        }
        .sa-badge-status {
            display: flex; align-items: center; gap: 7px;
        }
        .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
            animation: pulseGlow 2s infinite;
        }
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .nav-section-label {
            font-size:9.5px; font-weight:800; letter-spacing:1.8px;
            text-transform:uppercase; color:var(--muted2);
            padding:18px 20px 6px; font-family:'JetBrains Mono', monospace;
        }
        .nav-item {
            display:flex; align-items:center; gap:11px;
            padding:9.5px 14px; margin:2px 10px;
            border-radius:var(--radius-sm);
            text-decoration:none; color:var(--muted);
            font-size:13px; font-weight:500;
            transition:var(--transition);
            border:1px solid transparent; position:relative;
        }
        .nav-item i { font-size:15px; width:20px; text-align:center; flex-shrink:0; color: #64748b; transition: var(--transition); }
        .nav-item:hover { background:rgba(59, 130, 246, 0.08); color:#f8fafc; border-color: rgba(59, 130, 246, 0.1); }
        .nav-item:hover i { color: var(--accent-bright); }
        .nav-item.active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.18), rgba(59, 130, 246, 0.05));
            color: #ffffff; font-weight: 600;
            border: 1px solid rgba(59, 130, 246, 0.3);
            box-shadow: inset 3px 0 0 var(--accent);
        }
        .nav-item.active i { color: var(--accent-bright); }
        .nav-badge {
            margin-left:auto; background:var(--accent);
            color:#fff; font-size:10px; font-weight:700;
            padding:2px 7px; border-radius:6px; min-width:18px; text-align:center;
            font-family:'JetBrains Mono', monospace;
        }

        .sidebar-footer {
            margin-top:auto;
            padding:16px;
            border-top:1px solid rgba(59, 130, 246, 0.1);
            background: rgba(15, 23, 42, 0.3);
        }
        .admin-card {
            display:flex; align-items:center; gap:10px;
            padding:10px 12px; background:rgba(15, 23, 42, 0.6);
            border:1px solid rgba(59, 130, 246, 0.15); border-radius:var(--radius-sm);
        }
        .admin-avatar {
            width:34px; height:34px; border-radius:8px;
            background:linear-gradient(135deg, #1d4ed8, #0284c7);
            display:flex; align-items:center; justify-content:center;
            font-family:'Syne',sans-serif; font-weight:800; font-size:13px; color:#fff;
            flex-shrink:0; border: 1px solid rgba(255,255,255,0.2);
        }
        .admin-name  { font-size:12.5px; font-weight:600; color: #f8fafc; }
        .admin-role  { font-size:10.5px; color:var(--cyan-neon); font-weight:600; font-family:'JetBrains Mono', monospace; }
        .admin-logout {
            margin-left:auto; color:var(--muted);
            text-decoration:none; font-size:15px;
            padding: 4px; border-radius: 6px;
            transition:var(--transition);
        }
        .admin-logout:hover { color:var(--danger); background: var(--danger-dim); }

        /* ── Main content ── */
        .main-content {
            margin-left:var(--sidebar-w);
            flex:1; display:flex; flex-direction:column; min-height:100vh;
        }
        .topbar {
            padding:16px 32px;
            border-bottom:1px solid rgba(59, 130, 246, 0.12);
            display:flex; align-items:center; justify-content:space-between;
            background:rgba(9, 13, 22, 0.85); backdrop-filter:blur(16px);
            position:sticky; top:0; z-index:50;
        }
        .topbar-title { font-family:'Syne',sans-serif; font-weight:800; font-size:19px; letter-spacing: -0.4px; color: #fff; }
        .topbar-sub   { font-size:12px; color:var(--muted); margin-top:2px; }

        .page-body { padding:28px 32px; flex:1; }

        /* ── Cards ── */
        .card-glass {
            background:var(--card-bg); 
            border:1px solid var(--card-border);
            border-radius:var(--radius); padding:22px;
            transition:var(--transition);
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .card-glass:hover { border-color:var(--card-hover); }

        /* ── High-Tech Stat Cards ── */
        .stat-card {
            background:var(--card-bg); 
            border:1px solid var(--card-border);
            border-radius:var(--radius); padding:20px 22px;
            display:flex; align-items:center; gap:16px;
            transition:var(--transition); position:relative; overflow:hidden;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .stat-card::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(135deg,var(--glow-color,rgba(59, 130, 246, 0.06)),transparent 60%);
            pointer-events:none;
        }
        .stat-card:hover { border-color:rgba(59, 130, 246, 0.3); transform:translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
        .stat-icon {
            width:46px; height:46px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:20px; flex-shrink:0;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .stat-val  { font-family:'Syne',sans-serif; font-weight:800; font-size:26px; line-height:1; color:#fff; letter-spacing: -0.5px; }
        .stat-label{ font-size:12px; color:var(--muted); margin-top:5px; font-weight: 500; }
        .stat-change { font-size:11.5px; margin-top:6px; font-weight:600; display: flex; align-items: center; gap: 4px; }

        /* ── Table ── */
        .table-custom { width:100%; border-collapse:collapse; }
        .table-custom th {
            font-size:10.5px; font-weight:800; text-transform:uppercase;
            letter-spacing:1px; color:var(--muted2); font-family: 'JetBrains Mono', monospace;
            padding:12px 16px; border-bottom:1px solid rgba(59, 130, 246, 0.15);
            text-align:left; white-space:nowrap; background: rgba(15, 23, 42, 0.5);
        }
        .table-custom td {
            padding:14px 16px; border-bottom:1px solid rgba(255,255,255,0.04);
            font-size:13px; vertical-align:middle; color: #cbd5e1;
        }
        .table-custom tr:last-child td { border-bottom:none; }
        .table-custom tr:hover td { background:rgba(59, 130, 246, 0.05); color: #f8fafc; }

        /* ── Badges ── */
        .badge-custom {
            display:inline-flex; align-items:center; gap:5px;
            padding:3.5px 10px; border-radius:6px;
            font-size:11px; font-weight:600; white-space:nowrap;
            font-family: 'Plus Jakarta Sans', sans-serif;
            border: 1px solid transparent;
        }
        .badge-success { background:var(--success-dim); color:var(--success); border-color: rgba(16, 185, 129, 0.2); }
        .badge-danger  { background:var(--danger-dim);  color:var(--danger);  border-color: rgba(239, 68, 68, 0.2); }
        .badge-warning { background:var(--warning-dim); color:var(--warning); border-color: rgba(245, 158, 11, 0.2); }
        .badge-info    { background:var(--info-dim);    color:var(--info);    border-color: rgba(14, 165, 233, 0.2); }
        .badge-purple  { background:rgba(59, 130, 246, 0.15); color:var(--accent-bright); border-color: rgba(59, 130, 246, 0.25); }

        /* ── Alerts ─────────────────────────── */
        .alert-success, .alert-error {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #f87171;
        }

        /* ── Select dropdown fix (dark theme) ─── */
        select.input-custom option {
            background: #0f172a;
            color: #f1f5f9;
        }
        select.input-custom option:hover,
        select.input-custom option:checked {
            background: var(--accent);
            color: #fff;
        }
        select option {
            background: #0f172a;
            color: #f1f5f9;
        }

        /* ── Form labels ─────────────────────── */
        .input-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 6px;
        }

        /* ── Modal overlay ───────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(6px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid rgba(59, 130, 246, 0.12);
        }
        .modal-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
        }
        .modal-close {
            background: none;
            border: none;
            color: var(--muted);
            font-size: 18px;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: var(--transition);
        }
        .modal-close:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .modal-body { padding: 20px 24px; max-height: 70vh; overflow-y: auto; }
        .modal-footer {
            display: flex;
            gap: 10px;
            padding: 16px 24px;
            border-top: 1px solid rgba(59, 130, 246, 0.12);
            flex-wrap: wrap;
        }

        /* ── Page header ─────────────────────── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .page-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
            margin: 0;
            color: #fff;
        }
        .page-sub { font-size: 12.5px; color: var(--muted); margin: 3px 0 0; }

        .btn-primary-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; 
            background:linear-gradient(135deg, var(--accent-dark), var(--accent));
            border:1px solid rgba(255,255,255,0.15); border-radius:var(--radius-sm);
            color:#fff; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:12.5px;
            cursor:pointer; transition:var(--transition);
            box-shadow:0 4px 16px rgba(37, 99, 235, 0.3); text-decoration:none;
        }
        .btn-primary-custom:hover { filter:brightness(1.15); transform:translateY(-1px); color:#fff; box-shadow:0 6px 20px rgba(37, 99, 235, 0.4); }
        .btn-ghost-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; background:rgba(15, 23, 42, 0.6);
            border:1px solid rgba(59, 130, 246, 0.15); border-radius:var(--radius-sm);
            color:var(--muted); font-size:12.5px; cursor:pointer; font-weight:500;
            transition:var(--transition); text-decoration:none;
        }
        .btn-ghost-custom:hover { background:rgba(59, 130, 246, 0.12); color:#fff; border-color:rgba(59, 130, 246, 0.3); }
        .btn-danger-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; background:var(--danger-dim);
            border:1px solid rgba(239, 68, 68, 0.25); border-radius:var(--radius-sm);
            color:var(--danger); font-size:12.5px; cursor:pointer; font-weight:600;
            transition:var(--transition); text-decoration:none;
        }
        .btn-danger-custom:hover { background:rgba(239, 68, 68, 0.2); }
        .btn-success-custom {
            display:inline-flex; align-items:center; gap:7px;
            padding:8px 16px; background:var(--success-dim);
            border:1px solid rgba(16, 185, 129, 0.25); border-radius:var(--radius-sm);
            color:var(--success); font-size:12.5px; cursor:pointer; font-weight:600;
            transition:var(--transition); text-decoration:none;
        }
        .btn-success-custom:hover { background:rgba(16, 185, 129, 0.2); }

        /* ── Form inputs ── */
        .input-custom {
            width:100%; padding:10px 14px;
            background:rgba(15, 23, 42, 0.6);
            border:1px solid rgba(59, 130, 246, 0.15);
            border-radius:var(--radius-sm); color:var(--text);
            font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; outline:none;
            transition:var(--transition);
        }
        .input-custom:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(59, 130, 246, 0.18); }
        .input-custom::placeholder { color:var(--muted2); }
        .form-label-custom { font-size:11.5px; font-weight:600; color:var(--muted); margin-bottom:6px; letter-spacing:0.3px; }

        /* ── Section titles ── */
        .section-title { font-family:'Syne',sans-serif; font-weight:800; font-size:15px; color:#fff; letter-spacing: -0.2px; }
        .section-sub   { font-size:12px; color:var(--muted); margin-top:2px; }

        /* ── Modal ── */
        .modal-backdrop-custom {
            position:fixed; inset:0; background:rgba(0,0,0,0.75);
            backdrop-filter:blur(6px); z-index:1000;
            display:none; align-items:center; justify-content:center; padding:20px;
        }
        .modal-box {
            background:#0f172a; border:1px solid rgba(59, 130, 246, 0.2);
            border-radius:16px; padding:26px; width:100%; max-width:480px;
            max-height:90vh; overflow-y:auto;
            animation:modalIn 0.25s cubic-bezier(0.34,1.56,0.64,1);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        @keyframes modalIn { from{opacity:0;transform:scale(0.96)} to{opacity:1;transform:scale(1)} }
        .modal-title { font-family:'Syne',sans-serif; font-weight:800; font-size:17px; margin-bottom:18px; display:flex; align-items:center; gap:10px; color:#fff; }

        /* ── Alert flash ── */
        .alert-flash {
            display:flex; align-items:center; gap:10px;
            padding:12px 16px; border-radius:var(--radius-sm);
            font-size:13px; font-weight:500; margin-bottom:20px;
            animation:slideDown 0.25s ease;
        }
        @keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .alert-flash-success { background:var(--success-dim); border:1px solid rgba(16, 185, 129, 0.25); color:var(--success); }
        .alert-flash-danger  { background:var(--danger-dim);  border:1px solid rgba(239, 68, 68, 0.25); color:var(--danger);  }

        /* ── Maintenance banner ── */
        .maintenance-bar {
            background:linear-gradient(90deg,rgba(245, 158, 11, 0.15),rgba(245, 158, 11, 0.05));
            border-bottom:1px solid rgba(245, 158, 11, 0.25);
            padding:10px 32px; font-size:12.5px; color:var(--warning);
            display:flex; align-items:center; gap:10px;
        }

        /* ── Animate ── */
        .animate-in { animation:fadeUp 0.35s ease both; }
        .d1 { animation-delay:0.05s; } .d2 { animation-delay:0.1s; } .d3 { animation-delay:0.15s; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

        /* ── Responsive ── */
        .mobile-menu-btn { display:none; }
        @media(max-width:900px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .main-content { margin-left:0; }
            .mobile-menu-btn { display:flex; }
            .page-body { padding:20px 16px; }
            .topbar { padding: 14px 16px; }
        }
    </style>
</head>
<body>
<div class="layout">

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-cpu-fill"></i></div>
        <div>
            <div class="brand-text">TamizhMart</div>
            <div class="brand-sub">HQ COMMAND CENTER</div>
        </div>
    </div>

    <div class="sa-badge">
        <div class="sa-badge-status">
            <span class="pulse-dot"></span>
            <span>SYSTEM ACTIVE</span>
        </div>
        <span style="font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--cyan-neon); font-weight:700;">v2.4</span>
    </div>

    <nav style="flex:1;overflow-y:auto;padding-bottom:12px;">
        <div class="nav-section-label">Core Matrix</div>
        <a href="dashboard.php" class="nav-item <?= $current_page==='dashboard.php'?'active':'' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Command Console
        </a>

        <div class="nav-section-label">Platform Control</div>
        <a href="shops.php" class="nav-item <?= $current_page==='shops.php'?'active':'' ?>">
            <i class="bi bi-shop"></i> All Merchant Shops
            <span class="nav-badge"><?= $total_shops ?></span>
        </a>
        <a href="owners.php" class="nav-item <?= $current_page==='owners.php'?'active':'' ?>">
            <i class="bi bi-person-badge-fill"></i> Shop Owners
        </a>
        <a href="customers.php" class="nav-item <?= $current_page==='customers.php'?'active':'' ?>">
            <i class="bi bi-people-fill"></i> Customer Base
        </a>
        <a href="orders.php" class="nav-item <?= $current_page==='orders.php'?'active':'' ?>">
            <i class="bi bi-box-seam-fill"></i> Global Transactions
            <?php if ($pending_orders > 0): ?>
            <span class="nav-badge" style="background:var(--warning); color:#000;"><?= $pending_orders ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section-label">Revenue & Subscriptions</div>
        <a href="plans.php" class="nav-item <?= $current_page==='plans.php'?'active':'' ?>">
            <i class="bi bi-layers-fill"></i> Tier Plans
        </a>
        <a href="subscriptions.php" class="nav-item <?= $current_page==='subscriptions.php'?'active':'' ?>">
            <i class="bi bi-credit-card-2-front-fill"></i> Subscriptions & Billing
            <?php
            $grace_count = $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='grace'")->fetch_row()[0] ?? 0;
            if ($grace_count > 0): ?>
            <span class="nav-badge" style="background:var(--danger);"><?= $grace_count ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section-label">System Operations</div>
        <a href="settings.php" class="nav-item <?= $current_page==='settings.php'?'active':'' ?>">
            <i class="bi bi-sliders2"></i> Global Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-card">
            <div class="admin-avatar"><?= strtoupper(substr($_SESSION['superadmin_name'],0,1)) ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['superadmin_name']) ?></div>
                <div class="admin-role">ROOT SUPERADMIN</div>
            </div>
            <a href="logout.php" class="admin-logout" title="Exit Console"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</aside>

<!-- ── Main ── -->
<div class="main-content">

<?php
// Maintenance mode banner
$maint = $conn->query("SELECT setting_value FROM platform_settings WHERE setting_key='maintenance_mode'")->fetch_row()[0] ?? '0';
if ($maint === '1'):
?>
<div class="maintenance-bar">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>MAINTENANCE MODE ENGAGED</strong> &mdash; All public storefronts are currently displaying maintenance lock screen.
    <a href="settings.php" style="color:var(--warning);margin-left:auto;font-weight:700;text-decoration:none;">System Config &rarr;</a>
</div>
<?php endif; ?>

<div class="topbar">
    <div>
        <div class="topbar-title"><?= $page_title ?? 'Command Console' ?></div>
        <div class="topbar-sub"><?= $page_subtitle ?? 'TamizhMart Enterprise Infrastructure' ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <div style="display:flex; align-items:center; gap:8px; padding:6px 14px; background:rgba(15, 23, 42, 0.6); border:1px solid rgba(59, 130, 246, 0.15); border-radius:8px; font-size:11.5px; font-family:'JetBrains Mono',monospace; color:var(--muted);">
            <i class="bi bi-shield-lock-fill" style="color:var(--success);"></i>
            <span>TLS 1.3 ENCRYPTED</span>
        </div>
        <button class="mobile-menu-btn btn-ghost-custom" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="bi bi-list"></i>
        </button>
        <a href="../owner/login.php" class="btn-ghost-custom" style="font-size:12px;">
            <i class="bi bi-box-arrow-up-right"></i> Merchant Portal
        </a>
    </div>
</div>

<div class="page-body">