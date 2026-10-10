<?php
/*
 |--------------------------------------------------------------------
 | SITE-WIDE SEARCH — one function, every public content type
 |--------------------------------------------------------------------
 | Backs both the navbar search bar's live dropdown (search_api.php)
 | and the full results page (pages/search.php). Matches against the
 | same tables/status filters every public browse page already uses,
 | so a search result is never something a visitor couldn't otherwise
 | find by browsing — archived/pending/draft content stays out.
 */

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
        // Already-absolute paths (most admin uploads store these) pass
        // through untouched; only a bare filename gets the folder prefix.
        if (str_starts_with($rawImage, '/') || str_starts_with($rawImage, 'http')) {
            return $rawImage;
        }
        return '/kultoura/assets/uploads/' . kt_search_image_subfolder($category) . '/' . basename($rawImage);
    }
}

// Destination categories link to their own public page; everything else
// has one shared page.
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

/**
 * Searches every public content type for $query, returning a flat,
 * relevance-ordered (exact/starts-with matches first) array of:
 *   type, id, name, badgeText, category, image, link, snippet
 */
if (!function_exists('kt_search_all')) {
    function kt_search_all(mysqli $conn, string $query, int $limitPerType = 8): array
    {
        $query = trim($query);
        if ($query === '') return [];

        $like = '%' . $conn->real_escape_string($query) . '%';
        $results = [];

        // ---- Destinations (all 7 public categories share this table) ----
        $stmt = $conn->prepare(
            "SELECT destination_id, destination_name, category, description, image
             FROM destination WHERE status = 'active' AND (destination_name LIKE ? OR description LIKE ? OR address LIKE ?)
             ORDER BY destination_name ASC LIMIT ?"
        );
        $stmt->bind_param('sssi', $like, $like, $like, $limitPerType);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $results[] = [
                'type'      => 'destination',
                'id'        => (int) $row['destination_id'],
                'name'      => $row['destination_name'],
                'badgeText' => ucfirst($row['category']),
                'category'  => $row['category'],
                'image'     => kt_search_image_src($row['category'], $row['image']),
                'link'      => kt_search_link('destination', $row['category'], (int) $row['destination_id']),
                'snippet'   => mb_substr((string) $row['description'], 0, 110),
            ];
        }
        $stmt->close();

        // ---- Products ----
        $stmt = $conn->prepare(
            "SELECT product_id, product_name, category, description, image
             FROM products WHERE status = 'active' AND (product_name LIKE ? OR description LIKE ? OR category LIKE ?)
             ORDER BY product_name ASC LIMIT ?"
        );
        $stmt->bind_param('sssi', $like, $like, $like, $limitPerType);
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
                'snippet'   => mb_substr((string) $row['description'], 0, 110),
            ];
        }
        $stmt->close();

        // ---- Restaurants ----
        $stmt = $conn->prepare(
            "SELECT restaurant_id, restaurant_name, category, description, image
             FROM restaurants WHERE status = 'active' AND (restaurant_name LIKE ? OR description LIKE ? OR category LIKE ?)
             ORDER BY restaurant_name ASC LIMIT ?"
        );
        $stmt->bind_param('sssi', $like, $like, $like, $limitPerType);
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
                'snippet'   => mb_substr((string) $row['description'], 0, 110),
            ];
        }
        $stmt->close();

        // ---- Fiestas / Events ----
        $stmt = $conn->prepare(
            "SELECT fiesta_id, fiesta_name, type, description, image
             FROM fiestas WHERE status = 'active' AND (fiesta_name LIKE ? OR description LIKE ? OR location LIKE ?)
             ORDER BY fiesta_name ASC LIMIT ?"
        );
        $stmt->bind_param('sssi', $like, $like, $like, $limitPerType);
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
                'snippet'   => mb_substr((string) $row['description'], 0, 110),
            ];
        }
        $stmt->close();

        // ---- People of Malvar ----
        $stmt = $conn->prepare(
            "SELECT person_id, fullname, title, achievement, description, image
             FROM people WHERE status = 'active' AND (fullname LIKE ? OR title LIKE ? OR achievement LIKE ? OR description LIKE ?)
             ORDER BY fullname ASC LIMIT ?"
        );
        $stmt->bind_param('ssssi', $like, $like, $like, $like, $limitPerType);
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
                'snippet'   => mb_substr((string) ($row['achievement'] ?: $row['description']), 0, 110),
            ];
        }
        $stmt->close();

        // ---- Live announcements ----
        $stmt = $conn->prepare(
            "SELECT id, title, body, image
             FROM announcements WHERE status = 'live' AND (title LIKE ? OR body LIKE ?)
             ORDER BY published_at DESC LIMIT ?"
        );
        $stmt->bind_param('ssi', $like, $like, $limitPerType);
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

        // Exact/starts-with name matches first, then everything else —
        // each in the DB's own alphabetical order within that tier.
        $needle = mb_strtolower($query);
        usort($results, function ($a, $b) use ($needle) {
            $rank = function ($r) use ($needle) {
                $name = mb_strtolower($r['name']);
                if ($name === $needle) return 0;
                if (str_starts_with($name, $needle)) return 1;
                return 2;
            };
            return $rank($a) <=> $rank($b);
        });

        return $results;
    }
}
