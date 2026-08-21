<?php
// ── Task 5: Hide PHP version ──────────────────────────────────
ini_set('expose_php', '0');

// ── Task 4: Secure session cookies ───────────────────────────
// Only configure if session has NOT started yet
// (some pages call session_start() before including this file)
if (session_status() === PHP_SESSION_NONE) {
    // HttpOnly → JS cannot steal the cookie
    // Secure   → HTTPS only (live server; ignored on localhost HTTP)
    // SameSite → Lax (needed for OAuth callback redirects)
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime',  '7200');
    ini_set('session.name', 'TM_SESS');
}

// ── Environment auto-detection for Database ───────────────────
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_is_local = in_array($_host, ['localhost', 'localhost:8080', '127.0.0.1', '127.0.0.1:8080'], true)
             || (substr($_host, -6) === '.local');

if ($_is_local) {
    // Local Docker environment
    define('DB_HOST', 'mysql');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
    define('DB_NAME', 'tamizhmart_db');
} else {
    // Production Plesk environment
    define('DB_HOST', 'localhost');
    define('DB_USER', 'sivabalajisms');
    define('DB_PASS', 'tamizhmart@0103');
    define('DB_NAME', 'tamizhmart_db');
}
unset($_host, $_is_local);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
?>