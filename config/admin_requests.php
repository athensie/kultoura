<?php
/*
 |--------------------------------------------------------------------
 | ADMIN CHANGE REQUESTS — the Admin/Super Admin approval workflow
 |--------------------------------------------------------------------
 | Plain "Admin" accounts can no longer write directly to the 6
 | content tables (destination, products, restaurants, fiestas,
 | people, announcements, about_sections). Instead, their submitted
 | form data is stored here as a pending request; a "Super Admin"
 | reviews the actual proposed content in admin/adminrequests.php and
 | either approves it (which applies it for real, via the same
 | kt_apply_* functions the Super Admin's own direct edits use) or
 | rejects it (nothing is touched).
 |
 | "Archive" replaces "delete" everywhere — see kt_apply_* below,
 | none of which ever runs a DELETE statement.
 */

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

        // Give every content table a status an item can be archived into,
        // without disturbing whatever that column already means.
        kt_requests_ensure_enum_value($conn, 'destination', 'status', 'archived', "ENUM('active','pending','inactive','archived') NOT NULL DEFAULT 'active'");
        kt_requests_ensure_status_column($conn, 'products');
        kt_requests_ensure_status_column($conn, 'restaurants');
        kt_requests_ensure_status_column($conn, 'fiestas');
        kt_requests_ensure_status_column($conn, 'people');
        kt_requests_ensure_status_column($conn, 'about_sections');

        // Admin/User account "archive" (see adminusers.php) — admins already
        // has this column; users doesn't yet.
        try {
            $conn->query("ALTER TABLE users ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1060) { throw $e; }
        }
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
            return; // already has it (or the column doesn't exist — nothing to do)
        }
        $conn->query("ALTER TABLE `$table` MODIFY `$column` $newDefinitionSql");
    }
}

if (!function_exists('kt_is_super_admin')) {
    function kt_is_super_admin(): bool
    {
        return strtolower(trim($_SESSION['role'] ?? '')) === 'super admin';
    }
}

/*
 |--------------------------------------------------------------------
 | CREATE / LIST / RESOLVE a pending request
 |--------------------------------------------------------------------
 */
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
        $stmt = $conn->prepare(
            "UPDATE admin_change_requests SET status = ?, reviewed_by = ?, review_note = ?, reviewed_at = NOW() WHERE request_id = ?"
        );
        $stmt->bind_param('sisi', $status, $reviewerId, $note, $id);
        $stmt->execute();
        $stmt->close();
    }
}

/*
 |--------------------------------------------------------------------
 | APPLY — the only place any of these 6 tables actually gets written.
 |--------------------------------------------------------------------
 | Called directly for a Super Admin's own edits, and again (with the
 | same $data shape) when a Super Admin approves a pending request.
 | Returns a short label for flash messages / the request queue.
 | $action is one of 'create' | 'update' | 'archive'.
 */
if (!function_exists('kt_apply_entity_change')) {
    function kt_apply_entity_change(mysqli $conn, string $entityType, string $action, ?int $id, array $data): string
    {
        switch ($entityType) {
            case 'destination':      return kt_apply_destination($conn, $action, $id, $data);
            case 'product':          return kt_apply_product($conn, $action, $id, $data);
            case 'restaurant':       return kt_apply_restaurant($conn, $action, $id, $data);
            case 'fiesta':           return kt_apply_fiesta($conn, $action, $id, $data);
            case 'person':           return kt_apply_person($conn, $action, $id, $data);
            case 'announcement':     return kt_apply_announcement($conn, $action, $id, $data);
            case 'about_section':    return kt_apply_about_section($conn, $action, $id, $data);
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
        $image = $d['image'] ?? null;

        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO destination (destination_name, address, category, subcategory, status, description, image, google_maps)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssssss', $name, $d['address'], $d['category'], $subcategory, $d['status'], $d['description'], $image, $d['google_maps']);
            $stmt->execute();
            $stmt->close();
            return $name;
        }

        // update
        if ($image !== null) {
            $stmt = $conn->prepare(
                "UPDATE destination SET destination_name=?, address=?, category=?, subcategory=?, status=?, description=?, image=?, google_maps=? WHERE destination_id=?"
            );
            $stmt->bind_param('ssssssssi', $name, $d['address'], $d['category'], $subcategory, $d['status'], $d['description'], $image, $d['google_maps'], $id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE destination SET destination_name=?, address=?, category=?, subcategory=?, status=?, description=?, google_maps=? WHERE destination_id=?"
            );
            $stmt->bind_param('sssssssi', $name, $d['address'], $d['category'], $subcategory, $d['status'], $d['description'], $d['google_maps'], $id);
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

        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO fiestas (fiesta_name, type, celebration_date, location, latitude, longitude, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssssdds', $name, $d['type'], $d['celebration_date'], $d['location'], $lat, $lng, $d['description']);
        } else {
            $stmt = $conn->prepare(
                "UPDATE fiestas SET fiesta_name=?, type=?, celebration_date=?, location=?, latitude=?, longitude=?, description=? WHERE fiesta_id=?"
            );
            $stmt->bind_param('ssssddsi', $name, $d['type'], $d['celebration_date'], $d['location'], $lat, $lng, $d['description'], $id);
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

        if ($action === 'create') {
            $stmt = $conn->prepare(
                "INSERT INTO people (fullname, title, achievement, description, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssss', $name, $d['title'], $d['achievement'], $d['description']);
        } else {
            $stmt = $conn->prepare(
                "UPDATE people SET fullname=?, title=?, achievement=?, description=? WHERE person_id=?"
            );
            $stmt->bind_param('ssssi', $name, $d['title'], $d['achievement'], $d['description'], $id);
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
