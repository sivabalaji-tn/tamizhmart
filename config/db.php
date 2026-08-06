<?php
// ── Task 5: Hide PHP version ──────────────────────────────────
ini_set('expose_php', '0');

// ── Task 4: Secure session cookies ───────────────────────────
// Only configure if session has NOT started yet
// (some pages call session_start() before including this file)
if (session_status() === PHP_SESSION_NONE) {
    // HttpOnly → JS cannot steal the cookie
    // Secure   → HTTPS only (live server; ignored on localhost HTTP)
    // SameSite → blocks CSRF
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime',  '7200');
    ini_set('session.name', 'TM_SESS');
}
// If session already active — settings were applied at session_start() time

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tamizhmart_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
?>