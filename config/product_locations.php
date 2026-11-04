<?php
require_once __DIR__ . '/malvar_locations.php';
if (!function_exists('kt_product_locations_ensure_schema')) {
    function kt_product_locations_ensure_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS product_locations (
            location_id INT NOT NULL AUTO_INCREMENT,
            product_id INT NOT NULL,
            store_name VARCHAR(150) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (location_id),
            KEY idx_product_locations_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $conn->query("INSERT INTO product_locations (product_id, store_name, address, latitude, longitude)
            SELECT p.product_id, '', p.location, p.latitude, p.longitude
            FROM products p
            WHERE p.location IS NOT NULL AND TRIM(p.location) <> ''
              AND NOT EXISTS (SELECT 1 FROM product_locations pl WHERE pl.product_id = p.product_id)");
    }
}

if (!function_exists('kt_product_locations_fetch')) {
    function kt_product_locations_fetch(mysqli $conn, array $productIds): array
    {
        kt_product_locations_ensure_schema($conn);
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn($id) => $id > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT location_id, product_id, store_name, address, latitude, longitude
            FROM product_locations WHERE product_id IN ($placeholders) ORDER BY location_id ASC");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $locations = [];
        while ($row = $result->fetch_assoc()) {
            $locations[(int) $row['product_id']][] = [
                'id' => (int) $row['location_id'],
                'store_name' => $row['store_name'],
                'address' => $row['address'],
                'lat' => $row['latitude'],
                'lng' => $row['longitude'],
            ];
        }
        $stmt->close();
        return $locations;
    }
}

if (!function_exists('kt_product_locations_normalize')) {
    function kt_product_locations_normalize($submitted): array
    {
        if (!is_array($submitted)) return [];
        $locations = [];
        foreach (array_slice($submitted, 0, 20) as $row) {
            if (!is_array($row)) continue;
            $storeName = trim((string) ($row['store_name'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $latText = trim((string) ($row['latitude'] ?? ''));
            $lngText = trim((string) ($row['longitude'] ?? ''));
            if ($storeName === '' && $address === '' && $latText === '' && $lngText === '') continue;
            if ($address === '') continue;
            $lat = is_numeric($latText) && (float) $latText >= -90 && (float) $latText <= 90 ? (float) $latText : null;
            $lng = is_numeric($lngText) && (float) $lngText >= -180 && (float) $lngText <= 180 ? (float) $lngText : null;
            if (($lat !== null || $lng !== null) && ($lat === null || $lng === null || !kt_malvar_coordinates_in_bounds($lat, $lng))) continue;
            if ($lat === null || $lng === null) {
                $lat = null;
                $lng = null;
            }
            $locations[] = [
                'store_name' => mb_substr($storeName, 0, 150),
                'address' => mb_substr($address, 0, 255),
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }
        return $locations;
    }
}

if (!function_exists('kt_product_locations_save')) {
    function kt_product_locations_save(mysqli $conn, int $productId, array $locations): void
    {
        kt_product_locations_ensure_schema($conn);
        $delete = $conn->prepare('DELETE FROM product_locations WHERE product_id = ?');
        $delete->bind_param('i', $productId);
        $delete->execute();
        $delete->close();
        if (!$locations) return;
        $stmt = $conn->prepare('INSERT INTO product_locations (product_id, store_name, address, latitude, longitude) VALUES (?, ?, ?, ?, ?)');
        foreach ($locations as $location) {
            $storeName = (string) ($location['store_name'] ?? '');
            $address = (string) ($location['address'] ?? '');
            $lat = $location['latitude'] ?? null;
            $lng = $location['longitude'] ?? null;
            $stmt->bind_param('issdd', $productId, $storeName, $address, $lat, $lng);
            $stmt->execute();
        }
        $stmt->close();
    }
}
