<?php
session_start();
require '../config/db.php';

$page_title    = 'Analytics';
$page_subtitle = 'Deep insights into your store performance';

require 'includes/sidebar.php';
// ── This script is made by Siva Balaji sms ──────────────────────

$shop_id = $_SESSION['shop_id'];

// ── Date range filter ────────────────────────────────────────
$range  = $_GET['range'] ?? '30';
$ranges = ['7'=>'Last 7 Days','30'=>'Last 30 Days','90'=>'Last 90 Days','365'=>'This Year'];
if (!array_key_exists($range, $ranges)) $range = '30';
$date_from = date('Y-m-d', strtotime("-{$range} days"));

// ── Revenue over time ────────────────────────────────────────
$group_by = $range <= 30 ? 'DATE(created_at)' : 'DATE_FORMAT(created_at,"%Y-%m")';
$rev_data = $conn->query("
    SELECT $group_by as period, COALESCE(SUM(total_amount),0) as revenue, COUNT(*) as orders
    FROM orders WHERE shop_id=$shop_id AND status!='cancelled' AND created_at >= '$date_from'
    GROUP BY period ORDER BY period
");
$chart_labels = $chart_revenue = $chart_orders = [];
while ($r = $rev_data->fetch_assoc()) {
    $label = $range <= 30 ? date('M j', strtotime($r['period'])) : date('M Y', strtotime($r['period'].'-01'));
    $chart_labels[]  = $label;
    $chart_revenue[] = (float)$r['revenue'];
    $chart_orders[]  = (int)$r['orders'];
}

// ── Summary cards ────────────────────────────────────────────
$period_rev = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM orders WHERE shop_id=$shop_id AND status!='cancelled' AND created_at>='$date_from'")->fetch_assoc()['t'];
$period_orders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE shop_id=$shop_id AND created_at>='$date_from'")->fetch_assoc()['c'];
$period_customers = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM orders WHERE shop_id=$shop_id AND created_at>='$date_from'")->fetch_assoc()['c'];
$avg_order = $period_orders > 0 ? ($period_rev / $period_orders) : 0;

// Previous period for comparison
$prev_from = date('Y-m-d', strtotime("-" . ($range*2) . " days"));
$prev_rev  = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM orders WHERE shop_id=$shop_id AND status!='cancelled' AND created_at>='$prev_from' AND created_at<'$date_from'")->fetch_assoc()['t'];
$rev_change = $prev_rev > 0 ? (($period_rev - $prev_rev) / $prev_rev * 100) : 0;

// ── Top products ─────────────────────────────────────────────
$top_prods = $conn->query("
    SELECT p.name, p.image, SUM(oi.quantity) as units, SUM(oi.quantity*oi.price) as revenue
    FROM order_items oi JOIN products p ON oi.product_id=p.id
    JOIN orders o ON oi.order_id=o.id
    WHERE p.shop_id=$shop_id AND o.created_at>='$date_from'
    GROUP BY p.id ORDER BY revenue DESC LIMIT 8
");

// ── Category revenue ─────────────────────────────────────────
$cat_rev = $conn->query("
    SELECT c.name, COALESCE(SUM(oi.quantity*oi.price),0) as revenue
    FROM categories c LEFT JOIN products p ON p.category_id=c.id
    LEFT JOIN order_items oi ON oi.product_id=p.id
    LEFT JOIN orders o ON oi.order_id=o.id AND o.created_at>='$date_from'
    WHERE c.shop_id=$shop_id GROUP BY c.id ORDER BY revenue DESC
");
$cat_names = $cat_revenues = [];
while ($r = $cat_rev->fetch_assoc()) {
    $cat_names[]    = $r['name'];
    $cat_revenues[] = (float)$r['revenue'];
}

// ── Orders by hour (heatmap data) ───────────────────────────
$hourly = $conn->query("SELECT HOUR(created_at) as hr, COUNT(*) as c FROM orders WHERE shop_id=$shop_id AND created_at>='$date_from' GROUP BY hr");
$hour_data = array_fill(0, 24, 0);
while ($r = $hourly->fetch_assoc()) $hour_data[$r['hr']] = (int)$r['c'];

// ── Recent customers ─────────────────────────────────────────
$new_customers = $conn->query("
    SELECT u.name, u.email, u.created_at,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id AND o.shop_id=$shop_id) as order_count,
        (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id=u.id AND o.shop_id=$shop_id AND o.status!='cancelled') as total_spent
    FROM users u WHERE u.shop_id=$shop_id ORDER BY u.created_at DESC LIMIT 8
");
?>

<!-- Range Selector -->
<div class="animate-in" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;align-items:center;justify-content:space-between;">
    <div style="font-size:13px;color:var(--text-muted);">Period: <strong style="color:var(--text-primary);"><?= $ranges[$range] ?></strong></div>
    <div style="display:flex;gap:6px;">
        <?php foreach ($ranges as $val => $label): ?>
        <a href="?range=<?= $val ?>" style="padding:6px 13px;border-radius:6px;font-size:12.5px;text-decoration:none;border:1px solid;transition:all 0.15s ease-in-out;
            <?= $range == $val ? 'background:#1E293B;border-color:#1E293B;color:#FFFFFF;font-weight:600;' : 'background:#FFFFFF;border-color:#CBD5E1;color:var(--text-secondary);' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Summary Cards ── -->
<div class="row g-3 animate-in mb-3">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-value">&#8377;<?= number_format($period_rev, 0) ?></div>
            <div class="stat-label">Period Revenue</div>
            <span class="stat-trend <?= $rev_change >= 0 ? 'trend-up' : 'trend-down' ?>">
                <i class="bi bi-arrow-<?= $rev_change >= 0 ? 'up' : 'down' ?>-short"></i>
                <?= abs(round($rev_change, 1)) ?>% vs prev period
            </span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#F0F9FF;color:#0EA5E9;"><i class="bi bi-bag-check"></i></div>
            <div class="stat-value"><?= number_format($period_orders) ?></div>
            <div class="stat-label">Period Orders</div>
            <span class="stat-trend trend-neutral"><i class="bi bi-receipt"></i> <?= $ranges[$range] ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#ECFDF5;color:#10B981;"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= number_format($period_customers) ?></div>
            <div class="stat-label">Active Buyers</div>
            <span class="stat-trend trend-up"><i class="bi bi-person-check"></i> Ordered in period</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#F5F3FF;color:#8B5CF6;"><i class="bi bi-basket"></i></div>
            <div class="stat-value">&#8377;<?= number_format($avg_order, 0) ?></div>
            <div class="stat-label">Avg Order Value</div>
            <span class="stat-trend trend-neutral"><i class="bi bi-calculator"></i> Per order average</span>
        </div>
    </div>
</div>

<!-- ── Revenue & Orders Chart ── -->
<div class="card-glass animate-in d2 mb-4">
    <div class="section-head">
        <div>
            <div class="section-title">Revenue & Order Trends</div>
            <div class="section-sub"><?= $ranges[$range] ?> performance breakdown</div>
        </div>
        <div style="display:flex;gap:16px;font-size:12px;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--text-secondary);">
                <span style="width:14px;height:3px;background:#2563EB;display:inline-block;border-radius:2px;"></span> Revenue (&#8377;)
            </span>
            <span style="display:flex;align-items:center;gap:6px;color:var(--text-secondary);">
                <span style="width:14px;height:3px;background:#0EA5E9;display:inline-block;border-radius:2px;"></span> Orders
            </span>
        </div>
    </div>
    <?php if (empty($chart_labels)): ?>
    <div class="empty-state" style="padding:40px;">
        <i class="bi bi-bar-chart"></i>
        <p>No order data recorded in this period.</p>
    </div>
    <?php else: ?>
    <canvas id="revChart" height="100"></canvas>
    <?php endif; ?>
</div>

<div class="row g-3 animate-in d3 mb-4">
    <!-- Top Products by Revenue -->
    <div class="col-lg-6">
        <div class="card-glass" style="height:100%;">
            <div class="section-head">
                <div>
                    <div class="section-title">Top Products by Revenue</div>
                    <div class="section-sub"><?= $ranges[$range] ?> best sellers</div>
                </div>
            </div>
            <?php if ($top_prods->num_rows === 0): ?>
            <div class="empty-state" style="padding:32px;">
                <i class="bi bi-box-seam"></i>
                <p>No product sales in this period.</p>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php $rank=1; while ($p = $top_prods->fetch_assoc()): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:8px 10px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;">
                    <span style="font-weight:700;font-size:12.5px;color:var(--primary);width:20px;">#<?= $rank++ ?></span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['name']) ?></div>
                        <div style="font-size:11.5px;color:var(--text-muted);"><?= number_format($p['units']) ?> units sold</div>
                    </div>
                    <div style="font-weight:700;font-size:13.5px;color:var(--text-primary);">&#8377;<?= number_format($p['revenue'],0) ?></div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="col-lg-6">
        <div class="card-glass" style="height:100%;">
            <div class="section-head">
                <div>
                    <div class="section-title">Category Share</div>
                    <div class="section-sub"><?= $ranges[$range] ?> revenue by category</div>
                </div>
            </div>
            <?php if (empty($cat_names) || array_sum($cat_revenues) == 0): ?>
            <div class="empty-state" style="padding:32px;">
                <i class="bi bi-pie-chart"></i>
                <p>No category revenue data yet.</p>
            </div>
            <?php else: ?>
            <canvas id="catChart" height="180"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 animate-in d4">
    <!-- Peak Hours Heatmap -->
    <div class="col-lg-6">
        <div class="card-glass">
            <div class="section-head">
                <div>
                    <div class="section-title">Peak Order Hours</div>
                    <div class="section-sub">Order frequency distribution across 24h</div>
                </div>
            </div>
            <canvas id="hourChart" height="130"></canvas>
        </div>
    </div>

    <!-- Top Customers -->
    <div class="col-lg-6">
        <div class="card-glass">
            <div class="section-head">
                <div>
                    <div class="section-title">Recent Customers</div>
                    <div class="section-sub">Latest registered store buyers</div>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($new_customers->num_rows === 0): ?>
                        <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text-muted);">No customers registered yet.</td></tr>
                        <?php else: while ($cust = $new_customers->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;font-size:13px;color:var(--text-primary);"><?= htmlspecialchars($cust['name']) ?></div>
                                <div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($cust['email']) ?></div>
                            </td>
                            <td><span style="font-weight:600;"><?= $cust['order_count'] ?></span></td>
                            <td style="font-weight:700;">&#8377;<?= number_format($cust['total_spent'], 2) ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$labels_json  = json_encode($chart_labels);
$rev_json     = json_encode($chart_revenue);
$ord_json     = json_encode($chart_orders);
$cat_n_json   = json_encode($cat_names);
$cat_r_json   = json_encode($cat_revenues);
$hour_json    = json_encode(array_values($hour_data));

$extra_scripts = '
<script>
(function() {
    if (typeof Chart === "undefined") return;

    // ── Revenue & Orders Chart ────────────────────────────────
    const rCtx = document.getElementById("revChart");
    if (rCtx) {
        new Chart(rCtx, {
            type: "line",
            data: {
                labels: ' . $labels_json . ',
                datasets: [
                    {
                        label: "Revenue (₹)",
                        data: ' . $rev_json . ',
                        borderColor: "#2563EB",
                        backgroundColor: "rgba(37, 99, 235, 0.05)",
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        yAxisID: "y"
                    },
                    {
                        label: "Orders",
                        data: ' . $ord_json . ',
                        borderColor: "#0EA5E9",
                        backgroundColor: "transparent",
                        borderWidth: 2,
                        borderDash: [4, 4],
                        tension: 0.35,
                        yAxisID: "y1"
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: "#F1F5F9" }, ticks: { color: "#64748B", font: { size: 11, family: "Plus Jakarta Sans" } } },
                    y: {
                        type: "linear", display: true, position: "left",
                        grid: { color: "#F1F5F9" },
                        ticks: { color: "#64748B", font: { size: 11, family: "Plus Jakarta Sans" }, callback: v => "₹" + v.toLocaleString("en-IN") }
                    },
                    y1: {
                        type: "linear", display: true, position: "right",
                        grid: { drawOnChartArea: false },
                        ticks: { color: "#0EA5E9", font: { size: 11, family: "Plus Jakarta Sans" }, precision: 0 }
                    }
                }
            }
        });
    }

    // ── Category Chart ────────────────────────────────────────
    const cCtx = document.getElementById("catChart");
    if (cCtx) {
        const catNames = ' . $cat_n_json . ';
        const catRevs  = ' . $cat_r_json . ';
        new Chart(cCtx, {
            type: "doughnut",
            data: {
                labels: catNames,
                datasets: [{
                    data: catRevs,
                    backgroundColor: ["#2563EB","#10B981","#F59E0B","#0EA5E9","#8B5CF6","#EC4899","#64748B"],
                    borderWidth: 2,
                    borderColor: "#FFFFFF"
                }]
            },
            options: {
                responsive: true,
                cutout: "70%",
                plugins: {
                    legend: { position: "right", labels: { boxWidth: 12, font: { size: 11, family: "Plus Jakarta Sans" }, color: "#1E293B" } }
                }
            }
        });
    }

    // ── Peak Hours Chart ──────────────────────────────────────
    const hCtx = document.getElementById("hourChart");
    if (hCtx) {
        const hourData = ' . $hour_json . ';
        const hourLabels = Array.from({length:24}, (_,i) => i === 0 ? "12 AM" : (i < 12 ? i + " AM" : (i === 12 ? "12 PM" : (i-12) + " PM")));
        new Chart(hCtx, {
            type: "bar",
            data: {
                labels: hourLabels,
                datasets: [{
                    label: "Orders",
                    data: hourData,
                    backgroundColor: "#2563EB",
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: "#64748B", font: { size: 10, family: "Plus Jakarta Sans" } } },
                    y: { grid: { color: "#F1F5F9" }, ticks: { color: "#64748B", font: { size: 11, family: "Plus Jakarta Sans" }, precision: 0 } }
                }
            }
        });
    }
})();
</script>';

require 'includes/footer.php';
?>
