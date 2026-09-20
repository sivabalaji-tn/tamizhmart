<?php
// ── This script is made by Siva Balaji sms ──────────────────────
// AJAX: Verify delivery OTP, mark order delivered, update COD wallet
session_start();
require '../../config/db.php';

// PHPMailer namespaces — declared at top level (PHP requires this)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json');


if (!isset($_SESSION['handler_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$handler_id = (int)$_SESSION['handler_id'];
$shop_id    = (int)$_SESSION['handler_shop_id'];
session_write_close(); // Release session lock — prevents other tabs from hanging

$order_id   = (int)($_POST['order_id'] ?? 0);
$entered    = trim($_POST['otp_code'] ?? '');

if (!$order_id || !$entered) { echo json_encode(['error' => 'Missing data']); exit; }

// Fetch order + customer + shop — verify shop ownership
$o_stmt = $conn->prepare("
    SELECT o.id, o.total_amount, o.payment_method, o.status,
           o.shop_order_number, o.address, o.shipping_fee,
           u.name  AS customer_name,
           u.email AS customer_email,
           s.name  AS shop_name,
           s.logo  AS shop_logo,
           s.slug  AS shop_slug,
           s.city  AS shop_city,
           s.address AS shop_address
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN shops s ON o.shop_id = s.id
    WHERE o.id = ? AND o.shop_id = ?
    LIMIT 1
");
$o_stmt->bind_param('ii', $order_id, $shop_id);
$o_stmt->execute();
$order = $o_stmt->get_result()->fetch_assoc();

if (!$order) { echo json_encode(['error' => 'Order not found']); exit; }
if ($order['status'] !== 'out_for_delivery') { echo json_encode(['error' => 'Order is not in Out for Delivery status']); exit; }

// Fetch active OTP
$otp_stmt = $conn->prepare("SELECT * FROM delivery_otps WHERE order_id=? AND verified_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$otp_stmt->bind_param('i', $order_id);
$otp_stmt->execute();
$otp_row = $otp_stmt->get_result()->fetch_assoc();

if (!$otp_row) {
    echo json_encode(['error' => 'OTP has expired or was never sent. Please resend.']); exit;
}

// Trim both sides defensively — DB padding or browser autofill quirks can add whitespace
$stored_otp  = trim((string)$otp_row['otp_code']);
$entered_otp = trim((string)$entered);

if (!hash_equals($stored_otp, $entered_otp)) {
    echo json_encode(['error' => 'Incorrect OTP. Please try again.']); exit;
}


// ── OTP verified — mark delivered ───────────────────────────────
$conn->begin_transaction();
try {
    // 1. Mark OTP as verified
    $upd_otp = $conn->prepare("UPDATE delivery_otps SET verified_at=NOW() WHERE id=?");
    $upd_otp->bind_param('i', $otp_row['id']);
    $upd_otp->execute();


    // 2. Update order status to delivered
    $upd_order = $conn->prepare("UPDATE orders SET status='delivered', updated_at=NOW() WHERE id=?");
    $upd_order->bind_param('i', $order_id);
    $upd_order->execute();

    // 3. Log OTP verification
    $action = 'otp_verified';
    $log1 = $conn->prepare("INSERT INTO handler_activity_log (handler_id,shop_id,order_id,action,old_value,new_value) VALUES (?,?,?,?,'out_for_delivery','delivered')");
    $log1->bind_param('iiis', $handler_id, $shop_id, $order_id, $action);
    $log1->execute();

    $is_cod  = $order['payment_method'] === 'cod';
    $amount  = (float)$order['total_amount'];

    // 4. If COD — add to handler wallet
    if ($is_cod) {
        $upd_wallet = $conn->prepare("UPDATE shop_handlers SET cod_wallet = cod_wallet + ? WHERE id=?");
        $upd_wallet->bind_param('di', $amount, $handler_id);
        $upd_wallet->execute();

        $action2 = 'cod_collected';
        $amt_str = (string)$amount;
        $log2 = $conn->prepare("INSERT INTO handler_activity_log (handler_id,shop_id,order_id,action,new_value,note) VALUES (?,?,?,?,?,?)");
        $note = 'COD collected: ₹' . number_format($amount, 2);
        $log2->bind_param('iiisss', $handler_id, $shop_id, $order_id, $action2, $amt_str, $note);
        $log2->execute();
    }

    $conn->commit();

    // ── Send delivery + promo email to customer ─────────────────────
    if (!empty($order['customer_email'])) {
        $src = __DIR__ . '/../../vendor/phpmailer/src/';
        if (file_exists($src . 'PHPMailer.php')) {
            require_once $src . 'Exception.php';
            require_once $src . 'PHPMailer.php';
            require_once $src . 'SMTP.php';

            // Fetch top 4 active products for promo
            $promo_q = $conn->prepare("
                SELECT p.name, p.price, p.sale_price, p.image, p.image_url, c.name AS cat_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.shop_id = ? AND p.is_active = 1
                ORDER BY p.created_at DESC
                LIMIT 4
            ");
            $promo_q->bind_param('i', $shop_id);
            $promo_q->execute();
            $promo_products = $promo_q->get_result()->fetch_all(MYSQLI_ASSOC);

            // Shop URL
            $is_https   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $base_url   = ($is_https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $shop_url   = $base_url . '/shop/index.php?shop=' . urlencode($order['shop_slug'] ?? '');
            $order_num  = '#' . str_pad($order['shop_order_number'] ?? $order_id, 4, '0', STR_PAD_LEFT);
            $shop_name  = $order['shop_name'];
            $cname      = $order['customer_name'];
            $total_fmt  = '&#x20B9;' . number_format((float)$order['total_amount'], 2);
            $pay_label  = $is_cod ? 'Cash on Delivery' : 'Online Payment';

            // Build product cards HTML
            $product_cards = '';
            foreach ($promo_products as $p) {
                $pname  = htmlspecialchars($p['name']);
                $price  = (float)($p['sale_price'] > 0 ? $p['sale_price'] : $p['price']);
                $oprice = (float)$p['price'];
                $has_sale = $p['sale_price'] > 0 && $p['sale_price'] < $p['price'];

                // Image
                if (!empty($p['image_url'])) {
                    $img = htmlspecialchars($p['image_url']);
                } elseif (!empty($p['image'])) {
                    $img = $base_url . '/assets/uploads/products/' . htmlspecialchars($p['image']);
                } else {
                    $img = '';
                }

                $img_tag = $img
                    ? '<img src="' . $img . '" alt="' . $pname . '" width="100" height="100" style="width:100px;height:100px;object-fit:cover;border-radius:10px;display:block;">'
                    : '<div style="width:100px;height:100px;border-radius:10px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;font-size:28px;">&#128717;</div>';

                $sale_tag = $has_sale
                    ? '<div style="font-size:10px;font-weight:700;background:#7C3AED;color:#FFFFFF;padding:2px 7px;border-radius:4px;display:inline-block;margin-bottom:4px;">OFFER</div><br>'
                    : '';
                $old_price = $has_sale
                    ? '<span style="font-size:11px;color:#94A3B8;text-decoration:line-through;margin-right:4px;">&#x20B9;' . number_format($oprice, 0) . '</span>'
                    : '';

                $product_cards .= '
                <td width="25%" style="padding:8px;vertical-align:top;text-align:center;">
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:12px;border:1px solid #E2E8F0;overflow:hidden;">
                    <tr><td style="padding:12px 10px;text-align:center;">
                      ' . $img_tag . '
                      <div style="margin-top:8px;font-size:12.5px;font-weight:700;color:#1E293B;line-height:1.3;">' . $pname . '</div>
                      <div style="margin-top:5px;">' . $sale_tag . $old_price . '<strong style="font-size:14px;color:#7C3AED;">&#x20B9;' . number_format($price, 0) . '</strong></div>
                    </td></tr>
                    <tr><td style="text-align:center;padding:0 10px 12px;">
                      <a href="' . $shop_url . '" style="display:inline-block;background:#7C3AED;color:#FFFFFF;font-size:11px;font-weight:700;padding:5px 14px;border-radius:6px;text-decoration:none;">Order Now</a>
                    </td></tr>
                  </table>
                </td>';
            }

            // Pad to 4 cells if fewer products
            for ($i = count($promo_products); $i < 4; $i++) {
                $product_cards .= '<td width="25%" style="padding:8px;"></td>';
            }

            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#F0FDF4;font-family:\'Helvetica Neue\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F0FDF4;padding:32px 0;">
<tr><td>
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;">

  <!-- Header -->
  <tr><td style="background:#047857;border-radius:16px 16px 0 0;padding:28px 32px;text-align:center;">
    <div style="width:60px;height:60px;background:rgba(255,255,255,0.15);border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:28px;line-height:60px;">&#x2705;</div>
    <div style="font-size:13px;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px;">' . htmlspecialchars($shop_name) . '</div>
    <div style="font-size:24px;font-weight:900;color:#FFFFFF;">Order Delivered!</div>
    <div style="font-size:13px;color:rgba(255,255,255,0.65);margin-top:4px;">Your package has been handed over successfully.</div>
  </td></tr>

  <!-- Order Summary -->
  <tr><td style="background:#FFFFFF;padding:24px 32px;">
    <p style="margin:0 0 6px;font-size:17px;font-weight:700;color:#1E293B;">Hey ' . htmlspecialchars($cname) . ' &#x1F44B;</p>
    <p style="margin:0 0 20px;font-size:14px;color:#64748B;line-height:1.6;">Your order <strong style="color:#047857;">' . $order_num . '</strong> from <strong>' . htmlspecialchars($shop_name) . '</strong> has been delivered. Thank you for shopping with us!</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC;border-radius:10px;overflow:hidden;margin-bottom:20px;">
      <tr>
        <td style="padding:10px 16px;font-size:12px;color:#64748B;border-bottom:1px solid #E2E8F0;">ORDER NUMBER</td>
        <td style="padding:10px 16px;font-size:13px;font-weight:700;color:#1E293B;border-bottom:1px solid #E2E8F0;text-align:right;">' . $order_num . '</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:12px;color:#64748B;border-bottom:1px solid #E2E8F0;">TOTAL PAID</td>
        <td style="padding:10px 16px;font-size:14px;font-weight:900;color:#047857;border-bottom:1px solid #E2E8F0;text-align:right;">' . $total_fmt . '</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:12px;color:#64748B;">PAYMENT</td>
        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:#1E293B;text-align:right;">' . $pay_label . '</td>
      </tr>
    </table>

    <div style="text-align:center;">
      <a href="' . $shop_url . '" style="display:inline-block;background:#047857;color:#FFFFFF;font-size:14px;font-weight:700;padding:12px 28px;border-radius:50px;text-decoration:none;letter-spacing:0.3px;">&#x1F6CD; Shop Again</a>
    </div>
  </td></tr>

  <!-- Divider -->
  <tr><td style="background:#FFFFFF;padding:0 32px;">
    <div style="height:1px;background:#E2E8F0;"></div>
  </td></tr>

  ' . (!empty($promo_products) ? '
  <!-- Promo section -->
  <tr><td style="background:#FFFFFF;padding:24px 32px 8px;">
    <div style="text-align:center;margin-bottom:18px;">
      <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:#7C3AED;margin-bottom:4px;">&#127881; Fresh From Our Shop</div>
      <div style="font-size:18px;font-weight:900;color:#1E293B;">You Might Also Love These</div>
      <div style="font-size:13px;color:#64748B;margin-top:4px;">Handpicked for you from ' . htmlspecialchars($shop_name) . '</div>
    </div>
  </td></tr>
  <tr><td style="background:#FFFFFF;padding:0 24px 20px;">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>' . $product_cards . '</tr></table>
  </td></tr>
  <tr><td style="background:#FFFFFF;padding:0 32px 24px;text-align:center;">
    <a href="' . $shop_url . '" style="display:inline-block;background:#F5F3FF;border:2px solid #7C3AED;color:#7C3AED;font-size:14px;font-weight:800;padding:12px 32px;border-radius:50px;text-decoration:none;">&#x1F6D2; Browse All Products</a>
  </td></tr>
  ' : '') . '

  <!-- Footer -->
  <tr><td style="background:#1E293B;border-radius:0 0 16px 16px;padding:20px 32px;text-align:center;">
    <div style="font-size:13px;font-weight:700;color:#FFFFFF;margin-bottom:4px;">&#x1F496; Thank you for choosing ' . htmlspecialchars($shop_name) . '!</div>
    <div style="font-size:11.5px;color:rgba(148,163,184,0.8);">We\'re always here to serve you the best.</div>
    <div style="margin-top:10px;">
      <a href="' . $shop_url . '" style="color:#A78BFA;font-size:12px;text-decoration:none;">Visit Shop &#x2192;</a>
    </div>
    <div style="font-size:11px;color:rgba(100,116,139,0.6);margin-top:10px;">Powered by TamizhMart</div>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>';

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'sivathetechie24@gmail.com';
                $mail->Password   = 'yjqz ofcg htvl qxfu';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom('sivathetechie24@gmail.com', $shop_name);
                $mail->addAddress($order['customer_email'], $cname);
                $mail->isHTML(true);
                $mail->Subject = '✅ Your order ' . $order_num . ' has been delivered — ' . $shop_name;
                $mail->Body    = $html;
                $mail->send();
            } catch (MailException $e) {
                // Email failed — don't surface it, delivery is already confirmed
                error_log('Delivery email failed for order ' . $order_id . ': ' . $mail->ErrorInfo);
            }
        }
    }

    // ── Return success to handler UI ────────────────────────────────
    echo json_encode([
        'success' => true,
        'is_cod'  => $is_cod,
        'amount'  => number_format($amount, 2),
        'message' => $is_cod
            ? '✅ Order delivered! ₹' . number_format($amount, 2) . ' added to your COD wallet.'
            : '✅ Order delivered successfully!'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Database error. Please try again.']);
}
