<?php
/**
 * TamizhMart Super Admin — Subscriptions Management
 * FIX: Activate now inserts a NEW row (history preserved).
 *      Commission tracked per subscription period via commission_log.
 */
session_start();
require '../config/db.php';
if (empty($_SESSION['superadmin_id'])) { header('Location: login.php'); exit; }

$success = ''; $error = '';
$admin_id = (int)$_SESSION['superadmin_id'];

// ── One-time schema setup ─────────────────────────────────────────
@$conn->query("ALTER TABLE shop_subscriptions MODIFY COLUMN status
    ENUM('trial','active','grace','suspended','cancelled','completed') NOT NULL DEFAULT 'trial'");

$conn->query("CREATE TABLE IF NOT EXISTS commission_collections (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id         INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    total_revenue   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    order_count     INT NOT NULL DEFAULT 0,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    commission_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    period_start    DATETIME NULL,
    period_end      DATETIME NULL,
    collected_by    INT UNSIGNED NULL,
    note            TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shop  (shop_id),
    INDEX idx_date  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Activate / Renew — INSERT new row, mark old as 'completed' ──
    if ($action === 'activate') {
        $shop_id     = intval($_POST['shop_id']);
        $plan_id     = intval($_POST['plan_id']);
        $payment_ref = trim($_POST['payment_ref']  ?? '');
        $payment_note= trim($_POST['payment_note'] ?? '');
        $start       = trim($_POST['start_date']   ?? date('Y-m-d'));

        $plan = $conn->query("SELECT * FROM plans WHERE id=$plan_id LIMIT 1")->fetch_assoc();
        if (!$plan) { $error = 'Invalid plan.'; goto DONE; }

        $expires = date('Y-m-d H:i:s', strtotime("$start +{$plan['duration_days']} days"));
        $grace   = date('Y-m-d H:i:s', strtotime("$expires +7 days"));

        // Mark current subscription as 'completed' — keeps it as historical record
        $conn->query("UPDATE shop_subscriptions SET status='completed'
                      WHERE shop_id=$shop_id AND status IN ('active','trial','grace')
                      ORDER BY id DESC LIMIT 1");

        // Always INSERT a new subscription row — commission tracking resets naturally per period
        $conn->query("INSERT INTO shop_subscriptions
            (shop_id,plan_id,status,started_at,expires_at,grace_until,payment_ref,payment_note,activated_by,activated_at)
            VALUES ($shop_id,$plan_id,'active','$start','$expires','$grace',
            '".addslashes($payment_ref)."','".addslashes($payment_note)."',$admin_id,NOW())");

        $conn->query("UPDATE shops SET is_suspended=0 WHERE id=$shop_id");
        $success = "Shop activated on {$plan['name']} plan until " . date('d M Y', strtotime($expires)) . ".";
    }

    // ── Suspend ──────────────────────────────────────────────────────
    if ($action === 'suspend') {
        $shop_id = intval($_POST['shop_id']);
        $conn->query("UPDATE shop_subscriptions SET status='suspended'
                      WHERE shop_id=$shop_id AND status NOT IN ('completed','cancelled')
                      ORDER BY id DESC LIMIT 1");
        $conn->query("UPDATE shops SET is_suspended=1 WHERE id=$shop_id");
        $success = "Shop suspended.";
    }

    // ── Restore ──────────────────────────────────────────────────────
    if ($action === 'restore') {
        $shop_id = intval($_POST['shop_id']);
        $conn->query("UPDATE shop_subscriptions SET status='grace'
                      WHERE shop_id=$shop_id AND status='suspended'
                      ORDER BY id DESC LIMIT 1");
        $conn->query("UPDATE shops SET is_suspended=0 WHERE id=$shop_id");
        $success = "Shop restored to grace period.";
    }

    // ── Extend ───────────────────────────────────────────────────────
    if ($action === 'extend') {
        $sub_id = intval($_POST['sub_id']);
        $days   = intval($_POST['extend_days'] ?? 30);
        $conn->query("UPDATE shop_subscriptions SET
            expires_at  = DATE_ADD(expires_at,  INTERVAL $days DAY),
            grace_until = DATE_ADD(grace_until, INTERVAL $days DAY),
            status='active', activated_by=$admin_id, activated_at=NOW()
            WHERE id=$sub_id");
        $conn->query("UPDATE shops s JOIN shop_subscriptions ss ON s.id=ss.shop_id
                      SET s.is_suspended=0 WHERE ss.id=$sub_id");
        $success = "Subscription extended by $days days.";
    }

    // ── Collect Commission ────────────────────────────────────────────
    if ($action === 'collect_commission') {
        $shop_id = intval($_POST['shop_id']);
        $sub_id  = intval($_POST['sub_id']);
        $note    = trim($_POST['note'] ?? '');

        $sub = $conn->query("SELECT started_at, expires_at FROM shop_subscriptions
                             WHERE id=$sub_id AND shop_id=$shop_id LIMIT 1")->fetch_assoc();
        if (!$sub) { $error = 'Subscription not found.'; goto DONE; }

        $pending = $conn->query("
            SELECT COUNT(*)                     AS cnt,
                   COALESCE(SUM(order_amount),0)    AS revenue,
                   COALESCE(SUM(commission_amount),0) AS commission,
                   MAX(commission_rate)             AS rate
            FROM commission_log
            WHERE shop_id=$shop_id AND collected=0
              AND created_at >= '{$sub['started_at']}'
        ")->fetch_assoc();

        if ($pending && (float)$pending['commission'] > 0) {
            $period_end = date('Y-m-d H:i:s');
            $rv  = (float)$pending['revenue'];
            $cnt = (int)$pending['cnt'];
            $cm  = (float)$pending['commission'];
            $rt  = (float)$pending['rate'];
            $ps  = $sub['started_at'];

            $stmt = $conn->prepare("INSERT INTO commission_collections
                (shop_id,subscription_id,total_revenue,order_count,commission_amount,
                 commission_rate,period_start,period_end,collected_by,note)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("iididdssis",
                $shop_id, $sub_id, $rv, $cnt, $cm, $rt, $ps, $period_end, $admin_id, $note);
            $stmt->execute();

            $now = date('Y-m-d H:i:s');
            $conn->query("UPDATE commission_log SET collected=1, collected_at='$now'
                          WHERE shop_id=$shop_id AND collected=0
                            AND created_at >= '{$sub['started_at']}'");

            $amount_fmt = number_format($pending['commission'], 2);
            $success = "Commission of \xe2\x82\xb9{$amount_fmt} from {$pending['cnt']} orders collected & logged.";
        } else {
            $error = "No pending commission to collect for this shop.";
        }
    }
}
DONE:

// ── Filters ───────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$filter_plan   = intval($_GET['plan'] ?? 0);
$search        = trim($_GET['q'] ?? '');

$where = "ss.status NOT IN ('completed','cancelled')";
if ($filter_status !== 'all') $where = "ss.status='$filter_status'";
if ($filter_plan)             $where .= " AND ss.plan_id=$filter_plan";
if ($search)                  $where .= " AND (s.name LIKE '%".addslashes($search)."%' OR o.email LIKE '%".addslashes($search)."%')";

// ── Fetch current subscriptions with pending commission ───────────
$subs = $conn->query("
    SELECT ss.*, s.name AS shop_name, s.slug, s.is_suspended,
           p.name AS plan_name, p.slug AS plan_slug, p.price AS plan_price, p.commission_rate,
           o.email AS owner_email, o.name AS owner_name, o.phone AS owner_phone,
           COALESCE(clp.pending_commission, 0) AS pending_commission,
           COALESCE(clp.pending_orders, 0)     AS pending_orders
    FROM shop_subscriptions ss
    JOIN shops  s ON ss.shop_id  = s.id
    JOIN plans  p ON ss.plan_id  = p.id
    JOIN owners o ON s.owner_id  = o.id
    LEFT JOIN (
        SELECT shop_id,
               SUM(CASE WHEN collected=0 THEN commission_amount ELSE 0 END) AS pending_commission,
               COUNT(CASE WHEN collected=0 THEN 1 END)                      AS pending_orders
        FROM commission_log GROUP BY shop_id
    ) clp ON clp.shop_id = ss.shop_id
    WHERE $where
    ORDER BY ss.updated_at DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Stats ─────────────────────────────────────────────────────────
$plans_list  = $conn->query("SELECT id, name FROM plans WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$shops_list  = $conn->query("SELECT s.id, s.name, o.email FROM shops s JOIN owners o ON s.owner_id=o.id WHERE s.is_active=1 ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);

// ── Commission per active subscription period (FIXED query) ──────
$commission_data = $conn->query("
    SELECT s.name AS shop_name, s.id AS shop_id,
           ss.id AS sub_id, ss.started_at, ss.expires_at,
           p.name AS plan_name, p.commission_rate,
           COALESCE(SUM(cl.order_amount), 0)  AS total_revenue,
           COALESCE(SUM(CASE WHEN cl.collected=0 THEN cl.commission_amount ELSE 0 END), 0) AS pending_commission,
           COALESCE(SUM(CASE WHEN cl.collected=1 THEN cl.commission_amount ELSE 0 END), 0) AS collected_commission,
           COALESCE(COUNT(CASE WHEN cl.collected=0 THEN 1 END), 0) AS pending_orders
    FROM shops s
    JOIN shop_subscriptions ss ON ss.shop_id=s.id AND ss.status IN ('active','grace')
    JOIN plans p ON ss.plan_id=p.id AND p.commission_rate>0
    LEFT JOIN commission_log cl ON cl.shop_id=s.id AND cl.created_at >= ss.started_at
    GROUP BY s.id, ss.id
    ORDER BY pending_commission DESC
")->fetch_all(MYSQLI_ASSOC);

$total_pending_commission   = array_sum(array_column($commission_data, 'pending_commission'));
$total_collected_commission = array_sum(array_column($commission_data, 'collected_commission'));

require __DIR__ . '/includes/sidebar.php';

function statusBadge($status, $expires_at) {
    $days_left = (strtotime($expires_at) - time()) / 86400;
    $map = [
        'trial'     => ['warning','hourglass-split','Trial'],
        'active'    => ['success','check-circle-fill','Active'],
        'grace'     => ['orange','exclamation-circle','Grace Period'],
        'suspended' => ['danger','x-circle-fill','Suspended'],
        'cancelled' => ['muted','slash-circle','Cancelled'],
        'completed' => ['muted','check2-circle','Completed'],
    ];
    $s = $map[$status] ?? ['muted','circle','Unknown'];
    $extra = '';
    if (in_array($status, ['trial','active']) && $days_left <= 7 && $days_left >= 0)
        $extra = ' <span style="font-size:10px;color:#ef4444;">('.ceil($days_left).'d left)</span>';
    return "<span class='sub-badge badge-{$s[0]}'><i class='bi bi-{$s[1]}'></i>{$s[2]}</span>$extra";
}
?>
<style>
.sub-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700;}
.badge-success{background:rgba(16,185,129,.12);color:#059669;}
.badge-warning{background:rgba(251,191,36,.12);color:#d97706;}
.badge-orange{background:rgba(249,115,22,.12);color:#ea580c;}
.badge-danger{background:rgba(239,68,68,.12);color:#dc2626;}
.badge-muted{background:rgba(107,114,128,.1);color:var(--muted);}
.sub-row{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:16px 20px;margin-bottom:10px;display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr 1fr 130px auto;align-items:center;gap:12px;transition:transform .15s;}
.sub-row:hover{transform:translateX(2px);}
.sub-row .shop-nm{font-weight:700;font-size:14px;}
.sub-row .owner-em{font-size:12px;color:var(--muted);}
.sub-row .sub-meta{font-size:12px;color:var(--muted);}
.stat-mini{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:16px 20px;text-align:center;}
.stat-mini .num{font-family:'Syne',sans-serif;font-weight:800;font-size:24px;}
.stat-mini .lbl{font-size:12px;color:var(--muted);margin-top:2px;}
.pending-badge{display:inline-flex;align-items:center;gap:4px;background:rgba(234,88,12,.1);border:1px solid rgba(234,88,12,.22);color:#ea580c;padding:4px 9px;border-radius:99px;font-size:11.5px;font-weight:700;}
.collected-badge{display:inline-flex;align-items:center;gap:4px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.22);color:#059669;padding:4px 9px;border-radius:99px;font-size:11.5px;font-weight:700;}
@media(max-width:900px){.sub-row{grid-template-columns:1fr;}}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Subscriptions</h1>
        <p class="page-sub">Manage shop plans, activations and billing</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="commission_logs.php" class="btn-ghost-custom" style="padding:10px 16px;">
            <i class="bi bi-journal-text"></i> View Full Logs
        </a>
        <button class="btn-primary-custom" onclick="openModal('activateModal')">
            <i class="bi bi-plus-lg"></i> Assign Plan
        </button>
    </div>
</div>

<?php if ($success): ?><div class="alert-success animate-in"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert-error animate-in"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:24px;" class="animate-in">
    <div class="stat-mini"><div class="num" style="color:#d97706;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='trial'")->fetch_row()[0] ?></div><div class="lbl">Trial</div></div>
    <div class="stat-mini"><div class="num" style="color:#059669;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='active'")->fetch_row()[0] ?></div><div class="lbl">Active</div></div>
    <div class="stat-mini"><div class="num" style="color:#ea580c;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='grace'")->fetch_row()[0] ?></div><div class="lbl">Grace</div></div>
    <div class="stat-mini"><div class="num" style="color:#dc2626;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='suspended'")->fetch_row()[0] ?></div><div class="lbl">Suspended</div></div>
    <div class="stat-mini"><div class="num" style="color:var(--accent);">&#8377;<?= number_format($conn->query("SELECT COALESCE(SUM(p.price),0) FROM shop_subscriptions ss JOIN plans p ON ss.plan_id=p.id WHERE ss.status='active'")->fetch_row()[0],0) ?></div><div class="lbl">MRR</div></div>
    <div class="stat-mini"><div class="num" style="color:#ea580c;">&#8377;<?= number_format($total_pending_commission,2) ?></div><div class="lbl">Pending Commission</div></div>
    <div class="stat-mini"><div class="num" style="color:#059669;">&#8377;<?= number_format($total_collected_commission,2) ?></div><div class="lbl">Collected (Period)</div></div>
</div>

<!-- Commission per active period -->
<?php if (!empty($commission_data)): ?>
<div class="card-glass animate-in" style="margin-bottom:24px;border-color:rgba(200,169,126,.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:17px;display:flex;align-items:center;gap:8px;">
                <i class="bi bi-cash-coin" style="color:#ca8a04;"></i> Commission — Current Period
            </div>
            <div style="font-size:12px;color:var(--muted);margin-top:3px;">Tracked per subscription period. Resets correctly on renewal. <a href="commission_logs.php" style="color:var(--accent);">Full history &rarr;</a></div>
        </div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:var(--card-border);">
            <th style="padding:9px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Shop</th>
            <th style="padding:9px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Plan &amp; Period</th>
            <th style="padding:9px 14px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Rate</th>
            <th style="padding:9px 14px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Revenue</th>
            <th style="padding:9px 14px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Pending</th>
            <th style="padding:9px 14px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Collected</th>
            <th style="padding:9px 14px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Action</th>
        </tr></thead>
        <tbody>
        <?php foreach ($commission_data as $i => $cd): ?>
        <tr style="border-top:1px solid var(--card-border);background:<?= $i%2===0?'transparent':'rgba(255,255,255,.02)' ?>;">
            <td style="padding:11px 14px;font-weight:600;"><?= htmlspecialchars($cd['shop_name']) ?></td>
            <td style="padding:11px 14px;color:var(--muted);font-size:12px;">
                <?= htmlspecialchars($cd['plan_name']) ?><br>
                <span style="font-size:11px;opacity:.7;"><?= date('d M', strtotime($cd['started_at'])) ?> &ndash; <?= date('d M Y', strtotime($cd['expires_at'])) ?></span>
            </td>
            <td style="padding:11px 14px;text-align:right;color:#ca8a04;font-weight:700;"><?= $cd['commission_rate'] ?>%</td>
            <td style="padding:11px 14px;text-align:right;">&#8377;<?= number_format($cd['total_revenue'],2) ?></td>
            <td style="padding:11px 14px;text-align:right;">
                <?php if ($cd['pending_commission']>0): ?><span class="pending-badge">&#8377;<?= number_format($cd['pending_commission'],2) ?></span>
                <?php else: ?><span style="color:var(--muted);font-size:12px;">&mdash;</span><?php endif; ?>
            </td>
            <td style="padding:11px 14px;text-align:right;">
                <?php if ($cd['collected_commission']>0): ?><span class="collected-badge">&#8377;<?= number_format($cd['collected_commission'],2) ?></span>
                <?php else: ?><span style="color:var(--muted);font-size:12px;">&mdash;</span><?php endif; ?>
            </td>
            <td style="padding:11px 14px;text-align:center;">
                <?php if ($cd['pending_commission']>0): ?>
                <button class="btn-primary-custom" style="padding:5px 12px;font-size:12px;"
                    onclick="openCollect(<?= $cd['shop_id']?>,<?= $cd['sub_id']?>,'<?= addslashes($cd['shop_name'])?>',<?= $cd['pending_commission']?>,<?= $cd['pending_orders']?>)">
                    <i class="bi bi-check2-all"></i> Collect
                </button>
                <?php else: ?><span style="color:var(--muted);font-size:12px;"><i class="bi bi-check-all"></i> Settled</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:2px solid var(--card-border);background:rgba(200,169,126,.05);">
            <td colspan="4" style="padding:11px 14px;font-weight:700;text-align:right;">Total</td>
            <td style="padding:11px 14px;text-align:right;font-family:'Syne',sans-serif;font-weight:800;color:#ea580c;">&#8377;<?= number_format($total_pending_commission,2) ?></td>
            <td style="padding:11px 14px;text-align:right;font-family:'Syne',sans-serif;font-weight:800;color:#059669;">&#8377;<?= number_format($total_collected_commission,2) ?></td>
            <td></td>
        </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Filters -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;" class="animate-in d1">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;width:100%;">
        <input type="text" name="q" class="input-custom" placeholder="Search shop or owner..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
        <select name="status" class="input-custom" style="width:160px;">
            <option value="all"       <?= $filter_status==='all'      ?'selected':'' ?>>All Status</option>
            <option value="trial"     <?= $filter_status==='trial'    ?'selected':'' ?>>Trial</option>
            <option value="active"    <?= $filter_status==='active'   ?'selected':'' ?>>Active</option>
            <option value="grace"     <?= $filter_status==='grace'    ?'selected':'' ?>>Grace Period</option>
            <option value="suspended" <?= $filter_status==='suspended'?'selected':'' ?>>Suspended</option>
        </select>
        <select name="plan" class="input-custom" style="width:160px;">
            <option value="0">All Plans</option>
            <?php foreach ($plans_list as $pl): ?>
            <option value="<?= $pl['id'] ?>" <?= $filter_plan==$pl['id']?'selected':'' ?>><?= htmlspecialchars($pl['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
        <a href="subscriptions.php" class="btn-ghost-custom" style="padding:10px 16px;">Reset</a>
    </form>
</div>

<!-- Subscription rows -->
<div class="animate-in d2">
<?php if (empty($subs)): ?>
<div style="text-align:center;padding:48px;color:var(--muted);"><i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:12px;"></i>No subscriptions found.</div>
<?php else: foreach ($subs as $sub):
    $days_left    = ceil((strtotime($sub['expires_at']) - time()) / 86400);
    $in_grace     = $sub['status'] === 'grace';
    $is_suspended = $sub['status'] === 'suspended';
    $has_pending  = $sub['commission_rate'] > 0 && $sub['pending_commission'] > 0;
?>
<div class="sub-row" style="<?= $is_suspended ? 'border-color:rgba(239,68,68,.3);' : ($in_grace||$days_left<0 ? 'border-color:rgba(249,115,22,.3);' : '') ?>">
    <div>
        <div class="shop-nm"><?= htmlspecialchars($sub['shop_name']) ?></div>
        <div class="owner-em"><?= htmlspecialchars($sub['owner_email']) ?></div>
        <?php if ($sub['owner_phone']): ?><div class="owner-em"><i class="bi bi-telephone"></i> <?= htmlspecialchars($sub['owner_phone']) ?></div><?php endif; ?>
    </div>
    <div>
        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($sub['plan_name']) ?></div>
        <div class="sub-meta">&#8377;<?= number_format($sub['plan_price'],0) ?>/mo</div>
        <?php if ($sub['commission_rate']>0): ?><div class="sub-meta" style="color:#ca8a04;"><?= $sub['commission_rate'] ?>% commission</div><?php endif; ?>
    </div>
    <div><?= statusBadge($sub['status'], $sub['expires_at']) ?></div>
    <div>
        <div class="sub-meta">From: <?= date('d M Y', strtotime($sub['started_at'])) ?></div>
        <div class="sub-meta" style="<?= $days_left<=7&&$days_left>=0?'color:#ef4444;font-weight:600;':'' ?>">Exp: <?= date('d M Y', strtotime($sub['expires_at'])) ?></div>
        <?php if ($in_grace): ?><div class="sub-meta" style="color:#ea580c;">Grace: <?= date('d M Y', strtotime($sub['grace_until'])) ?></div><?php endif; ?>
    </div>
    <div>
        <?php if ($sub['payment_ref']): ?>
        <div class="sub-meta" style="font-size:11px;"><i class="bi bi-receipt"></i> <?= htmlspecialchars($sub['payment_ref']) ?></div>
        <?php else: ?><div class="sub-meta" style="font-size:11px;opacity:.5;">No payment ref</div><?php endif; ?>
    </div>
    <!-- Pending Commission column -->
    <div>
        <?php if ($sub['commission_rate']>0): ?>
            <?php if ($sub['pending_commission']>0): ?>
            <div class="pending-badge"><i class="bi bi-hourglass-split"></i> &#8377;<?= number_format($sub['pending_commission'],2) ?></div>
            <div style="font-size:10.5px;color:var(--muted);margin-top:3px;"><?= $sub['pending_orders'] ?> orders</div>
            <?php else: ?><div style="font-size:12px;color:var(--muted);"><i class="bi bi-check2-all"></i> Settled</div><?php endif; ?>
        <?php else: ?><div style="font-size:11px;color:var(--muted);">No commission</div><?php endif; ?>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;">
        <button class="btn-primary-custom" style="padding:6px 12px;font-size:12px;"
            onclick="openActivate(<?= $sub['shop_id'] ?>,'<?= addslashes($sub['shop_name']) ?>')">
            <i class="bi bi-arrow-up-circle"></i> Activate
        </button>
        <button class="btn-ghost-custom" style="padding:5px 12px;font-size:12px;"
            onclick="openExtend(<?= $sub['id'] ?>,'<?= addslashes($sub['shop_name']) ?>')">
            <i class="bi bi-calendar-plus"></i> Extend
        </button>
        <?php if ($has_pending): ?>
        <button class="btn-ghost-custom" style="padding:5px 12px;font-size:12px;color:#059669;border-color:rgba(16,185,129,.3);"
            onclick="openCollect(<?= $sub['shop_id']?>,<?= $sub['id']?>,'<?= addslashes($sub['shop_name'])?>',<?= $sub['pending_commission']?>,<?= $sub['pending_orders']?>)">
            <i class="bi bi-check2-all"></i> Collect &#8377;<?= number_format($sub['pending_commission'],2) ?>
        </button>
        <?php endif; ?>
        <?php if (!$is_suspended): ?>
        <form method="POST" onsubmit="return confirm('Suspend this shop?')">
            <input type="hidden" name="action" value="suspend">
            <input type="hidden" name="shop_id" value="<?= $sub['shop_id'] ?>">
            <button class="btn-danger-custom" style="width:100%;padding:5px 12px;font-size:12px;"><i class="bi bi-pause-circle"></i> Suspend</button>
        </form>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="shop_id" value="<?= $sub['shop_id'] ?>">
            <button class="btn-ghost-custom" style="width:100%;padding:5px 12px;font-size:12px;color:#059669;"><i class="bi bi-play-circle"></i> Restore</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; endif; ?>
</div>

<!-- ACTIVATE MODAL -->
<div id="activateModal" class="modal-overlay" style="display:none;"><div class="modal-box" style="max-width:500px;">
    <div class="modal-header"><div class="modal-title"><i class="bi bi-arrow-up-circle"></i> Assign / Activate Plan</div><button onclick="closeModal('activateModal')" class="modal-close"><i class="bi bi-x-lg"></i></button></div>
    <form method="POST"><input type="hidden" name="action" value="activate">
    <div class="modal-body" style="display:grid;gap:14px;">
        <div><label class="input-label">Shop *</label>
        <select name="shop_id" id="activateShopId" class="input-custom" required>
            <option value="">Select shop...</option>
            <?php foreach ($shops_list as $sh): ?><option value="<?= $sh['id'] ?>"><?= htmlspecialchars($sh['name']) ?> &mdash; <?= htmlspecialchars($sh['email']) ?></option><?php endforeach; ?>
        </select></div>
        <div><label class="input-label">Plan *</label>
        <select name="plan_id" class="input-custom" required>
            <?php foreach ($plans_list as $pl): ?><option value="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><label class="input-label">Start Date</label><input type="date" name="start_date" class="input-custom" value="<?= date('Y-m-d') ?>"></div>
            <div><label class="input-label">Payment Ref</label><input type="text" name="payment_ref" class="input-custom" placeholder="UPI Ref / TXN ID"></div>
        </div>
        <div><label class="input-label">Payment Note</label><textarea name="payment_note" class="input-custom" rows="2" placeholder="e.g. Paid via PhonePe on 21 Aug 2026"></textarea></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn-primary-custom"><i class="bi bi-check-lg"></i> Activate Plan</button><button type="button" onclick="closeModal('activateModal')" class="btn-ghost-custom">Cancel</button></div>
    </form>
</div></div>

<!-- EXTEND MODAL -->
<div id="extendModal" class="modal-overlay" style="display:none;"><div class="modal-box" style="max-width:400px;">
    <div class="modal-header"><div class="modal-title" id="extendTitle"><i class="bi bi-calendar-plus"></i> Extend Subscription</div><button onclick="closeModal('extendModal')" class="modal-close"><i class="bi bi-x-lg"></i></button></div>
    <form method="POST"><input type="hidden" name="action" value="extend"><input type="hidden" name="sub_id" id="extendSubId">
    <div class="modal-body"><div><label class="input-label">Extend by (days)</label><input type="number" name="extend_days" class="input-custom" value="30" min="1" max="365"></div></div>
    <div class="modal-footer"><button type="submit" class="btn-primary-custom"><i class="bi bi-check-lg"></i> Extend</button><button type="button" onclick="closeModal('extendModal')" class="btn-ghost-custom">Cancel</button></div>
    </form>
</div></div>

<!-- COLLECT COMMISSION MODAL -->
<div id="collectModal" class="modal-overlay" style="display:none;"><div class="modal-box" style="max-width:440px;">
    <div class="modal-header"><div class="modal-title"><i class="bi bi-check2-all" style="color:#059669;"></i> Collect Commission</div><button onclick="closeModal('collectModal')" class="modal-close"><i class="bi bi-x-lg"></i></button></div>
    <form method="POST"><input type="hidden" name="action" value="collect_commission"><input type="hidden" name="shop_id" id="collectShopId"><input type="hidden" name="sub_id" id="collectSubId">
    <div class="modal-body" style="display:grid;gap:16px;">
        <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:12px;padding:18px;text-align:center;">
            <div style="font-size:12px;color:var(--muted);margin-bottom:6px;" id="collectShopName">Shop</div>
            <div style="font-size:32px;font-family:'Syne',sans-serif;font-weight:900;color:#059669;" id="collectAmount">&#8377;0.00</div>
            <div style="font-size:12px;color:var(--muted);margin-top:4px;" id="collectOrders">from 0 orders</div>
        </div>
        <div><label class="input-label">Collection Note (optional)</label><input type="text" name="note" class="input-custom" placeholder="e.g. Collected via GPay on 21 Aug 2026"></div>
        <div style="font-size:12px;color:var(--muted);background:var(--card-border);border-radius:10px;padding:12px;"><i class="bi bi-info-circle"></i> This marks all pending commission orders as collected and stores the settlement in the commission log for full traceability.</div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn-primary-custom" style="background:#059669;"><i class="bi bi-check2-all"></i> Confirm & Log Collection</button><button type="button" onclick="closeModal('collectModal')" class="btn-ghost-custom">Cancel</button></div>
    </form>
</div></div>

<script>
function openActivate(shopId, shopName) { if (shopId) document.getElementById('activateShopId').value = shopId; openModal('activateModal'); }
function openExtend(subId, shopName) { document.getElementById('extendSubId').value = subId; document.getElementById('extendTitle').innerHTML = '<i class="bi bi-calendar-plus"></i> Extend &mdash; ' + shopName; openModal('extendModal'); }
function openCollect(shopId, subId, shopName, amount, orders) {
    document.getElementById('collectShopId').value = shopId;
    document.getElementById('collectSubId').value  = subId;
    document.getElementById('collectShopName').textContent = shopName;
    document.getElementById('collectAmount').textContent   = '\u20b9' + parseFloat(amount).toFixed(2);
    document.getElementById('collectOrders').textContent   = 'from ' + orders + ' pending orders';
    openModal('collectModal');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
