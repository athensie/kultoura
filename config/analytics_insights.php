<?php
/*
 * Whole-system analytics insights for the admin dashboard.
 *
 * Everything here is computed from recorded rows (page views, item views, searches,
 * reviews, sign-ups, favourites, travel-diary entries, clicks, newsletter sign-ups,
 * review reports and visitor sessions). Nothing is estimated by an AI model:
 *  - kt_system_snapshot():   totals for a date range (used for "this period" vs "previous period")
 *  - kt_traffic_forecast():  current-month projection + next-month forecast from recent daily activity
 *  - kt_category_outlook():  category interest for the next 30 days from the last 28 vs previous 28 days
 *  - kt_build_recommendations(): ranked admin actions, each with readable evidence
 *  - kt_text_numbers_supported(): guard that rejects AI text quoting numbers not present in the data
 */

const KT_DEMO_REFERRER = 'demo-seed.kultoura.local';

function kt_insight_table_exists(mysqli $conn, string $table): bool
{
    static $known = [];
    if (!array_key_exists($table, $known)) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        $known[$table] = $result && $result->num_rows > 0;
    }
    return $known[$table];
}

function kt_insight_has_column(mysqli $conn, string $table, string $column): bool
{
    static $known = [];
    $key = $table . '.' . $column;
    if (!array_key_exists($key, $known)) {
        $result = kt_insight_table_exists($conn, $table) ? $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'") : false;
        $known[$key] = $result && $result->num_rows > 0;
    }
    return $known[$key];
}

/** Runs a query whose only placeholders are (start, end) and returns the first row. */
function kt_insight_row(mysqli $conn, string $sql, string $startSql, string $endSql): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('ss', $startSql, $endSql);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return $row;
}

function kt_insight_count(mysqli $conn, string $table, string $sql, string $startSql, string $endSql): int
{
    if (!kt_insight_table_exists($conn, $table)) return 0;
    return (int) (kt_insight_row($conn, $sql, $startSql, $endSql)['total'] ?? 0);
}

/** Totals for one date range across the whole system. */
function kt_system_snapshot(mysqli $conn, string $startSql, string $endSql): array
{
    $demo = KT_DEMO_REFERRER;
    $reviews = kt_insight_table_exists($conn, 'item_reviews')
        ? kt_insight_row($conn, "SELECT COUNT(*) total, AVG(rating) average_rating FROM item_reviews WHERE status IN ('visible','demo') AND created_at >= ? AND created_at < ?", $startSql, $endSql)
        : [];
    $searchRow = kt_insight_table_exists($conn, 'search_log')
        ? kt_insight_row($conn, 'SELECT COUNT(*) total, SUM(result_count = 0) zero_results FROM search_log WHERE searched_at >= ? AND searched_at < ?', $startSql, $endSql)
        : [];
    $hasVisitIds = kt_insight_has_column($conn, 'page_views', 'visit_id');
    return [
        'site_visits' => kt_insight_count($conn, 'page_views', "SELECT COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> '{$demo}') AND viewed_at >= ? AND viewed_at < ?", $startSql, $endSql),
        'unique_visitors' => $hasVisitIds ? kt_insight_count($conn, 'page_views', 'SELECT COUNT(DISTINCT visitor_id) total FROM page_views WHERE visitor_id IS NOT NULL AND viewed_at >= ? AND viewed_at < ?', $startSql, $endSql) : 0,
        'visits' => $hasVisitIds ? kt_insight_count($conn, 'page_views', 'SELECT COUNT(DISTINCT visit_id) total FROM page_views WHERE visit_id IS NOT NULL AND viewed_at >= ? AND viewed_at < ?', $startSql, $endSql) : 0,
        'intent_actions' => array_sum(kt_intent_breakdown($conn, $startSql, $endSql)),
        'visitor_sessions' => kt_insight_count($conn, 'user_activity_log', 'SELECT COUNT(DISTINCT session_hash) total FROM user_activity_log WHERE activity_date >= ? AND activity_date < ?', $startSql, $endSql),
        'content_views' => kt_insight_count($conn, 'item_views', 'SELECT COUNT(*) total FROM item_views WHERE viewed_at >= ? AND viewed_at < ?', $startSql, $endSql),
        'clicks' => kt_insight_count($conn, 'user_activity_log', "SELECT COUNT(*) total FROM user_activity_log WHERE activity_type = 'click' AND activity_date >= ? AND activity_date < ?", $startSql, $endSql),
        'searches' => (int) ($searchRow['total'] ?? 0),
        'zero_result_searches' => (int) ($searchRow['zero_results'] ?? 0),
        'reviews' => (int) ($reviews['total'] ?? 0),
        'average_rating' => isset($reviews['average_rating']) && $reviews['average_rating'] !== null ? round((float) $reviews['average_rating'], 1) : null,
        'new_members' => kt_insight_count($conn, 'users', 'SELECT COUNT(*) total FROM users WHERE created_at >= ? AND created_at < ?', $startSql, $endSql),
        'favorites' => kt_insight_count($conn, 'favorites', 'SELECT COUNT(*) total FROM favorites WHERE created_at >= ? AND created_at < ?', $startSql, $endSql),
        'diary_entries' => kt_insight_count($conn, 'diary_entries', 'SELECT COUNT(*) total FROM diary_entries WHERE created_at >= ? AND created_at < ?', $startSql, $endSql),
        'newsletter_signups' => kt_insight_count($conn, 'newsletter_subscribers', 'SELECT COUNT(*) total FROM newsletter_subscribers WHERE created_at >= ? AND created_at < ?', $startSql, $endSql),
        'review_reports' => kt_insight_count($conn, 'item_review_reports', 'SELECT COUNT(*) total FROM item_review_reports WHERE created_at >= ? AND created_at < ?', $startSql, $endSql),
    ];
}

/**
 * The equal-length range immediately before [start, end). When the range is still in progress
 * (ends in the future), the previous range is cut at the same point in time, so "today so far"
 * is compared with "yesterday up to this time", not with all of yesterday.
 */
function kt_previous_range(string $startSql, string $endSql): ?array
{
    $start = new DateTimeImmutable($startSql);
    $end = new DateTimeImmutable($endSql);
    if ((int) $start->format('Y') < 2000 || $end <= $start) return null;
    $days = max(1, (int) $start->diff($end)->days);
    $now = new DateTimeImmutable();
    $comparableEnd = $end > $now ? $now : $end;
    return [
        'start' => $start->modify("-{$days} days")->format('Y-m-d H:i:s'),
        'end' => $comparableEnd->modify("-{$days} days")->format('Y-m-d H:i:s'),
        'days' => $days,
    ];
}

function kt_change_pct(float $current, float $previous): ?float
{
    if ($previous <= 0) return null;
    return (($current - $previous) / $previous) * 100;
}

/** "+12%", "−8%", or "" when there is nothing to compare against. */
function kt_format_change(?float $pct): string
{
    if ($pct === null) return '';
    $magnitude = abs($pct);
    $text = $magnitude < 10 ? rtrim(rtrim(number_format($magnitude, 1), '0'), '.') : number_format($magnitude, 0);
    if ($text === '0') return '±0%';
    return ($pct > 0 ? '+' : '−') . $text . '%';
}

function kt_plural(int $count, string $singular, ?string $plural = null): string
{
    return number_format($count) . ' ' . ($count === 1 ? $singular : ($plural ?? $singular . 's'));
}

/** Daily counts for every day in [start, end) (missing days are 0). */
function kt_daily_counts(mysqli $conn, string $table, string $dateColumn, string $startSql, string $endSql, string $extraWhere = '', string $groupColumn = ''): array
{
    if (!kt_insight_table_exists($conn, $table)) return [];
    $select = $groupColumn !== '' ? "{$groupColumn} grp, " : "'all' grp, ";
    $sql = "SELECT {$select}DATE({$dateColumn}) day, COUNT(*) total FROM {$table} WHERE {$dateColumn} >= ? AND {$dateColumn} < ?"
        . ($extraWhere !== '' ? " AND ({$extraWhere})" : '') . ' GROUP BY grp, day';
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('ss', $startSql, $endSql);
    $stmt->execute();
    $byGroup = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $byGroup[(string) $row['grp']][(string) $row['day']] = (int) $row['total'];
    $stmt->close();
    $days = [];
    for ($day = new DateTimeImmutable(substr($startSql, 0, 10)); $day < new DateTimeImmutable(substr($endSql, 0, 10)); $day = $day->modify('+1 day')) $days[] = $day->format('Y-m-d');
    $series = [];
    foreach (($groupColumn !== '' ? array_keys($byGroup) : ['all']) as $group) {
        $series[$group] = array_map(static fn($day) => $byGroup[$group][$day] ?? 0, $days);
    }
    return $groupColumn !== '' ? $series : ($series['all'] ?? array_fill(0, count($days), 0));
}

/** Mean after dropping the top and bottom 10% of days, so one-off spikes don't dominate. */
function kt_trimmed_mean(array $values): float
{
    $count = count($values);
    if ($count === 0) return 0.0;
    sort($values);
    $trim = $count >= 10 ? (int) floor($count * 0.1) : 0;
    $kept = array_slice($values, $trim, $count - 2 * $trim);
    return $kept ? array_sum($kept) / count($kept) : 0.0;
}

function kt_percentile(array $values, float $percent): float
{
    if (!$values) return 0.0;
    sort($values);
    $index = ($percent / 100) * (count($values) - 1);
    $low = (int) floor($index);
    $high = (int) ceil($index);
    return $values[$low] + ($values[$high] - $values[$low]) * ($index - $low);
}

/**
 * Page-view outlook from recent daily activity (last 56 complete days):
 * rate = 60% recent-14-day + 40% recent-28-day trimmed daily mean; a dampened 14-day trend
 * adjusts next month; the likely range uses the 25th–75th percentile of recent days.
 */
function kt_traffic_forecast(mysqli $conn): array
{
    $today = new DateTimeImmutable('today');
    $demoWhere = "referrer_host IS NULL OR referrer_host <> '" . KT_DEMO_REFERRER . "'";
    $daily = kt_daily_counts($conn, 'page_views', 'viewed_at', $today->modify('-56 days')->format('Y-m-d 00:00:00'), $today->format('Y-m-d 00:00:00'), $demoWhere);
    $last28 = array_slice($daily, -28);
    $last14 = array_slice($daily, -14);
    $prior14 = array_slice($daily, -28, 14);
    $result = ['available' => false, 'rows' => [], 'confidence' => 'Insufficient data'];
    // Need at least a week of days with visits in the last four weeks to project anything.
    if (count(array_filter($last28, static fn($value) => $value > 0)) < 7) return $result;

    $rate = 0.6 * kt_trimmed_mean($last14) + 0.4 * kt_trimmed_mean($last28);
    $priorRate = kt_trimmed_mean($prior14);
    $trend = $priorRate > 0 ? max(0.75, min(1.33, kt_trimmed_mean($last14) / $priorRate)) : 1.0;
    $trendFactor = 1 + ($trend - 1) / 2;
    $lowRate = kt_percentile($last28, 25);
    $highRate = kt_percentile($last28, 75);
    $activeDays = count(array_filter($last28, static fn($value) => $value > 0));
    // Variability of typical days (spikes trimmed, same as the rate) decides the confidence.
    $typicalDays = $last28;
    sort($typicalDays);
    $typicalDays = array_slice($typicalDays, 2, 24);
    $mean = array_sum($typicalDays) / count($typicalDays);
    $variance = array_sum(array_map(static fn($value) => ($value - $mean) ** 2, $typicalDays)) / count($typicalDays);
    $variation = $mean > 0 ? sqrt($variance) / $mean : 9;
    $confidence = ($activeDays >= 24 && $variation < 0.35) ? 'High' : (($activeDays >= 14 && $variation < 0.8) ? 'Moderate' : 'Low');

    $monthStart = new DateTimeImmutable('first day of this month');
    $nextMonthStart = $monthStart->modify('+1 month');
    $daysInMonth = (int) $monthStart->format('t');
    $completeDays = (int) $monthStart->diff($today)->days;          // days before today
    $remainingDays = $daysInMonth - $completeDays;                  // today + the rest of the month
    $demoSql = " AND ({$demoWhere})";
    $completeViews = kt_insight_count($conn, 'page_views', "SELECT COUNT(*) total FROM page_views WHERE viewed_at >= ? AND viewed_at < ?{$demoSql}", $monthStart->format('Y-m-d 00:00:00'), $today->format('Y-m-d 00:00:00'));
    $soFar = kt_insight_count($conn, 'page_views', "SELECT COUNT(*) total FROM page_views WHERE viewed_at >= ? AND viewed_at < ?{$demoSql}", $monthStart->format('Y-m-d 00:00:00'), $today->modify('+1 day')->format('Y-m-d 00:00:00'));
    $currentEstimate = max($soFar, (int) round($completeViews + $rate * $remainingDays));
    $currentLow = max($soFar, (int) round($completeViews + $lowRate * $remainingDays));
    $currentHigh = max($currentEstimate, (int) round($completeViews + $highRate * $remainingDays));

    $nextDays = (int) $nextMonthStart->format('t');
    $nextEstimate = (int) round($rate * $trendFactor * $nextDays);
    $nextLow = min($nextEstimate, (int) round($lowRate * $trendFactor * $nextDays));
    $nextHigh = max($nextEstimate, (int) round($highRate * $trendFactor * $nextDays));

    // Monthly history: the five complete months before this one.
    $history = [];
    $historyStart = $monthStart->modify('-5 months');
    $historyRows = [];
    if (kt_insight_table_exists($conn, 'page_views')) {
        $stmt = $conn->prepare("SELECT DATE_FORMAT(viewed_at, '%Y-%m') ym, COUNT(*) total FROM page_views WHERE viewed_at >= ? AND viewed_at < ?{$demoSql} GROUP BY ym");
        if ($stmt) {
            $historyStartSql = $historyStart->format('Y-m-d 00:00:00');
            $historyEndSql = $monthStart->format('Y-m-d 00:00:00');
            $stmt->bind_param('ss', $historyStartSql, $historyEndSql);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $historyRows[(string) $row['ym']] = (int) $row['total'];
            $stmt->close();
        }
    }
    for ($month = $historyStart; $month < $monthStart; $month = $month->modify('+1 month')) {
        $history[] = ['label' => $month->format('M Y'), 'value' => $historyRows[$month->format('Y-m')] ?? 0, 'kind' => 'actual'];
    }
    $history[] = ['label' => $monthStart->format('M Y'), 'value' => $currentEstimate, 'so_far' => $soFar, 'kind' => 'current'];
    $history[] = ['label' => $nextMonthStart->format('M Y'), 'value' => $nextEstimate, 'kind' => 'forecast'];

    $trendPct = ($trend - 1) * 100;
    return [
        'available' => true,
        'rows' => $history,
        'confidence' => $confidence,
        'daily_rate' => $rate,
        'trend_pct' => $priorRate > 0 ? $trendPct : null,
        'current' => ['label' => $monthStart->format('F Y'), 'so_far' => $soFar, 'estimate' => $currentEstimate, 'low' => $currentLow, 'high' => $currentHigh, 'days_left' => $remainingDays, 'days_in_month' => $daysInMonth],
        'next' => ['label' => $nextMonthStart->format('F Y'), 'estimate' => $nextEstimate, 'low' => $nextLow, 'high' => $nextHigh],
        'active_days' => $activeDays,
    ];
}

/** Category interest for the next 30 days, from item views in the last 28 days vs the 28 before. */
function kt_category_outlook(mysqli $conn, array $categoryDefinitions): array
{
    $today = new DateTimeImmutable('today');
    $series = kt_daily_counts($conn, 'item_views', 'viewed_at', $today->modify('-56 days')->format('Y-m-d 00:00:00'), $today->format('Y-m-d 00:00:00'), '', 'item_type');
    $outlook = [];
    foreach ($categoryDefinitions as $key => $definition) {
        $daily = $series[$definition['type']] ?? [];
        if (!$daily) continue;
        $recent = array_sum(array_slice($daily, -28));
        $previous = array_sum(array_slice($daily, 0, 28));
        $recent14 = array_sum(array_slice($daily, -14));
        if ($recent + $previous < 5) continue;
        $estimate = (int) round((0.6 * $recent14 / 14 + 0.4 * $recent / 28) * 30);
        $outlook[] = [
            'key' => $key, 'label' => $definition['label'], 'estimate' => $estimate,
            'recent' => $recent, 'previous' => $previous, 'change_pct' => kt_change_pct($recent, $previous),
        ];
    }
    usort($outlook, static fn($a, $b) => [$b['estimate'], $b['recent']] <=> [$a['estimate'], $a['recent']]);
    return $outlook;
}

/**
 * Ranked admin actions from the whole system. Each item: id, action, evidence, score.
 * $ctx keys: period_label, snapshot, previous, zero_result_searches, rated_items, items_by_category,
 *            category_outlook, pending_reports, promoted_keys
 */
function kt_build_recommendations(array $ctx): array
{
    $period = $ctx['period_label'];
    $periodLower = strtolower($period);
    $candidates = [];
    $add = static function (string $id, int|float $score, string $action, string $evidence) use (&$candidates) {
        $candidates[] = ['id' => $id, 'score' => $score, 'action' => $action, 'evidence' => $evidence];
    };

    if (($ctx['pending_reports'] ?? 0) > 0) {
        $count = (int) $ctx['pending_reports'];
        $add('pending_reports', 90 + min(20, $count), 'Resolve the pending review reports in Community Feedback.', kt_plural($count, 'review report') . ($count === 1 ? ' is' : ' are') . ' still waiting for a moderation decision.');
    }

    foreach (array_slice($ctx['zero_result_searches'] ?? [], 0, 3) as $index => $search) {
        $query = trim((string) ($search['query'] ?? ''));
        $count = (int) ($search['count'] ?? 0);
        if ($query === '' || $count < 2) continue;
        $add('zero_search_' . $index, 60 + min(30, $count * 4), 'Add or tag content that matches “' . $query . '” so visitors find results.', kt_plural($count, 'search', 'searches') . ' for “' . $query . '” returned no results (' . $periodLower . ').');
    }

    $rated = $ctx['rated_items'] ?? [];
    usort($rated, static fn($a, $b) => (float) ($a['average_rating'] ?? 0) <=> (float) ($b['average_rating'] ?? 0));
    foreach ($rated as $item) {
        $ratings = (int) ($item['ratings'] ?? 0);
        $average = (float) ($item['average_rating'] ?? 0);
        if ($ratings < 3 || $average >= 3.5) continue;
        $add('low_rating_' . ($item['type'] ?? '') . '_' . ($item['id'] ?? 0), 70 + (3.5 - $average) * 15, 'Read the recent reviews of ' . $item['name'] . ' and fix the recurring complaints.', 'Average rating ' . number_format($average, 1) . '/5 from ' . kt_plural($ratings, 'review') . ' (' . $periodLower . '), below the 3.5 attention threshold.');
        if (count(array_filter($candidates, static fn($c) => str_starts_with($c['id'], 'low_rating_'))) >= 2) break;
    }

    $snapshot = $ctx['snapshot'] ?? [];
    $previous = $ctx['previous'] ?? null;
    if ($previous) {
        $visitChange = kt_change_pct((float) ($snapshot['site_visits'] ?? 0), (float) ($previous['site_visits'] ?? 0));
        if ($visitChange !== null && $visitChange <= -20 && ($previous['site_visits'] ?? 0) >= 20) {
            $add('traffic_drop', 65 + min(25, abs($visitChange) / 4), 'Check what changed: site visits fell compared with the previous period.', kt_plural((int) $snapshot['site_visits'], 'visit') . ' vs ' . number_format((int) $previous['site_visits']) . ' in the previous period of the same length (' . kt_format_change($visitChange) . ').');
        }
        $zeroNow = (int) ($snapshot['zero_result_searches'] ?? 0);
        $searchesNow = (int) ($snapshot['searches'] ?? 0);
        if ($searchesNow >= 10 && $zeroNow / max(1, $searchesNow) >= 0.3) {
            $add('search_gap_rate', 55, 'Improve search coverage (synonyms, tags, missing listings).', number_format($zeroNow) . ' of ' . kt_plural($searchesNow, 'search', 'searches') . ' (' . round($zeroNow / $searchesNow * 100) . '%) found nothing (' . $periodLower . ').');
        }
    }

    $quality = $ctx['visit_quality'] ?? [];
    if (!empty($quality['tracked']) && ($quality['visits'] ?? 0) >= 30 && ($quality['bounce_rate'] ?? 0) >= 60) {
        $add('high_bounce', 50 + min(20, ($quality['bounce_rate'] - 60) / 2), 'Give landing pages clear next steps (related listings, directions, “see more”) so visitors keep exploring.', $quality['bounce_rate'] . '% of ' . kt_plural((int) $quality['visits'], 'visit') . ' saw only one page and opened no listing (' . $periodLower . ').');
    }

    foreach ($ctx['category_outlook'] ?? [] as $category) {
        $change = $category['change_pct'];
        if ($change !== null && $change >= 25 && $category['recent'] >= 10) {
            $add('rising_' . $category['key'], 45 + min(30, $change / 5), 'Feature ' . $category['label'] . ' in promotions while interest is rising.', number_format($category['recent']) . ' views in the last 28 days, ' . kt_format_change($change) . ' vs the 28 days before.');
        } elseif ($change !== null && $change <= -30 && $category['previous'] >= 10) {
            $add('falling_' . $category['key'], 40 + min(20, abs($change) / 5), 'Refresh ' . $category['label'] . ' listings (photos, descriptions, events) to win back interest.', number_format($category['recent']) . ' views in the last 28 days, ' . kt_format_change($change) . ' vs the 28 days before.');
        }
    }

    $promoted = $ctx['promoted_keys'] ?? [];
    $allItems = [];
    foreach ($ctx['items_by_category'] ?? [] as $categoryLabel => $items) {
        foreach ($items as $item) { $item['category_label'] = $categoryLabel; $allItems[] = $item; }
    }
    usort($allItems, static fn($a, $b) => (int) ($b['views'] ?? 0) <=> (int) ($a['views'] ?? 0));
    if ($allItems && (int) ($allItems[0]['views'] ?? 0) >= 10) {
        $top = $allItems[0];
        if (!isset($promoted[($top['type'] ?? '') . ':' . ($top['id'] ?? 0)])) {
            $add('promote_top', 42, 'Promote ' . $top['name'] . ' on the homepage “For You” section.', 'It is the most viewed listing (' . number_format((int) $top['views']) . ' views, ' . $periodLower . ') but is not currently promoted.');
        }
    }
    $popularUnreviewed = array_values(array_filter($allItems, static fn($item) => (int) ($item['views'] ?? 0) >= 15 && (int) ($item['ratings'] ?? 0) === 0));
    if ($popularUnreviewed) {
        $item = $popularUnreviewed[0];
        $add('ask_reviews', 35, 'Encourage visitors to review ' . $item['name'] . '.', number_format((int) $item['views']) . ' views but no reviews (' . $periodLower . '), so visitors have no ratings to go on.');
    }
    $unviewed = array_values(array_filter($allItems, static fn($item) => (int) ($item['views'] ?? 0) === 0));
    if (count($unviewed) >= 3 && count($allItems) > 0) {
        $examples = implode(', ', array_map(static fn($item) => $item['name'], array_slice($unviewed, 0, 2)));
        $add('unviewed_listings', 30 + min(15, count($unviewed) / 2), 'Improve discoverability of listings nobody opened (better photos, titles, links from related pages).', kt_plural(count($unviewed), 'active listing') . ' had no views (' . $periodLower . '), e.g. ' . $examples . '.');
    }

    usort($candidates, static fn($a, $b) => $b['score'] <=> $a['score']);
    return $candidates;
}

/**
 * True when every number quoted in $text appears somewhere in $facts (ignoring years and small
 * counting words). Used to reject AI wording that invents or alters figures.
 */
function kt_text_numbers_supported(string $text, array $facts): bool
{
    $allowed = [];
    array_walk_recursive($facts, static function ($value) use (&$allowed) {
        if (is_int($value) || is_float($value)) {
            $allowed[(string) round((float) $value)] = true;
            $allowed[number_format((float) $value, 1, '.', '')] = true;
        } elseif (is_string($value) && preg_match_all('/\d[\d,]*(?:\.\d+)?/', $value, $matches)) {
            foreach ($matches[0] as $number) {
                $clean = str_replace(',', '', $number);
                $allowed[$clean] = true;
                $allowed[(string) round((float) $clean)] = true;
            }
        }
    });
    if (!preg_match_all('/\d[\d,]*(?:\.\d+)?/', $text, $matches)) return true;
    foreach ($matches[0] as $number) {
        $clean = str_replace(',', '', $number);
        $numeric = (float) $clean;
        if ($numeric <= 5 && floor($numeric) == $numeric) continue;   // "3 actions", "5 stars"
        if ($numeric >= 2000 && $numeric <= 2100 && floor($numeric) == $numeric) continue; // years
        if (!isset($allowed[$clean]) && !isset($allowed[(string) round($numeric)]) && !isset($allowed[number_format($numeric, 1, '.', '')])) return false;
    }
    return true;
}

/**
 * Visit quality from anonymous visitor/visit IDs on page views (recorded from the date the
 * IDs were introduced; older page views have none and are left out of these figures).
 */
function kt_visit_quality(mysqli $conn, string $startSql, string $endSql): array
{
    $empty = ['tracked' => false, 'visits' => 0, 'unique_visitors' => 0, 'new_visitors' => 0, 'returning_visitors' => 0, 'pages_per_visit' => 0.0, 'bounce_rate' => null, 'tracking_since' => null];
    if (!kt_insight_has_column($conn, 'page_views', 'visit_id')) return $empty;
    $since = $conn->query('SELECT MIN(viewed_at) first_seen FROM page_views WHERE visit_id IS NOT NULL');
    $trackingSince = $since ? ($since->fetch_assoc()['first_seen'] ?? null) : null;
    if ($trackingSince === null) return $empty;

    // A "bounce" is a visit that saw one page and opened no listing.
    $itemJoin = kt_insight_has_column($conn, 'item_views', 'visit_id')
        ? 'LEFT JOIN (SELECT visit_id, COUNT(*) listings FROM item_views WHERE visit_id IS NOT NULL AND viewed_at >= ? AND viewed_at < ? GROUP BY visit_id) i ON i.visit_id = p.visit_id'
        : '';
    $sql = 'SELECT COUNT(*) visits, SUM(p.pages) page_views, SUM(p.pages = 1' . ($itemJoin ? ' AND COALESCE(i.listings, 0) = 0' : '') . ') bounces
            FROM (SELECT visit_id, COUNT(*) pages FROM page_views WHERE visit_id IS NOT NULL AND viewed_at >= ? AND viewed_at < ? GROUP BY visit_id) p ' . $itemJoin;
    $stmt = $conn->prepare($sql);
    $visitRow = [];
    if ($stmt) {
        if ($itemJoin) $stmt->bind_param('ssss', $startSql, $endSql, $startSql, $endSql);
        else $stmt->bind_param('ss', $startSql, $endSql);
        $stmt->execute();
        $visitRow = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }
    $visits = (int) ($visitRow['visits'] ?? 0);

    // A visitor is "returning" when their first recorded page view is before this period.
    $visitorRow = [];
    $stmt = $conn->prepare('SELECT COUNT(*) visitors, SUM(first_seen < ?) returning_visitors FROM (
            SELECT p.visitor_id, MIN(p.viewed_at) first_seen FROM page_views p
            WHERE p.visitor_id IN (SELECT DISTINCT visitor_id FROM page_views WHERE visitor_id IS NOT NULL AND viewed_at >= ? AND viewed_at < ?)
            GROUP BY p.visitor_id) v');
    if ($stmt) {
        $stmt->bind_param('sss', $startSql, $startSql, $endSql);
        $stmt->execute();
        $visitorRow = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }
    $uniqueVisitors = (int) ($visitorRow['visitors'] ?? 0);
    $returning = (int) ($visitorRow['returning_visitors'] ?? 0);
    return [
        'tracked' => $visits > 0,
        'visits' => $visits,
        'unique_visitors' => $uniqueVisitors,
        'new_visitors' => max(0, $uniqueVisitors - $returning),
        'returning_visitors' => $returning,
        'pages_per_visit' => $visits ? round((int) $visitRow['page_views'] / $visits, 1) : 0.0,
        'bounce_rate' => $visits ? (int) round((int) $visitRow['bounces'] / $visits * 100) : null,
        'tracking_since' => $trackingSince,
    ];
}

/** Visits per device type (Mobile / Desktop / Tablet / Unknown). */
function kt_device_breakdown(mysqli $conn, string $startSql, string $endSql): array
{
    if (!kt_insight_has_column($conn, 'page_views', 'device')) return [];
    $stmt = $conn->prepare("SELECT COALESCE(NULLIF(device, ''), 'Unknown') device_name, COUNT(DISTINCT visit_id) visits FROM page_views WHERE visit_id IS NOT NULL AND viewed_at >= ? AND viewed_at < ? GROUP BY device_name ORDER BY visits DESC");
    if (!$stmt) return [];
    $stmt->bind_param('ss', $startSql, $endSql);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $rows[(string) $row['device_name']] = (int) $row['visits'];
    $stmt->close();
    return $rows;
}

/**
 * "Intent to visit" actions: directions, calls, messages, emails, shares, social and website links.
 * Older map clicks recorded before intents existed count as directions.
 */
function kt_intent_breakdown(mysqli $conn, string $startSql, string $endSql): array
{
    static $cache = [];
    $cacheKey = $startSql . '|' . $endSql;
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];
    $labels = ['directions' => 'Directions / map', 'call' => 'Phone calls', 'message' => 'Messages', 'email' => 'Emails', 'website' => 'Website links', 'social' => 'Social pages', 'share' => 'Shares'];
    $counts = array_fill_keys(array_keys($labels), 0);
    if (!kt_insight_table_exists($conn, 'user_activity_log')) return [];
    $stmt = $conn->prepare("SELECT CASE WHEN metadata LIKE '%\"intent\"%' THEN JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.intent')) WHEN activity_type = 'map_interaction' THEN 'directions' ELSE NULL END intent_name, COUNT(*) total
            FROM user_activity_log WHERE activity_date >= ? AND activity_date < ? AND (activity_type = 'map_interaction' OR metadata LIKE '%\"intent\"%') GROUP BY intent_name");
    if (!$stmt) return [];
    $stmt->bind_param('ss', $startSql, $endSql);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        if (isset($counts[(string) $row['intent_name']])) $counts[(string) $row['intent_name']] += (int) $row['total'];
    }
    $stmt->close();
    $result = [];
    foreach ($counts as $key => $total) if ($total > 0) $result[$labels[$key]] = $total;
    arsort($result);
    return $cache[$cacheKey] = $result;
}
