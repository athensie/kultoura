<?php
/*
 |--------------------------------------------------------------------
 | "Continue with Google" — one file handles both steps:
 |   google.php?action=start  -> sends the visitor to Google
 |   google.php?code=...      -> Google sends them back here (callback)
 |
 | Needs two environment variables (Railway > kultoura > Variables):
 |   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET
 | Optional: GOOGLE_REDIRECT_URI (only if the auto-detected one is wrong)
 |
 | Only creates / signs in regular site users (the `users` table).
 | Admin accounts are never created or signed in through Google.
 |--------------------------------------------------------------------
 */
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';

define('BASE_URL', '/kultoura');

function g_env(string $key): string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_SERVER[$key] ?? '';
    }
    return (string) $v;
}

function g_fail(string $message): void
{
    $_SESSION['error'] = $message;
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

function g_redirect_uri(): string
{
    $override = g_env('GOOGLE_REDIRECT_URI');
    if ($override !== '') {
        return $override;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/auth/google.php';
}

function g_http(string $url, ?array $postFields = null, ?string $bearer = null): ?array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ];
    if ($postFields !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($postFields);
    }
    if ($bearer !== null) {
        $opts[CURLOPT_HTTPHEADER] = ['Authorization: Bearer ' . $bearer];
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

// Already signed in? Nothing to do.
if (isset($_SESSION['user_id']) || isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$clientId     = g_env('GOOGLE_CLIENT_ID');
$clientSecret = g_env('GOOGLE_CLIENT_SECRET');
if ($clientId === '' || $clientSecret === '') {
    g_fail('Google sign-in is not set up yet.');
}

/*
 |--------------------------------------------------------------------
 | STEP 1 — send the visitor to Google's account picker
 |--------------------------------------------------------------------
 */
if (($_GET['action'] ?? '') === 'start') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => g_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

/*
 |--------------------------------------------------------------------
 | STEP 2 — Google sent them back with ?code=...
 |--------------------------------------------------------------------
 */
if (isset($_GET['error'])) {
    g_fail('Google sign-in was cancelled.');
}

$expectedState = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
$givenState = (string) ($_GET['state'] ?? '');
if ($expectedState === '' || !hash_equals($expectedState, $givenState)) {
    g_fail('Google sign-in expired. Please try again.');
}

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
    g_fail('Google sign-in failed. Please try again.');
}

// Trade the code for an access token.
$token = g_http('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => g_redirect_uri(),
    'grant_type'    => 'authorization_code',
]);
if (!$token || empty($token['access_token'])) {
    error_log('Google token exchange failed: ' . json_encode($token));
    g_fail('Google sign-in failed. Please try again.');
}

// Ask Google who this is.
$info = g_http('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']);
$email = strtolower(trim((string) ($info['email'] ?? '')));
if ($email === '' || empty($info['email_verified'])) {
    g_fail('Your Google account does not have a verified email address.');
}
$fullName = trim((string) ($info['name'] ?? ''));

try {
    // Existing site user with this email? Sign them in.
    $stmt = $conn->prepare('SELECT user_id, username, fullname FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // New visitor — create their account.
    if (!$user) {
        $base = preg_replace('/[^A-Za-z0-9_]/', '', (string) strstr($email, '@', true));
        $base = substr($base, 0, 16);
        if (strlen($base) < 3) {
            $base = 'user' . $base;
        }

        // Usernames must be unique, 3-20 chars, letters/numbers/underscore.
        $username = $base;
        $check = $conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        for ($i = 0; $i < 20; $i++) {
            $check->bind_param('s', $username);
            $check->execute();
            $check->store_result();
            if ($check->num_rows === 0) {
                break;
            }
            $username = substr($base, 0, 16) . random_int(100, 9999);
        }
        $check->close();

        if ($fullName === '') {
            $fullName = $username;
        }

        // Google users never type a password; store an unguessable one.
        // (They can still use "Forgot password?" to set a real one.)
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $ins = $conn->prepare('INSERT INTO users (fullname, username, email, password) VALUES (?, ?, ?, ?)');
        $ins->bind_param('ssss', $fullName, $username, $email, $passwordHash);
        $ins->execute();
        $newId = (int) $conn->insert_id;
        $ins->close();

        $user = ['user_id' => $newId, 'username' => $username, 'fullname' => $fullName];
    }
} catch (Throwable $e) {
    error_log('Google sign-in DB error: ' . $e->getMessage());
    g_fail('Could not create your account. Please try again later.');
}

// Log them in — same session keys the normal login uses.
session_regenerate_id(true);
$_SESSION['user_id']   = (int) $user['user_id'];
$_SESSION['user_name'] = $user['username'];
$_SESSION['username']  = $user['username'];

header('Location: ' . BASE_URL . '/index.php');
exit;