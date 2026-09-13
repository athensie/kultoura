<?php
/*
 |--------------------------------------------------------------------
 | ANNOUNCEMENTS — shared table bootstrap + helpers
 |--------------------------------------------------------------------
 | Included by admin/adminannouncements.php, admin/announcements_actions.php,
 | and index.php (for the homepage News section) — same shared-bootstrap
 | pattern as config/sitecontent.php and config/analytics.php, so the
 | table exists no matter which of the three loads first.
 |
 | Include AFTER config/dbmain.php (needs $conn).
 */

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

// Lazy "cron": there's no background job runner in this app, so a
// scheduled announcement is promoted to live the next time ANY page
// that includes this file loads after its scheduled time has passed
// (the admin page, the actions handler, or the homepage itself).
$conn->query(
    "UPDATE announcements
     SET status = 'live', published_at = scheduled_at
     WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()"
);

// Shared label/color per type, reused by both the admin table's badges
// and the homepage News cards so they never drift out of sync.
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
