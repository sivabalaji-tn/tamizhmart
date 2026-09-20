<?php
// ── OTP Debug Tool — REMOVE THIS FILE AFTER DEBUGGING ──────────
// Access: /handler/includes/otp_debug.php?order_id=XX
// Shows stored OTP row for an order so you can confirm what's in DB
session_start();
require '../../config/db.php';

if (!isset($_SESSION['handler_id'])) { die('Not logged in as handler'); }

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { die('Pass ?order_id=XX'); }

// Show all OTP rows for this order
$rows = $conn->query("SELECT *, NOW() AS db_now, expires_at > NOW() AS is_valid FROM delivery_otps WHERE order_id = $order_id ORDER BY id DESC LIMIT 5");

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;">';
echo "DB NOW(): " . $conn->query("SELECT NOW()")->fetch_row()[0] . "\n";
echo "PHP date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP timezone: " . date_default_timezone_get() . "\n\n";

if ($rows->num_rows === 0) {
    echo "No OTP rows found for order $order_id\n";
} else {
    while ($r = $rows->fetch_assoc()) {
        echo "--- OTP Row ID: {$r['id']} ---\n";
        echo "otp_code:    [{$r['otp_code']}]\n";
        echo "sent_at:     {$r['sent_at']}\n";
        echo "expires_at:  {$r['expires_at']}\n";
        echo "verified_at: " . ($r['verified_at'] ?? 'NULL') . "\n";
        echo "is_valid:    " . ($r['is_valid'] ? 'YES - not expired' : 'NO - EXPIRED') . "\n\n";
    }
}
echo '</pre>';
