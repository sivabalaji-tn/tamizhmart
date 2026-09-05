<?php
/**
 * TamizhMart — Razorpay Verify & Place Order
 * POST JSON: { razorpay_order_id, razorpay_payment_id, razorpay_signature,
 *              shop_id, address, notes, amount, coupon_code?, discount_amount? }
 * Returns JSON: { success, order_number } or { success:false, error }
 *
 * Commission note: commission is always logged on the pre-discount subtotal.
 * This ensures superadmin earnings are never reduced by customer coupon discounts.
 */
session_start();
require '../config/db.php';
header('Content-Type: application/json');

function fail($msg) {
    echo json_encode(['success' => false, 'error' => $msg]); exit;
}

if (empty($_SESSION['user_id'])) fail('Not logged in.');

$d = json_decode(file_get_contents('php://input'), true);
if (!$d) fail('Bad request.');

$rz_oid      = trim($d['razorpay_order_id']   ?? '');
$rz_pid      = trim($d['razorpay_payment_id'] ?? '');
$rz_sig      = trim($d['razorpay_signature']  ?? '');
$shop_id     = (int)($d['shop_id']    ?? 0);
$address     = trim($d['address']     ?? '');
$notes       = trim($d['notes']       ?? '');
$subtotal    = floatval($d['amount']  ?? 0);   // pre-discount cart total from client
$coupon_code = strtoupper(trim($d['coupon_code'] ?? ''));

if (!$rz_oid || !$rz_pid || !$rz_sig)  fail('Missing Razorpay payment data.');
if (!$shop_id)                           fail('Missing shop.');
if (!$address)                           fail('Missing delivery address.');

// ── Get this shop's secret ────────────────────────────────────
$row = $conn->query("SELECT setting_value FROM shop_settings
                     WHERE shop_id=$shop_id AND setting_key='razorpay_key_secret' LIMIT 1")->fetch_row();
if (!$row || empty($row[0])) fail('Payment gateway not configured for this shop.');
$secret = $row[0];

// ── Verify Razorpay HMAC signature ───────────────────────────
$expected = hash_hmac('sha256', $rz_oid . '|' . $rz_pid, $secret);
if (!hash_equals($expected, $rz_sig)) {
    error_log("Razorpay sig mismatch shop=$shop_id rz_oid=$rz_oid rz_pid=$rz_pid");
    fail('Payment verification failed. Please contact support.');
}

// ── Signature OK — place order ────────────────────────────────
$user_id = (int)$_SESSION['user_id'];

$shop = $conn->query("SELECT * FROM shops WHERE id=$shop_id LIMIT 1")->fetch_assoc();
$user = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1")->fetch_assoc();

$settings_map = [];
$sr = $conn->query("SELECT setting_key, setting_value FROM shop_settings WHERE shop_id=$shop_id");
while ($r = $sr->fetch_assoc()) $settings_map[$r['setting_key']] = $r['setting_value'];

// ── Fetch cart to get server-authoritative subtotal ───────────
$cq = $conn->query("
    SELECT c.quantity, p.id AS pid, p.name, p.price, p.discount_price
    FROM cart c JOIN products p ON c.product_id = p.id
    WHERE c.user_id=$user_id AND c.shop_id=$shop_id AND p.is_active=1
");
$items = []; $cart_subtotal = 0;
while ($row = $cq->fetch_assoc()) {
    $row['fp']   = floatval($row['discount_price'] ?: $row['price']);
    $row['line'] = $row['fp'] * $row['quantity'];
    $cart_subtotal += $row['line'];
    $items[]     = $row;
}
if (empty($items)) fail('Cart is empty.');

// Use server-calculated cart subtotal (prevents client tampering)
$subtotal = $cart_subtotal;

// ── Server-side coupon re-validation ─────────────────────────
$discount    = 0.0;
$coupon_id   = null;
$applied_code = null;

if ($coupon_code) {
    $st = $conn->prepare("SELECT * FROM coupons WHERE shop_id=? AND code=? AND is_active=1 LIMIT 1");
    $st->bind_param('is', $shop_id, $coupon_code);
    $st->execute();
    $c = $st->get_result()->fetch_assoc();
    if ($c) {
        $expired = $c['expires_at'] && $c['expires_at'] !== '0000-00-00' && $c['expires_at'] < date('Y-m-d');
        $maxed   = $c['max_uses'] !== null && $c['used_count'] >= (int)$c['max_uses'];
        if (!$expired && !$maxed && $subtotal >= floatval($c['min_order'])) {
            $discount = ($c['type'] === 'percent')
                ? min(round($subtotal * floatval($c['value']) / 100, 2), $subtotal)
                : min(floatval($c['value']), $subtotal);
            $coupon_id    = (int)$c['id'];
            $applied_code = $coupon_code;
        }
    }
}

$final_total = round($subtotal - $discount, 2);

$conn->begin_transaction();
try {
    // Next order number for this shop
    $nxt = (int)$conn->query("SELECT COALESCE(MAX(shop_order_number),0)+1 FROM orders WHERE shop_id=$shop_id")->fetch_row()[0];

    // Insert order with final (discounted) total, preserving coupon info
    $ins = $conn->prepare("
        INSERT INTO orders
            (shop_id, user_id, total_amount, discount_amount, coupon_code,
             status, payment_method, payment_status,
             razorpay_order_id, razorpay_payment_id, address, notes, shop_order_number)
        VALUES
            (?,       ?,       ?,            ?,               ?,
             'confirmed', 'online', 'paid',
             ?,                  ?,                   ?,       ?,     ?)
    ");
    // i i d d s s s s s i = 10 params
    $ins->bind_param('iiddsssssi',
        $shop_id, $user_id, $final_total, $discount, $applied_code,
        $rz_oid, $rz_pid, $address, $notes,
        $nxt
    );
    $ins->execute();
    $order_id = (int)$conn->insert_id;
    if (!$order_id) throw new Exception('Order insert returned no ID.');

    // Insert items + reduce stock
    foreach ($items as $it) {
        $oi = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)");
        $oi->bind_param('iiid', $order_id, $it['pid'], $it['quantity'], $it['fp']);
        $oi->execute();
        $stock_upd = $conn->prepare("UPDATE products SET stock=stock-? WHERE id=? AND stock>=?");
        $stock_upd->bind_param('iii', $it['quantity'], $it['pid'], $it['quantity']);
        $stock_upd->execute();
    }

    // Clear cart
    $conn->query("DELETE FROM cart WHERE user_id=$user_id AND shop_id=$shop_id");

    // ── Increment coupon used_count ───────────────────────────
    if ($coupon_id) {
        $conn->query("UPDATE coupons SET used_count=used_count+1 WHERE id=$coupon_id");
    }

    // ── Log commission on PRE-DISCOUNT subtotal ───────────────
    // Commission is always calculated on the original cart value,
    // NOT on the discounted total. Superadmin earnings are never
    // reduced by customer coupon discounts.
    $comm_q = $conn->query("SELECT p.commission_rate FROM shop_subscriptions ss JOIN plans p ON ss.plan_id=p.id WHERE ss.shop_id=$shop_id AND p.commission_rate > 0 ORDER BY ss.id DESC LIMIT 1");
    if ($comm_row = $comm_q->fetch_assoc()) {
        $rate        = floatval($comm_row['commission_rate']);
        $comm_amount = round($subtotal * $rate / 100, 2); // $subtotal = pre-discount
        $conn->query("INSERT INTO commission_log (shop_id, order_id, order_amount, commission_rate, commission_amount) VALUES ($shop_id, $order_id, $subtotal, $rate, $comm_amount)");
    }

    $conn->commit();

    // Notifications (non-fatal)
    try {
        require_once 'includes/notifications.php';
        sendOrderNotifications($conn, $order_id, $shop, $user, $items, $final_total, $settings_map);
    } catch (Throwable $e) {
        error_log('Notification error: ' . $e->getMessage());
    }

    echo json_encode([
        'success'      => true,
        'order_id'     => $order_id,
        'order_number' => str_pad($nxt, 4, '0', STR_PAD_LEFT),
        'payment_id'   => $rz_pid,
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log("Order placement failed after Razorpay payment=$rz_pid : " . $e->getMessage());
    fail('Payment received but order could not be saved. Contact support with Payment ID: ' . $rz_pid);
}