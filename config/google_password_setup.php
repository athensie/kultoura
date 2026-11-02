<?php
if (!function_exists('kt_google_password_setup_ensure_schema')) {
    function kt_google_password_setup_ensure_schema(mysqli $conn): void
    {
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'google_password_set'");
        $exists = $result && $result->num_rows > 0;
        if ($result) $result->free();
        if (!$exists) {
            $conn->query('ALTER TABLE users ADD COLUMN google_password_set TINYINT(1) NULL DEFAULT NULL AFTER promotional_email');
        }
    }
}
