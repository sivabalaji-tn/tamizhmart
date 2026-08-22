<?php
/**
 * TamizhMart Super Admin — Commission & Subscription Logs
 * Full audit trail: subscription activations, renewals, commission collections, per-order log.
 */
$page_title = 'Commission & Subscription Logs';
session_start();
require '../config/db.php';
if (empty($_SESSION['superadmin_id'])) { header('Location: login.php'); exit; }

// ── Filters ───────────────────────────────────────────────────────
$filter_shop = intval($_GET['shop'] ?? 0);
$tab         = $_GET['tab'] ?? 'subscriptions';
$date_from   = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
$date_to     = $_GET['to']   ?? date('Y-m-d');
$df          = $date_from . ' 00:00:00';
$dt          = $date_to   . ' 23:59:59';
$shop_cond   = $filter_shop ? "AND shop_id=$filter_shop" : '';

// ── Subscription History (all rows including completed) ───────────
$sub_history = $conn->query("
    SELECT ss.*, s.name AS shop_name, p.name AS plan_name, p.price AS plan_price, p.commission_rate,
           o.email AS owner_email,
           sa.name AS activated_by_name
    FROM shop_subscriptions ss
    JOIN shops s ON ss.shop_id = s.id
    JOIN plans p ON ss.plan_id = p.id
    JOIN owners o ON s.owner_id = o.id
    LEFT JOIN super_admins sa ON sa.id = ss.activated_by
    WHERE ss.created_at BETWEEN '$df' AND '$dt'
    " . ($filter_shop ? "AND ss.shop_id=$filter_shop" : "") . "
    ORDER BY ss.created_at DESC
    LIMIT 300
")->fetch_all(MYSQLI_ASSOC);

// ── Commission Collections ────────────────────────────────────────
$collections = $conn->query("
    SELECT cc.*, s.name AS shop_name, p.name AS plan_name,
           sa.name AS collected_by_name,
           ss.started_at AS sub_start, ss.expires_at AS sub_end
    FROM commission_collections cc
    JOIN shops s ON cc.shop_id = s.id
    LEFT JOIN shop_subscriptions ss ON ss.id = cc.subscription_id
    LEFT JOIN plans p ON ss.plan_id = p.id
    LEFT JOIN super_admins sa ON sa.id = cc.collected_by
    WHERE cc.created_at BETWEEN '$df' AND '$dt'
    " . ($filter_shop ? "AND cc.shop_id=$filter_shop" : "") . "
    ORDER BY cc.created_at DESC
    LIMIT 300
")->fetch_all(MYSQLI_ASSOC);

// ── Per-order Commission Log ──────────────────────────────────────
$order_log = $conn->query("
    SELECT cl.*, s.name AS shop_name
    FROM commission_log cl
    JOIN shops s ON cl.shop_id = s.id
    WHERE cl.created_at BETWEEN '$df' AND '$dt'
    " . ($filter_shop ? "AND cl.shop_id=$filter_shop" : "") . "
    ORDER BY cl.created_at DESC
    LIMIT 500
")->fetch_all(MYSQLI_ASSOC);

// ── Summary stats ─────────────────────────────────────────────────
$total_collected_all = $conn->query("SELECT COALESCE(SUM(commission_amount),0) FROM commission_collections")->fetch_row()[0];
$total_pending_all   = $conn->query("SELECT COALESCE(SUM(commission_amount),0) FROM commission_log WHERE collected=0")->fetch_row()[0];
$total_activations   = $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE created_at BETWEEN '$df' AND '$dt'")->fetch_row()[0];
$total_collections   = count($collections);

$shops_list = $conn->query("SELECT s.id, s.name FROM shops s WHERE s.is_active=1 ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/includes/sidebar.php';
?>
<style>
.log-table{width:100%;border-collapse:collapse;font-size:13px;}
.log-table th{padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);background:var(--card-border);font-weight:700;}
.log-table td{padding:11px 14px;border-top:1px solid var(--card-border);}
.log-table tr:hover td{background:rgba(255,255,255,.02);}
.tab-bar{display:flex;gap:4px;border-bottom:2px solid var(--card-border);margin-bottom:20px;}
.tab-btn{padding:10px 18px;border:none;background:none;cursor:pointer;font-size:13.5px;font-weight:600;color:var(--muted);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:7px;}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
.tab-btn:hover:not(.active){color:var(--text);}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:700;}
.pill-active{background:rgba(16,185,129,.12);color:#059669;}
.pill-trial{background:rgba(251,191,36,.12);color:#d97706;}
.pill-grace{background:rgba(249,115,22,.12);color:#ea580c;}
.pill-suspended{background:rgba(239,68,68,.12);color:#dc2626;}
.pill-completed{background:rgba(107,114,128,.1);color:var(--muted);}
.pill-cancelled{background:rgba(107,114,128,.1);color:var(--muted);}
.pill-collected{background:rgba(16,185,129,.12);color:#059669;}
.pill-pending{background:rgba(234,88,12,.12);color:#ea580c;}
.empty-state{text-align:center;padding:48px;color:var(--muted);}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-journal-text" style="color:var(--accent);"></i> Commission &amp; Subscription Logs</h1>
        <p class="page-sub">Full audit trail — subscription activations, renewals, commission settlements and per-order log</p>
    </div>
    <a href="subscriptions.php" class="btn-ghost-custom" style="padding:10px 16px;">
        <i class="bi bi-arrow-left"></i> Back to Subscriptions
    </a>
</div>

<!-- Summary Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px;" class="animate-in">
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:16px 20px;text-align:center;">
        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:#059669;">&#8377;<?= number_format($total_collected_all,2) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;">Total Collected (All Time)</div>
    </div>
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:16px 20px;text-align:center;">
        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:#ea580c;">&#8377;<?= number_format($total_pending_all,2) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;">Currently Pending</div>
    </div>
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:16px 20px;text-align:center;">
        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:var(--accent);"><?= $total_activations ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;">Activations (filtered period)</div>
    </div>
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:16px 20px;text-align:center;">
        <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:#ca8a04;"><?= $total_collections ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;">Collections (filtered period)</div>
    </div>
</div>

<!-- Filters -->
<div class="card-glass animate-in" style="margin-bottom:20px;padding:16px 20px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div>
            <label style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:5px;">Shop</label>
            <select name="shop" class="input-custom" style="width:200px;">
                <option value="0">All Shops</option>
                <?php foreach ($shops_list as $sh): ?>
                <option value="<?= $sh['id'] ?>" <?= $filter_shop==$sh['id']?'selected':'' ?>><?= htmlspecialchars($sh['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:5px;">From</label>
            <input type="date" name="from" class="input-custom" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div>
            <label style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);display:block;margin-bottom:5px;">To</label>
            <input type="date" name="to" class="input-custom" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Apply</button>
        <a href="commission_logs.php" class="btn-ghost-custom" style="padding:10px 16px;">Reset</a>
    </form>
</div>

<!-- Tabs -->
<div class="tab-bar">
    <a href="?tab=subscriptions&shop=<?= $filter_shop ?>&from=<?= $date_from ?>&to=<?= $date_to ?>" class="tab-btn <?= $tab==='subscriptions'?'active':'' ?>">
        <i class="bi bi-shop"></i> Subscription History <span style="background:var(--card-border);padding:1px 7px;border-radius:99px;font-size:11px;"><?= count($sub_history) ?></span>
    </a>
    <a href="?tab=collections&shop=<?= $filter_shop ?>&from=<?= $date_from ?>&to=<?= $date_to ?>" class="tab-btn <?= $tab==='collections'?'active':'' ?>">
        <i class="bi bi-cash-coin"></i> Commission Collections <span style="background:var(--card-border);padding:1px 7px;border-radius:99px;font-size:11px;"><?= count($collections) ?></span>
    </a>
    <a href="?tab=orders&shop=<?= $filter_shop ?>&from=<?= $date_from ?>&to=<?= $date_to ?>" class="tab-btn <?= $tab==='orders'?'active':'' ?>">
        <i class="bi bi-receipt"></i> Per-Order Commission <span style="background:var(--card-border);padding:1px 7px;border-radius:99px;font-size:11px;"><?= count($order_log) ?></span>
    </a>
</div>

<!-- TAB 1: Subscription History -->
<?php if ($tab === 'subscriptions'): ?>
<div class="card-glass animate-in" style="overflow:hidden;">
    <?php if (empty($sub_history)): ?>
    <div class="empty-state"><i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:12px;"></i>No subscription records found for this period.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="log-table">
        <thead><tr>
            <th>Shop</th><th>Owner</th><th>Plan</th><th>Status</th>
            <th>Started</th><th>Expires</th><th>Payment Ref</th><th>Activated By</th><th>Commission %</th>
        </tr></thead>
        <tbody>
        <?php foreach ($sub_history as $r): ?>
        <?php
            $status_pill = match($r['status']) {
                'active'    => 'pill-active',
                'trial'     => 'pill-trial',
                'grace'     => 'pill-grace',
                'suspended' => 'pill-suspended',
                'completed' => 'pill-completed',
                default     => 'pill-cancelled',
            };
        ?>
        <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($r['shop_name']) ?></td>
            <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($r['owner_email']) ?></td>
            <td>
                <div style="font-weight:600;"><?= htmlspecialchars($r['plan_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);">&#8377;<?= number_format($r['plan_price'],0) ?>/mo</div>
            </td>
            <td><span class="status-pill <?= $status_pill ?>"><?= ucfirst($r['status']) ?></span></td>
            <td style="font-size:12px;"><?= date('d M Y', strtotime($r['started_at'])) ?></td>
            <td style="font-size:12px;"><?= date('d M Y', strtotime($r['expires_at'])) ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= $r['payment_ref'] ? htmlspecialchars($r['payment_ref']) : '<span style="opacity:.4;">—</span>' ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['activated_by_name'] ?? '—') ?></td>
            <td style="text-align:center;">
                <?php if ($r['commission_rate']>0): ?>
                <span style="color:#ca8a04;font-weight:700;"><?= $r['commission_rate'] ?>%</span>
                <?php else: ?><span style="color:var(--muted);">—</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- TAB 2: Commission Collections -->
<?php elseif ($tab === 'collections'): ?>
<div class="card-glass animate-in" style="overflow:hidden;">
    <?php if (empty($collections)): ?>
    <div class="empty-state"><i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:12px;"></i>No commission collections recorded yet.</div>
    <?php else: ?>
    <?php
        $tab_total_revenue = array_sum(array_column($collections, 'total_revenue'));
        $tab_total_comm    = array_sum(array_column($collections, 'commission_amount'));
    ?>
    <div style="display:flex;gap:24px;padding:16px 20px;border-bottom:1px solid var(--card-border);flex-wrap:wrap;">
        <div><span style="font-size:12px;color:var(--muted);">Total Shop Revenue (period)</span><br><span style="font-size:20px;font-family:'Syne',sans-serif;font-weight:800;">&#8377;<?= number_format($tab_total_revenue,2) ?></span></div>
        <div><span style="font-size:12px;color:var(--muted);">Total Commission Collected</span><br><span style="font-size:20px;font-family:'Syne',sans-serif;font-weight:800;color:#059669;">&#8377;<?= number_format($tab_total_comm,2) ?></span></div>
        <div><span style="font-size:12px;color:var(--muted);">Collection Events</span><br><span style="font-size:20px;font-family:'Syne',sans-serif;font-weight:800;"><?= count($collections) ?></span></div>
    </div>
    <div style="overflow-x:auto;">
    <table class="log-table">
        <thead><tr>
            <th>Date</th><th>Shop</th><th>Plan</th><th>Period Covered</th>
            <th style="text-align:right;">Orders</th><th style="text-align:right;">Shop Revenue</th>
            <th style="text-align:right;">Rate</th><th style="text-align:right;">Commission</th>
            <th>Note</th><th>Collected By</th>
        </tr></thead>
        <tbody>
        <?php foreach ($collections as $c): ?>
        <tr>
            <td style="font-size:12px;white-space:nowrap;"><?= date('d M Y H:i', strtotime($c['created_at'])) ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($c['shop_name']) ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($c['plan_name'] ?? '—') ?></td>
            <td style="font-size:11.5px;color:var(--muted);">
                <?php if ($c['period_start']): ?>
                <?= date('d M', strtotime($c['period_start'])) ?> &ndash; <?= date('d M Y', strtotime($c['period_end'] ?? $c['created_at'])) ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td style="text-align:right;"><?= $c['order_count'] ?></td>
            <td style="text-align:right;">&#8377;<?= number_format($c['total_revenue'],2) ?></td>
            <td style="text-align:right;color:#ca8a04;font-weight:700;"><?= $c['commission_rate'] ?>%</td>
            <td style="text-align:right;font-family:'Syne',sans-serif;font-weight:800;color:#059669;">&#8377;<?= number_format($c['commission_amount'],2) ?></td>
            <td style="font-size:12px;color:var(--muted);max-width:180px;"><?= htmlspecialchars($c['note'] ?: '—') ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($c['collected_by_name'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:2px solid var(--card-border);background:rgba(200,169,126,.05);">
            <td colspan="5" style="padding:11px 14px;font-weight:700;text-align:right;">Total</td>
            <td style="padding:11px 14px;text-align:right;font-weight:700;">&#8377;<?= number_format($tab_total_revenue,2) ?></td>
            <td></td>
            <td style="padding:11px 14px;text-align:right;font-family:'Syne',sans-serif;font-weight:800;color:#059669;">&#8377;<?= number_format($tab_total_comm,2) ?></td>
            <td colspan="2"></td>
        </tr>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- TAB 3: Per-Order Commission Log -->
<?php elseif ($tab === 'orders'): ?>
<div class="card-glass animate-in" style="overflow:hidden;">
    <?php if (empty($order_log)): ?>
    <div class="empty-state"><i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:12px;"></i>No commission orders found for this period.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="log-table">
        <thead><tr>
            <th>Date</th><th>Shop</th><th>Order #</th>
            <th style="text-align:right;">Order Amount</th>
            <th style="text-align:right;">Rate</th>
            <th style="text-align:right;">Commission</th>
            <th style="text-align:center;">Status</th>
            <th>Collected At</th>
        </tr></thead>
        <tbody>
        <?php
            $tab3_pending = 0; $tab3_collected = 0;
            foreach ($order_log as $ol):
                if ($ol['collected']) $tab3_collected += $ol['commission_amount'];
                else $tab3_pending += $ol['commission_amount'];
        ?>
        <tr>
            <td style="font-size:12px;white-space:nowrap;"><?= date('d M Y H:i', strtotime($ol['created_at'])) ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($ol['shop_name']) ?></td>
            <td style="font-family:'JetBrains Mono',monospace;font-size:12px;">#<?= $ol['order_id'] ?></td>
            <td style="text-align:right;">&#8377;<?= number_format($ol['order_amount'],2) ?></td>
            <td style="text-align:right;color:#ca8a04;font-weight:700;"><?= $ol['commission_rate'] ?>%</td>
            <td style="text-align:right;font-weight:700;">&#8377;<?= number_format($ol['commission_amount'],2) ?></td>
            <td style="text-align:center;">
                <?php if ($ol['collected']): ?>
                <span class="status-pill pill-collected"><i class="bi bi-check2-all"></i> Collected</span>
                <?php else: ?>
                <span class="status-pill pill-pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--muted);"><?= $ol['collected_at'] ? date('d M Y', strtotime($ol['collected_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:2px solid var(--card-border);background:rgba(200,169,126,.05);">
            <td colspan="5" style="padding:11px 14px;font-weight:700;text-align:right;">Totals</td>
            <td style="padding:11px 14px;text-align:right;font-family:'Syne',sans-serif;font-weight:800;">&#8377;<?= number_format($tab3_pending+$tab3_collected,2) ?></td>
            <td style="padding:11px 14px;text-align:center;font-size:12px;color:var(--muted);">
                <span class="status-pill pill-pending">&#8377;<?= number_format($tab3_pending,2) ?></span>
                <span class="status-pill pill-collected" style="margin-left:4px;">&#8377;<?= number_format($tab3_collected,2) ?></span>
            </td>
            <td></td>
        </tr>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
