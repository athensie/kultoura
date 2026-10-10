<?php
/*
 |--------------------------------------------------------------------
 | HOMEPAGE EXTRAS — Partners, Event Gallery, Facebook Videos
 |--------------------------------------------------------------------
 | Three small, admin-managed content types that only ever show up on
 | index.php: partner/brand logos, a photo grid of events hosted in
 | Malvar, and embedded Facebook videos ("recent happenings"). Kept in
 | their own file (mirrors config/announcements.php) rather than piled
 | into dbmain.php, and included wherever needed — the admin page, its
 | own actions, and index.php — same as announcements.php is.
 |
 | Each table carries its own 'live'/'archived' status and is wired
 | into the Admin/Super Admin approval queue (config/admin_requests.php)
 | the same way announcements already is: a plain Admin's add/edit/
 | archive is held for Super Admin approval, a Super Admin's applies
 | immediately.
 */

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
                status ENUM('live','archived') NOT NULL DEFAULT 'live',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

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
                status ENUM('live','archived') NOT NULL DEFAULT 'live',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

// Shared by partners (logo) and event_gallery (photo) — same validation/
// storage pattern as adminfoodanddining.php's kt_handle_image_upload(),
// just parameterized on the upload subfolder and filename prefix instead
// of copy-pasted per content type.
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

// Turns a pasted Facebook video/reel/watch URL into the embeddable
// plugin URL. Returns null for anything that isn't actually a
// facebook.com link, so a bad paste fails loudly instead of silently
// embedding nothing.
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
