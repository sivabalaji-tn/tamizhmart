<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require '../config/db.php';

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: login.php");
    exit;
}

$page_title = 'Email Campaigns';
$page_subtitle = 'Monitor and reset monthly shop campaign allowances';
$success = $error = '';

function ensureSuperadminCampaignTables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS email_campaign_allowances (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        shop_id INT UNSIGNED NOT NULL,
        month_key CHAR(7) NOT NULL,
        monthly_limit INT UNSIGNED NOT NULL DEFAULT 2,
        used_count INT UNSIGNED NOT NULL DEFAULT 0,
        reset_count INT UNSIGNED NOT NULL DEFAULT 0,
        reset_by INT UNSIGNED NULL,
        reset_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_shop_month (shop_id, month_key),
        KEY idx_month_key (month_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS email_campaigns (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        shop_id INT UNSIGNED NOT NULL,
        owner_id INT UNSIGNED NOT NULL,
        month_key CHAR(7) NOT NULL,
        preset VARCHAR(40) NOT NULL,
        campaign_type VARCHAR(40) NOT NULL,
        audience_type VARCHAR(40) NOT NULL DEFAULT 'all_registered',
        subject VARCHAR(180) NOT NULL,
        headline VARCHAR(180) NOT NULL,
        description TEXT NOT NULL,
        offer_text VARCHAR(220) DEFAULT NULL,
        coupon_code VARCHAR(60) DEFAULT NULL,
        cta_label VARCHAR(80) DEFAULT 'Shop Now',
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
        sent_count INT UNSIGNED NOT NULL DEFAULT 0,
        failed_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME NULL,
        KEY idx_shop_month (shop_id, month_key),
        KEY idx_shop_status (shop_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS email_campaign_products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NOT NULL,
        KEY idx_campaign (campaign_id),
        KEY idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS email_campaign_recipients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        email VARCHAR(180) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        sent_at DATETIME NULL,
        error_message VARCHAR(255) DEFAULT NULL,
        KEY idx_campaign (campaign_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureShopAllowance($conn, $shop_id, $month_key) {
    $st = $conn->prepare("INSERT IGNORE INTO email_campaign_allowances (shop_id, month_key, monthly_limit, used_count) VALUES (?, ?, 2, 0)");
    $st->bind_param('is', $shop_id, $month_key);
    $st->execute();
}

ensureSuperadminCampaignTables($conn);
$month_key = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    $shop_id = (int)($_POST['shop_id'] ?? 0);
    $post_month = preg_match('/^\d{4}-\d{2}$/', $_POST['month_key'] ?? '') ? $_POST['month_key'] : date('Y-m');

    if ($shop_id > 0) {
        ensureShopAllowance($conn, $shop_id, $post_month);

        if ($action === 'reset_usage') {
            $admin_id = (int)$_SESSION['superadmin_id'];
            $st = $conn->prepare("UPDATE email_campaign_allowances
                                  SET used_count=0, reset_count=reset_count+1, reset_by=?, reset_at=NOW()
                                  WHERE shop_id=? AND month_key=?");
            $st->bind_param('iis', $admin_id, $shop_id, $post_month);
            $st->execute();
            $success = 'Campaign usage reset for the selected shop.';
        } elseif ($action === 'update_limit') {
            $limit = max(0, min(50, (int)($_POST['monthly_limit'] ?? 2)));
            $st = $conn->prepare("UPDATE email_campaign_allowances SET monthly_limit=? WHERE shop_id=? AND month_key=?");
            $st->bind_param('iis', $limit, $shop_id, $post_month);
            $st->execute();
            $success = "Monthly campaign limit updated to {$limit}.";
        }
    } else {
        $error = 'Invalid shop selected.';
    }

    $month_key = $post_month;
}

require __DIR__ . '/includes/sidebar.php';

$shops_for_allowance = $conn->query("SELECT id FROM shops");
while ($shops_for_allowance && $s = $shops_for_allowance->fetch_assoc()) {
    ensureShopAllowance($conn, (int)$s['id'], $month_key);
}

$campaign_stats = $conn->query("
    SELECT
        COUNT(*) AS total_campaigns,
        COALESCE(SUM(sent_count),0) AS total_sent,
        COALESCE(SUM(failed_count),0) AS total_failed,
        COUNT(DISTINCT shop_id) AS active_campaign_shops
    FROM email_campaigns
    WHERE month_key='" . $conn->real_escape_string($month_key) . "'
")->fetch_assoc();

$shops = $conn->query("
    SELECT s.id, s.name, s.slug, s.is_active, s.is_suspended,
           o.name AS owner_name, o.email AS owner_email,
           a.monthly_limit, a.used_count, a.reset_count, a.reset_at,
           COUNT(c.id) AS campaign_count,
           COALESCE(SUM(c.sent_count),0) AS sent_count,
           COALESCE(SUM(c.failed_count),0) AS failed_count,
           MAX(c.sent_at) AS last_sent_at
    FROM shops s
    JOIN owners o ON o.id=s.owner_id
    LEFT JOIN email_campaign_allowances a ON a.shop_id=s.id AND a.month_key='" . $conn->real_escape_string($month_key) . "'
    LEFT JOIN email_campaigns c ON c.shop_id=s.id AND c.month_key='" . $conn->real_escape_string($month_key) . "'
    GROUP BY s.id
    ORDER BY s.name ASC
");
?>

<?php if ($success): ?>
<div class="alert-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error animate-in"><i class="bi bi-exclamation-triangle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<style>
.campaign-admin-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:20px;}
.campaign-admin-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:18px 20px;box-shadow:0 4px 20px rgba(0,0,0,.15);}
.campaign-admin-card .lbl{font-size:10.5px;color:var(--muted2);font-weight:800;text-transform:uppercase;letter-spacing:1px;font-family:'JetBrains Mono',monospace;margin-bottom:8px;}
.campaign-admin-card .num{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:#fff;line-height:1;}
.usage-meter{height:8px;background:rgba(255,255,255,.08);border-radius:99px;overflow:hidden;margin-top:8px;width:130px;}
.usage-meter span{display:block;height:100%;background:linear-gradient(90deg,var(--accent),var(--cyan-neon));border-radius:99px;}
.mini-form{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.mini-form .input-custom{width:78px;padding:6px 9px;font-size:12px;}
@media(max-width:900px){.campaign-admin-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.campaign-admin-stats{grid-template-columns:1fr}}
</style>

<form method="GET" class="card-glass animate-in" style="margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <div>
        <label class="input-label">Campaign month</label>
        <input type="month" name="month" class="input-custom" value="<?= htmlspecialchars($month_key) ?>" style="width:180px;">
    </div>
    <button type="submit" class="btn-primary-custom" style="margin-top:20px;"><i class="bi bi-calendar-check"></i> View Month</button>
</form>

<div class="campaign-admin-stats animate-in d1">
    <div class="campaign-admin-card">
        <div class="lbl">Campaigns</div>
        <div class="num"><?= number_format((int)$campaign_stats['total_campaigns']) ?></div>
    </div>
    <div class="campaign-admin-card">
        <div class="lbl">Emails Sent</div>
        <div class="num" style="color:var(--success);"><?= number_format((int)$campaign_stats['total_sent']) ?></div>
    </div>
    <div class="campaign-admin-card">
        <div class="lbl">Failed Sends</div>
        <div class="num" style="color:var(--danger);"><?= number_format((int)$campaign_stats['total_failed']) ?></div>
    </div>
    <div class="campaign-admin-card">
        <div class="lbl">Active Shops</div>
        <div class="num" style="color:var(--cyan-neon);"><?= number_format((int)$campaign_stats['active_campaign_shops']) ?></div>
    </div>
</div>

<div class="card-glass animate-in d2" style="padding:0;overflow:hidden;">
    <table class="table-custom">
        <thead>
            <tr>
                <th>Shop</th>
                <th>Owner</th>
                <th>Usage</th>
                <th>Delivery</th>
                <th>Last Campaign</th>
                <th>Status</th>
                <th>Controls</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$shops || $shops->num_rows === 0): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No shops found.</td></tr>
        <?php else: while ($s = $shops->fetch_assoc()):
            $limit = (int)($s['monthly_limit'] ?? 2);
            $used = (int)($s['used_count'] ?? 0);
            $pct = $limit > 0 ? min(100, round(($used / $limit) * 100)) : 0;
            $remaining = max(0, $limit - $used);
        ?>
            <tr>
                <td>
                    <div style="font-weight:700;color:#fff;font-size:13.5px;"><?= htmlspecialchars($s['name']) ?></div>
                    <div style="font-size:12px;color:var(--muted);">/?shop=<?= htmlspecialchars($s['slug']) ?></div>
                </td>
                <td>
                    <div style="font-size:13px;color:#e2e8f0;"><?= htmlspecialchars($s['owner_name']) ?></div>
                    <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($s['owner_email']) ?></div>
                </td>
                <td>
                    <div style="font-size:13px;font-weight:800;color:#fff;"><?= $used ?>/<?= $limit ?> used</div>
                    <div class="usage-meter"><span style="width:<?= $pct ?>%;"></span></div>
                    <div style="font-size:11.5px;color:var(--muted);margin-top:5px;"><?= $remaining ?> remaining · <?= (int)$s['reset_count'] ?> resets</div>
                </td>
                <td>
                    <div style="font-size:12.5px;color:var(--success);font-weight:800;"><?= (int)$s['sent_count'] ?> sent</div>
                    <div style="font-size:12px;color:var(--danger);"><?= (int)$s['failed_count'] ?> failed</div>
                    <div style="font-size:11.5px;color:var(--muted);"><?= (int)$s['campaign_count'] ?> campaigns</div>
                </td>
                <td style="font-size:12.5px;color:var(--muted);">
                    <?= $s['last_sent_at'] ? date('d M Y, h:i A', strtotime($s['last_sent_at'])) : 'Not sent this month' ?>
                </td>
                <td>
                    <?php if ((int)$s['is_suspended'] === 1): ?>
                    <span class="badge-custom badge-danger">Suspended</span>
                    <?php elseif ((int)$s['is_active'] === 1): ?>
                    <span class="badge-custom badge-success">Active</span>
                    <?php else: ?>
                    <span class="badge-custom badge-warning">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <form method="POST" class="mini-form">
                            <input type="hidden" name="action" value="reset_usage">
                            <input type="hidden" name="shop_id" value="<?= (int)$s['id'] ?>">
                            <input type="hidden" name="month_key" value="<?= htmlspecialchars($month_key) ?>">
                            <button type="submit" class="btn-success-custom" onclick="return confirm('Reset campaign usage for <?= htmlspecialchars(addslashes($s['name'])) ?>?')">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </button>
                        </form>
                        <form method="POST" class="mini-form">
                            <input type="hidden" name="action" value="update_limit">
                            <input type="hidden" name="shop_id" value="<?= (int)$s['id'] ?>">
                            <input type="hidden" name="month_key" value="<?= htmlspecialchars($month_key) ?>">
                            <input type="number" name="monthly_limit" min="0" max="50" value="<?= $limit ?>" class="input-custom">
                            <button type="submit" class="btn-ghost-custom"><i class="bi bi-save"></i> Limit</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
