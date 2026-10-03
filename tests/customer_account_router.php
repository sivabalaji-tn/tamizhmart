<?php
// Isolated HTTP integration server, never an Apache/Plesk entrypoint.
if (PHP_SAPI!=='cli-server' || getenv('APP_ENV')!=='test') { http_response_code(404); exit; }
$database=getenv('CUSTOMER_ACCOUNT_TEST_DB');
if (!is_string($database) || !preg_match('/^tm_account_test_[a-f0-9]{12}$/',$database)) { http_response_code(404); exit; }
$route=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$allowed=['/auth/login.php','/auth/logout.php','/shop/profile.php','/shop/account_photo.php','/shop/cart.php','/shop/cart_action.php','/shop/checkout.php','/shop/reciept.php','/shop/product.php','/shop/index.php'];
if (!in_array($route,$allowed,true)) { http_response_code(404); exit; }
$_SERVER['HTTP_HOST']='localhost:8080';
require_once __DIR__.'/../config/db.php';
$conn->select_db($database);
$file=dirname(__DIR__).$route;
chdir(dirname($file));
require $file;
