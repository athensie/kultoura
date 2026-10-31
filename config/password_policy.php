<?php
if (!function_exists('kt_password_meets_policy')) {
    function kt_password_meets_policy(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}
