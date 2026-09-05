<?php
/**
 * TamizhMart — Coupon Check (AJAX endpoint)
 * POST JSON: { shop_id, code, subtotal }
 * Returns:   { valid, discount_amount, type, value, message }
 * Commission note: This only calculates discount. Commission is always on subtotal.
 */
session_start();
require '../config/db.php';
header('Content-Type: application/json');

function fail(string $msg): void {
    echo json_encode(['valid' => false, 'discount_amount' => 0, 'message' => $msg]);
    exit;
}

if (empty($_SESSION['user_id'])) fail('Please log in to apply a coupon.');

$d        = json_decode(file_get_contents('php://input'), true);
$shop_id  = (int)($d['shop_id']  ?? 0);
$code     = strtoupper(trim($d['code'] ?? ''));
$subtotal = floatval($d['subtotal'] ?? 0);

if (!$shop_id || !$code || $subtotal <= 0) fail('Invalid request.');

// ── Fetch coupon ──────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT * FROM coupons WHERE shop_id = ? AND code = ? LIMIT 1"
);
$stmt->bind_param('is', $shop_id, $code);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();

if (!$coupon)              fail('Coupon code not found.');
if (!$coupon['is_active']) fail('This coupon is inactive.');

// ── Expiry check ──────────────────────────────────────────────
if ($coupon['expires_at'] && $coupon['expires_at'] !== '0000-00-00') {
    if (strtotime($coupon['expires_at']) < strtotime('today')) {
        fail('This coupon has expired.');
    }
}

// ── Usage limit check ─────────────────────────────────────────
if ($coupon['max_uses'] !== null && $coupon['used_count'] >= (int)$coupon['max_uses']) {
    fail('This coupon has reached its usage limit.');
}

// ── Minimum order check ───────────────────────────────────────
if ($subtotal < floatval($coupon['min_order'])) {
    $min = number_format($coupon['min_order'], 2);
    fail("Minimum order of ₹{$min} required to use this coupon.");
}

// ── Calculate discount ────────────────────────────────────────
$discount = 0.00;
if ($coupon['type'] === 'percent') {
    $discount = round($subtotal * floatval($coupon['value']) / 100, 2);
    $discount = min($discount, $subtotal); // can't exceed cart total
} else {
    $discount = min(floatval($coupon['value']), $subtotal);
}

echo json_encode([
    'valid'           => true,
    'discount_amount' => $discount,
    'type'            => $coupon['type'],
    'value'           => floatval($coupon['value']),
    'message'         => 'Coupon applied! You save ₹' . number_format($discount, 2),
]);
