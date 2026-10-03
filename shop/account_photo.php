<?php
session_start();
require_once '../config/db.php';
require_once __DIR__.'/includes/customer_account.php';
$shop=caQuery($conn,'SELECT * FROM shops WHERE slug=? AND is_active=1',[$_GET['shop']??''])->get_result()->fetch_assoc();
if (!$shop) { http_response_code(404); exit; }
$user=caRequireCustomer($conn,$shop);
caEnsure($conn);
$row=caQuery($conn,'SELECT photo FROM customer_accounts WHERE user_id=? AND shop_id=?',[$user['id'],$shop['id']])->get_result()->fetch_assoc();
if (empty($row['photo'])) { http_response_code(404); exit; }
header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
echo $row['photo'];
