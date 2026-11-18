<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super admin'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
$period = (string) ($_GET['period'] ?? 'month');
$fromInput = trim((string) ($_GET['date_from'] ?? ''));
$toInput = trim((string) ($_GET['date_to'] ?? ''));
$today = new DateTimeImmutable('today');
$from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromInput);
$to = DateTimeImmutable::createFromFormat('!Y-m-d', $toInput);
$custom = $from && $to && $from->format('Y-m-d') === $fromInput && $to->format('Y-m-d') === $toInput && $from <= $to && $to <= $today;
$start = $custom ? DateTime::createFromImmutable($from) : new DateTime('first day of this month');
if (!$custom) {
    switch ($period) {
        case 'all': $start = new DateTime('1000-01-01'); break;
        case 'today': $start = new DateTime('today'); break;
        case 'week': $start = new DateTime('monday this week'); break;
        case 'year': $start = new DateTime('first day of january this year'); break;
        case 'month': break;
        default: $period = 'month';
    }
}
$startSql = $start->format('Y-m-d 00:00:00');
$end = $custom ? $to->modify('+1 day') : $today->modify('+1 day');
$endSql = $end->format('Y-m-d 00:00:00');
$catalog = [
    'nature' => ['Nature', 'nature', "SELECT COUNT(*) c FROM destination WHERE status='active' AND category='nature'"],
    'industry' => ['Industry Zone', 'industry', "SELECT COUNT(*) c FROM destination WHERE status='active' AND category='industry'"],
    'resort' => ['Resorts', 'resort', "SELECT COUNT(*) c FROM destination WHERE status='active' AND category='resort'"],
    'church' => ['Churches', 'church', "SELECT COUNT(*) c FROM destination WHERE status='active' AND category='church'"],
    'product' => ['Products of Malvar', 'product', "SELECT COUNT(*) c FROM products WHERE status='active'"],
    'fiesta' => ['Fiestas', 'fiesta', "SELECT COUNT(*) c FROM fiestas WHERE status='active'"],
    'person' => ['People of Malvar', 'person', "SELECT COUNT(*) c FROM people WHERE status='active'"],
    'restaurant' => ['Restaurants', 'restaurant', "SELECT COUNT(*) c FROM restaurants WHERE status='active'"],
    'accommodation' => ['Accommodation', 'accommodation', "SELECT COUNT(*) c FROM destination WHERE status='active' AND category='accommodation'"],
    'bank' => ['Banks', 'bank', "SELECT COUNT(*) c FROM destination WHERE status='active' AND category='bank'"],
    'service' => ['Other Services', 'service', "SELECT COUNT(*) c FROM destination WHERE status='active' AND category='service'"],
];
$selected = (string) ($_GET['category'] ?? '');
if ($selected !== '' && !isset($catalog[$selected])) $selected = '';
$selectedParent = (string) ($_GET['parent'] ?? '');
if (!isset(kt_activity_parents()[$selectedParent])) $selectedParent = '';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="kultoura-tourism-analytics-' . ($custom ? $from->format('Ymd') . '-to-' . $to->format('Ymd') : $period) . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Category', 'Active Listings', 'Content Views', 'Clicks', 'Searches', 'Ratings', 'Average Rating']);
foreach ($catalog as $key => [$label, $type, $sql]) {
    if ($selected !== '' && $key !== $selected) continue;
    if ($selectedParent !== '' && !in_array($key, kt_activity_parents()[$selectedParent]['categories'], true)) continue;
    $res = $conn->query($sql);
    $listings = (int) ($res ? ($res->fetch_assoc()['c'] ?? 0) : 0);
    $items = analytics_top_items($conn, 10000, $startSql, [], [$type], $endSql);
    $views = array_sum(array_column($items, 'views'));
    $clicks = kt_activity_count($conn, 'click', $key, null, $startSql, $endSql);
    $searches = kt_activity_count($conn, 'search', $key, null, $startSql, $endSql);
    $ratings = 0; $average = 0.0;
    $reviewsExist = $conn->query("SHOW TABLES LIKE 'item_reviews'");
    if ($reviewsExist && $reviewsExist->num_rows) {
        $stmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(AVG(rating),0) avg_rating FROM item_reviews WHERE status='visible' AND item_type=? AND created_at>=? AND created_at<?");
        if ($stmt) {
            $stmt->bind_param('sss', $type, $startSql, $endSql);
            $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
            $ratings = (int) ($row['total'] ?? 0); $average = (float) ($row['avg_rating'] ?? 0);
        }
    }
    fputcsv($out, [$label, $listings, $views, $clicks, $searches, $ratings, $ratings ? number_format($average, 2) : '']);
}
fclose($out);
