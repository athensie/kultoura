<?php
function kt_promotions_ensure_schema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS promoted_content (
            item_type VARCHAR(20) NOT NULL,
            item_id INT NOT NULL,
            promoted_by INT NULL,
            promoted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (item_type, item_id),
            INDEX (promoted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function kt_promotion_types(): array
{
    return [
        'nature' => ['table' => 'destination', 'id' => 'destination_id', 'name' => 'destination_name', 'label' => 'Nature', 'category' => "category = 'nature'", 'image_folder' => 'destinations', 'link' => 'pages/tourism/nature.php'],
        'resort' => ['table' => 'destination', 'id' => 'destination_id', 'name' => 'destination_name', 'label' => 'Resorts', 'category' => "category = 'resort'", 'image_folder' => 'destinations', 'link' => 'pages/tourism/resort.php'],
        'industry' => ['table' => 'destination', 'id' => 'destination_id', 'name' => 'destination_name', 'label' => 'Industry', 'category' => "category = 'industry'", 'image_folder' => 'destinations', 'link' => 'pages/tourism/industry.php'],
        'accommodation' => ['table' => 'destination', 'id' => 'destination_id', 'name' => 'destination_name', 'label' => 'Accommodation', 'category' => "category = 'accommodation'", 'image_folder' => 'destinations', 'link' => 'pages/tourism/accommodation.php'],
        'bank' => ['table' => 'destination', 'id' => 'destination_id', 'name' => 'destination_name', 'label' => 'Banks', 'category' => "category = 'bank'", 'image_folder' => 'destinations', 'link' => 'pages/tourism/banks.php'],
        'service' => ['table' => 'destination', 'id' => 'destination_id', 'name' => 'destination_name', 'label' => 'Other Services', 'category' => "category = 'service'", 'image_folder' => 'destinations', 'link' => 'pages/tourism/services.php'],
        'church' => ['table' => 'destination', 'id' => 'destination_id', 'name' => 'destination_name', 'label' => 'Churches', 'category' => "category = 'church'", 'image_folder' => 'destinations', 'link' => 'pages/tourism/churches.php'],
        'product' => ['table' => 'products', 'id' => 'product_id', 'name' => 'product_name', 'label' => 'Products', 'category' => '1=1', 'image_folder' => 'food', 'link' => 'pages/tourism/products.php'],
        'restaurant' => ['table' => 'restaurants', 'id' => 'restaurant_id', 'name' => 'restaurant_name', 'label' => 'Restaurants', 'category' => '1=1', 'image_folder' => 'food', 'link' => 'pages/tourism/restaurants.php'],
        'fiesta' => ['table' => 'fiestas', 'id' => 'fiesta_id', 'name' => 'fiesta_name', 'label' => 'Events & Fiestas', 'category' => '1=1', 'image_folder' => 'fiestas', 'link' => 'pages/tourism/fiestas.php'],
        'person' => ['table' => 'people', 'id' => 'person_id', 'name' => 'fullname', 'label' => 'People', 'category' => '1=1', 'image_folder' => 'people', 'link' => 'pages/tourism/people.php'],
    ];
}

function kt_promotion_candidates(mysqli $conn): array
{
    kt_promotions_ensure_schema($conn);
    $items = [];
    foreach (kt_promotion_types() as $type => $meta) {
        $sql = "SELECT t.{$meta['id']} AS item_id, t.{$meta['name']} AS item_name,
                       t.summary, t.description, t.image,
                       COALESCE(v.views, 0) AS views,
                       EXISTS(SELECT 1 FROM promoted_content p WHERE p.item_type = '$type' AND p.item_id = t.{$meta['id']}) AS promoted
                FROM {$meta['table']} t
                LEFT JOIN (SELECT item_id, COUNT(*) AS views FROM item_views WHERE item_type = '$type' GROUP BY item_id) v
                    ON v.item_id = t.{$meta['id']}
                WHERE t.status = 'active' AND {$meta['category']}
                ORDER BY COALESCE(v.views, 0) ASC, item_name ASC";
        $result = $conn->query($sql);
        if (!$result) continue;
        while ($row = $result->fetch_assoc()) {
            $items[$meta['label']][] = [
                'type' => $type,
                'id' => (int) $row['item_id'],
                'name' => (string) $row['item_name'],
                'summary' => (string) ($row['summary'] ?: $row['description'] ?: ''),
                'image' => (string) ($row['image'] ?? ''),
                'views' => (int) $row['views'],
                'promoted' => (bool) $row['promoted'],
            ];
        }
    }
    return $items;
}

function kt_promoted_home_items(mysqli $conn, int $limit = 6): array
{
    kt_promotions_ensure_schema($conn);
    $items = [];
    $types = kt_promotion_types();
    $result = $conn->query('SELECT item_type, item_id FROM promoted_content ORDER BY promoted_at DESC LIMIT ' . max(1, min(24, $limit)));
    if (!$result) return $items;
    while ($promotion = $result->fetch_assoc()) {
        $type = (string) $promotion['item_type'];
        $id = (int) $promotion['item_id'];
        if (!isset($types[$type])) continue;
        $meta = $types[$type];
        $sql = "SELECT {$meta['name']} AS name, summary, description, image FROM {$meta['table']} WHERE {$meta['id']} = ? AND status = 'active' AND {$meta['category']} LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) continue;
        $items[] = [
            'name' => (string) $row['name'],
            'badge' => $meta['label'],
            'image' => trim((string) ($row['image'] ?? '')) !== '' ? 'assets/uploads/' . $meta['image_folder'] . '/' . basename($row['image']) : '',
            'summary' => (string) ($row['summary'] ?: $row['description'] ?: ''),
            'link' => $meta['link'],
        ];
    }
    return $items;
}
