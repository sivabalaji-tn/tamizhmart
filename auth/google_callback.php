<?php
session_start();
require '../config/db.php';
require '../config/google_oauth_config.php';

function oauth_error(string $msg, int $code = 400): never {
    http_response_code($code);
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo "<!DOCTYPE html><html><head><title>Auth Error</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;
    justify-content:center;min-height:100vh;margin:0;background:#0c0c0e;color:#f0ece4}
    .box{text-align:center;padding:40px;max-width:420px}
    h2{color:#f87171;margin-bottom:12px}p{color:#a0aec0;font-size:14px;margin-bottom:24px}
    a{color:#c8a97e;text-decoration:none;font-weight:600;padding:12px 24px;
    border:1px solid rgba(200,169,126,0.4);border-radius:10px;display:inline-block}</style></head>
    <body><div class='box'><h2>&#9888; {$safe}</h2>
    <a href='javascript:history.back()'>&#8592; Go back</a>
    </div></body></html>";
    exit;
}

// STEP 1: Validate CSRF state
$state_in   = $_GET['state']           ?? '';
$state_sess = $_SESSION['oauth_state'] ?? '';
if (!$state_sess || !hash_equals($state_sess, $state_in)) {
    unset($_SESSION['oauth_state'], $_SESSION['oauth_nonce'], $_SESSION['oauth_source']);
    oauth_error('Invalid state parameter. Possible CSRF attempt.');
}
unset($_SESSION['oauth_state']);

// STEP 2: Handle user cancellation
if (isset($_GET['error'])) {
    unset($_SESSION['oauth_nonce'], $_SESSION['oauth_source']);
    $slug = $_SESSION['oauth_shop_slug'] ?? $_SESSION['current_shop_slug'] ?? '';
    unset($_SESSION['oauth_shop_slug']);
    header('Location: login.php' . ($slug ? '?shop=' . urlencode($slug) : ''));
    exit;
}

// STEP 3: Exchange code for tokens
$code = $_GET['code'] ?? '';
if (!$code) { oauth_error('Authorization code missing.'); }

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        'ignore_errors' => true,
    ],
]);

$token_response = file_get_contents(GOOGLE_TOKEN_URL, false, $ctx);
if (!$token_response) { oauth_error('Failed to contact Google token endpoint.'); }

$token_data = json_decode($token_response, true);
if (!isset($token_data['id_token'])) {
    oauth_error($token_data['error_description'] ?? 'Google did not return an ID token.');
}

// STEP 4: Verify token via Google tokeninfo API
$id_token   = $token_data['id_token'];
$verify_ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
$verify_raw = file_get_contents(
    'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token),
    false, $verify_ctx
);
if (!$verify_raw) { oauth_error('Could not reach Google verification endpoint.'); }

$payload = json_decode($verify_raw, true);
if (isset($payload['error_description'])) {
    oauth_error('Token verification failed: ' . $payload['error_description']);
}

// STEP 5: Validate claims
if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID)          { oauth_error('Token audience mismatch.'); }
if (!in_array($payload['iss'] ?? '', ['https://accounts.google.com','accounts.google.com'], true)) {
    oauth_error('Token issuer mismatch.');
}
if (($payload['exp'] ?? 0) < time())                       { oauth_error('ID token has expired. Please try again.'); }
if (!($payload['email_verified'] ?? false))                { oauth_error('Your Google account email is not verified.'); }

$nonce_sess = $_SESSION['oauth_nonce'] ?? '';
if ($nonce_sess && isset($payload['nonce'])) {
    if (!hash_equals($nonce_sess, $payload['nonce'])) {
        unset($_SESSION['oauth_nonce']);
        oauth_error('Nonce mismatch. Possible replay attack.');
    }
}
unset($_SESSION['oauth_nonce']);

// STEP 6: Extract user info
$google_id = $payload['sub']   ?? null;
$email     = $payload['email'] ?? null;
$name      = trim(($payload['given_name'] ?? '') . ' ' . ($payload['family_name'] ?? ''));
if (!$name) { $name = $payload['name'] ?? $email; }
if (!$google_id || !$email) { oauth_error('Google did not provide required account information.'); }

// STEP 7: Read source and shop context
$source    = $_SESSION['oauth_source']    ?? 'login';   // 'login' | 'register'
$shop_slug = $_SESSION['oauth_shop_slug'] ?? null;
unset($_SESSION['oauth_source'], $_SESSION['oauth_shop_slug']);

if (!$shop_slug) { oauth_error('No shop context found. Please sign in from your shop page.'); }

$stmt = $conn->prepare("SELECT id, slug FROM shops WHERE slug = ? AND is_active = 1");
$stmt->bind_param("s", $shop_slug);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$shop) { oauth_error('Shop not available.', 404); }
$shop_id = $shop['id'];

// STEP 8: Find existing user (by google_id first, then by email)
$stmt = $conn->prepare("SELECT id, name FROM users WHERE google_id = ? AND shop_id = ? LIMIT 1");
$stmt->bind_param("si", $google_id, $shop_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$found_by_email = false;
if (!$user) {
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND shop_id = ? LIMIT 1");
    $stmt->bind_param("si", $email, $shop_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($user) { $found_by_email = true; }
}

// ─────────────────────────────────────────────────────────────────
//  STEP 9 — LOGIN vs REGISTER logic
// ─────────────────────────────────────────────────────────────────

if ($source === 'login') {
    // LOGIN PAGE: only allow existing accounts — do NOT auto-create
    if (!$user) {
        // No account found → send to register with friendly message
        header('Location: register.php?shop=' . urlencode($shop_slug)
             . '&google_email=' . urlencode($email)
             . '&notice=not_registered');
        exit;
    }
    // Existing user found by email → silently link Google ID
    if ($found_by_email) {
        $stmt = $conn->prepare("UPDATE users SET google_id=?, auth_provider='google' WHERE id=? LIMIT 1");
        $stmt->bind_param("si", $google_id, $user['id']);
        $stmt->execute();
        $stmt->close();
    }

} else {
    // REGISTER PAGE: create account if not exists
    if (!$user) {
        // Brand new user — create (password NULL, Google-only)
        $stmt = $conn->prepare(
            "INSERT INTO users (shop_id, name, email, password, google_id, auth_provider)
             VALUES (?, ?, ?, NULL, ?, 'google')"
        );
        $stmt->bind_param("isss", $shop_id, $name, $email, $google_id);
        $stmt->execute();
        $user = ['id' => $conn->insert_id, 'name' => $name];
        $stmt->close();
        // Flag: show profile completion tour after redirect
        $_SESSION['show_profile_tour'] = true;
    } elseif ($found_by_email) {
        // Already registered via email → link Google and just sign in
        $stmt = $conn->prepare("UPDATE users SET google_id=?, auth_provider='google' WHERE id=? LIMIT 1");
        $stmt->bind_param("si", $google_id, $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    // If found by google_id already → just sign in normally, no tour
}

// STEP 10: Start fresh secure session
session_regenerate_id(true);
$_SESSION['user_id']           = $user['id'];
$_SESSION['user_name']         = $user['name'];
$_SESSION['shop_id']           = $shop_id;
$_SESSION['current_shop_slug'] = $shop['slug'];
$_SESSION['auth_provider']     = 'google';
// Preserve tour flag through session regeneration
if (isset($_SESSION['show_profile_tour'])) {
    $_SESSION['show_profile_tour'] = true;
}

// STEP 11: Redirect
header('Location: ../shop/index.php?shop=' . urlencode($shop['slug']));
exit;