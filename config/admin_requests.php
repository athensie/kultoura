<?php
if (!function_exists('kt_requests_ensure_schema')) {
    function kt_requests_ensure_schema(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS admin_change_requests (
                request_id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(30) NOT NULL,
                entity_id INT NULL,
                entity_label VARCHAR(150) NULL,
                action VARCHAR(10) NOT NULL,
                payload LONGTEXT NOT NULL,
                requested_by INT NOT NULL,
                requested_by_name VARCHAR(100) NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'pending',
                reviewed_by INT NULL,
                review_note VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        kt_requests_ensure_enum_value($conn, 'destination', 'status', 'archived', "ENUM('active','pending','inactive','archived') NOT NULL DEFAULT 'active'");
        foreach ([
            'schedule_month' => 'DATE NULL',
            'schedule_text'  => 'TEXT NULL',
        ] as $column => $definition) {
            $res = $conn->query("SHOW COLUMNS FROM destination LIKE '" . $conn->real_escape_string($column) . "'");
            if (!$res || $res->num_rows === 0) {
                $conn->query("ALTER TABLE destination ADD COLUMN `$column` $definition");
            }
        }
        kt_requests_ensure_status_column($conn, 'products');
        kt_requests_ensure_status_column($conn, 'restaurants');
        kt_requests_ensure_status_column($conn, 'fiestas');
        kt_requests_ensure_status_column($conn, 'people');
        kt_requests_ensure_status_column($conn, 'about_sections');
        try {
            $conn->query("ALTER TABLE users ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1060) { throw $e; }
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS admin_notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                message VARCHAR(255) NOT NULL,
                link VARCHAR(255) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (admin_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
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
    }
}
if (!function_exists('kt_archive_note_save')) {
    function kt_archive_note_save(mysqli $conn, string $entityType, int $id, string $note): void
    {
        if (trim($note) === '') return;
        $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $adminName = $_SESSION['username'] ?? null;
        $stmt = $conn->prepare(
            "INSERT INTO archive_notes (entity_type, entity_id, note, archived_by, archived_by_name)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE note = VALUES(note), archived_by = VALUES(archived_by),
                archived_by_name = VALUES(archived_by_name), archived_at = CURRENT_TIMESTAMP"
        );
        $stmt->bind_param('sisis', $entityType, $id, $note, $adminId, $adminName);
        $stmt->execute();
        $stmt->close();
    }
}
if (!function_exists('kt_archive_note_get')) {
    function kt_archive_note_get(mysqli $conn, string $entityType, int $id): ?array
    {
        $stmt = $conn->prepare("SELECT note, archived_by_name, archived_at FROM archive_notes WHERE entity_type = ? AND entity_id = ?");
        $stmt->bind_param('si', $entityType, $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
if (!function_exists('kt_archive_note_clear')) {
    function kt_archive_note_clear(mysqli $conn, string $entityType, int $id): void
    {
        $stmt = $conn->prepare("DELETE FROM archive_notes WHERE entity_type = ? AND entity_id = ?");
        $stmt->bind_param('si', $entityType, $id);
        $stmt->execute();
        $stmt->close();
    }
}
if (!function_exists('kt_entity_meta')) {
    function kt_entity_meta(string $entityType): ?array
    {
        $map = [
            'destination'   => ['table' => 'destination',   'idCol' => 'destination_id', 'page' => 'admindestinations.php',   'label' => 'Destination'],
            'product'       => ['table' => 'products',       'idCol' => 'product_id',     'page' => 'adminfoodanddining.php',  'label' => 'Product'],
            'restaurant'    => ['table' => 'restaurants',     'idCol' => 'restaurant_id',  'page' => 'adminfoodanddining.php',  'label' => 'Restaurant'],
            'fiesta'        => ['table' => 'fiestas',         'idCol' => 'fiesta_id',      'page' => 'admineventandfiesta.php', 'label' => 'Event / Fiesta'],
            'person'        => ['table' => 'people',          'idCol' => 'person_id',      'page' => 'adminpeople.php',         'label' => 'Person of Malvar'],
            'announcement'  => ['table' => 'announcements',   'idCol' => 'id',             'page' => 'adminannouncements.php',  'label' => 'Announcement'],
            'about_section' => ['table' => 'about_sections',  'idCol' => 'section_id',     'page' => 'adminsitecontent.php',    'label' => 'About Section'],
            'partner'       => ['table' => 'partners',        'idCol' => 'id',             'page' => 'adminhomepage.php',       'label' => 'Partner'],
            'event_photo'   => ['table' => 'event_gallery',   'idCol' => 'id',             'page' => 'adminhomepage.php',       'label' => 'Event Gallery Photo'],
            'fb_video'      => ['table' => 'fb_videos',       'idCol' => 'id',             'page' => 'adminhomepage.php',       'label' => 'Facebook Video'],
            'page_widget'   => ['table' => 'page_widgets',    'idCol' => 'id',             'page' => 'adminpagewidgets.php',    'label' => 'Page Widget'],
        ];
        return $map[$entityType] ?? null;
    }
}
if (!function_exists('kt_archive_types')) {
    function kt_archive_types(): array
    {
        return [
            'destination'   => ['table' => 'destination',   'idCol' => 'destination_id', 'nameCol' => 'destination_name', 'imageCol' => 'image', 'activeStatus' => 'active', 'page' => 'admindestinations.php',   'label' => 'Destinations'],
            'product'       => ['table' => 'products',       'idCol' => 'product_id',     'nameCol' => 'product_name',     'imageCol' => 'image', 'activeStatus' => 'active', 'page' => 'adminfoodanddining.php',  'label' => 'Products'],
            'restaurant'    => ['table' => 'restaurants',     'idCol' => 'restaurant_id',  'nameCol' => 'restaurant_name',  'imageCol' => 'image', 'activeStatus' => 'active', 'page' => 'adminfoodanddining.php',  'label' => 'Restaurants'],
            'fiesta'        => ['table' => 'fiestas',         'idCol' => 'fiesta_id',      'nameCol' => 'fiesta_name',      'imageCol' => 'image', 'activeStatus' => 'active', 'page' => 'admineventandfiesta.php', 'label' => 'Events & Fiestas'],
            'person'        => ['table' => 'people',          'idCol' => 'person_id',      'nameCol' => 'fullname',         'imageCol' => 'image', 'activeStatus' => 'active', 'page' => 'adminpeople.php',         'label' => 'People of Malvar'],
            'announcement'  => ['table' => 'announcements',   'idCol' => 'id',             'nameCol' => 'title',            'imageCol' => 'image', 'activeStatus' => 'live',   'page' => 'adminannouncements.php',  'label' => 'Announcements'],
            'about_section' => ['table' => 'about_sections',  'idCol' => 'section_id',     'nameCol' => 'title',            'imageCol' => 'image', 'activeStatus' => 'active', 'page' => 'adminsitecontent.php',    'label' => 'About Sections'],
            'partner'       => ['table' => 'partners',        'idCol' => 'id',             'nameCol' => 'name',             'imageCol' => 'logo',  'activeStatus' => 'live',   'page' => 'adminhomepage.php',       'label' => 'Partners'],
            'event_photo'   => ['table' => 'event_gallery',   'idCol' => 'id',             'nameCol' => 'caption',          'imageCol' => 'image', 'activeStatus' => 'live',   'page' => 'adminhomepage.php',       'label' => 'Event Gallery'],
            'page_widget'   => ['table' => 'page_widgets',    'idCol' => 'id',             'nameCol' => 'page_slug',        'imageCol' => 'main_image', 'activeStatus' => 'live', 'page' => 'adminpagewidgets.php',   'label' => 'Page Widgets'],
        ];
    }
}
if (!function_exists('kt_archived_items_grouped')) {
    function kt_archived_items_grouped(mysqli $conn): array
    {
        $grouped = [];
        foreach (kt_archive_types() as $type => $meta) {
            $sql = "SELECT *, `{$meta['idCol']}` AS id, `{$meta['nameCol']}` AS name, `{$meta['imageCol']}` AS image
                     FROM `{$meta['table']}` WHERE status = 'archived' ORDER BY created_at DESC";
            $result = $conn->query($sql);
            if (!$result || $result->num_rows === 0) {
                continue;
            }
            $items = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($items as &$item) {
                $item['archive_note'] = kt_archive_note_get($conn, $type, (int) $item['id']);
            }
            unset($item);
            $grouped[$type] = [
                'label' => $meta['label'],
                'page'  => $meta['page'],
                'items' => $items,
            ];
        }
        return $grouped;
    }
}
if (!function_exists('kt_archive_restore')) {
    function kt_archive_restore(mysqli $conn, string $entityType, int $id): bool
    {
        $meta = kt_archive_types()[$entityType] ?? null;
        if (!$meta) return false;
        $stmt = $conn->prepare("UPDATE `{$meta['table']}` SET status = ? WHERE `{$meta['idCol']}` = ?");
        $stmt->bind_param('si', $meta['activeStatus'], $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            kt_archive_note_clear($conn, $entityType, $id);
        }
        return $ok;
    }
}
if (!function_exists('kt_archive_permanently_delete')) {
    function kt_archive_permanently_delete(mysqli $conn, string $entityType, int $id): bool
    {
        $meta = kt_archive_types()[$entityType] ?? null;
        if (!$meta) return false;
        $stmt = $conn->prepare("SELECT `{$meta['imageCol']}` AS image FROM `{$meta['table']}` WHERE `{$meta['idCol']}` = ? AND status = 'archived'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }
        $stmt = $conn->prepare("DELETE FROM `{$meta['table']}` WHERE `{$meta['idCol']}` = ? AND status = 'archived'");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        if ($ok) {
            kt_archive_note_clear($conn, $entityType, $id);
        }
        if ($ok && !empty($row['image'])) {
            $relative = preg_replace('#^/?kultoura/#', '', $row['image']);
            $path = __DIR__ . '/../' . $relative;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        return $ok;
    }
}
if (!function_exists('kt_fetch_entity_current')) {
    function kt_fetch_entity_current(mysqli $conn, string $entityType, ?int $entityId): ?array
    {
        $meta = kt_entity_meta($entityType);
        if (!$meta || !$entityId) {
            return null;
        }
        $stmt = $conn->prepare("SELECT * FROM `{$meta['table']}` WHERE `{$meta['idCol']}` = ?");
        $stmt->bind_param('i', $entityId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
if (!function_exists('kt_notify')) {
    function kt_notify(mysqli $conn, int $adminId, string $message, ?string $link = null): void
    {
        $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, message, link) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $adminId, $message, $link);
        $stmt->execute();
        $stmt->close();
    }
}
if (!function_exists('kt_notify_super_admins')) {
    function kt_notify_super_admins(mysqli $conn, string $message, ?string $link = null, ?int $excludeAdminId = null): void
    {
        $res = $conn->query("SELECT admin_id FROM admins WHERE LOWER(role) = 'super admin'");
        if (!$res) return;
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
            $id = (int) $row['admin_id'];
            if ($id === $excludeAdminId) continue;
            kt_notify($conn, $id, $message, $link);
        }
    }
}
if (!function_exists('kt_notifications_unread_count')) {
    function kt_notifications_unread_count(mysqli $conn, int $adminId): int
    {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM admin_notifications WHERE admin_id = ? AND is_read = 0");
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['c'] ?? 0);
    }
}
if (!function_exists('kt_notifications_list')) {
    function kt_notifications_list(mysqli $conn, int $adminId, int $limit = 20): array
    {
        $stmt = $conn->prepare("SELECT * FROM admin_notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param('ii', $adminId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
if (!function_exists('kt_notifications_mark_all_read')) {
    function kt_notifications_mark_all_read(mysqli $conn, int $adminId): void
    {
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0");
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
    }
}
if (!function_exists('kt_requests_ensure_status_column')) {
    function kt_requests_ensure_status_column(mysqli $conn, string $table): void
    {
        try {
            $conn->query("ALTER TABLE `$table` ADD COLUMN status ENUM('active','archived') NOT NULL DEFAULT 'active'");
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1060) { throw $e; }
        }
    }
}
if (!function_exists('kt_requests_ensure_enum_value')) {
    function kt_requests_ensure_enum_value(mysqli $conn, string $table, string $column, string $value, string $newDefinitionSql): void
    {
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row || stripos($row['Type'], "'$value'") !== false) {
            return;
        }
        $conn->query("ALTER TABLE `$table` MODIFY `$column` $newDefinitionSql");
    }
}
if (!function_exists('kt_base_url')) {
    function kt_base_url(): string
    {
        return defined('BASE_URL') ? BASE_URL : '/kultoura';
    }
}
if (!function_exists('kt_is_super_admin')) {
    function kt_is_super_admin(): bool
    {
        return strtolower(trim($_SESSION['role'] ?? '')) === 'super admin';
    }
}
if (!function_exists('kt_requests_create')) {
    function kt_requests_create(mysqli $conn, string $entityType, string $action, ?int $entityId, array $data, string $label, int $requestedBy, string $requestedByName): int
    {
        $payload = json_encode($data);
        $stmt = $conn->prepare(
            "INSERT INTO admin_change_requests (entity_type, entity_id, entity_label, action, payload, requested_by, requested_by_name)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sisssis', $entityType, $entityId, $label, $action, $payload, $requestedBy, $requestedByName);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        $meta = kt_entity_meta($entityType);
        $verb = $action === 'archive' ? 'archive' : $action;
        kt_notify_super_admins(
            $conn,
            "$requestedByName requested to $verb " . ($meta['label'] ?? $entityType) . " \"$label\"",
            kt_base_url() . '/admin/adminrequests.php'
        );
        return $id;
    }
}
if (!function_exists('kt_requests_pending_count')) {
    function kt_requests_pending_count(mysqli $conn): int
    {
        $res = $conn->query("SELECT COUNT(*) AS c FROM admin_change_requests WHERE status = 'pending'");
        return $res ? (int) ($res->fetch_assoc()['c'] ?? 0) : 0;
    }
}
if (!function_exists('kt_requests_list')) {
    function kt_requests_list(mysqli $conn, string $status = 'pending'): array
    {
        $stmt = $conn->prepare("SELECT * FROM admin_change_requests WHERE status = ? ORDER BY created_at ASC");
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as &$row) {
            $row['payload'] = json_decode($row['payload'], true) ?? [];
        }
        return $rows;
    }
}
if (!function_exists('kt_requests_find')) {
    function kt_requests_find(mysqli $conn, int $id): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM admin_change_requests WHERE request_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $row['payload'] = json_decode($row['payload'], true) ?? [];
        }
        return $row ?: null;
    }
}
if (!function_exists('kt_requests_resolve')) {
    function kt_requests_resolve(mysqli $conn, int $id, string $status, int $reviewerId, ?string $note = null): void
    {
        $request = kt_requests_find($conn, $id);
        $stmt = $conn->prepare(
            "UPDATE admin_change_requests SET status = ?, reviewed_by = ?, review_note = ?, reviewed_at = NOW() WHERE request_id = ?"
        );
        $stmt->bind_param('sisi', $status, $reviewerId, $note, $id);
        $stmt->execute();
        $stmt->close();
        if ($request) {
            $meta = kt_entity_meta($request['entity_type']);
            $verb = $request['action'] === 'archive' ? 'archive' : $request['action'];
            $message = "Your request to $verb " . ($meta['label'] ?? $request['entity_type']) . " \"{$request['entity_label']}\" was " . $status
                . ($status === 'rejected' && $note ? " ($note)" : '');
            kt_notify(
                $conn,
                (int) $request['requested_by'],
                $message,
                kt_base_url() . '/admin/' . ($meta['page'] ?? 'admindashboard.php')
            );
        }
    }
}
if (!function_exists('kt_apply_entity_change')) {
    function kt_apply_entity_change(mysqli $conn, string $entityType, string $action, ?int $id, array $data): string
    {
        if ($action === 'archive' && $id !== null && !empty($data['archive_note'])) {
            kt_archive_note_save($conn, $entityType, $id, (string) $data['archive_note']);
        }
        switch ($entityType) {
            case 'destination':      return kt_apply_destination($conn, $action, $id, $data);
            case 'product':          return kt_apply_product($conn, $action, $id, $data);
            case 'restaurant':       return kt_apply_restaurant($conn, $action, $id, $data);
            case 'fiesta':           return kt_apply_fiesta($conn, $action, $id, $data);
            case 'person':           return kt_apply_person($conn, $action, $id, $data);
            case 'announcement':     return kt_apply_announcement($conn, $action, $id, $data);
            case 'about_section':    return kt_apply_about_section($conn, $action, $id, $data);
            case 'partner':          return kt_apply_partner($conn, $action, $id, $data);
            case 'event_photo':      return kt_apply_event_photo($conn, $action, $id, $data);
            case 'fb_video':         return kt_apply_fb_video($conn, $action, $id, $data);
            case 'page_widget':      return kt_apply_page_widget($conn, $action, $id, $data);
            default:
                throw new InvalidArgumentException("Unknown entity type: $entityType");
        }
    }
}
if (!function_exists('kt_apply_destination')) {
    function kt_apply_destination(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $name = (string) ($d['destination_name'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE destination SET status = 'archived' WHERE destination_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $name;
        }
        $subcategory = ($d['category'] === 'nature' && !empty($d['subcategory'])) ? $d['subcategory'] : null;
        $scheduleMonth = ($d['category'] === 'church') ? ($d['schedule_month'] ?? null) : null;
        $scheduleText = ($d['category'] === 'church' && !empty($d['schedule_text'])) ? $d['schedule_text'] : null;
        $image = $d['image'] ?? null;
        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO destination (destination_name, address, category, subcategory, status, description, image, google_maps, schedule_month, schedule_text)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssssssss', $name, $d['address'], $d['category'], $subcategory, $d['status'], $d['description'], $image, $d['google_maps'], $scheduleMonth, $scheduleText);
            $stmt->execute();
            $stmt->close();
            return $name;
        }
        if ($image !== null) {
            $stmt = $conn->prepare(
                "UPDATE destination SET destination_name=?, address=?, category=?, subcategory=?, status=?, description=?, image=?, google_maps=?, schedule_month=?, schedule_text=? WHERE destination_id=?"
            );
            $stmt->bind_param('ssssssssssi', $name, $d['address'], $d['category'], $subcategory, $d['status'], $d['description'], $image, $d['google_maps'], $scheduleMonth, $scheduleText, $id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE destination SET destination_name=?, address=?, category=?, subcategory=?, status=?, description=?, google_maps=?, schedule_month=?, schedule_text=? WHERE destination_id=?"
            );
            $stmt->bind_param('sssssssssi', $name, $d['address'], $d['category'], $subcategory, $d['status'], $d['description'], $d['google_maps'], $scheduleMonth, $scheduleText, $id);
        }
        $stmt->execute();
        $stmt->close();
        return $name;
    }
}
if (!function_exists('kt_apply_product')) {
    function kt_apply_product(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $name = (string) ($d['product_name'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE products SET status = 'archived' WHERE product_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $name;
        }
        $price = $d['price'] !== null && $d['price'] !== '' ? (float) $d['price'] : null;
        $lat   = $d['latitude'] !== null && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $lng   = $d['longitude'] !== null && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $image = $d['image'] ?? '';
        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO products (product_name, category, description, price, location, latitude, longitude, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssdsdds', $name, $d['category'], $d['description'], $price, $d['location'], $lat, $lng, $image);
        } else {
            $stmt = $conn->prepare(
                "UPDATE products SET product_name=?, category=?, description=?, price=?, location=?, latitude=?, longitude=?, image=? WHERE product_id=?"
            );
            $stmt->bind_param('sssdsddsi', $name, $d['category'], $d['description'], $price, $d['location'], $lat, $lng, $image, $id);
        }
        $stmt->execute();
        $stmt->close();
        return $name;
    }
}
if (!function_exists('kt_apply_restaurant')) {
    function kt_apply_restaurant(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $name = (string) ($d['restaurant_name'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE restaurants SET status = 'archived' WHERE restaurant_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $name;
        }
        $lat   = $d['latitude'] !== null && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $lng   = $d['longitude'] !== null && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $image = $d['image'] ?? '';
        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO restaurants (restaurant_name, category, description, address, latitude, longitude, contact_number, opening_hours, google_map, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssddssss', $name, $d['category'], $d['description'], $d['address'], $lat, $lng, $d['contact_number'], $d['opening_hours'], $d['google_map'], $image);
        } else {
            $stmt = $conn->prepare(
                "UPDATE restaurants SET restaurant_name=?, category=?, description=?, address=?, latitude=?, longitude=?, contact_number=?, opening_hours=?, google_map=?, image=? WHERE restaurant_id=?"
            );
            $stmt->bind_param('ssssddssssi', $name, $d['category'], $d['description'], $d['address'], $lat, $lng, $d['contact_number'], $d['opening_hours'], $d['google_map'], $image, $id);
        }
        $stmt->execute();
        $stmt->close();
        return $name;
    }
}
if (!function_exists('kt_apply_fiesta')) {
    function kt_apply_fiesta(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $name = (string) ($d['fiesta_name'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE fiestas SET status = 'archived' WHERE fiesta_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $name;
        }
        $lat = $d['latitude'] !== null && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $lng = $d['longitude'] !== null && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $image = $d['image'] ?? null;
        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO fiestas (fiesta_name, type, celebration_date, location, latitude, longitude, description, image, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssssddss', $name, $d['type'], $d['celebration_date'], $d['location'], $lat, $lng, $d['description'], $image);
        } else {
            $stmt = $conn->prepare(
                "UPDATE fiestas SET fiesta_name=?, type=?, celebration_date=?, location=?, latitude=?, longitude=?, description=?, image=? WHERE fiesta_id=?"
            );
            $stmt->bind_param('ssssddssi', $name, $d['type'], $d['celebration_date'], $d['location'], $lat, $lng, $d['description'], $image, $id);
        }
        $stmt->execute();
        $stmt->close();
        return $name;
    }
}
if (!function_exists('kt_apply_person')) {
    function kt_apply_person(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $name = (string) ($d['fullname'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE people SET status = 'archived' WHERE person_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $name;
        }
        $image = $d['image'] ?? null;
        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO people (fullname, title, achievement, description, image, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('sssss', $name, $d['title'], $d['achievement'], $d['description'], $image);
        } else {
            $stmt = $conn->prepare(
                "UPDATE people SET fullname=?, title=?, achievement=?, description=?, image=? WHERE person_id=?"
            );
            $stmt->bind_param('sssssi', $name, $d['title'], $d['achievement'], $d['description'], $image, $id);
        }
        $stmt->execute();
        $stmt->close();
        return $name;
    }
}
if (!function_exists('kt_apply_announcement')) {
    function kt_apply_announcement(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $title = (string) ($d['title'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE announcements SET status = 'archived' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $title;
        }
        $image = $d['image'] ?? null;
        if ($action === 'create') {
            $publishedAt = $d['status'] === 'live' ? date('Y-m-d H:i:s') : null;
            $stmt = $conn->prepare(
                "INSERT INTO announcements (title, body, type, audience, image, status, scheduled_at, published_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssssss', $title, $d['body'], $d['type'], $d['audience'], $image, $d['status'], $d['scheduled_at'], $publishedAt);
        } else {
            $stmt = $conn->prepare(
                "UPDATE announcements
                 SET title=?, body=?, type=?, status=?, image=?,
                     published_at = CASE WHEN ? = 'live' AND published_at IS NULL THEN NOW() ELSE published_at END
                 WHERE id=?"
            );
            $stmt->bind_param('ssssssi', $title, $d['body'], $d['type'], $d['status'], $image, $d['status'], $id);
        }
        $stmt->execute();
        $stmt->close();
        return $title;
    }
}
if (!function_exists('kt_apply_partner')) {
    function kt_apply_partner(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $name = (string) ($d['name'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE partners SET status = 'archived' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $name;
        }
        $logo = $d['logo'] ?? null;
        $websiteUrl = $d['website_url'] ?? null;
        $description = $d['description'] ?? null;
        if ($action === 'create') {
            $stmt = $conn->prepare("INSERT INTO partners (name, logo, website_url, description, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $name, $logo, $websiteUrl, $description, $d['status']);
        } elseif ($logo !== null) {
            $stmt = $conn->prepare("UPDATE partners SET name=?, logo=?, website_url=?, description=?, status=? WHERE id=?");
            $stmt->bind_param('sssssi', $name, $logo, $websiteUrl, $description, $d['status'], $id);
        } else {
            $stmt = $conn->prepare("UPDATE partners SET name=?, website_url=?, description=?, status=? WHERE id=?");
            $stmt->bind_param('ssssi', $name, $websiteUrl, $description, $d['status'], $id);
        }
        $stmt->execute();
        $stmt->close();
        return $name;
    }
}
if (!function_exists('kt_apply_event_photo')) {
    function kt_apply_event_photo(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $caption = (string) ($d['caption'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE event_gallery SET status = 'archived' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $caption;
        }
        $image = $d['image'] ?? null;
        if ($action === 'create') {
            $images = array_slice(array_values($d['images'] ?? [$image]), 0, 6);
            if (!$images) $images = [$image];
            $imagesJson = json_encode($images, JSON_UNESCAPED_SLASHES);
            $image = $images[0];
            $stmt = $conn->prepare("INSERT INTO event_gallery (image, images_json, caption, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $image, $imagesJson, $caption, $d['status']);
        } elseif (is_array($d['images'] ?? null)) {
            $images = array_slice(array_values($d['images']), 0, 6);
            if (!$images) $images = [$image];
            $imagesJson = json_encode($images, JSON_UNESCAPED_SLASHES);
            $image = $images[0];
            $stmt = $conn->prepare("UPDATE event_gallery SET image=?, images_json=?, caption=?, status=? WHERE id=?");
            $stmt->bind_param('ssssi', $image, $imagesJson, $caption, $d['status'], $id);
        } elseif ($image !== null) {
            $stmt = $conn->prepare("UPDATE event_gallery SET image=?, images_json=NULL, caption=?, status=? WHERE id=?");
            $stmt->bind_param('sssi', $image, $caption, $d['status'], $id);
        } else {
            $stmt = $conn->prepare("UPDATE event_gallery SET caption=?, status=? WHERE id=?");
            $stmt->bind_param('ssi', $caption, $d['status'], $id);
        }
        $stmt->execute();
        $stmt->close();
        return $caption;
    }
}
if (!function_exists('kt_apply_fb_video')) {
    function kt_apply_fb_video(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $title = (string) ($d['title'] ?? '') ?: ($d['video_url'] ?? 'Video');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE fb_videos SET status = 'archived' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $title;
        }
        $thumbnail = $d['thumbnail'] ?? null;
        if ($action === 'create') {
            $stmt = $conn->prepare("INSERT INTO fb_videos (video_url, title, thumbnail, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $d['video_url'], $d['title'], $thumbnail, $d['status']);
        } else {
            $stmt = $conn->prepare("UPDATE fb_videos SET video_url=?, title=?, thumbnail=?, status=? WHERE id=?");
            $stmt->bind_param('ssssi', $d['video_url'], $d['title'], $thumbnail, $d['status'], $id);
        }
        $stmt->execute();
        $stmt->close();
        return $title;
    }
}
if (!function_exists('kt_apply_page_widget')) {
    function kt_apply_page_widget(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $slug = (string) ($d['page_slug'] ?? '');
        $label = $slug;
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE page_widgets SET status = 'archived' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $label;
        }
        $textFields = ['bg_color', 'headline', 'subtext', 'card1_text', 'card2_text',
            'photo_1_caption', 'photo_1_description', 'photo_2_caption', 'photo_2_description',
            'photo_3_caption', 'photo_3_description', 'photo_4_caption', 'photo_4_description',
            'photo_5_caption', 'photo_5_description'];
        $imageFields = ['main_image', 'gallery_image_1', 'gallery_main_image', 'gallery_image_2', 'gallery_image_3',
            'photo_1', 'photo_2', 'photo_3', 'photo_4', 'photo_5'];
        if ($action === 'create') {
            $cols = array_merge(['page_slug'], $textFields, $imageFields, ['status']);
            $values = array_merge(
                [$slug],
                array_map(fn($f) => $d[$f] ?? null, $textFields),
                array_map(fn($f) => $d[$f] ?? null, $imageFields),
                [$d['status']]
            );
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $stmt = $conn->prepare("INSERT INTO page_widgets (" . implode(',', $cols) . ") VALUES ($placeholders)");
            $stmt->bind_param(str_repeat('s', count($values)), ...$values);
            $stmt->execute();
            $stmt->close();
            return $label;
        }
        $setParts = [];
        $values = [];
        foreach ($textFields as $f) {
            $setParts[] = "$f = ?";
            $values[] = $d[$f] ?? null;
        }
        foreach ($imageFields as $f) {
            if (!empty($d[$f])) {
                $setParts[] = "$f = ?";
                $values[] = $d[$f];
            }
        }
        $setParts[] = "status = ?";
        $values[] = $d['status'];
        $values[] = $id;
        $stmt = $conn->prepare("UPDATE page_widgets SET " . implode(', ', $setParts) . " WHERE id = ?");
        $stmt->bind_param(str_repeat('s', count($values) - 1) . 'i', ...$values);
        $stmt->execute();
        $stmt->close();
        return $label;
    }
}
if (!function_exists('kt_apply_about_section')) {
    function kt_apply_about_section(mysqli $conn, string $action, ?int $id, array $d): string
    {
        $title = (string) ($d['title'] ?? '');
        if ($action === 'archive') {
            $stmt = $conn->prepare("UPDATE about_sections SET status = 'archived' WHERE section_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            return $title;
        }
        $image = $d['image'] ?? null;
        if ($action === 'create') {
            $maxRow = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS m FROM about_sections")->fetch_assoc();
            $nextOrder = (int) $maxRow['m'] + 1;
            $stmt = $conn->prepare("INSERT INTO about_sections (title, icon_key, image, body, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssi', $title, $d['icon_key'], $image, $d['body'], $nextOrder);
        } elseif (!empty($d['remove_image'])) {
            $stmt = $conn->prepare("UPDATE about_sections SET title=?, icon_key=?, body=?, image=NULL WHERE section_id=?");
            $stmt->bind_param('sssi', $title, $d['icon_key'], $d['body'], $id);
        } elseif ($image !== null) {
            $stmt = $conn->prepare("UPDATE about_sections SET title=?, icon_key=?, body=?, image=? WHERE section_id=?");
            $stmt->bind_param('ssssi', $title, $d['icon_key'], $d['body'], $image, $id);
        } else {
            $stmt = $conn->prepare("UPDATE about_sections SET title=?, icon_key=?, body=? WHERE section_id=?");
            $stmt->bind_param('sssi', $title, $d['icon_key'], $d['body'], $id);
        }
        $stmt->execute();
        $stmt->close();
        return $title;
    }
}
