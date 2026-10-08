<?php
/*
 |--------------------------------------------------------------------
 | MAINTENANCE MODE
 |--------------------------------------------------------------------
 | Toggled from Admin → Settings ("Maintenance Mode"). When on, every
 | public page calls kt_maintenance_gate($conn) right after dbmain.php
 | — anyone who isn't logged in as an admin sees the maintenance page
 | and nothing else. Admin pages and the auth pages (login, signup,
 | etc.) are never gated, so an admin can always still sign in and use
 | the admin panel.
 */

if (!function_exists('kt_maintenance_is_on')) {
    function kt_maintenance_is_on(mysqli $conn): bool
    {
        require_once __DIR__ . '/site_settings.php';
        $settings = site_settings_get_all($conn, ['maintenance_mode' => false]);
        return (bool) ($settings['maintenance_mode'] ?? false);
    }
}

if (!function_exists('kt_maintenance_gate')) {
    function kt_maintenance_gate(mysqli $conn): void
    {
        if (!kt_maintenance_is_on($conn)) {
            return;
        }

        $role = strtolower($_SESSION['role'] ?? '');
        if (in_array($role, ['admin', 'super admin'], true)) {
            return;
        }

        http_response_code(503);
        header('Retry-After: 1800');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance – KULTOURA</title>
    <link rel="stylesheet" href="/kultoura/assets/css/index.css">
    <style>
        .kt-maint-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
        }
        .kt-maint-card {
            max-width: 420px;
        }
        .kt-maint-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 20px;
            color: var(--gold, #C8A96E);
        }
        .kt-maint-card h1 {
            font-family: Georgia, serif;
            font-size: 26px;
            margin-bottom: 12px;
        }
        .kt-maint-card p {
            color: var(--muted, rgba(240,235,216,.6));
            font-size: 14.5px;
            line-height: 1.6;
            margin-bottom: 22px;
        }
        .kt-maint-card a {
            color: var(--gold, #C8A96E);
            font-size: 13px;
            text-decoration: none;
            font-weight: 600;
        }
        .kt-maint-card a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="kt-maint-wrap">
        <div class="kt-maint-card">
            <svg class="kt-maint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            <h1>We'll be right back</h1>
            <p>KulToura is currently undergoing scheduled maintenance. Please check back shortly — thanks for your patience!</p>
            <a href="/kultoura/auth/login.php">Admin sign in →</a>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
