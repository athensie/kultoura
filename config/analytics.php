<?php
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
$conn->query(
    "CREATE TABLE IF NOT EXISTS user_item_interactions (
        interaction_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_type VARCHAR(20) NOT NULL,
        item_id INT NOT NULL,
        viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id, viewed_at),
        INDEX (user_id, item_type, item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$conn->query(
    "CREATE TABLE IF NOT EXISTS search_log (
        search_id     INT AUTO_INCREMENT PRIMARY KEY,
        query         VARCHAR(150) NOT NULL,
        result_count  INT NOT NULL,
        searched_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (searched_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
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
function analytics_total_views(mysqli $conn, ?string $sinceSql = null): int
{
    $sql = "SELECT COUNT(*) c FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local')";
    if ($sinceSql) {
        $stmt = $conn->prepare($sql . " AND viewed_at >= ?");
        $stmt->bind_param('s', $sinceSql);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['c'] ?? 0);
    }
    $res = $conn->query($sql);
    return (int) ($res ? ($res->fetch_assoc()['c'] ?? 0) : 0);
}
function analytics_page_breakdown(mysqli $conn, ?string $sinceSql = null, int $limit = 6): array
{
    $labels = analytics_page_labels();
    $sql = "SELECT page, COUNT(*) c FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local')";
    if ($sinceSql) {
        $stmt = $conn->prepare($sql . " AND viewed_at >= ? GROUP BY page ORDER BY c DESC LIMIT ?");
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
function analytics_monthly_trend(mysqli $conn, int $months = 6): array
{
    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(viewed_at, '%Y-%m') ym, COUNT(*) c
         FROM page_views
         WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local')
           AND viewed_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL ? MONTH)
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
function analytics_traffic_sources(mysqli $conn, ?string $sinceSql = null, int $limit = 5): array
{
    $sql = "SELECT referrer_host, COUNT(*) c FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local')";
    if ($sinceSql) {
        $stmt = $conn->prepare($sql . " AND viewed_at >= ? GROUP BY referrer_host ORDER BY c DESC LIMIT ?");
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
function analytics_item_types(): array
{
    return [
        'destination'   => ['table' => 'destination',  'id_col' => 'destination_id', 'name_col' => 'destination_name', 'where' => '1=1'],
        'nature'        => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'nature'"],
        'resort'        => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'resort'"],
        'industry'      => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'industry'"],
        'accommodation' => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'accommodation'"],
        'bank'          => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'bank'"],
        'service'       => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'service'"],
        'church'        => ['table' => 'destination',  'id_col' => 'destination_id',  'name_col' => 'destination_name', 'where' => "category = 'church'"],
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
function analytics_track_user_item(mysqli $conn, int $userId, string $itemType, int $itemId): void
{
    if ($userId <= 0 || $itemId <= 0 || !array_key_exists($itemType, analytics_item_types())) {
        return;
    }
    $stmt = $conn->prepare('INSERT INTO user_item_interactions (user_id, item_type, item_id) VALUES (?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('isi', $userId, $itemType, $itemId);
        $stmt->execute();
        $stmt->close();
    }
}
function analytics_item_view_count(mysqli $conn, string $itemType, int $itemId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM item_views WHERE item_type = ? AND item_id = ?");
    $stmt->bind_param('si', $itemType, $itemId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}
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
function analytics_top_items(mysqli $conn, int $limit = 8, ?string $sinceSql = null, array $excludeTypes = [], array $includeTypes = []): array
{
    $results = [];
    foreach (analytics_item_types() as $slug => $meta) {
        if (in_array($slug, $excludeTypes, true) || (!empty($includeTypes) && !in_array($slug, $includeTypes, true))) {
            continue;
        }
        $sql = "SELECT t.{$meta['id_col']} AS id, t.{$meta['name_col']} AS name, COUNT(v.view_id) AS views
                FROM {$meta['table']} t
                INNER JOIN item_views v ON v.item_type = '$slug' AND v.item_id = t.{$meta['id_col']}
                WHERE {$meta['where']} AND t.status = 'active'" . ($sinceSql ? " AND v.viewed_at >= '$sinceSql'" : '') . "
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
function analytics_review_sentiment(string $text): float
{
    $positive = ['amazing','awesome','beautiful','best','clean','delicious','enjoy','excellent','friendly','good','great','helpful','love','nice','peaceful','recommend','relaxing','safe','sulit','masarap','maganda','malinis','mabait','masaya'];
    $negative = ['awful','bad','boring','dirty','expensive','hate','noisy','overpriced','poor','rude','slow','unsafe','waste','worst','pangit','marumi','mahal','malungkot','nakakadismaya','madumi'];
    $negators = ['not','never','no','hardly','isnt','wasnt','dont','didnt','cannot','hindi','di','walang','wala'];
    $tokens = preg_split('/[^\\p{L}\\p{N}\']+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $polarity = 0;
    $matched = 0;
    $negationWindow = 0;
    foreach ($tokens as $token) {
        if (in_array($token, $negators, true)) { $negationWindow = 3; continue; }
        $value = in_array($token, $positive, true) ? 1 : (in_array($token, $negative, true) ? -1 : 0);
        if ($value !== 0) {
            $polarity += $negationWindow > 0 ? -$value : $value;
            $matched++;
        }
        if ($negationWindow > 0) $negationWindow--;
    }
    return $matched ? max(-1, min(1, $polarity / $matched)) : 0.0;
}
function analytics_most_popular_items(mysqli $conn, int $limit = 24): array
{
    $items = analytics_top_items($conn, 5000);
    if (!$items) return [];
    $viewCounts = [];
    if ($res = $conn->query('SELECT item_type,item_id,COUNT(*) c FROM item_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY item_type,item_id')) {
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) $viewCounts[$row['item_type'].'-'.$row['item_id']] = (int)$row['c'];
    }
    $feedback = [];
    $maxRecentReviews = 0;
    $maxReviewCount = 0;
    $reviewTable = $conn->query("SHOW TABLES LIKE 'item_reviews'");
    if ($reviewTable && $reviewTable->num_rows > 0) {
        $candidateIds = [];
        foreach ($items as $item) $candidateIds[$item['type']][] = (int)$item['id'];
        $conditions = [];
        foreach ($candidateIds as $itemType => $ids) {
            $safeType = $conn->real_escape_string($itemType);
            $conditions[] = "(item_type='$safeType' AND item_id IN (".implode(',', $ids).'))';
        }
        $reviewSql = "SELECT item_type,item_id,rating,review_text,created_at FROM item_reviews WHERE status='visible' AND (".implode(' OR ', $conditions).") ORDER BY created_at DESC";
        if ($res = $conn->query($reviewSql)) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $review) {
                $key = $review['item_type'].'-'.$review['item_id'];
                if (!isset($feedback[$key])) $feedback[$key] = ['count'=>0,'rating_total'=>0,'sentiment_total'=>0,'recent'=>0];
                $feedback[$key]['count']++;
                $feedback[$key]['rating_total'] += (int)$review['rating'];
                $feedback[$key]['sentiment_total'] += analytics_review_sentiment((string)$review['review_text']);
                if (strtotime((string)$review['created_at']) >= time() - 30 * 86400) $feedback[$key]['recent']++;
            }
            $res->free();
        }
    }
    foreach ($feedback as $stats) {
        $maxRecentReviews = max($maxRecentReviews, $stats['recent']);
        $maxReviewCount = max($maxReviewCount, $stats['count']);
    }
    $maxViews = max(1, max(array_column($items, 'views')));
    $maxRecentViews = max(1, max($viewCounts ?: [0]));
    foreach ($items as &$item) {
        $key = $item['type'].'-'.$item['id'];
        $stats = $feedback[$key] ?? null;
        $recentViews = $viewCounts[$key] ?? 0;
        $viewScore = log(1 + $item['views']) / log(1 + $maxViews);
        $recentViewScore = log(1 + $recentViews) / log(1 + $maxRecentViews);
        $item['views_30d'] = $recentViews;
        $item['review_count'] = $stats['count'] ?? 0;
        $item['average_rating'] = $stats ? $stats['rating_total'] / $stats['count'] : 0.0;
        $item['sentiment'] = $stats ? $stats['sentiment_total'] / $stats['count'] : 0.0;
        $item['reviews_30d'] = $stats['recent'] ?? 0;
        if ($stats) {
            $ratingScore = $item['average_rating'] / 5;
            $sentimentScore = ($item['sentiment'] + 1) / 2;
            $reviewVolumeScore = log(1 + $stats['count']) / log(1 + max(1, $maxReviewCount));
            $reviewTrendScore = log(1 + $stats['recent']) / log(1 + max(1, $maxRecentReviews));
            $item['popularity_score'] = 100 * (.35*$viewScore + .15*$recentViewScore + .20*$ratingScore + .15*$sentimentScore + .075*$reviewVolumeScore + .075*$reviewTrendScore);
        } else {
            $item['popularity_score'] = 100 * (.65*$viewScore + .35*$recentViewScore);
        }
        $item['trending'] = $recentViews > 0 || ($stats['recent'] ?? 0) > 0;
    }
    unset($item);
    usort($items, static fn($a,$b) => ($b['popularity_score'] <=> $a['popularity_score']) ?: ($b['views'] <=> $a['views']));
    return array_slice($items, 0, max(1, $limit));
}
function analytics_type_group_map(): array
{
    return [
        'nature' => 'destination', 'resort' => 'destination', 'industry' => 'destination',
        'accommodation' => 'destination', 'bank' => 'destination', 'service' => 'destination', 'church' => 'destination',
    ];
}
function analytics_conversion_top_items(mysqli $conn, int $limit = 8, int $minViews = 3): array
{
    $viewItems = analytics_top_items($conn, 100);
    $viewItems = array_values(array_filter($viewItems, fn($i) => $i['views'] >= $minViews));
    if (empty($viewItems)) {
        return [];
    }
    $favCounts = [];
    if ($res = $conn->query("SELECT item_type, item_id, COUNT(*) c FROM favorites GROUP BY item_type, item_id")) {
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
            $favCounts[$r['item_type'] . '-' . $r['item_id']] = (int) $r['c'];
        }
    }
    $diaryCounts = [];
    if ($res = $conn->query("SELECT item_type, item_id, COUNT(*) c FROM diary_entries GROUP BY item_type, item_id")) {
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
            $diaryCounts[$r['item_type'] . '-' . $r['item_id']] = (int) $r['c'];
        }
    }
    $groupMap = analytics_type_group_map();
    $out = [];
    foreach ($viewItems as $item) {
        $favType = $groupMap[$item['type']] ?? $item['type'];
        $key = $favType . '-' . $item['id'];
        $favorited = $favCounts[$key] ?? 0;
        $logged = $diaryCounts[$key] ?? 0;
        $out[] = [
            'type'        => $item['type'],
            'id'          => $item['id'],
            'name'        => $item['name'],
            'views'       => $item['views'],
            'favorites'   => $favorited,
            'diaryLogs'   => $logged,
            'favoritePct' => (int) round($favorited / $item['views'] * 100),
            'diaryPct'    => (int) round($logged / $item['views'] * 100),
        ];
    }
    usort($out, fn($a, $b) => $b['views'] <=> $a['views']);
    return array_slice($out, 0, $limit);
}
function analytics_track_search(mysqli $conn, string $query, int $resultCount): void
{
    $query = trim($query);
    if ($query === '' || mb_strlen($query) > 150) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO search_log (query, result_count) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param('si', $query, $resultCount);
        $stmt->execute();
        $stmt->close();
    }
}
function analytics_top_searches(mysqli $conn, int $limit = 8): array
{
    $stmt = $conn->prepare(
        "SELECT LOWER(query) AS q, COUNT(*) c FROM search_log GROUP BY LOWER(query) ORDER BY c DESC LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($r) => ['query' => $r['q'], 'count' => (int) $r['c']], $rows);
}
function analytics_zero_result_searches(mysqli $conn, int $limit = 8): array
{
    $stmt = $conn->prepare(
        "SELECT LOWER(query) AS q, COUNT(*) c FROM search_log WHERE result_count = 0 GROUP BY LOWER(query) ORDER BY c DESC LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($r) => ['query' => $r['q'], 'count' => (int) $r['c']], $rows);
}
