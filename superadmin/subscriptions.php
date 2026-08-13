<?php
/**
 * TamizhMart Super Admin — Subscriptions Management
 * View all shop subscriptions, activate/extend/suspend manually
 */
session_start();
require '../config/db.php';
if (empty($_SESSION['superadmin_id'])) { header('Location: login.php'); exit; }

$success = ''; $error = '';
$admin_id = (int)$_SESSION['superadmin_id'];

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Activate / upgrade a shop to a plan
    if ($action === 'activate') {
        $shop_id     = intval($_POST['shop_id']);
        $plan_id     = intval($_POST['plan_id']);
        $payment_ref = trim($_POST['payment_ref'] ?? '');
        $payment_note= trim($_POST['payment_note'] ?? '');
        $start       = trim($_POST['start_date'] ?? date('Y-m-d'));

        $plan = $conn->query("SELECT * FROM plans WHERE id=$plan_id LIMIT 1")->fetch_assoc();
        if (!$plan) { $error = 'Invalid plan.'; goto DONE; }

        $expires = date('Y-m-d H:i:s', strtotime("$start +{$plan['duration_days']} days"));
        $grace   = date('Y-m-d H:i:s', strtotime("$expires +7 days"));

        // Check existing subscription
        $existing = $conn->query("SELECT id FROM shop_subscriptions WHERE shop_id=$shop_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE shop_subscriptions SET
                plan_id=$plan_id, status='active',
                started_at='$start', expires_at='$expires', grace_until='$grace',
                payment_ref='".addslashes($payment_ref)."',
                payment_note='".addslashes($payment_note)."',
                activated_by=$admin_id, activated_at=NOW()
                WHERE id={$existing['id']}");
        } else {
            $conn->query("INSERT INTO shop_subscriptions
                (shop_id,plan_id,status,started_at,expires_at,grace_until,payment_ref,payment_note,activated_by,activated_at)
                VALUES ($shop_id,$plan_id,'active','$start','$expires','$grace',
                '".addslashes($payment_ref)."','".addslashes($payment_note)."',$admin_id,NOW())");
        }

        // Update shop is_suspended based on plan
        $conn->query("UPDATE shops SET is_suspended=0 WHERE id=$shop_id");
        $success = "Shop activated on {$plan['name']} plan until " . date('d M Y', strtotime($expires)) . ".";
    }

    // Suspend a shop
    if ($action === 'suspend') {
        $shop_id = intval($_POST['shop_id']);
        $conn->query("UPDATE shop_subscriptions SET status='suspended' WHERE shop_id=$shop_id ORDER BY id DESC LIMIT 1");
        $conn->query("UPDATE shops SET is_suspended=1 WHERE id=$shop_id");
        $success = "Shop suspended.";
    }

    // Restore from suspension
    if ($action === 'restore') {
        $shop_id = intval($_POST['shop_id']);
        $conn->query("UPDATE shop_subscriptions SET status='grace' WHERE shop_id=$shop_id AND status='suspended' ORDER BY id DESC LIMIT 1");
        $conn->query("UPDATE shops SET is_suspended=0 WHERE id=$shop_id");
        $success = "Shop restored to grace period.";
    }

    // Extend current subscription
    if ($action === 'extend') {
        $sub_id  = intval($_POST['sub_id']);
        $days    = intval($_POST['extend_days'] ?? 30);
        $conn->query("UPDATE shop_subscriptions SET
            expires_at = DATE_ADD(expires_at, INTERVAL $days DAY),
            grace_until= DATE_ADD(grace_until, INTERVAL $days DAY),
            status='active', activated_by=$admin_id, activated_at=NOW()
            WHERE id=$sub_id");
        $conn->query("UPDATE shops s JOIN shop_subscriptions ss ON s.id=ss.shop_id SET s.is_suspended=0 WHERE ss.id=$sub_id");
        $success = "Subscription extended by $days days.";
    }
}
DONE:

// ── Filters ───────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$filter_plan   = intval($_GET['plan'] ?? 0);
$search        = trim($_GET['q'] ?? '');

$where = "1=1";
if ($filter_status !== 'all') $where .= " AND ss.status='$filter_status'";
if ($filter_plan)             $where .= " AND ss.plan_id=$filter_plan";
if ($search)                  $where .= " AND (s.name LIKE '%".addslashes($search)."%' OR u_owner.email LIKE '%".addslashes($search)."%')";

// ── Fetch subscriptions ───────────────────────────────────────
$subs = $conn->query("
    SELECT ss.*, s.name AS shop_name, s.slug, s.is_suspended,
           p.name AS plan_name, p.slug AS plan_slug, p.price AS plan_price,
           o.email AS owner_email, o.name AS owner_name, o.phone AS owner_phone
    FROM shop_subscriptions ss
    JOIN shops s ON ss.shop_id = s.id
    JOIN plans p ON ss.plan_id = p.id
    JOIN owners o ON s.owner_id = o.id
    WHERE $where
    ORDER BY ss.updated_at DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Stats ─────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status='trial')     AS trials,
        SUM(status='active')    AS active,
        SUM(status='grace')     AS grace,
        SUM(status='suspended') AS suspended,
        SUM(p.price)            AS mrr
    FROM shop_subscriptions ss
    JOIN plans p ON ss.plan_id = p.id
    WHERE ss.status IN ('active')
")->fetch_assoc();

$plans_list = $conn->query("SELECT id, name FROM plans WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// ── Commission earned per shop ────────────────────────────────
$commission_data = $conn->query("
    SELECT s.name AS shop_name, p.name AS plan_name, p.commission_rate,
           COALESCE(SUM(o.total_amount),0) AS total_revenue,
           COALESCE(SUM(o.total_amount) * p.commission_rate / 100, 0) AS commission_earned
    FROM shops s
    JOIN shop_subscriptions ss ON ss.shop_id = s.id
    JOIN plans p ON ss.plan_id = p.id
    LEFT JOIN orders o ON o.shop_id = s.id AND o.status NOT IN ('cancelled','pending')
    WHERE p.commission_rate > 0
    GROUP BY s.id, p.id
    ORDER BY commission_earned DESC
")->fetch_all(MYSQLI_ASSOC);

$total_commission = array_sum(array_column($commission_data, 'commission_earned'));
$shops_list = $conn->query("SELECT s.id, s.name, o.email FROM shops s JOIN owners o ON s.owner_id=o.id WHERE s.is_active=1 ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/includes/sidebar.php';

function statusBadge($status, $expires_at) {
    $days_left = (strtotime($expires_at) - time()) / 86400;
    $map = [
        'trial'     => ['warning', 'hourglass-split', 'Trial'],
        'active'    => ['success', 'check-circle-fill','Active'],
        'grace'     => ['orange',  'exclamation-circle','Grace Period'],
        'suspended' => ['danger',  'x-circle-fill',    'Suspended'],
        'cancelled' => ['muted',   'slash-circle',      'Cancelled'],
    ];
    $s = $map[$status] ?? ['muted','circle','Unknown'];
    $extra = '';
    if (in_array($status, ['trial','active']) && $days_left <= 7 && $days_left >= 0) {
        $extra = ' <span style="font-size:10px;color:#ef4444;">('.ceil($days_left).'d left)</span>';
    }
    return "<span class='sub-badge badge-{$s[0]}'><i class='bi bi-{$s[1]}'></i>{$s[2]}</span>$extra";
}
?>
<style>
.sub-badge {
    display:inline-flex;align-items:center;gap:5px;
    padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700;
}
.badge-success  { background:rgba(16,185,129,0.12); color:#059669; }
.badge-warning  { background:rgba(251,191,36,0.12); color:#d97706; }
.badge-orange   { background:rgba(249,115,22,0.12); color:#ea580c; }
.badge-danger   { background:rgba(239,68,68,0.12);  color:#dc2626; }
.badge-muted    { background:rgba(107,114,128,0.1); color:var(--muted); }
.badge-info     { background:rgba(99,102,241,0.12); color:var(--accent); }
.sub-row {
    background:var(--card-bg);
    border:1px solid var(--card-border);
    border-radius:var(--radius-sm);
    padding:16px 20px;
    margin-bottom:10px;
    display:grid;
    grid-template-columns:1.5fr 1fr 1fr 1fr 1fr auto;
    align-items:center;
    gap:12px;
    transition:transform .15s;
}
.sub-row:hover { transform:translateX(2px); }
.sub-row .shop-nm { font-weight:700;font-size:14px; }
.sub-row .owner-em { font-size:12px;color:var(--muted); }
.sub-row .sub-meta { font-size:12px;color:var(--muted); }
.stat-mini {
    background:var(--card-bg);border:1px solid var(--card-border);
    border-radius:var(--radius-sm);padding:16px 20px;text-align:center;
}
.stat-mini .num { font-family:'Syne',sans-serif;font-weight:800;font-size:24px; }
.stat-mini .lbl { font-size:12px;color:var(--muted);margin-top:2px; }
@media(max-width:900px) {
    .sub-row { grid-template-columns:1fr; }
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Subscriptions</h1>
        <p class="page-sub">Manage shop plans, activations and billing</p>
    </div>
    <button class="btn-primary-custom" onclick="openModal('activateModal')">
        <i class="bi bi-plus-lg"></i> Assign Plan
    </button>
</div>

<?php if ($success): ?>
<div class="alert-success animate-in"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error animate-in"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:24px;" class="animate-in">
    <div class="stat-mini">
        <div class="num" style="color:#d97706;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='trial'")->fetch_row()[0] ?></div>
        <div class="lbl">On Trial</div>
    </div>
    <div class="stat-mini">
        <div class="num" style="color:#059669;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='active'")->fetch_row()[0] ?></div>
        <div class="lbl">Active</div>
    </div>
    <div class="stat-mini">
        <div class="num" style="color:#ea580c;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='grace'")->fetch_row()[0] ?></div>
        <div class="lbl">Grace Period</div>
    </div>
    <div class="stat-mini">
        <div class="num" style="color:#dc2626;"><?= $conn->query("SELECT COUNT(*) FROM shop_subscriptions WHERE status='suspended'")->fetch_row()[0] ?></div>
        <div class="lbl">Suspended</div>
    </div>
    <div class="stat-mini">
        <div class="num" style="color:var(--accent);">₹<?= number_format($conn->query("SELECT COALESCE(SUM(p.price),0) FROM shop_subscriptions ss JOIN plans p ON ss.plan_id=p.id WHERE ss.status='active'")->fetch_row()[0], 0) ?></div>
        <div class="lbl">MRR</div>
    </div>
</div>

<!-- Commission Earned Card -->
<?php if (!empty($commission_data)): ?>
<div class="card-glass animate-in" style="margin-bottom:24px;border-color:rgba(200,169,126,0.2);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <div>
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:17px;display:flex;align-items:center;gap:8px;">
                <i class="bi bi-cash-coin" style="color:#ca8a04;"></i> Commission Earnings
            </div>
            <div style="font-size:12.5px;color:var(--muted);margin-top:2px;">From shops on commission-based plans</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:12px;color:var(--muted);">Total Commission Earned</div>
            <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:28px;color:#ca8a04;">
                ₹<?= number_format($total_commission, 2) ?>
            </div>
        </div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:var(--card-border);">
                <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Shop</th>
                <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Plan</th>
                <th style="padding:10px 14px;text-align:right;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Commission %</th>
                <th style="padding:10px 14px;text-align:right;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Shop Revenue</th>
                <th style="padding:10px 14px;text-align:right;font-weight:700;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">You Earn</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($commission_data as $i => $cd): ?>
        <tr style="border-top:1px solid var(--card-border);background:<?= $i%2===0 ? 'transparent' : 'rgba(255,255,255,0.02)' ?>;">
            <td style="padding:12px 14px;font-weight:600;"><?= htmlspecialchars($cd['shop_name']) ?></td>
            <td style="padding:12px 14px;color:var(--muted);"><?= htmlspecialchars($cd['plan_name']) ?></td>
            <td style="padding:12px 14px;text-align:right;color:#ca8a04;font-weight:700;"><?= $cd['commission_rate'] ?>%</td>
            <td style="padding:12px 14px;text-align:right;">₹<?= number_format($cd['total_revenue'], 2) ?></td>
            <td style="padding:12px 14px;text-align:right;font-family:'Syne',sans-serif;font-weight:800;color:#16a34a;">
                ₹<?= number_format($cd['commission_earned'], 2) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:2px solid var(--card-border);background:rgba(200,169,126,0.05);">
            <td colspan="4" style="padding:12px 14px;font-weight:700;text-align:right;">Total Commission</td>
            <td style="padding:12px 14px;text-align:right;font-family:'Syne',sans-serif;font-weight:800;font-size:16px;color:#ca8a04;">
                ₹<?= number_format($total_commission, 2) ?>
            </td>
        </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Filters -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;" class="animate-in d1">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;width:100%;">
        <input type="text" name="q" class="input-custom" placeholder="Search shop or owner..."
            value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
        <select name="status" class="input-custom" style="width:160px;">
            <option value="all"      <?= $filter_status==='all'       ?'selected':'' ?>>All Status</option>
            <option value="trial"    <?= $filter_status==='trial'     ?'selected':'' ?>>Trial</option>
            <option value="active"   <?= $filter_status==='active'    ?'selected':'' ?>>Active</option>
            <option value="grace"    <?= $filter_status==='grace'     ?'selected':'' ?>>Grace Period</option>
            <option value="suspended"<?= $filter_status==='suspended' ?'selected':'' ?>>Suspended</option>
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
<div style="text-align:center;padding:48px;color:var(--muted);">
    <i class="bi bi-inbox" style="font-size:40px;display:block;margin-bottom:12px;"></i>
    No subscriptions found.
</div>
<?php else: foreach ($subs as $sub):
    $days_left    = ceil((strtotime($sub['expires_at']) - time()) / 86400);
    $in_grace     = $sub['status'] === 'grace';
    $is_suspended = $sub['status'] === 'suspended';
    $expired      = $days_left < 0 && !$is_suspended;
?>
<div class="sub-row" style="<?= $is_suspended ? 'border-color:rgba(239,68,68,0.3);' : ($expired||$in_grace ? 'border-color:rgba(249,115,22,0.3);' : '') ?>">

    <!-- Shop info -->
    <div>
        <div class="shop-nm"><?= htmlspecialchars($sub['shop_name']) ?></div>
        <div class="owner-em"><?= htmlspecialchars($sub['owner_email']) ?></div>
        <?php if ($sub['owner_phone']): ?>
        <div class="owner-em"><i class="bi bi-telephone"></i> <?= htmlspecialchars($sub['owner_phone']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Plan -->
    <div>
        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($sub['plan_name']) ?></div>
        <div class="sub-meta">₹<?= number_format($sub['plan_price'], 0) ?>/mo</div>
    </div>

    <!-- Status -->
    <div><?= statusBadge($sub['status'], $sub['expires_at']) ?></div>

    <!-- Dates -->
    <div>
        <div class="sub-meta">From: <?= date('d M Y', strtotime($sub['started_at'])) ?></div>
        <div class="sub-meta" style="<?= $days_left<=7&&$days_left>=0?'color:#ef4444;font-weight:600;':'' ?>">
            Exp: <?= date('d M Y', strtotime($sub['expires_at'])) ?>
        </div>
        <?php if ($in_grace): ?>
        <div class="sub-meta" style="color:#ea580c;">Grace: <?= date('d M Y', strtotime($sub['grace_until'])) ?></div>
        <?php endif; ?>
    </div>

    <!-- Payment ref -->
    <div>
        <?php if ($sub['payment_ref']): ?>
        <div class="sub-meta" style="font-size:11px;"><i class="bi bi-receipt"></i> <?= htmlspecialchars($sub['payment_ref']) ?></div>
        <?php else: ?>
        <div class="sub-meta" style="font-size:11px;color:var(--muted);">No payment ref</div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:6px;">
        <button class="btn-primary-custom" style="padding:6px 12px;font-size:12px;"
            onclick="openActivate(<?= $sub['shop_id'] ?>,'<?= addslashes($sub['shop_name']) ?>')">
            <i class="bi bi-arrow-up-circle"></i> Activate
        </button>

        <button class="btn-ghost-custom" style="padding:5px 12px;font-size:12px;"
            onclick="openExtend(<?= $sub['id'] ?>,'<?= addslashes($sub['shop_name']) ?>')">
            <i class="bi bi-calendar-plus"></i> Extend
        </button>

        <?php if (!$is_suspended): ?>
        <form method="POST" onsubmit="return confirm('Suspend this shop?')">
            <input type="hidden" name="action"  value="suspend">
            <input type="hidden" name="shop_id" value="<?= $sub['shop_id'] ?>">
            <button class="btn-danger-custom" style="width:100%;padding:5px 12px;font-size:12px;">
                <i class="bi bi-pause-circle"></i> Suspend
            </button>
        </form>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action"  value="restore">
            <input type="hidden" name="shop_id" value="<?= $sub['shop_id'] ?>">
            <button class="btn-ghost-custom" style="width:100%;padding:5px 12px;font-size:12px;color:#059669;">
                <i class="bi bi-play-circle"></i> Restore
            </button>
        </form>
        <?php endif; ?>
    </div>

</div>
<?php endforeach; endif; ?>
</div>

<!-- ══ ACTIVATE MODAL ═══════════════════════════════════════ -->
<div id="activateModal" class="modal-overlay" style="display:none;">
<div class="modal-box" style="max-width:500px;">
    <div class="modal-header">
        <div class="modal-title"><i class="bi bi-arrow-up-circle"></i> Assign / Activate Plan</div>
        <button onclick="closeModal('activateModal')" class="modal-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
    <input type="hidden" name="action" value="activate">
    <div class="modal-body" style="display:grid;gap:14px;">
        <div>
            <label class="input-label">Shop *</label>
            <select name="shop_id" id="activateShopId" class="input-custom" required>
                <option value="">Select shop...</option>
                <?php foreach ($shops_list as $sh): ?>
                <option value="<?= $sh['id'] ?>"><?= htmlspecialchars($sh['name']) ?> — <?= htmlspecialchars($sh['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="input-label">Plan *</label>
            <select name="plan_id" class="input-custom" required>
                <?php foreach ($plans_list as $pl): ?>
                <option value="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label class="input-label">Start Date</label>
                <input type="date" name="start_date" class="input-custom" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label class="input-label">Payment Ref (UPI/Bank)</label>
                <input type="text" name="payment_ref" class="input-custom" placeholder="UPI Ref / TXN ID">
            </div>
        </div>
        <div>
            <label class="input-label">Payment Note</label>
            <textarea name="payment_note" class="input-custom" rows="2" placeholder="e.g. Paid via PhonePe on 01 Aug 2026"></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn-primary-custom"><i class="bi bi-check-lg"></i> Activate Plan</button>
        <button type="button" onclick="closeModal('activateModal')" class="btn-ghost-custom">Cancel</button>
    </div>
    </form>
</div>
</div>

<!-- ══ EXTEND MODAL ══════════════════════════════════════════ -->
<div id="extendModal" class="modal-overlay" style="display:none;">
<div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
        <div class="modal-title" id="extendTitle"><i class="bi bi-calendar-plus"></i> Extend Subscription</div>
        <button onclick="closeModal('extendModal')" class="modal-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
    <input type="hidden" name="action"  value="extend">
    <input type="hidden" name="sub_id"  id="extendSubId">
    <div class="modal-body" style="display:grid;gap:14px;">
        <div>
            <label class="input-label">Extend by (days)</label>
            <input type="number" name="extend_days" class="input-custom" value="30" min="1" max="365">
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn-primary-custom"><i class="bi bi-check-lg"></i> Extend</button>
        <button type="button" onclick="closeModal('extendModal')" class="btn-ghost-custom">Cancel</button>
    </div>
    </form>
</div>
</div>

<script>
function openActivate(shopId, shopName) {
    if (shopId) document.getElementById('activateShopId').value = shopId;
    openModal('activateModal');
}
function openExtend(subId, shopName) {
    document.getElementById('extendSubId').value = subId;
    document.getElementById('extendTitle').innerHTML = '<i class="bi bi-calendar-plus"></i> Extend — ' + shopName;
    openModal('extendModal');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>