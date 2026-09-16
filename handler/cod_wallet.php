<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require '../config/db.php';

require 'includes/sidebar.php'; // auth guard sets $handler_id, $handler_shop_id

$handler_id = (int)$_SESSION['handler_id'];
$shop_id    = $handler_shop_id;

$page_title    = 'COD Wallet';
$page_subtitle = 'Cash on Delivery collections & settlements';

// ── Fetch handler data ─────────────────────────────────────────
$h_stmt = $conn->prepare('SELECT * FROM shop_handlers WHERE id=?');
$h_stmt->bind_param('i', $handler_id);
$h_stmt->execute();
$handler = $h_stmt->get_result()->fetch_assoc();
$wallet_balance = (float)$handler['cod_wallet'];

// ── COD orders delivered by this handler ──────────────────────
$cod_orders = $conn->query("
    SELECT o.id, o.shop_order_number, o.total_amount, o.updated_at AS delivered_at,
           u.name AS customer_name, u.phone AS customer_phone,
           hal.created_at AS collected_at
    FROM handler_activity_log hal
    JOIN orders o ON hal.order_id = o.id
    JOIN users u ON o.user_id = u.id
    WHERE hal.handler_id = $handler_id AND hal.action = 'cod_collected' AND hal.shop_id = $shop_id
    ORDER BY hal.created_at DESC
    LIMIT 50
");

// ── Settlement history ─────────────────────────────────────────
$settlements = $conn->query("
    SELECT hcs.*, o_name.name AS owner_name
    FROM handler_cod_settlements hcs
    JOIN owners o_name ON hcs.settled_by = o_name.id
    WHERE hcs.handler_id = $handler_id
    ORDER BY hcs.settled_at DESC
    LIMIT 20
");

// ── Today's COD total ─────────────────────────────────────────
$today_cod = $conn->query("
    SELECT COALESCE(SUM(o.total_amount), 0) AS total
    FROM handler_activity_log hal
    JOIN orders o ON hal.order_id = o.id
    WHERE hal.handler_id = $handler_id AND hal.action = 'cod_collected'
    AND DATE(hal.created_at) = CURDATE()
")->fetch_assoc()['total'];
?>

<!-- ── Wallet Balance Hero ── -->
<div class="card-glass animate-in d1" style="text-align:center;padding:32px;border-color:<?= $wallet_balance > 0 ? 'var(--success-border)' : 'var(--card-border)' ?>;background:<?= $wallet_balance > 0 ? 'linear-gradient(135deg,#ECFDF5,#F0FDF4)' : 'var(--card-bg)' ?>;">
    <div style="width:60px;height:60px;border-radius:16px;background:<?= $wallet_balance > 0 ? '#DCFCE7' : '#F1F5F9' ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;">
        <i class="bi bi-wallet2" style="color:<?= $wallet_balance > 0 ? 'var(--success)' : 'var(--text-muted)' ?>;"></i>
    </div>
    <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:<?= $wallet_balance > 0 ? 'var(--success-text)' : 'var(--text-muted)' ?>;margin-bottom:8px;">Current COD Balance</div>
    <div style="font-size:48px;font-weight:900;letter-spacing:-2px;color:<?= $wallet_balance > 0 ? 'var(--success-text)' : 'var(--text-primary)' ?>;">
        ₹<?= number_format($wallet_balance, 2) ?>
    </div>
    <?php if ($wallet_balance > 0): ?>
    <div style="margin-top:12px;font-size:13px;color:var(--success-text);">
        <i class="bi bi-info-circle"></i> Hand this cash to your shop owner at end of day.
    </div>
    <?php else: ?>
    <div style="margin-top:12px;font-size:13px;color:var(--text-muted);">No pending COD cash. All settled.</div>
    <?php endif; ?>
</div>

<!-- ── Today's collection & Total ── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:16px 0;" class="animate-in d2">
    <div class="stat-card">
        <div class="stat-icon" style="background:#FEF3C7;color:#D97706;"><i class="bi bi-sun"></i></div>
        <div class="stat-value">₹<?= number_format($today_cod, 2) ?></div>
        <div class="stat-label">Collected Today</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#EFF6FF;color:var(--primary);"><i class="bi bi-list-check"></i></div>
        <div class="stat-value"><?= $cod_orders->num_rows ?></div>
        <div class="stat-label">Total COD Deliveries</div>
    </div>
</div>

<!-- ── COD Order Ledger ── -->
<div class="card-glass animate-in d3" style="padding:0;overflow:hidden;margin-bottom:16px;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--card-border);">
        <div class="section-title">COD Collection Ledger</div>
        <div class="section-sub">Orders where you collected cash on delivery</div>
    </div>
    <?php if ($cod_orders->num_rows === 0): ?>
    <div class="empty-state" style="padding:32px;">
        <i class="bi bi-cash-coin"></i>
        <h4>No COD collections yet</h4>
        <p>Delivered COD orders will appear here.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table-glass">
            <thead>
                <tr>
                    <th style="padding-left:20px;">Order</th>
                    <th>Customer</th>
                    <th>Amount Collected</th>
                    <th>Delivered At</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($c = $cod_orders->fetch_assoc()): ?>
            <tr>
                <td style="padding-left:20px;">
                    <a href="order_detail.php?order_id=<?= $c['id'] ?>" style="font-weight:700;color:var(--primary);text-decoration:none;">
                        #<?= str_pad($c['shop_order_number'] ?? $c['id'], 4, '0', STR_PAD_LEFT) ?>
                    </a>
                </td>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($c['customer_name']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($c['customer_phone'] ?? '') ?></div>
                </td>
                <td style="font-weight:800;font-size:15px;color:var(--success-text);">
                    ₹<?= number_format($c['total_amount'], 2) ?>
                </td>
                <td style="font-size:12.5px;color:var(--text-muted);"><?= date('M j, Y, g:i A', strtotime($c['collected_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Settlement History ── -->
<div class="card-glass animate-in d4" style="padding:0;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--card-border);">
        <div class="section-title">Settlement History</div>
        <div class="section-sub">Past wallet resets by your shop owner</div>
    </div>
    <?php if ($settlements->num_rows === 0): ?>
    <div class="empty-state" style="padding:32px;">
        <i class="bi bi-clock-history"></i>
        <h4>No settlements yet</h4>
        <p>Your owner will settle your wallet at the end of each day.</p>
    </div>
    <?php else: ?>
    <div>
        <?php while ($s = $settlements->fetch_assoc()): ?>
        <div style="padding:14px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:36px;height:36px;border-radius:8px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--primary);flex-shrink:0;">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:13.5px;">Settled by <?= htmlspecialchars($s['owner_name']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);"><?= date('M j, Y, g:i A', strtotime($s['settled_at'])) ?>
                        <?php if ($s['note']): ?> · <?= htmlspecialchars($s['note']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="font-weight:800;font-size:16px;color:var(--primary);">₹<?= number_format($s['amount'], 2) ?></div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
