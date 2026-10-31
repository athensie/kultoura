<?php
$conn->query(
    "CREATE TABLE IF NOT EXISTS announcements (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(200) NOT NULL,
        body         TEXT NOT NULL,
        type         ENUM('info','alert','event','update','maintenance') NOT NULL DEFAULT 'info',
        audience     VARCHAR(50) NOT NULL DEFAULT 'All Users',
        image        VARCHAR(255) NULL,
        status       ENUM('live','archived','draft','scheduled') NOT NULL DEFAULT 'draft',
        scheduled_at DATETIME NULL,
        published_at DATETIME NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$conn->query(
    "UPDATE announcements
     SET status = 'live', published_at = scheduled_at
     WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()"
);
function announcements_type_meta(): array
{
    return [
        'info'        => ['label' => 'Info',        'color' => '#4a90e2'],
        'alert'       => ['label' => 'Alert',       'color' => '#C9572A'],
        'event'       => ['label' => 'Event',       'color' => '#9fb88a'],
        'update'      => ['label' => 'Update',      'color' => '#6b8f47'],
        'maintenance' => ['label' => 'Maintenance', 'color' => '#8a8a5c'],
    ];
}
