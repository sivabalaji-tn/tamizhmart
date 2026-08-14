<?php
session_start();
require '../config/db.php';
// ── This script is made by Siva Balaji sms ──────────────────────
$page_title    = 'Command Console';
$page_subtitle = 'Global platform operations & real-time telemetry';

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
<div class="animate-in" style="background:linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(6, 182, 212, 0.06)); border:1px solid rgba(59, 130, 246, 0.2); border-radius:var(--radius); padding:22px 28px; margin-bottom:24px; display:flex; align-items:center; gap:24px; flex-wrap:wrap; position:relative; overflow:hidden; backdrop-filter:blur(12px);">
    <div style="position:absolute; right:-40px; top:-40px; width:200px; height:200px; background:radial-gradient(circle, rgba(6,182,212,0.1) 0%, transparent 70%); pointer-events:none;"></div>
    <div>
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
            <span style="font-size:11px; color:var(--cyan-neon); font-weight:700; letter-spacing:1px; text-transform:uppercase; font-family:'JetBrains Mono',monospace;">REAL-TIME TELEMETRY &middot; <?= date('d M Y, H:i T') ?></span>
        </div>
        <div style="font-family:'Syne',sans-serif; font-weight:800; font-size:22px; color:#fff;">Welcome back, <?= htmlspecialchars($_SESSION['superadmin_name']) ?></div>
        <div style="font-size:12.5px; color:var(--muted); margin-top:2px;">All platform microservices operating at optimal latency.</div>
    </div>
    <div style="margin-left:auto; display:flex; gap:24px; flex-wrap:wrap;">
        <div style="padding:10px 18px; background:rgba(15, 23, 42, 0.6); border:1px solid rgba(59, 130, 246, 0.15); border-radius:10px; text-align:center; min-width:110px;">
            <div style="font-family:'Syne',sans-serif; font-weight:800; font-size:20px; color:var(--accent-bright);"><?= $today_orders ?></div>
            <div style="font-size:11px; color:var(--muted); font-weight:500; margin-top:2px;">Today's Orders</div>
        </div>
        <div style="padding:10px 18px; background:rgba(15, 23, 42, 0.6); border:1px solid rgba(16, 185, 129, 0.2); border-radius:10px; text-align:center; min-width:130px;">
            <div style="font-family:'Syne',sans-serif; font-weight:800; font-size:20px; color:var(--success);">&#8377;<?= number_format($today_revenue, 0) ?></div>
            <div style="font-size:11px; color:var(--muted); font-weight:500; margin-top:2px;">Today's Gross</div>
        </div>
        <div style="padding:10px 18px; background:rgba(15, 23, 42, 0.6); border:1px solid rgba(6, 182, 212, 0.2); border-radius:10px; text-align:center; min-width:110px;">
            <div style="font-family:'Syne',sans-serif; font-weight:800; font-size:20px; color:var(--cyan-neon);"><?= $today_signups ?></div>
            <div style="font-size:11px; color:var(--muted); font-weight:500; margin-top:2px;">New Users</div>
        </div>
    </div>
</div>

<!-- ── Stat Cards ── -->
<div class="row g-3 animate-in d1" style="margin-bottom:24px;">
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(59, 130, 246, 0.1);">
            <div class="stat-icon" style="background:var(--accent-glow); color:var(--accent-bright);"><i class="bi bi-shop-window"></i></div>
            <div>
                <div class="stat-val"><?= $total_shops ?></div>
                <div class="stat-label">Merchant Network</div>
                <div class="stat-change" style="color:var(--success);"><i class="bi bi-check-circle-fill"></i> <?= $active_shops ?> Online &middot; <?= $suspended_shops ?> Suspended</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(6, 182, 212, 0.1);">
            <div class="stat-icon" style="background:var(--cyan-glow); color:var(--cyan-neon);"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-val"><?= number_format($total_customers) ?></div>
                <div class="stat-label">Global Userbase</div>
                <div class="stat-change" style="color:var(--muted);"><i class="bi bi-person-badge"></i> <?= $total_owners ?> Merchant Owners</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(245, 158, 11, 0.1);">
            <div class="stat-icon" style="background:var(--warning-dim); color:var(--warning);"><i class="bi bi-box-seam-fill"></i></div>
            <div>
                <div class="stat-val"><?= number_format($total_orders) ?></div>
                <div class="stat-label">Global Volume</div>
                <div class="stat-change" style="color:var(--warning);"><i class="bi bi-hourglass-split"></i> <?= $pending_orders ?> Processing</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="--glow-color:rgba(16, 185, 129, 0.1);">
            <div class="stat-icon" style="background:var(--success-dim); color:var(--success);"><i class="bi bi-currency-rupee"></i></div>
            <div>
                <div class="stat-val">&#8377;<?= number_format($total_revenue, 0) ?></div>
                <div class="stat-label">Network Revenue</div>
                <div class="stat-change" style="color:var(--muted);"><i class="bi bi-tags-fill"></i> <?= number_format($total_products) ?> Live Products</div>
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
                    <div class="section-title"><i class="bi bi-graph-up-arrow me-1" style="color:var(--accent-bright);"></i> Network Gross Volume</div>
                    <div class="section-sub">Rolling 14-day aggregated throughput across all merchants</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--muted); text-transform:uppercase;">14D Total</div>
                    <div style="font-family:'Syne',sans-serif; font-weight:800; font-size:19px; color:var(--accent-bright);">
                        &#8377;<?= number_format(array_sum($chart_data), 0) ?>
                    </div>
                </div>
            </div>
            <canvas id="revenueChart" height="85"></canvas>
        </div>
    </div>

    <!-- Order Status Donut -->
    <div class="col-lg-4">
        <div class="card-glass animate-in d2" style="height:100%;">
            <div class="section-title" style="margin-bottom:4px;"><i class="bi bi-pie-chart-fill me-1" style="color:var(--cyan-neon);"></i> Fulfillment Status</div>
            <div class="section-sub" style="margin-bottom:18px;">Global order lifecycle breakdown</div>
            <canvas id="statusChart" height="150"></canvas>
            <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
                <?php
                $status_colors = ['pending'=>'#f59e0b','processing'=>'#0ea5e9','out_for_delivery'=>'#3b82f6','delivered'=>'#10b981','cancelled'=>'#ef4444'];
                $status_labels = ['pending'=>'Pending','processing'=>'Processing','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                foreach ($status_counts as $st => $count):
                ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                    <div style="width:9px;height:9px;border-radius:50%;background:<?= $status_colors[$st] ?>;flex-shrink:0;"></div>
                    <span style="color:var(--muted);flex:1;"><?= $status_labels[$st] ?></span>
                    <span style="font-weight:700; font-family:'JetBrains Mono',monospace; color:#e2e8f0;"><?= number_format($count) ?></span>
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
                    <div class="section-title"><i class="bi bi-trophy-fill me-1" style="color:#f59e0b;"></i> Top Performing Merchants</div>
                    <div class="section-sub">Highest grossing stores on network</div>
                </div>
                <a href="shops.php" class="btn-ghost-custom" style="font-size:11.5px;padding:5px 12px;">View All</a>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php $rank = 1; while ($s = $top_shops->fetch_assoc()): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(15, 23, 42, 0.5);border-radius:10px;border:1px solid rgba(59, 130, 246, 0.1);">
                    <div style="width:30px;height:30px;border-radius:8px;background:var(--accent-glow);color:var(--accent-bright);font-family:'JetBrains Mono',monospace;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(59,130,246,0.2);">#<?= $rank++ ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#fff;"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="font-size:11.5px;color:var(--muted);"><?= number_format($s['orders']) ?> orders fulfilled</div>
                    </div>
                    <div style="font-family:'Syne',sans-serif;font-weight:800;color:var(--success);font-size:14px;">&#8377;<?= number_format($s['revenue'], 0) ?></div>
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
                    <div class="section-title"><i class="bi bi-plus-circle-fill me-1" style="color:var(--cyan-neon);"></i> Merchant Onboarding Stream</div>
                    <div class="section-sub">Latest stores onboarded</div>
                </div>
                <a href="shops.php" class="btn-ghost-custom" style="font-size:11.5px;padding:5px 12px;">View All</a>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php while ($s = $recent_shops->fetch_assoc()): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(15, 23, 42, 0.5);border-radius:10px;border:1px solid rgba(59, 130, 246, 0.1);">
                    <div style="width:36px;height:36px;border-radius:8px;overflow:hidden;background:var(--accent-glow);display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(59, 130, 246, 0.2);">
                        <?php if ($s['logo']): ?>
                        <img src="../assets/uploads/logos/<?= htmlspecialchars($s['logo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                        <i class="bi bi-shop" style="color:var(--accent-bright);font-size:16px;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:#fff;"><?= htmlspecialchars($s['name']) ?></div>
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

<?php require __DIR__ . '/includes/footer.php'; ?>

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
            borderColor: '#3b82f6',
            backgroundColor: (context) => {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
                gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
                return gradient;
            },
            borderWidth: 2.5,
            pointRadius: 3,
            pointBackgroundColor: '#60a5fa',
            pointBorderColor: '#0b0f19',
            pointBorderWidth: 2,
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#64748b', maxTicksLimit: 7, font: { family: 'Plus Jakarta Sans', size: 11 } }, grid: { color: 'rgba(59, 130, 246, 0.06)' } },
            y: { ticks: { color: '#64748b', callback: v => '₹' + v.toLocaleString('en-IN'), font: { family: 'Plus Jakarta Sans', size: 11 } }, grid: { color: 'rgba(59, 130, 246, 0.06)' } }
        }
    }
});

// Status donut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{ data: statusValues, backgroundColor: statusColors, borderWidth: 3, borderColor: '#0f172a' }]
    },
    options: {
        responsive: true,
        cutout: '74%',
        plugins: { legend: { display: false } }
    }
});
</script>