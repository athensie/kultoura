<?php
/*
 * Five years of DEMO audience analytics (visits, visitors, devices, listing views, intent clicks).
 *
 *   Seed:    KULTOURA_ENABLE_AUDIENCE_DEMO=1 php database/seed_audience_demo.php
 *   Remove:  KULTOURA_ENABLE_AUDIENCE_DEMO=1 php database/seed_audience_demo.php --remove
 *
 * Every demo row is tagged so --remove deletes exactly these rows and nothing real:
 *   page_views.visitor_id / visit_id, item_views.visit_id and user_activity_log.session_hash
 *   all start with DEMO_PREFIX, and activity metadata contains "demo":true.
 * On Railway, run it inside the web service (it reads the same MYSQL* variables as the site):
 *   railway ssh --service kultoura
 *   KULTOURA_ENABLE_AUDIENCE_DEMO=1 php database/seed_audience_demo.php
 * Demo numbers are invented. Remove them before using the dashboard for real decisions.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('KULTOURA_ENABLE_AUDIENCE_DEMO') !== '1') {
    fwrite(STDERR, "Set KULTOURA_ENABLE_AUDIENCE_DEMO=1 to seed (or --remove) demo audience analytics.\n");
    exit(1);
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
$conn->query("SET time_zone = '+08:00'");
if (!kt_activity_ensure_schema($conn)) {
    fwrite(STDERR, "Could not initialize the activity log table.\n");
    exit(1);
}
analytics_ensure_visit_columns($conn);

const DEMO_PREFIX = 'de1100de1100';   // 12 hex characters; real random IDs start with this ~1 in 280 trillion times
$like = DEMO_PREFIX . '%';

function demo_remove(mysqli $conn, string $like): array
{
    $counts = [];
    foreach ([
        'page_views' => 'DELETE FROM page_views WHERE visitor_id LIKE ?',
        'item_views' => 'DELETE FROM item_views WHERE visit_id LIKE ?',
        'user_activity_log' => 'DELETE FROM user_activity_log WHERE session_hash LIKE ?',
    ] as $table => $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $counts[$table] = $stmt->affected_rows;
        $stmt->close();
    }
    return $counts;
}

if (in_array('--remove', $argv, true)) {
    $removed = demo_remove($conn, $like);
    echo 'Removed demo rows: ' . json_encode($removed) . PHP_EOL;
    exit(0);
}

$existing = $conn->prepare('SELECT COUNT(*) total FROM page_views WHERE visitor_id LIKE ?');
$existing->bind_param('s', $like);
$existing->execute();
if ((int) $existing->get_result()->fetch_assoc()['total'] > 0) {
    fwrite(STDERR, "Demo audience data is already present. Run with --remove first to reseed.\n");
    exit(1);
}

mt_srand(20261124);
$rand = static fn(): float => mt_rand() / mt_getrandmax();
$pick = static function (array $weights) use ($rand) {
    $roll = $rand() * array_sum($weights);
    foreach ($weights as $key => $weight) { if (($roll -= $weight) <= 0) return $key; }
    return array_key_last($weights);
};
$demoId = static fn(): string => DEMO_PREFIX . bin2hex(random_bytes(10));

// Real active listings, so listing views and intent clicks point at actual content.
$listings = [];
foreach (analytics_item_types() as $type => $meta) {
    if ($type === 'destination') continue;
    $sql = "SELECT {$meta['id_col']} id FROM {$meta['table']} WHERE {$meta['where']} AND status = 'active'";
    if ($result = $conn->query($sql)) foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) $listings[] = ['type' => $type, 'id' => (int) $row['id']];
}
$categories = kt_activity_categories();

$pages = ['home' => 30, 'tourism' => 14, 'nature' => 9, 'restaurants' => 10, 'products' => 6, 'resort' => 6, 'fiestas' => 5, 'churches' => 4, 'people' => 3,
          'industry' => 2, 'accommodation' => 3, 'services' => 2, 'banks' => 1, 'about' => 3, 'foryou' => 4, 'mostpopular' => 4, 'search' => 3, 'traveldiary' => 2];
$referrers = ['Direct' => 52, 'www.facebook.com' => 24, 'm.facebook.com' => 8, 'www.google.com' => 11, 'l.messenger.com' => 2, 'www.tiktok.com' => 2, 'malvar.gov.ph' => 1];
$hourWeights = [1, 0.6, 0.4, 0.3, 0.3, 0.6, 1.2, 2, 3, 4, 5, 6, 6.5, 6, 5, 4.6, 4.8, 5.5, 6.5, 7.5, 7.5, 6, 4, 2];
$weekdayWeights = [1.25, 0.85, 0.9, 0.9, 0.95, 1.05, 1.35];                                   // Sun..Sat
$monthWeights = [1 => 1.05, 0.9, 1.0, 1.15, 1.3, 0.95, 0.85, 0.85, 0.9, 0.95, 1.0, 1.25];    // fiesta season May, holidays Dec
$pagesPerVisit = [1 => 42, 2 => 22, 3 => 15, 4 => 10, 5 => 6, 6 => 5];
$intents = ['directions' => 45, 'call' => 25, 'message' => 10, 'social' => 10, 'website' => 5, 'share' => 5];
$intentLabels = ['directions' => 'Navigate', 'call' => 'Call', 'message' => 'Message', 'social' => 'Facebook page', 'website' => 'Visit website', 'share' => 'Share'];

$end = new DateTimeImmutable('today');
$start = $end->modify('-5 years');
$totalDays = (int) $start->diff($end)->days;
$visitorPool = [];          // recent visitors who may come back: [id, last day index]
$pageRows = $itemRows = $activityRows = [];
$totals = ['visits' => 0, 'page_views' => 0, 'item_views' => 0, 'intent_actions' => 0];

$flush = static function (bool $force = false) use ($conn, &$pageRows, &$itemRows, &$activityRows) {
    if ($pageRows && ($force || count($pageRows) >= 1000)) { $conn->query('INSERT INTO page_views (page, referrer_host, visitor_id, visit_id, device, viewed_at) VALUES ' . implode(',', $pageRows)); $pageRows = []; }
    if ($itemRows && ($force || count($itemRows) >= 1000)) { $conn->query('INSERT INTO item_views (item_type, item_id, visit_id, viewed_at) VALUES ' . implode(',', $itemRows)); $itemRows = []; }
    if ($activityRows && ($force || count($activityRows) >= 500)) { $conn->query('INSERT INTO user_activity_log (user_id, session_hash, activity_type, category, parent_category, item_type, item_id, content_name, element_name, page_section, metadata, activity_date) VALUES ' . implode(',', $activityRows)); $activityRows = []; }
};

for ($dayIndex = 0; $dayIndex < $totalDays; $dayIndex++) {
    $day = $start->modify("+{$dayIndex} days");
    $progress = $dayIndex / max(1, $totalDays);
    $expected = 60 * (0.45 + 0.55 * $progress) * $weekdayWeights[(int) $day->format('w')] * $monthWeights[(int) $day->format('n')];
    $visitsToday = max(0, (int) round($expected * (0.85 + 0.3 * $rand())));
    $mobileShare = 0.58 + 0.14 * $progress;   // mobile grows over the years
    // Returning visitors (~32%) come from people seen in the last 180 days.
    $visitorPool = array_values(array_filter($visitorPool, static fn($entry) => $dayIndex - $entry[1] <= 180));
    for ($v = 0; $v < $visitsToday; $v++) {
        if ($visitorPool && $rand() < 0.32) {
            $slot = mt_rand(0, count($visitorPool) - 1);
            $visitorId = $visitorPool[$slot][0];
            $visitorPool[$slot][1] = $dayIndex;
        } else {
            $visitorId = $demoId();
            $visitorPool[] = [$visitorId, $dayIndex];
            if (count($visitorPool) > 4000) array_shift($visitorPool);
        }
        $visitId = $demoId();
        $deviceRoll = $rand();
        $device = $deviceRoll < $mobileShare ? 'Mobile' : ($deviceRoll < $mobileShare + 0.07 ? 'Tablet' : 'Desktop');
        $referrer = $pick($referrers);
        $time = $day->setTime((int) $pick($hourWeights), mt_rand(0, 59), mt_rand(0, 59));
        $pageCount = (int) $pick($pagesPerVisit);
        $hasListing = $pageCount > 1 || $rand() < 0.25;
        for ($p = 0; $p < $pageCount; $p++) {
            $page = $p === 0 && $rand() < 0.45 ? 'home' : $pick($pages);
            $pageRows[] = sprintf("('%s','%s','%s','%s','%s','%s')", $page, $conn->real_escape_string($p === 0 ? $referrer : 'Direct'), $visitorId, $visitId, $device, $time->format('Y-m-d H:i:s'));
            $totals['page_views']++;
            $time = $time->modify('+' . mt_rand(25, 240) . ' seconds');
        }
        if ($hasListing && $listings) {
            $opened = mt_rand(1, 3);
            for ($i = 0; $i < $opened; $i++) {
                $listing = $listings[mt_rand(0, count($listings) - 1)];
                $itemRows[] = sprintf("('%s',%d,'%s','%s')", $listing['type'], $listing['id'], $visitId, $time->format('Y-m-d H:i:s'));
                $totals['item_views']++;
                $time = $time->modify('+' . mt_rand(20, 180) . ' seconds');
                if ($rand() < 0.11) {
                    $intent = $pick($intents);
                    $category = isset($categories[$listing['type']]) ? $listing['type'] : null;
                    $parent = $category ? ($categories[$category]['parent'] ?? null) : null;
                    $metadata = json_encode(['intent' => $intent, 'device' => $device, 'device_source' => 'user_agent', 'demo' => true]);
                    $activityRows[] = sprintf(
                        "(NULL,'%s','%s',%s,%s,'%s',%d,NULL,'%s','%s','%s','%s')",
                        DEMO_PREFIX . substr(hash('sha256', $visitId), 0, 52),   // 64 chars, like real session hashes
                        $intent === 'directions' ? 'map_interaction' : 'click',
                        $category ? "'" . $category . "'" : 'NULL', $parent ? "'" . $parent . "'" : 'NULL',
                        $listing['type'], $listing['id'], $intentLabels[$intent], $listing['type'],
                        $conn->real_escape_string($metadata), $time->format('Y-m-d H:i:s')
                    );
                    $totals['intent_actions']++;
                }
            }
        }
        $totals['visits']++;
        $flush();
    }
    if ($dayIndex % 365 === 0) echo 'Seeding ' . $day->format('Y') . '…' . PHP_EOL;
}
$flush(true);
echo 'Seeded demo audience data: ' . json_encode($totals) . PHP_EOL;
echo 'Remove it any time with --remove.' . PHP_EOL;
