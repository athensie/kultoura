<?php
/*
 |--------------------------------------------------------------------
 | CSRF PROTECTION
 |--------------------------------------------------------------------
 | Requires an active session (include config/session_boot.php first).
 | One token per session, reused across forms/tabs so it doesn't
 | invalidate a form left open in another tab.
 */

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
    }
}

// Called at the top of every POST handler, before touching $_POST['action'].
if (!function_exists('csrf_verify')) {
    function csrf_verify(): void
    {
        $submitted = $_POST['csrf_token'] ?? '';
        $expected  = $_SESSION['csrf_token'] ?? '';

        if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
            http_response_code(403);
            die('Security check failed — your session may have expired. Please refresh the page and try again.');
        }
    }
}
