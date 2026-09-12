<?php
// ── This script is made by Siva Balaji sms ──────────────────────
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/includes/audit.php';
if (!empty($_SESSION['superadmin_id'])) {
    try {
        saAuditAuth($conn, 'logout');
    } catch (Throwable $exception) {
        error_log('Superadmin sign-out audit failed: ' . get_class($exception));
    }
}
session_destroy();
header("Location: login.php");
exit;
