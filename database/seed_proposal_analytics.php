<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('KULTOURA_ENABLE_PROPOSAL_ANALYTICS') !== '1') {
    fwrite(STDERR, "Set KULTOURA_ENABLE_PROPOSAL_ANALYTICS=1 to seed proposal analytics history.\n");
    exit(1);
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';

if (!kt_activity_ensure_schema($conn)) {
    fwrite(STDERR, "Could not initialize the activity log table.\n");
    exit(1);
}
$conn->query("SET time_zone = '+08:00'");
$seedKey = 'proposal_analytics_2024_2026_v1';
$pageViewSource = 'proposal-seed.kultoura.local';
$conn->query("CREATE TABLE IF NOT EXISTS analytics_proposal_seed_batches (
    seed_key VARCHAR(80) NOT NULL PRIMARY KEY,
    page_view_count INT UNSIGNED NOT NULL DEFAULT 0,
    item_view_count INT UNSIGNED NOT NULL DEFAULT 0,
    activity_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS analytics_proposal_seed_rows (
    seed_key VARCHAR(80) NOT NULL,
    table_name VARCHAR(32) NOT NULL,
    row_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (seed_key, table_name, row_id),
    KEY (table_name, row_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$exists = $conn->prepare('SELECT seed_key FROM analytics_proposal_seed_batches WHERE seed_key=? LIMIT 1');
$exists->bind_param('s', $seedKey);
$exists->execute();
if ($exists->get_result()->fetch_row()) {
    $exists->close();
    echo "Proposal analytics history is already present; no rows were changed.\n";
    exit(0);
}
$exists->close();

$catalog = [];
foreach (analytics_item_types() as $type => $definition) {
    $sql = "SELECT {$definition['id_col']} id, {$definition['name_col']} name FROM {$definition['table']} WHERE {$definition['where']} AND status='active'";
    if ($result = $conn->query($sql)) {
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $item) {
            $catalog[] = ['type' => $type, 'id' => (int) $item['id'], 'name' => (string) $item['name']];
        }
    }
}
$categories = kt_activity_categories();
$pageKeys = array_keys(analytics_page_labels());
$pageWeights = [
    'home' => 30, 'tourism' => 20, 'nature' => 9, 'resort' => 8, 'products' => 7,
    'restaurants' => 6, 'fiestas' => 5, 'mostpopular' => 4, 'foryou' => 3,
    'about' => 2, 'industry' => 2, 'people' => 2, 'traveldiary' => 1, 'favorites' => 1,
];
$activityWeights = ['click' => 42, 'search' => 18, 'generate' => 8, 'download' => 5, 'comment' => 7, 'recommendation' => 7, 'map_interaction' => 6, 'language' => 3, 'other' => 4];
$activityElements = [
    'click' => ['Destination card', 'Explore destination', 'View details', 'Open featured content'],
    'search' => ['Destination search', 'Cultural content search', 'Restaurant search'],
    'generate' => ['Generate itinerary'], 'download' => ['Download itinerary'],
    'comment' => ['Post comment'], 'recommendation' => ['Open recommendation'],
    'map_interaction' => ['Move map', 'Open map marker'], 'language' => ['Select language'], 'other' => ['Open content section'],
];
$months = [];
$today = new DateTimeImmutable('today');
$month = new DateTimeImmutable('2024-01-01');
while ($month <= $today) {
    $months[] = $month;
    $month = $month->modify('+1 month');
}
mt_srand(20261119);
$pageViewStmt = $conn->prepare('INSERT INTO page_views (page, referrer_host, viewed_at) VALUES (?, ?, ?)');
$itemViewStmt = $conn->prepare('INSERT INTO item_views (item_type, item_id, viewed_at) VALUES (?, ?, ?)');
$activityStmt = $conn->prepare('INSERT INTO user_activity_log (user_id, session_hash, activity_type, category, parent_category, item_type, item_id, content_name, element_name, page_section, metadata, activity_date) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$auditStmt = $conn->prepare('INSERT INTO analytics_proposal_seed_rows (seed_key, table_name, row_id) VALUES (?, ?, ?)');
if (!$pageViewStmt || !$itemViewStmt || !$activityStmt || !$auditStmt) {
    fwrite(STDERR, "Could not prepare proposal analytics statements.\n");
    exit(1);
}

$pageViewCount = $itemViewCount = $activityCount = 0;
$conn->begin_transaction();
try {
    foreach ($months as $monthDate) {
        $year = (int) $monthDate->format('Y');
        $monthNumber = (int) $monthDate->format('n');
        $base = $year === 2024 ? 76 : ($year === 2025 ? 112 : 148);
        $season = in_array($monthNumber, [4, 5, 12], true) ? 1.22 : (in_array($monthNumber, [6, 7, 8], true) ? .88 : 1.0);
        $monthlyPageViews = (int) round(($base + mt_rand(0, 36)) * $season);
        $daysInMonth = (int) $monthDate->format('t');
        if ($monthDate->format('Y-m') === $today->format('Y-m')) $daysInMonth = (int) $today->format('j');
        $monthPageStart = $pageViewCount;

        for ($index = 0; $index < $monthlyPageViews; $index++) {
            $page = $pageKeys[mt_rand(1, 100) <= 30 ? 0 : mt_rand(0, count($pageKeys) - 1)];
            $roll = mt_rand(1, array_sum($pageWeights));
            foreach ($pageWeights as $pageKey => $weight) {
                $roll -= $weight;
                if ($roll <= 0) { $page = $pageKey; break; }
            }
            $day = mt_rand(1, max(1, $daysInMonth));
            $hour = mt_rand(7, 22);
            $minute = mt_rand(0, 59);
            $viewedAt = $monthDate->format('Y-m') . sprintf('-%02d %02d:%02d:%02d', $day, $hour, $minute, mt_rand(0, 59));
            if (new DateTimeImmutable($viewedAt) > new DateTimeImmutable()) $viewedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $pageViewStmt->bind_param('sss', $page, $pageViewSource, $viewedAt);
            if (!$pageViewStmt->execute()) throw new RuntimeException('Could not insert proposal page views.');
            $pageViewCount++;
        }

        if ($catalog) {
            $monthlyItemViews = (int) round(($pageViewCount - $monthPageStart) * .32);
            for ($index = 0; $index < $monthlyItemViews; $index++) {
                $item = $catalog[mt_rand(0, count($catalog) - 1)];
                $day = mt_rand(1, max(1, $daysInMonth));
                $viewedAt = $monthDate->format('Y-m') . sprintf('-%02d %02d:%02d:%02d', $day, mt_rand(8, 21), mt_rand(0, 59), mt_rand(0, 59));
                if (new DateTimeImmutable($viewedAt) > new DateTimeImmutable()) $viewedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
                $itemType = $item['type']; $itemId = $item['id'];
                $itemViewStmt->bind_param('sis', $itemType, $itemId, $viewedAt);
                if (!$itemViewStmt->execute()) throw new RuntimeException('Could not insert proposal item views.');
                $rowId = (int) $itemViewStmt->insert_id;
                $tableName = 'item_views';
                $auditStmt->bind_param('ssi', $seedKey, $tableName, $rowId);
                if (!$auditStmt->execute()) throw new RuntimeException('Could not record item-view cleanup metadata.');
                $itemViewCount++;
            }
        }

        $monthlyActivity = (int) round(($pageViewCount - $monthPageStart) * .38);
        for ($index = 0; $index < $monthlyActivity; $index++) {
            $roll = mt_rand(1, array_sum($activityWeights));
            $activityType = 'click';
            foreach ($activityWeights as $type => $weight) {
                $roll -= $weight;
                if ($roll <= 0) { $activityType = $type; break; }
            }
            $item = $catalog ? $catalog[mt_rand(0, count($catalog) - 1)] : null;
            $category = $item['type'] ?? null;
            $parent = $category !== null ? ($categories[$category]['parent'] ?? null) : null;
            $itemType = $item['type'] ?? null;
            $itemId = $item['id'] ?? null;
            $contentName = $item['name'] ?? null;
            $elementOptions = $activityElements[$activityType] ?? ['Open content'];
            $elementName = $elementOptions[mt_rand(0, count($elementOptions) - 1)];
            $pageSection = $category ?? $pageKeys[mt_rand(0, count($pageKeys) - 1)];
            $sessionHash = hash('sha256', 'proposal-analytics-session-' . mt_rand(1, 700));
            $metadata = json_encode(['seed' => $seedKey], JSON_UNESCAPED_SLASHES);
            $day = mt_rand(1, max(1, $daysInMonth));
            $activityDate = $monthDate->format('Y-m') . sprintf('-%02d %02d:%02d:%02d', $day, mt_rand(8, 22), mt_rand(0, 59), mt_rand(0, 59));
            if (new DateTimeImmutable($activityDate) > new DateTimeImmutable()) $activityDate = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $activityStmt->bind_param('sssssisssss', $sessionHash, $activityType, $category, $parent, $itemType, $itemId, $contentName, $elementName, $pageSection, $metadata, $activityDate);
            if (!$activityStmt->execute()) throw new RuntimeException('Could not insert proposal activity history.');
            $rowId = (int) $activityStmt->insert_id;
            $tableName = 'user_activity_log';
            $auditStmt->bind_param('ssi', $seedKey, $tableName, $rowId);
            if (!$auditStmt->execute()) throw new RuntimeException('Could not record activity cleanup metadata.');
            $activityCount++;
        }
    }
    $batchStmt = $conn->prepare('INSERT INTO analytics_proposal_seed_batches (seed_key,page_view_count,item_view_count,activity_count) VALUES (?,?,?,?)');
    $batchStmt->bind_param('siii', $seedKey, $pageViewCount, $itemViewCount, $activityCount);
    if (!$batchStmt->execute()) throw new RuntimeException('Could not save proposal seed completion marker.');
    $batchStmt->close();
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    fwrite(STDERR, $error->getMessage() . " All inserted rows were rolled back.\n");
    exit(1);
} finally {
    $pageViewStmt->close();
    $itemViewStmt->close();
    $activityStmt->close();
    $auditStmt->close();
}

echo "Proposal analytics history added from 2024-01 through " . $today->format('Y-m-d') . ".\n";
echo "Page views: {$pageViewCount}; item views: {$itemViewCount}; user activity events: {$activityCount}.\n";
