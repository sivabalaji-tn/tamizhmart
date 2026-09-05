<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require '../config/db.php';
require_once '../email/campaign_email.php';

if (!isset($_SESSION['owner_id'], $_SESSION['shop_id'])) {
    header("Location: login.php");
    exit;
}

$page_title = 'Email Campaigns';
$page_subtitle = 'Promote products to customers registered in your shop';
$topbar_action_label = 'Compose Campaign';
$topbar_action_icon = 'envelope-plus';
$topbar_action_onclick = "document.getElementById('campaignComposer').scrollIntoView({behavior:'smooth',block:'start'})";

function ensureEmailCampaignTables($conn) {
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

function currentCampaignAllowance($conn, $shop_id, $month_key) {
    $ins = $conn->prepare("INSERT IGNORE INTO email_campaign_allowances (shop_id, month_key, monthly_limit, used_count) VALUES (?, ?, 2, 0)");
    $ins->bind_param('is', $shop_id, $month_key);
    $ins->execute();

    $st = $conn->prepare("SELECT * FROM email_campaign_allowances WHERE shop_id=? AND month_key=? LIMIT 1");
    $st->bind_param('is', $shop_id, $month_key);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

function campaignRecipients($conn, $shop_id, $audience_type) {
    if ($audience_type === 'buyers') {
        $sql = "SELECT DISTINCT u.id, u.name, u.email
                FROM users u
                JOIN orders o ON o.user_id=u.id AND o.shop_id=u.shop_id
                WHERE u.shop_id=? AND u.is_active=1 AND u.email!=''";
    } elseif ($audience_type === 'recent_buyers') {
        $sql = "SELECT DISTINCT u.id, u.name, u.email
                FROM users u
                JOIN orders o ON o.user_id=u.id AND o.shop_id=u.shop_id
                WHERE u.shop_id=? AND u.is_active=1 AND u.email!=''
                  AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
    } elseif ($audience_type === 'inactive_customers') {
        $sql = "SELECT DISTINCT u.id, u.name, u.email
                FROM users u
                WHERE u.shop_id=? AND u.is_active=1 AND u.email!=''
                  AND NOT EXISTS (
                      SELECT 1 FROM orders o
                      WHERE o.user_id=u.id AND o.shop_id=u.shop_id
                        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                  )";
    } else {
        $sql = "SELECT DISTINCT id, name, email FROM users WHERE shop_id=? AND is_active=1 AND email!=''";
    }

    $st = $conn->prepare($sql);
    $st->bind_param('i', $shop_id);
    $st->execute();
    return $st->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchCampaignProducts($conn, $shop_id, $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $id_sql = implode(',', $ids);
    $res = $conn->query("SELECT id, name, description, price, discount_price, image, image_url
                         FROM products
                         WHERE shop_id=$shop_id AND id IN ($id_sql) AND is_active=1
                         ORDER BY FIELD(id, $id_sql)");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

ensureEmailCampaignTables($conn);
require 'includes/sidebar.php';

$shop_id = (int)$_SESSION['shop_id'];
$owner_id = (int)$_SESSION['owner_id'];
$month_key = date('Y-m');
$success = $error = '';
$allowance = currentCampaignAllowance($conn, $shop_id, $month_key);

$presets = [
    'premium_sale' => ['label' => 'Premium Sale', 'icon' => 'gem', 'hint' => 'Elegant gold offer mail for value deals'],
    'festival_offer' => ['label' => 'Festival Offer', 'icon' => 'stars', 'hint' => 'Warm festival campaign for seasonal sales'],
    'new_arrival' => ['label' => 'New Arrival', 'icon' => 'box-seam', 'hint' => 'Clean launch mail for new products'],
    'clearance' => ['label' => 'Clearance', 'icon' => 'lightning-charge', 'hint' => 'Urgent stock-clearance promotion'],
    'customer_reward' => ['label' => 'Customer Reward', 'icon' => 'heart', 'hint' => 'Loyalty style campaign for repeat buyers'],
];

$audiences = [
    'all_registered' => 'All registered customers',
    'buyers' => 'Customers who ordered before',
    'recent_buyers' => 'Recent buyers - last 90 days',
    'inactive_customers' => 'Inactive customers - no order in 60 days',
];

$campaign_types = [
    'offer' => 'Product Offer',
    'coupon' => 'Coupon Campaign',
    'announcement' => 'Shop Announcement',
    'new_arrival' => 'New Arrival',
    'stock_clearance' => 'Stock Clearance',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'send_campaign') {
    $allowance = currentCampaignAllowance($conn, $shop_id, $month_key);
    $remaining = max(0, (int)$allowance['monthly_limit'] - (int)$allowance['used_count']);

    $preset = array_key_exists($_POST['preset'] ?? '', $presets) ? $_POST['preset'] : 'premium_sale';
    $campaign_type = array_key_exists($_POST['campaign_type'] ?? '', $campaign_types) ? $_POST['campaign_type'] : 'offer';
    $audience_type = array_key_exists($_POST['audience_type'] ?? '', $audiences) ? $_POST['audience_type'] : 'all_registered';
    $subject = trim($_POST['subject'] ?? '');
    $headline = trim($_POST['headline'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $offer_text = trim($_POST['offer_text'] ?? '');
    $coupon_code = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($_POST['coupon_code'] ?? '')));
    $cta_label = trim($_POST['cta_label'] ?? 'Shop Now');
    $product_ids = $_POST['product_ids'] ?? [];

    if ($remaining <= 0) {
        $error = 'Monthly campaign limit reached. Ask superadmin to reset this shop if you need to send again this month.';
    } elseif (!$subject || !$headline || !$description) {
        $error = 'Subject, headline and campaign description are required.';
    } elseif (strlen($subject) > 180 || strlen($headline) > 180) {
        $error = 'Subject and headline must be under 180 characters.';
    } elseif (count($product_ids) > 4) {
        $error = 'Select up to 4 products only. Focused campaigns convert better.';
    } elseif ($campaign_type === 'coupon' && !$coupon_code) {
        $error = 'Coupon campaign needs a coupon code.';
    } else {
        $selected_products = fetchCampaignProducts($conn, $shop_id, $product_ids);
        $recipients = campaignRecipients($conn, $shop_id, $audience_type);

        if (!$recipients) {
            $error = 'No matching registered customers found for the selected audience.';
        } else {
            $status = 'draft';
            $recipient_count = count($recipients);
            $sent_count = 0;
            $failed_count = 0;

            $ins = $conn->prepare("INSERT INTO email_campaigns
                (shop_id, owner_id, month_key, preset, campaign_type, audience_type, subject, headline, description, offer_text, coupon_code, cta_label, status, recipient_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param(
                'iisssssssssssi',
                $shop_id,
                $owner_id,
                $month_key,
                $preset,
                $campaign_type,
                $audience_type,
                $subject,
                $headline,
                $description,
                $offer_text,
                $coupon_code,
                $cta_label,
                $status,
                $recipient_count
            );
            $ins->execute();
            $campaign_id = (int)$ins->insert_id;

            if ($selected_products) {
                $link = $conn->prepare("INSERT INTO email_campaign_products (campaign_id, product_id) VALUES (?, ?)");
                foreach ($selected_products as $p) {
                    $pid = (int)$p['id'];
                    $link->bind_param('ii', $campaign_id, $pid);
                    $link->execute();
                }
            }

            $campaign = [
                'preset' => $preset,
                'subject' => $subject,
                'headline' => $headline,
                'description' => $description,
                'offer_text' => $offer_text,
                'coupon_code' => $coupon_code,
                'cta_label' => $cta_label ?: 'Shop Now',
            ];

            $log = $conn->prepare("INSERT INTO email_campaign_recipients (campaign_id, user_id, email, status, sent_at, error_message) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($recipients as $r) {
                $sent = sendCampaignEmail($r['email'], $r['name'] ?: 'Customer', $shop, $campaign, $selected_products);
                $row_status = $sent ? 'sent' : 'failed';
                $sent_at = $sent ? date('Y-m-d H:i:s') : null;
                $msg = $sent ? null : 'SMTP send failed';
                if ($sent) $sent_count++; else $failed_count++;
                $uid = (int)$r['id'];
                $email = $r['email'];
                $log->bind_param('iissss', $campaign_id, $uid, $email, $row_status, $sent_at, $msg);
                $log->execute();
            }

            $final_status = $sent_count > 0 ? 'sent' : 'failed';
            $sent_at_final = date('Y-m-d H:i:s');
            $upd = $conn->prepare("UPDATE email_campaigns SET status=?, sent_count=?, failed_count=?, sent_at=? WHERE id=? AND shop_id=?");
            $upd->bind_param('siisii', $final_status, $sent_count, $failed_count, $sent_at_final, $campaign_id, $shop_id);
            $upd->execute();

            $quota = $conn->prepare("UPDATE email_campaign_allowances SET used_count=used_count+1 WHERE shop_id=? AND month_key=?");
            $quota->bind_param('is', $shop_id, $month_key);
            $quota->execute();

            $success = "Campaign sent to {$sent_count} customer" . ($sent_count === 1 ? '' : 's') . ". Failed: {$failed_count}.";
            $allowance = currentCampaignAllowance($conn, $shop_id, $month_key);
        }
    }
}

$products_q = $conn->query("SELECT id, name, price, discount_price, stock, image, image_url
                            FROM products
                            WHERE shop_id=$shop_id AND is_active=1
                            ORDER BY created_at DESC");
$products = $products_q ? $products_q->fetch_all(MYSQLI_ASSOC) : [];

$customers_total = (int)$conn->query("SELECT COUNT(*) FROM users WHERE shop_id=$shop_id AND is_active=1 AND email!=''")->fetch_row()[0];
$sent_total = (int)$conn->query("SELECT COALESCE(SUM(sent_count),0) FROM email_campaigns WHERE shop_id=$shop_id")->fetch_row()[0];
$campaign_total = (int)$conn->query("SELECT COUNT(*) FROM email_campaigns WHERE shop_id=$shop_id")->fetch_row()[0];
$remaining = max(0, (int)$allowance['monthly_limit'] - (int)$allowance['used_count']);

$history = $conn->query("SELECT * FROM email_campaigns WHERE shop_id=$shop_id ORDER BY created_at DESC LIMIT 10");
?>

<?php if ($success): ?>
<div class="alert-flash alert-flash-success animate-in"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-flash alert-flash-error animate-in"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<style>
.campaign-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px;}
.campaign-stat{background:#fff;border:1px solid var(--card-border);border-radius:10px;padding:16px;box-shadow:var(--shadow-card);}
.campaign-stat .lbl{font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;}
.campaign-stat .num{font-size:24px;font-weight:800;color:var(--text-primary);line-height:1;}
.campaign-stat .sub{font-size:12px;color:var(--text-muted);margin-top:6px;}
.campaign-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start;}
.preset-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;}
.preset-card{position:relative;border:1px solid var(--card-border);border-radius:10px;padding:13px;background:#fff;cursor:pointer;transition:var(--transition);}
.preset-card:hover{border-color:var(--primary);}
.preset-card input{position:absolute;opacity:0;pointer-events:none;}
.preset-card:has(input:checked){border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.12);background:#F8FAFC;}
.preset-card .title{display:flex;align-items:center;gap:8px;font-weight:800;font-size:13px;color:var(--text-primary);}
.preset-card .hint{font-size:12px;color:var(--text-muted);line-height:1.45;margin-top:6px;}
.campaign-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px;}
.span-2{grid-column:1/-1;}
.product-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px;margin-top:10px;max-height:340px;overflow:auto;padding-right:4px;}
.product-pick{position:relative;display:flex;gap:10px;border:1px solid var(--card-border);border-radius:10px;padding:10px;background:#fff;cursor:pointer;}
.product-pick input{margin-top:4px;}
.product-thumb{width:48px;height:48px;border-radius:8px;background:#F1F5F9;border:1px solid #E2E8F0;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-muted);}
.product-thumb img{width:100%;height:100%;object-fit:cover;}
.product-pick .name{font-size:12.5px;font-weight:700;color:var(--text-primary);line-height:1.35;}
.product-pick .price{font-size:12px;color:var(--success-text);font-weight:700;margin-top:4px;}
.product-pick:has(input:checked){border-color:var(--primary);background:#EFF6FF;}
.campaign-preview{position:sticky;top:92px;}
.preview-mail{overflow:hidden;border-radius:14px;border:1px solid var(--card-border);background:#fff;box-shadow:var(--shadow-card);}
.preview-head{background:#1a1208;padding:22px;text-align:center;color:#c8a97e;font-weight:900;}
.preview-hero{background:linear-gradient(135deg,#1a1208,#c8a97e);padding:24px;text-align:center;color:#fff;}
.preview-badge{display:inline-flex;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.24);border-radius:99px;padding:5px 10px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;}
.preview-title{font-size:20px;font-weight:900;line-height:1.15;margin-bottom:8px;}
.preview-desc{font-size:12.5px;color:rgba(255,255,255,.78);line-height:1.55;}
.preview-body{padding:18px;}
.preview-offer{border-left:4px solid #c8a97e;background:#faf7f2;border-radius:8px;padding:10px;font-size:12px;font-weight:800;margin-bottom:12px;}
.preview-code{border:1px dashed #c8a97e;background:#faf7f2;border-radius:10px;text-align:center;padding:12px;font-weight:900;letter-spacing:1.5px;margin-bottom:12px;}
.preview-cta{display:block;background:#1a1208;color:#fff;text-align:center;border-radius:99px;padding:11px 16px;font-size:12px;font-weight:900;text-decoration:none;}
.history-table td{vertical-align:middle;}
@media(max-width:1050px){.campaign-layout{grid-template-columns:1fr}.campaign-preview{position:static}.campaign-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.campaign-form-grid{grid-template-columns:1fr}.campaign-stats{grid-template-columns:1fr}.product-picker{grid-template-columns:1fr}}
</style>

<div class="campaign-stats animate-in">
    <div class="campaign-stat">
        <div class="lbl">Monthly usage</div>
        <div class="num"><?= (int)$allowance['used_count'] ?>/<?= (int)$allowance['monthly_limit'] ?></div>
        <div class="sub"><?= $remaining ?> campaign<?= $remaining === 1 ? '' : 's' ?> remaining this month</div>
    </div>
    <div class="campaign-stat">
        <div class="lbl">Customers reachable</div>
        <div class="num"><?= number_format($customers_total) ?></div>
        <div class="sub">Only registered customers of this shop</div>
    </div>
    <div class="campaign-stat">
        <div class="lbl">Total sent</div>
        <div class="num"><?= number_format($sent_total) ?></div>
        <div class="sub">Lifetime campaign emails</div>
    </div>
    <div class="campaign-stat">
        <div class="lbl">Campaigns</div>
        <div class="num"><?= number_format($campaign_total) ?></div>
        <div class="sub">Saved in ERP history</div>
    </div>
</div>

<?php if ($remaining <= 0): ?>
<div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:12px;margin-bottom:18px;">
    <i class="bi bi-exclamation-triangle-fill" style="color:#B45309;font-size:18px;"></i>
    <div>
        <div style="font-weight:800;color:#92400E;font-size:13.5px;">Monthly campaign limit reached</div>
        <div style="font-size:12.5px;color:#B45309;">Superadmin can reset your campaign usage for <?= htmlspecialchars($month_key) ?>.</div>
    </div>
</div>
<?php endif; ?>

<div class="campaign-layout">
    <form method="POST" class="card-glass animate-in d1" id="campaignComposer">
        <input type="hidden" name="action" value="send_campaign">
        <div class="section-head">
            <div>
                <div class="section-title">Campaign Composer</div>
                <div class="section-sub">Create one attractive email campaign from ready-made presets.</div>
            </div>
            <span class="status-pill <?= $remaining > 0 ? 'pill-active' : 'pill-cancelled' ?>"><?= $remaining ?> left</span>
        </div>

        <div class="form-label-custom" style="margin-top:12px;">Email preset</div>
        <div class="preset-grid">
            <?php foreach ($presets as $key => $p): ?>
            <label class="preset-card">
                <input type="radio" name="preset" value="<?= $key ?>" <?= $key === 'premium_sale' ? 'checked' : '' ?> data-preset-label="<?= htmlspecialchars($p['label']) ?>">
                <div class="title"><i class="bi bi-<?= $p['icon'] ?>" style="color:var(--primary);"></i><?= htmlspecialchars($p['label']) ?></div>
                <div class="hint"><?= htmlspecialchars($p['hint']) ?></div>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="campaign-form-grid">
            <div>
                <label class="form-label-custom">Campaign type</label>
                <select name="campaign_type" class="input-custom" id="campaignType">
                    <?php foreach ($campaign_types as $k => $v): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label-custom">Target audience</label>
                <select name="audience_type" class="input-custom">
                    <?php foreach ($audiences as $k => $v): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="span-2">
                <label class="form-label-custom">Email subject</label>
                <input type="text" name="subject" class="input-custom js-preview" data-preview="subject" maxlength="180" placeholder="Example: Weekend special offers from <?= htmlspecialchars($shop['name']) ?>" required>
            </div>
            <div class="span-2">
                <label class="form-label-custom">Campaign headline</label>
                <input type="text" name="headline" class="input-custom js-preview" data-preview="headline" maxlength="180" placeholder="Fresh deals are waiting for you" required>
            </div>
            <div class="span-2">
                <label class="form-label-custom">Campaign description</label>
                <textarea name="description" class="input-custom js-preview" data-preview="description" rows="4" placeholder="Tell customers why these products or offers are useful today..." required></textarea>
            </div>
            <div>
                <label class="form-label-custom">Offer line</label>
                <input type="text" name="offer_text" class="input-custom js-preview" data-preview="offer" maxlength="220" placeholder="Example: Free delivery above Rs 999">
            </div>
            <div>
                <label class="form-label-custom">Coupon code</label>
                <input type="text" name="coupon_code" class="input-custom js-preview" data-preview="coupon" maxlength="60" placeholder="Example: SAVE10">
            </div>
            <div>
                <label class="form-label-custom">Button label</label>
                <input type="text" name="cta_label" class="input-custom js-preview" data-preview="cta" maxlength="80" value="Shop Now">
            </div>
            <div>
                <label class="form-label-custom">Send mode</label>
                <input type="text" class="input-custom" value="Send immediately" disabled>
            </div>
        </div>

        <div style="margin-top:18px;">
            <label class="form-label-custom">Feature products - optional, max 4</label>
            <?php if (!$products): ?>
            <div class="empty-state" style="padding:28px 16px;">
                <i class="bi bi-box-seam"></i>
                <h4>No active products</h4>
                <p>Add active products before creating a product campaign.</p>
            </div>
            <?php else: ?>
            <div class="product-picker">
                <?php foreach ($products as $p):
                    $img = !empty($p['image_url']) ? htmlspecialchars($p['image_url'])
                        : (!empty($p['image']) ? (strpos($p['image'], 'http') === 0 ? htmlspecialchars($p['image']) : '../assets/uploads/products/' . htmlspecialchars($p['image'])) : '');
                    $price = $p['discount_price'] ?: $p['price'];
                ?>
                <label class="product-pick">
                    <input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>">
                    <div class="product-thumb">
                        <?php if ($img): ?><img src="<?= $img ?>" alt=""><?php else: ?><i class="bi bi-image"></i><?php endif; ?>
                    </div>
                    <div style="min-width:0;">
                        <div class="name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="price">Rs <?= number_format((float)$price, 0) ?> <?= (int)$p['stock'] <= 5 ? '<span style="color:var(--warning-text);font-weight:700;">Low stock</span>' : '' ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;flex-wrap:wrap;">
            <button type="reset" class="btn-ghost-custom"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            <button type="submit" class="btn-primary-custom" <?= $remaining <= 0 ? 'disabled' : '' ?> onclick="return confirm('Send this campaign to the selected shop customers now?')">
                <i class="bi bi-send-fill"></i> Send Campaign
            </button>
        </div>
    </form>

    <aside class="campaign-preview animate-in d2">
        <div class="card-glass" style="padding:0;overflow:hidden;">
            <div style="padding:16px 18px;border-bottom:1px solid var(--card-border);">
                <div class="section-title">Live Email Preview</div>
                <div class="section-sub">The final email uses the selected preset and products.</div>
            </div>
            <div class="preview-mail">
                <div class="preview-head"><?= htmlspecialchars($shop['name']) ?></div>
                <div class="preview-hero">
                    <div class="preview-badge" id="previewPreset">Premium Sale</div>
                    <div class="preview-title" id="previewHeadline">Fresh deals are waiting for you</div>
                    <div class="preview-desc" id="previewDescription">Add a campaign description to show customers why they should shop today.</div>
                </div>
                <div class="preview-body">
                    <div class="preview-offer" id="previewOffer">Offer line appears here</div>
                    <div class="preview-code" id="previewCoupon">COUPON</div>
                    <a class="preview-cta" id="previewCta">Shop Now</a>
                </div>
            </div>
            <div style="padding:16px 18px;">
                <div style="font-size:12px;color:var(--text-muted);line-height:1.6;">
                    ERP controls included: monthly quota, shop-only recipients, audience targeting, campaign history, delivery counts, preset templates and product linking.
                </div>
            </div>
        </div>
    </aside>
</div>

<div class="card-glass animate-in d3" style="padding:0;overflow:hidden;margin-top:18px;">
    <div style="padding:18px 20px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <div class="section-title">Campaign History</div>
            <div class="section-sub">Latest campaigns sent from this shop.</div>
        </div>
    </div>
    <?php if (!$history || $history->num_rows === 0): ?>
    <div class="empty-state">
        <i class="bi bi-envelope-paper"></i>
        <h4>No campaigns yet</h4>
        <p>Your sent campaign reports will appear here.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table-glass history-table">
            <thead>
                <tr>
                    <th style="padding-left:20px;">Campaign</th>
                    <th>Audience</th>
                    <th>Preset</th>
                    <th>Delivery</th>
                    <th>Status</th>
                    <th style="padding-right:20px;">Sent At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($c = $history->fetch_assoc()): ?>
                <tr>
                    <td style="padding-left:20px;">
                        <div style="font-weight:700;color:var(--text-primary);font-size:13.5px;"><?= htmlspecialchars($c['headline']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($c['subject']) ?></div>
                    </td>
                    <td style="font-size:12.5px;color:var(--text-secondary);"><?= htmlspecialchars($audiences[$c['audience_type']] ?? $c['audience_type']) ?></td>
                    <td><span style="background:#F1F5F9;border:1px solid #E2E8F0;border-radius:6px;padding:4px 8px;font-size:11.5px;font-weight:700;color:var(--text-secondary);"><?= htmlspecialchars($presets[$c['preset']]['label'] ?? $c['preset']) ?></span></td>
                    <td>
                        <div style="font-size:12.5px;font-weight:700;color:var(--success-text);"><?= (int)$c['sent_count'] ?> sent</div>
                        <div style="font-size:11.5px;color:var(--danger-text);"><?= (int)$c['failed_count'] ?> failed</div>
                    </td>
                    <td><span class="status-pill <?= $c['status'] === 'sent' ? 'pill-active' : 'pill-cancelled' ?>"><?= htmlspecialchars(ucfirst($c['status'])) ?></span></td>
                    <td style="padding-right:20px;font-size:12.5px;color:var(--text-muted);"><?= $c['sent_at'] ? date('M j, Y h:i A', strtotime($c['sent_at'])) : date('M j, Y h:i A', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
const presetLabels = Object.fromEntries([...document.querySelectorAll('input[name="preset"]')].map(input => [input.value, input.dataset.presetLabel]));
const presetStyles = {
    premium_sale: ['#1a1208', '#c8a97e', '#faf7f2'],
    festival_offer: ['#2a1205', '#f97316', '#fff7ed'],
    new_arrival: ['#0f172a', '#2563eb', '#eff6ff'],
    clearance: ['#1f0a0a', '#dc2626', '#fef2f2'],
    customer_reward: ['#06251a', '#10b981', '#ecfdf5']
};
const fields = document.querySelectorAll('.js-preview');
function updatePreview() {
    const get = name => document.querySelector(`[data-preview="${name}"]`)?.value.trim();
    const preset = document.querySelector('input[name="preset"]:checked')?.value || 'premium_sale';
    const [dark, accent, soft] = presetStyles[preset] || presetStyles.premium_sale;
    document.getElementById('previewPreset').textContent = presetLabels[preset] || 'Premium Sale';
    document.getElementById('previewHeadline').textContent = get('headline') || 'Fresh deals are waiting for you';
    document.getElementById('previewDescription').textContent = get('description') || 'Add a campaign description to show customers why they should shop today.';
    document.getElementById('previewOffer').textContent = get('offer') || 'Offer line appears here';
    document.getElementById('previewCoupon').textContent = get('coupon') || 'COUPON';
    document.getElementById('previewCta').textContent = get('cta') || 'Shop Now';
    document.querySelector('.preview-head').style.cssText = `background:${dark};padding:22px;text-align:center;color:${accent};font-weight:900;`;
    document.querySelector('.preview-hero').style.background = `linear-gradient(135deg,${dark},${accent})`;
    document.getElementById('previewOffer').style.cssText = `border-left:4px solid ${accent};background:${soft};border-radius:8px;padding:10px;font-size:12px;font-weight:800;margin-bottom:12px;`;
    document.getElementById('previewCoupon').style.cssText = `border:1px dashed ${accent};background:${soft};border-radius:10px;text-align:center;padding:12px;font-weight:900;letter-spacing:1.5px;margin-bottom:12px;`;
    document.getElementById('previewCta').style.background = dark;
}
fields.forEach(el => el.addEventListener('input', updatePreview));
document.querySelectorAll('input[name="preset"]').forEach(el => el.addEventListener('change', updatePreview));
document.querySelectorAll('.product-pick input[type="checkbox"]').forEach(input => {
    input.addEventListener('change', () => {
        const selected = document.querySelectorAll('.product-pick input[type="checkbox"]:checked');
        if (selected.length > 4) {
            input.checked = false;
            alert('Select up to 4 products only.');
        }
    });
});
updatePreview();
</script>

<?php require 'includes/footer.php'; ?>
