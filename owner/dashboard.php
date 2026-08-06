<?php
session_start();
require '../config/db.php';

$page_title    = 'Dashboard';
$page_subtitle = 'Welcome back, ' . ($_SESSION['owner_name'] ?? 'Owner');
$topbar_action_label   = 'Add Product';
$topbar_action_icon    = 'plus-lg';
$topbar_action_onclick = "window.location='products.php?action=add'";

require 'includes/sidebar.php';

$shop_id = $_SESSION['shop_id'];

// ── Subscription status for banner ────────────────────────────
$sub_info = $conn->query("
    SELECT ss.*, p.name AS plan_name, p.slug AS plan_slug, p.price AS plan_price
    FROM shop_subscriptions ss
    JOIN plans p ON ss.plan_id = p.id
    WHERE ss.shop_id = $shop_id
    ORDER BY ss.id DESC LIMIT 1
")->fetch_assoc();

// ── Stats ──────────────────────────────────────────────────
// Total revenue
$rev = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM orders WHERE shop_id=$shop_id AND status != 'cancelled'")->fetch_assoc()['total'];

// Today revenue
$today_rev = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM orders WHERE shop_id=$shop_id AND DATE(created_at)=CURDATE() AND status!='cancelled'")->fetch_assoc()['total'];

// Total orders
$total_orders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE shop_id=$shop_id")->fetch_assoc()['c'];

// Pending orders
$pending_orders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE shop_id=$shop_id AND status='pending'")->fetch_assoc()['c'];

// Total customers
$total_customers = $conn->query("SELECT COUNT(*) as c FROM users WHERE shop_id=$shop_id")->fetch_assoc()['c'];

// Total products
$total_products = $conn->query("SELECT COUNT(*) as c FROM products WHERE shop_id=$shop_id")->fetch_assoc()['c'];

// Low stock products (stock <= 5)
$low_stock = $conn->query("SELECT COUNT(*) as c FROM products WHERE shop_id=$shop_id AND stock <= 5 AND stock > 0")->fetch_assoc()['c'];

// Out of stock
$out_stock = $conn->query("SELECT COUNT(*) as c FROM products WHERE shop_id=$shop_id AND stock = 0")->fetch_assoc()['c'];

// ── Weekly revenue chart (last 7 days) ──────────────────────
$weekly = [];
$weekly_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($date));
    $r = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM orders WHERE shop_id=$shop_id AND DATE(created_at)='$date' AND status!='cancelled'")->fetch_assoc()['total'];
    $weekly[] = (float)$r;
    $weekly_labels[] = $label;
}

// ── Recent orders ────────────────────────────────────────────
$recent_orders = $conn->query("
    SELECT o.id, o.total_amount, o.status, o.created_at, u.name as customer_name
    FROM orders o JOIN users u ON o.user_id=u.id
    WHERE o.shop_id=$shop_id
    ORDER BY o.created_at DESC LIMIT 8
");

// ── Top products ─────────────────────────────────────────────
$top_products = $conn->query("
    SELECT p.name, p.image, SUM(oi.quantity) as sold, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id=p.id
    WHERE p.shop_id=$shop_id
    GROUP BY p.id ORDER BY sold DESC LIMIT 5
");

// ── Order status distribution ─────────────────────────────────
$status_dist = $conn->query("SELECT status, COUNT(*) as c FROM orders WHERE shop_id=$shop_id GROUP BY status");
$status_data = ['pending'=>0,'processing'=>0,'out_for_delivery'=>0,'delivered'=>0,'cancelled'=>0];
while ($r = $status_dist->fetch_assoc()) $status_data[$r['status']] = (int)$r['c'];
?>

<!-- ── Stat Cards ── -->
<?php
// ── Subscription warning banner ───────────────────────────────
if ($sub_info):
    $now        = time();
    $expires    = strtotime($sub_info['expires_at']);
    $grace_end  = strtotime($sub_info['grace_until'] ?? $sub_info['expires_at']);
    $days_left  = ceil(($expires - $now) / 86400);
    $show_warn  = false;
    $warn_msg   = '';
    $warn_type  = 'orange';

    if ($sub_info['status'] === 'trial') {
        if ($days_left <= 7) {
            $show_warn = true;
            $warn_type = $days_left <= 2 ? 'red' : 'orange';
            $warn_msg  = "⏳ Your free trial ends in <strong>$days_left day(s)</strong>. Contact admin to upgrade to Elite or Premium.";
        }
    } elseif ($sub_info['status'] === 'active') {
        if ($days_left <= 7 && $days_left >= 0) {
            $show_warn = true;
            $warn_msg  = "🔔 Your <strong>{$sub_info['plan_name']}</strong> plan expires in <strong>$days_left day(s)</strong>. Contact admin to renew.";
        }
    } elseif ($sub_info['status'] === 'grace') {
        $grace_left = ceil(($grace_end - $now) / 86400);
        $show_warn  = true;
        $warn_type  = 'red';
        $warn_msg   = "⚠️ <strong>Grace Period:</strong> Your subscription expired. $grace_left day(s) left before shop suspension. Contact admin immediately.";
    }

    if ($show_warn || !empty($_SESSION['sub_warning'])):
        $msg = $warn_msg ?: $_SESSION['sub_warning'];
        unset($_SESSION['sub_warning']);
?>
<div style="background:<?= $warn_type==='red' ? 'rgba(239,68,68,0.1)' : 'rgba(251,191,36,0.1)' ?>;
     border:1px solid <?= $warn_type==='red' ? 'rgba(239,68,68,0.3)' : 'rgba(251,191,36,0.3)' ?>;
     border-radius:var(--radius-sm);
     padding:14px 18px;
     margin-bottom:20px;
     display:flex;
     align-items:center;
     justify-content:space-between;
     gap:12px;
     flex-wrap:wrap;"
     class="animate-in">
    <div style="font-size:13.5px;color:<?= $warn_type==='red' ? '#dc2626' : '#92400e' ?>;">
        <?= $msg ?>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <span style="font-size:11.5px;font-weight:700;padding:4px 12px;border-radius:99px;
            background:<?= $warn_type==='red' ? 'rgba(239,68,68,0.15)' : 'rgba(251,191,36,0.15)' ?>;
            color:<?= $warn_type==='red' ? '#dc2626' : '#92400e' ?>;">
            <?= strtoupper($sub_info['plan_name']) ?> PLAN
        </span>
    </div>
</div>
<?php endif; endif; ?>

<!-- Subscription info bar -->
<?php if ($sub_info): ?>
<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;
    background:var(--card-bg);border:1px solid var(--card-border);
    border-radius:var(--radius-sm);padding:12px 18px;font-size:13px;" class="animate-in">
    <span style="color:var(--muted);">Current Plan:</span>
    <span style="font-weight:700;color:var(--accent);"><?= htmlspecialchars($sub_info['plan_name']) ?></span>
    <span style="color:var(--muted);">|</span>
    <span style="color:var(--muted);">Status:</span>
    <span style="font-weight:600;text-transform:capitalize;"><?= $sub_info['status'] ?></span>
    <span style="color:var(--muted);">|</span>
    <span style="color:var(--muted);">Expires:</span>
    <span style="font-weight:600;"><?= date('d M Y', strtotime($sub_info['expires_at'])) ?></span>
</div>
<?php endif; ?>

<div class="row g-3 animate-in">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(200,169,126,0.12);color:var(--accent);">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="stat-value">&#8377;<?= number_format($rev, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
            <span class="stat-trend trend-up"><i class="bi bi-arrow-up-right"></i> &#8377;<?= number_format($today_rev, 0) ?> today</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(96,165,250,0.12);color:var(--info);">
                <i class="bi bi-bag-check"></i>
            </div>
            <div class="stat-value"><?= $total_orders ?></div>
            <div class="stat-label">Total Orders</div>
            <span class="stat-trend trend-neutral"><i class="bi bi-clock"></i> <?= $pending_orders ?> pending</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(74,222,128,0.12);color:var(--success);">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= $total_customers ?></div>
            <div class="stat-label">Customers</div>
            <span class="stat-trend trend-up"><i class="bi bi-person-plus"></i> Registered</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(251,191,36,0.12);color:var(--warning);">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-value"><?= $total_products ?></div>
            <div class="stat-label">Products</div>
            <?php if ($out_stock > 0): ?>
            <span class="stat-trend trend-down"><i class="bi bi-exclamation-triangle"></i> <?= $out_stock ?> out of stock</span>
            <?php else: ?>
            <span class="stat-trend trend-up"><i class="bi bi-check-circle"></i> All stocked</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Charts Row ── -->
<div class="row g-3 mt-1 animate-in d2">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card-glass" style="height:100%;">
            <div class="section-head" style="margin-bottom:20px;">
                <div>
                    <div class="section-title">Revenue Overview</div>
                    <div class="section-sub">Last 7 days</div>
                </div>
                <span class="status-pill pill-active"><i class="bi bi-graph-up" style="font-size:10px;"></i> Live</span>
            </div>
            <canvas id="revenueChart" height="90"></canvas>
        </div>
    </div>

    <!-- Order Status Donut -->
    <div class="col-lg-4">
        <div class="card-glass" style="height:100%;">
            <div class="section-title" style="margin-bottom:4px;">Order Status</div>
            <div class="section-sub" style="margin-bottom:20px;">All time distribution</div>
            <canvas id="statusChart" height="160"></canvas>
            <div style="margin-top:18px;display:flex;flex-direction:column;gap:8px;">
                <?php
                $status_labels = ['pending'=>'Pending','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                $status_colors = ['pending'=>'#fbbf24','processing'=>'#60a5fa','out_for_delivery'=>'#a78bfa','delivered'=>'#4ade80','cancelled'=>'#f87171'];
                foreach ($status_data as $k => $v):
                    if ($v === 0) continue;
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;">
                    <span style="display:flex;align-items:center;gap:7px;color:var(--muted);">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $status_colors[$k] ?>;flex-shrink:0;"></span>
                        <?= $status_labels[$k] ?>
                    </span>
                    <span style="font-weight:600;color:var(--text);"><?= $v ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Orders + Top Products ── -->
<div class="row g-3 mt-1 animate-in d3">
    <!-- Recent Orders -->
    <div class="col-lg-7">
        <div class="card-glass">
            <div class="section-head">
                <div>
                    <div class="section-title">Recent Orders</div>
                    <div class="section-sub">Latest transactions</div>
                </div>
                <a href="orders.php" class="btn-ghost-custom" style="padding:7px 14px;font-size:12.5px;">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div style="overflow-x:auto;">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders->num_rows === 0): ?>
                        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--muted);">No orders yet</td></tr>
                        <?php else: while ($order = $recent_orders->fetch_assoc()): ?>
                        <tr>
                            <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--accent);">#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td style="font-weight:500;">&#8377;<?= number_format($order['total_amount'], 2) ?></td>
                            <td><span class="status-pill pill-<?= $order['status'] ?>"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span></td>
                            <td style="color:var(--muted);font-size:12.5px;"><?= date('M j, g:i A', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="col-lg-5">
        <div class="card-glass" style="height:100%;">
            <div class="section-head">
                <div>
                    <div class="section-title">Top Products</div>
                    <div class="section-sub">By units sold</div>
                </div>
            </div>
            <?php if ($top_products->num_rows === 0): ?>
            <div class="empty-state" style="padding:32px;">
                <i class="bi bi-box-seam"></i>
                <p>No sales data yet</p>
            </div>
            <?php else: $rank = 1; while ($p = $top_products->fetch_assoc()): ?>
            <div style="display:flex;align-items:center;gap:14px;padding:12px 0;<?= $rank < $top_products->num_rows + 1 ? 'border-bottom:1px solid var(--card-border);' : '' ?>">
                <div style="width:34px;height:34px;border-radius:9px;overflow:hidden;background:var(--card-bg);flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                    <?php if ($p['image']): ?>
                    <img src="../assets/uploads/products/<?= htmlspecialchars($p['image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <i class="bi bi-image" style="color:var(--muted);font-size:14px;"></i>
                    <?php endif; ?>
                </div>
                <div style="flex:1;overflow:hidden;">
                    <div style="font-size:13.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:12px;color:var(--muted);"><?= $p['sold'] ?> units &middot; &#8377;<?= number_format($p['revenue'],0) ?></div>
                </div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;color:var(--accent);">#<?= $rank ?></div>
            </div>
            <?php $rank++; endwhile; endif; ?>
        </div>
    </div>
</div>

<!-- Low stock alert -->
<?php if ($low_stock > 0 || $out_stock > 0): ?>
<div style="margin-top:20px;" class="animate-in d4">
    <div style="background:rgba(251,191,36,0.07);border:1px solid rgba(251,191,36,0.18);border-radius:var(--radius);padding:16px 20px;display:flex;align-items:center;gap:14px;">
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning);font-size:20px;flex-shrink:0;"></i>
        <div>
            <div style="font-weight:600;font-size:14px;color:var(--warning);">Stock Alert</div>
            <div style="font-size:13px;color:var(--muted);margin-top:2px;">
                <?php if ($out_stock > 0) echo "$out_stock product(s) are out of stock. "; ?>
                <?php if ($low_stock > 0) echo "$low_stock product(s) are running low (5 or fewer remaining)."; ?>
            </div>
        </div>
        <a href="products.php" class="btn-ghost-custom" style="margin-left:auto;white-space:nowrap;font-size:12.5px;">Manage Products</a>
    </div>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>