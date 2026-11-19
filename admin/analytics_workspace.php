<?php
// Analytics workspace view. Every plotted value comes from the current database queries.
$destinationRows = $cultureRows = $restaurantRows = $destinationRatedRows = $cultureRatedRows = [];
foreach ($tourismCategoryAnalytics as $categoryKey => $stats) {
    foreach ($stats['top_items'] as $item) {
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
foreach (['views'=>'Views','clicks'=>'Clicks','searches'=>'Searches'] as $field=>$label) $cultureEngagementDatasets[]=['label'=>$label,'data'=>array_map(static fn($key)=>$tourismCategoryAnalytics[$key][$field],$culturalKeys),'backgroundColor'=>['Views'=>'#a8c28e','Clicks'=>'#d8b66c','Searches'=>'#c98267'][$label]];
$sectionEngagementDatasets=[];
foreach (['views'=>'Views','clicks'=>'Clicks','searches'=>'Searches'] as $field=>$label) $sectionEngagementDatasets[]=['label'=>$label,'data'=>array_column(array_values($tourismCategoryAnalytics),$field),'backgroundColor'=>['Views'=>'#a8c28e','Clicks'=>'#d8b66c','Searches'=>'#c98267'][$label]];
$ratingLabels = ['1 star', '2 stars', '3 stars', '4 stars', '5 stars'];
$ratingValues = array_values($ratingDistribution);
$cultureRatingDistribution = array_fill(1,5,0);
foreach (['product','fiesta','person'] as $cultureType) foreach (($categoryRatingDistribution[$cultureType] ?? []) as $rating=>$count) $cultureRatingDistribution[(int)$rating] += (int)$count;
$weekdayChart = ['labels' => ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], 'values' => array_values($weekdayCounts)];
$pageLabels = array_map(static fn($row) => $row['label'], $topPages);
$pageValues = array_map(static fn($row) => $row['views'], $topPages);
$searchLabels = array_map(static fn($row) => $row['query'], $topSearches);
$searchValues = array_map(static fn($row) => $row['count'], $topSearches);
$destinationChart = ['labels' => array_column($destinationRows, 'name'), 'values' => array_column($destinationRows, 'views')];
$destinationLeastChart = ['labels' => array_column(array_slice($destinationAllRows, 0, 10), 'name'), 'values' => array_column(array_slice($destinationAllRows, 0, 10), 'views')];
$cultureChart = ['labels' => array_column($cultureRows, 'name'), 'values' => array_column($cultureRows, 'views')];
$cultureLeastChart = ['labels' => array_column(array_slice($cultureAllRows, 0, 10), 'name'), 'values' => array_column(array_slice($cultureAllRows, 0, 10), 'views')];
$destinationScatter = array_values(array_map(static fn($row) => ['x'=>(int)$row['views'],'y'=>(float)$row['average_rating'],'label'=>$row['name']], array_filter($destinationRatedRows, static fn($row) => ($row['views']??0)>0)));
$cultureScatter = array_values(array_map(static fn($row) => ['x'=>(int)$row['views'],'y'=>(float)$row['average_rating'],'label'=>$row['name']], array_filter($cultureRatedRows, static fn($row) => ($row['views']??0)>0)));
$restaurantChart = ['labels' => array_column($restaurantRows, 'name'), 'values' => array_column($restaurantRows, 'views')];
$dailyChart = ['labels' => array_column($trafficTrend, 'label'), 'values' => array_column($trafficTrend, 'value')];
$monthlyVisitBuckets = $yearlyVisitBuckets = [];
$bucketStmt = $conn->prepare("SELECT DATE_FORMAT(viewed_at,'%Y-%m') bucket, COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY bucket ORDER BY bucket");
if ($bucketStmt) { $bucketStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql); $bucketStmt->execute(); foreach ($bucketStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $monthlyVisitBuckets[$row['bucket']] = (int)$row['total']; $bucketStmt->close(); }
$yearStmt = $conn->prepare("SELECT YEAR(viewed_at) bucket, COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY YEAR(viewed_at) ORDER BY bucket");
if ($yearStmt) { $yearStmt->bind_param('ss', $periodStartSql, $periodEndExclusiveSql); $yearStmt->execute(); foreach ($yearStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $yearlyVisitBuckets[(string)$row['bucket']] = (int)$row['total']; $yearStmt->close(); }
$monthlyVisitTrend = [];
if ($monthlyVisitBuckets) { $fromMonth = new DateTimeImmutable(array_key_first($monthlyVisitBuckets) . '-01'); $toMonth = new DateTimeImmutable(array_key_last($monthlyVisitBuckets) . '-01'); for ($month=$fromMonth; $month<=$toMonth; $month=$month->modify('+1 month')) { $key=$month->format('Y-m'); $monthlyVisitTrend[]=['label'=>$month->format('M Y'),'value'=>$monthlyVisitBuckets[$key]??0]; } }
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
$cultureMonthBuckets = $fiestaMonthBuckets = $commentMonthBuckets = $searchMonthBuckets = $itineraryMonthBuckets = $engagementMonthBuckets = $registrationBuckets = [];
$cultureMonthStmt = $conn->prepare("SELECT DATE_FORMAT(viewed_at,'%Y-%m') bucket,COUNT(*) total FROM item_views WHERE item_type IN ('product','fiesta','person') AND viewed_at>=? AND viewed_at<? GROUP BY bucket ORDER BY bucket");
if ($cultureMonthStmt) { $cultureMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $cultureMonthStmt->execute(); foreach ($cultureMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $cultureMonthBuckets[$row['bucket']]=(int)$row['total']; $cultureMonthStmt->close(); }
$fiestaMonthStmt = $conn->prepare("SELECT DATE_FORMAT(viewed_at,'%Y-%m') bucket,COUNT(*) total FROM item_views WHERE item_type='fiesta' AND viewed_at>=? AND viewed_at<? GROUP BY bucket ORDER BY bucket");
if ($fiestaMonthStmt) { $fiestaMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $fiestaMonthStmt->execute(); foreach ($fiestaMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $fiestaMonthBuckets[$row['bucket']]=(int)$row['total']; $fiestaMonthStmt->close(); }
$commentMonthStmt = $conn->prepare("SELECT DATE_FORMAT(activity_date,'%Y-%m') bucket,COUNT(*) total FROM user_activity_log WHERE activity_type='comment' AND activity_date>=? AND activity_date<? GROUP BY bucket ORDER BY bucket");
if ($commentMonthStmt) { $commentMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $commentMonthStmt->execute(); foreach ($commentMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $commentMonthBuckets[$row['bucket']]=(int)$row['total']; $commentMonthStmt->close(); }
$searchMonthStmt = $conn->prepare("SELECT DATE_FORMAT(activity_date,'%Y-%m') bucket,COUNT(*) total FROM user_activity_log WHERE activity_type='search' AND activity_date>=? AND activity_date<? GROUP BY bucket ORDER BY bucket");
if ($searchMonthStmt) { $searchMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $searchMonthStmt->execute(); foreach ($searchMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $searchMonthBuckets[$row['bucket']]=(int)$row['total']; $searchMonthStmt->close(); }
$itineraryMonthStmt = $conn->prepare("SELECT DATE_FORMAT(activity_date,'%Y-%m') bucket,COUNT(*) total FROM user_activity_log WHERE activity_type='generate' AND activity_date>=? AND activity_date<? GROUP BY bucket ORDER BY bucket");
if ($itineraryMonthStmt) { $itineraryMonthStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $itineraryMonthStmt->execute(); foreach ($itineraryMonthStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $itineraryMonthBuckets[$row['bucket']]=(int)$row['total']; $itineraryMonthStmt->close(); }
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
    $sentimentStmt = $conn->prepare("SELECT review_text FROM item_reviews WHERE status='visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at>=? AND created_at<?");
    if ($sentimentStmt) { $sentimentStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $sentimentStmt->execute(); foreach ($sentimentStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $reviewRow) { $score=analytics_review_sentiment((string)$reviewRow['review_text']); $sentimentCounts[$score>0?'Positive':($score<0?'Negative':'Neutral')]++; } $sentimentStmt->close(); }
}
$destinationMapPoints = [];
$mapStmt = $conn->prepare("SELECT d.destination_name,d.latitude,d.longitude,COUNT(v.view_id) views FROM destination d LEFT JOIN item_views v ON v.item_type=d.category AND v.item_id=d.destination_id AND v.viewed_at>=? AND v.viewed_at<? WHERE d.status='active' AND d.latitude IS NOT NULL AND d.longitude IS NOT NULL GROUP BY d.destination_id,d.destination_name,d.latitude,d.longitude");
if ($mapStmt) { $mapStmt->bind_param('ss',$periodStartSql,$periodEndExclusiveSql); $mapStmt->execute(); foreach ($mapStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $destinationMapPoints[]=['name'=>(string)$row['destination_name'],'lat'=>(float)$row['latitude'],'lng'=>(float)$row['longitude'],'views'=>(int)$row['views']]; $mapStmt->close(); }
$chartData = [
    'weekday' => $weekdayChart, 'pages' => ['labels' => $pageLabels, 'values' => $pageValues], 'searches' => ['labels' => $searchLabels, 'values' => $searchValues],
    'destinationRanks' => $destinationChart, 'destinationLeast' => $destinationLeastChart, 'cultureRanks' => $cultureChart, 'cultureLeast' => $cultureLeastChart, 'restaurants' => $restaurantChart,
    'destinationCategories' => ['labels' => $destinationCategoryLabels, 'values' => $destinationCategoryViews],
    'cultureCategories' => ['labels' => $cultureCategoryLabels, 'values' => $cultureCategoryViews],
    'cultureEngagement' => ['labels'=>$cultureEngagementLabels,'values'=>$cultureEngagementValues,'datasets'=>$cultureEngagementDatasets],
    'sectionEngagement' => ['labels'=>$categoryLabels,'values'=>$categoryViews,'datasets'=>$sectionEngagementDatasets],
    'allCategories' => ['labels' => $categoryLabels, 'values' => $categoryViews], 'ratings' => ['labels' => $ratingLabels, 'values' => $ratingValues],
    'cultureRatings' => ['labels'=>$ratingLabels,'values'=>array_values($cultureRatingDistribution)],
    'signup' => $bucketSeries($registrationBuckets), 'daily' => $dailyChart,
    'monthlyVisits' => ['labels'=>array_column($monthlyVisitTrend,'label'),'values'=>array_column($monthlyVisitTrend,'value')],
    'yearlyVisits' => ['labels'=>array_column($yearlyVisitTrend,'label'),'values'=>array_column($yearlyVisitTrend,'value')],
    'cultureMonthly' => $bucketSeries($cultureMonthBuckets), 'fiestaMonthly' => $bucketSeries($fiestaMonthBuckets),
    'commentsMonthly' => $bucketSeries($commentMonthBuckets), 'searchMonthly' => $bucketSeries($searchMonthBuckets),
    'itineraryMonthly' => $bucketSeries($itineraryMonthBuckets), 'engagementMonthly' => $bucketSeries($engagementMonthBuckets),
    'sentiment' => ['labels'=>array_keys($sentimentCounts),'values'=>array_values($sentimentCounts)],
    'destinationRatingGauge' => $destinationRatingGauge, 'cultureRatingGauge' => $cultureRatingGauge,
    'heatmapViews' => $heatmapViews, 'heatmapClicks' => $heatmapClicks,
    'destinationMap' => $destinationMapPoints,
    'interactionCalendar' => $interactionCalendar,
    'clickSections' => ['labels' => $categoryLabels, 'values' => array_column(array_values($tourismCategoryAnalytics), 'clicks')],
    'destinationRated' => ['labels' => array_column(array_slice($destinationRatedRows, 0, 10), 'name'), 'values' => array_map(static fn($r) => round((float) $r['average_rating'], 2), array_slice($destinationRatedRows, 0, 10))],
    'cultureRated' => ['labels' => array_column(array_slice($cultureRatedRows, 0, 10), 'name'), 'values' => array_map(static fn($r) => round((float) $r['average_rating'], 2), array_slice($cultureRatedRows, 0, 10))],
    'destinationScatter' => ['labels'=>array_column($destinationScatter,'label'),'values'=>array_column($destinationScatter,'y'),'points'=>$destinationScatter],
    'cultureScatter' => ['labels'=>array_column($cultureScatter,'label'),'values'=>array_column($cultureScatter,'y'),'points'=>$cultureScatter],
];
$chartDataJson = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$dailyTrafficSupported = in_array($period, ['yesterday', '7days', '30days', 'week', 'month'], true) || ($customRange && $dateFrom->diff($dateTo)->days <= 62);
$workspaceTabs = [
    'engagement' => 'Tourism Engagement', 'destination' => 'Destinations', 'culture' => 'Cultural Content',
    'recommendations' => 'AI Recommendations', 'community' => 'Community', 'audience' => 'Audience',
];
$workspaceMetrics = [
    'engagement' => [
        ['Visits per Hour', 'heatmap', 'heatmapViews', 'Days of week × hours · exact recorded page views.'],
        ['Visits per Day', 'line', $dailyTrafficSupported ? 'daily' : null, $dailyTrafficSupported ? '' : 'Choose a daily date range to view daily visits.'],
        ['Visits per Week', 'bar', 'weekday', 'Visits grouped by weekday for the selected period.'],
        ['Visits per Month', 'area', 'monthlyVisits', 'Monthly page views in the selected period.'],
        ['Visits per Year', 'line', 'yearlyVisits', 'Annual page views in the selected period.'],
        ['Total Destination Views', 'kpi', null, number_format($destinationViews)],
        ['Most Visited Destinations', 'rank', 'destinationRanks', 'Recorded destination detail views.'],
        ['Least Visited Destinations', 'rank-low', 'destinationLeast', ''],
        ['Destination Views by Category', 'donut', 'destinationCategories', ''],
        ['Average Time Spent Viewing Content', 'bar', null, 'Content view duration is not recorded.'],
        ['Returning vs. New Visitors', 'donut', 'visitors', ''],
        ['Visitor Engagement Trends', 'line', 'engagementMonthly', 'Recorded user activity events grouped by month.'],
    ],
    'destination' => [
        ['Most Popular Destinations', 'rank', 'destinationRanks', ''], ['Least Popular Destinations', 'rank-low', 'destinationLeast', ''],
        ['Destination Popularity by Barangay', 'map', 'destinationMap', 'Map markers show verified destination coordinates; barangay boundaries are not available.'], ['Destination Views by Barangay', 'map', null, 'No verified barangay boundary geometry is available for a choropleth.'],
        ['Destination Ratings', 'bar', 'destinationRated', 'Destinations with visitor ratings in the selected period.'], ['Destination Rating Distribution', 'histogram', 'ratings', ''],
        ['Most Searched Destinations', 'rank', null, 'Search logs store query totals, not selected destination IDs.'], ['Most Viewed Establishments', 'rank', 'restaurants', 'Restaurant listing detail views.'],
        ['Restaurant Engagement', 'bar', 'restaurants', 'Views are available; restaurant-specific searches and recommendations are not fully tracked.'],
        ['Destination Performance Over Time', 'line', null, 'Per-destination historical view buckets are not currently queried.'], ['Views vs. Ratings', 'scatter', 'destinationScatter', 'Each point is one destination with both views and a rating.'],
        ['Destination Category Comparison', 'donut', 'destinationCategories', ''],
    ],
    'culture' => [
        ['Most Viewed Cultural Content', 'rank', 'cultureRanks', ''], ['Least Viewed Cultural Content', 'rank-low', 'cultureLeast', ''],
        ['Culture vs. Tradition vs. Fiesta Views', 'donut', 'cultureCategories', 'Available category groups: products, fiestas, and people of Malvar.'], ['Cultural Content Views per Month', 'line', 'cultureMonthly', 'Product, fiesta, and people detail views.'],
        ['Most Searched Cultural Topics', 'wordcloud', null, 'Search logs do not associate queries with cultural topics.'], ['Cultural Content Engagement', 'stacked', 'cultureEngagement', 'Recorded views, clicks, and searches by cultural category.'],
        ['Content Performance by Barangay', 'map', null, 'Cultural content barangay mapping is not available.'], ['Fiesta Interest Over Time', 'line', 'fiestaMonthly', 'Recorded fiesta detail views.'],
        ['Most Rated Cultural Content', 'rank', 'cultureRated', 'Rated cultural items in the selected period.'], ['Cultural Content Ratings', 'histogram', 'cultureRatings', ''],
        ['Views vs. Comments', 'scatter', null, 'Item-level comments are not linked to view totals in the current analytics data.'], ['Cultural Content Popularity Ranking', 'rank', 'cultureRanks', ''],
    ],
    'recommendations' => [
        ['Most Recommended Destinations', 'rank', null, 'Recommendation impressions are not logged separately from recommendation clicks.'], ['Most Recommended Establishments', 'rank', null, 'Recommendation impressions are not logged separately from recommendation clicks.'],
        ['AI Recommendation Click-through Rate', 'kpi-empty', null, 'Recommendation impressions and clicks are not linked.'], ['Recommended vs. Actually Viewed Destinations', 'bar', null, 'Recommendation impressions cannot be compared to views without impression records.'],
        ['Most Generated Itinerary Categories', 'donut', null, 'Itinerary categories are not recorded.'], ['Itinerary Generation Trends', 'line', 'itineraryMonthly', 'Recorded itinerary generation events grouped by month.'],
        ['Most Selected User Preferences', 'bar', null, 'Preference selections are not recorded as analytics events.'], ['Recommendation Engagement Rate', 'kpi-empty', null, 'Recommendation impressions are not recorded.'],
        ['Itinerary Download Count', 'kpi', null, number_format($overallActivityCounts['download'])], ['AI Recommendation Performance', 'line', null, 'Recommendation outcomes are not linked to generated recommendations.'],
    ],
    'community' => [
        ['Total Comments', 'kpi', null, number_format($overallActivityCounts['comment'])], ['Total Ratings', 'kpi', null, number_format($overallRatingCount)],
        ['Average Destination Rating', 'gauge', 'destinationRatingGauge', $destinationRatingCount ? number_format($averageDestinationRating, 1) . ' / 5 average from ' . number_format($destinationRatingCount) . ' ratings' : 'No ratings yet'],
        ['Average Cultural Content Rating', 'gauge', 'cultureRatingGauge', $cultureRatingCount ? number_format($cultureAverageRating, 1) . ' / 5 average from ' . number_format($cultureRatingCount) . ' ratings' : 'No cultural ratings yet'], ['Comments per Month', 'line', 'commentsMonthly', 'Recorded comment events grouped by month.'],
        ['Most Discussed Destinations', 'rank', null, 'Comments are not linked to destination IDs in current analytics.'], ['Positive vs. Negative Feedback', 'donut', 'sentiment', 'Rule-based sentiment estimate from recorded visitor reviews; neutral reviews are shown separately.'],
        ['Rating Distribution', 'histogram', 'ratings', ''], ['Most Engaged Destinations', 'rank', 'destinationRanks', 'Ranked by recorded detail views.'],
        ['User Engagement by Section', 'stacked', 'sectionEngagement', 'Recorded views, clicks, and searches by catalog section.'],
    ],
    'audience' => [
        ['Visitors by Location', 'map', null, 'Visitor location is not collected.'], ['Visitors by Barangay', 'bar', null, 'Visitor barangay is not collected.'],
        ['New vs. Returning Users', 'donut', 'visitors', 'Counts are tracked sessions, not identified people.'], ['User Registration Trends', 'line', 'signup', 'Monthly registrations from account creation records.'],
        ['Preferred Language', 'donut', null, 'Language preference values are not stored in analytics.'], ['Most Active User Groups', 'bar', null, 'User groups or demographics are not collected.'],
        ['Visitor Activity by Day', 'heatmap', 'heatmapViews', 'Page visits grouped by weekday and hour for the selected period.'], ['Visitor Activity by Hour', 'heatmap', 'heatmapViews', 'Page visits grouped by hour and weekday for the selected period.'],
    ],
];
$visitorDonut = ['labels' => ['New tracked sessions', 'Returning tracked sessions'], 'values' => [$newTrackedVisitors, $returningTrackedVisitors]];
$chartData['visitors'] = $visitorDonut;
$chartDataJson = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<style>
.main-content { background:#f4f6f1; color:#26342b; }
.analytics-workspace { --aw-bg:#f4f6f1; --aw-card:#fff; --aw-line:rgba(38,52,43,.14); --aw-muted:#66746a; --aw-text:#26342b; --aw-chart-text:#34443a; }
.analytics-workspace, .analytics-workspace * { box-sizing:border-box; }
.aw-heading { display:flex; flex-direction:column; gap:7px; margin:0 0 22px; }
.aw-heading .section-label { margin:0; }
.aw-heading h1 { margin:0; color:var(--aw-text); font:700 clamp(1.8rem,3vw,2.45rem)/1.15 'Playfair Display',serif; }
.aw-heading h1 em { color:#88641f; }
.aw-heading p { margin:0; max-width:65ch; color:var(--aw-muted); font:.88rem/1.5 'DM Sans',sans-serif; }
.aw-toolbar { display:grid; gap:12px; margin:0 0 20px; }
.aw-periods { display:flex; flex-wrap:wrap; gap:7px; min-width:0; }
.aw-periods a { display:inline-flex; align-items:center; min-height:35px; padding:0 13px; border:1px solid var(--aw-line); border-radius:8px; color:var(--aw-muted); font:500 .75rem 'DM Sans',sans-serif; text-decoration:none; }
.aw-periods a.active,.aw-periods a:hover { border-color:#547958; background:#547958; color:#fff; }
.aw-controls { display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; }
.aw-tabs { display:flex; gap:5px; overflow-x:auto; padding:4px; margin:0 0 18px; border:1px solid var(--aw-line); border-radius:10px; background:#e9eee8; scrollbar-width:thin; }
.aw-tab { flex:0 0 auto; min-height:38px; padding:0 13px; border:0; border-radius:7px; background:transparent; color:var(--aw-muted); font:600 .76rem 'DM Sans',sans-serif; cursor:pointer; }
.aw-tab[aria-selected="true"] { background:#547958; color:#fff; }
.aw-section { display:none; }
.aw-section.is-active { display:block; }
.aw-section-head { display:flex; align-items:end; justify-content:space-between; gap:12px; margin:0 0 12px; }
.aw-section-head h2 { margin:0; color:var(--aw-text); font:700 1.2rem 'Playfair Display',serif; }
.aw-section-head p { margin:0; color:var(--aw-muted); font:.72rem 'DM Sans',sans-serif; }
.aw-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.aw-card { min-width:0; min-height:290px; padding:17px; border:1px solid var(--aw-line); border-radius:11px; background:var(--aw-card); box-shadow:0 2px 10px rgba(38,52,43,.04); }
.aw-card h3 { min-height:2.5em; margin:0 0 10px; color:var(--aw-text); font:600 .88rem/1.35 'DM Sans',sans-serif; }
.aw-card-canvas { position:relative; height:220px; }
.aw-leaflet-map { height:220px; overflow:hidden; border-radius:8px; background:#edf1eb; }
.aw-card-empty { display:grid; min-height:205px; place-content:center; gap:8px; padding:16px; border:1px dashed var(--aw-line); border-radius:8px; color:var(--aw-muted); text-align:center; }
.aw-card-empty strong { color:#5e6f60; font:600 .8rem 'DM Sans',sans-serif; }
.aw-card-empty span { max-width:42ch; font:.73rem/1.5 'DM Sans',sans-serif; }
.aw-kpi-value { padding:18px 0 8px; color:#88641f; font:700 clamp(1.8rem,3vw,2.4rem)/1.1 'Playfair Display',serif; }
.aw-kpi-note { color:var(--aw-muted); font:.75rem/1.5 'DM Sans',sans-serif; }
.aw-drilldown { min-height:1.2em; margin:4px 0 6px; color:#88641f; font:.72rem 'DM Sans',sans-serif; }
.aw-word-cloud { display:flex; min-height:205px; align-content:center; align-items:center; justify-content:center; flex-wrap:wrap; gap:10px 14px; padding:16px; }
.aw-word-cloud span { color:#88641f; font:600 1rem/1.2 'DM Sans',sans-serif; }
.aw-heatmap { display:grid; grid-template-columns:34px repeat(24,minmax(10px,1fr)); gap:3px; align-items:center; overflow-x:auto; padding:8px 0; }
.aw-heatmap-label { color:var(--aw-muted); font:500 .58rem 'DM Sans',sans-serif; text-align:center; }
.aw-heatmap-cell { width:100%; min-width:10px; height:13px; border:0; border-radius:3px; background:#a8c28e; }
.aw-calendar { display:grid; grid-template-columns:repeat(7,minmax(20px,1fr)); gap:4px; max-height:210px; overflow:auto; padding:8px 0; }
.aw-calendar-cell { aspect-ratio:1; min-width:20px; border-radius:4px; background:#a8c28e; }
.analytics-workspace .date-range-field { color:var(--aw-muted); }
.analytics-workspace .date-range-field input { border-color:var(--aw-line); background:#fff; color:var(--aw-text); color-scheme:light; }
.analytics-workspace .date-range-submit { background:#547958; color:#fff; }
.analytics-workspace .date-range-clear { color:var(--aw-muted); }
.analytics-workspace .date-range-error { color:#a33434; }
.analytics-workspace .analytics-report-export { border-color:var(--aw-line); background:#fff; color:#43564a; }
@media(max-width:760px){.aw-grid{grid-template-columns:1fr}.aw-card{min-height:260px}.aw-section-head{align-items:flex-start;flex-direction:column}.aw-card-canvas{height:205px}}
</style>
<div class="analytics-workspace">
    <div class="admin-site-switch-row"><a class="admin-site-switch" href="<?php echo BASE_URL; ?>/index.php"><i data-lucide="external-link" class="lucide"></i> View user site</a></div>
    <header class="aw-heading">
        <div class="section-label">Tourism Insights</div><h1>Tourism <em>analytics</em></h1>
        <p>Explore visitor activity, destination performance, cultural engagement, and audience trends.</p>
    </header>
    <div class="aw-toolbar">
        <nav class="aw-periods" aria-label="Analytics date range">
            <?php foreach (['today'=>'Today','yesterday'=>'Yesterday','7days'=>'Last 7 days','30days'=>'Last 30 days','week'=>'This week','month'=>'This month','lastmonth'=>'Last month','year'=>'This year','all'=>'All time'] as $key=>$label): ?><a href="?period=<?php echo $key; ?>" class="<?php echo $period === $key ? 'active' : ''; ?>"><?php echo $label; ?></a><?php endforeach; ?>
        </nav>
        <div class="aw-controls">
            <form class="date-range-form" method="get" action="admindashboard.php" aria-label="Custom date range"><input type="hidden" name="period" value="custom"><label class="date-range-field">From<input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFromInput); ?>" max="<?php echo $today->format('Y-m-d'); ?>" required></label><label class="date-range-field">To<input type="date" name="date_to" value="<?php echo htmlspecialchars($dateToInput); ?>" max="<?php echo $today->format('Y-m-d'); ?>" required></label><button class="date-range-submit" type="submit">Apply</button><?php if ($customRange): ?><a class="date-range-clear" href="?period=month">Reset</a><?php endif; ?></form>
            <a class="period-btn analytics-report-export" href="<?php echo BASE_URL; ?>/admin/analytics_export.php?period=<?php echo urlencode($period); ?>&amp;date_from=<?php echo urlencode($dateFromInput); ?>&amp;date_to=<?php echo urlencode($dateToInput); ?>">Export CSV</a>
        </div>
        <?php if ($dateRangeError !== ''): ?><div class="date-range-error" role="alert"><?php echo htmlspecialchars($dateRangeError); ?></div><?php endif; ?>
    </div>
    <div class="aw-tabs" role="tablist" aria-label="Analytics sections">
        <?php foreach ($workspaceTabs as $key=>$label): ?><button class="aw-tab" type="button" role="tab" id="aw-tab-<?php echo $key; ?>" aria-controls="aw-section-<?php echo $key; ?>" aria-selected="<?php echo $key === 'engagement' ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($label); ?></button><?php endforeach; ?>
    </div>
    <script type="application/json" id="aw-chart-data"><?php echo $chartDataJson; ?></script>
    <?php foreach ($workspaceMetrics as $sectionKey=>$metrics): ?>
    <section class="aw-section <?php echo $sectionKey === 'engagement' ? 'is-active' : ''; ?>" role="tabpanel" id="aw-section-<?php echo $sectionKey; ?>" aria-labelledby="aw-tab-<?php echo $sectionKey; ?>" <?php echo $sectionKey === 'engagement' ? '' : 'hidden'; ?>>
        <div class="aw-section-head"><div><h2><?php echo htmlspecialchars($workspaceTabs[$sectionKey]); ?></h2></div><p><?php echo htmlspecialchars($periodLabels[$period]); ?> · Based on recorded data</p></div>
        <div class="aw-grid">
            <?php foreach ($metrics as [$title,$kind,$dataKey,$note]): $payload = $dataKey ? ($chartData[$dataKey] ?? ['labels'=>[],'values'=>[]]) : ['labels'=>[],'values'=>[]]; $isHeatmap = $kind === 'heatmap'; $isMap = $kind === 'map'; $isCalendar=$kind === 'calendar'; $hasData = $isHeatmap ? (is_array($payload) && array_sum(array_map('array_sum', $payload)) > 0) : ($isMap ? !empty($payload) : ($isCalendar ? (!empty($payload) && array_sum(array_column($payload,'value'))>0) : (!empty($payload['labels']) && !empty($payload['values']) && array_sum(array_map('floatval',$payload['values'])) > 0))); $mapId='aw-map-'.$sectionKey.'-'.substr(sha1($title),0,7); ?>
            <article class="aw-card"><h3><?php echo htmlspecialchars($title); ?></h3>
                <?php if ($kind === 'kpi'): ?><div class="aw-kpi-value"><?php echo htmlspecialchars($note); ?></div><div class="aw-kpi-note">Selected period · actual records</div>
                <?php elseif ($kind === 'kpi-empty'): ?><div class="aw-card-empty"><strong>Not tracked yet</strong><span><?php echo htmlspecialchars($note); ?></span></div>
                <?php elseif ($isMap && $hasData): ?><div class="aw-leaflet-map" id="<?php echo $mapId; ?>" data-aw-map="<?php echo htmlspecialchars($dataKey); ?>" aria-label="<?php echo htmlspecialchars($title); ?>"></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php elseif ($isHeatmap && $hasData): ?><div class="aw-heatmap" aria-label="Activity by weekday and hour"><?php $dayLabels=['','Sun','Mon','Tue','Wed','Thu','Fri','Sat']; $heatMax=max(array_map('max',$payload)); ?><span></span><?php for($hour=0;$hour<24;$hour++): ?><span class="aw-heatmap-label"><?php echo $hour%3===0?sprintf('%02d',$hour):''; ?></span><?php endfor; ?><?php for($day=1;$day<=7;$day++): ?><span class="aw-heatmap-label"><?php echo $dayLabels[$day]; ?></span><?php for($hour=0;$hour<24;$hour++): $count=(int)$payload[$day][$hour]; $opacity=$heatMax>0?max(.08,$count/$heatMax):.08; ?><span class="aw-heatmap-cell" style="opacity:<?php echo number_format($opacity,2); ?>" title="<?php echo htmlspecialchars($dayLabels[$day] . ' ' . sprintf('%02d:00', $hour) . ' - ' . number_format($count)); ?>"></span><?php endfor; ?><?php endfor; ?></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php elseif ($isCalendar && $hasData): ?><div class="aw-calendar" aria-label="Daily interaction frequency"><?php $calendarMax=max(array_column($payload,'value')); $calendarOffset=(int)(new DateTimeImmutable($payload[0]['date']))->format('w'); for($blank=0;$blank<$calendarOffset;$blank++): ?><span></span><?php endfor; foreach($payload as $calendarDay): $opacity=$calendarMax>0?max(.08,$calendarDay['value']/$calendarMax):.08; ?><span class="aw-calendar-cell" style="opacity:<?php echo number_format($opacity,2); ?>" title="<?php echo htmlspecialchars($calendarDay['label'] . ' - ' . number_format($calendarDay['value']) . ' events'); ?>"></span><?php endforeach; ?></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php elseif ($hasData && $dataKey && $kind === 'wordcloud'): ?><div class="aw-word-cloud"><?php $wordMax = max(1, max($payload['values'])); foreach ($payload['labels'] as $wordIndex => $word): $wordScale = 1 + .9 * ((float) $payload['values'][$wordIndex] / $wordMax); ?><span style="font-size:<?php echo number_format($wordScale, 2); ?>rem" title="<?php echo (int) $payload['values'][$wordIndex]; ?> searches"><?php echo htmlspecialchars($word); ?></span><?php endforeach; ?></div>
                <?php elseif ($hasData && $dataKey): ?><div class="aw-card-canvas"><canvas data-aw-chart="<?php echo htmlspecialchars($kind); ?>" data-aw-key="<?php echo htmlspecialchars($dataKey); ?>" role="img" aria-label="<?php echo htmlspecialchars($title); ?>"></canvas></div><div class="aw-drilldown" aria-live="polite"></div><?php if ($note !== ''): ?><div class="aw-kpi-note"><?php echo htmlspecialchars($note); ?></div><?php endif; ?>
                <?php else: ?><div class="aw-card-empty"><strong><?php echo $kind === 'map' ? 'Map data unavailable' : 'More data needed'; ?></strong><span><?php echo htmlspecialchars($note ?: 'There are no matching records in this period.'); ?></span></div><?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/analytics-workspace.js"></script>
