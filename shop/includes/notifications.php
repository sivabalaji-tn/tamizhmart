<?php
/**
 * TamizhMart — Order Notifications
 * Uses PHPMailer + Gmail SMTP (free, no cost)
 *
 * SETUP REQUIRED (one time only):
 *   1. composer require phpmailer/phpmailer  (run in project root)
 *   2. Set your Gmail + App Password below
 */

// ══════════════════════════════════════════════════════════════
//  YOUR GMAIL SETTINGS — fill these in
// ══════════════════════════════════════════════════════════════
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'sivathetechie24@gmail.com');   // ← your Gmail address
define('MAIL_PASSWORD', 'yjqz ofcg htvl qxfu');    // ← 16-char App Password from Step 2
define('MAIL_FROM',     'sivathetechie24@gmail.com');   // ← same Gmail address
define('MAIL_FROMNAME', 'TamizhMart');
// ══════════════════════════════════════════════════════════════

// PHPMailer classes — must be declared outside functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendOrderEmail($to_email, $to_name, $shop_name, $order_num, $items, $total, $address) {
    // Load PHPMailer (manual install — no Composer needed)
    $src = dirname(__DIR__, 2) . '/vendor/phpmailer/src/';
    if (!file_exists($src . 'PHPMailer.php')) {
        error_log('PHPMailer not found. Copy Exception.php, PHPMailer.php, SMTP.php to vendor/phpmailer/src/');
        return false;
    }
    require_once $src . 'Exception.php';
    require_once $src . 'PHPMailer.php';
    require_once $src . 'SMTP.php';

    // Build items HTML
    $items_html = '';
    foreach ($items as $item) {
        $fp    = floatval($item['fp'] ?? $item['price'] ?? 0);
        $qty   = intval($item['quantity']);
        $name  = htmlspecialchars($item['name']);
        $items_html .= "
        <tr>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;'>$name</td>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;text-align:center;'>$qty</td>
            <td style='padding:10px 16px;border-bottom:1px solid #f0f0f0;font-size:14px;text-align:right;font-weight:700;'>₹" . number_format($fp * $qty, 2) . "</td>
        </tr>";
    }

    $body = "
    <!DOCTYPE html>
    <html><head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;'>
        <div style='max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);'>
            <div style='background:#1a1208;padding:28px 32px;text-align:center;'>
                <div style='font-size:22px;font-weight:800;color:#c8a97e;'>$shop_name</div>
                <div style='font-size:13px;color:rgba(255,255,255,0.5);margin-top:4px;'>Order Confirmation</div>
            </div>
            <div style='padding:28px 32px;'>
                <h2 style='font-size:20px;font-weight:800;margin:0 0 6px;'>Thank you, " . htmlspecialchars($to_name) . "! 🎉</h2>
                <p style='color:#666;font-size:14px;margin:0 0 24px;'>Your order has been placed. We'll get it ready soon.</p>
                <div style='background:#faf7f2;border:1px solid #e8ddd0;border-radius:10px;padding:16px;margin-bottom:24px;text-align:center;'>
                    <div style='font-size:13px;color:#888;'>Order Number</div>
                    <div style='font-size:22px;font-weight:800;color:#c8a97e;'>#$order_num</div>
                </div>
                <table style='width:100%;border-collapse:collapse;margin-bottom:16px;'>
                    <thead>
                        <tr style='background:#f9f9f9;'>
                            <th style='padding:10px 16px;text-align:left;font-size:12px;color:#888;border-bottom:2px solid #f0f0f0;'>Product</th>
                            <th style='padding:10px 16px;text-align:center;font-size:12px;color:#888;border-bottom:2px solid #f0f0f0;'>Qty</th>
                            <th style='padding:10px 16px;text-align:right;font-size:12px;color:#888;border-bottom:2px solid #f0f0f0;'>Price</th>
                        </tr>
                    </thead>
                    <tbody>$items_html</tbody>
                </table>
                <div style='display:flex;justify-content:space-between;padding:14px 16px;background:#1a1208;border-radius:10px;margin-bottom:24px;'>
                    <div style='color:rgba(255,255,255,0.6);font-size:14px;font-weight:600;'>Total</div>
                    <div style='color:#c8a97e;font-size:18px;font-weight:800;'>₹" . number_format($total, 2) . "</div>
                </div>
                <div style='background:#f9f9f9;border-radius:10px;padding:16px;margin-bottom:20px;'>
                    <div style='font-size:12px;color:#888;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;'>Delivery Address</div>
                    <div style='font-size:14px;color:#333;line-height:1.6;'>" . nl2br(htmlspecialchars($address)) . "</div>
                </div>
            </div>
            <div style='background:#f5f5f5;padding:16px 32px;text-align:center;border-top:1px solid #eee;'>
                <p style='font-size:12px;color:#aaa;margin:0;'>Automated confirmation from <strong>$shop_name</strong> via TamizhMart</p>
            </div>
        </div>
    </body></html>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROMNAME . ' — ' . $shop_name);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(MAIL_FROM, $shop_name);

        $mail->isHTML(true);
        $mail->Subject = "Your order #$order_num is confirmed — $shop_name";
        $mail->Body    = $body;
        $mail->AltBody = "Hi $to_name, your order #$order_num has been placed. Total: ₹" . number_format($total, 2);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed for order #$order_num : " . $e->getMessage());
        return false;
    }
}

function sendWhatsAppNotification($shop_phone, $message) {
    if (empty($shop_phone)) return false;
    $log = date('Y-m-d H:i:s') . " | WhatsApp to $shop_phone: $message\n";
    file_put_contents(__DIR__ . '/../../logs/whatsapp.log', $log, FILE_APPEND | LOCK_EX);
    return true;
}

function sendOrderNotifications($conn, $order_id, $shop, $user, $items, $total, $shop_settings) {
    $row       = $conn->query("SELECT shop_order_number, payment_method FROM orders WHERE id=$order_id")->fetch_assoc();
    $order_num = str_pad($row['shop_order_number'] ?? $order_id, 4, '0', STR_PAD_LEFT);

    // Email to customer
    sendOrderEmail(
        $user['email'],
        $user['name'],
        $shop['name'],
        $order_num,
        $items,
        $total,
        $user['address'] ?? ''
    );

    // WhatsApp log to owner
    $owner_phone = $shop_settings['phone'] ?? '';
    if ($owner_phone) {
        $item_list = implode(', ', array_map(fn($i) => $i['name'] . ' x' . $i['quantity'], $items));
        $payment   = strtoupper($row['payment_method'] ?? 'COD');
        sendWhatsAppNotification($owner_phone,
            "🛍️ New Order #$order_num on {$shop['name']}!\n"
          . "Customer: {$user['name']}\n"
          . "Items: $item_list\n"
          . "Total: ₹" . number_format($total, 2) . "\n"
          . "Payment: $payment"
        );
    }
}

// ============================================================
//  REGISTRATION EMAILS
// ============================================================

/**
 * Email 1 — Sent to the OWNER after registering their shop
 * Welcome email with shop link, login link, getting started tips
 */
function sendOwnerRegistrationEmail($to_email, $owner_name, $shop_name, $shop_slug, $shop_url) {
    $src = dirname(__DIR__, 2) . '/vendor/phpmailer/src/';
    if (!file_exists($src . 'PHPMailer.php')) return false;
    require_once $src . 'Exception.php';
    require_once $src . 'PHPMailer.php';
    require_once $src . 'SMTP.php';

    $login_url    = 'http://tamizhmart.optikl.ink/owner/login.php';
    $dashboard_url= 'http://tamizhmart.optikl.ink/owner/dashboard.php';

    $body = "
    <!DOCTYPE html>
    <html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:Arial,Helvetica,sans-serif; background:#f5f0ea; color:#1a1208; }
        .wrap { max-width:580px; margin:32px auto; background:#fff; border-radius:20px; overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,0.1); }
        .header { background:#1a1208; padding:36px 40px; text-align:center; }
        .logo { font-size:24px; font-weight:900; color:#c8a97e; letter-spacing:-0.5px; }
        .logo span { color:#fff; }
        .header-sub { font-size:13px; color:rgba(255,255,255,0.4); margin-top:4px; }
        .hero { background:linear-gradient(135deg,#1a1208,#2d1f0a); padding:40px; text-align:center; }
        .emoji { font-size:52px; display:block; margin-bottom:16px; }
        .hero h1 { font-size:24px; font-weight:900; color:#fff; letter-spacing:-0.5px; margin-bottom:8px; }
        .hero p { font-size:14px; color:rgba(255,255,255,0.5); line-height:1.6; }
        .body { padding:36px 40px; }
        .hi { font-size:16px; font-weight:700; margin-bottom:16px; color:#1a1208; }
        .intro { font-size:14px; color:#555; line-height:1.7; margin-bottom:28px; }
        /* Shop card */
        .shop-card { background:#faf7f2; border:1px solid #e8ddd0; border-radius:14px; padding:24px; margin-bottom:28px; }
        .shop-card-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#c8a97e; margin-bottom:14px; }
        .shop-row { display:flex; align-items:flex-start; gap:10px; padding:8px 0; border-bottom:1px solid #f0e8df; }
        .shop-row:last-child { border-bottom:none; }
        .shop-row-label { font-size:12px; font-weight:700; color:#888; min-width:100px; padding-top:1px; }
        .shop-row-val { font-size:13.5px; color:#1a1208; font-weight:500; word-break:break-all; }
        .shop-link { color:#c8a97e; text-decoration:none; font-weight:700; }
        /* Steps */
        .steps-title { font-size:13px; font-weight:700; color:#1a1208; margin-bottom:16px; }
        .step { display:flex; align-items:flex-start; gap:14px; margin-bottom:14px; }
        .step-num { width:28px; height:28px; border-radius:50%; background:#1a1208; color:#c8a97e; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
        .step-text { font-size:13.5px; color:#444; line-height:1.6; }
        .step-text strong { color:#1a1208; display:block; margin-bottom:2px; }
        /* CTA */
        .cta-wrap { text-align:center; padding:28px 0 0; }
        .cta-btn { display:inline-block; background:#c8a97e; color:#1a1208; text-decoration:none; padding:14px 36px; border-radius:99px; font-size:14px; font-weight:800; letter-spacing:0.3px; }
        .cta-btn:hover { background:#b8996e; }
        .cta-sub { font-size:12px; color:#999; margin-top:10px; }
        /* Footer */
        .footer { background:#faf7f2; padding:24px 40px; text-align:center; border-top:1px solid #ede8e0; }
        .footer p { font-size:12px; color:#aaa; line-height:1.7; }
        .footer strong { color:#888; }
    </style>
    </head>
    <body>
    <div class='wrap'>
        <div class='header'>
            <div class='logo'>Tamizhmart</div>
            <div class='header-sub'>Tamil Nadu's Local Shopping Platform</div>
        </div>
        <div class='hero'>
            <span class='emoji'>🎉</span>
            <h1>Your Shop is Live!</h1>
            <p>Welcome to TamizhMart — your online store is ready to go.</p>
        </div>
        <div class='body'>
            <p class='hi'>Hi " . htmlspecialchars($owner_name) . ",</p>
            <p class='intro'>
                Congratulations! Your shop <strong>" . htmlspecialchars($shop_name) . "</strong> has been
                successfully created on TamizhMart. You can now log in to your dashboard,
                add products, and start accepting orders from customers across Tamil Nadu.
            </p>

            <!-- Shop Details Card -->
            <div class='shop-card'>
                <div class='shop-card-title'>📋 Your Shop Details</div>
                <div class='shop-row'>
                    <div class='shop-row-label'>Shop Name</div>
                    <div class='shop-row-val'>" . htmlspecialchars($shop_name) . "</div>
                </div>
                <div class='shop-row'>
                    <div class='shop-row-label'>Shop URL</div>
                    <div class='shop-row-val'><a href='$shop_url' class='shop-link'>$shop_url</a></div>
                </div>
                <div class='shop-row'>
                    <div class='shop-row-label'>Shop Slug</div>
                    <div class='shop-row-val'>$shop_slug</div>
                </div>
                <div class='shop-row'>
                    <div class='shop-row-label'>Owner Email</div>
                    <div class='shop-row-val'>" . htmlspecialchars($to_email) . "</div>
                </div>
                <div class='shop-row'>
                    <div class='shop-row-label'>Dashboard</div>
                    <div class='shop-row-val'><a href='$dashboard_url' class='shop-link'>$dashboard_url</a></div>
                </div>
                <div class='shop-row'>
                    <div class='shop-row-label'>Platform</div>
                    <div class='shop-row-val'>TamizhMart — Tamil Nadu</div>
                </div>
            </div>

            <!-- Getting started steps -->
            <p class='steps-title'>🚀 Get Started in 3 Steps</p>
            <div class='step'>
                <div class='step-num'>1</div>
                <div class='step-text'>
                    <strong>Complete Setup Wizard</strong>
                    Log in and finish the setup — add your logo, shop address, and contact details.
                </div>
            </div>
            <div class='step'>
                <div class='step-num'>2</div>
                <div class='step-text'>
                    <strong>Add Your Products</strong>
                    Go to Products → Add Product. Upload images, set prices, and manage stock.
                </div>
            </div>
            <div class='step'>
                <div class='step-num'>3</div>
                <div class='step-text'>
                    <strong>Share Your Shop Link</strong>
                    Share <a href='$shop_url' style='color:#c8a97e;font-weight:600;'>your shop URL</a>
                    on WhatsApp, Instagram, and with customers to start receiving orders.
                </div>
            </div>

            <div class='cta-wrap'>
                <a href='$login_url' class='cta-btn'>Login to Dashboard &rarr;</a>
                <p class='cta-sub'>Use your registered email and password to sign in.</p>
            </div>
        </div>
        <div class='footer'>
            <p>
                This email was sent because you registered a shop on TamizhMart.<br>
                <strong>Made with ♥ by SM Tech</strong> · Tamil Nadu, India
            </p>
        </div>
    </div>
    </body></html>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, 'TamizhMart');
        $mail->addAddress($to_email, $owner_name);
        $mail->isHTML(true);
        $mail->Subject = "🎉 Your shop \"$shop_name\" is live on TamizhMart!";
        $mail->Body    = $body;
        $mail->AltBody = "Hi $owner_name, your shop $shop_name is live! Visit: $shop_url | Login: $login_url";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Owner registration email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Email 2 — Sent to SUPER ADMIN when a new shop registers
 * New shop notification with owner details
 */
function sendAdminNewShopEmail($owner_name, $owner_email, $shop_name, $shop_slug, $shop_url) {
    $src = dirname(__DIR__, 2) . '/vendor/phpmailer/src/';
    if (!file_exists($src . 'PHPMailer.php')) return false;
    require_once $src . 'Exception.php';
    require_once $src . 'PHPMailer.php';
    require_once $src . 'SMTP.php';

    $admin_url = 'http://tamizhmart.optikl.ink/superadmin/shops.php';

    $body = "
    <!DOCTYPE html>
    <html><head><meta charset='UTF-8'>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:Arial,sans-serif; background:#f5f0ea; color:#1a1208; }
        .wrap { max-width:520px; margin:32px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.1); }
        .header { background:#1a1208; padding:24px 32px; display:flex; align-items:center; justify-content:space-between; }
        .logo { font-size:18px; font-weight:900; color:#c8a97e; }
        .badge { background:rgba(200,169,126,0.15); color:#c8a97e; border:1px solid rgba(200,169,126,0.3); padding:4px 12px; border-radius:99px; font-size:11px; font-weight:700; }
        .body { padding:28px 32px; }
        .alert-icon { font-size:36px; margin-bottom:12px; }
        h2 { font-size:20px; font-weight:800; margin-bottom:8px; }
        .sub { font-size:13.5px; color:#666; margin-bottom:24px; line-height:1.6; }
        .info-table { width:100%; border-collapse:collapse; margin-bottom:24px; }
        .info-table td { padding:10px 14px; font-size:13.5px; border-bottom:1px solid #f0e8df; }
        .info-table td:first-child { font-weight:700; color:#888; width:120px; }
        .info-table tr:last-child td { border-bottom:none; }
        .info-table td a { color:#c8a97e; }
        .cta { display:inline-block; background:#1a1208; color:#c8a97e; text-decoration:none; padding:12px 28px; border-radius:99px; font-size:13.5px; font-weight:700; }
        .footer { background:#faf7f2; padding:16px 32px; text-align:center; font-size:12px; color:#aaa; border-top:1px solid #ede8e0; }
    </style>
    </head>
    <body>
    <div class='wrap'>
        <div class='header'>
            <div class='logo'>TamizhMart</div>
            <div class='badge'>Super Admin Alert</div>
        </div>
        <div class='body'>
            <div class='alert-icon'>🏪</div>
            <h2>New Shop Registered</h2>
            <p class='sub'>A new shop owner has registered on TamizhMart. Here are the details:</p>
            <table class='info-table'>
                <tr><td>Owner Name</td><td>" . htmlspecialchars($owner_name) . "</td></tr>
                <tr><td>Owner Email</td><td>" . htmlspecialchars($owner_email) . "</td></tr>
                <tr><td>Shop Name</td><td><strong>" . htmlspecialchars($shop_name) . "</strong></td></tr>
                <tr><td>Shop Slug</td><td>$shop_slug</td></tr>
                <tr><td>Shop URL</td><td><a href='$shop_url'>$shop_url</a></td></tr>
                <tr><td>Registered At</td><td>" . date('d M Y, h:i A') . "</td></tr>
            </table>
            <a href='$admin_url' class='cta'>View in Super Admin &rarr;</a>
        </div>
        <div class='footer'>TamizhMart Platform · Made with ♥ by SM Tech</div>
    </div>
    </body></html>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, 'TamizhMart System');
        $mail->addAddress(MAIL_USERNAME); // sends to admin (your Gmail)
        $mail->isHTML(true);
        $mail->Subject = "🏪 New Shop Registered: $shop_name";
        $mail->Body    = $body;
        $mail->AltBody = "New shop registered: $shop_name by $owner_name ($owner_email). URL: $shop_url";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Admin notification email failed: " . $e->getMessage());
        return false;
    }
}