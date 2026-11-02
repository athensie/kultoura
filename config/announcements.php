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
        expires_at   DATETIME NULL,
        published_at DATETIME NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$expiryColumn = $conn->query("SHOW COLUMNS FROM announcements LIKE 'expires_at'");
if (!$expiryColumn || $expiryColumn->num_rows === 0) {
    $conn->query('ALTER TABLE announcements ADD COLUMN expires_at DATETIME NULL AFTER scheduled_at');
}
if ($expiryColumn instanceof mysqli_result) $expiryColumn->free();
$conn->query(
    "UPDATE announcements
     SET status = 'live', published_at = scheduled_at
     WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= UTC_TIMESTAMP()"
);
$conn->query(
    "CREATE TABLE IF NOT EXISTS archive_notes (
        entity_type VARCHAR(30) NOT NULL,
        entity_id INT NOT NULL,
        note TEXT NULL,
        archived_by INT NULL,
        archived_by_name VARCHAR(100) NULL,
        archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$conn->begin_transaction();
try {
    $conn->query(
        "INSERT INTO archive_notes (entity_type, entity_id, note, archived_by_name)
         SELECT 'announcement', id, 'Effective period ended automatically.', 'System'
         FROM announcements
         WHERE status IN ('live', 'scheduled') AND expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP()
         ON DUPLICATE KEY UPDATE note = VALUES(note), archived_by = NULL,
            archived_by_name = 'System', archived_at = CURRENT_TIMESTAMP"
    );
    $conn->query(
        "UPDATE announcements SET status = 'archived'
         WHERE status IN ('live', 'scheduled') AND expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP()"
    );
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('announcement expiry: ' . $e->getMessage());
}
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
