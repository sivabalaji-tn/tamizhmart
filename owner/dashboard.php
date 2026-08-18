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
$sub_info = null;
if ($shop_id) {
    $sub_q = $conn->prepare("
        SELECT ss.*, p.name AS plan_name, p.slug AS plan_slug, p.price AS plan_price
        FROM shop_subscriptions ss
        JOIN plans p ON ss.plan_id = p.id
        WHERE ss.shop_id = ?
        ORDER BY ss.id DESC LIMIT 1
    ");
    $sub_q->bind_param('i', $shop_id);
    $sub_q->execute();
    $sub_info = $sub_q->get_result()->fetch_assoc();
}

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
    SELECT p.name, p.image, p.image_url, SUM(oi.quantity) as sold, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id=p.id
    WHERE p.shop_id=$shop_id
    GROUP BY p.id ORDER BY sold DESC LIMIT 5
");

// ── Order status distribution ─────────────────────────────────
$status_dist = $conn->query("SELECT status, COUNT(*) as c FROM orders WHERE shop_id=$shop_id GROUP BY status");
$status_data = ['pending'=>0,'confirmed'=>0,'processing'=>0,'out_for_delivery'=>0,'delivered'=>0,'cancelled'=>0];
while ($r = $status_dist->fetch_assoc()) $status_data[$r['status']] = (int)$r['c'];
?>

<!-- ── Subscription Warning Banner ── -->
<?php
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
            $warn_msg  = "Your free trial ends in <strong>$days_left day(s)</strong>. Contact admin to upgrade your plan.";
        }
    } elseif ($sub_info['status'] === 'active') {
        if ($days_left <= 7 && $days_left >= 0) {
            $show_warn = true;
            $warn_msg  = "Your <strong>{$sub_info['plan_name']}</strong> plan expires in <strong>$days_left day(s)</strong>. Contact admin to renew.";
        }
    } elseif ($sub_info['status'] === 'grace') {
        $grace_left = ceil(($grace_end - $now) / 86400);
        $show_warn  = true;
        $warn_type  = 'red';
        $warn_msg   = "<strong>Grace Period:</strong> Your subscription expired. $grace_left day(s) left before shop suspension. Contact admin immediately.";
    }

    if ($show_warn || !empty($_SESSION['sub_warning'])):
        $msg = $warn_msg ?: $_SESSION['sub_warning'];
        unset($_SESSION['sub_warning']);
?>
<div class="alert-flash <?= $warn_type==='red' ? 'alert-flash-error' : 'alert-flash-warning' ?> animate-in">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div style="flex:1; font-size:13px;"><?= $msg ?></div>
    <span class="badge bg-secondary" style="font-size:11px; text-transform:uppercase;"><?= htmlspecialchars($sub_info['plan_name']) ?> PLAN</span>
</div>
<?php endif; endif; ?>

<!-- ── Stat Cards ── -->
<div class="row g-3 animate-in mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF; color:#2563EB;">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="stat-value">&#8377;<?= number_format($rev, 0) ?></div>
            <div class="stat-label">Total Gross Revenue</div>
            <span class="stat-trend trend-up"><i class="bi bi-arrow-up-short"></i> &#8377;<?= number_format($today_rev, 0) ?> today</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#F0F9FF; color:#0EA5E9;">
                <i class="bi bi-bag-check"></i>
            </div>
            <div class="stat-value"><?= number_format($total_orders) ?></div>
            <div class="stat-label">Total Orders Handled</div>
            <span class="stat-trend trend-neutral"><i class="bi bi-clock-history"></i> <?= $pending_orders ?> pending</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#ECFDF5; color:#10B981;">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= number_format($total_customers) ?></div>
            <div class="stat-label">Customer Base</div>
            <span class="stat-trend trend-up"><i class="bi bi-person-check"></i> Registered</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#FFFBEB; color:#F59E0B;">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-value"><?= number_format($total_products) ?></div>
            <div class="stat-label">Active Products</div>
            <?php if ($out_stock > 0): ?>
            <span class="stat-trend trend-down"><i class="bi bi-exclamation-circle"></i> <?= $out_stock ?> out of stock</span>
            <?php else: ?>
            <span class="stat-trend trend-up"><i class="bi bi-check-circle"></i> In stock</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Charts Row ── -->
<div class="row g-3 animate-in d2 mb-4">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card-glass" style="height:100%;">
            <div class="section-head">
                <div>
                    <div class="section-title">Revenue Overview</div>
                    <div class="section-sub">Daily revenue throughput for the last 7 days</div>
                </div>
                <span class="status-pill pill-active"><i class="bi bi-circle-fill" style="font-size:6px;"></i> Live Updates</span>
            </div>
            <canvas id="revenueChart" height="100"></canvas>
        </div>
    </div>

    <!-- Order Status Donut -->
    <div class="col-lg-4">
        <div class="card-glass" style="height:100%;">
            <div class="section-title" style="margin-bottom:2px;">Fulfillment Status</div>
            <div class="section-sub" style="margin-bottom:16px;">Order breakdown by status</div>
            <canvas id="statusChart" height="160"></canvas>
            <div style="margin-top:16px; display:flex; flex-direction:column; gap:8px;">
                <?php
                $status_labels = ['pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                $status_colors = ['pending'=>'#F59E0B','confirmed'=>'#10B981','processing'=>'#0EA5E9','out_for_delivery'=>'#8B5CF6','delivered'=>'#059669','cancelled'=>'#EF4444'];
                foreach ($status_data as $k => $v):
                    if ($v === 0) continue;
                ?>
                <div style="display:flex; align-items:center; justify-content:space-between; font-size:12.5px;">
                    <span style="display:flex; align-items:center; gap:7px; color:var(--text-secondary);">
                        <span style="width:8px; height:8px; border-radius:50%; background:<?= $status_colors[$k] ?>; flex-shrink:0;"></span>
                        <?= $status_labels[$k] ?>
                    </span>
                    <span style="font-weight:700; color:var(--text-primary);"><?= $v ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Orders + Top Products ── -->
<div class="row g-3 animate-in d3 mb-4">
    <!-- Recent Orders -->
    <div class="col-lg-7">
        <div class="card-glass">
            <div class="section-head">
                <div>
                    <div class="section-title">Recent Orders</div>
                    <div class="section-sub">Latest customer transactions</div>
                </div>
                <a href="orders.php" class="btn-ghost-custom" style="padding:6px 12px; font-size:12px;">View All Orders <i class="bi bi-arrow-right"></i></a>
            </div>
            <div style="overflow-x:auto;">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders->num_rows === 0): ?>
                        <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">No orders recorded yet.</td></tr>
                        <?php else: while ($order = $recent_orders->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:700; color:var(--primary);">#<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td style="font-weight:500;"><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td style="font-weight:700;">&#8377;<?= number_format($order['total_amount'], 2) ?></td>
                            <td><span class="status-pill pill-<?= $order['status'] ?>"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span></td>
                            <td style="color:var(--text-muted); font-size:12px;"><?= date('M j, g:i A', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="col-lg-5">
        <div class="card-glass" style="height:fit-content;">
            <div class="section-head">
                <div>
                    <div class="section-title">Top Selling Products</div>
                    <div class="section-sub">Ranked by volume sold</div>
                </div>
            </div>
            <?php if ($top_products->num_rows === 0): ?>
            <div class="empty-state" style="padding:32px;">
                <i class="bi bi-box-seam"></i>
                <p>No sales data yet</p>
            </div>
            <?php else: $rank = 1; while ($p = $top_products->fetch_assoc()): ?>
            <div style="display:flex; align-items:center; gap:12px; padding:10px 0; <?= $rank < $top_products->num_rows ? 'border-bottom:1px solid var(--card-border);' : '' ?>">
                <div style="width:36px; height:36px; border-radius:6px; overflow:hidden; background:#F1F5F9; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:1px solid #E2E8F0;">
                    <?php
                    $dash_img = !empty($p['image_url']) ? htmlspecialchars($p['image_url'])
                        : (!empty($p['image']) ? (strpos($p['image'],'http')===0 ? htmlspecialchars($p['image']) : '../assets/uploads/products/'.htmlspecialchars($p['image'])) : '');
                    ?>
                    <?php if ($dash_img): ?>
                    <img src="<?= $dash_img ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                    <i class="bi bi-image" style="color:var(--text-muted); font-size:14px;"></i>
                    <?php endif; ?>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:13px; font-weight:600; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:11.5px; color:var(--text-muted);"><?= $p['sold'] ?> units sold &middot; &#8377;<?= number_format($p['revenue'], 0) ?></div>
                </div>
                <div style="font-weight:700; font-size:12.5px; color:var(--primary);">#<?= $rank ?></div>
            </div>
            <?php $rank++; endwhile; endif; ?>
        </div>
    </div>
</div>

<!-- Low Stock Warning Alert Bar -->
<?php if ($low_stock > 0 || $out_stock > 0): ?>
<div class="animate-in d4">
    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:var(--radius); padding:16px 20px; display:flex; align-items:center; gap:14px;">
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--warning-text); font-size:22px; flex-shrink:0;"></i>
        <div style="flex:1;">
            <div style="font-weight:700; font-size:13.5px; color:var(--warning-text);">Stock Inventory Alert</div>
            <div style="font-size:12.5px; color:#92400E; margin-top:2px;">
                <?php if ($out_stock > 0) echo "<strong>$out_stock product(s)</strong> are completely out of stock. "; ?>
                <?php if ($low_stock > 0) echo "<strong>$low_stock product(s)</strong> have 5 or fewer items remaining."; ?>
            </div>
        </div>
        <a href="products.php" class="btn-ghost-custom" style="white-space:nowrap; font-size:12.5px; border-color:#FCD34D; background:#FFFFFF;">Manage Inventory</a>
    </div>
</div>
<?php endif; ?>

<?php
$weekly_json = json_encode($weekly);
$labels_json = json_encode($weekly_labels);
$sd_vals = json_encode(array_values($status_data));
$sd_lbls = json_encode(array_values(array_map(fn($k)=>['pending'=>'Pending','confirmed'=>'Confirmed','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'][$k], array_keys($status_data))));
$sd_cols = json_encode(array_values(['pending'=>'#F59E0B','confirmed'=>'#10B981','processing'=>'#0EA5E9','out_for_delivery'=>'#8B5CF6','delivered'=>'#059669','cancelled'=>'#EF4444']));
$extra_scripts = '
<script>
(function() {
    if (typeof Chart === "undefined") return;

    // ── Revenue Line Chart ────────────────────────────────────
    const rCtx = document.getElementById("revenueChart");
    if (rCtx) {
        new Chart(rCtx, {
            type: "line",
            data: {
                labels: ' . $labels_json . ',
                datasets: [{
                    label: "Revenue (₹)",
                    data: ' . $weekly_json . ',
                    borderColor: "#2563EB",
                    backgroundColor: "rgba(37, 99, 235, 0.06)",
                    borderWidth: 2.5,
                    pointBackgroundColor: "#2563EB",
                    pointBorderColor: "#FFFFFF",
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => " ₹" + ctx.parsed.y.toLocaleString("en-IN")
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: "#F1F5F9" },
                        ticks: { color: "#64748B", font: { size: 11, family: "Plus Jakarta Sans" } }
                    },
                    y: {
                        grid: { color: "#F1F5F9" },
                        ticks: {
                            color: "#64748B",
                            font: { size: 11, family: "Plus Jakarta Sans" },
                            callback: v => "₹" + v.toLocaleString("en-IN")
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // ── Order Status Donut ────────────────────────────────────
    const sCtx = document.getElementById("statusChart");
    if (sCtx) {
        const vals = ' . $sd_vals . ';
        const lbls = ' . $sd_lbls . ';
        const cols = ' . $sd_cols . ';

        const filtered = vals.map((v,i) => ({v,l:lbls[i],c:cols[i]})).filter(x => x.v > 0);

        if (filtered.length > 0) {
            new Chart(sCtx, {
                type: "doughnut",
                data: {
                    labels: filtered.map(x => x.l),
                    datasets: [{
                        data: filtered.map(x => x.v),
                        backgroundColor: filtered.map(x => x.c),
                        borderWidth: 2,
                        borderColor: "#FFFFFF",
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    cutout: "72%",
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => " " + ctx.label + ": " + ctx.parsed
                            }
                        }
                    }
                }
            });
        } else {
            sCtx.parentElement.innerHTML += "<div style=\"text-align:center;padding:24px;color:#94A3B8;font-size:12.5px;\">No order data recorded yet</div>";
            sCtx.remove();
        }
    }
})();
</script>';
?>

<?php require 'includes/footer.php'; ?>
