<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
$page_title    = 'Customers';
$page_subtitle = 'Registered customer directory and purchase metrics';

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];
$search  = trim($_GET['q'] ?? '');

$where = "u.shop_id = $shop_id";
if ($search) $where .= " AND (u.name LIKE '%" . $conn->real_escape_string($search) . "%' OR u.email LIKE '%" . $conn->real_escape_string($search) . "%' OR u.phone LIKE '%" . $conn->real_escape_string($search) . "%')";

$customers = $conn->query("
    SELECT u.*,
           COUNT(o.id) as total_orders,
           COALESCE(SUM(o.total_amount),0) as total_spent,
           MAX(o.created_at) as last_order_at
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id AND o.shop_id = $shop_id AND o.status != 'cancelled'
    WHERE $where
    GROUP BY u.id
    ORDER BY total_spent DESC
");

$total_customers = $conn->query("SELECT COUNT(*) FROM users WHERE shop_id=$shop_id")->fetch_row()[0];
$total_revenue   = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE shop_id=$shop_id AND status!='cancelled'")->fetch_row()[0];
$avg_spend       = $total_customers > 0 ? round($total_revenue / $total_customers, 2) : 0;
?>

<!-- Stats -->
<div class="row g-3 animate-in mb-3">
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#F0F9FF;color:#0EA5E9;"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= number_format($total_customers) ?></div>
            <div class="stat-label">Total Customer Accounts</div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#ECFDF5;color:#10B981;"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-value">&#8377;<?= number_format($total_revenue, 0) ?></div>
            <div class="stat-label">Total Customer Spend</div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value">&#8377;<?= number_format($avg_spend, 0) ?></div>
            <div class="stat-label">Average Spend / Customer</div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="card-glass animate-in d1" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
        <div style="position:relative;flex:1;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;"></i>
            <input type="text" name="q" class="input-custom" placeholder="Search customer by name, email or phone number..."
                value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;">
        </div>
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Search</button>
        <?php if ($search): ?><a href="customers.php" class="btn-ghost-custom">Clear</a><?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card-glass animate-in d2" style="padding:0;overflow:hidden;">
    <table class="table-glass">
        <thead>
            <tr>
                <th style="padding-left:20px;">Customer</th>
                <th>Contact Details</th>
                <th>Orders</th>
                <th>Total Spent</th>
                <th>Last Order</th>
                <th style="padding-right:20px;">Joined</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($customers->num_rows === 0): ?>
        <tr><td colspan="6" style="text-align:center;padding:48px;color:var(--text-muted);">No customers found matching criteria.</td></tr>
        <?php endif; ?>
        <?php while ($c = $customers->fetch_assoc()): ?>
        <tr>
            <td style="padding-left:20px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:6px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;border:1px solid #BFDBFE;">
                        <?= strtoupper(substr($c['name'],0,1)) ?>
                    </div>
                    <div style="font-weight:600;font-size:13.5px;color:var(--text-primary);"><?= htmlspecialchars($c['name']) ?></div>
                </div>
            </td>
            <td>
                <div style="font-size:13px;color:var(--text-primary);"><?= htmlspecialchars($c['email']) ?></div>
                <?php if ($c['phone']): ?><div style="font-size:12px;color:var(--text-muted);margin-top:1px;"><?= htmlspecialchars($c['phone']) ?></div><?php endif; ?>
            </td>
            <td>
                <span style="font-weight:700;font-size:14px;color:var(--text-primary);"><?= $c['total_orders'] ?></span>
            </td>
            <td>
                <span style="font-weight:700;color:var(--success-text);">&#8377;<?= number_format($c['total_spent'], 2) ?></span>
            </td>
            <td style="font-size:12.5px;color:var(--text-muted);">
                <?= $c['last_order_at'] ? date('d M Y', strtotime($c['last_order_at'])) : '&mdash;' ?>
            </td>
            <td style="padding-right:20px;font-size:12.5px;color:var(--text-muted);">
                <?= date('d M Y', strtotime($c['created_at'])) ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php require 'includes/footer.php'; ?>