<?php
/*
 * Trending model for KulToura listings — explainable rules, computed from recorded activity.
 *
 * 1) Activity points per listing per day
 *      view of the listing ............................ 1
 *      rating ......................................... 3
 *      rating with a written comment (20+ characters) . 5
 *      intent action (directions, call, message,
 *        website, social page, share) ................. 4
 *    (Sample/generated reviews are excluded, as in the popularity score.)
 *
 * 2) Compare THIS WEEK (last 7 days) with the listing's USUAL WEEK
 *    (average week over the 28 days before that).
 *
 * 3) TRENDING when all hold:
 *      a) volume   — at least KT_TREND_MIN_POINTS points this week
 *      b) growth   — at least KT_TREND_GROWTH × the usual week (a new listing with no history needs only (a))
 *      c) not luck — Poisson z-score (this week − usual) / √usual ≥ KT_TREND_MIN_Z (≈95% confidence)
 *      d) reception — with 2+ reviews this week, their average is at least KT_TREND_MIN_RATING
 *
 * 4) RISING (likely to trend) when not trending but:
 *      at least KT_RISING_MIN_POINTS points, at least KT_RISING_GROWTH × the usual week, and at the pace of the
 *      last 3 days the next 7 days would meet the trending volume and growth rules.
 *
 * 5) WATCH (attention with mixed reviews) when a, b and c hold but d fails.
 */
const KT_TREND_MIN_POINTS = 15;
const KT_TREND_GROWTH = 1.5;
const KT_TREND_MIN_Z = 2.0;
const KT_TREND_MIN_RATING = 3.5;
const KT_RISING_MIN_POINTS = 6;
const KT_RISING_GROWTH = 1.25;
const KT_TREND_POINTS = ['view' => 1, 'rating' => 3, 'comment' => 5, 'intent' => 4];

/**
 * Returns ['type-id' => [status, points_week, points_usual, growth, z, projected_week, views, ratings, comments,
 *  intents, recent_rating, reason]] for every listing with activity in the last 35 days.
 * status is 'trending', 'rising', 'watch' or 'steady'.
 */
function kt_trending_status(mysqli $conn, ?DateTimeImmutable $today = null): array
{
    static $cache = null;
    if ($cache !== null && $today === null) return $cache;
    $today = $today ?? new DateTimeImmutable('today');
    $weekStart = $today->modify('-6 days');            // last 7 days including today
    $last3Start = $today->modify('-2 days');           // last 3 days including today
    $baselineStart = $weekStart->modify('-28 days');   // the 28 days before this week
    $fromSql = $baselineStart->format('Y-m-d 00:00:00');
    $toSql = $today->modify('+1 day')->format('Y-m-d 00:00:00');
    $weekKey = $weekStart->format('Y-m-d');
    $last3Key = $last3Start->format('Y-m-d');

    $rows = [];
    $add = static function (string $key, string $day, string $kind, int $count = 1, ?int $rating = null) use (&$rows, $weekKey, $last3Key) {
        if (!isset($rows[$key])) $rows[$key] = ['week' => 0, 'last3' => 0, 'baseline' => 0, 'views' => 0, 'ratings' => 0, 'comments' => 0, 'intents' => 0, 'rating_total' => 0, 'rating_count' => 0];
        $points = KT_TREND_POINTS[$kind] * $count;
        if ($day >= $weekKey) {
            $rows[$key]['week'] += $points;
            if ($day >= $last3Key) $rows[$key]['last3'] += $points;
            $rows[$key][$kind === 'view' ? 'views' : ($kind === 'intent' ? 'intents' : ($kind === 'comment' ? 'comments' : 'ratings'))] += $count;
            if ($rating !== null) { $rows[$key]['rating_total'] += $rating; $rows[$key]['rating_count']++; }
        } else {
            $rows[$key]['baseline'] += $points;
        }
    };

    if ($stmt = $conn->prepare('SELECT item_type, item_id, DATE(viewed_at) day, COUNT(*) total FROM item_views WHERE viewed_at >= ? AND viewed_at < ? GROUP BY item_type, item_id, day')) {
        $stmt->bind_param('ss', $fromSql, $toSql);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $add($row['item_type'] . '-' . (int) $row['item_id'], (string) $row['day'], 'view', (int) $row['total']);
        $stmt->close();
    }
    $reviewTable = $conn->query("SHOW TABLES LIKE 'item_reviews'");
    if ($reviewTable && $reviewTable->num_rows > 0 && ($stmt = $conn->prepare("SELECT item_type, item_id, DATE(created_at) day, rating, CHAR_LENGTH(TRIM(COALESCE(review_text, ''))) comment_length FROM item_reviews WHERE status = 'visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at >= ? AND created_at < ?"))) {
        $stmt->bind_param('ss', $fromSql, $toSql);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $add($row['item_type'] . '-' . (int) $row['item_id'], (string) $row['day'], (int) $row['comment_length'] >= 20 ? 'comment' : 'rating', 1, (int) $row['rating']);
        }
        $stmt->close();
    }
    $activityTable = $conn->query("SHOW TABLES LIKE 'user_activity_log'");
    if ($activityTable && $activityTable->num_rows > 0 && ($stmt = $conn->prepare("SELECT item_type, item_id, DATE(activity_date) day, COUNT(*) total FROM user_activity_log WHERE item_id IS NOT NULL AND item_type IS NOT NULL AND activity_date >= ? AND activity_date < ? AND (activity_type = 'map_interaction' OR metadata LIKE '%\"intent\"%') GROUP BY item_type, item_id, day"))) {
        $stmt->bind_param('ss', $fromSql, $toSql);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $add($row['item_type'] . '-' . (int) $row['item_id'], (string) $row['day'], 'intent', (int) $row['total']);
        $stmt->close();
    }

    $result = [];
    foreach ($rows as $key => $row) {
        $week = $row['week'];
        $usual = $row['baseline'] / 4;                                 // average week over the previous 28 days
        $growth = $usual > 0 ? $week / $usual : null;                  // null = no history (new)
        $z = $usual > 0 ? ($week - $usual) / sqrt($usual) : ($week > 0 ? INF : 0.0);
        $projected = (int) round($row['last3'] / 3 * 7);               // next 7 days at the last-3-days pace
        $recentRating = $row['rating_count'] ? $row['rating_total'] / $row['rating_count'] : null;
        $meetsVolume = $week >= KT_TREND_MIN_POINTS;
        $meetsGrowth = $usual == 0 ? true : $week >= KT_TREND_GROWTH * $usual;
        $meetsChance = $z >= KT_TREND_MIN_Z;
        $meetsReception = $row['rating_count'] < 2 || $recentRating >= KT_TREND_MIN_RATING;
        $status = 'steady';
        if ($meetsVolume && $meetsGrowth && $meetsChance) {
            $status = $meetsReception ? 'trending' : 'watch';
        } elseif ($week >= KT_RISING_MIN_POINTS && ($usual == 0 || $week >= KT_RISING_GROWTH * $usual)
            && $projected >= KT_TREND_MIN_POINTS && ($usual == 0 || $projected >= KT_TREND_GROWTH * $usual)) {
            $status = 'rising';
        }
        $growthText = $growth === null ? 'new activity (no earlier history)' : ('+' . max(0, (int) round(($growth - 1) * 100)) . '% vs a usual week (' . round($usual, 1) . ' pts)');
        $reason = match ($status) {
            'trending' => $week . ' activity points this week, ' . $growthText . '.',
            'watch' => $week . ' points this week (' . $growthText . '), but recent reviews average ' . number_format((float) $recentRating, 1) . '★.',
            'rising' => $week . ' points this week; at the last 3 days\' pace it reaches about ' . $projected . ' next week.',
            default => $week . ' points this week, ' . ($growth === null ? 'no earlier history' : round($growth, 2) . '× a usual week') . '.',
        };
        $result[$key] = [
            'status' => $status, 'points_week' => $week, 'points_usual' => round($usual, 1),
            'growth' => $growth === null ? null : round($growth, 2), 'z' => is_infinite($z) ? null : round($z, 1),
            'projected_week' => $projected, 'views' => $row['views'], 'ratings' => $row['ratings'], 'comments' => $row['comments'],
            'intents' => $row['intents'], 'recent_rating' => $recentRating === null ? null : round($recentRating, 1), 'reason' => $reason,
        ];
    }
    if ($today == new DateTimeImmutable('today')) $cache = $result;
    return $result;
}
