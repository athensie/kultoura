<?php
function kt_activity_categories(): array
{
    return [
        'nature' => ['label' => 'Nature', 'parent' => 'tourism_attractions', 'type' => 'nature'],
        'industry' => ['label' => 'Industry Zone', 'parent' => 'tourism_attractions', 'type' => 'industry'],
        'resort' => ['label' => 'Resorts', 'parent' => 'tourism_attractions', 'type' => 'resort'],
        'church' => ['label' => 'Churches', 'parent' => 'tourism_attractions', 'type' => 'church'],
        'product' => ['label' => 'Products of Malvar', 'parent' => 'culture_heritage', 'type' => 'product'],
        'fiesta' => ['label' => 'Fiestas', 'parent' => 'culture_heritage', 'type' => 'fiesta'],
        'person' => ['label' => 'People of Malvar', 'parent' => 'culture_heritage', 'type' => 'person'],
        'restaurant' => ['label' => 'Restaurants', 'parent' => 'local_services', 'type' => 'restaurant'],
        'accommodation' => ['label' => 'Accommodation', 'parent' => 'local_services', 'type' => 'accommodation'],
        'bank' => ['label' => 'Banks', 'parent' => 'local_services', 'type' => 'bank'],
        'service' => ['label' => 'Other Services', 'parent' => 'local_services', 'type' => 'service'],
    ];
}

function kt_activity_parents(): array
{
    return [
        'tourism_attractions' => ['label' => 'Tourism and Attractions', 'categories' => ['nature', 'industry', 'resort', 'church']],
        'culture_heritage' => ['label' => 'Culture and Heritage', 'categories' => ['product', 'fiesta', 'person']],
        'local_services' => ['label' => 'Local Services', 'categories' => ['restaurant', 'accommodation', 'bank', 'service']],
    ];
}

function kt_activity_ensure_schema(mysqli $conn): bool
{
    static $ensured = null;
    if ($ensured !== null) return $ensured;
    $ensured = (bool) $conn->query("CREATE TABLE IF NOT EXISTS user_activity_log (
        activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT NULL,
        session_hash CHAR(64) NOT NULL,
        activity_type VARCHAR(20) NOT NULL,
        category VARCHAR(32) NULL,
        parent_category VARCHAR(32) NULL,
        item_type VARCHAR(24) NULL,
        item_id INT NULL,
        content_name VARCHAR(180) NULL,
        element_name VARCHAR(120) NULL,
        page_section VARCHAR(120) NOT NULL DEFAULT '',
        metadata TEXT NULL,
        activity_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (activity_id),
        KEY activity_period (activity_date),
        KEY activity_category_period (category, activity_date),
        KEY activity_parent_period (parent_category, activity_date),
        KEY activity_type_period (activity_type, activity_date),
        KEY activity_user_period (user_id, activity_date),
        KEY activity_item (item_type, item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    return $ensured;
}

function kt_activity_category_for_page(string $page): ?string
{
    $map = [
        'nature' => 'nature', 'industry' => 'industry', 'resort' => 'resort', 'churches' => 'church',
        'products' => 'product', 'fiestas' => 'fiesta', 'people' => 'person',
        'restaurants' => 'restaurant', 'accommodation' => 'accommodation', 'banks' => 'bank', 'services' => 'service',
    ];
    return $map[strtolower($page)] ?? null;
}

function kt_activity_item_name(mysqli $conn, string $itemType, int $itemId): ?string
{
    $lookup = [
        'nature' => ['destination', 'destination_id', 'destination_name'], 'industry' => ['destination', 'destination_id', 'destination_name'],
        'resort' => ['destination', 'destination_id', 'destination_name'], 'church' => ['destination', 'destination_id', 'destination_name'],
        'accommodation' => ['destination', 'destination_id', 'destination_name'], 'bank' => ['destination', 'destination_id', 'destination_name'], 'service' => ['destination', 'destination_id', 'destination_name'],
        'product' => ['products', 'product_id', 'product_name'], 'restaurant' => ['restaurants', 'restaurant_id', 'restaurant_name'],
        'fiesta' => ['fiestas', 'fiesta_id', 'fiesta_name'], 'person' => ['people', 'person_id', 'fullname'],
    ];
    if ($itemId <= 0 || !isset($lookup[$itemType])) return null;
    [$table, $idColumn, $nameColumn] = $lookup[$itemType];
    $stmt = $conn->prepare("SELECT {$nameColumn} AS item_name FROM {$table} WHERE {$idColumn} = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $name = $stmt->get_result()->fetch_assoc()['item_name'] ?? null;
    $stmt->close();
    return $name !== null ? mb_substr((string) $name, 0, 180) : null;
}

function kt_activity_device_type(): string
{
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent === '') return 'Unknown';
    if (preg_match('/ipad|tablet|kindle|silk|playbook|sm-t\d|android(?!.*mobile)/i', $userAgent)) return 'Tablet';
    if (preg_match('/mobile|iphone|ipod|windows phone|iemobile|blackberry|opera mini|android/i', $userAgent)) return 'Mobile';
    return preg_match('/mozilla|chrome|safari|firefox|edg|opr|trident/i', $userAgent) ? 'Desktop' : 'Unknown';
}

function kt_activity_record(mysqli $conn, string $type, ?string $category, string $page, string $element = '', ?string $itemType = null, ?int $itemId = null, array $metadata = []): bool
{
    static $allowed = ['view', 'click', 'search', 'generate', 'download', 'rating', 'review', 'comment', 'recommendation', 'map_interaction', 'language', 'other'];
    if (!in_array($type, $allowed, true) || !kt_activity_ensure_schema($conn)) return false;
    $categories = kt_activity_categories();
    if (!isset($categories[$category ?? ''])) $category = kt_activity_category_for_page($page);
    $parent = $category !== null ? ($categories[$category]['parent'] ?? null) : null;
    $itemName = ($itemType && $itemId) ? kt_activity_item_name($conn, $itemType, (int) $itemId) : null;
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $sessionHash = hash('sha256', session_id());
    $pageSection = substr(basename(parse_url('/' . trim($page, '/'), PHP_URL_PATH) ?: $page), 0, 120);
    $element = mb_substr(trim(strip_tags($element)), 0, 120);
    $metadata['device'] = kt_activity_device_type();
    $metadata['device_source'] = 'user_agent';
    $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
    $stmt = $conn->prepare('INSERT INTO user_activity_log (user_id, session_hash, activity_type, category, parent_category, item_type, item_id, content_name, element_name, page_section, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) return false;
    $stmt->bind_param('isssssissss', $userId, $sessionHash, $type, $category, $parent, $itemType, $itemId, $itemName, $element, $pageSection, $metadataJson);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function kt_activity_count(mysqli $conn, ?string $type = null, ?string $category = null, ?string $parent = null, ?string $since = null, ?string $until = null): int
{
    if (!kt_activity_ensure_schema($conn)) return 0;
    $where = [];
    $types = '';
    $values = [];
    if ($type !== null) { $where[] = 'activity_type = ?'; $types .= 's'; $values[] = $type; }
    if ($category !== null) { $where[] = 'category = ?'; $types .= 's'; $values[] = $category; }
    if ($parent !== null) { $where[] = 'parent_category = ?'; $types .= 's'; $values[] = $parent; }
    if ($since !== null) { $where[] = 'activity_date >= ?'; $types .= 's'; $values[] = $since; }
    if ($until !== null) { $where[] = 'activity_date < ?'; $types .= 's'; $values[] = $until; }
    $sql = 'SELECT COUNT(*) total FROM user_activity_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($values) {
        $args = [$types];
        foreach ($values as $index => &$value) $args[] = &$value;
        call_user_func_array([$stmt, 'bind_param'], $args);
    }
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $count;
}
