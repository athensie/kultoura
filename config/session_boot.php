<?php
/*
 |--------------------------------------------------------------------
 | SESSION BOOTSTRAP — replaces a bare session_start() everywhere
 |--------------------------------------------------------------------
 | Sets hardened cookie flags before the session actually starts:
 |   - httponly: JS (and so a stray XSS) can't read the session cookie
 |   - samesite=Lax: cross-site requests won't carry the cookie, which
 |     also blankets most CSRF vectors that don't already go through
 |     csrf.php's token check
 |   - secure: only when the request actually arrived over HTTPS —
 |     detected via $_SERVER['HTTPS'] (direct TLS, e.g. local XAMPP
 |     never sets this) or X-Forwarded-Proto (Railway terminates TLS
 |     at its edge and forwards plain HTTP internally, so this is the
 |     only signal available in that environment). Hardcoding this to
 |     true would silently break every login on local HTTP dev.
 */

if (!function_exists('kt_session_start')) {
    function kt_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

kt_session_start();
