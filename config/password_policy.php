<?php
/*
 |--------------------------------------------------------------------
 | PASSWORD POLICY — shared by signup, password reset, and admin
 | "change password" so every path that sets a password enforces the
 | same rule: at least 8 characters, one uppercase letter, and one
 | special (non-alphanumeric) character.
 |--------------------------------------------------------------------
 */

if (!function_exists('kt_password_meets_policy')) {
    function kt_password_meets_policy(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}
