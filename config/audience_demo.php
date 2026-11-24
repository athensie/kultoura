<?php
/*
 * Demo audience analytics: five years of invented visits, visitors (new/returning), devices,
 * engaged time, listing views and intent clicks, for demonstrating the Audience dashboard.
 *
 * Every demo row is tagged so it can be removed exactly:
 *   page_views.visitor_id / visit_id, item_views.visit_id, user_activity_log.session_hash
 *   all start with KT_AUDIENCE_DEMO_PREFIX (and activity metadata has "demo":true).
 * Used by database/seed_audience_demo.php (CLI) and admin/demo_data_actions.php (Super Admin button).
 * Requires config/analytics.php to be loaded first.
 */
const KT_AUDIENCE_DEMO_PREFIX = 'de1100de1100';   // real random IDs start with this ~1 in 280 trillion times

function kt_audience_demo_present(mysqli $conn): bool
{
    analytics_ensure_visit_columns($conn);
    $like = KT_AUDIENCE_DEMO_PREFIX . '%';
    $stmt = $conn->prepare('SELECT 1 FROM page_views WHERE visitor_id LIKE ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $present = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $present;
}

function kt_audience_demo_remove(mysqli $conn): array
{
    analytics_ensure_visit_columns($conn);
    $like = KT_AUDIENCE_DEMO_PREFIX . '%';
    $counts = [];
    foreach ([
        'page_views' => 'DELETE FROM page_views WHERE visitor_id LIKE ?',
        'item_views' => 'DELETE FROM item_views WHERE visit_id LIKE ?',
        'user_activity_log' => 'DELETE FROM user_activity_log WHERE session_hash LIKE ?',
    ] as $table => $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) continue;
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $counts[$table] = $stmt->affected_rows;
        $stmt->close();
    }
    return $counts;
}

/** Seeds five years of demo data ending yesterday. Returns totals; throws if demo data already exists. */
function kt_audience_demo_seed(mysqli $conn, ?callable $progress = null): array
{
    if (!kt_activity_ensure_schema($conn)) throw new RuntimeException('Could not initialize the activity log table.');
    analytics_ensure_visit_columns($conn);
    if (kt_audience_demo_present($conn)) throw new RuntimeException('Demo audience data is already loaded. Remove it first to reload.');
    @set_time_limit(600);

    mt_srand(20261124);
    $rand = static fn(): float => mt_rand() / mt_getrandmax();
    $pick = static function (array $weights) use ($rand) {
        $roll = $rand() * array_sum($weights);
        foreach ($weights as $key => $weight) { if (($roll -= $weight) <= 0) return $key; }
        return array_key_last($weights);
    };
    $demoId = static fn(): string => KT_AUDIENCE_DEMO_PREFIX . bin2hex(random_bytes(10));

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
    // Typical engaged seconds per page (detail-heavy pages hold attention longer).
    $pageAttention = ['home' => 35, 'tourism' => 45, 'nature' => 80, 'restaurants' => 75, 'products' => 65, 'resort' => 85, 'fiestas' => 90, 'churches' => 70, 'people' => 95,
                      'industry' => 50, 'accommodation' => 70, 'services' => 40, 'banks' => 25, 'about' => 110, 'foryou' => 60, 'mostpopular' => 55, 'search' => 30, 'traveldiary' => 120];
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
    $visitorPool = [];
    $pageRows = $itemRows = $activityRows = [];
    $totals = ['visits' => 0, 'page_views' => 0, 'item_views' => 0, 'intent_actions' => 0];
    $flush = static function (bool $force = false) use ($conn, &$pageRows, &$itemRows, &$activityRows) {
        if ($pageRows && ($force || count($pageRows) >= 1000)) { $conn->query('INSERT INTO page_views (page, referrer_host, visitor_id, visit_id, device, engaged_seconds, viewed_at) VALUES ' . implode(',', $pageRows)); $pageRows = []; }
        if ($itemRows && ($force || count($itemRows) >= 1000)) { $conn->query('INSERT INTO item_views (item_type, item_id, visit_id, viewed_at) VALUES ' . implode(',', $itemRows)); $itemRows = []; }
        if ($activityRows && ($force || count($activityRows) >= 500)) { $conn->query('INSERT INTO user_activity_log (user_id, session_hash, activity_type, category, parent_category, item_type, item_id, content_name, element_name, page_section, metadata, activity_date) VALUES ' . implode(',', $activityRows)); $activityRows = []; }
    };

    for ($dayIndex = 0; $dayIndex < $totalDays; $dayIndex++) {
        $day = $start->modify("+{$dayIndex} days");
        $progressRatio = $dayIndex / max(1, $totalDays);
        $expected = 60 * (0.45 + 0.55 * $progressRatio) * $weekdayWeights[(int) $day->format('w')] * $monthWeights[(int) $day->format('n')];
        $visitsToday = max(0, (int) round($expected * (0.85 + 0.3 * $rand())));
        $mobileShare = 0.58 + 0.14 * $progressRatio;   // mobile grows over the years
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
            for ($p = 0; $p < $pageCount; $p++) {
                $page = $p === 0 && $rand() < 0.45 ? 'home' : $pick($pages);
                // Engaged time: skewed around the page's typical attention, 3 s to 15 min.
                $engaged = (int) max(3, min(900, round($pageAttention[$page] * exp(($rand() + $rand() + $rand() - 1.5) * 0.9))));
                $pageRows[] = sprintf("('%s','%s','%s','%s','%s',%d,'%s')", $page, $conn->real_escape_string($p === 0 ? $referrer : 'Direct'), $visitorId, $visitId, $device, $engaged, $time->format('Y-m-d H:i:s'));
                $totals['page_views']++;
                $time = $time->modify('+' . max(20, $engaged + mt_rand(5, 40)) . ' seconds');
            }
            if (($pageCount > 1 || $rand() < 0.25) && $listings) {
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
                            KT_AUDIENCE_DEMO_PREFIX . substr(hash('sha256', $visitId), 0, 52),
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
        if ($progress && $dayIndex % 365 === 0) $progress($day->format('Y'));
    }
    $flush(true);
    return $totals;
}
