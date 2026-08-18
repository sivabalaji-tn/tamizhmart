<?php
require_once '../config/google_oauth_config.php';

/**
 * Generates a Google OAuth authorization URL.
 * @param string $shop_slug  The current shop slug
 * @param string $source     'login' or 'register' — controls behaviour in callback
 */
function google_oauth_url(string $shop_slug, string $source = 'login'): string {
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(24));

    $_SESSION['oauth_state']     = $state;
    $_SESSION['oauth_nonce']     = $nonce;
    $_SESSION['oauth_shop_slug'] = $shop_slug;
    $_SESSION['oauth_source']    = $source;   // 'login' | 'register'

    return GOOGLE_AUTH_URL . '?' . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'nonce'         => $nonce,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);
}

