<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
// Clear only handler session keys — don't touch owner session
unset($_SESSION['handler_id'], $_SESSION['handler_shop_id'], $_SESSION['handler_name'], $_SESSION['handler_shop_name']);
header('Location: login.php');
exit;
