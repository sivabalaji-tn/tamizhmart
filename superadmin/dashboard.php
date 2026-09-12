<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
$page_title    = 'Overview';
$page_subtitle = 'Sales, shops and customer activity across your platform';

require __DIR__ . '/includes/sidebar.php';

// ── Platform Stats ────────────────────────────────────────────
$total_shops     = $conn->query("SELECT COUNT(*) FROM shops")->fetch_row()[0];
$active_shops    = $conn->query("SELECT COUNT(*) FROM shops WHERE is_active=1 AND (is_suspended IS NULL OR is_suspended=0)")->fetch_row()[0];
$suspended_shops = $conn->query("SELECT COUNT(*) FROM shops WHERE is_suspended=1")->fetch_row()[0];
$total_owners    = $conn->query("SELECT COUNT(*) FROM owners")->fetch_row()[0];
$total_customers = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_orders    = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$total_revenue   = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled'")->fetch_row()[0];
$total_products  = $conn->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetch_row()[0];
$pending_orders  = $conn->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetch_row()[0];

// ── Revenue last 14 days ──────────────────────────────────────
$chart_labels = $chart_data = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d M', strtotime("-$i days"));
    $rev = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)='$date' AND status!='cancelled'")->fetch_row()[0];
    $chart_labels[] = $label;
    $chart_data[]   = (float)$rev;
}

// ── Today stats ───────────────────────────────────────────────
$today = date('Y-m-d');
$today_orders  = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today'")->fetch_row()[0];
$today_revenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)='$today' AND status!='cancelled'")->fetch_row()[0];
$today_signups = $conn->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)='$today'")->fetch_row()[0];

// ── Recently registered shops ─────────────────────────────────
$recent_shops = $conn->query("
    SELECT s.*, o.name as owner_name, o.email as owner_email,
           COUNT(DISTINCT p.id) as product_count,
           COUNT(DISTINCT ord.id) as order_count
    FROM shops s
    JOIN owners o ON s.owner_id = o.id
    LEFT JOIN products p ON p.shop_id = s.id
    LEFT JOIN orders ord ON ord.shop_id = s.id
    GROUP BY s.id
    ORDER BY s.created_at DESC LIMIT 6
");

// ── Top shops by revenue ──────────────────────────────────────
$top_shops = $conn->query("
    SELECT s.name, s.slug, s.theme_primary,
           COALESCE(SUM(o.total_amount),0) as revenue,
           COUNT(o.id) as orders
    FROM shops s
    LEFT JOIN orders o ON o.shop_id = s.id AND o.status != 'cancelled'
    GROUP BY s.id
    ORDER BY revenue DESC LIMIT 5
");

// ── Orders by status ──────────────────────────────────────────
$status_counts = [];
$statuses = ['pending','processing','out_for_delivery','delivered','cancelled'];
foreach ($statuses as $st) {
    $status_counts[$st] = $conn->query("SELECT COUNT(*) FROM orders WHERE status='$st'")->fetch_row()[0];
}
?>

<!-- ── Live System Telemetry Banner ── -->
<section class="dashboard-today" aria-label="Today's activity">
    <div><h2>Today's activity</h2><p class="section-sub">Orders and registrations for <?= date('d M Y') ?></p></div>
    <div class="today-metrics">
        <div><div class="today-value"><?= number_format($today_orders) ?></div><div class="today-label">Orders today</div></div>
        <div><div class="today-value">&#8377;<?= number_format($today_revenue, 0) ?></div><div class="today-label">Revenue today</div></div>
        <div><div class="today-value"><?= number_format($today_signups) ?></div><div class="today-label">New customers</div></div>
    </div>
</section>

<!-- ── Stat Cards ── -->
<div class="row g-3 animate-in d1" style="margin-bottom:24px;">
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(59, 130, 246, 0.1);">
            <div class="stat-icon" style="background:var(--accent-glow); color:var(--accent-bright);"><i class="bi bi-shop-window"></i></div>
            <div>
                <div class="stat-val"><?= $total_shops ?></div>
                <div class="stat-label">Shops</div>
                <div class="stat-change" style="color:var(--success);"><i class="bi bi-check-circle-fill"></i> <?= $active_shops ?> active &middot; <?= $suspended_shops ?> suspended</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(6, 182, 212, 0.1);">
            <div class="stat-icon" style="background:var(--cyan-glow); color:var(--cyan-neon);"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-val"><?= number_format($total_customers) ?></div>
                <div class="stat-label">Customers</div>
                <div class="stat-change" style="color:var(--muted);"><i class="bi bi-person-badge"></i> <?= $total_owners ?> shop owners</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(245, 158, 11, 0.1);">
            <div class="stat-icon" style="background:var(--warning-dim); color:var(--warning);"><i class="bi bi-box-seam-fill"></i></div>
            <div>
                <div class="stat-val"><?= number_format($total_orders) ?></div>
                <div class="stat-label">Orders</div>
                <div class="stat-change" style="color:var(--warning);"><i class="bi bi-hourglass-split"></i> <?= $pending_orders ?> pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(16, 185, 129, 0.1);">
            <div class="stat-icon" style="background:var(--success-dim); color:var(--success);"><i class="bi bi-currency-rupee"></i></div>
            <div>
                <div class="stat-val">&#8377;<?= number_format($total_revenue, 0) ?></div>
                <div class="stat-label">Total revenue</div>
                <div class="stat-change" style="color:var(--muted);"><i class="bi bi-tags-fill"></i> <?= number_format($total_products) ?> products</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card-glass animate-in d2" style="height:100%;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <div>
                    <div class="section-title"><i class="bi bi-graph-up-arrow me-1" style="color:var(--accent-bright);"></i> Sales revenue</div>
                    <div class="section-sub">Daily sales across all shops over the last 14 days</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-family:var(--font-ui); font-size:10px; color:var(--muted); text-transform:uppercase;">14D Total</div>
                    <div style="font-family:var(--font-ui); font-weight:650; font-size:19px; color:var(--accent-bright);">
                        &#8377;<?= number_format(array_sum($chart_data), 0) ?>
                    </div>
                </div>
            </div>
            <div class="chart-frame"><canvas id="revenueChart" aria-label="Revenue over the last 14 days" role="img"></canvas></div>
        </div>
    </div>

    <!-- Order Status Donut -->
    <div class="col-lg-4">
        <div class="card-glass animate-in d2" style="height:100%;">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-pie-chart-fill me-1" style="color:var(--cyan-neon);"></i> Fulfillment Status</div>
            <div class="section-sub" style="margin-bottom:18px;">Orders by current status</div>
            <div class="chart-frame chart-frame-donut"><canvas id="statusChart" aria-label="Orders by status" role="img"></canvas></div>
            <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
                <?php
                $status_colors = ['pending'=>'#f59e0b','processing'=>'#0ea5e9','out_for_delivery'=>'#3b82f6','delivered'=>'#10b981','cancelled'=>'#ef4444'];
                $status_labels = ['pending'=>'Pending','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                foreach ($status_counts as $st => $count):
                ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                    <div style="width:9px;height:9px;border-radius:50%;background:<?= $status_colors[$st] ?>;flex-shrink:0;"></div>
                    <span style="color:var(--muted);flex:1;"><?= $status_labels[$st] ?></span>
                    <span style="font-weight:700; font-family:var(--font-ui); color:var(--text);"><?= number_format($count) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Top Shops -->
    <div class="col-lg-6">
        <div class="card-glass animate-in d3">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div>
                    <div class="section-title"><i class="bi bi-trophy-fill me-1" style="color:#f59e0b;"></i> Top shops by revenue</div>
                    <div class="section-sub">Sales performance across your shops</div>
                </div>
                <a href="shops.php" class="btn-ghost-custom" style="font-size:11.5px;padding:5px 12px;">View All</a>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php $rank = 1; while ($s = $top_shops->fetch_assoc()): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface-soft);border-radius:4px;border:1px solid rgba(59, 130, 246, 0.1);">
                    <div style="width:30px;height:30px;border-radius:8px;background:var(--accent-glow);color:var(--accent-bright);font-family:var(--font-ui);font-weight:650;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(59,130,246,0.2);">#<?= $rank++ ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text);"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="font-size:11.5px;color:var(--muted);"><?= number_format($s['orders']) ?> orders</div>
                    </div>
                    <div style="font-family:var(--font-ui);font-weight:650;color:var(--success);font-size:14px;">&#8377;<?= number_format($s['revenue'], 0) ?></div>
                    <a href="../shop/index.php?shop=<?= $s['slug'] ?>" target="_blank" class="btn-ghost-custom" style="padding:5px 9px;font-size:11px;">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Recently Registered Shops -->
    <div class="col-lg-6">
        <div class="card-glass animate-in d3">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div>
                    <div class="section-title"><i class="bi bi-plus-circle-fill me-1" style="color:var(--cyan-neon);"></i> Recently added shops</div>
                    <div class="section-sub">Latest shop registrations</div>
                </div>
                <a href="shops.php" class="btn-ghost-custom" style="font-size:11.5px;padding:5px 12px;">View All</a>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php while ($s = $recent_shops->fetch_assoc()): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface-soft);border-radius:4px;border:1px solid rgba(59, 130, 246, 0.1);">
                    <div style="width:36px;height:36px;border-radius:8px;overflow:hidden;background:var(--accent-glow);display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(59, 130, 246, 0.2);">
                        <?php if ($s['logo']): ?>
                        <img src="../assets/uploads/logos/<?= htmlspecialchars($s['logo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                        <i class="bi bi-shop" style="color:var(--accent-bright);font-size:16px;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--text);"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="font-size:11.5px;color:var(--muted);"><?= htmlspecialchars($s['owner_name']) ?> &middot; <?= $s['product_count'] ?> products</div>
                    </div>
                    <div>
                        <?php if ($s['is_suspended'] ?? 0): ?>
                        <span class="badge-custom badge-danger"><i class="bi bi-slash-circle-fill"></i> Suspended</span>
                        <?php elseif ($s['is_active']): ?>
                        <span class="badge-custom badge-success"><i class="bi bi-check-circle-fill"></i> Active</span>
                        <?php else: ?>
                        <span class="badge-custom badge-warning"><i class="bi bi-pause-circle-fill"></i> Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>



<script>
const chartLabels  = <?= json_encode($chart_labels) ?>;
const chartRevenue = <?= json_encode($chart_data) ?>;
const statusLabels = <?= json_encode(array_values(array_map(fn($s) => ucfirst(str_replace('_',' ',$s)), $statuses))) ?>;
const statusValues = <?= json_encode(array_values($status_counts)) ?>;
const statusColors = <?= json_encode(array_values($status_colors)) ?>;

// Revenue chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Revenue (₹)',
            data: chartRevenue,
            borderColor: '#256b73',
            backgroundColor: (context) => {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(37, 107, 115, 0.12)');
                gradient.addColorStop(1, 'rgba(37, 107, 115, 0.01)');
                return gradient;
            },
            borderWidth: 2.5,
            pointRadius: 3,
            pointBackgroundColor: '#256b73',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#64748b', maxTicksLimit: 7, font: { family: 'Segoe UI', size: 11 } }, grid: { color: 'rgba(36, 41, 47, 0.06)' } },
            y: { ticks: { color: '#64748b', callback: v => '₹' + v.toLocaleString('en-IN'), font: { family: 'Segoe UI', size: 11 } }, grid: { color: 'rgba(36, 41, 47, 0.06)' } }
        }
    }
});

// Status donut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{ data: statusValues, backgroundColor: statusColors, borderWidth: 3, borderColor: '#ffffff' }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '74%',
        plugins: { legend: { display: false } }
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
