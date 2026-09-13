<?php
/*
 |--------------------------------------------------------------------
 | ANALYTICS — real page-view logging + read helpers
 |--------------------------------------------------------------------
 | Backs the "Active Users", "Total Page Views", "Monthly Visitor
 | Trend", and "Traffic Sources" panels on admin/admindashboard.php,
 | which previously had no real data source at all.
 |
 | Include AFTER config/dbmain.php (needs $conn). Call analytics_track()
 | once near the top of every public page you want counted — it logs
 | one row per page load, page-level (not per-card-click), which is
 | the standard definition of a "page view."
 |
 |   page_views(view_id, page, referrer_host, viewed_at)
 */

$conn->query(
    "CREATE TABLE IF NOT EXISTS page_views (
        view_id       INT AUTO_INCREMENT PRIMARY KEY,
        page          VARCHAR(30) NOT NULL,
        referrer_host VARCHAR(120) NULL,
        viewed_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (page),
        INDEX (viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Per-place view counts (e.g. "how many visitors did BFC have?") — logged
// whenever a visitor opens a listing's "View Details" modal/page, across
// every catalog type. See pages/track_item_view.php for the public
// endpoint that writes here, and analytics_item_types() below for the
// item_type slugs it accepts.
$conn->query(
    "CREATE TABLE IF NOT EXISTS item_views (
        view_id   INT AUTO_INCREMENT PRIMARY KEY,
        item_type VARCHAR(20) NOT NULL,
        item_id   INT NOT NULL,
        viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (item_type, item_id),
        INDEX (viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Human-readable labels for the "page" slugs passed to analytics_track(),
// reused wherever the admin dashboard needs to display them.
function analytics_page_labels(): array
{
    return [
        'home'        => 'Homepage',
        'about'       => 'About Malvar',
        'tourism'     => 'Tourism Hub',
        'nature'      => 'Nature',
        'resort'      => 'Resort',
        'industry'    => 'Industry Zone',
        'products'    => 'Products',
        'restaurants' => 'Restaurants',
        'fiestas'     => 'Fiestas',
        'people'      => 'People of Malvar',
        'foryou'      => 'For You',
        'traveldiary' => 'Travel Diary',
        'favorites'   => 'Favorites',
        'mostpopular' => 'Most Popular',
    ];
}

/*
 |--------------------------------------------------------------------
 | WRITE
 |--------------------------------------------------------------------
 */
function analytics_track(mysqli $conn, string $page): void
{
    $referrerHost = 'Direct';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $selfHost = $_SERVER['HTTP_HOST'] ?? '';
        if ($host && $host !== $selfHost) {
            $referrerHost = $host;
        }
    }

    $stmt = $conn->prepare("INSERT INTO page_views (page, referrer_host) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param('ss', $page, $referrerHost);
        $stmt->execute();
        $stmt->close();
    }
}

/*
 |--------------------------------------------------------------------
 | READ
 |--------------------------------------------------------------------
 */

// Site visitors with a heartbeat (config/dbmain.php) in the last N minutes.
function analytics_active_users(mysqli $conn, int $minutes = 5): int
{
    $count = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM users WHERE last_activity >= (NOW() - INTERVAL ? MINUTE)");
    if ($stmt) {
        $stmt->bind_param('i', $minutes);
        $stmt->execute();
        $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
    }
    return $count;
}

// Total logged page views, optionally since a given SQL datetime string.
function analytics_total_views(mysqli $conn, ?string $sinceSql = null): int
{
    $sql = "SELECT COUNT(*) c FROM page_views";
    if ($sinceSql) {
        $stmt = $conn->prepare($sql . " WHERE viewed_at >= ?");
        $stmt->bind_param('s', $sinceSql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['c'] ?? 0);
    }
    $res = $conn->query($sql);
    return (int) ($res ? ($res->fetch_assoc()['c'] ?? 0) : 0);
}

// [{page, label, views}], most-viewed first.
function analytics_page_breakdown(mysqli $conn, ?string $sinceSql = null, int $limit = 6): array
{
    $labels = analytics_page_labels();
    $sql = "SELECT page, COUNT(*) c FROM page_views";
    if ($sinceSql) {
        $stmt = $conn->prepare($sql . " WHERE viewed_at >= ? GROUP BY page ORDER BY c DESC LIMIT ?");
        $stmt->bind_param('si', $sinceSql, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $stmt = $conn->prepare($sql . " GROUP BY page ORDER BY c DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    return array_map(fn($r) => [
        'page'  => $r['page'],
        'label' => $labels[$r['page']] ?? ucfirst($r['page']),
        'views' => (int) $r['c'],
    ], $rows);
}

// Last N calendar months (oldest first): [{label: 'Mar 2026', ym: '2026-03', value, pct}]
function analytics_monthly_trend(mysqli $conn, int $months = 6): array
{
    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(viewed_at, '%Y-%m') ym, COUNT(*) c
         FROM page_views
         WHERE viewed_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL ? MONTH)
         GROUP BY ym"
    );
    $lookback = $months - 1;
    $stmt->bind_param('i', $lookback);
    $stmt->execute();
    $byMonth = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $byMonth[$r['ym']] = (int) $r['c'];
    }
    $stmt->close();

    $result = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $dt = new DateTime("first day of -{$i} months");
        $ym = $dt->format('Y-m');
        $result[] = ['ym' => $ym, 'label' => $dt->format('M Y'), 'value' => $byMonth[$ym] ?? 0];
    }
    $max = !empty($result) ? max(array_column($result, 'value')) : 0;
    foreach ($result as &$row) {
        $row['pct'] = $max > 0 ? (int) round($row['value'] / $max * 100) : 0;
    }
    unset($row);
    return $result;
}

// [{label, pct}] — where traffic is coming from, optionally since a date.
function analytics_traffic_sources(mysqli $conn, ?string $sinceSql = null, int $limit = 5): array
{
    $sql = "SELECT referrer_host, COUNT(*) c FROM page_views";
    if ($sinceSql) {
        $stmt = $conn->prepare($sql . " WHERE viewed_at >= ? GROUP BY referrer_host ORDER BY c DESC LIMIT ?");
        $stmt->bind_param('si', $sinceSql, $limit);
    } else {
        $stmt = $conn->prepare($sql . " GROUP BY referrer_host ORDER BY c DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total = array_sum(array_column($rows, 'c'));
    if ($total === 0) return [];

    return array_map(fn($r) => [
        'label' => $r['referrer_host'] === 'Direct' ? 'Direct / App' : $r['referrer_host'],
        'pct'   => (int) round((int) $r['c'] / $total * 100),
    ], $rows);
}

/*
 |--------------------------------------------------------------------
 | PER-ITEM VIEWS — "how many visitors did BFC have?"
 |--------------------------------------------------------------------
 */

// item_type slug -> where its real name/id live, so item_views rows can
// be joined back to an actual listing name. nature/resort/industry all
// share the `destination` table, split by category.
function analytics_item_types(): array
{
    return [
        'nature'     => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'nature'"],
        'resort'     => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'resort'"],
        'industry'   => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'industry'"],
        'product'    => ['table' => 'products',     'id_col' => 'product_id',      'name_col' => 'product_name',     'where' => '1=1'],
        'restaurant' => ['table' => 'restaurants',  'id_col' => 'restaurant_id',   'name_col' => 'restaurant_name',  'where' => '1=1'],
        'fiesta'     => ['table' => 'fiestas',      'id_col' => 'fiesta_id',       'name_col' => 'fiesta_name',      'where' => '1=1'],
        'person'     => ['table' => 'people',       'id_col' => 'person_id',       'name_col' => 'fullname',         'where' => '1=1'],
    ];
}

function analytics_track_item(mysqli $conn, string $itemType, int $itemId): void
{
    if ($itemId <= 0 || !array_key_exists($itemType, analytics_item_types())) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO item_views (item_type, item_id) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param('si', $itemType, $itemId);
        $stmt->execute();
        $stmt->close();
    }
}

// Views for one specific item — e.g. analytics_item_view_count($conn, 'restaurant', 7).
function analytics_item_view_count(mysqli $conn, string $itemType, int $itemId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM item_views WHERE item_type = ? AND item_id = ?");
    $stmt->bind_param('si', $itemType, $itemId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

// [item_id => views] for one item_type — lets an admin listing table show
// a Views column without running one query per row.
function analytics_item_views_bulk(mysqli $conn, string $itemType): array
{
    $counts = [];
    $stmt = $conn->prepare("SELECT item_id, COUNT(*) c FROM item_views WHERE item_type = ? GROUP BY item_id");
    $stmt->bind_param('s', $itemType);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $counts[(int) $r['item_id']] = (int) $r['c'];
    }
    $stmt->close();
    return $counts;
}

// The most-viewed real places across every catalog type, joined back to
// their actual names — powers "Most Viewed Places" on the dashboard and
// answers "how many visitors did BFC have?" directly.
function analytics_top_items(mysqli $conn, int $limit = 8, ?string $sinceSql = null): array
{
    $results = [];
    foreach (analytics_item_types() as $slug => $meta) {
        $sql = "SELECT t.{$meta['id_col']} AS id, t.{$meta['name_col']} AS name, COUNT(v.view_id) AS views
                FROM {$meta['table']} t
                INNER JOIN item_views v ON v.item_type = '$slug' AND v.item_id = t.{$meta['id_col']}
                WHERE {$meta['where']}" . ($sinceSql ? " AND v.viewed_at >= '$sinceSql'" : '') . "
                GROUP BY t.{$meta['id_col']}, t.{$meta['name_col']}";
        if ($res = $conn->query($sql)) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
                $results[] = ['type' => $slug, 'id' => (int) $r['id'], 'name' => $r['name'], 'views' => (int) $r['views']];
            }
        }
    }
    usort($results, fn($a, $b) => $b['views'] <=> $a['views']);
    return array_slice($results, 0, $limit);
}
