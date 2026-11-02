<?php
if (!function_exists('kt_search_image_subfolder')) {
    function kt_search_image_subfolder(string $category): string
    {
        $map = [
            'product'       => 'food',
            'restaurant'    => 'food',
            'nature'        => 'destinations',
            'resort'        => 'destinations',
            'industry'      => 'destinations',
            'accommodation' => 'destinations',
            'bank'          => 'destinations',
            'service'       => 'destinations',
            'church'        => 'destinations',
            'fiesta'        => 'fiestas',
            'person'        => 'people',
            'announcement'  => 'announcements',
        ];
        return $map[$category] ?? $category;
    }
}
if (!function_exists('kt_search_image_src')) {
    function kt_search_image_src(string $category, ?string $rawImage): string
    {
        if (empty($rawImage)) return '';
        if (str_starts_with($rawImage, '/') || str_starts_with($rawImage, 'http')) {
            return $rawImage;
        }
        return '/kultoura/assets/uploads/' . kt_search_image_subfolder($category) . '/' . basename($rawImage);
    }
}
if (!function_exists('kt_search_link')) {
    function kt_search_link(string $type, string $category, int $id): string
    {
        $destinationLinks = [
            'nature' => 'nature.php', 'resort' => 'resort.php', 'industry' => 'industry.php',
            'accommodation' => 'accommodation.php', 'bank' => 'banks.php',
            'service' => 'services.php', 'church' => 'churches.php',
        ];
        $pages = [
            'product'      => 'products.php',
            'restaurant'   => 'restaurants.php',
            'fiesta'       => 'fiestas.php',
            'person'       => 'people.php',
        ];
        if ($type === 'destination') {
            return '/kultoura/pages/tourism/' . ($destinationLinks[$category] ?? 'nature.php');
        }
        if ($type === 'announcement') {
            return '/kultoura/index.php#announcements';
        }
        return '/kultoura/pages/tourism/' . ($pages[$type] ?? '');
    }
}
if (!function_exists('kt_search_all')) {
    function kt_search_all(mysqli $conn, string $query, int $limitPerType = 8): array
    {
        $query = trim($query);
        if ($query === '') return [];
        $like = '%' . $conn->real_escape_string($query) . '%';
        $destinationCategoryLabels = [
            'nature' => 'Nature', 'industry' => 'Industry Zone', 'resort' => 'Resort',
            'accommodation' => 'Accommodation', 'bank' => 'Banks', 'service' => 'Other Services',
            'church' => 'Churches',
        ];
        $results = [];
        $stmt = $conn->prepare(
            "SELECT destination_id, destination_name, category, subcategory, description, summary, image
             FROM destination WHERE status = 'active' AND destination_name LIKE ?
             ORDER BY destination_name ASC LIMIT ?"
        );
        $stmt->bind_param('si', $like, $limitPerType);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $results[] = [
                'type'      => 'destination',
                'id'        => (int) $row['destination_id'],
                'name'      => $row['destination_name'],
                'badgeText' => $destinationCategoryLabels[$row['category']] ?? ucfirst($row['category']),
                'category'  => $row['category'],
                'image'     => kt_search_image_src($row['category'], $row['image']),
                'link'      => kt_search_link('destination', $row['category'], (int) $row['destination_id']),
                'snippet'   => mb_strimwidth((string) ($row['summary'] ?: $row['description']), 0, 110, '…'),
            ];
        }
        $stmt->close();
        $stmt = $conn->prepare(
            "SELECT product_id, product_name, category, description, summary, image
             FROM products WHERE status = 'active' AND product_name LIKE ?
             ORDER BY product_name ASC LIMIT ?"
        );
        $stmt->bind_param('si', $like, $limitPerType);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $results[] = [
                'type'      => 'product',
                'id'        => (int) $row['product_id'],
                'name'      => $row['product_name'],
                'badgeText' => $row['category'] ?: 'Product',
                'category'  => 'product',
                'image'     => kt_search_image_src('product', $row['image']),
                'link'      => kt_search_link('product', '', (int) $row['product_id']),
                'snippet'   => mb_strimwidth((string) ($row['summary'] ?: $row['description']), 0, 110, '…'),
            ];
        }
        $stmt->close();
        $stmt = $conn->prepare(
            "SELECT restaurant_id, restaurant_name, category, description, summary, image
             FROM restaurants WHERE status = 'active' AND restaurant_name LIKE ?
             ORDER BY restaurant_name ASC LIMIT ?"
        );
        $stmt->bind_param('si', $like, $limitPerType);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $results[] = [
                'type'      => 'restaurant',
                'id'        => (int) $row['restaurant_id'],
                'name'      => $row['restaurant_name'],
                'badgeText' => $row['category'] ?: 'Restaurant',
                'category'  => 'restaurant',
                'image'     => kt_search_image_src('restaurant', $row['image']),
                'link'      => kt_search_link('restaurant', '', (int) $row['restaurant_id']),
                'snippet'   => mb_strimwidth((string) ($row['summary'] ?: $row['description']), 0, 110, '…'),
            ];
        }
        $stmt->close();
        $stmt = $conn->prepare(
            "SELECT fiesta_id, fiesta_name, type, description, summary, image
             FROM fiestas WHERE status = 'active' AND fiesta_name LIKE ?
             ORDER BY fiesta_name ASC LIMIT ?"
        );
        $stmt->bind_param('si', $like, $limitPerType);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $results[] = [
                'type'      => 'fiesta',
                'id'        => (int) $row['fiesta_id'],
                'name'      => $row['fiesta_name'],
                'badgeText' => $row['type'] ?: 'Fiesta',
                'category'  => 'fiesta',
                'image'     => kt_search_image_src('fiesta', $row['image']),
                'link'      => kt_search_link('fiesta', '', (int) $row['fiesta_id']),
                'snippet'   => mb_strimwidth((string) ($row['summary'] ?: $row['description']), 0, 110, '…'),
            ];
        }
        $stmt->close();
        $stmt = $conn->prepare(
            "SELECT person_id, fullname, title, achievement, description, summary, image
             FROM people WHERE status = 'active' AND fullname LIKE ?
             ORDER BY fullname ASC LIMIT ?"
        );
        $stmt->bind_param('si', $like, $limitPerType);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $results[] = [
                'type'      => 'person',
                'id'        => (int) $row['person_id'],
                'name'      => $row['fullname'],
                'badgeText' => $row['title'] ?: 'Person',
                'category'  => 'person',
                'image'     => kt_search_image_src('person', $row['image']),
                'link'      => kt_search_link('person', '', (int) $row['person_id']),
                'snippet'   => mb_strimwidth((string) ($row['summary'] ?: $row['achievement'] ?: $row['description']), 0, 110, '…'),
            ];
        }
        $stmt->close();
        $stmt = $conn->prepare(
            "SELECT id, title, body, image
             FROM announcements WHERE status = 'live'
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
               AND title LIKE ?
             ORDER BY title ASC LIMIT ?"
        );
        $stmt->bind_param('si', $like, $limitPerType);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $results[] = [
                'type'      => 'announcement',
                'id'        => (int) $row['id'],
                'name'      => $row['title'],
                'badgeText' => 'Announcement',
                'category'  => 'announcement',
                'image'     => kt_search_image_src('announcement', $row['image']),
                'link'      => kt_search_link('announcement', '', (int) $row['id']),
                'snippet'   => mb_substr((string) $row['body'], 0, 110),
            ];
        }
        $stmt->close();
        usort($results, function ($a, $b) {
            $nameOrder = strnatcasecmp($a['name'], $b['name']);
            if ($nameOrder !== 0) return $nameOrder;
            return strcasecmp($a['badgeText'], $b['badgeText']);
        });
        return $results;
    }
}
