<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'super admin'], true)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
$adminName = $_SESSION['username'] ?? 'Admin';
$adminRole = $_SESSION['role'] ?? 'Admin';
include '../config/dbmain.php';
include '../config/analytics.php';
date_default_timezone_set('Asia/Manila');
@$conn->query("SET time_zone = '+08:00'");
require_once '../config/promotions.php';
require_once '../config/admin_requests.php';
require_once '../config/ai_recommend.php';
$isSuperAdmin = kt_is_super_admin();
$pendingRequestCount = kt_requests_pending_count($conn);
$validPeriods = ['all', 'today', 'yesterday', '7days', '30days', 'week', 'month', 'lastmonth', 'year'];
$period = $_GET['period'] ?? 'month';
$dateFromInput = trim((string) ($_GET['date_from'] ?? ''));
$dateToInput = trim((string) ($_GET['date_to'] ?? ''));
$customRange = false;
$dateRangeError = '';
$dateFrom = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFromInput);
$dateTo = DateTimeImmutable::createFromFormat('!Y-m-d', $dateToInput);
$today = new DateTimeImmutable('today');
if ($dateFromInput !== '' || $dateToInput !== '') {
    $validFrom = $dateFrom && $dateFrom->format('Y-m-d') === $dateFromInput;
    $validTo = $dateTo && $dateTo->format('Y-m-d') === $dateToInput;
    if ($validFrom && $validTo && $dateFrom <= $dateTo && $dateTo <= $today) {
        $customRange = true;
        $period = 'custom';
    } else {
        $dateRangeError = 'Choose a valid date range ending today or earlier.';
    }
}
if (!$customRange && !in_array($period, $validPeriods, true)) {
    $period = 'month';
}
$periodLabels = ['all' => 'All Time', 'today' => 'Today', 'yesterday' => 'Yesterday', '7days' => 'Last 7 Days', '30days' => 'Last 30 Days', 'week' => 'This Week', 'month' => 'This Month', 'lastmonth' => 'Last Month', 'year' => 'This Year'];
$periodStart = new DateTime('today');
if ($customRange) {
    $periodStart = DateTime::createFromImmutable($dateFrom);
    $periodLabels['custom'] = $dateFrom->format('M j, Y') . ' – ' . $dateTo->format('M j, Y');
} else {
    switch ($period) {
        case 'all':   $periodStart = new DateTime('1000-01-01'); break;
        case 'yesterday': $periodStart = new DateTime('yesterday'); break;
        case '7days': $periodStart = new DateTime('today -6 days'); break;
        case '30days': $periodStart = new DateTime('today -29 days'); break;
        case 'week':  $periodStart = new DateTime('monday this week'); break;
        case 'month': $periodStart = new DateTime('first day of this month'); break;
        case 'lastmonth': $periodStart = new DateTime('first day of last month'); break;
        case 'year':  $periodStart = new DateTime('first day of january this year'); break;
    }
}
$periodEndExclusive = $customRange ? $dateTo->modify('+1 day') : ($period === 'yesterday' ? $today : ($period === 'lastmonth' ? new DateTimeImmutable('first day of this month') : $today->modify('+1 day')));
$periodDurationDays = max(1, (int) $periodStart->diff($periodEndExclusive)->days);
$previousStart = $periodStart->modify('-' . $periodDurationDays . ' days');
$previousEnd = $periodStart;
$periodStartSql = $periodStart->format('Y-m-d 00:00:00');
$periodEndExclusiveSql = $periodEndExclusive->format('Y-m-d 00:00:00');
$previousStartSql = $previousStart->format('Y-m-d 00:00:00');
$previousEndSql = $previousEnd->format('Y-m-d 00:00:00');
$activeUsers = analytics_active_users($conn, 5);
$registeredUsers = 0;
if ($res = $conn->query("SELECT COUNT(*) c FROM users")) $registeredUsers = (int) ($res->fetch_assoc()['c'] ?? 0);
$listedDestinations = 0;
if ($res = $conn->query("SELECT COUNT(*) c FROM destination WHERE status = 'active'")) {
    $listedDestinations = (int) ($res->fetch_assoc()['c'] ?? 0);
}
$tourismCatalogCounts = [
    'product' => 0, 'nature' => 0, 'industry' => 0, 'resort' => 0, 'church' => 0,
    'fiesta' => 0, 'person' => 0, 'service' => 0, 'restaurant' => 0, 'accommodation' => 0, 'bank' => 0,
];
$tourismCatalogQueries = [
    'product' => "SELECT COUNT(*) c FROM products WHERE status = 'active'",
    'nature' => "SELECT COUNT(*) c FROM destination WHERE status = 'active' AND category = 'nature'",
    'industry' => "SELECT COUNT(*) c FROM destination WHERE status = 'active' AND category = 'industry'",
    'resort' => "SELECT COUNT(*) c FROM destination WHERE status = 'active' AND category = 'resort'",
    'church' => "SELECT COUNT(*) c FROM destination WHERE status = 'active' AND category = 'church'",
    'fiesta' => "SELECT COUNT(*) c FROM fiestas WHERE status = 'active'",
    'person' => "SELECT COUNT(*) c FROM people WHERE status = 'active'",
    'service' => "SELECT COUNT(*) c FROM destination WHERE status = 'active' AND category = 'service'",
    'restaurant' => "SELECT COUNT(*) c FROM restaurants WHERE status = 'active'",
    'accommodation' => "SELECT COUNT(*) c FROM destination WHERE status = 'active' AND category = 'accommodation'",
    'bank' => "SELECT COUNT(*) c FROM destination WHERE status = 'active' AND category = 'bank'",
];
foreach ($tourismCatalogQueries as $key => $sql) {
    if ($res = $conn->query($sql)) {
        $tourismCatalogCounts[$key] = (int) ($res->fetch_assoc()['c'] ?? 0);
    }
}
$tourismCategoryDefinitions = [
    'product' => ['label' => 'Products of Malvar', 'type' => 'product'],
    'nature' => ['label' => 'Nature', 'type' => 'nature'],
    'industry' => ['label' => 'Industry Zone', 'type' => 'industry'],
    'resort' => ['label' => 'Resort', 'type' => 'resort'],
    'church' => ['label' => 'Churches', 'type' => 'church'],
    'fiesta' => ['label' => 'Fiestas', 'type' => 'fiesta'],
    'person' => ['label' => 'People of Malvar', 'type' => 'person'],
    'service' => ['label' => 'Other Services', 'type' => 'service'],
    'restaurant' => ['label' => 'Restaurants', 'type' => 'restaurant'],
    'accommodation' => ['label' => 'Accommodation', 'type' => 'accommodation'],
    'bank' => ['label' => 'Banks', 'type' => 'bank'],
];
$tourismCategoryAnalytics = [];
$itemReviewRatings = [];
$ratingDistribution = array_fill(1, 5, 0);
$reviewTableExists = $conn->query("SHOW TABLES LIKE 'item_reviews'");
if ($reviewTableExists && $reviewTableExists->num_rows > 0) {
    $distributionStmt = $conn->prepare("SELECT rating, COUNT(*) total FROM item_reviews WHERE status = 'visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at >= ? AND created_at < ? GROUP BY rating");
    if ($distributionStmt) {
        $distributionStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
        $distributionStmt->execute();
        foreach ($distributionStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $ratingRow) $ratingDistribution[(int) $ratingRow['rating']] = (int) $ratingRow['total'];
        $distributionStmt->close();
    }
    $reviewStmt = $conn->prepare("SELECT item_type, COUNT(*) total, COALESCE(AVG(rating), 0) average_rating FROM item_reviews WHERE status = 'visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at >= ? AND created_at < ? GROUP BY item_type");
    if ($reviewStmt) {
        $reviewStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
        $reviewStmt->execute();
        foreach ($reviewStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $reviewStat) $itemReviewRatings[$reviewStat['item_type']] = ['count' => (int) $reviewStat['total'], 'average' => (float) $reviewStat['average_rating']];
        $reviewStmt->close();
    }
}
$interactionTypes = ['click', 'search', 'generate', 'download', 'rating', 'review', 'comment', 'recommendation', 'map_interaction', 'language', 'other'];
foreach ($tourismCategoryDefinitions as $key => $definition) {
    $categoryItems = analytics_category_item_performance($conn, $definition['type'], $periodStartSql, $periodEndExclusiveSql);
    $rating = $itemReviewRatings[$definition['type']] ?? ['count' => 0, 'average' => 0.0];
    $activityCounts = [];
    foreach ($interactionTypes as $activityType) $activityCounts[$activityType] = kt_activity_count($conn, $activityType, $key, null, $periodStartSql, $periodEndExclusiveSql);
    $tourismCategoryAnalytics[$key] = [
        'label' => $definition['label'],
        'listings' => $tourismCatalogCounts[$key],
        'views' => array_sum(array_column($categoryItems, 'views')),
        'clicks' => $activityCounts['click'],
        'searches' => $activityCounts['search'],
        'search_selections' => array_sum(array_column($categoryItems, 'search_selections')),
        'interactions' => array_sum($activityCounts),
        'ratings' => $rating['count'],
        'average_rating' => $rating['average'],
        'top_items' => $categoryItems,
    ];
}
$tourismParentAnalytics = [];
foreach (kt_activity_parents() as $parentKey => $parentMeta) {
    $types = array_map(static fn($category) => $tourismCategoryDefinitions[$category]['type'], $parentMeta['categories']);
    $parentItems = analytics_top_items($conn, 10000, $periodStartSql, [], $types, $periodEndExclusiveSql);
    $parentAnalytics = ['label' => $parentMeta['label'], 'listings' => 0, 'views' => array_sum(array_column($parentItems, 'views')), 'clicks' => kt_activity_count($conn, 'click', null, $parentKey, $periodStartSql, $periodEndExclusiveSql), 'searches' => kt_activity_count($conn, 'search', null, $parentKey, $periodStartSql, $periodEndExclusiveSql), 'ratings' => 0, 'rating_total' => 0, 'top_items' => array_slice($parentItems, 0, 5)];
    foreach ($parentMeta['categories'] as $categoryKey) {
        $stats = $tourismCategoryAnalytics[$categoryKey];
        $parentAnalytics['listings'] += $stats['listings'];
        $parentAnalytics['ratings'] += $stats['ratings'];
        $parentAnalytics['rating_total'] += $stats['average_rating'] * $stats['ratings'];
    }
    $parentAnalytics['interactions'] = 0;
    foreach ($parentMeta['categories'] as $categoryKey) $parentAnalytics['interactions'] += $tourismCategoryAnalytics[$categoryKey]['interactions'];
    $parentAnalytics['average_rating'] = $parentAnalytics['ratings'] > 0 ? $parentAnalytics['rating_total'] / $parentAnalytics['ratings'] : 0.0;
    unset($parentAnalytics['rating_total']);
    $tourismParentAnalytics[$parentKey] = $parentAnalytics;
}
$overallActivityTypes = ['click', 'search', 'generate', 'download', 'rating', 'review', 'comment', 'recommendation', 'map_interaction', 'language', 'other'];
$overallActivityCounts = [];
foreach ($overallActivityTypes as $activityType) $overallActivityCounts[$activityType] = kt_activity_count($conn, $activityType, null, null, $periodStartSql, $periodEndExclusiveSql);
$totalClicks = $overallActivityCounts['click'];
$totalSearches = $overallActivityCounts['search'];
$totalInteractions = array_sum($overallActivityCounts);
$totalListedTourismEntries = array_sum($tourismCatalogCounts);
$totalCategories = count($tourismCategoryDefinitions);
$totalContentViews = array_sum(array_column($tourismCategoryAnalytics, 'views'));
$allCategoryRanks = array_values($tourismCategoryAnalytics);
$mostViewedCategory = null;
$leastViewedCategory = null;
$mostClickedCategory = null;
$mostSearchedCategory = null;
$highestRatedCategory = null;
$lowestRatedCategory = null;
foreach ($allCategoryRanks as $categoryRank) {
    if ($mostViewedCategory === null || $categoryRank['views'] > $mostViewedCategory['views']) $mostViewedCategory = $categoryRank;
    if ($leastViewedCategory === null || $categoryRank['views'] < $leastViewedCategory['views']) $leastViewedCategory = $categoryRank;
    if ($mostClickedCategory === null || $categoryRank['clicks'] > $mostClickedCategory['clicks']) $mostClickedCategory = $categoryRank;
    if ($mostSearchedCategory === null || $categoryRank['searches'] > $mostSearchedCategory['searches']) $mostSearchedCategory = $categoryRank;
    if ($categoryRank['ratings'] > 0 && ($highestRatedCategory === null || $categoryRank['average_rating'] > $highestRatedCategory['average_rating'])) $highestRatedCategory = $categoryRank;
    if ($categoryRank['ratings'] > 0 && ($lowestRatedCategory === null || $categoryRank['average_rating'] < $lowestRatedCategory['average_rating'])) $lowestRatedCategory = $categoryRank;
}
$overallRatingCount = 0;
$overallRatingTotal = 0.0;
foreach ($tourismCategoryAnalytics as $categoryStats) {
    $overallRatingCount += $categoryStats['ratings'];
    $overallRatingTotal += $categoryStats['average_rating'] * $categoryStats['ratings'];
}
$overallAverageRating = $overallRatingCount > 0 ? $overallRatingTotal / $overallRatingCount : 0.0;
$destinationRatingCount = 0;
$destinationRatingTotal = 0.0;
foreach (['nature', 'industry', 'resort', 'church'] as $destinationType) {
    $destinationRating = $itemReviewRatings[$destinationType] ?? ['count' => 0, 'average' => 0.0];
    $destinationRatingCount += $destinationRating['count'];
    $destinationRatingTotal += $destinationRating['average'] * $destinationRating['count'];
}
$averageDestinationRating = $destinationRatingCount > 0 ? $destinationRatingTotal / $destinationRatingCount : 0.0;
$categoryRatingDistribution = [];
$categoryRecentReviews = [];
if ($reviewTableExists && $reviewTableExists->num_rows > 0) {
    $categoryRatingStmt = $conn->prepare("SELECT item_type, rating, COUNT(*) total FROM item_reviews WHERE status='visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at>=? AND created_at<? GROUP BY item_type,rating");
    if ($categoryRatingStmt) {
        $categoryRatingStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
        $categoryRatingStmt->execute();
        foreach ($categoryRatingStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $categoryRatingDistribution[$row['item_type']][(int) $row['rating']] = (int) $row['total'];
        $categoryRatingStmt->close();
    }
    $recentReviewStmt = $conn->prepare("SELECT item_type,item_id,rating,display_name,review_text,created_at FROM item_reviews WHERE status='visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at>=? AND created_at<? ORDER BY created_at DESC LIMIT 300");
    if ($recentReviewStmt) {
        $recentReviewStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
        $recentReviewStmt->execute();
        foreach ($recentReviewStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $review) {
            $reviewType = (string) $review['item_type'];
            if (!isset($categoryRecentReviews[$reviewType])) $categoryRecentReviews[$reviewType] = [];
            if (count($categoryRecentReviews[$reviewType]) < 5) {
                $categoryRecentReviews[$reviewType][] = $review;
            }
        }
        $recentReviewStmt->close();
    }
}
$topCategory = '—';
$categoryCounts = [];
if ($res = $conn->query("SELECT category, COUNT(*) c FROM products WHERE category IS NOT NULL AND category <> '' GROUP BY category")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $categoryCounts[$row['category']] = ($categoryCounts[$row['category']] ?? 0) + (int) $row['c'];
    }
}
if ($res = $conn->query("SELECT category, COUNT(*) c FROM restaurants WHERE category IS NOT NULL AND category <> '' GROUP BY category")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $categoryCounts[$row['category']] = ($categoryCounts[$row['category']] ?? 0) + (int) $row['c'];
    }
}
if (!empty($categoryCounts)) {
    arsort($categoryCounts);
    $topCategory = array_key_first($categoryCounts);
}
$topCategoryCount = $categoryCounts[$topCategory] ?? 0;
$totalPageViews = analytics_total_views($conn, $periodStartSql, $periodEndExclusiveSql);
$previousPageViews = 0;
$previousSearches = 0;
$previousInteractions = 0;
$previousContentViews = 0;
$previousDestinationViews = 0;
$previousCultureViews = 0;
$destinationViews = $tourismParentAnalytics['tourism_attractions']['views'] ?? 0;
$cultureViews = $tourismParentAnalytics['culture_heritage']['views'] ?? 0;
$newTrackedVisitors = 0;
$returningTrackedVisitors = 0;
$visitorStmt = $conn->prepare("SELECT SUM(first_seen >= ?) new_visitors, SUM(first_seen < ?) returning_visitors FROM (SELECT session_hash, MIN(activity_date) first_seen FROM user_activity_log WHERE activity_date < ? GROUP BY session_hash) sessions WHERE first_seen >= ?");
if ($visitorStmt) {
    $visitorStmt->bind_param('ssss', $periodStartSql, $periodStartSql, $periodEndExclusiveSql, $periodStartSql);
    $visitorStmt->execute();
    $visitorCounts = $visitorStmt->get_result()->fetch_assoc() ?: [];
    $newTrackedVisitors = (int) ($visitorCounts['new_visitors'] ?? 0);
    $returningTrackedVisitors = (int) ($visitorCounts['returning_visitors'] ?? 0);
    $visitorStmt->close();
}
if ($period !== 'all') {
    $previousPageViews = analytics_total_views($conn, $previousStartSql, $previousEndSql);
    foreach ($overallActivityTypes as $activityType) {
        $count = kt_activity_count($conn, $activityType, null, null, $previousStartSql, $previousEndSql);
        $previousInteractions += $count;
        if ($activityType === 'search') $previousSearches = $count;
    }
    $previousItems = analytics_top_items($conn, 10000, $previousStartSql, [], [], $previousEndSql);
    foreach ($previousItems as $previousItem) {
        if (in_array($previousItem['type'], ['nature', 'industry', 'resort', 'church'], true)) $previousDestinationViews += $previousItem['views'];
        if (in_array($previousItem['type'], ['product', 'fiesta', 'person'], true)) $previousCultureViews += $previousItem['views'];
    }
    $previousContentViews = $previousCultureViews;
}
$periodChange = static function (int $current, int $previous): string {
    if ($previous <= 0) return $current > 0 ? 'New activity vs. previous period' : 'No activity in either period';
    $change = round((($current - $previous) / $previous) * 100, 1);
    return ($change > 0 ? '+' : '') . $change . '% vs. previous period';
};
$topPages = analytics_page_breakdown($conn, $periodStartSql, 5, $periodEndExclusiveSql);
$topPagesMax = !empty($topPages) ? max(array_column($topPages, 'views')) : 0;
foreach ($topPages as &$tp) {
    $tp['pct'] = $topPagesMax > 0 ? (int) round($tp['views'] / $topPagesMax * 100) : 0;
}
unset($tp);
$itemTypeLabels = [
    'nature' => 'Nature', 'resort' => 'Resort', 'industry' => 'Industry',
    'accommodation' => 'Accommodation', 'bank' => 'Bank', 'service' => 'Service', 'church' => 'Church',
    'product' => 'Product', 'restaurant' => 'Restaurant', 'fiesta' => 'Fiesta', 'person' => 'People of Malvar',
];
$topItems = analytics_top_items($conn, 6, $periodStartSql, [], [], $periodEndExclusiveSql);
$topPeople = analytics_top_items($conn, 6, $periodStartSql, [], ['person'], $periodEndExclusiveSql);
$conversionItems = analytics_conversion_top_items($conn, 8, 3);
$promotedEffectiveness = kt_promotion_effectiveness($conn);
$promotedImpressions = array_sum(array_column($promotedEffectiveness, 'impressions'));
$promotedClicks = array_sum(array_column($promotedEffectiveness, 'clicks'));
$promotedCtr = $promotedImpressions > 0 ? round(($promotedClicks / $promotedImpressions) * 100, 1) : 0;
$promotedChartItems = array_slice($promotedEffectiveness, 0, 8);
$topSearches = analytics_top_searches($conn, 6, $periodStartSql, $periodEndExclusiveSql);
$zeroResultSearches = analytics_zero_result_searches($conn, 6, $periodStartSql, $periodEndExclusiveSql);
$trending = [];
$recentUnion = [];
if ($res = $conn->query("SELECT destination_name AS name, category AS sub, created_at FROM destination WHERE created_at >= '$periodStartSql' AND created_at < '$periodEndExclusiveSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Destination · ' . ucfirst($r['sub']), 'created_at' => $r['created_at']];
    }
}
if ($res = $conn->query("SELECT product_name AS name, category AS sub, created_at FROM products WHERE created_at >= '$periodStartSql' AND created_at < '$periodEndExclusiveSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Product' . (!empty($r['sub']) ? ' · ' . $r['sub'] : ''), 'created_at' => $r['created_at']];
    }
}
if ($res = $conn->query("SELECT restaurant_name AS name, category AS sub, created_at FROM restaurants WHERE created_at >= '$periodStartSql' AND created_at < '$periodEndExclusiveSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Restaurant' . (!empty($r['sub']) ? ' · ' . $r['sub'] : ''), 'created_at' => $r['created_at']];
    }
}
if ($res = $conn->query("SELECT fiesta_name AS name, location AS sub, created_at FROM fiestas WHERE created_at >= '$periodStartSql' AND created_at < '$periodEndExclusiveSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Fiesta' . (!empty($r['sub']) ? ' · ' . $r['sub'] : ''), 'created_at' => $r['created_at']];
    }
}
usort($recentUnion, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
$recentUnion = array_slice($recentUnion, 0, 6);
foreach ($recentUnion as $r) {
    $trending[] = [
        'name'   => $r['name'],
        'sub'    => $r['sub'],
        'visits' => date('M j', strtotime($r['created_at'])),
    ];
}
$categoryShare = [];
$destCount = 0;    if ($r = $conn->query("SELECT COUNT(*) c FROM destination"))  $destCount    = (int) ($r->fetch_assoc()['c'] ?? 0);
$prodCount = 0;    if ($r = $conn->query("SELECT COUNT(*) c FROM products"))     $prodCount    = (int) ($r->fetch_assoc()['c'] ?? 0);
$restCount = 0;    if ($r = $conn->query("SELECT COUNT(*) c FROM restaurants"))  $restCount    = (int) ($r->fetch_assoc()['c'] ?? 0);
$fiestaCount = 0;  if ($r = $conn->query("SELECT COUNT(*) c FROM fiestas"))      $fiestaCount  = (int) ($r->fetch_assoc()['c'] ?? 0);
$contentTotal = $destCount + $prodCount + $restCount + $fiestaCount;
if ($contentTotal > 0) {
    $categoryShare = [
        ['label' => 'Destinations', 'pct' => (int) round($destCount   / $contentTotal * 100), 'color' => '#C9572A'],
        ['label' => 'Products',     'pct' => (int) round($prodCount   / $contentTotal * 100), 'color' => '#6b8f47'],
        ['label' => 'Restaurants',  'pct' => (int) round($restCount   / $contentTotal * 100), 'color' => '#4a90e2'],
        ['label' => 'Fiestas',      'pct' => (int) round($fiestaCount / $contentTotal * 100), 'color' => '#C8A96E'],
    ];
}
$donutStops = [];
$cursor = 0;
foreach ($categoryShare as $c) {
    $start = $cursor;
    $cursor += $c['pct'];
    $donutStops[] = "{$c['color']} {$start}% {$cursor}%";
}
$donutGradient = !empty($donutStops) ? implode(', ', $donutStops) : '#333 0% 100%';
$monthlyVisitors = analytics_monthly_trend($conn, 6);
$hasMonthlyData = array_sum(array_column($monthlyVisitors, 'value')) > 0;
$weekdayLabels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$weekdayCounts = array_fill(1, 7, 0);
$weekdayStmt = $conn->prepare("SELECT DAYOFWEEK(viewed_at) weekday, COUNT(*) views FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= ? AND viewed_at < ? GROUP BY DAYOFWEEK(viewed_at)");
if ($weekdayStmt) {
    $weekdayStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
    $weekdayStmt->execute();
    foreach ($weekdayStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $weekdayRow) {
        $weekdayCounts[(int) $weekdayRow['weekday']] = (int) $weekdayRow['views'];
    }
    $weekdayStmt->close();
}
$weekdayMax = max(1, max($weekdayCounts));
$hourlyCounts = array_fill(0, 24, 0);
$hourlyStmt = $conn->prepare("SELECT HOUR(viewed_at) visit_hour, COUNT(*) views FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= ? AND viewed_at < ? GROUP BY HOUR(viewed_at)");
if ($hourlyStmt) {
    $hourlyStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
    $hourlyStmt->execute();
    foreach ($hourlyStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $hourlyRow) {
        $hourlyCounts[(int) $hourlyRow['visit_hour']] = (int) $hourlyRow['views'];
    }
    $hourlyStmt->close();
}
$hourlyMax = max(1, max($hourlyCounts));
$hasHourlyData = array_sum($hourlyCounts) > 0;
$signupStmt = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') signup_month, COUNT(*) total FROM users WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
$signupCounts = [];
if ($signupStmt) {
    $signupStmt->execute();
    foreach ($signupStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $signupRow) {
        $signupCounts[$signupRow['signup_month']] = (int) $signupRow['total'];
    }
    $signupStmt->close();
}
$signupTrend = [];
$signupMax = 1;
for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
    $monthDate = new DateTimeImmutable('first day of this month -' . $monthOffset . ' months');
    $monthKey = $monthDate->format('Y-m');
    $signupTrend[] = ['label' => $monthDate->format('M'), 'value' => $signupCounts[$monthKey] ?? 0];
    $signupMax = max($signupMax, $signupCounts[$monthKey] ?? 0);
}
$hasSignupData = array_sum(array_column($signupTrend, 'value')) > 0;
$trafficTrend = [];
$dailyVisitors = [];
$trafficBucketCounts = [];
$trafficLabel = $periodLabels[$period];
$trafficSubtitle = 'Daily page views for ' . strtolower($periodLabels[$period]);
$customMonthly = $customRange && $dateFrom->diff($dateTo)->days > 62;
$customYearly = $customRange && $dateFrom->diff($dateTo)->days > 730;
if ($period === 'all') {
    $trafficQuery = $conn->prepare("SELECT DATE_FORMAT(viewed_at, '%Y-%m') bucket, COUNT(*) views FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= ? AND viewed_at < ? GROUP BY DATE_FORMAT(viewed_at, '%Y-%m')");
    $trafficSubtitle = 'Monthly page views across all available history';
} elseif ($period === 'today') {
    $trafficQuery = $conn->prepare("SELECT HOUR(viewed_at) bucket, COUNT(*) views FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= ? AND viewed_at < ? GROUP BY HOUR(viewed_at)");
    $trafficSubtitle = 'Hourly page views for today';
} elseif ($customYearly) {
    $trafficQuery = $conn->prepare("SELECT YEAR(viewed_at) bucket, COUNT(*) views FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= ? AND viewed_at < ? GROUP BY YEAR(viewed_at)");
    $trafficSubtitle = 'Yearly page views for the selected date range';
} elseif ($period === 'year' || $customMonthly) {
    $trafficQuery = $conn->prepare("SELECT DATE_FORMAT(viewed_at, '%Y-%m') bucket, COUNT(*) views FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= ? AND viewed_at < ? GROUP BY DATE_FORMAT(viewed_at, '%Y-%m')");
    $trafficSubtitle = $customMonthly ? 'Monthly page views for the selected date range' : 'Monthly page views for ' . date('Y');
} else {
    $trafficQuery = $conn->prepare("SELECT DATE(viewed_at) bucket, COUNT(*) views FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= ? AND viewed_at < ? GROUP BY DATE(viewed_at)");
}
if ($trafficQuery) {
    $trafficQuery->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
    $trafficQuery->execute();
    foreach ($trafficQuery->get_result()->fetch_all(MYSQLI_ASSOC) as $trafficRow) {
        $trafficBucketCounts[$trafficRow['bucket']] = (int) $trafficRow['views'];
    }
    $trafficQuery->close();
}
if ($period === 'all') {
    $lastMonth = new DateTimeImmutable('first day of this month');
    $minimumStartMonth = $lastMonth->modify('-59 months');
    $firstDataMonth = !empty($trafficBucketCounts)
        ? new DateTimeImmutable(min(array_keys($trafficBucketCounts)) . '-01')
        : $minimumStartMonth;
    $firstMonth = $firstDataMonth < $minimumStartMonth ? $firstDataMonth : $minimumStartMonth;
    for ($month = $firstMonth; $month <= $lastMonth; $month = $month->modify('+1 month')) {
        $monthKey = $month->format('Y-m');
        $point = ['label' => $month->format('M Y'), 'value' => $trafficBucketCounts[$monthKey] ?? 0];
        $trafficTrend[] = $point;
        $dailyVisitors[] = $point;
    }
} elseif ($period === 'today') {
    for ($hour = 0; $hour < 24; $hour++) {
        $point = ['label' => date('g A', strtotime(sprintf('%02d:00', $hour))), 'value' => $trafficBucketCounts[(string) $hour] ?? 0];
        $trafficTrend[] = $point;
        $dailyVisitors[] = $point;
    }
} elseif ($period === 'year') {
    for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
        $monthDate = (new DateTimeImmutable('first day of this month'))->modify('-' . $monthOffset . ' months');
        $monthKey = $monthDate->format('Y-m');
        $point = ['label' => $monthDate->format('M'), 'value' => $trafficBucketCounts[$monthKey] ?? 0];
        $trafficTrend[] = $point;
        $dailyVisitors[] = $point;
    }
} elseif ($customMonthly && !$customYearly) {
    $month = $dateFrom->modify('first day of this month');
    $lastMonth = $dateTo->modify('first day of this month');
    for (; $month <= $lastMonth; $month = $month->modify('+1 month')) {
        $monthKey = $month->format('Y-m');
        $point = ['label' => $month->format('M Y'), 'value' => $trafficBucketCounts[$monthKey] ?? 0];
        $trafficTrend[] = $point;
        $dailyVisitors[] = $point;
    }
} elseif ($customYearly) {
    for ($year = (int) $dateFrom->format('Y'); $year <= (int) $dateTo->format('Y'); $year++) {
        $point = ['label' => (string) $year, 'value' => $trafficBucketCounts[(string) $year] ?? 0];
        $trafficTrend[] = $point;
        $dailyVisitors[] = $point;
    }
} else {
    $rangeEnd = $customRange ? $dateTo : ($period === 'yesterday' ? $periodEndExclusive->modify('-1 day') : ($period === 'lastmonth' ? $periodEndExclusive->modify('-1 day') : new DateTimeImmutable('today')));
    for ($date = clone $periodStart; $date <= $rangeEnd; $date->modify('+1 day')) {
        $dateKey = $date->format('Y-m-d');
        $point = ['label' => $date->format('M j'), 'value' => $trafficBucketCounts[$dateKey] ?? 0];
        $trafficTrend[] = $point;
        $dailyVisitors[] = $point;
    }
}
$dailyVisitorsMax = max(1, max(array_column($dailyVisitors, 'value')));
$hasDailyData = array_sum(array_column($dailyVisitors, 'value')) > 0;
$trafficSources = analytics_traffic_sources($conn, $periodStartSql, 5, $periodEndExclusiveSql);
$newSignups = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM users WHERE created_at >= ? AND created_at < ?");
$stmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
$stmt->execute();
$newSignups = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$adminLogins = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM admins WHERE last_login >= ? AND last_login < ?");
$stmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
$stmt->execute();
$adminLogins = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$projectedNextMonth = null;
$projectedGrowthPct = null;
$monthsWithData = array_filter($monthlyVisitors, fn($m) => $m['value'] > 0);
$totalRecentViews = array_sum(array_column($monthlyVisitors, 'value'));
if (count($monthsWithData) >= 3 && $totalRecentViews >= 10) {
    $n = count($monthlyVisitors);
    $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
    foreach (array_values($monthlyVisitors) as $i => $m) {
        $sumX  += $i;
        $sumY  += $m['value'];
        $sumXY += $i * $m['value'];
        $sumXX += $i * $i;
    }
    $denominator = ($n * $sumXX) - ($sumX * $sumX);
    $slope = $denominator != 0 ? ((($n * $sumXY) - ($sumX * $sumY)) / $denominator) : 0;
    $intercept = ($sumY - ($slope * $sumX)) / $n;
    $projectedNextMonth = max(0, (int) round($intercept + ($slope * $n)));
    $latest = end($monthlyVisitors)['value'];
    $baseline = $latest > 0 ? $latest : ($sumY / $n);
    $projectedGrowthPct = $baseline > 0
        ? (int) round((($projectedNextMonth - $baseline) / $baseline) * 100)
        : 0;
    $projectedGrowthPct = max(-90, min(200, $projectedGrowthPct));
}
$forecastIsAI = false;
$forecastInsight = null;
if ($projectedNextMonth !== null) {
    $aiForecast = ai_forecast_pageviews($conn, $monthlyVisitors);
    if ($aiForecast !== null) {
        $projectedNextMonth = $aiForecast['projected_next_month'];
        $projectedGrowthPct = $aiForecast['growth_pct'];
        $forecastInsight = $aiForecast['insight'];
        $forecastIsAI = true;
    }
}
$monthlySummary = ai_monthly_summary($conn, [
    'total_page_views'        => $totalPageViews,
    'active_users_now'        => $activeUsers,
    'listed_destinations'     => $listedDestinations,
    'top_category'            => $topCategory,
    'top_viewed_places'       => array_map(fn($i) => $i['name'] . ' (' . $i['views'] . ' views)', array_slice($topItems, 0, 3)),
    'best_converting_place'   => !empty($conversionItems) ? $conversionItems[0]['name'] . ' (' . $conversionItems[0]['favoritePct'] . '% of viewers favorited it)' : null,
    'projected_next_month_growth_pct' => $projectedGrowthPct,
    'top_searches'            => array_map(fn($s) => $s['query'], array_slice($topSearches, 0, 3)),
]);
$trendingPages = [];
$thisWeekStart = (new DateTime('monday this week'))->format('Y-m-d 00:00:00');
$lastWeekStart = (new DateTime('monday last week'))->format('Y-m-d 00:00:00');
$thisWeekCounts = [];
$lastWeekCounts = [];
if ($res = $conn->query("SELECT page, COUNT(*) c FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= '$thisWeekStart' GROUP BY page")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) $thisWeekCounts[$r['page']] = (int) $r['c'];
}
if ($res = $conn->query("SELECT page, COUNT(*) c FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at >= '$lastWeekStart' AND viewed_at < '$thisWeekStart' GROUP BY page")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) $lastWeekCounts[$r['page']] = (int) $r['c'];
}
$pageLabels = analytics_page_labels();
$MIN_MOMENTUM_VIEWS = 5;
foreach ($thisWeekCounts as $page => $count) {
    $prevCount = $lastWeekCounts[$page] ?? 0;
    if (($count + $prevCount) < $MIN_MOMENTUM_VIEWS) {
        continue;
    }
    if ($prevCount > 0) {
        $growth = (int) round((($count - $prevCount) / $prevCount) * 100);
    } else {
        $growth = 100;
    }
    if ($growth > 0) {
        $trendingPages[] = ['label' => $pageLabels[$page] ?? ucfirst($page), 'growth' => min($growth, 300), 'views' => $count];
    }
}
usort($trendingPages, fn($a, $b) => $b['growth'] <=> $a['growth']);
$trendingPages = array_slice($trendingPages, 0, 4);
$recommendations = [];
if ($activeUsers === 0) {
    $recommendations[] = ['icon' => 'users', 'level' => 'info',
        'text' => 'No visitors are active right now — consider timing social posts or announcements for higher-traffic hours.'];
}
if ($totalPageViews === 0) {
    $recommendations[] = ['icon' => 'eye-off', 'level' => 'warn',
        'text' => 'No page views logged ' . strtolower($periodLabels[$period]) . ' yet — share the site link to start building traffic data.'];
} elseif (!empty($topPages) && $topPagesMax > 0) {
    $lowest = end($topPages);
    if ($lowest['views'] > 0 && $lowest['views'] < $topPagesMax * 0.2) {
        $recommendations[] = ['icon' => 'megaphone', 'level' => 'info',
            'text' => '"' . $lowest['label'] . '" is getting comparatively little traffic — consider featuring it on the homepage or in an announcement.'];
    }
}
if ($listedDestinations < 5) {
    $recommendations[] = ['icon' => 'map-pin', 'level' => 'warn',
        'text' => 'Only ' . $listedDestinations . ' destination(s) are listed — adding more gives tourists more reasons to keep exploring the site.'];
}
if ($topCategoryCount === 0) {
    $recommendations[] = ['icon' => 'utensils', 'level' => 'warn',
        'text' => 'No Food & Dining listings yet — this is often the most-browsed category on a tourism site.'];
}
foreach ($categoryShare as $c) {
    if ($c['pct'] >= 60) {
        $recommendations[] = ['icon' => 'pie-chart', 'level' => 'info',
            'text' => $c['label'] . ' makes up ' . $c['pct'] . '% of all listings — balancing content across categories could broaden appeal.'];
    }
}
if (!empty($trendingPages)) {
    $top = $trendingPages[0];
    $recommendations[] = ['icon' => 'trending-up', 'level' => 'good',
        'text' => '"' . $top['label'] . '" is trending up ' . $top['growth'] . '% week-over-week — consider spotlighting it while interest is high.'];
}
if (empty($recommendations)) {
    $recommendations[] = ['icon' => 'check-circle', 'level' => 'good',
        'text' => 'No urgent issues detected right now — content mix and traffic look healthy for the current data.'];
}
$recommendationsAreAI = false;
$aiActions = ai_recommended_actions($conn, [
    'active_users_now'      => $activeUsers,
    'total_page_views'      => $totalPageViews,
    'listed_destinations'   => $listedDestinations,
    'top_category'          => $topCategory,
    'top_category_count'    => $topCategoryCount,
    'category_share'        => $categoryShare,
    'lowest_viewed_page'    => !empty($topPages) ? end($topPages) : null,
    'trending_pages'        => $trendingPages,
    'top_viewed_places'     => array_map(fn($i) => ['name' => $i['name'], 'views' => $i['views']], array_slice($topItems, 0, 5)),
    'conversion_leaders'    => array_map(fn($c) => ['name' => $c['name'], 'views' => $c['views'], 'favorite_pct' => $c['favoritePct'], 'diary_pct' => $c['diaryPct']], array_slice($conversionItems, 0, 5)),
    'zero_result_searches'  => array_map(fn($s) => $s['query'], $zeroResultSearches),
    'projected_next_month_growth_pct' => $projectedGrowthPct,
]);
if ($aiActions !== null) {
    $recommendations = $aiActions;
    $recommendationsAreAI = true;
}
$chartInsights = ai_chart_insights($conn, [
    'most_viewed_pages' => ['title' => 'Most Viewed Pages', 'data' => $topPages],
    'recently_added' => ['title' => 'Recently Added', 'data' => array_map(fn($t) => ['name' => $t['name'], 'sub' => $t['sub']], $trending)],
    'most_viewed_places' => ['title' => 'Most Viewed Content', 'data' => array_map(fn($i) => ['name' => $i['name'], 'type' => $itemTypeLabels[$i['type']] ?? ucfirst($i['type']), 'views' => $i['views']], $topItems)],
    'most_viewed_people' => ['title' => 'Most Viewed People', 'data' => array_map(fn($i) => ['name' => $i['name'], 'views' => $i['views']], $topPeople)],
    'conversion' => ['title' => 'Favorites & Travel Diary Conversion', 'data' => array_map(fn($c) => ['name' => $c['name'], 'views' => $c['views'], 'favorite_pct' => $c['favoritePct'], 'diary_pct' => $c['diaryPct']], $conversionItems)],
    'top_searches' => ['title' => 'Top Searches', 'data' => $topSearches],
    'zero_result_searches' => ['title' => 'Zero-Result Searches', 'data' => $zeroResultSearches],
    'category_engagement' => ['title' => 'Content Engagement by Category', 'data' => $categoryShare],
    'traffic_sources' => ['title' => 'Traffic Sources', 'data' => $trafficSources],
    'monthly_trend' => ['title' => 'Monthly Visitor Trend', 'data' => array_map(fn($m) => ['month' => $m['label'], 'views' => $m['value']], $monthlyVisitors)],
    'user_activity' => ['title' => 'User Activity Summary', 'data' => ['new_signups' => $newSignups, 'admin_logins' => $adminLogins, 'active_now' => $activeUsers]],
]);
$chartInsightsAreAI = !empty($chartInsights);
function kt_render_chart_insight(array $chartInsights, string $key): void
{
    if (!isset($chartInsights[$key])) {
        return;
    }
    $insight = $chartInsights[$key];
    ?>
    <div class="chart-ai-insight">
        <div class="chart-ai-row"><i data-lucide="sparkles" class="lucide"></i><span><strong>Interpretation:</strong> <?php echo htmlspecialchars($insight['interpretation']); ?></span></div>
        <div class="chart-ai-row"><i data-lucide="wrench" class="lucide"></i><span><strong>Solution:</strong> <?php echo htmlspecialchars($insight['solution']); ?></span></div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics – KULTOURA Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/admindashboard.css?v=20261118-2">
    <link rel="stylesheet" href="../assets/css/admin-theme.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <style>
        .period-selector .period-btn { text-decoration: none; }
        .period-selector .period-btn,
        .period-selector .print-analytics-btn {
            align-items: center; display: inline-flex; justify-content: center; min-height: 40px;
            padding: 0 15px; border: 1px solid rgba(var(--overlay-rgb), .12); border-radius: 999px;
            font: 500 .84rem 'DM Sans', sans-serif; white-space: nowrap;
        }
        .analytics-controls { display: flex; flex-direction: column; align-items: stretch; gap: 10px; width: 100%; min-width: 0; }
        .period-selector { justify-content: flex-start; flex-wrap: wrap; align-self: stretch; gap: 6px; max-width: 100%; }
        .page-header { align-items: flex-start; flex-direction: column; }
        .period-selector .period-btn { background: transparent; color: rgba(var(--cream-rgb), .72); }
        .period-selector .period-btn.active,
        .period-selector .period-btn:hover,
        .period-selector .print-analytics-btn {
            background: var(--clay); border-color: var(--clay); color: #152318;
        }
        .period-selector .period-btn.active { font-weight: 600; }
        .period-selector .print-analytics-btn { gap: 7px; cursor: pointer; font-weight: 700; }
        .period-selector .print-analytics-btn:hover { filter: brightness(1.06); }
        .period-selector .print-analytics-btn i { width: 18px; height: 18px; }
        .date-range-form { display: flex; align-items: flex-end; justify-content: flex-start; flex-wrap: wrap; gap: 8px; margin: 0; }
        .date-range-field { display: grid; gap: 3px; color: rgba(var(--cream-rgb), .62); font: 500 .68rem 'DM Sans', sans-serif; }
        .date-range-field input { width: 138px; height: 34px; padding: 0 8px; border: 1px solid rgba(var(--overlay-rgb), .14); border-radius: 8px; background: rgba(var(--overlay-rgb), .04); color: var(--cream); font: 500 .75rem 'DM Sans', sans-serif; color-scheme: dark; }
        :root[data-theme="light"] .date-range-field input { color-scheme: light; }
        .date-range-submit { height: 34px; padding: 0 12px; border: 1px solid var(--clay); border-radius: 999px; background: var(--clay); color: #152318; cursor: pointer; font: 700 .75rem 'DM Sans', sans-serif; }
        .date-range-clear { padding: 0 5px 8px; color: rgba(var(--cream-rgb), .58); font: 500 .7rem 'DM Sans', sans-serif; text-decoration: none; white-space: nowrap; }
        .date-range-clear:hover { color: var(--cream); text-decoration: underline; }
        .date-range-error { color: #e88b76; font: 500 .75rem 'DM Sans', sans-serif; text-align: right; }
        @media (max-width: 1100px) {
            .analytics-controls { align-items: flex-end; }
            .period-selector { flex-wrap: wrap; }
        }
        @media (max-width: 720px) {
            .analytics-controls { align-items: flex-start; width: 100%; }
            .period-selector { justify-content: flex-start; }
            .date-range-form { justify-content: flex-start; flex-wrap: wrap; }
        }
        .tourism-category-overview { margin: 0 0 24px; }
        .tourism-category-heading { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin: 0 0 12px; }
        .tourism-category-heading h2 { margin: 2px 0 0; color: var(--cream); font: 700 1.35rem 'Playfair Display', serif; }
        .tourism-category-heading p { margin: 0; color: rgba(var(--cream-rgb), .55); font: 400 .78rem 'DM Sans', sans-serif; }
        .tourism-category-all { margin: 0 0 12px; padding: 8px 15px; border: 1px solid rgba(var(--overlay-rgb), .12); border-radius: 999px; background: transparent; color: rgba(var(--cream-rgb), .72); cursor: pointer; font: 600 .78rem 'DM Sans', sans-serif; }
        .tourism-category-all:hover, .tourism-category-all.is-active { border-color: var(--clay); background: var(--clay); color: #fff; }
        .tourism-category-groups { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .tourism-category-folder { overflow: hidden; border: 1px solid rgba(var(--overlay-rgb), .1); border-radius: 14px; background: rgba(var(--overlay-rgb), .025); }
        .tourism-category-folder summary { display: flex; align-items: center; gap: 9px; padding: 15px 16px; cursor: pointer; list-style: none; color: var(--cream); font: 600 .9rem 'DM Sans', sans-serif; }
        .tourism-category-folder summary::-webkit-details-marker { display: none; }
        .tourism-category-folder summary > .lucide { width: 17px; height: 17px; color: var(--sage); }
        .tourism-category-total { margin-left: auto; color: var(--gold); font-size: .75rem; }
        .tourism-category-items { padding: 0 16px 12px 42px; }
        .tourism-category-items > button { width: 100%; display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border: 0; border-top: 1px solid rgba(var(--overlay-rgb), .07); background: transparent; color: rgba(var(--cream-rgb), .68); cursor: pointer; text-align: left; font: 400 .8rem 'DM Sans', sans-serif; }
        .tourism-category-items > button:hover, .tourism-category-items > button.is-active { color: var(--gold); }
        .tourism-category-items strong { color: var(--cream); font-weight: 600; }
        .category-analytics-panel { margin: 4px 0 24px; padding: 20px; border: 1px solid rgba(var(--overlay-rgb), .1); border-radius: 14px; background: rgba(var(--overlay-rgb), .025); }
        .category-analytics-panel.is-hidden { display: none; }
        .traffic-overview.is-hidden { display: none; }
        .category-analytics-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 15px; }
        .category-analytics-header h3 { margin: 0; color: var(--cream); font: 700 1.15rem 'Playfair Display', serif; }
        .category-analytics-header > span { color: rgba(var(--cream-rgb), .55); font: 500 .75rem 'DM Sans', sans-serif; }
        .category-analytics-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 15px; }
        .category-analytics-kpis > div { display: grid; gap: 4px; padding: 15px; border: 1px solid rgba(var(--overlay-rgb), .08); border-radius: 10px; }
        .category-analytics-kpis strong { color: var(--gold); font: 700 1.4rem 'DM Sans', sans-serif; }
        .category-analytics-kpis span, .category-analytics-top > p { color: rgba(var(--cream-rgb), .58); font: 400 .75rem 'DM Sans', sans-serif; }
        .category-analytics-top h4 { margin: 0 0 8px; color: var(--cream); font: 600 .88rem 'DM Sans', sans-serif; }
        .category-analytics-top > div { display: flex; justify-content: space-between; gap: 14px; padding: 10px 0; border-top: 1px solid rgba(var(--overlay-rgb), .07); color: rgba(var(--cream-rgb), .72); font: 400 .78rem 'DM Sans', sans-serif; }
        .category-analytics-top > div b, .category-analytics-top > div strong { color: var(--gold); }
        .analytics-export-link { margin-left: 8px; color: var(--gold); font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
        .category-comparison { margin: 8px 0 24px; padding: 20px; border: 1px solid rgba(var(--overlay-rgb), .1); border-radius: 14px; background: rgba(var(--overlay-rgb), .025); overflow-x: auto; }
        .category-comparison h3 { margin: 0 0 14px; color: var(--cream); font: 700 1.1rem 'Playfair Display', serif; }
        .category-comparison table { width: 100%; border-collapse: collapse; min-width: 650px; }
        .category-comparison th, .category-comparison td { padding: 10px 12px; border-bottom: 1px solid rgba(var(--overlay-rgb), .07); text-align: right; color: rgba(var(--cream-rgb), .72); font: 400 .78rem 'DM Sans', sans-serif; }
        .category-comparison th { color: var(--gold); font-weight: 700; }
        .category-comparison th:first-child, .category-comparison td:first-child { text-align: left; }
        .category-comparison tr:last-child td { border-bottom: 0; }
        .category-comparison ul { margin: 0; padding-left: 20px; color: rgba(var(--cream-rgb), .72); font: 400 .82rem/1.65 'DM Sans', sans-serif; }
        .category-comparison li + li { margin-top: 7px; }
        .kpi-grid[hidden] { display: none !important; }
        .category-chart-grid { display:grid; grid-template-columns:minmax(0,2fr) minmax(240px,1fr); gap:16px; margin:16px 0 22px; }
        .category-chart-box { min-width:0; padding:18px; border:1px solid rgba(var(--overlay-rgb),.1); border-radius:13px; background:rgba(var(--overlay-rgb),.025); }
        .category-chart-box h3 { margin:0 0 14px; color:var(--cream); font:700 1rem 'Playfair Display',serif; }
        .category-chart-canvas { position:relative; height:285px; }
        .category-chart-canvas.donut { height:260px; }
        .category-chart-grid.is-hidden, .category-comparison.is-hidden { display:none; }
        .tourism-overview-kpis.is-hidden { display:none; }
        .category-item-table-wrap { max-height:540px; overflow:auto; border:1px solid rgba(var(--overlay-rgb),.08); border-radius:10px; }
        .category-item-table { width:100%; border-collapse:collapse; min-width:580px; }
        .category-item-table th,.category-item-table td { padding:10px 12px; border-bottom:1px solid rgba(var(--overlay-rgb),.07); color:rgba(var(--cream-rgb),.72); font:400 .78rem 'DM Sans',sans-serif; text-align:right; }
        .category-item-table th { position:sticky; top:0; z-index:1; background:#172319; color:var(--gold); font-weight:700; }
        .category-item-table th:nth-child(2),.category-item-table td:nth-child(2) { text-align:left; }
        .category-item-table tr:last-child td { border-bottom:0; }
        @media(max-width:850px) { .category-chart-grid { grid-template-columns:1fr; } }
        @media (max-width: 900px) { .tourism-category-groups { grid-template-columns: 1fr; } }
        @media (max-width: 600px) { .tourism-category-heading { align-items: flex-start; flex-direction: column; gap: 5px; } }
        .dash-wrapper { max-width: 1540px; padding: 28px clamp(18px, 3vw, 46px) 64px; }
        .main-content { min-width: 0; width: calc(100% - 220px); }
        .dash-wrapper { width: 100%; min-width: 0; box-sizing: border-box; }
        .analytics-summary, .traffic-overview, .tourism-category-overview, .analytics-section { width: 100%; min-width: 0; }
        .page-header { margin-bottom: 18px; }
        .page-header h1 { font-size: clamp(1.8rem, 3vw, 2.65rem); }
        .page-header p { max-width: 680px; }
        .analytics-primary-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 0 0 12px; }
        .analytics-primary-kpis .kpi-card { min-height: 112px; padding: 16px 18px; border-radius: 12px; background: #142217; border-color: rgba(var(--overlay-rgb),.13); }
        .analytics-primary-kpis .kpi-card:first-child { grid-column: auto; }
        .analytics-primary-kpis .kpi-icon { display: none; }
        .analytics-primary-kpis .kpi-num { font: 700 1.65rem/1.05 'Playfair Display', serif; }
        .analytics-primary-kpis .kpi-label { margin-top: 7px; color: rgba(var(--cream-rgb),.82); font-size: .68rem; letter-spacing: .045em; }
        .analytics-primary-kpis .kpi-change { margin-top: 6px; color: rgba(var(--cream-rgb),.64); font-size: .69rem; line-height: 1.4; }
        .analytics-secondary-kpis { grid-template-columns: repeat(2, minmax(150px, 220px)); gap: 8px; margin: 0 0 18px; }
        .analytics-secondary-kpis .kpi-card { min-height: 62px; padding: 10px 13px; border-radius: 9px; }
        .analytics-secondary-kpis .kpi-icon, .analytics-secondary-kpis .kpi-change { display: none; }
        .analytics-secondary-kpis .kpi-num { font: 700 1.05rem/1.1 'DM Sans', sans-serif; color: var(--cream); }
        .analytics-secondary-kpis .kpi-label { margin-top: 4px; color: rgba(var(--cream-rgb),.72); font-size: .62rem; letter-spacing: .05em; }
        .analytics-summary .kpi-card::before { display: none; }
        .analytics-summary .kpi-card { min-width: 0; }
        .charts-2col { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .charts-2col > *, .category-chart-grid > * { min-width: 0; }
        .analytics-category-select-label { display: block; margin: 2px 0 5px; color: rgba(var(--cream-rgb),.58); font: 600 .65rem 'DM Sans',sans-serif; letter-spacing: .08em; text-transform: uppercase; }
        .analytics-category-select { min-width: 190px; height: 36px; padding: 0 12px; border: 1px solid rgba(var(--overlay-rgb),.16); border-radius: 8px; background: #17271c; color: var(--cream); font: 500 .75rem 'DM Sans',sans-serif; }
        :root[data-theme="light"] .analytics-category-select { background: #fff; color: #2c1a0e; }
        .tourism-category-heading { align-items: flex-end; margin-bottom: 10px; }
        .tourism-category-heading p { margin-left: auto; }
        .category-chart-grid { grid-template-columns: minmax(0, 1.5fr) minmax(250px, 1fr); gap: 10px; margin: 10px 0 14px; }
        .category-chart-box { padding: 13px 15px; border-radius: 10px; }
        .category-chart-box h3 { margin-bottom: 8px; font-size: .84rem; }
        .category-chart-canvas { height: 220px; }
        .category-chart-canvas.donut { height: 220px; }
        .analytics-filter-bar { margin: 4px 0 18px; }
        .analytics-filter-pill { padding: 6px 13px; font-size: .69rem; }
        .chart-box { padding: 19px 21px; border-radius: 11px; }
        .chart-box h4 { margin-bottom: 14px; }
        .charts-2col { gap: 12px; margin-bottom: 12px; }
        .analytics-report-export { background: rgba(157,187,134,.16) !important; border-color: rgba(157,187,134,.25) !important; color: var(--cream) !important; }
        .traffic-overview { margin-bottom: 16px; padding: 18px 20px 14px; border-radius: 11px; }
        .traffic-overview-header h2 { font-size: 1.05rem; }
        .traffic-chart-wrap { height: 245px; }
        .content-performance-track { height: 4px; margin-top: 8px; border-radius: 99px; background: rgba(var(--overlay-rgb),.08); overflow: hidden; }
        .content-performance-track span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg,var(--clay),var(--gold)); }
        .activity-type-chart { gap: 8px; }
        .activity-type-chart .bar-name { min-width: 150px; font-size: .7rem; }
        .activity-type-chart .bar-track { height: 6px; }
        .activity-type-chart .bar-pct { font-size: .68rem; }
        .alabel-desc { margin: 20px 0 10px; padding: 0; background: none; color: var(--cream); font-size: .72rem; letter-spacing: .04em; }
        @media (max-width: 900px) {
            .analytics-primary-kpis { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .analytics-secondary-kpis { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .main-content { width: 100%; }
            .charts-2col { grid-template-columns: minmax(0, 1fr); }
            .tourism-category-heading { align-items: flex-start; flex-direction: column; }
            .tourism-category-heading p { margin-left: 0; }
        }
        @media (max-width: 560px) {
            .analytics-secondary-kpis { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .analytics-primary-kpis .kpi-card { padding: 12px; }
            .analytics-primary-kpis .kpi-num { font-size: 1.35rem; }
            .analytics-category-select { width: 100%; }
            .analytics-controls { width: 100%; }
            .period-selector { gap: 5px; }
            .period-selector .period-btn, .period-selector .print-analytics-btn { min-height: 34px; padding: 0 10px; font-size: .69rem; }
        }
        @media print {
            body, .main-content, .dash-wrapper { background: #fff !important; color: #111 !important; }
            .sidebar, .hamburger-btn, .admin-site-switch-row, .analytics-controls, .analytics-filter-bar { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .dash-wrapper { max-width: none !important; padding: 0 !important; }
            .page-header, .chart-box, .kpi-card, .analytics-section { background: #fff !important; color: #111 !important; box-shadow: none !important; break-inside: avoid; }
            .page-header p, .chart-box p, .chart-box h2, .chart-box h3, .chart-box h4, .kpi-label, .kpi-change, .alabel-desc { color: #222 !important; }
            .chart-box, .kpi-card { border: 1px solid #bbb !important; }
            canvas { max-width: 100% !important; }
        }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar"><i data-lucide="chevrons-left" class="lucide"></i></button>
    <div class="sidebar-logo">KulToura</div>
    <div class="sidebar-tagline">Malvar, Batangas</div>
    <div class="sidebar-section">Overview</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindashboard.php" class="active"><span class="nav-icon"><i data-lucide="bar-chart-2" class="lucide"></i></span> Analytics</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminactivitylog.php"><span class="nav-icon"><i data-lucide="activity" class="lucide"></i></span> User Activity Log</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminreviews.php"><span class="nav-icon"><i data-lucide="star" class="lucide"></i></span> Ratings &amp; Reviews</a></li>
    </ul>
    <div class="sidebar-section">Content</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindestinations.php"><span class="nav-icon"><i data-lucide="map-pin" class="lucide"></i></span> Destinations</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpromote.php"><span class="nav-icon"><i data-lucide="sparkles" class="lucide"></i></span> Promote Content</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminfoodanddining.php"><span class="nav-icon"><i data-lucide="utensils" class="lucide"></i></span> Food &amp; Dining</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/admineventandfiesta.php"><span class="nav-icon"><i data-lucide="calendar-heart" class="lucide"></i></span> Events &amp; Fiesta</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpeople.php"><span class="nav-icon"><i data-lucide="users-round" class="lucide"></i></span> People of Malvar</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsitecontent.php"><span class="nav-icon"><i data-lucide="image" class="lucide"></i></span> Site Content</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminhomepage.php"><span class="nav-icon"><i data-lucide="layout-grid" class="lucide"></i></span> Homepage Content</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpagewidgets.php"><span class="nav-icon"><i data-lucide="layout-template" class="lucide"></i></span> Page Widgets</a></li>
    </ul>
    <div class="sidebar-section">Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminannouncements.php"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
        <?php if ($isSuperAdmin): ?>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminrequests.php"><span class="nav-icon"><i data-lucide="inbox" class="lucide"></i></span> Requests<?php if ($pendingRequestCount > 0): ?> <span style="background:var(--gold,#C8A96E);color:#1a1812;font-size:.62rem;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:4px;"><?php echo $pendingRequestCount; ?></span><?php endif; ?></a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminarchive.php"><span class="nav-icon"><i data-lucide="archive" class="lucide"></i></span> Archive</a></li>
        <?php endif; ?>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsettings.php"><span class="nav-icon"><i data-lucide="settings" class="lucide"></i></span> Settings</a></li>
    </ul>
    <div class="sidebar-footer">
        <div class="admin-avatar">
            <div class="avatar-circle"><i data-lucide="user" class="lucide" style="width:1.1rem;height:1.1rem;"></i></div>
            <div>
                <div class="avatar-name"><?php echo htmlspecialchars($adminName); ?></div>
                <div class="avatar-role"><?php echo htmlspecialchars($adminRole); ?></div>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/auth/auth.php?action=logout" class="logout-link">
            <i data-lucide="log-out" class="lucide" style="width:.9rem;height:.9rem;"></i> Log Out
        </a>
    </div>
</aside>
<button class="hamburger-btn" onclick="toggleSidebar()"><i data-lucide="menu" class="lucide"></i></button>
<div class="main-content">
    <div class="dash-wrapper">
        <div class="admin-site-switch-row"><a class="admin-site-switch" href="<?php echo BASE_URL; ?>/index.php"><i data-lucide="external-link" class="lucide"></i> View user site</a></div>
        <div class="page-header">
            <div>
                <div class="section-label">Tourism Insights</div>
                <h1>Tourism <em>analytics</em></h1>
                <p>Explore visitor activity, content performance, and tourism engagement in Malvar, Batangas.</p>
            </div>
            <div class="analytics-controls">
            <div class="period-selector">
                <a href="?period=all" class="period-btn<?php echo $period === 'all' ? ' active' : ''; ?>">All</a>
                <a href="?period=today" class="period-btn<?php echo $period === 'today' ? ' active' : ''; ?>">Today</a>
                <a href="?period=yesterday" class="period-btn<?php echo $period === 'yesterday' ? ' active' : ''; ?>">Yesterday</a>
                <a href="?period=7days" class="period-btn<?php echo $period === '7days' ? ' active' : ''; ?>">Last 7 Days</a>
                <a href="?period=30days" class="period-btn<?php echo $period === '30days' ? ' active' : ''; ?>">Last 30 Days</a>
                <a href="?period=week" class="period-btn<?php echo $period === 'week' ? ' active' : ''; ?>">This Week</a>
                <a href="?period=month" class="period-btn<?php echo $period === 'month' ? ' active' : ''; ?>">This Month</a>
                <a href="?period=lastmonth" class="period-btn<?php echo $period === 'lastmonth' ? ' active' : ''; ?>">Last Month</a>
                <a href="?period=year" class="period-btn<?php echo $period === 'year' ? ' active' : ''; ?>">This Year</a>
                <a class="period-btn analytics-report-export" href="<?php echo BASE_URL; ?>/admin/analytics_export.php?period=<?php echo urlencode($period); ?>&amp;date_from=<?php echo urlencode($dateFromInput); ?>&amp;date_to=<?php echo urlencode($dateToInput); ?>">Export report CSV</a>
                <button type="button" class="period-btn analytics-theme-toggle" id="analyticsThemeToggle">Light mode</button>
                <button type="button" class="print-analytics-btn" onclick="window.print()"><i data-lucide="printer" aria-hidden="true"></i>Print Analytics</button>
            </div>
            <form class="date-range-form" method="get" action="admindashboard.php" aria-label="Filter analytics by date range">
                <input type="hidden" name="period" value="custom">
                <label class="date-range-field">From
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFromInput); ?>" max="<?php echo $today->format('Y-m-d'); ?>" required>
                </label>
                <label class="date-range-field">To
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateToInput); ?>" max="<?php echo $today->format('Y-m-d'); ?>" required>
                </label>
                <button type="submit" class="date-range-submit">Apply</button>
                <a class="date-range-clear" href="?period=month">Clear range</a>
            </form>
            <?php if ($dateRangeError !== ''): ?><div class="date-range-error" role="alert"><?php echo htmlspecialchars($dateRangeError); ?></div><?php endif; ?>
            </div>
        </div>
        <section class="analytics-summary" aria-label="Analytics summary">
            <div class="kpi-grid analytics-primary-kpis tourism-overview-kpis">
                <?php $primaryKpis = [
                    ['map-pin', 'Destination views', $destinationViews, $periodChange($destinationViews, $previousDestinationViews)],
                    ['scroll-text', 'Cultural content views', $cultureViews, $periodChange($cultureViews, $previousCultureViews)],
                    ['search', 'Search submissions', $totalSearches, $periodChange($totalSearches, $previousSearches)],
                    ['map', 'Generated itineraries', $overallActivityCounts['generate'], 'Recorded generation events'],
                    ['activity', 'User interactions', $totalInteractions, $periodChange($totalInteractions, $previousInteractions)],
                    ['star', 'Average destination rating', $destinationRatingCount ? number_format($averageDestinationRating, 1) . ' / 5' : '—', number_format($destinationRatingCount) . ' actual ratings'],
                    ['user-round', 'Registered users', $registeredUsers, 'All visitor accounts'],
                    ['users-round', 'New / returning visitors', $newTrackedVisitors . ' / ' . $returningTrackedVisitors, 'Distinct tracked sessions; activity logging only'],
                ]; foreach ($primaryKpis as $index => [$icon, $label, $value, $detail]): ?>
                <div class="kpi-card accent-<?= ['clay','green','gold','blue'][$index % 4] ?>"><div class="kpi-icon"><i data-lucide="<?= htmlspecialchars($icon) ?>" class="lucide"></i></div><div class="kpi-num"><?= htmlspecialchars((string) (is_numeric($value) ? number_format((float) $value) : $value)) ?></div><div class="kpi-label"><?= htmlspecialchars($label) ?></div><div class="kpi-change"><?= htmlspecialchars($detail) ?></div></div>
                <?php endforeach; ?>
            </div>
            <div class="kpi-grid analytics-secondary-kpis tourism-overview-kpis">
                <?php $summaryKpis = [
                    ['Active tourism listings', $totalListedTourismEntries], ['Content categories', $totalCategories],
                ]; foreach ($summaryKpis as [$label, $value]): ?>
                <div class="kpi-card"><div class="kpi-num"><?= number_format($value) ?></div><div class="kpi-label"><?= htmlspecialchars($label) ?></div></div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="traffic-overview chart-box animate" aria-labelledby="trafficOverviewTitle">
            <div class="traffic-overview-header">
                <div><span class="section-label">Audience activity</span><h2 id="trafficOverviewTitle">Traffic overview</h2><p><?php echo htmlspecialchars($trafficSubtitle); ?></p></div>
            </div>
            <div class="traffic-chart-summary"><strong><?php echo number_format($totalPageViews); ?></strong><span>page views &mdash; <?php echo htmlspecialchars(strtolower($trafficLabel)); ?></span></div>
            <div class="traffic-chart-wrap"><canvas id="trafficOverviewChart" aria-label="Daily page views trend chart" role="img"></canvas></div>
            <script type="application/json" id="trafficOverviewData" data-initial-window="<?php echo $period === 'all' ? '12' : '0'; ?>"><?php echo json_encode($trafficTrend, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        </section>
        <section class="tourism-category-overview" aria-labelledby="tourismCategoryTitle">
            <div class="tourism-category-heading">
                <div><span class="section-label">Content catalog</span><h2 id="tourismCategoryTitle">Tourism Categories</h2></div>
                <p>Choose a category to see its analytics for <?php echo htmlspecialchars($periodLabels[$period]); ?>. <a href="<?php echo BASE_URL; ?>/admin/analytics_export.php?period=<?php echo urlencode($period); ?>&amp;date_from=<?php echo urlencode($dateFromInput); ?>&amp;date_to=<?php echo urlencode($dateToInput); ?>" class="analytics-export-link">Export category report (CSV)</a></p>
            </div>
            <label class="analytics-category-select-label" for="analyticsCategorySelect">Category</label>
            <select id="analyticsCategorySelect" class="analytics-category-select" aria-label="Choose analytics category">
                <option value="all">All categories</option>
                <?php foreach (kt_activity_parents() as $parentKey => $parentMeta): ?>
                <optgroup label="<?= htmlspecialchars($parentMeta['label']) ?>">
                    <option value="<?= htmlspecialchars($parentKey) ?>">All <?= htmlspecialchars($parentMeta['label']) ?></option>
                    <?php foreach ($parentMeta['categories'] as $categoryKey): ?><option value="<?= htmlspecialchars($categoryKey) ?>"><?= htmlspecialchars($tourismCategoryDefinitions[$categoryKey]['label']) ?> (<?= number_format($tourismCatalogCounts[$categoryKey]) ?>)</option><?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="category-chart-grid" id="categorySummaryVisuals">
                <div class="category-chart-box"><h3>Views, Clicks, and Searches by Category</h3><div class="category-chart-canvas"><canvas id="tourismCategoryComparison" aria-label="Tourism category comparison chart" role="img"></canvas></div></div>
                <div class="category-chart-box"><h3>Engagement Share</h3><div class="category-chart-canvas donut"><canvas id="tourismCategoryEngagement" aria-label="Tourism category engagement share chart" role="img"></canvas></div></div>
                <div class="category-chart-box"><h3>Visitor Rating Distribution</h3><div class="category-chart-canvas donut"><canvas id="tourismRatingDistribution" aria-label="Actual visitor rating distribution chart" role="img"></canvas></div></div>
                <script type="application/json" id="tourismCategoryChartData"><?php echo json_encode(['keys' => array_keys($tourismCategoryAnalytics), 'labels' => array_column(array_values($tourismCategoryAnalytics), 'label'), 'views' => array_column(array_values($tourismCategoryAnalytics), 'views'), 'clicks' => array_column(array_values($tourismCategoryAnalytics), 'clicks'), 'searches' => array_column(array_values($tourismCategoryAnalytics), 'searches'), 'engagement' => array_map(static fn($stats) => $stats['views'] + $stats['clicks'] + $stats['searches'], array_values($tourismCategoryAnalytics)), 'ratings' => array_values($ratingDistribution)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
            </div>
            <?php $allTourismAnalytics = $tourismParentAnalytics + $tourismCategoryAnalytics; foreach ($allTourismAnalytics as $key => $categoryAnalytics): ?>
            <div class="category-analytics-panel is-hidden" data-category-panel="<?php echo htmlspecialchars($key); ?>">
                <div class="category-analytics-header"><h3><?php echo htmlspecialchars($categoryAnalytics['label']); ?> Analytics</h3><span><?php echo htmlspecialchars($periodLabels[$period]); ?> · <a class="analytics-export-link" href="<?php echo BASE_URL; ?>/admin/analytics_export.php?<?php echo isset($tourismCategoryDefinitions[$key]) ? 'category=' . urlencode($key) : 'parent=' . urlencode($key); ?>&amp;period=<?php echo urlencode($period); ?>&amp;date_from=<?php echo urlencode($dateFromInput); ?>&amp;date_to=<?php echo urlencode($dateToInput); ?>">Export CSV</a></span></div>
                <div class="category-analytics-kpis category-analytics-kpis-wide">
                    <div><strong><?php echo number_format($categoryAnalytics['listings']); ?></strong><span>Active listings</span></div>
                    <div><strong><?php echo number_format($categoryAnalytics['views']); ?></strong><span>Views in selected period</span></div>
                    <div><strong><?php echo number_format($categoryAnalytics['clicks']); ?></strong><span>Clicks recorded</span></div>
                    <div><strong><?php echo number_format($categoryAnalytics['searches']); ?></strong><span>Category searches</span></div>
                    <div><strong><?php echo number_format($categoryAnalytics['interactions']); ?></strong><span>Total interactions</span></div>
                    <div><strong><?php echo number_format($categoryAnalytics['ratings']); ?></strong><span>Submitted ratings</span></div>
                    <div><strong><?php echo $categoryAnalytics['ratings'] > 0 ? number_format($categoryAnalytics['average_rating'], 1) . ' / 5' : '—'; ?></strong><span>Average rating</span></div>
                </div>
                <div class="category-analytics-top">
                    <h4>Listing Performance · highest views first</h4>
                    <?php if (empty($categoryAnalytics['top_items'])): ?>
                        <p>No active listings are available for this category.</p>
                    <?php else: ?>
                        <div class="category-item-table-wrap"><table class="category-item-table"><thead><tr><th>Rank</th><th>Listing</th><th>Views</th><th>Clicks</th><th>Search result selections</th><th>Ratings</th><th>Average</th></tr></thead><tbody>
                        <?php foreach ($categoryAnalytics['top_items'] as $rank => $item): ?>
                        <tr><td><?php echo $rank + 1; ?></td><td><?php echo htmlspecialchars($item['name']); ?></td><td><?php echo number_format($item['views']); ?></td><td><?php echo number_format($item['clicks'] ?? 0); ?></td><td><?php echo number_format($item['search_selections'] ?? 0); ?></td><td><?php echo number_format($item['ratings'] ?? 0); ?></td><td><?php echo !empty($item['ratings']) ? number_format($item['average_rating'], 1) . ' / 5' : '—'; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
                <?php if (isset($tourismCategoryDefinitions[$key])): $itemType = $tourismCategoryDefinitions[$key]['type']; $ratingCountsForCategory = $categoryRatingDistribution[$itemType] ?? []; ?>
                <div class="category-analytics-top">
                    <h4>Entry rankings</h4>
                    <?php $rankings = [
                        'Most viewed' => $categoryAnalytics['top_items'],
                        'Least viewed' => array_slice(array_reverse($categoryAnalytics['top_items']), 0, 5),
                    ];
                    $clickRankings = $categoryAnalytics['top_items']; usort($clickRankings, static fn($a, $b) => ($b['clicks'] <=> $a['clicks']) ?: ($b['views'] <=> $a['views'])); $rankings['Most clicked'] = array_slice($clickRankings, 0, 5);
                    $searchRankings = $categoryAnalytics['top_items']; usort($searchRankings, static fn($a, $b) => ($b['search_selections'] <=> $a['search_selections']) ?: ($b['views'] <=> $a['views'])); $rankings['Most selected from search results'] = array_slice(array_values(array_filter($searchRankings, static fn($item) => $item['search_selections'] > 0)), 0, 5);
                    $ratedRankings = array_values(array_filter($categoryAnalytics['top_items'], static fn($item) => $item['ratings'] > 0));
                    $highRated = $ratedRankings; usort($highRated, static fn($a, $b) => ($b['average_rating'] <=> $a['average_rating']) ?: ($b['ratings'] <=> $a['ratings'])); $rankings['Highest rated'] = array_slice($highRated, 0, 5);
                    $lowRated = $ratedRankings; usort($lowRated, static fn($a, $b) => ($a['average_rating'] <=> $b['average_rating']) ?: ($b['ratings'] <=> $a['ratings'])); $rankings['Lowest rated'] = array_slice($lowRated, 0, 5);
                    foreach ($rankings as $rankingLabel => $rankingItems): ?><h5><?= htmlspecialchars($rankingLabel) ?></h5><?php if (!$rankingItems): ?><p>No matching entries recorded in this period.</p><?php else: ?><ol><?php foreach ($rankingItems as $rankedItem): ?><li><?= htmlspecialchars($rankedItem['name']) ?> — <?= number_format($rankedItem['views']) ?> views, <?= number_format($rankedItem['clicks']) ?> clicks, <?= number_format($rankedItem['search_selections']) ?> search-result selections<?= $rankedItem['ratings'] ? ', ' . number_format($rankedItem['average_rating'], 1) . ' / 5 (' . number_format($rankedItem['ratings']) . ' reviews)' : '' ?></li><?php endforeach; ?></ol><?php endif; endforeach; ?>
                </div>
                <div class="category-analytics-top">
                    <h4>Ratings and recent visitor feedback</h4>
                    <div class="category-item-table-wrap"><table class="category-item-table"><thead><tr><th>Rating</th><?php foreach ([1,2,3,4,5] as $star): ?><th><?= $star ?> star</th><?php endforeach; ?></tr></thead><tbody><tr><th>Reviews</th><?php foreach ([1,2,3,4,5] as $star): ?><td><?= number_format($ratingCountsForCategory[$star] ?? 0) ?></td><?php endforeach; ?></tr></tbody></table></div>
                    <?php $recentForCategory = $categoryRecentReviews[$itemType] ?? []; if (!$recentForCategory): ?><p>No public visitor reviews in this period.</p><?php else: ?><div class="category-item-table-wrap"><table class="category-item-table"><thead><tr><th>Content</th><th>Visitor</th><th>Rating</th><th>Review</th><th>Date</th></tr></thead><tbody><?php foreach ($recentForCategory as $review): $matchedItem = null; foreach ($categoryAnalytics['top_items'] as $candidate) { if ((int) $candidate['id'] === (int) $review['item_id']) { $matchedItem = $candidate; break; } } ?><tr><td><?= htmlspecialchars($matchedItem['name'] ?? ('Item #' . (int) $review['item_id'])) ?></td><td><?= htmlspecialchars($review['display_name'] ?: 'Visitor') ?></td><td><?= (int) $review['rating'] ?>/5</td><td><?= htmlspecialchars(mb_strimwidth((string) $review['review_text'], 0, 180, '…')) ?></td><td><?= htmlspecialchars(date('Y-m-d', strtotime($review['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="category-comparison">
                <h3>Category Performance Comparison</h3>
                <table><thead><tr><th>Category</th><th>Active Listings</th><th>Views</th><th>Clicks</th><th>Searches</th><th>Interactions</th><th>Reviews</th><th>Avg. Rating</th><th>Top viewed listings</th></tr></thead><tbody>
                <?php $comparisonRows = array_values($tourismCategoryAnalytics); usort($comparisonRows, static fn($a, $b) => $b['views'] <=> $a['views']); foreach ($comparisonRows as $stats): ?>
                <?php $leaders = array_slice($stats['top_items'], 0, 3); $leaderText = implode(', ', array_map(static fn($item) => $item['name'] . ' (' . number_format($item['views']) . ')', $leaders)); ?>
                <tr><td><?= htmlspecialchars($stats['label']) ?></td><td><?= number_format($stats['listings']) ?></td><td><?= number_format($stats['views']) ?></td><td><?= number_format($stats['clicks']) ?></td><td><?= number_format($stats['searches']) ?></td><td><?= number_format($stats['interactions']) ?></td><td><?= number_format($stats['ratings']) ?></td><td><?= $stats['ratings'] ? number_format($stats['average_rating'], 1) . ' / 5' : '—' ?></td><td><?= htmlspecialchars($leaderText !== '' ? $leaderText : 'No active listings') ?></td></tr>
                <?php endforeach; ?></tbody></table>
            </div>
            <div class="category-comparison">
                <h3>Data-Based Promotion and Improvement Opportunities</h3>
                <?php
                $averageCategoryViews = $totalContentViews / max(1, count($tourismCategoryAnalytics));
                $opportunities = [];
                foreach ($tourismCategoryAnalytics as $stats) {
                    if ($stats['listings'] > 0 && $stats['views'] < $averageCategoryViews) $opportunities[] = $stats['label'] . ' has ' . number_format($stats['views']) . ' content views, below the category average of ' . number_format((int) round($averageCategoryViews)) . '. Consider giving its listings more visibility; low views alone do not indicate poor quality.';
                    if ($stats['ratings'] >= 3 && $stats['average_rating'] < 3.5) $opportunities[] = $stats['label'] . ' averages ' . number_format($stats['average_rating'], 1) . ' / 5 across ' . number_format($stats['ratings']) . ' actual ratings. Review visitor feedback and investigate possible service or content issues.';
                    if ($stats['ratings'] >= 3 && $stats['average_rating'] >= 4.5 && $stats['views'] < $averageCategoryViews) $opportunities[] = $stats['label'] . ' has strong visitor ratings but below-average views. Consider featuring it in a promotion.';
                }
                if (!$opportunities) $opportunities[] = 'There is not enough recorded engagement or rating data in this period to recommend a category action.';
                ?>
                <ul><?php foreach (array_slice($opportunities, 0, 6) as $opportunity): ?><li><?= htmlspecialchars($opportunity) ?></li><?php endforeach; ?></ul>
            </div>
        </section>
        <div class="analytics-filter-bar" id="analyticsFilterBar">
            <button type="button" class="analytics-filter-pill is-active" data-filter="all">All</button>
            <button type="button" class="analytics-filter-pill" data-filter="descriptive">Descriptive</button>
            <button type="button" class="analytics-filter-pill" data-filter="predictive">Predictive</button>
            <button type="button" class="analytics-filter-pill" data-filter="prescriptive">Prescriptive</button>
        </div>
        <div class="analytics-section" data-category="descriptive">
        <div class="alabel-desc">Trending &amp; Highly Visited Destinations <span class="analytics-tag">Descriptive</span><?php if ($chartInsightsAreAI): ?> <span class="analytics-tag tag-ai">AI-Powered</span><?php endif; ?></div>
        <div class="kpi-grid animate" hidden>
            <div class="kpi-card accent-clay">
                <div class="kpi-icon"><i data-lucide="users" class="lucide"></i></div>
                <div class="kpi-num"><?php echo htmlspecialchars((string) $activeUsers); ?></div>
                <div class="kpi-label">Active Users</div>
                <div class="kpi-change"><?php echo $activeUsers > 0 ? 'Online in the last 5 minutes' : 'No one online right now'; ?></div>
            </div>
            <div class="kpi-card accent-green">
                <div class="kpi-icon"><i data-lucide="map-pin" class="lucide"></i></div>
                <div class="kpi-num"><?php echo htmlspecialchars((string) $listedDestinations); ?></div>
                <div class="kpi-label">Listed Destinations</div>
                <div class="kpi-change"><?php echo $listedDestinations > 0 ? 'Currently live on the site' : 'No destinations yet'; ?></div>
            </div>
            <div class="kpi-card accent-gold">
                <div class="kpi-icon"><i data-lucide="utensils" class="lucide"></i></div>
                <div class="kpi-num" style="font-size:1.6rem;"><?php echo htmlspecialchars($topCategory); ?></div>
                <div class="kpi-label">Most Popular Category</div>
                <div class="kpi-change"><?php echo $topCategoryCount > 0 ? $topCategoryCount . ' Food & Dining listings' : 'No Food & Dining listings yet'; ?></div>
            </div>
            <div class="kpi-card accent-blue">
                <div class="kpi-icon"><i data-lucide="eye" class="lucide"></i></div>
                <div class="kpi-num"><?php echo htmlspecialchars((string) $totalPageViews); ?></div>
                <div class="kpi-label">Total Page Views</div>
                <div class="kpi-change"><?php echo $totalPageViews > 0 ? 'Across every public page · ' . htmlspecialchars($periodLabels[$period]) : 'No page views logged yet'; ?></div>
            </div>
        </div>
        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Most Viewed Pages &mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></h4>
                <?php if (empty($topPages)): ?>
                    <div class="chart-empty">
                        <i data-lucide="bar-chart-3" class="lucide"></i>
                        <div class="chart-empty-title">No visit data yet</div>
                        <div class="chart-empty-sub">This chart will populate automatically as tourists browse the site.</div>
                    </div>
                <?php else: ?>
                    <div class="bar-chart">
                        <?php foreach ($topPages as $d): ?>
                            <div class="bar-row">
                                <span class="bar-name"><?php echo htmlspecialchars($d['label']); ?></span>
                                <div class="bar-track"><div class="bar-fill" style="width:<?php echo (int) $d['pct']; ?>%"></div></div>
                                <span class="bar-pct"><?php echo (int) $d['views']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php kt_render_chart_insight($chartInsights, 'most_viewed_pages'); ?>
                <?php endif; ?>
            </div>
            <div class="chart-box">
                <h4>Recently Added &mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></h4>
                <?php if (empty($trending)): ?>
                    <div class="chart-empty">
                        <i data-lucide="trending-up" class="lucide"></i>
                        <div class="chart-empty-title">Nothing added <?php echo htmlspecialchars(strtolower($periodLabels[$period])); ?></div>
                        <div class="chart-empty-sub">New destinations and fiestas added in this period will show up here.</div>
                    </div>
                <?php else: ?>
                    <div class="trending-list">
                        <?php foreach ($trending as $i => $t): ?>
                            <div class="trending-row">
                                <div class="trending-rank">#<?php echo $i + 1; ?></div>
                                <div class="trending-info">
                                    <div class="trending-name"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <div class="trending-sub"><?php echo htmlspecialchars($t['sub']); ?></div>
                                </div>
                                <div class="trending-visits"><?php echo htmlspecialchars((string) $t['visits']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php kt_render_chart_insight($chartInsights, 'recently_added'); ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="charts-2col animate">
        <div class="chart-box">
            <h4>Most Viewed Content <span class="chart-sub">&mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></span></h4>
            <?php if (empty($topItems)): ?>
                <div class="chart-empty">
                    <i data-lucide="flame" class="lucide"></i>
                    <div class="chart-empty-title">No content views yet</div>
                    <div class="chart-empty-sub">Views of active destinations, products, restaurants, fiestas, and people will appear here.</div>
                </div>
            <?php else: ?>
                <div class="trending-list">
                        <?php $topItemViewMax = max(1, max(array_column($topItems, 'views'))); ?>
                    <?php foreach ($topItems as $i => $it): ?>
                        <div class="trending-row">
                            <div class="trending-rank">#<?php echo $i + 1; ?></div>
                            <div class="trending-info">
                                <div class="trending-name"><?php echo htmlspecialchars($it['name']); ?></div>
                                <div class="trending-sub"><?php echo htmlspecialchars($itemTypeLabels[$it['type']] ?? ucfirst($it['type'])); ?></div>
                                    <div class="content-performance-track"><span style="width:<?php echo (int) round($it['views'] / $topItemViewMax * 100); ?>%"></span></div>
                            </div>
                            <div class="trending-visits"><?php echo (int) $it['views']; ?> views</div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php kt_render_chart_insight($chartInsights, 'most_viewed_places'); ?>
            <?php endif; ?>
        </div>
        <div class="chart-box">
            <h4>Most Viewed People <span class="chart-sub">&mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></span></h4>
            <?php if (empty($topPeople)): ?>
                <div class="chart-empty">
                    <i data-lucide="users-round" class="lucide"></i>
                    <div class="chart-empty-title">No people profile views yet</div>
                    <div class="chart-empty-sub">Views will appear here when visitors open People of Malvar profiles.</div>
                </div>
            <?php else: ?>
                <div class="trending-list">
                    <?php foreach ($topPeople as $i => $person): ?>
                        <div class="trending-row">
                            <div class="trending-rank">#<?php echo $i + 1; ?></div>
                            <div class="trending-info">
                                <div class="trending-name"><?php echo htmlspecialchars($person['name']); ?></div>
                                <div class="trending-sub">People of Malvar profile</div>
                            </div>
                            <div class="trending-visits"><?php echo (int) $person['views']; ?> views</div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php kt_render_chart_insight($chartInsights, 'most_viewed_people'); ?>
            <?php endif; ?>
        </div>
        </div>
        </div>
        <?php if ($monthlySummary): ?>
        <div class="analytics-section" data-category="descriptive">
        <div class="alabel-desc">
            AI Monthly Summary
            <span class="analytics-tag">Descriptive</span>
            <span class="analytics-tag tag-ai">AI-Powered</span>
        </div>
        <div class="ai-summary-box animate">
            <i data-lucide="sparkles" class="lucide"></i>
            <p><?php echo htmlspecialchars($monthlySummary); ?></p>
        </div>
        </div>
        <?php endif; ?>
        <div class="analytics-section" data-category="descriptive">
        <div class="alabel-desc">Favorites &amp; Travel Diary Conversion <span class="analytics-tag">Descriptive</span><?php if ($chartInsightsAreAI): ?> <span class="analytics-tag tag-ai">AI-Powered</span><?php endif; ?></div>
        <div class="chart-box chart-box-full animate">
            <h4>How Views Turn Into Favorites &amp; Diary Visits</h4>
            <?php if (empty($conversionItems)): ?>
                <div class="chart-empty">
                    <i data-lucide="heart" class="lucide"></i>
                    <div class="chart-empty-title">Not enough view data yet</div>
                    <div class="chart-empty-sub">Once a few listings have at least 3 views, their favorite/diary conversion rate will show up here.</div>
                </div>
            <?php else: ?>
                <div class="conversion-list">
                    <?php foreach ($conversionItems as $ci): ?>
                        <div class="conversion-row">
                            <div class="trending-info">
                                <div class="trending-name"><?php echo htmlspecialchars($ci['name']); ?></div>
                                <div class="trending-sub"><?php echo htmlspecialchars($itemTypeLabels[$ci['type']] ?? ucfirst($ci['type'])); ?> &middot; <?php echo (int) $ci['views']; ?> views</div>
                            </div>
                            <div class="conversion-stats">
                                <div class="conversion-stat">
                                    <span class="conversion-stat-num"><?php echo (int) $ci['favoritePct']; ?>%</span>
                                    <span class="conversion-stat-label">Favorited</span>
                                </div>
                                <div class="conversion-stat">
                                    <span class="conversion-stat-num"><?php echo (int) $ci['diaryPct']; ?>%</span>
                                    <span class="conversion-stat-label">Logged</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php kt_render_chart_insight($chartInsights, 'conversion'); ?>
            <?php endif; ?>
        </div>
        </div>
        <div class="analytics-section" data-category="descriptive">
        <div class="alabel-desc">Check This Out Effectiveness <span class="analytics-tag">Promotion Analytics</span></div>
        <div class="chart-box chart-box-full animate">
            <h4>Homepage Exposure &amp; Modal Opens <span class="chart-sub">lifetime</span></h4>
            <div class="promo-effectiveness-summary">
                <div><strong><?php echo number_format($promotedImpressions); ?></strong><span>Visible impressions</span></div>
                <div><strong><?php echo number_format($promotedClicks); ?></strong><span>Modal opens</span></div>
                <div><strong><?php echo number_format($promotedCtr, 1); ?>%</strong><span>Click-through rate</span></div>
            </div>
            <?php if (empty($promotedEffectiveness)): ?>
                <div class="chart-empty">
                    <i data-lucide="megaphone" class="lucide"></i>
                    <div class="chart-empty-title">No promoted content yet</div>
                    <div class="chart-empty-sub">Promote an item first; its visible impressions and modal opens will be measured here.</div>
                </div>
            <?php else: ?>
                <div class="promo-effectiveness-chart"><canvas id="promotedEffectivenessChart" aria-label="Visible impressions compared with modal opens by promoted item" role="img"></canvas></div>
                <div class="promo-effectiveness-table-wrap">
                    <table class="promo-effectiveness-table">
                        <thead><tr><th>Content</th><th>Category</th><th>Impressions</th><th>Modal Opens</th><th>CTR</th></tr></thead>
                        <tbody>
                        <?php foreach ($promotedEffectiveness as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($itemTypeLabels[$item['type']] ?? ucfirst($item['type'])); ?></td>
                                <td><?php echo number_format($item['impressions']); ?></td>
                                <td><?php echo number_format($item['clicks']); ?></td>
                                <td><?php echo number_format($item['ctr'], 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <script type="application/json" id="promotedEffectivenessData"><?php echo json_encode($promotedChartItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?></script>
            <?php endif; ?>
        </div>
        </div>
        <div class="analytics-section" data-category="descriptive">
        <div class="alabel-desc">Search Insights <span class="analytics-tag">Descriptive</span><?php if ($chartInsightsAreAI): ?> <span class="analytics-tag tag-ai">AI-Powered</span><?php endif; ?></div>
        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Top Searches</h4>
                <?php if (empty($topSearches)): ?>
                    <div class="chart-empty">
                        <i data-lucide="search" class="lucide"></i>
                        <div class="chart-empty-title">No searches logged yet</div>
                        <div class="chart-empty-sub">What visitors search for will show up here.</div>
                    </div>
                <?php else: ?>
                    <div class="trending-list">
                        <?php foreach ($topSearches as $i => $s): ?>
                            <div class="trending-row">
                                <div class="trending-rank">#<?php echo $i + 1; ?></div>
                                <div class="trending-info">
                                    <div class="trending-name">&ldquo;<?php echo htmlspecialchars($s['query']); ?>&rdquo;</div>
                                </div>
                                <div class="trending-visits"><?php echo (int) $s['count']; ?>&times;</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php kt_render_chart_insight($chartInsights, 'top_searches'); ?>
                <?php endif; ?>
            </div>
            <div class="chart-box">
                <h4>Zero-Result Searches <span class="chart-sub">&mdash; catalog gaps</span></h4>
                <?php if (empty($zeroResultSearches)): ?>
                    <div class="chart-empty">
                        <i data-lucide="search-x" class="lucide"></i>
                        <div class="chart-empty-title">No gaps found</div>
                        <div class="chart-empty-sub">Searches that return nothing will show up here, once there are enough searches logged.</div>
                    </div>
                <?php else: ?>
                    <div class="trending-list">
                        <?php foreach ($zeroResultSearches as $i => $s): ?>
                            <div class="trending-row">
                                <div class="trending-rank">#<?php echo $i + 1; ?></div>
                                <div class="trending-info">
                                    <div class="trending-name">&ldquo;<?php echo htmlspecialchars($s['query']); ?>&rdquo;</div>
                                </div>
                                <div class="trending-visits"><?php echo (int) $s['count']; ?>&times;</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php kt_render_chart_insight($chartInsights, 'zero_result_searches'); ?>
                <?php endif; ?>
            </div>
        </div>
        </div>
        <div class="analytics-section" data-category="descriptive">
        <div class="alabel-desc">Tourist Engagement &amp; Content Interactions <span class="analytics-tag">Descriptive</span><?php if ($chartInsightsAreAI): ?> <span class="analytics-tag tag-ai">AI-Powered</span><?php endif; ?></div>
        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Interactions by type</h4>
                <?php $interactionLabels = ['click'=>'Clicks','search'=>'Searches','generate'=>'Itinerary generations','download'=>'Downloads','rating'=>'Ratings','review'=>'Reviews','comment'=>'Comments','recommendation'=>'Recommendations opened','map_interaction'=>'Map interactions','language'=>'Language selections','other'=>'Other']; $interactionMax = max(1, max($overallActivityCounts)); ?>
                <?php if (array_sum($overallActivityCounts) === 0): ?>
                    <div class="chart-empty">
                        <i data-lucide="activity" class="lucide"></i>
                        <div class="chart-empty-title">No engagement data yet</div>
                        <div class="chart-empty-sub">Clicks, searches, ratings, and other visitor actions will appear as they are recorded.</div>
                    </div>
                <?php else: ?>
                    <div class="bar-chart activity-type-chart">
                        <?php foreach ($interactionLabels as $type => $label): $count = $overallActivityCounts[$type] ?? 0; if ($count === 0) continue; ?>
                        <div class="bar-row"><span class="bar-name"><?= htmlspecialchars($label) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= (int) round($count / $interactionMax * 100) ?>%"></div></div><span class="bar-pct"><?= number_format($count) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="chart-box">
                <h4>Traffic Sources</h4>
                <?php if (empty($trafficSources)): ?>
                    <div class="chart-empty">
                        <i data-lucide="globe" class="lucide"></i>
                        <div class="chart-empty-title">No traffic data yet</div>
                        <div class="chart-empty-sub">Where visitors come from will be tracked here.</div>
                    </div>
                <?php else: ?>
                    <div class="source-list">
                        <?php foreach ($trafficSources as $s): ?>
                            <div class="source-row">
                                <div class="source-info">
                                    <div class="source-name"><?php echo htmlspecialchars($s['label']); ?></div>
                                    <div class="source-bar-wrap"><div class="source-bar" style="width:<?php echo (int) $s['pct']; ?>%"></div></div>
                                </div>
                                <div class="source-pct"><?php echo (int) $s['pct']; ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php kt_render_chart_insight($chartInsights, 'traffic_sources'); ?>
                <?php endif; ?>
            </div>
        </div>
        </div>
        <div class="analytics-section" data-category="descriptive">
        <div class="alabel-desc">Visual Reports &amp; Tourism Insights <span class="analytics-tag">Descriptive</span><?php if ($chartInsightsAreAI): ?> <span class="analytics-tag tag-ai">AI-Powered</span><?php endif; ?></div>
        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Monthly Visitor Trend</h4>
                <?php if (!$hasMonthlyData): ?>
                    <div class="chart-empty">
                        <i data-lucide="calendar" class="lucide"></i>
                        <div class="chart-empty-title">No visitor history yet</div>
                        <div class="chart-empty-sub">Monthly totals will build up automatically over time.</div>
                    </div>
                <?php else: ?>
                    <div class="month-chart">
                        <?php foreach ($monthlyVisitors as $m): ?>
                            <div class="month-row">
                                <span class="month-label"><?php echo htmlspecialchars($m['label']); ?></span>
                                <div class="month-track"><div class="month-fill" style="width:<?php echo (int) $m['pct']; ?>%"></div></div>
                                <span class="month-val"><?php echo htmlspecialchars((string) $m['value']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php kt_render_chart_insight($chartInsights, 'monthly_trend'); ?>
                <?php endif; ?>
            </div>
            <div class="chart-box">
                <h4>User Activity Summary &mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></h4>
                <?php if ($newSignups === 0 && $adminLogins === 0 && $activeUsers === 0): ?>
                    <div class="chart-empty">
                        <i data-lucide="activity" class="lucide"></i>
                        <div class="chart-empty-title">No activity recorded <?php echo htmlspecialchars(strtolower($periodLabels[$period])); ?></div>
                        <div class="chart-empty-sub">Login frequency and session activity will appear here once users start signing in.</div>
                    </div>
                <?php else: ?>
                    <div class="activity-summary">
                        <div class="activity-stat">
                            <i data-lucide="user-plus" class="lucide"></i>
                            <div><div class="activity-num"><?php echo (int) $newSignups; ?></div><div class="activity-label">New Visitor Accounts</div></div>
                        </div>
                        <div class="activity-stat">
                            <i data-lucide="log-in" class="lucide"></i>
                            <div><div class="activity-num"><?php echo (int) $adminLogins; ?></div><div class="activity-label">Admin Logins</div></div>
                        </div>
                        <div class="activity-stat">
                            <i data-lucide="radio" class="lucide"></i>
                            <div><div class="activity-num"><?php echo (int) $activeUsers; ?></div><div class="activity-label">Online Right Now</div></div>
                        </div>
                    </div>
                    <?php kt_render_chart_insight($chartInsights, 'user_activity'); ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Page Views by Time <span class="chart-sub">&mdash; <?php echo htmlspecialchars($trafficLabel); ?></span></h4>
                <?php if (!$hasDailyData): ?>
                    <div class="chart-empty">
                        <i data-lucide="activity" class="lucide"></i>
                        <div class="chart-empty-title">No daily visits yet</div>
                        <div class="chart-empty-sub">This graph will fill as visitors browse the public pages.</div>
                    </div>
                <?php else: ?>
                    <?php $dailyLabelStride = max(1, (int) ceil(count($dailyVisitors) / 10)); ?>
                    <div class="daily-views-chart">
                        <?php foreach ($dailyVisitors as $dayIndex => $day): $height = $day['value'] > 0 ? max(5, (int) round($day['value'] / $dailyVisitorsMax * 100)) : 0; ?>
                            <div class="daily-views-column" title="<?php echo htmlspecialchars($day['label'] . ': ' . $day['value'] . ' views'); ?>">
                                <span class="daily-views-value"><?php echo $day['value'] ?: ''; ?></span>
                                <div class="daily-views-track"><div class="daily-views-bar" style="height:<?php echo $height; ?>%"></div></div>
                                <span class="daily-views-label"><?php echo ($dayIndex % $dailyLabelStride === 0 || $dayIndex === count($dailyVisitors) - 1) ? htmlspecialchars($day['label']) : ''; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="chart-box">
                <h4>Visits by Day of Week <span class="chart-sub">&mdash; <?php echo htmlspecialchars($trafficLabel); ?></span></h4>
                <?php if (array_sum($weekdayCounts) === 0): ?>
                    <div class="chart-empty">
                        <i data-lucide="calendar-days" class="lucide"></i>
                        <div class="chart-empty-title">No weekday pattern yet</div>
                        <div class="chart-empty-sub">A weekly browsing pattern will appear as page views are recorded.</div>
                    </div>
                <?php else: ?>
                    <div class="weekday-views-chart">
                        <?php foreach ($weekdayLabels as $index => $weekdayLabel): $count = $weekdayCounts[$index + 1]; ?>
                            <div class="bar-row">
                                <span class="bar-name"><?php echo htmlspecialchars(substr($weekdayLabel, 0, 3)); ?></span>
                                <div class="bar-track"><div class="bar-fill" style="width:<?php echo (int) round($count / $weekdayMax * 100); ?>%"></div></div>
                                <span class="bar-pct"><?php echo $count; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Visits by Hour <span class="chart-sub">&mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></span></h4>
                <?php if (!$hasHourlyData): ?>
                    <div class="chart-empty"><i data-lucide="clock-3" class="lucide"></i><div class="chart-empty-title">No hourly visit data yet</div><div class="chart-empty-sub">This graph shows when visitors browse during the selected period.</div></div>
                <?php else: ?>
                    <div class="daily-views-chart hourly-views-chart">
                        <?php foreach ($hourlyCounts as $hour => $count): $height = $count > 0 ? max(5, (int) round($count / $hourlyMax * 100)) : 0; ?>
                            <div class="daily-views-column" title="<?php echo sprintf('%02d:00 — %d views', $hour, $count); ?>">
                                <span class="daily-views-value"><?php echo $count ?: ''; ?></span>
                                <div class="daily-views-track"><div class="daily-views-bar" style="height:<?php echo $height; ?>%"></div></div>
                                <span class="daily-views-label"><?php echo $hour % 3 === 0 ? sprintf('%02d', $hour) : ''; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="chart-box">
                <h4>New Visitor Accounts <span class="chart-sub">&mdash; last 6 months</span></h4>
                <?php if (!$hasSignupData): ?>
                    <div class="chart-empty"><i data-lucide="user-plus" class="lucide"></i><div class="chart-empty-title">No new accounts yet</div><div class="chart-empty-sub">Monthly visitor registrations will appear here as people join.</div></div>
                <?php else: ?>
                    <div class="month-chart">
                        <?php foreach ($signupTrend as $signupMonth): ?>
                            <div class="month-row"><span class="month-label"><?php echo htmlspecialchars($signupMonth['label']); ?></span><div class="month-track"><div class="month-fill" style="width:<?php echo (int) round($signupMonth['value'] / $signupMax * 100); ?>%"></div></div><span class="month-val"><?php echo (int) $signupMonth['value']; ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
        <div class="analytics-section" data-category="predictive">
        <div class="alabel-desc">
            Forecasts &amp; Momentum
            <span class="analytics-tag tag-predictive">Predictive</span>
            <?php if ($forecastIsAI): ?><span class="analytics-tag tag-ai">AI-Powered</span><?php endif; ?>
        </div>
        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Next Month&rsquo;s Projected Views</h4>
                <?php if ($projectedNextMonth === null): ?>
                    <div class="chart-empty">
                        <i data-lucide="line-chart" class="lucide"></i>
                        <div class="chart-empty-title">Not enough history yet</div>
                        <div class="chart-empty-sub">Once at least 3 months of page views have been logged, a projection will appear here.</div>
                    </div>
                <?php else: ?>
                    <div class="forecast-box">
                        <div class="forecast-num"><?php echo number_format($projectedNextMonth); ?></div>
                        <div class="forecast-label">projected page views next month</div>
                        <div class="forecast-change <?php echo $projectedGrowthPct >= 0 ? 'up' : 'down'; ?>">
                            <i data-lucide="<?php echo $projectedGrowthPct >= 0 ? 'trending-up' : 'trending-down'; ?>" class="lucide"></i>
                            <?php echo ($projectedGrowthPct >= 0 ? '+' : '') . $projectedGrowthPct; ?>% vs. last month (<?php echo $forecastIsAI ? 'AI estimate' : 'trend-line estimate'; ?>)
                        </div>
                        <?php if ($forecastInsight): ?>
                            <p class="forecast-insight"><?php echo htmlspecialchars($forecastInsight); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="chart-box">
                <h4>Pages Gaining Momentum</h4>
                <?php if (empty($trendingPages)): ?>
                    <div class="chart-empty">
                        <i data-lucide="trending-up" class="lucide"></i>
                        <div class="chart-empty-title">No upward trend yet</div>
                        <div class="chart-empty-sub">Pages with more views this week than last week will show up here.</div>
                    </div>
                <?php else: ?>
                    <div class="trending-list">
                        <?php foreach ($trendingPages as $tp): ?>
                            <div class="trending-row">
                                <div class="trending-rank up"><i data-lucide="arrow-up" class="lucide" style="width:.8rem;height:.8rem;"></i></div>
                                <div class="trending-info">
                                    <div class="trending-name"><?php echo htmlspecialchars($tp['label']); ?></div>
                                    <div class="trending-sub"><?php echo (int) $tp['views']; ?> views this week</div>
                                </div>
                                <div class="trending-visits up-pct">+<?php echo (int) $tp['growth']; ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
        <div class="analytics-section" data-category="prescriptive">
        <div class="alabel-desc">
            Recommended Actions
            <span class="analytics-tag tag-prescriptive">Prescriptive</span>
            <?php if ($recommendationsAreAI): ?><span class="analytics-tag tag-ai">AI-Powered</span><?php endif; ?>
        </div>
        <div class="rec-list animate">
            <?php foreach ($recommendations as $rec): ?>
                <div class="rec-row rec-<?php echo htmlspecialchars($rec['level']); ?>">
                    <div class="rec-icon"><i data-lucide="<?php echo htmlspecialchars($rec['icon']); ?>" class="lucide"></i></div>
                    <div class="rec-text"><?php echo htmlspecialchars($rec['text']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</div>
<script src="../assets/js/admin-sidebar-collapse.js"></script>

<script src="../assets/js/admin-notifications.js"></script>
<script src="../assets/js/admindashboard.js"></script>
<script src="../assets/js/promoted-analytics.js?v=20261102-1"></script>
<script>initSidebarCollapse();</script>

    <script src="../assets/js/admin-filter-state.js"></script>
</body>
</html>
