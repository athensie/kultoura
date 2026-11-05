<?php
function kt_reviews_ensure_schema(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS item_reviews (
        review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        item_type VARCHAR(24) NOT NULL,
        item_id INT NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        review_text VARCHAR(1500) NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'visible',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (review_id),
        UNIQUE KEY unique_user_item_review (user_id, item_type, item_id),
        KEY item_reviews_listing (item_type, item_id, created_at),
        KEY item_reviews_period (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('Unable to create item_reviews table: ' . $conn->error);
        return false;
    }
    $statusColumn = $conn->query("SHOW COLUMNS FROM item_reviews LIKE 'status'");
    if ($statusColumn && $statusColumn->num_rows === 0 && !$conn->query("ALTER TABLE item_reviews ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'visible' AFTER review_text")) {
        error_log('Unable to add item review moderation status: ' . $conn->error);
        return false;
    }
    $reportSql = "CREATE TABLE IF NOT EXISTS item_review_reports (
        report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id BIGINT UNSIGNED NOT NULL,
        reporter_id INT NOT NULL,
        reason_code VARCHAR(32) NOT NULL,
        details VARCHAR(1000) NOT NULL DEFAULT '',
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        resolved_by INT NULL DEFAULT NULL,
        resolution_action VARCHAR(16) NULL DEFAULT NULL,
        PRIMARY KEY (report_id),
        UNIQUE KEY unique_reporter_review (review_id, reporter_id),
        KEY review_reports_status (status, created_at),
        KEY review_reports_review (review_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if ($conn->query($reportSql)) return true;
    error_log('Unable to create item_review_reports table: ' . $conn->error);
    return false;
}

function kt_reviews_allowed_types(): array
{
    return ['product', 'restaurant', 'fiesta', 'nature', 'resort', 'industry', 'accommodation', 'church', 'service'];
}
