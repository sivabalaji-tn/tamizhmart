<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
session_destroy();
header("Location: login.php");
exit;
