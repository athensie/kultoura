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
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (review_id),
        UNIQUE KEY unique_user_item_review (user_id, item_type, item_id),
        KEY item_reviews_listing (item_type, item_id, created_at),
        KEY item_reviews_period (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if ($conn->query($sql)) return true;
    error_log('Unable to create item_reviews table: ' . $conn->error);
    return false;
}

function kt_reviews_allowed_types(): array
{
    return ['product', 'restaurant', 'fiesta', 'nature', 'resort', 'industry', 'accommodation', 'church', 'service'];
}
