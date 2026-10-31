<?php
if (!function_exists('kt_homepage_content_ensure_schema')) {
    function kt_homepage_content_ensure_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $conn->query(
            "CREATE TABLE IF NOT EXISTS partners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                logo VARCHAR(255) NOT NULL,
                website_url VARCHAR(255) NULL,
                description VARCHAR(300) NULL,
                status ENUM('live','archived') NOT NULL DEFAULT 'live',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        try {
            $conn->query("ALTER TABLE partners ADD COLUMN description VARCHAR(300) NULL AFTER website_url");
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1060) { throw $e; }
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS event_gallery (
                id INT AUTO_INCREMENT PRIMARY KEY,
                image VARCHAR(255) NOT NULL,
                caption VARCHAR(100) NOT NULL,
                status ENUM('live','archived') NOT NULL DEFAULT 'live',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $conn->query(
            "CREATE TABLE IF NOT EXISTS fb_videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                video_url VARCHAR(500) NOT NULL,
                title VARCHAR(150) NULL,
                thumbnail VARCHAR(255) NULL,
                status ENUM('live','archived') NOT NULL DEFAULT 'live',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        try {
            $conn->query("ALTER TABLE fb_videos ADD COLUMN thumbnail VARCHAR(255) NULL AFTER title");
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1060) { throw $e; }
        }
    }
}
if (!function_exists('kt_homepage_handle_image_upload')) {
    function kt_homepage_handle_image_upload(string $fieldName, string $subfolder, string $prefix): ?string
    {
        if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $originalName = $_FILES[$fieldName]['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return null;
        }
        if (@getimagesize($_FILES[$fieldName]['tmp_name']) === false) {
            return null;
        }
        $uploadDir = __DIR__ . '/../assets/uploads/' . $subfolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $safeName = preg_replace('/[^a-z0-9_-]/i', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $filename = uniqid($prefix . '_', true) . '_' . $safeName . '.' . $ext;
        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadDir . $filename)) {
            return (defined('BASE_URL') ? BASE_URL : '/kultoura') . '/assets/uploads/' . $subfolder . '/' . $filename;
        }
        return null;
    }
}
if (!function_exists('kt_fb_video_embed_url')) {
    function kt_fb_video_embed_url(string $videoUrl): ?string
    {
        $host = parse_url($videoUrl, PHP_URL_HOST);
        if (!$host || stripos($host, 'facebook.com') === false) {
            return null;
        }
        return 'https://www.facebook.com/plugins/video.php?href=' . urlencode($videoUrl) . '&show_text=false';
    }
}
