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
require_once __DIR__ . '/../config/ai_recommend.php';
date_default_timezone_set('Asia/Manila');
@$conn->query("SET time_zone = '+08:00'");
require_once '../config/admin_requests.php';
require_once __DIR__ . '/../config/csrf.php';
$isSuperAdmin = kt_is_super_admin();
$pendingRequestCount = kt_requests_pending_count($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderation_action'])) {
    csrf_verify();
    $action = (string) $_POST['moderation_action'];
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $adminId = (int) $_SESSION['user_id'];
    $isAjaxRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $actionSucceeded = false;
    $transactionStarted = false;
    $errorMessage = 'The moderation action could not be completed.';
    try {
        if (in_array($action, ['archive', 'safe', 'hide', 'remove', 'keep'], true) && $reviewId > 0) {
            $status = $action === 'archive' ? 'archived' : ($action === 'hide' ? 'hidden' : ($action === 'remove' ? 'removed' : 'visible'));
            $resolution = $action === 'archive' ? 'archived' : ($action === 'safe' ? 'safe_kept' : ($action === 'hide' ? 'valid_hidden' : ($action === 'remove' ? 'valid_removed' : 'invalid_kept')));
            $conn->begin_transaction();
            $transactionStarted = true;
            $stmt = $conn->prepare('UPDATE item_reviews SET status = ? WHERE review_id = ?');
            if (!$stmt) throw new RuntimeException('Could not prepare the review update.');
            $stmt->bind_param('si', $status, $reviewId);
            if (!$stmt->execute()) throw new RuntimeException('Could not update the review.');
            $stmt->close();
            $stmt = $conn->prepare("UPDATE item_review_reports SET status = 'resolved', resolution_action = ?, resolved_at = NOW(), resolved_by = ? WHERE review_id = ? AND status IN ('pending','dismissed')");
            if (!$stmt) throw new RuntimeException('Could not prepare the report update.');
            $stmt->bind_param('sii', $resolution, $adminId, $reviewId);
            if (!$stmt->execute()) throw new RuntimeException('Could not update the report.');
            $stmt->close();
            $conn->commit();
            $transactionStarted = false;
            $actionSucceeded = true;
        } elseif ($action === 'dismiss' && $reportId > 0) {
            $stmt = $conn->prepare("UPDATE item_review_reports SET status = 'dismissed', resolution_action = 'dismissed', resolved_at = NOW(), resolved_by = ? WHERE report_id = ? AND status IN ('pending','dismissed')");
            if (!$stmt) throw new RuntimeException('Could not prepare the report dismissal.');
            $stmt->bind_param('ii', $adminId, $reportId);
            $actionSucceeded = $stmt->execute();
            $stmt->close();
        } else {
            $errorMessage = 'Choose a valid moderation action.';
        }
    } catch (Throwable $error) {
        if ($transactionStarted) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
        error_log('Review moderation failed: ' . $error->getMessage());
        $actionSucceeded = false;
    }
    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$actionSucceeded || $action !== 'dismiss') http_response_code(422);
        echo json_encode($actionSucceeded && $action === 'dismiss'
            ? ['success' => true, 'action' => $action, 'report_id' => $reportId]
            : ['success' => false, 'message' => $errorMessage]);
        exit;
    }
    $redirectParams = $_GET;
    $redirectParams['review_tab'] = 'reports';
    unset($redirectParams['rating']);
    header('Location: ' . BASE_URL . '/admin/admindashboard.php?' . http_build_query($redirectParams) . '#community');
    exit;
}
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
$periodStartSql = $periodStart->format('Y-m-d 00:00:00');
$periodEndExclusiveSql = $periodEndExclusive->format('Y-m-d 00:00:00');
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
    $distributionStmt = $conn->prepare("SELECT rating, COUNT(*) total FROM item_reviews WHERE status IN ('visible','demo') AND created_at >= ? AND created_at < ? GROUP BY rating");
    if ($distributionStmt) {
        $distributionStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
        $distributionStmt->execute();
        foreach ($distributionStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $ratingRow) $ratingDistribution[(int) $ratingRow['rating']] = (int) $ratingRow['total'];
        $distributionStmt->close();
    }
    $reviewStmt = $conn->prepare("SELECT item_type, COUNT(*) total, COALESCE(AVG(rating), 0) average_rating FROM item_reviews WHERE status IN ('visible','demo') AND created_at >= ? AND created_at < ? GROUP BY item_type");
    if ($reviewStmt) {
        $reviewStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
        $reviewStmt->execute();
        foreach ($reviewStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $reviewStat) $itemReviewRatings[$reviewStat['item_type']] = ['count' => (int) $reviewStat['total'], 'average' => (float) $reviewStat['average_rating']];
        $reviewStmt->close();
    }
}
$interactionTypes = ['click', 'search', 'download', 'rating', 'review', 'comment', 'recommendation', 'map_interaction', 'other'];
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
    $parentAnalytics = ['label' => $parentMeta['label'], 'listings' => 0, 'views' => 0, 'clicks' => kt_activity_count($conn, 'click', null, $parentKey, $periodStartSql, $periodEndExclusiveSql), 'searches' => kt_activity_count($conn, 'search', null, $parentKey, $periodStartSql, $periodEndExclusiveSql), 'ratings' => 0, 'rating_total' => 0];
    foreach ($parentMeta['categories'] as $categoryKey) {
        $stats = $tourismCategoryAnalytics[$categoryKey];
        $parentAnalytics['listings'] += $stats['listings'];
        $parentAnalytics['views'] += $stats['views'];
        $parentAnalytics['ratings'] += $stats['ratings'];
        $parentAnalytics['rating_total'] += $stats['average_rating'] * $stats['ratings'];
    }
    $parentAnalytics['interactions'] = 0;
    foreach ($parentMeta['categories'] as $categoryKey) $parentAnalytics['interactions'] += $tourismCategoryAnalytics[$categoryKey]['interactions'];
    $parentAnalytics['average_rating'] = $parentAnalytics['ratings'] > 0 ? $parentAnalytics['rating_total'] / $parentAnalytics['ratings'] : 0.0;
    unset($parentAnalytics['rating_total']);
    $tourismParentAnalytics[$parentKey] = $parentAnalytics;
}
$overallActivityTypes = ['click', 'search', 'download', 'rating', 'review', 'comment', 'recommendation', 'map_interaction', 'other'];
$overallActivityCounts = [];
foreach ($overallActivityTypes as $activityType) $overallActivityCounts[$activityType] = kt_activity_count($conn, $activityType, null, null, $periodStartSql, $periodEndExclusiveSql);
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
if ($reviewTableExists && $reviewTableExists->num_rows > 0) {
    $categoryRatingStmt = $conn->prepare("SELECT item_type, rating, COUNT(*) total FROM item_reviews WHERE status IN ('visible','demo') AND created_at>=? AND created_at<? GROUP BY item_type,rating");
    if ($categoryRatingStmt) {
        $categoryRatingStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
        $categoryRatingStmt->execute();
        foreach ($categoryRatingStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $categoryRatingDistribution[$row['item_type']][(int) $row['rating']] = (int) $row['total'];
        $categoryRatingStmt->close();
    }
}
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
$topPages = analytics_page_breakdown($conn, $periodStartSql, 5, $periodEndExclusiveSql);
$topSearches = analytics_top_searches($conn, 6, $periodStartSql, $periodEndExclusiveSql);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics – KULTOURA Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/admindashboard.css?v=20261121-community-feedback">
    <link rel="stylesheet" href="../assets/css/admin-theme.css?v=20261125-center">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar"><i data-lucide="chevrons-left" class="lucide"></i></button>
    <div class="sidebar-logo">KulToura</div>
    <div class="sidebar-tagline">Malvar, Batangas</div>
    <div class="sidebar-section">Overview</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindashboard.php" class="active"><span class="nav-icon"><i data-lucide="bar-chart-2" class="lucide"></i></span> Analytics</a></li>
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminrequests.php"><span class="nav-icon"><i data-lucide="inbox" class="lucide"></i></span> Requests<?php if ($pendingRequestCount > 0): ?> <span class="sidebar-badge"><?php echo $pendingRequestCount; ?></span><?php endif; ?></a></li>
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
        <?php include __DIR__ . '/analytics_workspace.php'; ?>
    </div>
</div>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-folder-modal.js?v=20261124-prefix"></script>

<script src="../assets/js/admin-notifications.js"></script>
<script>initSidebarCollapse();</script>

</body>
</html>
