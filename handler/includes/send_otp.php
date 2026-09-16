<?php
// ── This script is made by Siva Balaji sms ──────────────────────
// AJAX: Generate OTP, send to customer email via PHPMailer SMTP
session_start();
require '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['handler_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$handler_id = (int)$_SESSION['handler_id'];
$shop_id    = (int)$_SESSION['handler_shop_id'];
$order_id   = (int)($_POST['order_id'] ?? 0);

if (!$order_id) { echo json_encode(['error' => 'Invalid order ID']); exit; }

// Verify order belongs to this shop and is at out_for_delivery status
$o_stmt = $conn->prepare("SELECT o.*, u.name AS cname, u.email AS cemail, s.name AS shop_name FROM orders o JOIN users u ON o.user_id=u.id JOIN shops s ON o.shop_id=s.id WHERE o.id=? AND o.shop_id=?");
$o_stmt->bind_param('ii', $order_id, $shop_id);
$o_stmt->execute();
$order = $o_stmt->get_result()->fetch_assoc();

if (!$order) { echo json_encode(['error' => 'Order not found']); exit; }
if ($order['status'] !== 'out_for_delivery') { echo json_encode(['error' => 'Order must be Out for Delivery to send OTP']); exit; }

$customer_email = $order['cemail'];
if (!$customer_email) { echo json_encode(['error' => 'Customer has no email on record']); exit; }

// Generate 6-digit OTP
$otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Upsert OTP (replace if exists)
$ins = $conn->prepare("INSERT INTO delivery_otps (order_id, handler_id, otp_code, expires_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE handler_id=VALUES(handler_id), otp_code=VALUES(otp_code), sent_at=NOW(), expires_at=VALUES(expires_at), verified_at=NULL");
$ins->bind_param('iiss', $order_id, $handler_id, $otp, $expires);
$ins->execute();

// Log OTP sent
$log = $conn->prepare("INSERT INTO handler_activity_log (handler_id,shop_id,order_id,action,new_value,note) VALUES (?,?,?,'otp_sent',?,?)");
$note = 'OTP sent to ' . $customer_email;
$log->bind_param('iiiss', $handler_id, $shop_id, $order_id, $otp, $note);
$log->execute();

// ── Send OTP email via PHPMailer ─────────────────────────────────
$src = __DIR__ . '/../../vendor/phpmailer/src/';
if (!file_exists($src . 'PHPMailer.php')) {
    echo json_encode(['error' => 'PHPMailer not found on server']); exit;
}

require_once $src . 'Exception.php';
require_once $src . 'PHPMailer.php';
require_once $src . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$order_num  = '#' . str_pad($order['shop_order_number'] ?? $order['id'], 4, '0', STR_PAD_LEFT);
$shop_name  = htmlspecialchars($order['shop_name']);
$cname      = htmlspecialchars($order['cname']);

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f5f3ef;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ef;padding:32px 0;">
<tr><td>
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <tr><td style="background:#7C3AED;padding:28px 32px;text-align:center;">
    <div style="font-size:13px;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:1px;">$shop_name</div>
    <div style="font-size:22px;font-weight:800;color:#fff;margin-top:4px;">Delivery Confirmation OTP</div>
  </td></tr>
  <tr><td style="padding:32px 32px 20px;text-align:center;">
    <div style="font-size:36px;margin-bottom:12px;">📦</div>
    <p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#1E293B;">Your order is on the way, $cname!</p>
    <p style="margin:0;color:#64748B;font-size:14px;line-height:1.6;">Order $order_num is out for delivery. Share the OTP below with our delivery handler to confirm receipt.</p>
  </td></tr>
  <tr><td style="padding:0 32px 28px;text-align:center;">
    <div style="background:#F5F3FF;border:2px dashed #7C3AED;border-radius:12px;padding:24px 32px;display:inline-block;margin:0 auto;">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#7C3AED;margin-bottom:8px;">Your OTP</div>
      <div style="font-size:48px;font-weight:900;letter-spacing:12px;color:#1E293B;font-family:monospace;">$otp</div>
      <div style="font-size:12px;color:#64748B;margin-top:8px;">Valid for 15 minutes</div>
    </div>
  </td></tr>
  <tr><td style="padding:0 32px 28px;">
    <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px;">
      <div style="font-size:13px;font-weight:600;color:#92400E;">⚠️ Do NOT share this OTP with anyone other than the delivery handler at your door.</div>
    </div>
  </td></tr>
  <tr><td style="background:#1E293B;padding:20px 32px;text-align:center;">
    <div style="color:rgba(226,232,240,0.6);font-size:12px;">© {$order['shop_name']} · Powered by TamizhMart</div>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;

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
    $mail->setFrom('sivathetechie24@gmail.com', $order['shop_name']);
    $mail->addAddress($customer_email, $order['cname']);
    $mail->isHTML(true);
    $mail->Subject = '🚚 Your Delivery OTP for Order ' . $order_num . ' — ' . $order['shop_name'];
    $mail->Body    = $html;
    $mail->send();

    echo json_encode(['success' => true, 'masked_email' => substr($customer_email, 0, 3) . '***@' . explode('@', $customer_email)[1]]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to send OTP email: ' . $mail->ErrorInfo]);
}
