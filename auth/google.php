<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/login_throttle.php';
require_once __DIR__ . '/../config/password_policy.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/admin_password_approval.php';
require_once __DIR__ . '/../config/newsletter.php';
define('BASE_URL', '/kultoura');
const GOOGLE_ADMIN_MODE = 'signin';
function g_env(string $key): string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_SERVER[$key] ?? ($_ENV[$key] ?? '');
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
function g_find_admin(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare('SELECT admin_id, username, role, status, first_name, last_name FROM admins WHERE LOWER(email) = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
function g_find_user(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare('SELECT id, username, fullname, status FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
if (isset($_SESSION['user_id']) || isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
$clientId     = g_env('GOOGLE_CLIENT_ID');
$clientSecret = g_env('GOOGLE_CLIENT_SECRET');
if ($clientId === '' || $clientSecret === '') {
    g_fail('Google sign-in is not set up yet.');
}
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
$info  = g_http('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']);
$email = strtolower(trim((string) ($info['email'] ?? '')));
if ($email === '' || empty($info['email_verified'])) {
    g_fail('Your Google account does not have a verified email address.');
}
$fullName = trim((string) ($info['name'] ?? ''));
try {
    $admin = g_find_admin($conn, $email);
    if ($admin) {
        if (GOOGLE_ADMIN_MODE !== 'signin') {
            g_fail('This email belongs to an admin account. Please sign in with your admin username and password.');
        }
        if (($admin['status'] ?? 'Active') === 'Inactive') {
            g_fail('This account has been deactivated. Contact a Super Admin.');
        }
        session_regenerate_id(true);
        $_SESSION['user_id']       = $admin['admin_id'];
        $_SESSION['username']      = $admin['username'];
        $_SESSION['role']          = $admin['role'];
        $_SESSION['user_name']     = trim($admin['first_name'] . ' ' . $admin['last_name']);
        $_SESSION['session_token'] = kt_session_issue($conn, 'admins', 'admin_id', (int) $admin['admin_id']);
        header('Location: ' . BASE_URL . '/admin/admindashboard.php');
        exit;
    }
    $user = g_find_user($conn, $email);
    if ($user && ($user['status'] ?? 'Active') === 'Inactive') {
        g_fail('This account has been deactivated.');
    }
    if (!$user) {
        $base = preg_replace('/[^A-Za-z0-9_]/', '', (string) strstr($email, '@', true));
        $base = substr($base, 0, 16);
        if (strlen($base) < 4) {
            $base = 'user' . $base;
        }
        $username = $base;
        $checkU = $conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $checkA = $conn->prepare('SELECT 1 FROM admins WHERE username = ? LIMIT 1');
        for ($i = 0; $i < 20; $i++) {
            $checkU->bind_param('s', $username);
            $checkU->execute();
            $checkU->store_result();
            $takenU = $checkU->num_rows > 0;
            $checkA->bind_param('s', $username);
            $checkA->execute();
            $checkA->store_result();
            $takenA = $checkA->num_rows > 0;
            if (!$takenU && !$takenA) {
                break;
            }
            $username = substr($base, 0, 16) . random_int(100, 9999);
        }
        $checkU->close();
        $checkA->close();
        if ($fullName === '') {
            $fullName = $username;
        }
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        try {
            $ins = $conn->prepare('INSERT INTO users (fullname, username, email, password, promotional_email) VALUES (?, ?, ?, ?, 0)');
            $ins->bind_param('ssss', $fullName, $username, $email, $passwordHash);
            $ins->execute();
            $newId = (int) $conn->insert_id;
            $ins->close();
            $user = ['id' => $newId, 'username' => $username, 'fullname' => $fullName, 'status' => 'Active'];
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1062) {
                $user = g_find_user($conn, $email);
                if (!$user) {
                    throw $e;
                }
                if (($user['status'] ?? 'Active') === 'Inactive') {
                    g_fail('This account has been deactivated.');
                }
            } else {
                throw $e;
            }
        }
    }
} catch (Throwable $e) {
    error_log('Google sign-in DB error: ' . $e->getMessage());
    g_fail('Could not sign you in with Google. Please try again later.');
}
$displayName = trim((string) ($user['fullname'] ?? ''));
if ($displayName === '') {
    $displayName = (string) $user['username'];
}
session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['id'];
$_SESSION['username']      = $user['username'];
$_SESSION['role']          = 'User';
$_SESSION['user_name']     = $displayName;
$_SESSION['session_token'] = kt_session_issue($conn, 'users', 'id', (int) $user['id']);
header('Location: ' . BASE_URL . '/index.php');
exit;
