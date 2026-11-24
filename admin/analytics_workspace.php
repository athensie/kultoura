<?php
// Analytics workspace view. Every plotted value comes from the current database queries.
$destinationRows = $cultureRows = $restaurantRows = $destinationRatedRows = $cultureRatedRows = [];
foreach ($tourismCategoryAnalytics as $categoryKey => $stats) {
    foreach ($stats['top_items'] as $item) {
        $item['category_label'] = $stats['label'];
        if (in_array($categoryKey, ['nature', 'industry', 'resort', 'church'], true)) $destinationRows[] = $item;
        if (in_array($categoryKey, ['product', 'fiesta', 'person'], true)) $cultureRows[] = $item;
        if ($categoryKey === 'restaurant') $restaurantRows[] = $item;
        if (($item['ratings'] ?? 0) > 0 && in_array($categoryKey, ['nature', 'industry', 'resort', 'church'], true)) $destinationRatedRows[] = $item;
        if (($item['ratings'] ?? 0) > 0 && in_array($categoryKey, ['product', 'fiesta', 'person'], true)) $cultureRatedRows[] = $item;
    }
}
$sortByViews = static fn($a, $b) => ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
$destinationAllRows = $destinationRows; $cultureAllRows = $cultureRows;
usort($destinationRows, $sortByViews); usort($cultureRows, $sortByViews); usort($restaurantRows, $sortByViews);
usort($destinationAllRows, static fn($a, $b) => ($a['views'] ?? 0) <=> ($b['views'] ?? 0));
usort($cultureAllRows, static fn($a, $b) => ($a['views'] ?? 0) <=> ($b['views'] ?? 0));
usort($destinationRatedRows, static fn($a, $b) => ($b['average_rating'] ?? 0) <=> ($a['average_rating'] ?? 0));
usort($cultureRatedRows, static fn($a, $b) => ($b['average_rating'] ?? 0) <=> ($a['average_rating'] ?? 0));
$destinationRows = array_slice($destinationRows, 0, 10);
$cultureRows = array_slice($cultureRows, 0, 10);
$restaurantRows = array_slice($restaurantRows, 0, 10);
$categoryLabels = array_column(array_values($tourismCategoryAnalytics), 'label');
$categoryViews = array_column(array_values($tourismCategoryAnalytics), 'views');
$destinationCategoryLabels = $destinationCategoryViews = $cultureCategoryLabels = $cultureCategoryViews = [];
foreach ($tourismCategoryAnalytics as $key => $stats) {
    if (in_array($key, ['nature', 'industry', 'resort', 'church'], true)) { $destinationCategoryLabels[] = $stats['label']; $destinationCategoryViews[] = $stats['views']; }
    if (in_array($key, ['product', 'fiesta', 'person'], true)) { $cultureCategoryLabels[] = $stats['label']; $cultureCategoryViews[] = $stats['views']; }
}
$culturalKeys = array_values(array_filter(array_keys($tourismCategoryAnalytics), static fn($key)=>in_array($key,['product','fiesta','person'],true)));
$cultureEngagementLabels = array_map(static fn($key)=>$tourismCategoryAnalytics[$key]['label'],$culturalKeys);
$cultureEngagementValues = array_map(static fn($key)=>$tourismCategoryAnalytics[$key]['views']+$tourismCategoryAnalytics[$key]['clicks']+$tourismCategoryAnalytics[$key]['searches'],$culturalKeys);
$cultureEngagementDatasets=[];
foreach (['views'=>'Views','clicks'=>'Clicks','searches'=>'Searches'] as $field=>$label) $cultureEngagementDatasets[]=['label'=>$label,'data'=>array_map(static fn($key)=>$tourismCategoryAnalytics[$key][$field],$culturalKeys),'backgroundColor'=>['Views'=>'#2c4a2e','Clicks'=>'#9fb88a','Searches'=>'#987123'][$label]];
$sectionEngagementDatasets=[];
foreach (['views'=>'Views','clicks'=>'Clicks','searches'=>'Searches'] as $field=>$label) $sectionEngagementDatasets[]=['label'=>$label,'data'=>array_column(array_values($tourismCategoryAnalytics),$field),'backgroundColor'=>['Views'=>'#2c4a2e','Clicks'=>'#9fb88a','Searches'=>'#987123'][$label]];
$ratingLabels = ['1 star', '2 stars', '3 stars', '4 stars', '5 stars'];
$ratingValues = array_values($ratingDistribution);
$cultureRatingDistribution = array_fill(1,5,0);
foreach (['product','fiesta','person'] as $cultureType) foreach (($categoryRatingDistribution[$cultureType] ?? []) as $rating=>$count) $cultureRatingDistribution[(int)$rating] += (int)$count;
// Actual calendar range the charts cover (shown in the section header and on weekday totals).
$rangeFirstDay = new DateTimeImmutable($periodStart->format('Y-m-d'));
$rangeLastDay = $periodEndExclusive->modify('-1 day');
if ($rangeLastDay > new DateTimeImmutable('today')) $rangeLastDay = new DateTimeImmutable('today');
if ($period === 'all') {
    $firstVisitResult = $conn->query("SELECT MIN(DATE(viewed_at)) first_day FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local')");
    $firstVisitDay = $firstVisitResult ? ($firstVisitResult->fetch_assoc()['first_day'] ?? null) : null;
    if ($firstVisitDay) $rangeFirstDay = new DateTimeImmutable($firstVisitDay);
}
$rangeHasDates = (int) $rangeFirstDay->format('Y') >= 2000 && $rangeFirstDay <= $rangeLastDay;
$rangeLabel = '';
if ($rangeHasDates) {
    if ($rangeFirstDay->format('Y-m-d') === $rangeLastDay->format('Y-m-d')) $rangeLabel = $rangeLastDay->format('M j, Y');
    elseif ($rangeFirstDay->format('Y') === $rangeLastDay->format('Y')) $rangeLabel = $rangeFirstDay->format('M j') . ' – ' . $rangeLastDay->format('M j, Y');
    else $rangeLabel = $rangeFirstDay->format('M j, Y') . ' – ' . $rangeLastDay->format('M j, Y');
}
// How many Sundays, Mondays, ... fall inside the range, so weekday totals can be read as "across N Saturdays".
$weekdayOccurrences = array_fill(0, 7, 0);
if ($rangeHasDates) {
    $rangeDayCount = (int) $rangeFirstDay->diff($rangeLastDay)->days + 1;
    $rangeFirstWeekday = (int) $rangeFirstDay->format('w');
    for ($weekdayIndex = 0; $weekdayIndex < 7; $weekdayIndex++) {
        $weekdayOccurrences[$weekdayIndex] = intdiv($rangeDayCount, 7) + ((($weekdayIndex - $rangeFirstWeekday + 7) % 7) < ($rangeDayCount % 7) ? 1 : 0);
    }
}
$weekdayChart = [
    'labels' => ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], 'values' => array_values($weekdayCounts),
    'fullLabels' => ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
    'occurrences' => $rangeHasDates ? $weekdayOccurrences : null,
];
$weekdayNote = $rangeHasDates
    ? 'Total visits for each weekday, ' . $rangeLabel . '. Each bar adds up every matching day in that range (e.g. all Saturdays), not a single week. Hover a bar to see how many days it covers.'
    : 'Total visits on each day of the week in the selected period. Each bar adds up every matching day, not one single week.';
$pageLabels = array_map(static fn($row) => $row['label'], $topPages);
$trafficSources = analytics_traffic_sources($conn, $periodStartSql, 6, $periodEndExclusiveSql);
$pageValues = array_map(static fn($row) => $row['views'], $topPages);
$searchLabels = array_map(static fn($row) => $row['query'], $topSearches);
$searchValues = array_map(static fn($row) => $row['count'], $topSearches);
$zeroResultSearches = analytics_zero_result_searches($conn, 8, $periodStartSql, $periodEndExclusiveSql);
$zeroResultSearchTotal = 0;
$zeroResultTotalStmt = $conn->prepare('SELECT COUNT(*) total FROM search_log WHERE result_count = 0 AND searched_at >= ? AND searched_at < ?');
if ($zeroResultTotalStmt) {
    $zeroResultTotalStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
    $zeroResultTotalStmt->execute();
    $zeroResultSearchTotal = (int) ($zeroResultTotalStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $zeroResultTotalStmt->close();
}
$promotedPerformance = [];
$promotedStmt = $conn->query('SELECT item_type, item_id, impressions, clicks FROM promoted_content_analytics ORDER BY impressions DESC LIMIT 8');
if ($promotedStmt) {
    foreach ($promotedStmt->fetch_all(MYSQLI_ASSOC) as $row) {
        $promotedPerformance[] = [
            'label' => ucfirst((string) $row['item_type']) . ' #' . (int) $row['item_id'],
            'impressions' => (int) $row['impressions'],
            'clicks' => (int) $row['clicks'],
        ];
    }
    $promotedStmt->free();
}
$destinationChart = ['labels' => array_column($destinationRows, 'name'), 'values' => array_column($destinationRows, 'views')];
$destinationLeastChart = ['labels' => array_column(array_slice($destinationAllRows, 0, 10), 'name'), 'values' => array_column(array_slice($destinationAllRows, 0, 10), 'views')];
$cultureChart = ['labels' => array_column($cultureRows, 'name'), 'values' => array_column($cultureRows, 'views')];
$cultureLeastChart = ['labels' => array_column(array_slice($cultureAllRows, 0, 10), 'name'), 'values' => array_column(array_slice($cultureAllRows, 0, 10), 'views')];
$destinationScatter = array_values(array_map(static fn($row) => ['x'=>(int)$row['views'],'y'=>(float)$row['average_rating'],'label'=>$row['name']], array_filter($destinationRatedRows, static fn($row) => ($row['views']??0)>0)));
$cultureScatter = array_values(array_map(static fn($row) => ['x'=>(int)$row['views'],'y'=>(float)$row['average_rating'],'label'=>$row['name']], array_filter($cultureRatedRows, static fn($row) => ($row['views']??0)>0)));
$restaurantChart = ['labels' => array_column($restaurantRows, 'name'), 'values' => array_column($restaurantRows, 'views')];
$dailyVisitBuckets=[];
$dailyVisitStmt=$conn->prepare("SELECT DATE(viewed_at) bucket,COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY DATE(viewed_at) ORDER BY bucket");
if ($dailyVisitStmt) { $dailyVisitStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $dailyVisitStmt->execute(); foreach ($dailyVisitStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $dailyVisitBuckets[$row['bucket']]=(int)$row['total']; $dailyVisitStmt->close(); }
$dailyVisitLabels=$dailyVisitValues=[];
if ($dailyVisitBuckets) { $day=(new DateTimeImmutable(array_key_first($dailyVisitBuckets)));$lastDay=(new DateTimeImmutable(array_key_last($dailyVisitBuckets))); for(;$day<=$lastDay;$day=$day->modify('+1 day')) { $dayKey=$day->format('Y-m-d');$dailyVisitLabels[]=$day->format('M j, Y');$dailyVisitValues[]=$dailyVisitBuckets[$dayKey]??0; } }
$dailyChart = ['labels'=>$dailyVisitLabels,'values'=>$dailyVisitValues];
$monthlyVisitBuckets = $yearlyVisitBuckets = [];
$bucketStmt = $conn->prepare("SELECT DATE_FORMAT(viewed_at,'%Y-%m') bucket, COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY bucket ORDER BY bucket");
if ($bucketStmt) { $bucketStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql); $bucketStmt->execute(); foreach ($bucketStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $monthlyVisitBuckets[$row['bucket']] = (int)$row['total']; $bucketStmt->close(); }
$yearStmt = $conn->prepare("SELECT YEAR(viewed_at) bucket, COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY YEAR(viewed_at) ORDER BY bucket");
if ($yearStmt) { $yearStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql); $yearStmt->execute(); foreach ($yearStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $yearlyVisitBuckets[(string)$row['bucket']] = (int)$row['total']; $yearStmt->close(); }
$monthlyVisitTrend = [];
if ($monthlyVisitBuckets) { $fromMonth = new DateTimeImmutable(array_key_first($monthlyVisitBuckets) . '-01'); $toMonth = new DateTimeImmutable(array_key_last($monthlyVisitBuckets) . '-01'); for ($month=$fromMonth; $month<=$toMonth; $month=$month->modify('+1 month')) { $key=$month->format('Y-m'); $monthlyVisitTrend[]=['label'=>$month->format('M Y'),'value'=>$monthlyVisitBuckets[$key]??0]; } }
$selectedRangeDays = (int)$periodStart->diff($periodEndExclusive->modify('-1 day'))->days;
if ($period === 'today') {
    $visitsTrendChart=['labels'=>array_column($trafficTrend,'label'),'values'=>array_column($trafficTrend,'value')];
    $visitsMetricTitle='Visits by Hour';
    $visitsMetricNote='Hourly page views for today.';
} elseif ($selectedRangeDays > 90) {
    $visitsTrendChart=['labels'=>array_column($monthlyVisitTrend,'label'),'values'=>array_column($monthlyVisitTrend,'value')];
    $visitsMetricTitle='Visits per Month';
    $visitsMetricNote='Monthly page views for the selected range; monthly grouping keeps long-range trends readable.';
} else {
    $visitsTrendChart=$dailyChart;
    $visitsMetricTitle='Visits per Day';
    $visitsMetricNote='Daily page views recorded during the selected period.';
}
$yearlyVisitTrend = [];
if ($yearlyVisitBuckets) for ($year=(int)array_key_first($yearlyVisitBuckets); $year<=(int)array_key_last($yearlyVisitBuckets); $year++) $yearlyVisitTrend[]=['label'=>(string)$year,'value'=>$yearlyVisitBuckets[(string)$year]??0];
$heatmapViews = array_fill(1, 7, array_fill(0, 24, 0));
$heatmapStmt = $conn->prepare("SELECT DAYOFWEEK(viewed_at) dow, HOUR(viewed_at) hr, COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY dow,hr");
if ($heatmapStmt) { $heatmapStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql); $heatmapStmt->execute(); foreach ($heatmapStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $heatmapViews[(int)$row['dow']][(int)$row['hr']] = (int)$row['total']; $heatmapStmt->close(); }
$heatmapClicks = array_fill(1, 7, array_fill(0, 24, 0));
$clickHeatmapStmt = $conn->prepare("SELECT DAYOFWEEK(activity_date) dow, HOUR(activity_date) hr, COUNT(*) total FROM user_activity_log WHERE activity_type='click' AND activity_date>=? AND activity_date<? GROUP BY dow,hr");
if ($clickHeatmapStmt) { $clickHeatmapStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql); $clickHeatmapStmt->execute(); foreach ($clickHeatmapStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $heatmapClicks[(int)$row['dow']][(int)$row['hr']] = (int)$row['total']; $clickHeatmapStmt->close(); }
$calendarEndDate = $periodEndExclusive->modify('-1 day');
$calendarStartDate = new DateTimeImmutable($periodStart->format('Y-m-d'));
if ($calendarStartDate->diff($calendarEndDate)->days > 364) $calendarStartDate = $calendarEndDate->modify('-364 days');
$calendarStartSql = $calendarStartDate->format('Y-m-d 00:00:00');
$calendarEndSql = $periodEndExclusiveSql;
$dailyActivityCounts = [];
$calendarStmt = $conn->prepare("SELECT DATE(activity_date) day,COUNT(*) total FROM user_activity_log WHERE activity_date>=? AND activity_date<? GROUP BY DATE(activity_date)");
if ($calendarStmt) { $calendarStmt->bind_param('ss',$calendarStartSql,$calendarEndSql); $calendarStmt->execute(); foreach ($calendarStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $dailyActivityCounts[$row['day']]=(int)$row['total']; $calendarStmt->close(); }
$interactionCalendar = [];
for ($day=$calendarStartDate; $day<$periodEndExclusive; $day=$day->modify('+1 day')) $interactionCalendar[]=['date'=>$day->format('Y-m-d'),'label'=>$day->format('M j, Y'),'value'=>$dailyActivityCounts[$day->format('Y-m-d')]??0];
$cultureMonthBuckets = $fiestaMonthBuckets = $commentMonthBuckets = $reviewMonthBuckets = $searchMonthBuckets = $engagementMonthBuckets = $registrationBuckets = [];
$cultureMonthStmt = $conn->prepare("SELECT DATE_FORMAT(viewed_at,'%Y-%m') bucket,COUNT(*) total FROM item_views WHERE item_type IN ('product','fiesta','person') AND viewed_at>=? AND viewed_at<? GROUP BY bucket ORDER BY bucket");
if ($cultureMonthStmt) { $cultureMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $cultureMonthStmt->execute(); foreach ($cultureMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $cultureMonthBuckets[$row['bucket']]=(int)$row['total']; $cultureMonthStmt->close(); }
$fiestaMonthStmt = $conn->prepare("SELECT DATE_FORMAT(viewed_at,'%Y-%m') bucket,COUNT(*) total FROM item_views WHERE item_type='fiesta' AND viewed_at>=? AND viewed_at<? GROUP BY bucket ORDER BY bucket");
if ($fiestaMonthStmt) { $fiestaMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $fiestaMonthStmt->execute(); foreach ($fiestaMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $fiestaMonthBuckets[$row['bucket']]=(int)$row['total']; $fiestaMonthStmt->close(); }
$commentMonthStmt = $conn->prepare("SELECT DATE_FORMAT(activity_date,'%Y-%m') bucket,COUNT(*) total FROM user_activity_log WHERE activity_type='comment' AND activity_date>=? AND activity_date<? GROUP BY bucket ORDER BY bucket");
if ($commentMonthStmt) { $commentMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $commentMonthStmt->execute(); foreach ($commentMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $commentMonthBuckets[$row['bucket']]=(int)$row['total']; $commentMonthStmt->close(); }
$reviewCount = 0;
if ($reviewTableExists && $reviewTableExists->num_rows > 0) {
    $reviewCountStmt = $conn->prepare("SELECT COUNT(*) total FROM item_reviews WHERE status IN ('visible','demo') AND review_text IS NOT NULL AND TRIM(review_text)<>'' AND created_at>=? AND created_at<?");
    if ($reviewCountStmt) { $reviewCountStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $reviewCountStmt->execute(); $reviewCount=(int)($reviewCountStmt->get_result()->fetch_assoc()['total']??0); $reviewCountStmt->close(); }
    $reviewMonthStmt = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') bucket,COUNT(*) total FROM item_reviews WHERE status IN ('visible','demo') AND review_text IS NOT NULL AND TRIM(review_text)<>'' AND created_at>=? AND created_at<? GROUP BY bucket ORDER BY bucket");
    if ($reviewMonthStmt) { $reviewMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $reviewMonthStmt->execute(); foreach ($reviewMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $reviewMonthBuckets[$row['bucket']]=(int)$row['total']; $reviewMonthStmt->close(); }
}
$searchMonthStmt = $conn->prepare("SELECT DATE_FORMAT(activity_date,'%Y-%m') bucket,COUNT(*) total FROM user_activity_log WHERE activity_type='search' AND activity_date>=? AND activity_date<? GROUP BY bucket ORDER BY bucket");
if ($searchMonthStmt) { $searchMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $searchMonthStmt->execute(); foreach ($searchMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $searchMonthBuckets[$row['bucket']]=(int)$row['total']; $searchMonthStmt->close(); }
$engagementMonthStmt = $conn->prepare("SELECT DATE_FORMAT(activity_date,'%Y-%m') bucket,COUNT(*) total FROM user_activity_log WHERE activity_date>=? AND activity_date<? GROUP BY bucket ORDER BY bucket");
if ($engagementMonthStmt) { $engagementMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $engagementMonthStmt->execute(); foreach ($engagementMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $engagementMonthBuckets[$row['bucket']]=(int)$row['total']; $engagementMonthStmt->close(); }
$registrationStmt = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') bucket,COUNT(*) total FROM users WHERE created_at>=? AND created_at<? GROUP BY bucket ORDER BY bucket");
if ($registrationStmt) { $registrationStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $registrationStmt->execute(); foreach ($registrationStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $registrationBuckets[$row['bucket']]=(int)$row['total']; $registrationStmt->close(); }
$bucketSeries = static function(array $buckets): array { return ['labels'=>array_map(static fn($key)=>(new DateTimeImmutable($key.'-01'))->format('M Y'),array_keys($buckets)),'values'=>array_values($buckets)]; };
$cultureAverageTotal = 0.0; $cultureRatingCount = 0;
foreach (['product','fiesta','person'] as $cultureType) { $entry=$itemReviewRatings[$cultureType]??['count'=>0,'average'=>0.0]; $cultureRatingCount += $entry['count']; $cultureAverageTotal += $entry['count']*$entry['average']; }
$cultureAverageRating = $cultureRatingCount ? $cultureAverageTotal/$cultureRatingCount : 0.0;
$destinationRatingGauge = $destinationRatingCount ? ['labels'=>['Average rating','Remaining'],'values'=>[$averageDestinationRating,max(0,5-$averageDestinationRating)]] : ['labels'=>[],'values'=>[]];
$cultureRatingGauge = $cultureRatingCount ? ['labels'=>['Average rating','Remaining'],'values'=>[$cultureAverageRating,max(0,5-$cultureAverageRating)]] : ['labels'=>[],'values'=>[]];
$sentimentCounts = ['Positive'=>0,'Neutral'=>0,'Negative'=>0];
if ($reviewTableExists && $reviewTableExists->num_rows > 0) {
    $sentimentStmt = $conn->prepare("SELECT review_text FROM item_reviews WHERE status IN ('visible','demo') AND review_text IS NOT NULL AND TRIM(review_text)<>'' AND created_at>=? AND created_at<?");
    if ($sentimentStmt) { $sentimentStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $sentimentStmt->execute(); foreach ($sentimentStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $reviewRow) { $score=analytics_review_sentiment((string)$reviewRow['review_text']); $sentimentCounts[$score>0?'Positive':($score<0?'Negative':'Neutral')]++; } $sentimentStmt->close(); }
}
$destinationMapPoints = [];
$mapStmt = $conn->prepare("SELECT d.destination_name,d.latitude,d.longitude,COUNT(v.view_id) views FROM destination d LEFT JOIN item_views v ON v.item_type=d.category AND v.item_id=d.destination_id AND v.viewed_at>=? AND v.viewed_at<? WHERE d.status='active' AND d.latitude IS NOT NULL AND d.longitude IS NOT NULL GROUP BY d.destination_id,d.destination_name,d.latitude,d.longitude");
if ($mapStmt) { $mapStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $mapStmt->execute(); foreach ($mapStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $destinationMapPoints[]=['name'=>(string)$row['destination_name'],'lat'=>(float)$row['latitude'],'lng'=>(float)$row['longitude'],'views'=>(int)$row['views']]; $mapStmt->close(); }
$destinationBarangayLabels = $destinationBarangayValues = [];
require_once __DIR__ . '/../config/malvar_locations.php';
$destinationBarangays = array_fill_keys(kt_malvar_barangays(), 0);
$barangayStmt = $conn->prepare("SELECT d.address,COUNT(v.view_id) views FROM destination d LEFT JOIN item_views v ON v.item_type=d.category AND v.item_id=d.destination_id AND v.viewed_at>=? AND v.viewed_at<? WHERE d.status='active' GROUP BY d.destination_id,d.address");
if ($barangayStmt) { $barangayStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $barangayStmt->execute(); foreach ($barangayStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) { $address=mb_strtolower((string)$row['address']); foreach ($destinationBarangays as $barangay=>$count) if (str_contains($address,mb_strtolower($barangay))) { $destinationBarangays[$barangay]+=(int)$row['views']; break; } } $barangayStmt->close(); }
arsort($destinationBarangays);
foreach ($destinationBarangays as $barangay=>$views) if ($views>0) { $destinationBarangayLabels[]=$barangay; $destinationBarangayValues[]=$views; }
$cultureBarangays=array_fill_keys(kt_malvar_barangays(),0);
$cultureLocationQueries=[
    "SELECT pl.address,COUNT(v.view_id) views FROM product_locations pl INNER JOIN item_views v ON v.item_type='product' AND v.item_id=pl.product_id AND v.viewed_at>=? AND v.viewed_at<? GROUP BY pl.location_id,pl.address",
    "SELECT f.location address,COUNT(v.view_id) views FROM fiestas f INNER JOIN item_views v ON v.item_type='fiesta' AND v.item_id=f.fiesta_id AND v.viewed_at>=? AND v.viewed_at<? GROUP BY f.fiesta_id,f.location",
];
foreach ($cultureLocationQueries as $cultureLocationSql) {
    $cultureLocationStmt=$conn->prepare($cultureLocationSql);
    if ($cultureLocationStmt) { $cultureLocationStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $cultureLocationStmt->execute(); foreach ($cultureLocationStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) { $address=mb_strtolower((string)$row['address']); foreach ($cultureBarangays as $barangay=>$count) if (str_contains($address,mb_strtolower($barangay))) { $cultureBarangays[$barangay]+=(int)$row['views']; break; } } $cultureLocationStmt->close(); }
}
arsort($cultureBarangays);$cultureBarangayLabels=[];$cultureBarangayValues=[];
foreach ($cultureBarangays as $barangay=>$views) if ($views>0) { $cultureBarangayLabels[]=$barangay;$cultureBarangayValues[]=$views; }
$searchRankings = ['destination'=>[],'culture'=>[]];
$searchCategoryGroups = ['destination'=>"'nature','industry','resort','church'",'culture'=>"'product','fiesta','person'"];
foreach ($searchCategoryGroups as $group=>$categoriesSql) {
    $searchStmt=$conn->prepare("SELECT content_name name,COUNT(*) total FROM user_activity_log WHERE activity_type='search' AND category IN ({$categoriesSql}) AND item_id IS NOT NULL AND content_name IS NOT NULL AND content_name<>'' AND activity_date>=? AND activity_date<? GROUP BY item_type,item_id,content_name ORDER BY total DESC LIMIT 8");
    if ($searchStmt) { $searchStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $searchStmt->execute(); $searchRankings[$group]=$searchStmt->get_result()->fetch_all(MYSQLI_ASSOC); $searchStmt->close(); }
}
$recommendationRankings=['destination'=>[],'restaurant'=>[]];
foreach (['destination'=>"'nature','industry','resort','church'",'restaurant'=>"'restaurant'"] as $group=>$categoriesSql) {
    $recommendationStmt=$conn->prepare("SELECT content_name name,COUNT(*) total FROM user_activity_log WHERE activity_type='recommendation' AND category IN ({$categoriesSql}) AND item_id IS NOT NULL AND content_name IS NOT NULL AND content_name<>'' AND activity_date>=? AND activity_date<? GROUP BY item_type,item_id,content_name ORDER BY total DESC LIMIT 8");
    if ($recommendationStmt) { $recommendationStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $recommendationStmt->execute(); $recommendationRankings[$group]=$recommendationStmt->get_result()->fetch_all(MYSQLI_ASSOC); $recommendationStmt->close(); }
}
$cultureCommentPoints=[];
$commentScatterStmt=$conn->prepare("SELECT l.item_type,l.item_id,MAX(l.content_name) name,COUNT(*) comments,COALESCE(v.views,0) views FROM user_activity_log l LEFT JOIN (SELECT item_type,item_id,COUNT(*) views FROM item_views WHERE viewed_at>=? AND viewed_at<? GROUP BY item_type,item_id) v ON BINARY v.item_type=BINARY l.item_type AND v.item_id=l.item_id WHERE l.activity_type='comment' AND l.category IN ('product','fiesta','person') AND l.item_id IS NOT NULL AND l.activity_date>=? AND l.activity_date<? GROUP BY l.item_type,l.item_id,v.views ORDER BY comments DESC LIMIT 40");
if ($commentScatterStmt) { $commentScatterStmt->bind_param('ssss',$periodStartSql,$periodEndExclusiveSql,$periodStartSql,$periodEndExclusiveSql); $commentScatterStmt->execute(); foreach ($commentScatterStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $cultureCommentPoints[]=['x'=>(int)$row['views'],'y'=>(int)$row['comments'],'label'=>(string)($row['name']?:'Cultural item')]; $commentScatterStmt->close(); }
$destinationMonthRows=[];
$destinationMonthStmt=$conn->prepare("SELECT d.destination_name name,DATE_FORMAT(v.viewed_at,'%Y-%m') bucket,COUNT(*) total FROM item_views v INNER JOIN destination d ON d.destination_id=v.item_id AND d.category=v.item_type WHERE v.item_type IN ('nature','industry','resort','church') AND v.viewed_at>=? AND v.viewed_at<? AND d.status='active' GROUP BY d.destination_id,d.destination_name,bucket ORDER BY bucket");
if ($destinationMonthStmt) { $destinationMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $destinationMonthStmt->execute(); $destinationMonthRows=$destinationMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC); $destinationMonthStmt->close(); }
$destinationMonthTotals=[];
foreach ($destinationMonthRows as $row) $destinationMonthTotals[$row['name']]=($destinationMonthTotals[$row['name']]??0)+(int)$row['total'];
arsort($destinationMonthTotals);
$destinationMonthRowsByName=[];
foreach ($destinationMonthRows as $row) $destinationMonthRowsByName[$row['name']][$row['bucket']]=(int)$row['total'];
$destinationPerformanceLabels=array_map(static fn($key)=>(new DateTimeImmutable($key.'-01'))->format('M Y'),array_keys($monthlyVisitBuckets));
$destinationPerformanceDatasets=[];
foreach (array_slice(array_keys($destinationMonthTotals),0,5) as $name) $destinationPerformanceDatasets[]=['label'=>$name,'data'=>array_map(static fn($key)=>$destinationMonthRowsByName[$name][$key]??0,array_keys($monthlyVisitBuckets)),'borderColor'=>'#2c7a45','backgroundColor'=>'rgba(44,122,69,.12)','tension'=>.3,'fill'=>false];
$destinationPerformanceValues=[];
foreach (array_keys($monthlyVisitBuckets) as $monthKey) { $monthTotal=0; foreach ($destinationPerformanceDatasets as $series) { $monthTotal+=(int)($series['data'][count($destinationPerformanceValues)]??0); } $destinationPerformanceValues[]=$monthTotal; }
$recommendationVsViewsLabels=[];$recommendationVsViewsValues=[];$recommendationVsViewsData=[];
foreach ($recommendationRankings['destination'] as $row) { $recommendationVsViewsLabels[]=(string)$row['name']; $recommendationVsViewsValues[]=(int)$row['total']; }
$recommendationVsViewsStmt=$conn->prepare("SELECT d.destination_name name,COUNT(v.view_id) total FROM destination d LEFT JOIN item_views v ON v.item_type=d.category AND v.item_id=d.destination_id AND v.viewed_at>=? AND v.viewed_at<? WHERE d.status='active' GROUP BY d.destination_id,d.destination_name");
if ($recommendationVsViewsStmt) { $recommendationVsViewsStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $recommendationVsViewsStmt->execute(); foreach ($recommendationVsViewsStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $recommendationVsViewsData[$row['name']]=(int)$row['total']; $recommendationVsViewsStmt->close(); }
$recommendationVsViewsLabels=array_slice($recommendationVsViewsLabels,0,8);
$recommendationVsViewsValues=array_slice($recommendationVsViewsValues,0,8);
$recommendationVsViewsActual=array_map(static fn($name)=>$recommendationVsViewsData[$name]??0,$recommendationVsViewsLabels);
$chartData = [
    'weekday' => $weekdayChart, 'pages' => ['labels' => $pageLabels, 'values' => $pageValues], 'searches' => ['labels' => $searchLabels, 'values' => $searchValues],
    'sources' => ['labels'=>array_column($trafficSources,'label'),'values'=>array_column($trafficSources,'pct')],
    'zeroResultSearches' => ['labels'=>array_column($zeroResultSearches,'query'),'values'=>array_column($zeroResultSearches,'count')],
    'promotedPerformance' => [
        'labels'=>array_column($promotedPerformance,'label'),
        'values'=>array_map(static fn($row)=>$row['impressions']+$row['clicks'],$promotedPerformance),
        'datasets'=>[
            ['label'=>'Impressions','data'=>array_column($promotedPerformance,'impressions'),'backgroundColor'=>'#9fb88a'],
            ['label'=>'Clicks','data'=>array_column($promotedPerformance,'clicks'),'backgroundColor'=>'#987123'],
        ],
    ],
    'destinationRanks' => $destinationChart, 'destinationLeast' => $destinationLeastChart, 'cultureRanks' => $cultureChart, 'cultureLeast' => $cultureLeastChart, 'restaurants' => $restaurantChart,
    'destinationCategories' => ['labels' => $destinationCategoryLabels, 'values' => $destinationCategoryViews],
    'cultureCategories' => ['labels' => $cultureCategoryLabels, 'values' => $cultureCategoryViews],
    'cultureEngagement' => ['labels'=>$cultureEngagementLabels,'values'=>$cultureEngagementValues,'datasets'=>$cultureEngagementDatasets],
    'sectionEngagement' => ['labels'=>$categoryLabels,'values'=>$categoryViews,'datasets'=>$sectionEngagementDatasets],
    'allCategories' => ['labels' => $categoryLabels, 'values' => $categoryViews], 'ratings' => ['labels' => $ratingLabels, 'values' => $ratingValues],
    'cultureRatings' => ['labels'=>$ratingLabels,'values'=>array_values($cultureRatingDistribution)],
    'signup' => $bucketSeries($registrationBuckets), 'daily' => $visitsTrendChart,
    'monthlyVisits' => ['labels'=>array_column($monthlyVisitTrend,'label'),'values'=>array_column($monthlyVisitTrend,'value')],
    'yearlyVisits' => ['labels'=>array_column($yearlyVisitTrend,'label'),'values'=>array_column($yearlyVisitTrend,'value')],
    'cultureMonthly' => $bucketSeries($cultureMonthBuckets), 'fiestaMonthly' => $bucketSeries($fiestaMonthBuckets),
    'commentsMonthly' => $bucketSeries($commentMonthBuckets), 'reviewsMonthly' => $bucketSeries($reviewMonthBuckets), 'searchMonthly' => $bucketSeries($searchMonthBuckets),
    'engagementMonthly' => $bucketSeries($engagementMonthBuckets),
    'sentiment' => ['labels'=>array_keys($sentimentCounts),'values'=>array_values($sentimentCounts)],
    'destinationRatingGauge' => $destinationRatingGauge, 'cultureRatingGauge' => $cultureRatingGauge,
    'heatmapViews' => $heatmapViews, 'heatmapClicks' => $heatmapClicks,
    'destinationMap' => $destinationMapPoints,
    'destinationBarangays'=>['labels'=>$destinationBarangayLabels,'values'=>$destinationBarangayValues],
    'destinationSearches'=>['labels'=>array_column($searchRankings['destination'],'name'),'values'=>array_map('intval',array_column($searchRankings['destination'],'total'))],
    'cultureSearches'=>['labels'=>array_column($searchRankings['culture'],'name'),'values'=>array_map('intval',array_column($searchRankings['culture'],'total'))],
    'cultureBarangays'=>['labels'=>$cultureBarangayLabels,'values'=>$cultureBarangayValues],
    'recommendedDestinations'=>['labels'=>array_column($recommendationRankings['destination'],'name'),'values'=>array_map('intval',array_column($recommendationRankings['destination'],'total'))],
    'recommendedRestaurants'=>['labels'=>array_column($recommendationRankings['restaurant'],'name'),'values'=>array_map('intval',array_column($recommendationRankings['restaurant'],'total'))],
    'destinationPerformance'=>['labels'=>$destinationPerformanceLabels,'values'=>$destinationPerformanceValues,'datasets'=>$destinationPerformanceDatasets],
    'recommendationComparison'=>['labels'=>$recommendationVsViewsLabels,'values'=>$recommendationVsViewsValues,'datasets'=>[['label'=>'Recommendation clicks','data'=>$recommendationVsViewsValues,'backgroundColor'=>'#2c4a2e'],['label'=>'Destination views','data'=>$recommendationVsViewsActual,'backgroundColor'=>'#9fb88a']]],
    'cultureComments'=>['labels'=>array_column($cultureCommentPoints,'label'),'values'=>array_column($cultureCommentPoints,'y'),'points'=>$cultureCommentPoints],
    'interactionCalendar' => $interactionCalendar,
    'clickSections' => ['labels' => $categoryLabels, 'values' => array_column(array_values($tourismCategoryAnalytics), 'clicks')],
    'destinationRated' => ['labels' => array_column(array_slice($destinationRatedRows, 0, 10), 'name'), 'values' => array_map(static fn($r) => round((float) $r['average_rating'], 2), array_slice($destinationRatedRows, 0, 10))],
    'cultureRated' => ['labels' => array_column(array_slice($cultureRatedRows, 0, 10), 'name'), 'values' => array_map(static fn($r) => round((float) $r['average_rating'], 2), array_slice($cultureRatedRows, 0, 10))],
    'destinationScatter' => ['labels'=>array_column($destinationScatter,'label'),'values'=>array_column($destinationScatter,'y'),'points'=>$destinationScatter],
    'cultureScatter' => ['labels'=>array_column($cultureScatter,'label'),'values'=>array_column($cultureScatter,'y'),'points'=>$cultureScatter],
];
$chartDataJson = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$workspaceTabs = [
    'traffic' => 'Website Traffic', 'destination' => 'Tourism & Destinations', 'culture' => 'Culture & Heritage',
    'search' => 'Search Insights', 'community' => 'Community Feedback', 'audience' => 'Audience',
    'recommendations' => 'Recommendations & Promotions',
];
$initialWorkspaceTab = isset($_GET['review_tab']) ? 'community' : (isset($workspaceTabs[$_GET['aw_tab'] ?? '']) ? (string) $_GET['aw_tab'] : 'traffic');
// Visitor-level metrics from anonymous visitor/visit IDs (see analytics_visit_ids()).
require_once __DIR__ . '/../config/analytics_insights.php';
analytics_ensure_visit_columns($conn);
$visitQuality = kt_visit_quality($conn, $periodStartSql, $periodEndExclusiveSql);
$deviceBreakdown = kt_device_breakdown($conn, $periodStartSql, $periodEndExclusiveSql);
$intentBreakdown = kt_intent_breakdown($conn, $periodStartSql, $periodEndExclusiveSql);
$visitTrackingSince = $visitQuality['tracking_since'] ? (new DateTimeImmutable($visitQuality['tracking_since']))->format('M j, Y') : null;
$visitTrackingNote = $visitTrackingSince ? ' Visitor IDs have been recorded since ' . $visitTrackingSince . '; earlier visits are not included.' : ' Visitor IDs start recording with the next public page view.';
if ($visitQuality['tracked']) {
    $chartData['visitors'] = ['labels' => ['New visitors', 'Returning visitors'], 'values' => [$visitQuality['new_visitors'], $visitQuality['returning_visitors']]];
    $visitorsNote = 'Unique visitors (anonymous browser IDs, not identified people). Returning = first seen before this period.' . $visitTrackingNote;
} else {
    $chartData['visitors'] = ['labels' => ['New tracked sessions', 'Returning tracked sessions'], 'values' => [$newTrackedVisitors, $returningTrackedVisitors]];
    $visitorsNote = 'Counts are tracked sessions, not identified people.' . $visitTrackingNote;
}
$chartData['visitQuality'] = $visitQuality['tracked'] ? [
    'labels' => ['Unique visitors', 'Visits', 'Pages per visit', 'Bounce rate', 'Returning visitors'],
    'values' => [
        number_format($visitQuality['unique_visitors']), number_format($visitQuality['visits']),
        number_format($visitQuality['pages_per_visit'], 1), $visitQuality['bounce_rate'] . '%',
        $visitQuality['unique_visitors'] ? round($visitQuality['returning_visitors'] / $visitQuality['unique_visitors'] * 100) . '%' : '0%',
    ],
] : ['labels' => [], 'values' => []];
$chartData['devices'] = ['labels' => array_keys($deviceBreakdown), 'values' => array_values($deviceBreakdown)];
$engagedTime = kt_engaged_time_by_page($conn, $periodStartSql, $periodEndExclusiveSql);
$chartData['engagedTime'] = ['labels' => array_keys($engagedTime), 'values' => array_values($engagedTime), 'valueFormat' => 'duration', 'seriesName' => 'Average time on page'];
$barangayInterest = kt_barangay_interest($conn, $periodStartSql, $periodEndExclusiveSql);
$chartData['barangayInterest'] = ['labels' => array_keys($barangayInterest['barangays']), 'values' => array_values($barangayInterest['barangays']), 'seriesName' => 'Listing views'];
$barangayNote = 'Listing views grouped by the barangay named in each listing\'s address (visitors\' own barangay cannot be known from a browser).'
    . ($barangayInterest['unmatched'] > 0 ? ' ' . number_format($barangayInterest['unmatched']) . ' views were for listings whose address does not name a barangay; add the barangay to those addresses to include them.' : '');
require_once __DIR__ . '/../config/audience_demo.php';
$audienceDemoPresent = kt_audience_demo_present($conn);
$canManageDemoData = in_array(strtolower((string) ($_SESSION['role'] ?? '')), ['admin', 'super admin'], true);
$demoDataStatus = (string) ($_GET['demo'] ?? '');
$chartData['intents'] = ['labels' => array_keys($intentBreakdown), 'values' => array_values($intentBreakdown)];
$workspaceMetrics = [
    'traffic' => [
        ['Visits per Hour', 'heatmap', 'heatmapViews', 'Page views grouped by weekday and hour.'],
        [$visitsMetricTitle, 'line', 'daily', $visitsMetricNote], ['Visits by Day of Week', 'bar', 'weekday', $weekdayNote],
        ['Visits per Month', 'area', 'monthlyVisits', 'Monthly page views in the selected period.'], ['Visits per Year', 'line', 'yearlyVisits', 'Annual page views in the selected period.'],
        ['Top Pages', 'rank', 'pages', 'Page view counts from recorded visits.'], ['Traffic Sources', 'donut', 'sources', 'Referrer host recorded with each page view.'],
        ['Visitor Engagement Trends', 'line', 'engagementMonthly', 'Recorded user activity events grouped by month.'],
    ],
    'destination' => [
        ['Total Destination Views', 'kpi', null, number_format($destinationViews)], ['Most Popular Destinations', 'rank', 'destinationRanks', 'Recorded destination detail views.'],
        ['Least Popular Destinations', 'rank-low', 'destinationLeast', ''], ['Destination Views by Category', 'donut', 'destinationCategories', ''],
        ['Destination Popularity by Barangay', 'map', 'destinationMap', 'Map markers show verified destination coordinates; barangay boundaries are not available.'],
        ['Destination Views by Barangay', 'bar', 'destinationBarangays', 'Views grouped using the barangay named in each destination address.'],
        ['Most Viewed Establishments', 'rank', 'restaurants', 'Restaurant listing detail views.'], ['Restaurant Engagement', 'bar', 'restaurants', 'Views are available; restaurant-specific searches and recommendations are not fully tracked.'],
        ['Destination Performance Over Time', 'line', 'destinationPerformance', 'Monthly detail views for the five most-viewed destinations in this period.'],
    ],
    'culture' => [
        ['Most Viewed Cultural Content', 'rank', 'cultureRanks', ''], ['Least Viewed Cultural Content', 'rank-low', 'cultureLeast', ''],
        ['Culture and Heritage Views by Category', 'donut', 'cultureCategories', 'Products, fiestas, and people of Malvar.'], ['Cultural Content Views per Month', 'line', 'cultureMonthly', 'Product, fiesta, and people detail views.'],
        ['Cultural Content Engagement', 'stacked', 'cultureEngagement', 'Recorded views, clicks, and searches by cultural category.'], ['Content Performance by Barangay', 'bar', 'cultureBarangays', 'Cultural views grouped by barangay from product store and fiesta location records.'],
        ['Fiesta Interest Over Time', 'line', 'fiestaMonthly', 'Recorded fiesta detail views.'],
        ['Views vs. Comments', 'scatter', 'cultureComments', 'Each point compares item detail views with linked comment events.'],
    ],
    'search' => [
        ['Most Popular Search Queries', 'wordcloud', 'searches', 'Queries and counts recorded by site search.'], ['Searches per Month', 'line', 'searchMonthly', 'Recorded search activity grouped by month.'],
        ['Searches With No Results', 'rank', 'zeroResultSearches', 'Search log entries with zero results during the selected period.'],
        ['Most Searched Destinations', 'rank', 'destinationSearches', 'Search events linked to destination records.'], ['Most Searched Cultural Topics', 'wordcloud', 'cultureSearches', 'Search events linked to cultural content categories.'],
    ],
    'community' => [],
    'audience' => [
        ['Visit Quality', 'stats', 'visitQuality', 'A visit ends after 30 minutes without activity. Bounce rate = visits that saw one page and opened no listing. Bots, link previews and logged-in admins are excluded.' . $visitTrackingNote],
        ['New vs. Returning Visitors', 'donut', 'visitors', $visitorsNote],
        ['Visitors by Device', 'donut', 'devices', 'Share of visits by device type, detected from the browser.' . $visitTrackingNote],
        ['Visitor Intent Actions', 'rank', 'intents', 'Clicks that show plans to visit: directions, calls, messages, emails, website and social links, and shares.'],
        ['Listing Interest by Barangay', 'rank', 'barangayInterest', $barangayNote],
        ['User Registration Trends', 'line', 'signup', 'Monthly registrations from account creation records.'], ['Visitor Activity by Day', 'heatmap', 'heatmapViews', 'Page visits grouped by weekday and hour for the selected period.'],
        ['Visitor Activity by Hour', 'heatmap', 'heatmapViews', 'Page visits grouped by hour and weekday for the selected period.'], ['Average Time Spent per Page', 'rank', 'engagedTime', 'Average time each page was actually visible on screen (background tabs excluded), for pages with at least 5 measured views.' . $visitTrackingNote],
    ],
    'recommendations' => [
        ['Promoted Content Impressions and Clicks', 'stacked', 'promotedPerformance', 'Cumulative totals; the promoted content counter does not retain event dates.'],
        ['Most Recommended Destinations', 'rank', 'recommendedDestinations', 'Destinations associated with recorded recommendation events.'], ['Most Recommended Establishments', 'rank', 'recommendedRestaurants', 'Establishments associated with recorded recommendation events.'],
        ['AI Recommendation Click-through Rate', 'kpi-empty', null, 'Recommendation impressions are not logged, so a click-through rate cannot be calculated.'], ['Recommended vs. Actually Viewed Destinations', 'bar', 'recommendationComparison', 'Compares recommendation events with destination detail views; impressions are not tracked.'],
        ['Recommendation Engagement Rate', 'kpi-empty', null, 'Recommendation impressions are not recorded.'], ['AI Recommendation Performance', 'line', null, 'Recommendation outcomes are not linked to generated recommendations.'],
    ],
];
require __DIR__ . '/reviews_admin_data.php';
$dashboardKpis = [];
foreach ($workspaceMetrics as $sectionKey => &$metrics) {
    foreach ($metrics as $metricIndex => $metric) {
        if (($metric[1] ?? '') !== 'kpi') continue;
        $dashboardKpis[] = ['section' => $sectionKey, 'index' => $metricIndex, 'title' => $metric[0], 'value' => $metric[3]];
    }
}
unset($metrics);
// Headline KPIs beside "Total Destination Views": traffic, top destination, satisfaction and demand,
// all for the selected period and all from recorded data.
$kpiPeriodStart = new DateTimeImmutable($periodStartSql);
$kpiPeriodEnd = new DateTimeImmutable($periodEndExclusiveSql);
$kpiSiteVisits = analytics_total_views($conn, $periodStartSql, $periodEndExclusiveSql);
$kpiVisitsChange = null;
if ($period !== 'all') {
    $kpiPeriodDays = max(1, (int) $kpiPeriodStart->diff($kpiPeriodEnd)->days);
    $kpiPreviousStartSql = $kpiPeriodStart->modify('-' . $kpiPeriodDays . ' days')->format('Y-m-d H:i:s');
    $kpiPreviousVisits = analytics_total_views($conn, $kpiPreviousStartSql, $periodStartSql);
    if ($kpiPreviousVisits > 0) $kpiVisitsChange = (($kpiSiteVisits - $kpiPreviousVisits) / $kpiPreviousVisits) * 100;
}
$kpiVisitsDescription = $kpiVisitsChange === null
    ? 'Page views across the whole site in the selected period.'
    : sprintf('%s%s%% vs the previous %s (%s visits).', $kpiVisitsChange >= 0 ? '+' : '', number_format($kpiVisitsChange, abs($kpiVisitsChange) < 10 ? 1 : 0), ($kpiPeriodDays ?? 0) === 1 ? 'day' : ($kpiPeriodDays . ' days'), number_format($kpiPreviousVisits));
$kpiTopDestination = null;
foreach ($destinationRows as $destinationRow) { if (($destinationRow['views'] ?? 0) > 0) { $kpiTopDestination = $destinationRow; break; } }
$kpiRatingCount = 0; $kpiRatingAverage = 0.0;
$kpiRatingStmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(AVG(rating),0) average_rating FROM item_reviews WHERE status IN ('visible','demo') AND created_at >= ? AND created_at < ?");
if ($kpiRatingStmt) {
    $kpiRatingStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
    $kpiRatingStmt->execute();
    $kpiRatingRow = $kpiRatingStmt->get_result()->fetch_assoc();
    $kpiRatingCount = (int) ($kpiRatingRow['total'] ?? 0);
    $kpiRatingAverage = (float) ($kpiRatingRow['average_rating'] ?? 0);
    $kpiRatingStmt->close();
}
$kpiSearchCount = 0;
$kpiSearchStmt = $conn->prepare('SELECT COUNT(*) total FROM search_log WHERE searched_at >= ? AND searched_at < ?');
if ($kpiSearchStmt) {
    $kpiSearchStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql);
    $kpiSearchStmt->execute();
    $kpiSearchCount = (int) ($kpiSearchStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $kpiSearchStmt->close();
}
$dashboardKpis[] = ['section' => 'headline', 'index' => 0, 'title' => 'Total Site Visits', 'value' => number_format($kpiSiteVisits), 'description' => $kpiVisitsDescription, 'trend' => $kpiVisitsChange === null ? null : ($kpiVisitsChange >= 0 ? 'up' : 'down')];
$dashboardKpis[] = ['section' => 'headline', 'index' => 1, 'title' => 'Top Destination', 'value' => $kpiTopDestination ? (string) $kpiTopDestination['name'] : 'No views yet', 'value_is_text' => true, 'description' => $kpiTopDestination ? number_format((int) $kpiTopDestination['views']) . ' views · ' . ($kpiTopDestination['category_label'] ?? 'Destination') : 'No destination detail views were recorded in the selected period.'];
$dashboardKpis[] = ['section' => 'headline', 'index' => 2, 'title' => 'Average Visitor Rating', 'value' => $kpiRatingCount ? number_format($kpiRatingAverage, 1) . ' / 5' : 'No reviews', 'value_is_text' => !$kpiRatingCount, 'description' => $kpiRatingCount ? 'From ' . number_format($kpiRatingCount) . ' review' . ($kpiRatingCount === 1 ? '' : 's') . ' posted in the selected period.' : 'No visitor reviews were posted in the selected period.'];
$dashboardKpis[] = ['section' => 'headline', 'index' => 3, 'title' => 'Visitor Searches', 'value' => number_format($kpiSearchCount), 'description' => $kpiSearchCount ? number_format($zeroResultSearchTotal) . ' returned no results' . ($zeroResultSearchTotal ? ' — possible content gaps to fill.' : '.') : 'No site searches were recorded in the selected period.'];
$aiCardFacts = [];
$aiCardFallbacks = [];
$aiCardSuggestionFallbacks = [];
$weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
foreach ($workspaceMetrics as $sectionKey => $metrics) {
    foreach ($metrics as $metricIndex => [$title, $kind, $dataKey, $note]) {
        $cardId = $sectionKey . '_' . $metricIndex;
        $fact = ['title' => $title, 'visualization' => $kind, 'data_note' => (string) $note];
        $payload = $dataKey !== null ? ($chartData[$dataKey] ?? null) : null;
        $fallback = '';
        if ($kind === 'kpi') {
            $fact['recorded_value'] = (string) $note;
            $fallback = $title . ' recorded ' . $note . ' in the selected period.';
        } elseif (is_array($payload) && $kind === 'heatmap') {
            $cells = [];
            foreach ($payload as $day => $hours) {
                foreach ($hours as $hour => $value) {
                    $cells[] = ['day' => $weekdayNames[((int) $day - 1) % 7], 'hour' => (int) $hour, 'value' => (int) $value];
                }
            }
            usort($cells, static fn($a, $b) => $b['value'] <=> $a['value']);
            $fact['total_events'] = array_sum(array_column($cells, 'value'));
            $fact['busiest_periods'] = array_slice($cells, 0, 3);
            $peak = $cells[0] ?? null;
            $fallback = $peak && $peak['value'] > 0
                ? 'Recorded activity was highest on ' . $peak['day'] . ' around ' . sprintf('%02d:00', $peak['hour']) . ' (' . number_format($peak['value']) . ' events).'
                : 'No activity was recorded in this heatmap for the selected period.';
        } elseif (is_array($payload) && $kind === 'stats') {
            $statPairs = !empty($payload['labels']) ? array_combine($payload['labels'], $payload['values']) : [];
            $fact['recorded_values'] = $statPairs;
            $fallback = $statPairs ? implode('; ', array_map(static fn($label, $value) => $label . ' ' . $value, array_keys($statPairs), $statPairs)) . '.' : '';
        } elseif (is_array($payload) && $kind === 'map') {
            $points = array_values(array_filter($payload, 'is_array'));
            usort($points, static fn($a, $b) => (int) ($b['views'] ?? 0) <=> (int) ($a['views'] ?? 0));
            $fact['mapped_locations'] = count($points);
            $fact['total_views_at_mapped_locations'] = array_sum(array_map(static fn($point) => (int) ($point['views'] ?? 0), $points));
            $fact['most_viewed_mapped_locations'] = array_map(static fn($point) => ['name' => $point['name'], 'views' => (int) $point['views']], array_slice($points, 0, 3));
            $fallback = count($points) . ' locations have usable map coordinates; mapped records may not represent locations without coordinates.';
        } elseif (is_array($payload) && $kind === 'calendar') {
            $values = array_map(static fn($day) => (int) ($day['value'] ?? 0), $payload);
            $fact['days_with_activity'] = count(array_filter($values, static fn($value) => $value > 0));
            $fact['total_events'] = array_sum($values);
            $fallback = number_format(array_sum($values)) . ' recorded interactions occurred across ' . count(array_filter($values, static fn($value) => $value > 0)) . ' active days in the displayed calendar.';
        } elseif (is_array($payload) && isset($payload['labels'], $payload['values'])) {
            $pairs = [];
            foreach ($payload['labels'] as $index => $label) {
                if (!array_key_exists($index, $payload['values']) || !is_numeric($payload['values'][$index])) continue;
                $pairs[] = ['label' => (string) $label, 'value' => (float) $payload['values'][$index]];
            }
            $sortedPairs = $pairs;
            usort($sortedPairs, static fn($a, $b) => $b['value'] <=> $a['value']);
            $fact['record_count'] = count($pairs);
            $fact['total'] = array_sum(array_column($pairs, 'value'));
            $fact['leading_values'] = array_slice($sortedPairs, 0, 5);
            if (isset($payload['datasets'])) {
                $fact['series_totals'] = array_map(static fn($dataset) => [
                    'series' => (string) ($dataset['label'] ?? 'Series'),
                    'total' => array_sum(array_map('floatval', $dataset['data'] ?? [])),
                ], $payload['datasets']);
            }
            if ($kind === 'scatter' && !empty($payload['points'])) {
                $fact['points'] = array_slice(array_map(static fn($point) => [
                    'label' => (string) ($point['label'] ?? ''), 'views' => (int) ($point['x'] ?? 0), 'rating' => (float) ($point['y'] ?? 0),
                ], $payload['points']), 0, 12);
            }
            if ($pairs && array_sum(array_column($pairs, 'value')) > 0) {
                $leader = $sortedPairs[0];
                $fallback = $title . ': ' . $leader['label'] . ' is highest in the displayed data (' . rtrim(rtrim(number_format($leader['value'], 2), '0'), '.') . ').';
                if (in_array($kind, ['line', 'area'], true) && count($pairs) > 1) {
                    $first = $pairs[0]; $last = $pairs[count($pairs) - 1];
                    $direction = $last['value'] > $first['value'] ? 'up' : ($last['value'] < $first['value'] ? 'down' : 'unchanged');
                    $fallback = 'The series is ' . $direction . ' from ' . $first['label'] . ' (' . number_format($first['value']) . ') to ' . $last['label'] . ' (' . number_format($last['value']) . '); the highest displayed point is ' . $leader['label'] . '.';
                }
            } else {
                $fallback = 'No values are available to interpret for this metric in the selected period.';
            }
        }
        if (($payload === null && $kind !== 'kpi') || $payload === [] || $kind === 'kpi-empty' || $fallback === '') {
            $limitation = trim((string) $note);
            $fallback = $limitation !== ''
                ? 'This metric cannot be interpreted from current records: ' . $limitation
                : 'There are not enough recorded values to interpret this metric for the selected period.';
        }
        $aiCardFacts[$cardId] = $fact;
        $aiCardFallbacks[$cardId] = $fallback;
        if ($kind === 'kpi-empty') {
            $suggestionFallback = 'This metric is not currently tracked. Add reliable tracking before using it to guide decisions.';
        } elseif ($kind === 'heatmap' && !empty($fact['busiest_periods'][0]['value'])) {
            $peak = $fact['busiest_periods'][0];
            $suggestionFallback = 'Consider reviewing content timing around ' . $peak['day'] . ' at ' . sprintf('%02d:00', $peak['hour']) . '; this is a recorded activity peak, not proof of cause.';
        } elseif (!empty($fact['leading_values'][0]['value'])) {
            $leader = $fact['leading_values'][0];
            $suggestionFallback = 'Review ' . $leader['label'] . ' (' . number_format((float) $leader['value'], 0) . ') and compare later periods before changing promotion plans.';
        } elseif (!empty($fact['most_viewed_mapped_locations'][0])) {
            $leader = $fact['most_viewed_mapped_locations'][0];
            $suggestionFallback = 'Keep ' . $leader['name'] . ' map details accurate; it leads the mapped locations with ' . number_format((int) $leader['views']) . ' views.';
        } elseif ($kind === 'stats' && !empty($fact['recorded_values'])) {
            $suggestionFallback = 'If the bounce rate is high, link related listings and directions from your most visited pages so visitors keep exploring.';
        } elseif ($kind === 'kpi') {
            $suggestionFallback = 'Track this actual value against the next reporting period before deciding whether to change promotion.';
        } else {
            $suggestionFallback = 'There is not enough recorded data to support a specific action yet; continue collecting this metric.';
        }
        $aiCardSuggestionFallbacks[$cardId] = $suggestionFallback;
    }
}
// ---------------------------------------------------------------------------
// Descriptive / predictive / prescriptive analytics, computed from the whole system
// (see config/analytics_insights.php). AI may only reword text; every number shown
// comes from recorded data, and AI text quoting numbers that are not in the data is rejected.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../config/analytics_insights.php';
$selectedPeriodLabel = (string) ($periodLabels[$period] ?? 'selected period');
$insightStartSql = ($period === 'all' && $rangeHasDates) ? $rangeFirstDay->format('Y-m-d 00:00:00') : $periodStartSql;
$systemSnapshot = kt_system_snapshot($conn, $insightStartSql, $periodEndExclusiveSql);
$previousRange = $period === 'all' ? null : kt_previous_range($periodStartSql, $periodEndExclusiveSql);
$previousSnapshot = $previousRange ? kt_system_snapshot($conn, $previousRange['start'], $previousRange['end']) : null;
$previousRangeLabel = $previousRange ? ($previousRange['days'] === 1 ? 'the previous day' : 'the previous ' . $previousRange['days'] . ' days') : '';
$snapshotChange = static fn(string $key): ?float => $previousSnapshot ? kt_change_pct((float) $systemSnapshot[$key], (float) $previousSnapshot[$key]) : null;
$selectedPageViews = (int) $systemSnapshot['site_visits'];

// Every tourism listing's performance in the period, across all categories.
$itemsByCategory = [];
$allPeriodItems = [];
foreach ($tourismCategoryAnalytics as $categoryStats) {
    $itemsByCategory[$categoryStats['label']] = $categoryStats['top_items'];
    foreach ($categoryStats['top_items'] as $item) { $item['category_label'] = $categoryStats['label']; $allPeriodItems[] = $item; }
}
usort($allPeriodItems, static fn($a, $b) => (int) ($b['views'] ?? 0) <=> (int) ($a['views'] ?? 0));
$descriptiveTopContent = array_values(array_filter(array_slice($allPeriodItems, 0, 5), static fn($item) => (int) ($item['views'] ?? 0) > 0));

// ---- Descriptive ----
$descriptiveHighlights = [
    ['label' => 'Site visits', 'value' => number_format($systemSnapshot['site_visits']), 'change' => $snapshotChange('site_visits')],
    $systemSnapshot['unique_visitors'] > 0
        ? ['label' => 'Unique visitors', 'value' => number_format($systemSnapshot['unique_visitors']), 'change' => ($previousSnapshot['unique_visitors'] ?? 0) > 0 ? $snapshotChange('unique_visitors') : null]
        : ['label' => 'Visitor sessions', 'value' => number_format($systemSnapshot['visitor_sessions']), 'change' => $snapshotChange('visitor_sessions')],
    ['label' => 'Listing views', 'value' => number_format($systemSnapshot['content_views']), 'change' => $snapshotChange('content_views')],
    ['label' => 'Searches · with no results', 'value' => number_format($systemSnapshot['searches']) . ' · ' . number_format($systemSnapshot['zero_result_searches']), 'change' => $snapshotChange('searches')],
    ['label' => 'Reviews · average rating', 'value' => number_format($systemSnapshot['reviews']) . ($systemSnapshot['average_rating'] !== null ? ' · ' . number_format($systemSnapshot['average_rating'], 1) . '/5' : ''), 'change' => $snapshotChange('reviews')],
    ['label' => 'New members', 'value' => number_format($systemSnapshot['new_members']), 'change' => $snapshotChange('new_members')],
    ['label' => 'Favourites saved', 'value' => number_format($systemSnapshot['favorites']), 'change' => $snapshotChange('favorites')],
    ['label' => 'Intent actions (directions, calls…)', 'value' => number_format($systemSnapshot['intent_actions']), 'change' => $snapshotChange('intent_actions')],
];
$summaryParts = [];
$visitsChangeText = kt_format_change($snapshotChange('site_visits'));
$summaryParts[] = $selectedPeriodLabel . ($rangeLabel !== '' && $period !== 'custom' ? ' (' . $rangeLabel . ')' : '') . ' recorded '
    . kt_plural($systemSnapshot['site_visits'], 'site visit') . ($visitsChangeText !== '' ? ' (' . $visitsChangeText . ' vs ' . $previousRangeLabel . ')' : '')
    . ' and ' . kt_plural($systemSnapshot['content_views'], 'listing view') . '.';
if ($descriptiveTopContent) {
    $leader = $descriptiveTopContent[0];
    $summaryParts[] = $leader['name'] . ' (' . $leader['category_label'] . ') was the most viewed listing with ' . kt_plural((int) $leader['views'], 'view') . '.';
}
if ($systemSnapshot['reviews'] > 0) $summaryParts[] = 'Visitors posted ' . kt_plural($systemSnapshot['reviews'], 'review') . ' averaging ' . number_format((float) $systemSnapshot['average_rating'], 1) . '/5.';
if ($systemSnapshot['searches'] > 0) $summaryParts[] = 'They ran ' . kt_plural($systemSnapshot['searches'], 'search', 'searches') . ($systemSnapshot['zero_result_searches'] > 0 ? ', ' . number_format($systemSnapshot['zero_result_searches']) . ' with no results' : '') . '.';
if ($systemSnapshot['intent_actions'] > 0) $summaryParts[] = kt_plural($systemSnapshot['intent_actions'], 'visitor action') . ' showed plans to visit (directions, calls, messages or links).';
if ($systemSnapshot['new_members'] > 0 || $systemSnapshot['favorites'] > 0) $summaryParts[] = kt_plural($systemSnapshot['new_members'], 'new member') . ' signed up and ' . kt_plural($systemSnapshot['favorites'], 'favourite') . ' were saved.';
$systemSummary = implode(' ', $summaryParts);

// ---- Predictive ----
$trafficForecast = kt_traffic_forecast($conn);
$categoryOutlook = kt_category_outlook($conn, $tourismCategoryDefinitions);
$forecastChartMax = $trafficForecast['available'] ? max(1, ...array_map(static fn($row) => (int) $row['value'], $trafficForecast['rows'])) : 1;

// ---- AI: optional wording for the summary and per-chart notes (numbers verified) ----
$aiDashboardFacts = [
    'selected_period' => $selectedPeriodLabel,
    'date_range' => $rangeLabel,
    'compared_with' => $previousRangeLabel,
    'this_period' => $systemSnapshot,
    'previous_period' => $previousSnapshot,
    'top_listings' => array_map(static fn($row) => ['name' => (string) $row['name'], 'category' => (string) $row['category_label'], 'views' => (int) $row['views']], $descriptiveTopContent),
    'top_searches' => array_map(static fn($row) => ['query' => (string) ($row['query'] ?? ''), 'searches' => (int) ($row['count'] ?? 0)], array_slice($topSearches, 0, 5)),
    'analytics_cards' => $aiCardFacts,
];
$aiDashboardResult = null;
$aiFactsJson = json_encode($aiDashboardFacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$aiCacheKey = 'dashai_v10_' . substr(sha1((string) $aiFactsJson), 0, 24);
if (ai_text_configured()) {
    $aiPrompt = "You are the analytics analyst for KULTOURA, a tourism website for Malvar, Batangas. Use only these real aggregate facts; do not invent data, causes, visitor demographics, or certainty. "
        . "Write summary: 2-3 plain sentences describing this_period (the selected period) and, when previous_period is present, how it compares; quote only numbers that appear in the facts. "
        . "Also write exactly one concise interpretation for every analytics_cards entry, keyed by its exact ID in card_insights, and one practical admin suggestion for every entry, keyed by its exact ID in card_suggestions. Quote only numbers present in that card's facts; do not claim causation. If a card has no usable data, say so and suggest collecting more data. "
        . "Return JSON only with keys summary (string), card_insights (object), card_suggestions (object). Facts: " . json_encode($aiDashboardFacts, JSON_UNESCAPED_UNICODE);
    $cachedAiStmt = $conn->prepare("SELECT result FROM analytics_forecasts WHERE forecast_key=? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)");
    if ($cachedAiStmt) {
        $cachedAiStmt->bind_param('s', $aiCacheKey);
        $cachedAiStmt->execute();
        $cachedAiRow = $cachedAiStmt->get_result()->fetch_assoc();
        $cachedAiStmt->close();
        if ($cachedAiRow) $aiDashboardResult = json_decode((string) $cachedAiRow['result'], true);
    }
    if (!is_array($aiDashboardResult)) {
        $conn->query("DELETE FROM analytics_forecasts WHERE forecast_key='" . $conn->real_escape_string($aiCacheKey) . "'");
        $aiDashboardResult = ai_cached_or_generate($conn, $aiCacheKey, $aiDashboardFacts, $aiPrompt);
    }
}
$aiSummary = $systemSummary;
$aiSummaryIsGenerated = false;
$aiCardInterpretations = $aiCardFallbacks;
$aiCardSuggestions = [];
if (is_array($aiDashboardResult)) {
    $candidateSummary = trim((string) ($aiDashboardResult['summary'] ?? ''));
    if ($candidateSummary !== '' && kt_text_numbers_supported($candidateSummary, $aiDashboardFacts)) {
        $aiSummary = mb_substr($candidateSummary, 0, 600);
        $aiSummaryIsGenerated = true;
    }
    foreach (['card_insights' => 'insight', 'card_suggestions' => 'suggestion'] as $resultKey => $kindOfText) {
        if (!isset($aiDashboardResult[$resultKey]) || !is_array($aiDashboardResult[$resultKey])) continue;
        foreach ($aiCardFallbacks as $cardId => $unused) {
            $candidate = $aiDashboardResult[$resultKey][$cardId] ?? null;
            if (!is_string($candidate) || trim($candidate) === '') continue;
            if (!kt_text_numbers_supported($candidate, ['card' => $aiCardFacts[$cardId] ?? [], 'facts' => $aiDashboardFacts])) continue;
            if ($kindOfText === 'insight') $aiCardInterpretations[$cardId] = mb_substr(trim($candidate), 0, 260);
            else $aiCardSuggestions[$cardId] = mb_substr(trim($candidate), 0, 220);
        }
    }
}

// ---- Prescriptive ----
$pendingReportCount = kt_insight_count($conn, 'item_review_reports', "SELECT COUNT(*) total FROM item_review_reports WHERE status = 'pending' AND created_at >= ? AND created_at < ?", '1971-01-01 00:00:00', '2999-01-01 00:00:00');
$promotedKeys = [];
if (kt_insight_table_exists($conn, 'promoted_content') && ($promotedResult = $conn->query('SELECT item_type, item_id FROM promoted_content'))) {
    foreach ($promotedResult->fetch_all(MYSQLI_ASSOC) as $promotedRow) $promotedKeys[$promotedRow['item_type'] . ':' . (int) $promotedRow['item_id']] = true;
}
$recommendationCandidates = kt_build_recommendations([
    'period_label' => $selectedPeriodLabel,
    'snapshot' => $systemSnapshot,
    'previous' => $previousSnapshot,
    'zero_result_searches' => $zeroResultSearches,
    'rated_items' => array_values(array_filter($allPeriodItems, static fn($item) => (int) ($item['ratings'] ?? 0) > 0)),
    'items_by_category' => $itemsByCategory,
    'category_outlook' => $categoryOutlook,
    'pending_reports' => $pendingReportCount,
    'promoted_keys' => $promotedKeys,
    'visit_quality' => $visitQuality,
]);
$prescriptiveRecommendations = array_map(static fn($candidate) => ['action' => $candidate['action'], 'evidence' => $candidate['evidence']], array_slice($recommendationCandidates, 0, 4));
if (!$prescriptiveRecommendations) {
    $prescriptiveRecommendations[] = [
        'action' => 'No urgent issues stand out. Keep content up to date and check again as more activity is recorded.',
        'evidence' => 'No search gaps, low ratings, pending reports, or sharp category changes were found for ' . strtolower($selectedPeriodLabel) . '.',
    ];
}
$aiTextActive = ai_text_configured();
$prescriptiveAiUsed = false;
$prescriptiveAiIssue = ai_text_configuration_issue();
if ($aiTextActive && $recommendationCandidates) {
    $candidatesForAi = array_map(static fn($candidate) => ['id' => $candidate['id'], 'action' => $candidate['action'], 'evidence' => $candidate['evidence']], array_slice($recommendationCandidates, 0, 8));
    $prescriptivePrompt = "You are a tourism planning adviser for KulToura in Malvar, Batangas. Below are candidate admin actions already computed from recorded analytics, in priority order. "
        . "Pick up to 4 that are most useful (keep the order unless one is clearly more urgent) and rewrite each action as one clear, specific instruction of at most 22 words. "
        . "Do not add numbers, names, places or facts that are not in that candidate. Return JSON only: {\"recommendations\":[{\"id\":\"<candidate id>\",\"action\":\"<rewritten action>\"}]}. Candidates: "
        . json_encode($candidatesForAi, JSON_UNESCAPED_UNICODE);
    $prescriptiveResult = ai_cached_or_generate($conn, 'prescriptive_v2', $candidatesForAi, $prescriptivePrompt);
    $candidatesById = array_column($candidatesForAi, null, 'id');
    $aiRecommendations = [];
    foreach (is_array($prescriptiveResult['recommendations'] ?? null) ? $prescriptiveResult['recommendations'] : [] as $recommendation) {
        if (!is_array($recommendation) || count($aiRecommendations) >= 4) continue;
        $candidate = $candidatesById[(string) ($recommendation['id'] ?? '')] ?? null;
        $action = trim((string) ($recommendation['action'] ?? ''));
        if (!$candidate || $action === '' || mb_strlen($action) > 240 || !kt_text_numbers_supported($action, $candidate)) continue;
        $aiRecommendations[] = ['action' => $action, 'evidence' => $candidate['evidence']];
        unset($candidatesById[$candidate['id']]);
    }
    if ($aiRecommendations) {
        $prescriptiveRecommendations = $aiRecommendations;
        $prescriptiveAiUsed = true;
    } else {
        $prescriptiveAiIssue = 'AI wording was unavailable, so the recommendations below are shown as calculated.';
    }
}
$aiAnalysisLabel = $aiSummaryIsGenerated ? 'AI-written · numbers verified' : 'Calculated from recorded data';
$chartDataJson = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$filterFromValue = $customRange ? $dateFromInput : ($period === 'all' ? '' : $periodStart->format('Y-m-d'));
$filterToValue = $customRange ? $dateToInput : ($period === 'all' ? '' : $periodEndExclusive->modify('-1 day')->format('Y-m-d'));
?>
<style>
.main-content { background:#f0ebd8; color:#111e14; }
.dash-wrapper { max-width:1660px; padding:32px 38px 64px; }
.analytics-workspace { --aw-bg:#f0ebd8; --aw-card:#fff; --aw-line:#d9dfd2; --aw-muted:#657265; --aw-text:#111e14; --aw-chart-text:#354839; --aw-green:#2c4a2e; --aw-sage:#9fb88a; --aw-gold:#987123; --aw-highlight:#2c7a45; }
.analytics-workspace, .analytics-workspace * { box-sizing:border-box; }
.aw-heading { display:flex; flex-direction:column; gap:7px; margin:0 0 22px; }
.aw-heading .section-label { margin:0; }
.aw-heading h1 { margin:0; color:var(--aw-text); font:700 clamp(1.8rem,3vw,2.45rem)/1.15 'Playfair Display',serif; }
.aw-heading h1 em { color:var(--aw-green); }
.aw-heading .section-label { color:#536b4c; }
.aw-heading p { margin:0; max-width:65ch; color:var(--aw-muted); font:.88rem/1.5 'DM Sans',sans-serif; }
.aw-toolbar { display:grid; gap:12px; margin:0 0 20px; }
.aw-date-filter { display:grid; gap:16px; padding:16px; border:1px solid var(--aw-line); border-radius:16px; background:#fff; box-shadow:0 2px 8px rgba(38,52,43,.035); }
.aw-date-filter-head { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.aw-date-filter-head h2 { margin:0; color:var(--aw-text); font:600 1rem 'DM Sans',sans-serif; }
.aw-date-current { padding:5px 11px; border-radius:999px; background:#dcf4e6; color:#287448; font:600 .72rem 'DM Sans',sans-serif; white-space:nowrap; }
.aw-periods { display:flex; flex-wrap:wrap; gap:7px; min-width:0; }
.aw-periods a,.aw-period-custom { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:0 15px; border:1px solid transparent; border-radius:10px; background:#f0f3ee; color:#34443a; font:500 .82rem 'DM Sans',sans-serif; text-decoration:none; cursor:pointer; transition:background .15s ease,color .15s ease,border-color .15s ease; }
.aw-periods a.active,.aw-periods a:hover,.aw-period-custom.active,.aw-period-custom:hover { border-color:var(--aw-green); background:var(--aw-green); color:#fff; }
.aw-date-filter-divider { height:1px; background:#e9eee8; }
.aw-controls { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto auto; align-items:end; gap:10px; }
.aw-controls .date-range-field { display:grid; gap:6px; min-width:0; color:var(--aw-muted); font:500 .75rem 'DM Sans',sans-serif; }
.aw-controls .date-range-field input { width:100%; min-width:0; height:48px; padding:0 11px; border:1px solid var(--aw-line); border-radius:10px; background:#fff; color:var(--aw-text); color-scheme:light; font:400 .88rem 'DM Sans',sans-serif; }
.aw-controls .date-range-field input:focus { border-color:var(--aw-green); outline:3px solid rgba(74,124,71,.12); }
.aw-controls .date-range-submit,.aw-controls .analytics-report-export { display:inline-flex; align-items:center; justify-content:center; min-width:48px; height:44px; align-self:end; border:1px solid var(--aw-line); border-radius:10px; background:#fff; color:var(--aw-green); }
.aw-controls .date-range-submit { padding:0 17px; border-color:var(--aw-green); background:var(--aw-green); color:#fff; font:600 .82rem 'DM Sans',sans-serif; cursor:pointer; }
.aw-controls .analytics-report-export { width:44px; min-width:44px; text-decoration:none; }
.aw-controls .analytics-report-export:hover { border-color:var(--aw-green); background:#f0f6ed; }
.aw-tabs { display:flex; gap:5px; overflow-x:auto; padding:5px; margin:0 0 22px; border:1px solid var(--aw-line); border-radius:9px; background:#e9eee8; scrollbar-width:thin; }
.aw-tab { flex:0 0 auto; min-height:38px; padding:0 13px; border:0; border-radius:7px; background:transparent; color:var(--aw-muted); font:600 .76rem 'DM Sans',sans-serif; cursor:pointer; }
.aw-tab[aria-selected="true"] { background:var(--aw-green); color:#fff; }
.aw-section { display:none; }
.aw-section.is-active { display:block; }
.aw-section-head { display:flex; align-items:end; justify-content:space-between; gap:12px; margin:0 0 14px; }
.aw-section-head h2 { margin:0; color:var(--aw-text); font:700 1.2rem 'Playfair Display',serif; }
.aw-section-head p { margin:0; color:var(--aw-muted); font:.72rem 'DM Sans',sans-serif; }
.aw-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:14px; align-items:stretch; }
.aw-kpi-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin:0 0 20px; }
.aw-kpi-card { min-width:0; padding:15px 16px; border:1px solid var(--aw-line); border-top:3px solid var(--aw-green); border-radius:8px; background:var(--aw-card); box-shadow:0 1px 3px rgba(17,30,20,.06); }
.aw-kpi-card h2 { margin:0 0 9px; color:var(--aw-muted); font:600 .72rem 'DM Sans',sans-serif; }
.aw-kpi-card strong { display:block; color:var(--aw-text); font:700 clamp(1.45rem,2.1vw,2rem)/1.1 'Playfair Display',serif; }
.aw-kpi-card p { margin:8px 0 0; color:var(--aw-muted); font:.69rem/1.45 'DM Sans',sans-serif; }
.aw-card { grid-column:span 4; min-width:0; min-height:280px; padding:14px; border:1px solid var(--aw-line); border-radius:8px; background:var(--aw-card); box-shadow:0 1px 3px rgba(30,48,75,.05); }
.aw-card--wide { grid-column:span 8; }
.aw-card h3 { position:relative; min-height:0; margin:-14px -14px 12px; padding:12px 14px 11px; border-bottom:1px solid var(--aw-line); color:var(--aw-text); font:600 .88rem/1.35 'DM Sans',sans-serif; }
.aw-card h3::after { position:absolute; bottom:-1px; left:14px; width:96px; height:3px; background:var(--aw-highlight); content:""; }
.aw-card-canvas { position:relative; height:195px; }
.aw-card--wide .aw-card-canvas { height:225px; }
.aw-leaflet-map { height:220px; overflow:hidden; border-radius:6px; background:#edf1f7; }
.aw-card-empty { display:grid; min-height:205px; place-content:center; gap:8px; padding:16px; border:1px dashed var(--aw-line); border-radius:8px; color:var(--aw-muted); text-align:center; }
.aw-card-empty strong { color:var(--aw-green); font:600 .8rem 'DM Sans',sans-serif; }
.aw-card-empty span { max-width:42ch; font:.73rem/1.5 'DM Sans',sans-serif; }
.aw-kpi-value { padding:18px 0 8px; color:var(--aw-green); font:700 clamp(1.8rem,3vw,2.4rem)/1.1 'Playfair Display',serif; }
.aw-kpi-note { color:var(--aw-muted); font:.75rem/1.5 'DM Sans',sans-serif; }
.aw-card-insight { display:grid; gap:4px; margin:12px 0 0; padding:9px 10px; border-left:3px solid var(--aw-highlight); border-radius:0 5px 5px 0; background:#f3f7f1; }
.aw-card-insight strong { color:var(--aw-green); font:600 .65rem 'DM Sans',sans-serif; }
.aw-card-insight p { margin:0; color:#536257; font:.68rem/1.5 'DM Sans',sans-serif; }
.aw-card-suggestion { border-left-color:#b88b35; background:#fbf7ed; }
.aw-card-suggestion strong { color:#87651f; }
.aw-drilldown { min-height:1.2em; margin:4px 0 6px; color:var(--aw-green); font:.72rem 'DM Sans',sans-serif; }
.aw-word-cloud { display:flex; min-height:205px; align-content:center; align-items:center; justify-content:center; flex-wrap:wrap; gap:10px 14px; padding:16px; }
.aw-word-cloud span { color:var(--aw-green); font:600 1rem/1.2 'DM Sans',sans-serif; }
.aw-heatmap { display:grid; grid-template-columns:34px repeat(24,minmax(10px,1fr)); gap:3px; align-items:center; overflow-x:auto; padding:8px 0; }
.aw-heatmap-label { color:var(--aw-muted); font:500 .58rem 'DM Sans',sans-serif; text-align:center; }
.aw-heatmap-cell { width:100%; min-width:10px; height:13px; border:0; border-radius:3px; background:var(--aw-green); }
.aw-calendar { display:grid; grid-template-columns:repeat(7,minmax(20px,1fr)); gap:4px; max-height:210px; overflow:auto; padding:8px 0; }
.aw-calendar-cell { aspect-ratio:1; min-width:20px; border-radius:4px; background:var(--aw-green); }
.analytics-workspace .date-range-clear { color:var(--aw-muted); }
.analytics-workspace .date-range-error { color:#a33434; }
.analytics-workspace .aw-controls .analytics-report-export { border-color:var(--aw-line); background:#fff; color:var(--aw-green); }
.aw-ai-dashboard { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin:0 0 22px; }
.aw-ai-card { min-width:0; border:1px solid var(--aw-line); border-radius:8px; background:#fff; box-shadow:0 1px 3px rgba(30,48,75,.05); }
.aw-ai-card h2 { position:relative; margin:0; padding:13px 16px 12px; border-bottom:1px solid var(--aw-line); color:var(--aw-text); font:600 .95rem 'DM Sans',sans-serif; }
.aw-ai-card h2::after { position:absolute; bottom:-1px; left:16px; width:115px; height:3px; background:var(--aw-highlight); content:""; }
.aw-ai-card-body { padding:15px 16px 17px; }
.aw-ai-card-kicker { margin:0 0 9px; color:var(--aw-muted); font:500 .68rem 'DM Sans',sans-serif; }
.aw-descriptive-stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px; margin:14px 0; }
.aw-descriptive-stat { min-width:0; padding:9px 10px; border:1px solid var(--aw-line); border-radius:6px; background:#fbfcfa; }
.aw-descriptive-stat strong { display:block; overflow-wrap:anywhere; color:var(--aw-text); font:600 .86rem 'DM Sans',sans-serif; }
.aw-descriptive-stat span { display:block; margin-top:3px; color:var(--aw-muted); font:.62rem/1.35 'DM Sans',sans-serif; }
.aw-interest-list,.aw-category-forecast-list,.aw-prescriptive-list { display:grid; gap:7px; margin:11px 0 0; padding:0; list-style:none; }
.aw-interest-list li,.aw-category-forecast-list li { display:flex; justify-content:space-between; gap:10px; color:#45536a; font:.68rem/1.4 'DM Sans',sans-serif; }
.aw-interest-list strong,.aw-category-forecast-list strong { color:var(--aw-text); font-weight:600; text-align:right; }
.aw-category-forecast-list { padding-top:10px; border-top:1px solid var(--aw-line); }
.aw-prescriptive-list li { padding:9px 10px; border-left:3px solid #b88b35; border-radius:0 5px 5px 0; background:#fbf7ed; }
.aw-prescriptive-list strong { display:block; color:#34443a; font:600 .7rem/1.45 'DM Sans',sans-serif; }
.aw-prescriptive-list span { display:block; margin-top:4px; color:#657265; font:.64rem/1.45 'DM Sans',sans-serif; }
.aw-ai-badge { display:inline-flex; align-items:center; gap:6px; margin:0 0 10px; padding:4px 9px; border-radius:999px; background:#e8f0e4; color:#426d3e; font:600 .65rem 'DM Sans',sans-serif; }
.aw-ai-summary { margin:0; color:#45536a; font:.83rem/1.65 'DM Sans',sans-serif; }
.aw-ai-actions-title { margin:16px 0 8px; color:var(--aw-text); font:600 .75rem 'DM Sans',sans-serif; }
.aw-ai-actions { display:grid; gap:7px; margin:0; padding:0; list-style:none; }
.aw-ai-actions li { display:flex; gap:8px; align-items:flex-start; color:#536278; font:.74rem/1.5 'DM Sans',sans-serif; }
.aw-ai-actions li::before { width:7px; height:7px; flex:0 0 7px; margin-top:5px; border-radius:50%; background:var(--aw-sage); content:""; }
.aw-forecast-total { display:flex; align-items:baseline; gap:9px; margin-bottom:4px; }
.aw-forecast-total strong { color:var(--aw-green); font:700 2rem/1.1 'Playfair Display',serif; }
.aw-forecast-total span { color:var(--aw-muted); font:.72rem 'DM Sans',sans-serif; }
.aw-forecast-change { margin:0 0 12px; color:var(--aw-muted); font:.7rem 'DM Sans',sans-serif; }
.aw-forecast-chart { display:flex; align-items:flex-end; gap:8px; height:115px; padding:8px 2px 0; border-bottom:1px solid var(--aw-line); }
.aw-forecast-month { display:flex; min-width:0; height:100%; flex:1; flex-direction:column; align-items:center; justify-content:flex-end; gap:5px; }
.aw-forecast-month strong { color:#586779; font:500 .55rem 'DM Sans',sans-serif; white-space:nowrap; }
.aw-forecast-bar { width:min(100%,30px); min-height:4px; border-radius:4px 4px 0 0; background:var(--aw-sage); }
.aw-forecast-month.is-projected .aw-forecast-bar { background:var(--aw-green); }
.aw-forecast-month span { max-width:100%; overflow:hidden; color:#586779; font:500 .57rem 'DM Sans',sans-serif; text-overflow:ellipsis; white-space:nowrap; }
.aw-forecast-note { margin:9px 0 0; color:var(--aw-muted); font:.66rem/1.45 'DM Sans',sans-serif; }
@media(max-width:1100px){.aw-card{grid-column:span 6}.aw-card--wide{grid-column:span 12}.dash-wrapper{padding:30px 24px 56px}}
@media(max-width:900px){.aw-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:1050px){.aw-ai-dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.aw-ai-card--prescriptive{grid-column:span 2}}
@media(max-width:760px){.aw-ai-dashboard{grid-template-columns:1fr}.aw-ai-card--prescriptive{grid-column:auto}}
@media(max-width:700px){.aw-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.aw-card,.aw-card--wide{grid-column:span 2;min-height:260px}.aw-section-head{align-items:flex-start;flex-direction:column}.aw-card-canvas,.aw-card--wide .aw-card-canvas{height:205px}.dash-wrapper{padding:24px 15px 44px}.aw-controls{grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto auto;gap:7px}.aw-controls .date-range-field input{height:44px;padding:0 7px;font-size:.75rem}.aw-controls .date-range-submit{min-width:0;padding:0 11px}.aw-controls .analytics-report-export{width:40px;min-width:40px;height:40px}.aw-periods a,.aw-period-custom{min-height:40px;padding:0 12px;font-size:.76rem}.aw-kpi-grid{gap:10px}}
@media(max-width:430px){.aw-date-filter{padding:13px;gap:13px}.aw-controls{grid-template-columns:1fr 1fr}.aw-controls .date-range-submit{width:100%}.aw-controls .analytics-report-export{width:100%;height:44px}.aw-date-filter-head h2{font-size:.92rem}}
</style>
<div class="analytics-workspace">
    <div class="admin-site-switch-row"><a class="admin-site-switch" href="<?php echo BASE_URL; ?>/index.php"><i data-lucide="external-link" class="lucide"></i> View user site</a></div>
    <header class="aw-heading">
        <div class="section-label">Tourism Insights</div><h1>Tourism <em>analytics</em></h1>
        <p>Explore visitor activity, destination performance, cultural engagement, and audience trends.</p>
    </header>
    <div class="aw-toolbar">
        <section class="aw-date-filter" aria-label="Analytics date filter">
            <div class="aw-date-filter-head"><h2>Date Filter</h2><span class="aw-date-current"><?php echo htmlspecialchars($periodLabels[$period] ?? 'Selected range'); ?></span></div>
            <nav class="aw-periods" aria-label="Quick date ranges">
                <?php foreach (['today'=>'Today','yesterday'=>'Yesterday','7days'=>'7 Days','30days'=>'30 Days','month'=>'This Month'] as $key=>$label): $periodIsActive = $period === $key; ?><a href="?period=<?php echo $periodIsActive ? 'all' : $key; ?>" class="<?php echo $periodIsActive ? 'active' : ''; ?>"<?php echo $periodIsActive ? ' aria-current="true" title="Click again to clear this filter and show all time"' : ''; ?>><?php echo $label; ?></a><?php endforeach; ?>
                <button class="aw-period-custom<?php echo $customRange ? ' active' : ''; ?>" type="button" aria-pressed="<?php echo $customRange ? 'true' : 'false'; ?>" data-custom-range>Custom</button>
            </nav>
            <div class="aw-date-filter-divider" aria-hidden="true"></div>
            <form class="aw-controls date-range-form" method="get" action="admindashboard.php" aria-label="Choose a date range">
                <input type="hidden" name="period" value="custom">
                <label class="date-range-field">Start date<input type="date" name="date_from" value="<?php echo htmlspecialchars($filterFromValue); ?>" max="<?php echo $today->format('Y-m-d'); ?>" required></label>
                <label class="date-range-field">End date<input type="date" name="date_to" value="<?php echo htmlspecialchars($filterToValue); ?>" max="<?php echo $today->format('Y-m-d'); ?>" required></label>
                <button class="date-range-submit" type="submit">Apply</button>
                <a class="analytics-report-export" href="<?php echo BASE_URL; ?>/admin/analytics_export.php?period=<?php echo urlencode($period); ?>&amp;date_from=<?php echo urlencode($dateFromInput); ?>&amp;date_to=<?php echo urlencode($dateToInput); ?>" title="Download full Excel analytics report" aria-label="Download full Excel analytics report"><i data-lucide="download" class="lucide" aria-hidden="true"></i></a>
            </form>
        </section>
        <?php if ($dateRangeError !== ''): ?><div class="date-range-error" role="alert"><?php echo htmlspecialchars($dateRangeError); ?></div><?php endif; ?>
    </div>
    <?php if ($demoDataStatus !== ''): ?><div class="aw-demo-notice is-<?php echo $demoDataStatus === 'error' ? 'error' : 'ok'; ?>" role="status"><?php echo htmlspecialchars(['loaded' => 'Demo analytics data was loaded: five years of sample visits now fill the Audience tab and other charts.', 'removed' => 'Demo analytics data was removed. The dashboard now shows only real visits.', 'error' => (string) ($_GET['demo_message'] ?? 'The demo data action could not be completed.')][$demoDataStatus] ?? ''); ?></div><?php endif; ?>
    <?php if ($audienceDemoPresent): ?>
    <div class="aw-demo-banner" role="note">
        <i data-lucide="flask-conical" class="lucide" aria-hidden="true"></i>
        <div><strong>Demo analytics data is loaded.</strong> Five years of sample visits are included in these numbers, so they do not reflect real visitors. Remove it before using the dashboard for real decisions.</div>
        <?php if ($canManageDemoData): ?><form method="post" action="<?php echo BASE_URL; ?>/admin/demo_data_actions.php" onsubmit="return confirm('Remove all demo analytics data? Real visits are not affected.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove"><button type="submit" class="btn-ghost">Remove demo data</button></form><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($dashboardKpis): ?>
    <section class="aw-kpi-grid" aria-label="Key performance indicators">
        <?php foreach ($dashboardKpis as $kpi): $kpiId = $kpi['section'] . '_' . $kpi['index']; ?>
        <article class="aw-kpi-card"><h2><?php echo htmlspecialchars($kpi['title']); ?></h2><strong<?php echo !empty($kpi['value_is_text']) ? ' class="aw-kpi-text" title="' . htmlspecialchars($kpi['value']) . '"' : ''; ?>><?php echo htmlspecialchars($kpi['value']); ?></strong><p<?php echo !empty($kpi['trend']) ? ' class="aw-kpi-trend is-' . $kpi['trend'] . '"' : ''; ?>><?php echo htmlspecialchars($kpi['description'] ?? $aiCardInterpretations[$kpiId] ?? 'Selected period · actual records'); ?></p></article>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <section class="aw-ai-dashboard" aria-label="Descriptive, predictive, and prescriptive analytics">
        <article class="aw-ai-card">
            <h2><i data-lucide="file-chart-column" class="lucide" style="width:1rem;height:1rem;color:var(--aw-green);vertical-align:-2px;"></i> Descriptive analytics</h2>
            <div class="aw-ai-card-body">
                <p class="aw-ai-card-kicker">What happened in <?php echo htmlspecialchars($selectedPeriodLabel); ?><?php if ($rangeLabel !== '' && $period !== 'custom'): ?> (<?php echo htmlspecialchars($rangeLabel); ?>)<?php endif; ?></p>
                <span class="aw-ai-badge"><?php echo htmlspecialchars($aiAnalysisLabel); ?></span>
                <p class="aw-ai-summary"><?php echo htmlspecialchars($aiSummary); ?></p>
                <div class="aw-descriptive-stats" aria-label="Selected-period totals across the site">
                    <?php foreach ($descriptiveHighlights as $highlight): $highlightChange = kt_format_change($highlight['change']); ?><div class="aw-descriptive-stat"><strong><?php echo htmlspecialchars($highlight['value']); ?><?php if ($highlightChange !== ''): ?> <em class="aw-change <?php echo $highlight['change'] > 0 ? 'is-up' : ($highlight['change'] < 0 ? 'is-down' : ''); ?>" title="vs <?php echo htmlspecialchars($previousRangeLabel); ?>"><?php echo htmlspecialchars($highlightChange); ?></em><?php endif; ?></strong><span><?php echo htmlspecialchars($highlight['label']); ?></span></div><?php endforeach; ?>
                </div>
                <p class="aw-forecast-note"><?php echo $previousRangeLabel !== '' ? 'Percentages compare with ' . htmlspecialchars($previousRangeLabel) . '.' : 'No comparison is shown for All Time.'; ?> Totals come from page views, listing views, searches, reviews, sign-ups, favourites, travel diaries and visitor sessions.</p>
                <strong class="aw-ai-actions-title">Most viewed listings</strong>
                <?php if ($descriptiveTopContent): ?><ul class="aw-interest-list"><?php foreach (array_slice($descriptiveTopContent, 0, 3) as $topContent): ?><li><span><?php echo htmlspecialchars((string) $topContent['name']); ?> <small>(<?php echo htmlspecialchars((string) ($topContent['category_label'] ?? 'Tourism')); ?>)</small></span><strong><?php echo htmlspecialchars(kt_plural((int) $topContent['views'], 'view')); ?></strong></li><?php endforeach; ?></ul><?php else: ?><p class="aw-forecast-note">No listing views were recorded in this period.</p><?php endif; ?>
                <strong class="aw-ai-actions-title">What visitors searched for</strong>
                <?php if (!empty($topSearches)): ?><ul class="aw-interest-list"><?php foreach (array_slice($topSearches, 0, 3) as $searchInterest): ?><li><span>“<?php echo htmlspecialchars((string) $searchInterest['query']); ?>”</span><strong><?php echo htmlspecialchars(kt_plural((int) $searchInterest['count'], 'search', 'searches')); ?></strong></li><?php endforeach; ?></ul><?php else: ?><p class="aw-forecast-note">No searches were recorded in this period.</p><?php endif; ?>
            </div>
        </article>
        <article class="aw-ai-card">
            <h2><i data-lucide="trending-up" class="lucide" style="width:1rem;height:1rem;color:var(--aw-green);vertical-align:-2px;"></i> Predictive analytics</h2>
            <div class="aw-ai-card-body">
                <p class="aw-ai-card-kicker">What is likely next · site visits</p>
                <?php if ($trafficForecast['available']): $forecastCurrent = $trafficForecast['current']; $forecastNext = $trafficForecast['next']; ?>
                    <div class="aw-forecast-pair">
                        <div class="aw-forecast-total"><span><?php echo htmlspecialchars($forecastCurrent['label']); ?> (in progress)</span><strong>≈ <?php echo number_format($forecastCurrent['estimate']); ?></strong><small><?php echo number_format($forecastCurrent['so_far']); ?> so far · <?php echo htmlspecialchars(kt_plural($forecastCurrent['days_left'], 'day')); ?> left · likely <?php echo number_format($forecastCurrent['low']); ?>–<?php echo number_format($forecastCurrent['high']); ?></small></div>
                        <div class="aw-forecast-total"><span><?php echo htmlspecialchars($forecastNext['label']); ?></span><strong>≈ <?php echo number_format($forecastNext['estimate']); ?></strong><small>likely <?php echo number_format($forecastNext['low']); ?>–<?php echo number_format($forecastNext['high']); ?></small></div>
                    </div>
                    <p class="aw-forecast-change"><?php echo htmlspecialchars($trafficForecast['confidence']); ?> confidence · about <?php echo number_format($trafficForecast['daily_rate'], $trafficForecast['daily_rate'] < 10 ? 1 : 0); ?> visits a day recently<?php if ($trafficForecast['trend_pct'] !== null): ?> · last 2 weeks <?php echo htmlspecialchars(kt_format_change($trafficForecast['trend_pct'])); ?> vs the 2 weeks before<?php endif; ?></p>
                    <div class="aw-forecast-chart" role="img" aria-label="Monthly site visits with the current-month projection and next-month forecast">
                        <?php foreach ($trafficForecast['rows'] as $forecastRow): $forecastHeight = max(4, (int) round(((int) $forecastRow['value'] / $forecastChartMax) * 100)); $soFarHeight = isset($forecastRow['so_far']) ? max(3, (int) round(((int) $forecastRow['so_far'] / max(1, (int) $forecastRow['value'])) * 100)) : 0; ?>
                        <div class="aw-forecast-month is-<?php echo $forecastRow['kind']; ?>" title="<?php echo htmlspecialchars($forecastRow['label'] . ': ' . ($forecastRow['kind'] === 'actual' ? number_format((int) $forecastRow['value']) . ' visits' : ($forecastRow['kind'] === 'current' ? number_format((int) $forecastRow['so_far']) . ' so far, about ' . number_format((int) $forecastRow['value']) . ' projected' : 'about ' . number_format((int) $forecastRow['value']) . ' forecast'))); ?>">
                            <strong><?php echo number_format((int) $forecastRow['value']); ?></strong><i class="aw-forecast-bar" style="height:<?php echo $forecastHeight; ?>%"><?php if ($forecastRow['kind'] === 'current'): ?><b style="height:<?php echo $soFarHeight; ?>%"></b><?php endif; ?></i><span><?php echo htmlspecialchars($forecastRow['label']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="aw-forecast-legend"><span class="is-actual"></span>Recorded <span class="is-current"></span>This month so far <span class="is-projected"></span>Projected</p>
                    <p class="aw-forecast-note">Based on the last 8 weeks of daily visits, with unusual spikes and dips trimmed out. Updates every day as new visits are recorded. These are estimates, not guarantees.</p>
                <?php else: ?>
                    <div class="aw-forecast-total"><strong>—</strong><span>forecast unavailable</span></div>
                    <p class="aw-forecast-note">Fewer than 7 days in the last 4 weeks had recorded visits, so a projection would not be reliable yet.</p>
                <?php endif; ?>
                <strong class="aw-ai-actions-title">Category interest · next 30 days</strong>
                <?php if ($categoryOutlook): ?><ul class="aw-category-forecast-list"><?php foreach (array_slice($categoryOutlook, 0, 5) as $outlook): $outlookChange = kt_format_change($outlook['change_pct']); ?><li><span><?php echo htmlspecialchars($outlook['label']); ?></span><strong>≈ <?php echo htmlspecialchars(kt_plural($outlook['estimate'], 'view')); ?><?php if ($outlookChange !== ''): ?> <em class="aw-change <?php echo $outlook['change_pct'] > 0 ? 'is-up' : ($outlook['change_pct'] < 0 ? 'is-down' : ''); ?>"><?php echo htmlspecialchars($outlookChange); ?></em><?php elseif ($outlook['previous'] === 0): ?> <em class="aw-change is-up">new</em><?php endif; ?></strong></li><?php endforeach; ?></ul><p class="aw-forecast-note">From listing views in the last 28 days; the % compares with the 28 days before.</p><?php else: ?><p class="aw-forecast-note">Not enough listing views in the last 8 weeks to project category interest yet.</p><?php endif; ?>
            </div>
        </article>
        <article class="aw-ai-card aw-ai-card--prescriptive">
            <h2><i data-lucide="list-checks" class="lucide" style="width:1rem;height:1rem;color:var(--aw-green);vertical-align:-2px;"></i> Prescriptive analytics</h2>
            <div class="aw-ai-card-body">
                <p class="aw-ai-card-kicker">What to do next, most important first</p>
                <span class="aw-ai-badge"><?php echo $prescriptiveAiUsed ? 'AI-worded · evidence from recorded data' : 'Ranked from recorded data'; ?></span>
                <?php if (!$prescriptiveAiUsed && $aiTextActive && $prescriptiveAiIssue): ?><p class="aw-forecast-note" role="status"><?php echo htmlspecialchars($prescriptiveAiIssue); ?></p><?php endif; ?>
                <ol class="aw-prescriptive-list" aria-label="Recommended admin actions with supporting evidence"><?php foreach ($prescriptiveRecommendations as $recommendation): ?><li><strong><?php echo htmlspecialchars($recommendation['action']); ?></strong><span><?php echo htmlspecialchars($recommendation['evidence']); ?></span></li><?php endforeach; ?></ol>
                <p class="aw-forecast-note">Checks pending review reports, searches with no results, low ratings, traffic changes, rising or falling categories, popular listings without reviews or promotion, and listings nobody viewed.</p>
            </div>
        </article>
    </section>
    <div class="aw-tabs" role="tablist" aria-label="Analytics sections">
        <?php $workspaceTabIcons = ['traffic' => 'activity', 'destination' => 'map-pin', 'culture' => 'landmark', 'search' => 'search', 'community' => 'messages-square', 'audience' => 'users', 'recommendations' => 'sparkles']; ?>
        <?php foreach ($workspaceTabs as $key=>$label): ?><button class="aw-tab" type="button" role="tab" id="aw-tab-<?php echo $key; ?>" aria-controls="aw-section-<?php echo $key; ?>" aria-selected="<?php echo $key === $initialWorkspaceTab ? 'true' : 'false'; ?>"><i data-lucide="<?php echo $workspaceTabIcons[$key] ?? 'circle'; ?>" class="lucide" aria-hidden="true"></i><span><?php echo htmlspecialchars($label); ?></span></button><?php endforeach; ?>
    </div>
    <script type="application/json" id="aw-chart-data"><?php echo $chartDataJson; ?></script>
    <?php foreach ($workspaceMetrics as $sectionKey=>$metrics): ?>
    <section class="aw-section <?php echo $sectionKey === $initialWorkspaceTab ? 'is-active' : ''; ?>" role="tabpanel" id="aw-section-<?php echo $sectionKey; ?>" aria-labelledby="aw-tab-<?php echo $sectionKey; ?>" <?php echo $sectionKey === $initialWorkspaceTab ? '' : 'hidden'; ?>>
        <div class="aw-section-head"><div><h2><?php echo htmlspecialchars($workspaceTabs[$sectionKey]); ?></h2></div><p><?php echo htmlspecialchars($periodLabels[$period]); ?><?php if ($rangeLabel !== '' && $period !== 'custom'): ?> <span class="aw-range">(<?php echo htmlspecialchars($rangeLabel); ?>)</span><?php endif; ?> · Based on recorded data</p></div>
        <?php if ($sectionKey === 'audience' && !$audienceDemoPresent): ?>
        <div class="aw-demo-banner is-empty" role="note">
            <i data-lucide="users" class="lucide" aria-hidden="true"></i>
            <div><strong><?php echo $visitQuality['tracked'] ? 'Only a little visitor data so far.' : 'No visitor data yet for this period.'; ?></strong> Visitor tracking started recently, so these charts fill in as people browse the public site.<?php if ($canManageDemoData): ?> To see how they work now, load five years of clearly marked demo data (you can remove it at any time).<?php endif; ?></div>
            <?php if ($canManageDemoData): ?><form method="post" action="<?php echo BASE_URL; ?>/admin/demo_data_actions.php" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Loading… (up to a minute)';"><?php echo csrf_field(); ?><input type="hidden" name="action" value="load"><button type="submit" class="btn-primary">Load 5-year demo data</button></form><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($sectionKey === 'community'): include __DIR__ . '/reviews_admin_panel.php'; else: ?>
        <div class="aw-grid">
            <?php foreach ($metrics as $metricIndex => [$title,$kind,$dataKey,$note]): if ($kind === 'kpi') continue; $cardId = $sectionKey . '_' . $metricIndex; $payload = $dataKey ? ($chartData[$dataKey] ?? ['labels'=>[],'values'=>[]]) : ['labels'=>[],'values'=>[]]; $isHeatmap = $kind === 'heatmap'; $isMap = $kind === 'map'; $isCalendar=$kind === 'calendar'; $hasData = $isHeatmap ? (is_array($payload) && array_sum(array_map('array_sum', $payload)) > 0) : ($isMap ? !empty($payload) : ($isCalendar ? (!empty($payload) && array_sum(array_column($payload,'value'))>0) : (!empty($payload['labels']) && !empty($payload['values']) && array_sum(array_map('floatval',$payload['values'])) > 0))); $mapId='aw-map-'.$sectionKey.'-'.substr(sha1($title),0,7); $cardWide=$isHeatmap || $isMap || $kind === 'wordcloud' || $kind === 'rank' || $kind === 'rank-low' || (in_array($kind, ['line', 'area', 'bar'], true) && count($payload['labels'] ?? []) > 12); ?>
            <article class="aw-card<?php echo $cardWide ? ' aw-card--wide' : ''; ?>"><h3><?php echo htmlspecialchars($title); ?></h3>
                <?php if ($kind === 'kpi'): ?><div class="aw-kpi-value"><?php echo htmlspecialchars($note); ?></div><div class="aw-kpi-note">Selected period · actual records</div>
                <?php elseif ($kind === 'kpi-empty'): ?><div class="aw-card-empty"><strong>Not tracked yet</strong><span><?php echo htmlspecialchars($note); ?></span></div>
                <?php elseif ($isMap && $hasData): ?><div class="aw-leaflet-map" id="<?php echo $mapId; ?>" data-aw-map="<?php echo htmlspecialchars($dataKey); ?>" aria-label="<?php echo htmlspecialchars($title); ?>"></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php elseif ($isHeatmap && $hasData): ?><div class="aw-heatmap" aria-label="Activity by weekday and hour"><?php $dayLabels=['','Sun','Mon','Tue','Wed','Thu','Fri','Sat']; $heatMax=max(array_map('max',$payload)); ?><span></span><?php for($hour=0;$hour<24;$hour++): ?><span class="aw-heatmap-label"><?php echo $hour%3===0?sprintf('%02d',$hour):''; ?></span><?php endfor; ?><?php for($day=1;$day<=7;$day++): ?><span class="aw-heatmap-label"><?php echo $dayLabels[$day]; ?></span><?php for($hour=0;$hour<24;$hour++): $count=(int)$payload[$day][$hour]; $opacity=$heatMax>0?max(.08,$count/$heatMax):.08; ?><span class="aw-heatmap-cell" style="opacity:<?php echo number_format($opacity,2); ?>" title="<?php echo htmlspecialchars($dayLabels[$day] . ' ' . sprintf('%02d:00', $hour) . ' - ' . number_format($count)); ?>"></span><?php endfor; ?><?php endfor; ?></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php elseif ($isCalendar && $hasData): ?><div class="aw-calendar" aria-label="Daily interaction frequency"><?php $calendarMax=max(array_column($payload,'value')); $calendarOffset=(int)(new DateTimeImmutable($payload[0]['date']))->format('w'); for($blank=0;$blank<$calendarOffset;$blank++): ?><span></span><?php endfor; foreach($payload as $calendarDay): $opacity=$calendarMax>0?max(.08,$calendarDay['value']/$calendarMax):.08; ?><span class="aw-calendar-cell" style="opacity:<?php echo number_format($opacity,2); ?>" title="<?php echo htmlspecialchars($calendarDay['label'] . ' - ' . number_format($calendarDay['value']) . ' events'); ?>"></span><?php endforeach; ?></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php elseif ($hasData && $dataKey && $kind === 'wordcloud'): ?><div class="aw-word-cloud"><?php $wordMax = max(1, max($payload['values'])); foreach ($payload['labels'] as $wordIndex => $word): $wordScale = 1 + .9 * ((float) $payload['values'][$wordIndex] / $wordMax); ?><span style="font-size:<?php echo number_format($wordScale, 2); ?>rem" title="<?php echo (int) $payload['values'][$wordIndex]; ?> searches"><?php echo htmlspecialchars($word); ?></span><?php endforeach; ?></div>
                <?php elseif ($kind === 'stats' && $hasData): ?><div class="aw-stat-tiles"><?php foreach ($payload['labels'] as $statIndex => $statLabel): ?><div class="aw-stat-tile"><strong><?php echo htmlspecialchars((string) $payload['values'][$statIndex]); ?></strong><span><?php echo htmlspecialchars($statLabel); ?></span></div><?php endforeach; ?></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php elseif ($hasData && $dataKey): ?><div class="aw-card-canvas"><canvas data-aw-chart="<?php echo htmlspecialchars($kind); ?>" data-aw-key="<?php echo htmlspecialchars($dataKey); ?>" role="img" aria-label="<?php echo htmlspecialchars($title); ?>"></canvas></div><div class="aw-drilldown" aria-live="polite"></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php else: ?><div class="aw-card-empty"><strong><?php echo $kind === 'map' ? 'Map data unavailable' : 'More data needed'; ?></strong><span><?php echo htmlspecialchars($note ?: 'There are no matching records in this period.'); ?></span></div><?php endif; ?>
                <?php $cardInsightIsAi = isset($aiDashboardResult['card_insights'][$cardId]) && is_string($aiDashboardResult['card_insights'][$cardId]) && trim($aiDashboardResult['card_insights'][$cardId]) !== ''; ?>
                <aside class="aw-card-insight"><strong><i data-lucide="sparkles" class="lucide" style="width:.8rem;height:.8rem;vertical-align:-2px;"></i> <?php echo $cardInsightIsAi ? 'AI interpretation' : 'Data interpretation'; ?></strong><p><?php echo htmlspecialchars($aiCardInterpretations[$cardId] ?? 'There are not enough recorded values to interpret this metric.'); ?></p></aside>
                <?php $cardSuggestionIsAi = isset($aiDashboardResult['card_suggestions'][$cardId]) && is_string($aiDashboardResult['card_suggestions'][$cardId]) && trim($aiDashboardResult['card_suggestions'][$cardId]) !== ''; ?>
                <aside class="aw-card-insight aw-card-suggestion"><strong><i data-lucide="lightbulb" class="lucide" style="width:.8rem;height:.8rem;vertical-align:-2px;"></i> <?php echo $cardSuggestionIsAi ? 'AI suggestion' : 'Data-based suggestion'; ?></strong><p><?php echo htmlspecialchars($aiCardSuggestions[$cardId] ?? ($aiCardSuggestionFallbacks[$cardId] ?? 'Collect more data before making a decision based on this metric.')); ?></p></aside>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/analytics-workspace.js?v=20261124-duration"></script>
