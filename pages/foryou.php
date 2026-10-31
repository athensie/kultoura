<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
require_once __DIR__ . '/../config/maintenance.php';
kt_maintenance_gate($conn);
include '../config/analytics.php';
include '../config/ai_recommend.php';
analytics_track($conn, 'foryou');
$siteName = "KULTOURA";
$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? '');
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}
if (!isset($_SESSION['itemHistory'])) {
    $_SESSION['itemHistory'] = [];
}
if (isset($_GET['track'])) {
    $track = preg_replace('/[^a-z_]/', '', strtolower($_GET['track']));
    if ($track !== '') {
        $_SESSION['history'][] = $track;
        $_SESSION['history'] = array_slice($_SESSION['history'], -30);
    }
    header('Location: foryou.php');
    exit;
}
$imageSubfolder = [
    'product'       => 'food',
    'restaurant'    => 'food',
    'nature'        => 'destinations',
    'resort'        => 'destinations',
    'industry'      => 'destinations',
    'accommodation' => 'destinations',
    'bank'          => 'destinations',
    'service'       => 'destinations',
    'fiesta'        => 'fiestas',
    'person'        => 'people',
];
function fy_image_src(string $category, ?string $rawImage, array $imageSubfolder): string
{
    if (empty($rawImage)) return '';
    return '../assets/uploads/' . ($imageSubfolder[$category] ?? $category) . '/' . basename($rawImage);
}
$favoritedKeys = [];
if ($isLoggedIn) {
    $favUserId = (int) $_SESSION['user_id'];
    if ($favStmt = $conn->prepare("SELECT item_type, item_id FROM favorites WHERE user_id = ?")) {
        $favStmt->bind_param('i', $favUserId);
        $favStmt->execute();
        $favRows = $favStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $favStmt->close();
        foreach ($favRows as $fr) {
            $favoritedKeys[$fr['item_type'] . '-' . $fr['item_id']] = true;
        }
    }
}
function fy_is_favorited(string $itemType, int $itemId, array $favoritedKeys): bool
{
    return isset($favoritedKeys[$itemType . '-' . $itemId]);
}
$diaryItemKeys = [];
if ($isLoggedIn) {
    $diaryUserId = (int) $_SESSION['user_id'];
    if ($diaryStmt = $conn->prepare("SELECT item_type, item_id FROM diary_entries WHERE user_id = ? ORDER BY created_at ASC")) {
        $diaryStmt->bind_param('i', $diaryUserId);
        $diaryStmt->execute();
        $diaryRows = $diaryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $diaryStmt->close();
        foreach ($diaryRows as $dr) {
            $diaryItemKeys[] = $dr['item_type'] . '-' . $dr['item_id'];
        }
    }
}
$filterGroup = [
    'product'       => 'product',
    'restaurant'    => 'restaurant',
    'nature'        => 'destination',
    'resort'        => 'destination',
    'industry'      => 'destination',
    'accommodation' => 'destination',
    'bank'          => 'destination',
    'service'       => 'destination',
    'church'        => 'destination',
    'fiesta'        => 'fiesta',
    'person'        => 'person',
];
$places = [];
if ($result = $conn->query("SELECT product_id, product_name, category, description, image, location, latitude, longitude, created_at FROM products WHERE status = 'active'")) {
    while ($row = $result->fetch_assoc()) {
        $itemId = (int) $row['product_id'];
        $places[] = [
            'id'         => 'product-' . $itemId,
            'name'       => $row['product_name'],
            'category'   => 'product',
            'group'      => $filterGroup['product'],
            'badgeText'  => $row['category'] ?: 'Product',
            'image'      => fy_image_src('product', $row['image'] ?? null, $imageSubfolder),
            'lat'        => ($row['latitude'] !== null && $row['latitude'] !== '') ? (float) $row['latitude'] : null,
            'lng'        => ($row['longitude'] !== null && $row['longitude'] !== '') ? (float) $row['longitude'] : null,
            'desc'       => $row['description'] ?? '',
            'location'   => $row['location'] ?? '',
            'createdAt'  => strtotime($row['created_at']),
            'link'       => 'tourism/products.php',
            'itemType'   => 'product',
            'itemId'     => $itemId,
            'favorited'  => fy_is_favorited('product', $itemId, $favoritedKeys),
        ];
    }
}
if ($result = $conn->query("SELECT restaurant_id, restaurant_name, category, description, image, address, latitude, longitude, created_at FROM restaurants WHERE status = 'active'")) {
    while ($row = $result->fetch_assoc()) {
        $itemId = (int) $row['restaurant_id'];
        $places[] = [
            'id'         => 'restaurant-' . $itemId,
            'name'       => $row['restaurant_name'],
            'category'   => 'restaurant',
            'group'      => $filterGroup['restaurant'],
            'badgeText'  => $row['category'] ?: 'Restaurant',
            'image'      => fy_image_src('restaurant', $row['image'] ?? null, $imageSubfolder),
            'lat'        => ($row['latitude'] !== null && $row['latitude'] !== '') ? (float) $row['latitude'] : null,
            'lng'        => ($row['longitude'] !== null && $row['longitude'] !== '') ? (float) $row['longitude'] : null,
            'desc'       => $row['description'] ?? '',
            'location'   => $row['address'] ?? '',
            'createdAt'  => strtotime($row['created_at']),
            'link'       => 'tourism/restaurants.php',
            'itemType'   => 'restaurant',
            'itemId'     => $itemId,
            'favorited'  => fy_is_favorited('restaurant', $itemId, $favoritedKeys),
        ];
    }
}
$destinationMeta = [
    'nature'        => ['link' => 'tourism/nature.php'],
    'resort'        => ['link' => 'tourism/resort.php'],
    'industry'      => ['link' => 'tourism/industry.php'],
    'accommodation' => ['link' => 'tourism/accommodation.php'],
    'bank'          => ['link' => 'tourism/banks.php'],
    'service'       => ['link' => 'tourism/services.php'],
    'church'        => ['link' => 'tourism/churches.php'],
];
if ($result = $conn->query("SELECT destination_id, destination_name, description, image, category, address, latitude, longitude, created_at FROM destination WHERE status = 'active'")) {
    while ($row = $result->fetch_assoc()) {
        $cat = $row['category'];
        if (!isset($destinationMeta[$cat])) continue;
        $itemId = (int) $row['destination_id'];
        $places[] = [
            'id'         => 'destination-' . $itemId,
            'name'       => $row['destination_name'],
            'category'   => $cat,
            'group'      => $filterGroup[$cat],
            'badgeText'  => ucfirst($cat),
            'image'      => fy_image_src($cat, $row['image'] ?? null, $imageSubfolder),
            'lat'        => ($row['latitude'] !== null && $row['latitude'] !== '') ? (float) $row['latitude'] : null,
            'lng'        => ($row['longitude'] !== null && $row['longitude'] !== '') ? (float) $row['longitude'] : null,
            'desc'       => $row['description'] ?? '',
            'location'   => $row['address'] ?? '',
            'createdAt'  => strtotime($row['created_at']),
            'link'       => $destinationMeta[$cat]['link'],
            'itemType'   => 'destination',
            'itemId'     => $itemId,
            'favorited'  => fy_is_favorited('destination', $itemId, $favoritedKeys),
        ];
    }
}
if ($result = $conn->query("SELECT fiesta_id, fiesta_name, type, description, image, location, latitude, longitude, created_at FROM fiestas WHERE status = 'active' AND celebration_date >= CURDATE()")) {
    while ($row = $result->fetch_assoc()) {
        $itemId = (int) $row['fiesta_id'];
        $places[] = [
            'id'         => 'fiesta-' . $itemId,
            'name'       => $row['fiesta_name'],
            'category'   => 'fiesta',
            'group'      => $filterGroup['fiesta'],
            'badgeText'  => $row['type'] ?: 'Fiesta',
            'image'      => fy_image_src('fiesta', $row['image'] ?? null, $imageSubfolder),
            'lat'        => ($row['latitude'] !== null && $row['latitude'] !== '') ? (float) $row['latitude'] : null,
            'lng'        => ($row['longitude'] !== null && $row['longitude'] !== '') ? (float) $row['longitude'] : null,
            'desc'       => $row['description'] ?? '',
            'location'   => $row['location'] ?? '',
            'createdAt'  => strtotime($row['created_at']),
            'link'       => 'tourism/fiestas.php',
            'itemType'   => 'fiesta',
            'itemId'     => $itemId,
            'favorited'  => fy_is_favorited('fiesta', $itemId, $favoritedKeys),
        ];
    }
}
if ($result = $conn->query("SELECT person_id, fullname, description, image, title, created_at FROM people WHERE status = 'active'")) {
    while ($row = $result->fetch_assoc()) {
        $itemId = (int) $row['person_id'];
        $places[] = [
            'id'         => 'person-' . $itemId,
            'name'       => $row['fullname'],
            'category'   => 'person',
            'group'      => $filterGroup['person'],
            'badgeText'  => $row['title'] ?: 'Person',
            'image'      => fy_image_src('person', $row['image'] ?? null, $imageSubfolder),
            'lat'        => null,
            'lng'        => null,
            'desc'       => $row['description'] ?? '',
            'location'   => $row['title'] ?? '',
            'createdAt'  => strtotime($row['created_at']),
            'link'       => 'tourism/people.php',
            'itemType'   => 'person',
            'itemId'     => $itemId,
            'favorited'  => fy_is_favorited('person', $itemId, $favoritedKeys),
        ];
    }
}
$categoryLabels = [
    'product'       => 'Local Products',
    'restaurant'    => 'Local Cuisine',
    'nature'        => 'Natural Wonders',
    'resort'        => 'Resorts & Relaxation',
    'industry'      => 'Industry Zones',
    'church'        => 'Churches',
    'accommodation' => 'Accommodation',
    'bank'          => 'Banks & Finance',
    'service'       => 'Essential Services',
    'fiesta'        => 'Fiestas & Events',
    'person'        => 'Faces of Malvar',
];
function fy_tokenize(string $text): array
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $stopwords = ['the', 'a', 'an', 'and', 'or', 'of', 'in', 'on', 'at', 'to', 'for',
        'with', 'is', 'are', 'was', 'were', 'it', 'its', 'this', 'that', 'as', 'by', 'from', 'be'];
    $tokens = preg_split('/\s+/', trim($text));
    return array_values(array_filter($tokens, fn($t) => $t !== '' && strlen($t) > 1 && !in_array($t, $stopwords, true)));
}
function fy_tfidf_vector(array $tokens, array $docFreq, int $totalDocs): array
{
    if (empty($tokens)) return [];
    $termCounts = array_count_values($tokens);
    $maxCount = max($termCounts);
    $vector = [];
    foreach ($termCounts as $term => $count) {
        $tf = 0.5 + 0.5 * ($count / $maxCount);
        $idf = log(($totalDocs + 1) / (($docFreq[$term] ?? 0) + 1)) + 1;
        $vector[$term] = $tf * $idf;
    }
    return $vector;
}
function fy_cosine_similarity(array $a, array $b): float
{
    if (empty($a) || empty($b)) return 0.0;
    $dot = 0.0;
    foreach ($a as $term => $w) {
        if (isset($b[$term])) $dot += $w * $b[$term];
    }
    if ($dot == 0.0) return 0.0;
    $normA = sqrt(array_sum(array_map(fn($w) => $w * $w, $a)));
    $normB = sqrt(array_sum(array_map(fn($w) => $w * $w, $b)));
    return ($normA > 0 && $normB > 0) ? $dot / ($normA * $normB) : 0.0;
}
function fy_recency_weighted_scores(array $items, float $weight): array
{
    $scores = [];
    $n = count($items);
    foreach ($items as $i => $key) {
        $recencyFactor = $n > 1 ? 0.5 + ($i / ($n - 1)) : 1.0;
        $scores[$key] = ($scores[$key] ?? 0) + ($weight * $recencyFactor);
    }
    return $scores;
}
$aiAvailable = ai_configured();
$aiFetchBudget = 15;
$placesByKey = [];
$documents = [];
$embedTexts = [];
foreach ($places as $p) {
    $placesByKey[$p['itemType'] . '-' . $p['itemId']] = $p;
    $embedTexts[$p['id']] = $p['name'] . '. ' . ($p['badgeText'] ?? '') . '. ' . ucfirst($p['category']) . '. ' . ($p['desc'] ?? '');
    $doc = $p['name'] . ' ' . $p['name'] . ' ' . ($p['badgeText'] ?? '') . ' '
         . $p['category'] . ' ' . $p['category'] . ' ' . $p['category'] . ' '
         . ($p['desc'] ?? '');
    $documents[$p['id']] = fy_tokenize($doc);
}
$docFreq = [];
foreach ($documents as $tokens) {
    foreach (array_unique($tokens) as $t) {
        $docFreq[$t] = ($docFreq[$t] ?? 0) + 1;
    }
}
$totalDocs = count($documents);
$placeVectors = [];
foreach ($places as $p) {
    if ($aiAvailable) {
        $embedding = ai_get_cached_embedding($conn, $p['itemType'], $p['itemId'], $embedTexts[$p['id']]);
        if ($embedding === null && $aiFetchBudget > 0) {
            $embedding = ai_get_place_embedding($conn, $p['itemType'], $p['itemId'], $embedTexts[$p['id']]);
            $aiFetchBudget--;
        }
        $placeVectors[$p['id']] = $embedding ?? [];
    } else {
        $placeVectors[$p['id']] = fy_tfidf_vector($documents[$p['id']], $docFreq, $totalDocs);
    }
}
$history = $_SESSION['history'];
$itemHistory = $_SESSION['itemHistory'];
$hasHistory = count($history) > 0 || count($itemHistory) > 0 || count($favoritedKeys) > 0 || count($diaryItemKeys) > 0;
$freq = fy_recency_weighted_scores($history, 1.0);
$itemHistoryCats = array_map(fn($h) => $h['type'], $itemHistory);
foreach (fy_recency_weighted_scores($itemHistoryCats, 3.0) as $cat => $score) {
    $freq[$cat] = ($freq[$cat] ?? 0) + $score;
}
$diaryCats = array_values(array_filter(array_map(fn($key) => $placesByKey[$key]['category'] ?? null, $diaryItemKeys)));
foreach (fy_recency_weighted_scores($diaryCats, 4.0) as $cat => $score) {
    $freq[$cat] = ($freq[$cat] ?? 0) + $score;
}
foreach (array_keys($favoritedKeys) as $key) {
    if (isset($placesByKey[$key])) {
        $freq[$placesByKey[$key]['category']] = ($freq[$placesByKey[$key]['category']] ?? 0) + 5.0;
    }
}
arsort($freq);
$topCategories = array_keys($freq);
$maxFreq = !empty($freq) ? max($freq) : 0;
$profileVector = [];
$profileWeight = 0.0;
$itemHistoryKeyed = array_map(fn($h) => $h['type'] . '-' . $h['id'], $itemHistory);
foreach (fy_recency_weighted_scores($itemHistoryKeyed, 3.0) as $key => $weight) {
    if (!isset($placesByKey[$key])) continue;
    foreach ($placeVectors[$placesByKey[$key]['id']] as $term => $w) {
        $profileVector[$term] = ($profileVector[$term] ?? 0) + ($w * $weight);
    }
    $profileWeight += $weight;
}
foreach (fy_recency_weighted_scores($diaryItemKeys, 4.0) as $key => $weight) {
    if (!isset($placesByKey[$key])) continue;
    foreach ($placeVectors[$placesByKey[$key]['id']] as $term => $w) {
        $profileVector[$term] = ($profileVector[$term] ?? 0) + ($w * $weight);
    }
    $profileWeight += $weight;
}
foreach (array_keys($favoritedKeys) as $key) {
    if (!isset($placesByKey[$key])) continue;
    $weight = 5.0;
    foreach ($placeVectors[$placesByKey[$key]['id']] as $term => $w) {
        $profileVector[$term] = ($profileVector[$term] ?? 0) + ($w * $weight);
    }
    $profileWeight += $weight;
}
if ($profileWeight > 0) {
    foreach ($profileVector as $term => $w) {
        $profileVector[$term] = $w / $profileWeight;
    }
}
$viewedItemKeys = [];
foreach ($itemHistory as $h) {
    $viewedItemKeys[$h['type'] . '-' . $h['id']] = true;
}
foreach ($diaryItemKeys as $key) {
    $viewedItemKeys[$key] = true;
}
$interestScores = [];
foreach ($places as $p) {
    $contentScore = fy_cosine_similarity($profileVector, $placeVectors[$p['id']]);
    $categoryScore = $maxFreq > 0 ? (($freq[$p['category']] ?? 0) / $maxFreq) : 0;
    $interestScores[$p['id']] = $profileWeight > 0
        ? (0.65 * $contentScore) + (0.35 * $categoryScore)
        : $categoryScore;
}
$recommended = [];
if ($hasHistory) {
    $scored = [];
    foreach ($places as $p) {
        $key = $p['itemType'] . '-' . $p['itemId'];
        if (isset($viewedItemKeys[$key])) continue;
        $scored[] = ['place' => $p, 'score' => $interestScores[$p['id']]];
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $recommended = array_column(array_slice($scored, 0, 6), 'place');
    if (count($recommended) < 6) {
        $seen = [];
        foreach ($recommended as $p) $seen[$p['id']] = true;
        foreach ($topCategories as $cat) {
            foreach ($places as $p) {
                if ($p['category'] !== $cat || isset($seen[$p['id']])) continue;
                $seen[$p['id']] = true;
                $recommended[] = $p;
                if (count($recommended) >= 6) break 2;
            }
        }
    }
} else {
    $used = [];
    foreach ($places as $p) {
        if (!isset($used[$p['category']])) {
            $recommended[] = $p;
            $used[$p['category']] = true;
        }
    }
    $recommended = array_slice($recommended, 0, 6);
}
$categoryCounts = [];
foreach ($places as $p) {
    $categoryCounts[$p['category']] = ($categoryCounts[$p['category']] ?? 0) + 1;
}
$hubTiles = [
    ['category' => 'product',    'title' => 'Products',        'link' => 'tourism/products.php'],
    ['category' => 'restaurant', 'title' => 'Restaurants',      'link' => 'tourism/restaurants.php'],
    ['category' => 'nature',     'title' => 'Nature',           'link' => 'tourism/nature.php'],
    ['category' => 'resort',     'title' => 'Resorts',          'link' => 'tourism/resort.php'],
    ['category' => 'industry',   'title' => 'Industry Zone',    'link' => 'tourism/industry.php'],
    ['category' => 'fiesta',     'title' => 'Fiestas',          'link' => 'tourism/fiestas.php'],
    ['category' => 'person',     'title' => 'People of Malvar', 'link' => 'tourism/people.php'],
];
$categoryIcons = [
    'product'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>',
    'restaurant' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg>',
    'nature'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg>',
    'resort'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M3 14c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M3 20c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/></svg>',
    'industry'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V10l5 3v-3l5 3V5l4 3v13"/></svg>',
    'fiesta'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    'person'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>',
];
$heroImage = '';
$heroSource = !empty($recommended) ? $recommended : $places;
foreach ($heroSource as $p) {
    if (!empty($p['image'])) {
        $heroImage = $p['image'];
        break;
    }
}
foreach ($places as &$p) {
    $p['interestScore'] = $interestScores[$p['id']] ?? 0;
    $p['viewed'] = isset($viewedItemKeys[$p['itemType'] . '-' . $p['itemId']]);
}
unset($p);
$placesJson = json_encode($places, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>For You · <?php echo $siteName; ?></title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/search.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/foryou.css">
</head>
<body>
<header class="navbar">
    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../assets/images/salakot.png?v=20261008" alt=""></span><span class="navbar-greeting-text">Mabuhay, <?php echo $userName; ?></span></div>
    <?php else: ?>
        <div class="user-greeting-left navbar-brand-logo"><img src="../assets/images/kultoura.png" alt="KulToura"></div>
    <?php endif; ?>
    <nav class="nav-links">
        <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
        <div class="dropdown">
            <a href="tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR ▾</span></a>
            <div class="mega-menu">
                <div class="mega-column">
                    <h4>Local Products</h4>
                    <a href="tourism/products.php">Products</a>
                </div>
                <div class="mega-column">
                    <h4>Local Destinations</h4>
                    <a href="tourism/nature.php">Nature</a>
                    <a href="tourism/industry.php">Industry Zone</a>
                    <a href="tourism/resort.php">Resort</a>
                    <a href="tourism/churches.php">Churches</a>
                </div>
                <div class="mega-column">
                    <h4>Culture &amp; Services</h4>
                    <a href="tourism/fiestas.php">Fiestas</a>
                    <a href="tourism/people.php">People of Malvar</a>
                    <a href="tourism/services.php">Other Services</a>
                </div>
            </div>
        </div>
        <a href="tourism/restaurants.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg></span><span>RESTAURANTS</span></a>
        <a href="tourism/accommodation.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 12v8"/><path d="M2 12h20"/><path d="M2 8h6a2 2 0 0 1 2 2v2"/><path d="M22 8h-6a2 2 0 0 0-2 2v2"/></svg></span><span>ACCOMMODATION</span></a>
        <a href="tourism/banks.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M2 10h20L12 4z"/><path d="M8 14v4M12 14v4M16 14v4"/></svg></span><span>BANKS</span></a>
        <div class="dropdown">
            <a href="#" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></span><span>MORE ▾</span></a>
            <div class="mega-menu mega-menu-simple">
                <div class="mega-column">
                    <a href="foryou.php">For You</a>
                    <a href="traveldiary.php">Travel Diary</a><?php if ($isLoggedIn): ?><a href="profile.php">My Profile</a><?php endif; ?>
                    <a href="favorites.php">Favorites</a>
                    <a href="mostpopular.php">Most Popular</a>
                    <a href="about.php">About</a>
                </div>
            </div>
        </div>
            <?php if ($isLoggedIn): ?><a href="../auth/logout.php" class="nav-item nav-signout-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span><span>SIGN OUT</span></a><?php else: ?><a href="../auth/login.php" class="nav-item nav-signin-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg></span><span>SIGN IN</span></a><?php endif; ?>
    </nav>
        <div class="navbar-search" data-api="search_api.php" data-results="search.php">
        <button type="button" class="navbar-search-toggle" aria-label="Search" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </button>
        <form class="navbar-search-form" action="search.php" method="GET">
            <input type="search" name="q" class="navbar-search-input" placeholder="Search KulToura…" autocomplete="off">
        </form>
        <div class="navbar-search-dropdown"></div>
    </div>
<button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</header>
<main class="fy-page">
    <section class="fy-hero">
        <div class="fy-hero-text">
            <p class="section-label">CURATED FOR YOU</p>
            <h1 class="fy-title">
                <?php if ($isLoggedIn): ?>
                    Welcome back, <span class="brand-accent-text"><?php echo $userName; ?></span>
                <?php else: ?>
                    Made <span class="brand-accent-text">Just for You</span>
                <?php endif; ?>
            </h1>
            <p class="fy-sub">
                <?php if ($hasHistory): ?>
                    Picked based on what you've been exploring around Malvar.
                <?php else: ?>
                    Start exploring and this page will learn what you love.
                <?php endif; ?>
            </p>
            <?php if ($hasHistory): ?>
            <div class="fy-history-chips">
                <span class="fy-history-label">You've been exploring</span>
                <?php foreach (array_slice($topCategories, 0, 5) as $cat): ?>
                    <span class="fy-chip"><?php echo htmlspecialchars($categoryLabels[$cat] ?? $cat); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <a href="#fy-recommended" class="fy-hero-cta">Explore Now →</a>
        </div>
        <?php if ($heroImage): ?>
        <div class="fy-hero-media">
            <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="">
        </div>
        <?php endif; ?>
    </section>
    <div class="fy-layout-wrap">
    <div class="fy-page-layout">
        <aside class="fy-sidebar">
            <p class="fy-sidebar-label">Browse by Category</p>
            <nav class="fy-cat-list">
                <?php foreach ($hubTiles as $tile): ?>
                    <a href="<?php echo htmlspecialchars($tile['link']); ?>" class="fy-cat-item">
                        <span class="fy-cat-icon"><?php echo $categoryIcons[$tile['category']]; ?></span>
                        <div class="fy-cat-text">
                            <span class="fy-cat-title"><?php echo htmlspecialchars($tile['title']); ?></span>
                            <span class="fy-cat-count"><?php echo (int) ($categoryCounts[$tile['category']] ?? 0); ?> spots</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="fy-near-widget">
                <p class="fy-sidebar-label">Near You</p>
                <p class="fy-near-widget-sub">Top spots near your current location</p>
                <div id="fy-near-widget-list" class="fy-near-widget-list"></div>
                <p id="fy-near-widget-status" class="fy-near-widget-status">Enable location to see this.</p>
                <a href="#fy-nearby" class="fy-near-widget-link">View all nearby →</a>
            </div>
        </aside>
        <div class="fy-content-col">
    <section class="fy-section" id="fy-recommended">
        <div class="fy-section-head-row">
            <div class="fy-section-head">
                <h2><?php echo $hasHistory ? 'Because You Explored' : 'Trending in Malvar'; ?></h2>
                <p><?php echo $hasHistory
                    ? 'More places like the ones you\'ve viewed recently.'
                    : 'A little bit of everything to get you started.'; ?></p>
            </div>
            <div class="fy-toolbar">
                <div class="fy-filter-bar" id="fyFilterBar">
                    <button type="button" class="fy-filter-pill is-active" data-group="all">All</button>
                    <button type="button" class="fy-filter-pill" data-group="product">Products</button>
                    <button type="button" class="fy-filter-pill" data-group="restaurant">Restaurants</button>
                    <button type="button" class="fy-filter-pill" data-group="destination">Destinations</button>
                    <button type="button" class="fy-filter-pill" data-group="fiesta">Fiestas</button>
                    <button type="button" class="fy-filter-pill" data-group="person">People</button>
                </div>
                <select id="fySortSelect" class="fy-sort-select">
                    <option value="default">Recommended</option>
                    <option value="recent">Most Recent</option>
                </select>
            </div>
        </div>
        <div class="fy-grid" id="fyRecommendedGrid">
            <?php foreach ($recommended as $p): ?>
                <div class="fy-card" data-group="<?php echo htmlspecialchars($p['group']); ?>" data-created="<?php echo (int) $p['createdAt']; ?>" data-item-type="<?php echo htmlspecialchars($p['itemType']); ?>" data-item-id="<?php echo (int) $p['itemId']; ?>">
                    <a class="fy-card-link" href="<?php echo htmlspecialchars($p['link']); ?>">
                        <div class="fy-gcard-media">
                            <span class="fy-gcard-badge fy-badge-<?php echo htmlspecialchars($p['category']); ?>"><?php echo htmlspecialchars($p['badgeText']); ?></span>
                            <?php if (!empty($p['image'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="fy-gcard-initial"><?php echo htmlspecialchars(mb_substr($p['name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="fy-gcard-body">
                            <h3 class="fy-card-name"><?php echo htmlspecialchars($p['name']); ?></h3>
                            <?php if (!empty($p['desc'])): ?>
                                <p class="fy-card-desc"><?php echo htmlspecialchars($p['desc']); ?></p>
                            <?php endif; ?>
                            <p class="fy-gcard-meta">Malvar, Batangas<?php echo !empty($p['location']) ? ' · ' . htmlspecialchars($p['location']) : ''; ?></p>
                        </div>
                    </a>
                    <div class="fy-card-actions">
                        <button type="button" class="fy-action-btn fy-fav-btn<?php echo $p['favorited'] ? ' is-favorited' : ''; ?>"
                                data-item-type="<?php echo htmlspecialchars($p['itemType']); ?>"
                                data-item-id="<?php echo (int) $p['itemId']; ?>"
                                onclick="fyToggleFavorite(this)">
                            <span class="fy-action-icon"><?php echo $p['favorited'] ? '&#9829;' : '&#9825;'; ?></span> Save
                        </button>
                        <a class="fy-action-btn fy-view-btn" href="<?php echo htmlspecialchars($p['link']); ?>"
                           data-item-type="<?php echo htmlspecialchars($p['itemType']); ?>"
                           data-item-id="<?php echo (int) $p['itemId']; ?>">View</a>
                        <button type="button" class="fy-action-btn" onclick="fyShare(this, '<?php echo htmlspecialchars($p['link'], ENT_QUOTES); ?>')">
                            <span class="fy-action-icon">↗</span> Share
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="fy-section fy-nearby" id="fy-nearby">
        <div class="fy-section-head">
            <h2>Near You</h2>
            <p>Turn on location to see what's closest to where you are right now.</p>
        </div>
        <div id="fy-location-gate" class="fy-location-gate">
            <p>See destinations sorted by distance from you.</p>
            <button id="fy-enable-location" class="btn btn-filled fy-location-btn">ENABLE LOCATION</button>
            <p id="fy-location-status" class="fy-location-status"></p>
        </div>
        <div id="fy-nearby-grid" class="fy-grid fy-nearby-grid" hidden></div>
    </section>
        </div>
    </div>
    </div>
</main>
<div class="fy-detail-overlay" id="fyDetailOverlay">
    <div class="fy-detail-card">
        <button type="button" class="fy-detail-close" id="fyDetailClose" aria-label="Close">&times;</button>
        <div class="fy-detail-media" id="fyDetailMedia">
            <span class="fy-gcard-badge" id="fyDetailBadge"></span>
        </div>
        <div class="fy-detail-body">
            <h2 class="fy-detail-name" id="fyDetailName"></h2>
            <p class="fy-detail-meta" id="fyDetailMeta"></p>
            <p class="fy-detail-desc" id="fyDetailDesc"></p>
            <div class="fy-detail-actions">
                <button type="button" class="fy-action-btn fy-fav-btn" id="fyDetailFavBtn" onclick="fyToggleFavorite(this)">
                    <span class="fy-action-icon">&#9825;</span> Save
                </button>
                <button type="button" class="fy-action-btn" id="fyDetailShareBtn">
                    <span class="fy-action-icon">↗</span> Share
                </button>
                <a class="fy-action-btn fy-detail-fullpage" id="fyDetailFullLink" href="#">Open Full Page →</a>
            </div>
        </div>
    </div>
</div>
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>
<script>
const KULTOURA_PLACES = <?php echo $placesJson; ?>;
</script>
<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/navbar-search.js"></script>
<script src="../assets/js/foryou.js"></script>
</body>
</html>
