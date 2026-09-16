<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require '../config/db.php';

$page_title    = 'Dashboard';
$page_subtitle = 'Welcome back, ' . ($_SESSION['handler_name'] ?? 'Handler');

require 'includes/sidebar.php';

$shop_id    = $handler_shop_id;
$handler_id = (int)$_SESSION['handler_id'];
$today      = date('Y-m-d');

// ── Today's stats ──────────────────────────────────────────────
$today_pending = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE shop_id=$shop_id AND status='pending' AND DATE(created_at)='$today'")->fetch_assoc()['c'];
$today_ofd     = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE shop_id=$shop_id AND status='out_for_delivery'")->fetch_assoc()['c'];
$today_done    = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE shop_id=$shop_id AND status='delivered' AND DATE(updated_at)='$today'")->fetch_assoc()['c'];
$total_active  = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE shop_id=$shop_id AND status NOT IN ('delivered','cancelled')")->fetch_assoc()['c'];

// ── Handler wallet ─────────────────────────────────────────────
$wallet_stmt = $conn->prepare('SELECT cod_wallet FROM shop_handlers WHERE id = ?');
$wallet_stmt->bind_param('i', $handler_id);
$wallet_stmt->execute();
$cod_wallet = (float)$wallet_stmt->get_result()->fetch_assoc()['cod_wallet'];

// ── Quick action orders (not done, most recent first, limit 12) ─
$feed = $conn->query("
    SELECT o.*, u.name AS customer_name, u.phone AS customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.shop_id = $shop_id AND o.status NOT IN ('delivered','cancelled')
    ORDER BY o.created_at DESC
    LIMIT 12
");
?>

<!-- ── Stats row ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;">
    <div class="stat-card animate-in d1">
        <div class="stat-icon" style="background:#FEF3C7;color:#D97706;"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-value"><?= $today_pending ?></div>
        <div class="stat-label">Today's Pending</div>
    </div>
    <div class="stat-card animate-in d2">
        <div class="stat-icon" style="background:#F5F3FF;color:#7C3AED;"><i class="bi bi-truck"></i></div>
        <div class="stat-value"><?= $today_ofd ?></div>
        <div class="stat-label">Out for Delivery</div>
    </div>
    <div class="stat-card animate-in d3">
        <div class="stat-icon" style="background:#ECFDF5;color:#047857;"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-value"><?= $today_done ?></div>
        <div class="stat-label">Delivered Today</div>
    </div>
    <div class="stat-card animate-in d4" onclick="window.location='cod_wallet.php'" style="cursor:pointer;">
        <div class="stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-wallet2"></i></div>
        <div class="stat-value" style="color:<?= $cod_wallet > 0 ? '#16a34a' : 'var(--text-primary)' ?>;">
            ₹<?= number_format($cod_wallet, 2) ?>
        </div>
        <div class="stat-label">COD Wallet <?= $cod_wallet > 0 ? '· Tap to view' : '' ?></div>
    </div>
</div>

<!-- ── Active orders feed ── -->
<div class="card-glass animate-in d2" style="padding:0;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
        <div>
            <div class="section-title">Active Orders</div>
            <div class="section-sub"><?= $total_active ?> order<?= $total_active != 1 ? 's' : '' ?> needing action</div>
        </div>
        <a href="orders.php" class="btn-ghost-custom" style="font-size:12px;padding:6px 12px;">
            <i class="bi bi-list-ul"></i> All Orders
        </a>
    </div>

    <?php if ($feed->num_rows === 0): ?>
    <div class="empty-state" style="padding:32px;">
        <i class="bi bi-check-all"></i>
        <h4>All caught up!</h4>
        <p>No orders currently need your attention.</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;">
        <?php while ($o = $feed->fetch_assoc()):
            $num = '#' . str_pad($o['shop_order_number'] ?? $o['id'], 4, '0', STR_PAD_LEFT);
            $status_labels = ['pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing','out_for_delivery'=>'Out for Delivery'];
            $is_cod = $o['payment_method'] === 'cod';
        ?>
        <div style="padding:14px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;gap:14px;transition:background 0.12s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background=''">
            <!-- Status dot -->
            <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?= ['pending'=>'#F59E0B','confirmed'=>'#10B981','processing'=>'#0EA5E9','out_for_delivery'=>'#7C3AED'][$o['status']] ?? '#94A3B8' ?>"></div>

            <!-- Order info -->
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-weight:800;font-size:14px;color:var(--primary);"><?= $num ?></span>
                    <span class="status-pill pill-<?= $o['status'] ?>"><?= $status_labels[$o['status']] ?? $o['status'] ?></span>
                    <?php if ($is_cod): ?>
                    <span style="font-size:10.5px;font-weight:700;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;padding:1px 6px;border-radius:3px;">COD</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                    <?= htmlspecialchars($o['customer_name']) ?> &nbsp;·&nbsp; <?= date('M j, g:i A', strtotime($o['created_at'])) ?>
                </div>
            </div>

            <!-- Amount -->
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-weight:700;font-size:14px;color:var(--text-primary);">₹<?= number_format($o['total_amount'], 2) ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?= strtoupper($o['payment_method']) ?></div>
            </div>

            <!-- Action -->
            <a href="order_detail.php?order_id=<?= $o['id'] ?>" class="btn-handler-custom" style="flex-shrink:0;padding:6px 12px;font-size:12px;">
                <i class="bi bi-arrow-right-circle"></i> Handle
            </a>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
