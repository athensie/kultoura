<?php
session_start();
include '../config/dbmain.php';
include '../config/analytics.php';
include '../config/ai_recommend.php';
analytics_track($conn, 'foryou');

$siteName = "KULTOURA";
$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

/* ============================================================
   BROWSING HISTORY TRACKER
   ------------------------------------------------------------
   Each of the 7 tourism pages (products.php, restaurants.php,
   nature.php, resort.php, industry.php, fiestas.php, people.php)
   logs itself into $_SESSION['history'] on load — see the top of
   each file. This just reads whatever they've accumulated.

   For quick manual testing you can also simulate a visit by
   opening:  foryou.php?track=nature
============================================================ */
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
        $_SESSION['history'] = array_slice($_SESSION['history'], -30); // keep last 30
    }
    header('Location: foryou.php');
    exit;
}

/* ============================================================
   DESTINATION / CONTENT DATA
   ------------------------------------------------------------
   Pulled live from the same tables every tourism page reads from
   (products, restaurants, destination, fiestas, people). Each
   tourism page logs its own category into $_SESSION['history']
   on load (see the top of nature.php, restaurants.php, etc.) so
   the recommendations below reflect what the visitor has actually
   been browsing, not a hardcoded demo list.
============================================================ */
// Upload subfolder per item type — same mapping used by favorites.php.
$imageSubfolder = [
    'product'       => 'products',
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

// Which items the logged-in user has already favorited, keyed the same
// way the `favorites` table stores them ("item_type-item_id") so every
// heart button below can render its correct starting state.
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

// Which broad filter pill each category belongs to, and which color
// the category badge on each card should use.
$filterGroup = [
    'product' => 'product', 'restaurant' => 'restaurant',
    'nature' => 'destination', 'resort' => 'destination', 'industry' => 'destination',
    'accommodation' => 'destination', 'bank' => 'destination', 'service' => 'destination',
    'fiesta' => 'fiesta', 'person' => 'person',
];

$places = [];

if ($result = $conn->query("SELECT product_id, product_name, category, description, image, location, latitude, longitude, created_at FROM products")) {
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

if ($result = $conn->query("SELECT restaurant_id, restaurant_name, category, description, image, address, latitude, longitude, created_at FROM restaurants")) {
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

if ($result = $conn->query("SELECT fiesta_id, fiesta_name, type, description, image, location, latitude, longitude, created_at FROM fiestas WHERE celebration_date >= CURDATE()")) {
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

// People have no map coordinates — they'll show up in "Because You
// Explored" but are naturally excluded from "Near You" (null lat/lng).
// Their "location" line shows their role/title instead of an address.
if ($result = $conn->query("SELECT person_id, fullname, description, image, title, created_at FROM people")) {
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
    'accommodation' => 'Accommodation',
    'bank'          => 'Banks & Finance',
    'service'       => 'Essential Services',
    'fiesta'        => 'Fiestas & Events',
    'person'        => 'Faces of Malvar',
];

/* ============================================================
   RECOMMENDATION LOGIC — content-based filtering (TF-IDF + cosine
   similarity), the same vector-space technique most "AI-powered"
   recommendation engines use under the hood — fully local, no API
   key or external call needed.
   ------------------------------------------------------------
   1. Every place becomes a TF-IDF vector built from its name,
      category and description.
   2. A "profile vector" for the visitor is the weighted centroid
      of the vectors for every place they opened or favorited —
      real interaction signals, not just which category page loaded:
        - items opened ($_SESSION['itemHistory'])  weight 3x
        - favorites    ($favoritedKeys)             weight 5x
      Each is also recency-weighted so recent activity outweighs
      older activity.
   3. Every other place is scored by cosine similarity to that
      profile vector — how much its actual content (not just its
      category label) resembles what the visitor has shown
      interest in — blended with a category-affinity score built
      from those same signals plus plain page visits (1x).
   4. Already-opened places rank last (via backfill) so the section
      surfaces something new. No history yet falls back to a
      trending mix so the page never looks empty.
============================================================ */
function fy_tokenize(string $text): array
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $stopwords = ['the', 'a', 'an', 'and', 'or', 'of', 'in', 'on', 'at', 'to', 'for',
        'with', 'is', 'are', 'was', 'were', 'it', 'its', 'this', 'that', 'as', 'by', 'from', 'be'];
    $tokens = preg_split('/\s+/', trim($text));
    return array_values(array_filter($tokens, fn($t) => $t !== '' && strlen($t) > 1 && !in_array($t, $stopwords, true)));
}

// Augmented term frequency (dampens long descriptions dominating short
// names) times inverse document frequency (rewards words that make a
// place distinctive rather than generic filler shared by everything).
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
        // Oldest entry ~0.5x weight, newest ~1.5x — recent activity
        // matters more without letting old history drop to zero.
        $recencyFactor = $n > 1 ? 0.5 + ($i / ($n - 1)) : 1.0;
        $scores[$key] = ($scores[$key] ?? 0) + ($weight * $recencyFactor);
    }
    return $scores;
}

// -- Build a content vector for every place in the catalog --
// When a Gemini key is configured (config/gemini.php — see
// config/gemini.example.php), real semantic embeddings are used,
// cached per place in `place_embeddings` so only new/edited listings
// ever cost a live API call. Without a key, or if a place's embedding
// can't be fetched this request, it falls back to the local TF-IDF
// vector below — the page always has something to compare with.
$aiAvailable = ai_configured();
$aiFetchBudget = 15; // cap live Gemini calls per page load so a cold cache can't stall a request

$placesByKey = [];
$documents = [];
$embedTexts = [];
foreach ($places as $p) {
    $placesByKey[$p['itemType'] . '-' . $p['itemId']] = $p;
    $embedTexts[$p['id']] = $p['name'] . '. ' . ($p['badgeText'] ?? '') . '. ' . ucfirst($p['category']) . '. ' . ($p['desc'] ?? '');
    // Name and category repeated so they outweigh incidental words
    // in the free-text description when terms are scored (TF-IDF only).
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

// Embedding vectors (1536-dim) and TF-IDF vectors (term-keyed) are not
// comparable to each other — mixing them in the same profile centroid
// would silently produce meaningless scores. So this only ever runs in
// one mode per request: every place gets a real embedding, or every
// place gets a TF-IDF vector; a place that can't get an embedding this
// request (budget exhausted, API error) gets an empty vector instead
// of a TF-IDF one, so it just contributes zero content-similarity
// rather than corrupting the shared vector space.
$placeVectors = [];
foreach ($places as $p) {
    if ($aiAvailable) {
        // Cache reads are free and don't touch the fetch budget — only
        // a genuine cache miss (new/edited listing) spends one of the
        // capped live API calls below.
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
$hasHistory = count($history) > 0 || count($itemHistory) > 0 || count($favoritedKeys) > 0;

// -- Category affinity (recency-weighted, same three signals) --
$freq = fy_recency_weighted_scores($history, 1.0);
$itemHistoryCats = array_map(fn($h) => $h['type'], $itemHistory);
foreach (fy_recency_weighted_scores($itemHistoryCats, 3.0) as $cat => $score) {
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

// -- Content profile vector: weighted centroid of every item opened
//    or favorited, so what the visitor actually read about (not just
//    which category they clicked) drives the similarity score. --
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

// Items already opened this session — excluded first so the section
// surfaces something new rather than a repeat of what was just viewed.
$viewedItemKeys = [];
foreach ($itemHistory as $h) {
    $viewedItemKeys[$h['type'] . '-' . $h['id']] = true;
}

$recommended = [];
if ($hasHistory) {
    $scored = [];
    foreach ($places as $p) {
        $key = $p['itemType'] . '-' . $p['itemId'];
        if (isset($viewedItemKeys[$key])) continue;
        $contentScore = fy_cosine_similarity($profileVector, $placeVectors[$p['id']]);
        $categoryScore = $maxFreq > 0 ? (($freq[$p['category']] ?? 0) / $maxFreq) : 0;
        // Lean on content similarity once there's a real profile vector
        // to compare against; otherwise (e.g. only page visits logged,
        // no items opened yet) fall back to category affinity alone.
        $score = $profileWeight > 0
            ? (0.65 * $contentScore) + (0.35 * $categoryScore)
            : $categoryScore;
        $scored[] = ['place' => $p, 'score' => $score];
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $recommended = array_column(array_slice($scored, 0, 6), 'place');

    // Catalog too small to fill 6 unseen picks? Backfill with
    // already-viewed places by top category rather than a short section.
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
    // trending fallback: one pick per category
    $used = [];
    foreach ($places as $p) {
        if (!isset($used[$p['category']])) {
            $recommended[] = $p;
            $used[$p['category']] = true;
        }
    }
    $recommended = array_slice($recommended, 0, 6);
}

// ── "Browse by Category" sidebar ──
// Count of how many spots are in each category, for the subtitle under
// each sidebar entry.
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

// Small monochrome line icons (currentColor) for the sidebar — no emoji.
$categoryIcons = [
    'product'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>',
    'restaurant' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg>',
    'nature'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg>',
    'resort'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M3 14c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M3 20c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/></svg>',
    'industry'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V10l5 3v-3l5 3V5l4 3v13"/></svg>',
    'fiesta'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    'person'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>',
];

// The first recommended item with a photo becomes the hero image.
$heroImage = '';
$heroSource = !empty($recommended) ? $recommended : $places;
foreach ($heroSource as $p) {
    if (!empty($p['image'])) {
        $heroImage = $p['image'];
        break;
    }
}

$placesJson = json_encode($places, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>For You · <?php echo $siteName; ?></title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/foryou.css">
</head>
<body>

<header class="navbar">

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../assets/images/salakot.png" alt=""></span>Mabuhay, <?php echo $userName; ?></div>
    <?php else: ?>
        <div class="user-greeting-left" style="color:#C8A96E;letter-spacing:2px;font-size:15px;font-weight:900;"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>KUL<span style="color:#9fb88a">TOURA</span></div>
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
                    <a href="traveldiary.php">Travel Diary</a>
                    <a href="favorites.php">Favorites</a>
                    <a href="mostpopular.php">Most Popular</a>
                    <a href="about.php">About</a>
                </div>
            </div>
        </div>

    </nav>

    <?php if ($isLoggedIn): ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../auth/logout.php" class="sign-in-btn sign-in-btn-icon-only" aria-label="Sign Out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
    <?php else: ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../auth/login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN IN</span></a>
    <?php endif; ?>

    <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

</header>

<main class="fy-page">

    <!-- Hero banner -->
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

        <!-- Browse by Category sidebar -->
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

            <!-- Near You widget -->
            <div class="fy-near-widget">
                <p class="fy-sidebar-label">Near You</p>
                <p class="fy-near-widget-sub">Top spots near your current location</p>
                <div id="fy-near-widget-list" class="fy-near-widget-list"></div>
                <p id="fy-near-widget-status" class="fy-near-widget-status">Enable location to see this.</p>
                <a href="#fy-nearby" class="fy-near-widget-link">View all nearby →</a>
            </div>
        </aside>

        <!-- Content column -->
        <div class="fy-content-col">

    <!-- Recommended section -->
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
                <div class="fy-card" data-group="<?php echo htmlspecialchars($p['group']); ?>" data-created="<?php echo (int) $p['createdAt']; ?>">
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
                        <a class="fy-action-btn" href="<?php echo htmlspecialchars($p['link']); ?>">View</a>
                        <button type="button" class="fy-action-btn" onclick="fyShare(this, '<?php echo htmlspecialchars($p['link'], ENT_QUOTES); ?>')">
                            <span class="fy-action-icon">↗</span> Share
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Near You section -->
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
        <!-- /fy-content-col -->

    </div>
    </div>
    <!-- /fy-page-layout, /fy-layout-wrap -->

</main>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<script>
const KULTOURA_PLACES = <?php echo $placesJson; ?>;
</script>
<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/foryou.js"></script>
</body>
</html>