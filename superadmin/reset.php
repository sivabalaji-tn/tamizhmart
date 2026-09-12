<?php
session_start();
require_once __DIR__ . '/includes/audit.php';
saAuditRequireAdmin();
// Password changes go through the authenticated, audited settings form.
header('Location: settings.php');
exit;
