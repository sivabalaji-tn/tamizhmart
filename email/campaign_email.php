<?php
/**
 * TamizhMart email campaign helper.
 * Builds preset-based product campaign emails and sends them via PHPMailer.
 */

if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', 'smtp.gmail.com');
    define('MAIL_PORT', 587);
    define('MAIL_USERNAME', 'sivathetechie24@gmail.com');
    define('MAIL_PASSWORD', 'yjqz ofcg htvl qxfu');
    define('MAIL_FROM', 'sivathetechie24@gmail.com');
    define('MAIL_FROMNAME', 'TamizhMart');
}

function campaign_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function campaignPresetConfig($preset) {
    $presets = [
        'premium_sale' => [
            'name' => 'Premium Sale',
            'accent' => '#c8a97e',
            'dark' => '#1a1208',
            'soft' => '#faf7f2',
            'badge' => 'Limited-time offer',
        ],
        'festival_offer' => [
            'name' => 'Festival Offer',
            'accent' => '#f97316',
            'dark' => '#2a1205',
            'soft' => '#fff7ed',
            'badge' => 'Festival special',
        ],
        'new_arrival' => [
            'name' => 'New Arrival',
            'accent' => '#2563eb',
            'dark' => '#0f172a',
            'soft' => '#eff6ff',
            'badge' => 'Fresh in store',
        ],
        'clearance' => [
            'name' => 'Clearance Deal',
            'accent' => '#dc2626',
            'dark' => '#1f0a0a',
            'soft' => '#fef2f2',
            'badge' => 'Stock clearance',
        ],
        'customer_reward' => [
            'name' => 'Customer Reward',
            'accent' => '#10b981',
            'dark' => '#06251a',
            'soft' => '#ecfdf5',
            'badge' => 'For our customers',
        ],
    ];

    return $presets[$preset] ?? $presets['premium_sale'];
}

function buildCampaignEmailHtml($shop, $campaign, $products, $customer_name = 'Customer') {
    $cfg = campaignPresetConfig($campaign['preset'] ?? 'premium_sale');
    $shop_name = campaign_h($shop['name'] ?? 'TamizhMart Shop');
    $headline = campaign_h($campaign['headline'] ?? '');
    $description = nl2br(campaign_h($campaign['description'] ?? ''));
    $offer = campaign_h($campaign['offer_text'] ?? '');
    $coupon = campaign_h($campaign['coupon_code'] ?? '');
    $cta = campaign_h($campaign['cta_label'] ?? 'Shop now');
    $slug = campaign_h($shop['slug'] ?? '');
    $shop_url = 'http://tamizhmart.optikl.ink/shop/index.php?shop=' . rawurlencode($shop['slug'] ?? '');

    $product_html = '';
    foreach ($products as $p) {
        $img = '';
        if (!empty($p['image_url'])) {
            $img = campaign_h($p['image_url']);
        } elseif (!empty($p['image'])) {
            $img = strpos($p['image'], 'http') === 0
                ? campaign_h($p['image'])
                : 'http://tamizhmart.optikl.ink/assets/uploads/products/' . campaign_h($p['image']);
        }

        $price = (float)($p['discount_price'] ?: $p['price']);
        $mrp = !empty($p['discount_price']) ? (float)$p['price'] : 0;
        $product_url = 'http://tamizhmart.optikl.ink/shop/product.php?shop=' . rawurlencode($shop['slug'] ?? '') . '&id=' . (int)$p['id'];

        $image_block = $img
            ? "<img src='{$img}' alt='" . campaign_h($p['name']) . "' style='width:100%;height:150px;object-fit:cover;display:block;'>"
            : "<div style='height:150px;background:{$cfg['soft']};display:flex;align-items:center;justify-content:center;color:{$cfg['accent']};font-size:42px;font-weight:800;'>" . strtoupper(substr($p['name'], 0, 1)) . "</div>";

        $mrp_html = $mrp > 0 ? "<span style='font-size:12px;color:#9ca3af;text-decoration:line-through;margin-left:6px;'>Rs " . number_format($mrp, 0) . "</span>" : '';

        $product_html .= "
            <td style='width:50%;padding:8px;vertical-align:top;'>
                <div style='border:1px solid #eee4d8;border-radius:14px;overflow:hidden;background:#fff;'>
                    {$image_block}
                    <div style='padding:14px;'>
                        <div style='font-size:14px;font-weight:800;color:#1a1208;line-height:1.35;margin-bottom:8px;'>" . campaign_h($p['name']) . "</div>
                        <div style='font-size:15px;font-weight:900;color:{$cfg['accent']};'>Rs " . number_format($price, 0) . "{$mrp_html}</div>
                        <a href='{$product_url}' style='display:inline-block;margin-top:12px;color:{$cfg['dark']};font-size:12px;font-weight:800;text-decoration:none;'>View product &rarr;</a>
                    </div>
                </div>
            </td>";
    }

    if ($product_html) {
        $product_html = "<table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='margin:20px 0 4px;'><tr>{$product_html}</tr></table>";
    }

    $coupon_html = $coupon ? "
        <div style='margin:22px 0;padding:16px;border:1px dashed {$cfg['accent']};background:{$cfg['soft']};border-radius:14px;text-align:center;'>
            <div style='font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#6b7280;font-weight:800;margin-bottom:8px;'>Use coupon code</div>
            <div style='font-size:24px;font-weight:900;letter-spacing:2px;color:{$cfg['dark']};'>{$coupon}</div>
        </div>" : '';

    $offer_html = $offer ? "
        <div style='margin:22px 0 0;background:#fff;border-left:4px solid {$cfg['accent']};padding:14px 16px;border-radius:10px;'>
            <div style='font-size:13px;font-weight:800;color:{$cfg['dark']};'>{$offer}</div>
        </div>" : '';

    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
    <body style='margin:0;padding:0;background:#f5f0ea;font-family:Arial,Helvetica,sans-serif;color:#1a1208;'>
        <div style='display:none;max-height:0;overflow:hidden;'>New update from {$shop_name}: {$headline}</div>
        <div style='max-width:640px;margin:28px auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 36px rgba(26,18,8,0.12);'>
            <div style='background:{$cfg['dark']};padding:30px 34px;text-align:center;'>
                <div style='font-size:22px;font-weight:900;color:{$cfg['accent']};'>{$shop_name}</div>
                <div style='font-size:12px;color:rgba(255,255,255,0.58);margin-top:4px;'>TamizhMart campaign</div>
            </div>
            <div style='background:linear-gradient(135deg,{$cfg['dark']},{$cfg['accent']});padding:34px;text-align:center;'>
                <div style='display:inline-block;background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.24);color:#fff;border-radius:999px;padding:7px 14px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;'>{$cfg['badge']}</div>
                <h1 style='font-size:28px;line-height:1.12;color:#fff;margin:0 0 12px;font-weight:900;'>{$headline}</h1>
                <p style='font-size:14.5px;line-height:1.7;color:rgba(255,255,255,0.78);margin:0;'>Hi " . campaign_h($customer_name) . ", {$description}</p>
            </div>
            <div style='padding:30px 34px;'>
                {$offer_html}
                {$coupon_html}
                {$product_html}
                <div style='text-align:center;margin-top:28px;'>
                    <a href='{$shop_url}' style='display:inline-block;background:{$cfg['dark']};color:#fff;text-decoration:none;border-radius:999px;padding:14px 34px;font-size:14px;font-weight:900;'>{$cta}</a>
                </div>
            </div>
            <div style='background:#faf7f2;border-top:1px solid #eee4d8;padding:18px 34px;text-align:center;'>
                <p style='font-size:12px;color:#8b7b67;line-height:1.6;margin:0;'>
                    You received this email because you registered with <strong>{$shop_name}</strong> on TamizhMart.<br>
                    Shop slug: {$slug}
                </p>
            </div>
        </div>
    </body>
    </html>";
}

function sendCampaignEmail($to_email, $to_name, $shop, $campaign, $products) {
    $src = dirname(__DIR__) . '/vendor/phpmailer/src/';
    if (!file_exists($src . 'PHPMailer.php')) {
        error_log('PHPMailer not found for campaign email.');
        return false;
    }

    require_once $src . 'Exception.php';
    require_once $src . 'PHPMailer.php';
    require_once $src . 'SMTP.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $shop_name = $shop['name'] ?? 'TamizhMart Shop';
        $mail->setFrom(MAIL_FROM, MAIL_FROMNAME . ' - ' . $shop_name);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(MAIL_FROM, $shop_name);
        $mail->isHTML(true);
        $mail->Subject = $campaign['subject'] ?: (($campaign['headline'] ?? 'New offer') . ' - ' . $shop_name);
        $mail->Body = buildCampaignEmailHtml($shop, $campaign, $products, $to_name);
        $mail->AltBody = strip_tags(($campaign['headline'] ?? '') . "\n\n" . ($campaign['description'] ?? '') . "\n\n" . ($campaign['offer_text'] ?? ''));
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Campaign email failed: ' . $e->getMessage());
        return false;
    }
}
?>
