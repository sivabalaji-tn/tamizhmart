<?php
// ── This script is made by Siva Balaji sms ──────────────────────
// AJAX: Verify delivery OTP, mark order delivered, update COD wallet
session_start();
require '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['handler_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$handler_id = (int)$_SESSION['handler_id'];
$shop_id    = (int)$_SESSION['handler_shop_id'];
$order_id   = (int)($_POST['order_id'] ?? 0);
$entered    = trim($_POST['otp_code'] ?? '');

if (!$order_id || !$entered) { echo json_encode(['error' => 'Missing data']); exit; }

// Fetch order — verify shop ownership
$o_stmt = $conn->prepare("SELECT total_amount, payment_method, status FROM orders WHERE id=? AND shop_id=?");
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

// Constant-time comparison to prevent timing attacks
if (!hash_equals($otp_row['otp_code'], $entered)) {
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
