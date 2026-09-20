<?php
// ── This script is made by Siva Balaji sms ──────────────────────
// AJAX: Return order items for handler order detail
session_start();
require '../../config/db.php';

if (!isset($_SESSION['handler_id'])) { http_response_code(403); exit; }

$order_id = (int)($_GET['order_id'] ?? 0);
$shop_id  = (int)$_SESSION['handler_shop_id'];
session_write_close(); // Release session lock


$check = $conn->query("SELECT id FROM orders WHERE id=$order_id AND shop_id=$shop_id");
if ($check->num_rows === 0) { http_response_code(403); exit; }

$items = $conn->query("
    SELECT oi.*, p.name, p.image, p.image_url, p.stock, c.name AS cat_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = $order_id
");

$result = [];
while ($row = $items->fetch_assoc()) $result[] = $row;

header('Content-Type: application/json');
echo json_encode($result);
