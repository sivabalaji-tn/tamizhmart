<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require '../config/db.php';

require 'includes/sidebar.php';

$handler_id = (int)$_SESSION['handler_id'];
$shop_id    = $handler_shop_id;

$page_title    = 'Activity Log';
$page_subtitle = 'Your action history on all orders';

$page    = max(1, (int)($_GET['page'] ?? 1));
$per_pg  = 20;
$offset  = ($page - 1) * $per_pg;

$total = $conn->query("SELECT COUNT(*) AS c FROM handler_activity_log WHERE handler_id=$handler_id AND shop_id=$shop_id")->fetch_assoc()['c'];
$pages = ceil($total / $per_pg);

$logs = $conn->query("
    SELECT hal.*, o.shop_order_number
    FROM handler_activity_log hal
    LEFT JOIN orders o ON hal.order_id = o.id
    WHERE hal.handler_id = $handler_id AND hal.shop_id = $shop_id
    ORDER BY hal.created_at DESC
    LIMIT $per_pg OFFSET $offset
");

$action_labels = [
    'status_update' => 'Status Update',
    'otp_sent'      => 'OTP Sent',
    'otp_verified'  => 'OTP Verified / Delivered',
    'cod_collected' => 'COD Collected',
];
$action_icons  = [
    'status_update' => 'arrow-right-circle',
    'otp_sent'      => 'envelope-fill',
    'otp_verified'  => 'shield-check-fill',
    'cod_collected' => 'cash-coin',
];
$action_colors = [
    'status_update' => 'var(--primary)',
    'otp_sent'      => '#7C3AED',
    'otp_verified'  => 'var(--success)',
    'cod_collected' => '#D97706',
];
?>

<div class="card-glass animate-in" style="padding:0;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
        <div>
            <div class="section-title">Activity Log</div>
            <div class="section-sub"><?= $total ?> total actions recorded</div>
        </div>
    </div>

    <?php if ($logs->num_rows === 0): ?>
    <div class="empty-state" style="padding:40px;">
        <i class="bi bi-clock-history"></i>
        <h4>No activity yet</h4>
        <p>Your actions on orders will be recorded here.</p>
    </div>
    <?php else: ?>
    <div>
        <?php while ($log = $logs->fetch_assoc()):
            $icon  = $action_icons[$log['action']]  ?? 'dot';
            $color = $action_colors[$log['action']] ?? 'var(--text-muted)';
            $label = $action_labels[$log['action']] ?? $log['action'];
            $num   = '#' . str_pad($log['shop_order_number'] ?? $log['order_id'], 4, '0', STR_PAD_LEFT);
        ?>
        <div style="padding:14px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:flex-start;gap:14px;">
            <div style="width:36px;height:36px;border-radius:8px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                <i class="bi bi-<?= $icon ?>" style="color:<?= $color ?>;font-size:15px;"></i>
            </div>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <a href="order_detail.php?order_id=<?= $log['order_id'] ?>" style="font-weight:700;color:var(--primary);text-decoration:none;"><?= $num ?></a>
                    <span style="font-size:12.5px;font-weight:600;color:var(--text-secondary);"><?= $label ?></span>
                    <?php if ($log['action'] === 'status_update' && $log['old_value'] && $log['new_value']): ?>
                    <span style="font-size:11.5px;color:var(--text-muted);"><?= ucfirst(str_replace('_',' ',$log['old_value'])) ?> → <?= ucfirst(str_replace('_',' ',$log['new_value'])) ?></span>
                    <?php elseif ($log['action'] === 'cod_collected'): ?>
                    <span style="font-size:12.5px;font-weight:700;color:#D97706;">₹<?= htmlspecialchars($log['new_value']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
                    <?= date('M j, Y, g:i A', strtotime($log['created_at'])) ?>
                    <?php if ($log['note']): ?> · <?= htmlspecialchars($log['note']) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div style="padding:14px 20px;border-top:1px solid var(--card-border);background:#F8FAFC;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:12.5px;color:var(--text-muted);">Page <?= $page ?> of <?= $pages ?></span>
        <div style="display:flex;gap:4px;">
            <?php for ($p = 1; $p <= min($pages, 10); $p++): ?>
            <a href="?page=<?= $p ?>"
                style="width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12.5px;text-decoration:none;border:1px solid;
                <?= $p == $page ? 'background:var(--handler-accent);border-color:var(--handler-accent);color:#FFFFFF;font-weight:700;' : 'background:#FFFFFF;border-color:#CBD5E1;color:var(--text-secondary);' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
