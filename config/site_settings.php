<?php
if (!function_exists('site_settings_ensure_table')) {
    function site_settings_ensure_table(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                setting_value TINYINT(1) NOT NULL DEFAULT 0
            )"
        );
    }
}
if (!function_exists('site_settings_get_all')) {
    function site_settings_get_all(mysqli $conn, array $defaults): array
    {
        site_settings_ensure_table($conn);
        $settings = $defaults;
        if ($result = $conn->query("SELECT setting_key, setting_value FROM settings")) {
            foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
                $settings[$row['setting_key']] = (bool) $row['setting_value'];
            }
        }
        return $settings;
    }
}
if (!function_exists('site_settings_set')) {
    function site_settings_set(mysqli $conn, string $key, bool $value): void
    {
        site_settings_ensure_table($conn);
        $stmt = $conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $intValue = $value ? 1 : 0;
        $stmt->bind_param('si', $key, $intValue);
        $stmt->execute();
        $stmt->close();
    }
}
