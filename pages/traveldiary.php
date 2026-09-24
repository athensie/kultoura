<?php
session_start();
include '../config/dbmain.php';
include '../config/analytics.php';
analytics_track($conn, 'traveldiary');

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

// A diary is inherently personal — there's no meaningful guest view,
// same guard pattern as favorites.php.
if (!$isLoggedIn) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* ============================================================
   CATALOG
   ------------------------------------------------------------
   Every product/restaurant/destination/fiesta/person, the same
   dispatch-table aggregation used by favorites.php and foryou.php.
   Powers the Visited Places Checklist and is the lookup source for
   enriching diary_entries rows (which only store item_type+item_id).
============================================================ */
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

function td_image_src(string $category, ?string $rawImage, array $imageSubfolder): string
{
    if (empty($rawImage)) return '';
    return '../assets/uploads/' . ($imageSubfolder[$category] ?? $category) . '/' . basename($rawImage);
}

$categoryLabels = [
    'product'       => 'Product',
    'restaurant'    => 'Restaurant',
    'nature'        => 'Nature',
    'resort'        => 'Resort',
    'industry'      => 'Industry',
    'accommodation' => 'Accommodation',
    'bank'          => 'Bank',
    'service'       => 'Other Service',
    'fiesta'        => 'Fiesta',
    'person'        => 'Person',
];

// Verb used in the timeline ("Visited But First Coffee", "Attended
// Fiesta of Malvar"...), keyed by itemType (not the finer-grained
// category) since it's about the kind of visit, not the exact place type.
$itemTypeVerbs = [
    'product'     => 'Visited',
    'restaurant'  => 'Visited',
    'destination' => 'Explored',
    'fiesta'      => 'Attended',
    'person'      => 'Met',
];

// Small monochrome line icons (currentColor), reused across the stat
// cards, category badges-with-icon, and timeline dots. Same style as
// the ones built for foryou.php's sidebar — no emoji.
$categoryIcons = [
    'product'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>',
    'restaurant'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg>',
    'nature'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg>',
    'resort'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M3 14c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M3 20c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/></svg>',
    'industry'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V10l5 3v-3l5 3V5l4 3v13"/></svg>',
    'accommodation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 12v8"/><path d="M2 12h20"/><path d="M2 8h6a2 2 0 0 1 2 2v2"/><path d="M22 8h-6a2 2 0 0 0-2 2v2"/></svg>',
    'bank'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M2 10h20L12 4z"/><path d="M8 14v4M12 14v4M16 14v4"/></svg>',
    'service'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg>',
    'fiesta'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    'person'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>',
];

// Generic icons for the stat cards / Journey card (not tied to a category).
$statIcons = [
    'places'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>',
    'photos'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l2-2h6l2 2h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="14" r="3.5"/></svg>',
    'days'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    'favorites'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.5-9.5-9C.5 7 2 3.5 5.5 3.5c2 0 3.5 1 4.5 2.5 1-1.5 2.5-2.5 4.5-2.5C18 3.5 19.5 7 19.5 11 17 15.5 12 20 12 20z"/></svg>',
    'categories' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>',
    'flag'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4"/><path d="M5 4h13l-3 4 3 4H5"/></svg>',
    'sun'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>',
    'fire'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4 0 6-3 6-6.5 0-2.5-1.5-4-2.5-5.5.5 2-1 3-2 2 0-2.5-1.5-4-3-6-.5 3-3 4.5-3 8 0 1-1 1.5-2 1-.5 3 2 7 6.5 7z"/></svg>',
    'memories'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l2.6 5.6 6.1.6-4.6 4.1 1.3 6-5.4-3.1-5.4 3.1 1.3-6-4.6-4.1 6.1-.6z"/></svg>',
    'pin'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>',
    'compass'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/></svg>',
    'clip'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12.5V7a4 4 0 0 1 8 0v9a2.5 2.5 0 0 1-5 0V9"/></svg>',
];

// Coordinates power the "are you actually there?" geofence check on the
// Explorer checklist — destination.google_maps stores "lat,lng" as one
// string, the other tables have separate latitude/longitude columns.
function td_parse_latlng_pair(?string $raw): array
{
    if (!$raw) return [null, null];
    $parts = array_map('trim', explode(',', $raw));
    if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) return [null, null];
    return [(float) $parts[0], (float) $parts[1]];
}

$catalog = []; // keyed "itemType-itemId" => item details

if ($result = $conn->query("SELECT product_id, product_name, category, image, latitude, longitude FROM products")) {
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['product_id'];
        $catalog['product-' . $id] = [
            'itemType'  => 'product',
            'itemId'    => $id,
            'name'      => $row['product_name'],
            'category'  => 'product',
            'badgeText' => $row['category'] ?: 'Product',
            'image'     => td_image_src('product', $row['image'] ?? null, $imageSubfolder),
            'link'      => 'tourism/products.php',
            'lat'       => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'lng'       => $row['longitude'] !== null ? (float) $row['longitude'] : null,
        ];
    }
}

if ($result = $conn->query("SELECT restaurant_id, restaurant_name, category, image, latitude, longitude FROM restaurants")) {
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['restaurant_id'];
        $catalog['restaurant-' . $id] = [
            'itemType'  => 'restaurant',
            'itemId'    => $id,
            'name'      => $row['restaurant_name'],
            'category'  => 'restaurant',
            'badgeText' => $row['category'] ?: 'Restaurant',
            'image'     => td_image_src('restaurant', $row['image'] ?? null, $imageSubfolder),
            'link'      => 'tourism/restaurants.php',
            'lat'       => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'lng'       => $row['longitude'] !== null ? (float) $row['longitude'] : null,
        ];
    }
}

$destinationLinks = [
    'nature'        => 'tourism/nature.php',
    'resort'        => 'tourism/resort.php',
    'industry'      => 'tourism/industry.php',
    'accommodation' => 'tourism/accommodation.php',
    'bank'          => 'tourism/banks.php',
    'service'       => 'tourism/services.php',
];
if ($result = $conn->query("SELECT destination_id, destination_name, category, image, google_maps FROM destination WHERE status = 'active'")) {
    while ($row = $result->fetch_assoc()) {
        $cat = $row['category'];
        if (!isset($destinationLinks[$cat])) continue;
        $id = (int) $row['destination_id'];
        [$destLat, $destLng] = td_parse_latlng_pair($row['google_maps'] ?? null);
        $catalog['destination-' . $id] = [
            'itemType'  => 'destination',
            'itemId'    => $id,
            'name'      => $row['destination_name'],
            'category'  => $cat,
            'badgeText' => ucfirst($cat),
            'image'     => td_image_src($cat, $row['image'] ?? null, $imageSubfolder),
            'link'      => $destinationLinks[$cat],
            'lat'       => $destLat,
            'lng'       => $destLng,
        ];
    }
}

if ($result = $conn->query("SELECT fiesta_id, fiesta_name, type, image, latitude, longitude FROM fiestas")) {
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['fiesta_id'];
        $catalog['fiesta-' . $id] = [
            'itemType'  => 'fiesta',
            'itemId'    => $id,
            'name'      => $row['fiesta_name'],
            'category'  => 'fiesta',
            'badgeText' => $row['type'] ?: 'Fiesta',
            'image'     => td_image_src('fiesta', $row['image'] ?? null, $imageSubfolder),
            'link'      => 'tourism/fiestas.php',
            'lat'       => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'lng'       => $row['longitude'] !== null ? (float) $row['longitude'] : null,
        ];
    }
}

if ($result = $conn->query("SELECT person_id, fullname, title, image FROM people")) {
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['person_id'];
        $catalog['person-' . $id] = [
            'itemType'  => 'person',
            'itemId'    => $id,
            'name'      => $row['fullname'],
            'category'  => 'person',
            'badgeText' => $row['title'] ?: 'Person',
            'image'     => td_image_src('person', $row['image'] ?? null, $imageSubfolder),
            'link'      => 'tourism/people.php',
            // People aren't geotagged — the modal always falls back to
            // the manual "are you sure?" confirmation for these.
            'lat'       => null,
            'lng'       => null,
        ];
    }
}

/* ============================================================
   THIS USER'S DIARY ENTRIES + PHOTOS
============================================================ */
$entries = [];
if ($stmt = $conn->prepare(
    "SELECT entry_id, item_type, item_id, visited_date, visited_time, note, created_at
     FROM diary_entries WHERE user_id = ? ORDER BY visited_date DESC, entry_id DESC"
)) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$entryIds = array_column($entries, 'entry_id');
$photosByEntry = []; // entry_id => [photo, photo, ...]
$allPhotos = [];
if (!empty($entryIds)) {
    $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
    $stmt = $conn->prepare("SELECT photo_id, entry_id, image, caption, created_at FROM diary_photos WHERE entry_id IN ($placeholders) ORDER BY photo_id ASC");
    $stmt->bind_param(str_repeat('i', count($entryIds)), ...$entryIds);
    $stmt->execute();
    $allPhotos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($allPhotos as $p) {
        $photosByEntry[(int) $p['entry_id']][] = $p;
    }
}

// Visit counts + latest note/entry per catalog item, for the checklist.
$visitsByItem = []; // "type-id" => [entries...]
foreach ($entries as $e) {
    $key = $e['item_type'] . '-' . $e['item_id'];
    $visitsByItem[$key][] = $e;
}

/* ============================================================
   TRAVEL STATISTICS
============================================================ */
$distinctVisitedKeys = array_keys($visitsByItem);
$totalPlacesVisited  = count($distinctVisitedKeys);

$countByType = ['product' => 0, 'restaurant' => 0, 'destination' => 0, 'fiesta' => 0, 'person' => 0];
$countByCategory = [];
foreach ($distinctVisitedKeys as $key) {
    $item = $catalog[$key] ?? null;
    if (!$item) continue;
    $countByType[$item['itemType']] = ($countByType[$item['itemType']] ?? 0) + 1;
    $countByCategory[$item['category']] = ($countByCategory[$item['category']] ?? 0) + 1;
}

$favoriteCategory = null;
if (!empty($countByCategory)) {
    arsort($countByCategory);
    $favoriteCategoryKey = array_key_first($countByCategory);
    $favoriteCategory = $categoryLabels[$favoriteCategoryKey] ?? $favoriteCategoryKey;
}

$mostVisited = null;
$mostVisitedCount = 0;
foreach ($visitsByItem as $key => $visits) {
    if (count($visits) > $mostVisitedCount) {
        $mostVisitedCount = count($visits);
        $mostVisited = $catalog[$key] ?? null;
    }
}

$distinctDates  = array_unique(array_column($entries, 'visited_date'));
$daysTraveled   = count($distinctDates);
$totalPhotos    = count($allPhotos);
$totalMemories  = count($entries); // every logged visit, including repeats — not just distinct places
$hasAnyEntries  = count($entries) > 0;

// How many of the 5 catalog categories have at least one logged visit.
$categoriesCompleted = count(array_filter($countByType, fn($c) => $c > 0));
$totalCategories      = count($countByType);

// The "Badges Collected" stat, spelled out: one badge per catalog
// category, unlocked the moment the user logs their first visit in it.
$badgeDefs = [
    ['type' => 'product',     'label' => 'Products',     'color' => '#a9843f', 'icon' => $categoryIcons['product']],
    ['type' => 'restaurant',  'label' => 'Restaurants',  'color' => '#8b2e1a', 'icon' => $categoryIcons['restaurant']],
    ['type' => 'destination', 'label' => 'Destinations', 'color' => '#5f7d4c', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg>'],
    ['type' => 'fiesta',      'label' => 'Fiestas',      'color' => '#a8433a', 'icon' => $categoryIcons['fiesta']],
    ['type' => 'person',      'label' => 'People',       'color' => '#4a5a78', 'icon' => $categoryIcons['person']],
];

// Real count from the existing favorites table — same one every other
// page's heart button writes to.
$favoritesCount = 0;
if ($favStmt = $conn->prepare("SELECT COUNT(*) AS c FROM favorites WHERE user_id = ?")) {
    $favStmt->bind_param('i', $userId);
    $favStmt->execute();
    $favoritesCount = (int) $favStmt->get_result()->fetch_assoc()['c'];
    $favStmt->close();
}

$currentYear = date('Y');

/* ============================================================
   TRAVEL WRAPPED (extra data only this feature needs)
   ------------------------------------------------------------
   Wrapped is generated for a chosen look-back window — Weekly,
   Monthly, or Yearly — picked via ?wrapped=weekly|monthly|yearly.
   Everything below is scoped to that date range and kept separate
   from the Journey card's all-time stats above.
============================================================ */
$wrappedPeriod = $_GET['wrapped'] ?? 'yearly';
if (!in_array($wrappedPeriod, ['weekly', 'monthly', 'yearly'], true)) {
    $wrappedPeriod = 'yearly';
}
$wrappedRequested = isset($_GET['wrapped']);

$now = new DateTime();
switch ($wrappedPeriod) {
    case 'weekly':
        $wrappedRangeStart = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
        $wrappedRangeEnd   = (clone $wrappedRangeStart)->modify('+7 days');
        $wrappedLabel      = 'This Week';
        break;
    case 'monthly':
        $wrappedRangeStart = new DateTime($now->format('Y-m-01'));
        $wrappedRangeEnd   = (clone $wrappedRangeStart)->modify('+1 month');
        $wrappedLabel      = $now->format('F Y');
        break;
    default: // yearly
        $wrappedRangeStart = new DateTime($now->format('Y-01-01'));
        $wrappedRangeEnd   = (clone $wrappedRangeStart)->modify('+1 year');
        $wrappedLabel      = $now->format('Y');
        break;
}
$wrappedRangeStartStr = $wrappedRangeStart->format('Y-m-d');
$wrappedRangeEndStr   = $wrappedRangeEnd->format('Y-m-d');

// $entries is already sorted visited_date DESC, entry_id DESC — filtering
// preserves that order, so index 0 stays "most recent" within the window.
$wrappedEntries = array_values(array_filter($entries, function ($e) use ($wrappedRangeStartStr, $wrappedRangeEndStr) {
    return $e['visited_date'] >= $wrappedRangeStartStr && $e['visited_date'] < $wrappedRangeEndStr;
}));

function td_wrapped_stats(array $periodEntries, array $catalog, array $photosByEntry, array $categoryLabels, array $itemTypeVerbs): array
{
    $visitsByItem = [];
    foreach ($periodEntries as $e) {
        $visitsByItem[$e['item_type'] . '-' . $e['item_id']][] = $e;
    }
    $distinctKeys = array_keys($visitsByItem);

    $countByType = [];
    $countByCategory = [];
    foreach ($distinctKeys as $key) {
        $item = $catalog[$key] ?? null;
        if (!$item) continue;
        $countByType[$item['itemType']] = ($countByType[$item['itemType']] ?? 0) + 1;
        $countByCategory[$item['category']] = ($countByCategory[$item['category']] ?? 0) + 1;
    }

    $favoriteCategoryKey   = null;
    $favoriteCategory      = null;
    $favoriteCategoryImage = '';
    if (!empty($countByCategory)) {
        arsort($countByCategory);
        $favoriteCategoryKey = array_key_first($countByCategory);
        $favoriteCategory    = $categoryLabels[$favoriteCategoryKey] ?? $favoriteCategoryKey;
        foreach ($catalog as $item) {
            if ($item['category'] === $favoriteCategoryKey && !empty($item['image'])) {
                $favoriteCategoryImage = $item['image'];
                break;
            }
        }
    }

    $mostVisited = null;
    $mostVisitedCount = 0;
    foreach ($visitsByItem as $key => $visits) {
        if (count($visits) > $mostVisitedCount) {
            $mostVisitedCount = count($visits);
            $mostVisited = $catalog[$key] ?? null;
        }
    }

    $distinctDates = array_unique(array_column($periodEntries, 'visited_date'));

    $totalPhotos = 0;
    foreach ($periodEntries as $e) {
        $totalPhotos += count($photosByEntry[(int) $e['entry_id']] ?? []);
    }

    $latestAdventure   = null;
    $firstPlaceVisited = null;
    if (!empty($periodEntries)) {
        $latestKey = $periodEntries[0]['item_type'] . '-' . $periodEntries[0]['item_id'];
        $firstKey  = $periodEntries[count($periodEntries) - 1]['item_type'] . '-' . $periodEntries[count($periodEntries) - 1]['item_id'];
        $latestAdventure   = $catalog[$latestKey] ?? null;
        $firstPlaceVisited = $catalog[$firstKey] ?? null;
    }

    // Longest streak: consecutive-day run across distinct visited_date values.
    $sortedDates = array_values($distinctDates);
    sort($sortedDates);
    $longestStreak = $sortedDates ? 1 : 0;
    $currentStreak = $sortedDates ? 1 : 0;
    for ($i = 1; $i < count($sortedDates); $i++) {
        $diff = (int) (new DateTime($sortedDates[$i - 1]))->diff(new DateTime($sortedDates[$i]))->format('%a');
        if ($diff === 1) {
            $currentStreak++;
            $longestStreak = max($longestStreak, $currentStreak);
        } else {
            $currentStreak = 1;
        }
    }

    $timeline = [];
    foreach ($periodEntries as $e) {
        $key  = $e['item_type'] . '-' . $e['item_id'];
        $item = $catalog[$key] ?? null;
        if (!$item) continue;
        $timeline[] = [
            'name'        => $item['name'],
            'category'    => $item['category'],
            'verb'        => $itemTypeVerbs[$item['itemType']] ?? 'Visited',
            'visitedDate' => $e['visited_date'],
            'photos'      => $photosByEntry[(int) $e['entry_id']] ?? [],
        ];
    }

    $photoMemories = [];
    foreach ($periodEntries as $e) {
        $key  = $e['item_type'] . '-' . $e['item_id'];
        $item = $catalog[$key] ?? null;
        if (!$item) continue;
        foreach ($photosByEntry[(int) $e['entry_id']] ?? [] as $p) {
            $photoMemories[] = [
                'image'       => '../assets/uploads/diary/' . basename($p['image']),
                'visitedDate' => $e['visited_date'],
            ];
        }
    }
    usort($photoMemories, fn($a, $b) => strcmp($b['visitedDate'], $a['visitedDate']));

    return [
        'totalPlaces'           => count($distinctKeys),
        'daysTraveled'          => count($distinctDates),
        'totalPhotos'           => $totalPhotos,
        'categoriesCompleted'   => count(array_filter($countByType, fn($c) => $c > 0)),
        'favoriteCategory'      => $favoriteCategory,
        'favoriteCategoryKey'   => $favoriteCategoryKey,
        'favoriteCategoryImage' => $favoriteCategoryImage,
        'mostVisited'           => $mostVisited,
        'latestAdventure'       => $latestAdventure,
        'firstPlaceVisited'     => $firstPlaceVisited,
        'longestStreak'         => $longestStreak,
        'timeline'              => $timeline,
        'photoMemories'         => $photoMemories,
    ];
}

$tw = td_wrapped_stats($wrappedEntries, $catalog, $photosByEntry, $categoryLabels, $itemTypeVerbs);

// Favorites marked within the same window (favorites.created_at).
$twFavoritesCount = 0;
if ($twFavStmt = $conn->prepare("SELECT COUNT(*) AS c FROM favorites WHERE user_id = ? AND created_at >= ? AND created_at < ?")) {
    $twFavStmt->bind_param('iss', $userId, $wrappedRangeStartStr, $wrappedRangeEndStr);
    $twFavStmt->execute();
    $twFavoritesCount = (int) $twFavStmt->get_result()->fetch_assoc()['c'];
    $twFavStmt->close();
}

// Enough logged visits in the chosen window to make a Wrapped worth generating.
$canGenerateWrapped = count($wrappedEntries) > 0;

/* ============================================================
   TIMELINE + PHOTO MEMORIES (enrich with catalog details)
============================================================ */
$timeline = [];
foreach ($entries as $e) {
    $key  = $e['item_type'] . '-' . $e['item_id'];
    $item = $catalog[$key] ?? null;
    if (!$item) continue; // source item was deleted since — skip
    $timeline[] = [
        'entryId'      => (int) $e['entry_id'],
        'name'         => $item['name'],
        'badgeText'    => $item['badgeText'],
        'category'     => $item['category'],
        'itemType'     => $item['itemType'],
        'verb'         => $itemTypeVerbs[$item['itemType']] ?? 'Visited',
        'image'        => $item['image'],
        'link'         => $item['link'],
        'visitedDate'  => $e['visited_date'],
        'visitedTime'  => $e['visited_time'],
        'note'         => $e['note'],
        'photos'       => $photosByEntry[(int) $e['entry_id']] ?? [],
    ];
}

$photoMemories = [];
foreach ($entries as $e) {
    $key  = $e['item_type'] . '-' . $e['item_id'];
    $item = $catalog[$key] ?? null;
    if (!$item) continue;
    foreach ($photosByEntry[(int) $e['entry_id']] ?? [] as $p) {
        $photoMemories[] = [
            'photoId'     => (int) $p['photo_id'],
            'image'       => '../assets/uploads/diary/' . basename($p['image']),
            'caption'     => $p['caption'] ?: $e['note'],
            'placeName'   => $item['name'],
            'category'    => $item['category'],
            'visitedDate' => $e['visited_date'],
        ];
    }
}
// Newest photos first.
usort($photoMemories, fn($a, $b) => strcmp($b['visitedDate'], $a['visitedDate']));

// Checklist ("Visited Places Explorer"): every catalog item, with this
// user's visit state attached.
$checklist = [];
foreach ($catalog as $key => $item) {
    $visits = $visitsByItem[$key] ?? [];
    $latestNote = '';
    foreach ($visits as $v) {
        if ($v['note']) { $latestNote = $v['note']; break; } // entries already sorted DESC
    }
    $checklist[] = $item + [
        'visited'        => count($visits) > 0,
        'visitCount'     => count($visits),
        'note'           => $latestNote,
        'lastVisitedDate'=> $visits[0]['visited_date'] ?? null,
    ];
}
// Visited places first (most recent visit first), then unvisited alphabetically.
usort($checklist, function ($a, $b) {
    if ($a['visited'] !== $b['visited']) return $a['visited'] ? -1 : 1;
    if ($a['visited']) return strcmp($b['lastVisitedDate'], $a['lastVisitedDate']);
    return strcasecmp($a['name'], $b['name']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Diary – KULTOURA</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/traveldiary.css">
</head>
<body>

<!-- ── Navbar (shared) ── -->
<header class="navbar navbar-solid">

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../assets/images/salakot.png" alt=""></span>Mabuhay, <?php echo $userName; ?></div>
    <?php else: ?>
        <div class="user-greeting-left" style="letter-spacing:2px;font-size:15px;font-weight:900;">
            <span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>
            <a href="../index.php" style="text-decoration:none;color:#C8A96E;">KUL<span style="color:#9fb88a">TOURA</span></a>
        </div>
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

<main class="td-page">

    <!-- ── Hero ── -->
    <section class="td-hero">
        <div class="td-hero-decor" aria-hidden="true">
            <svg class="td-hero-mountains" viewBox="0 0 500 160" preserveAspectRatio="none">
                <path d="M0 160 L70 85 L130 130 L200 55 L270 120 L340 70 L410 125 L500 90 L500 160 Z" fill="#e9e0cd"/>
                <path d="M0 160 L110 118 L180 150 L250 100 L320 145 L400 112 L500 160 Z" fill="#f0e8d6"/>
            </svg>
            <svg class="td-hero-path" viewBox="0 0 500 160" preserveAspectRatio="none">
                <path d="M30 140 Q140 70 210 105 T420 40" fill="none" stroke="#c8a96e" stroke-width="2.5" stroke-dasharray="1 10" stroke-linecap="round"/>
            </svg>
            <span class="td-hero-pin"><?php echo $statIcons['pin']; ?></span>
            <span class="td-hero-leaf td-hero-leaf-1"><?php echo $statIcons['memories']; ?></span>
        </div>

        <div class="td-hero-text">
            <p class="t-eyebrow td-hero-eyebrow"><?php echo $statIcons['pin']; ?> MALVAR, BATANGAS</p>
            <h1 class="td-hero-title">Travel <span class="td-hero-accent">Diary</span></h1>
            <p class="td-hero-tagline">Every journey you've etched tells a story.</p>
            <p class="td-hero-sub">Your personal log of everywhere you've explored in Malvar.</p>
            <button type="button" class="td-wrapped-btn td-wrapped-trigger">+ Generate My Travel Wrapped</button>
        </div>

        <div class="td-journey-card">
            <div class="td-journey-head">
                <span class="td-journey-title">Your mini journey</span>
                <a href="#td-explorer" class="td-journey-viewall">View All</a>
            </div>
            <div class="td-journey-stats">
                <div class="td-journey-stat">
                    <span class="td-journey-icon td-journey-icon-a"><?php echo $statIcons['places']; ?></span>
                    <div>
                        <span class="td-journey-num"><?php echo $totalPlacesVisited; ?></span>
                        <span class="td-journey-label">Places Visited</span>
                    </div>
                </div>
                <div class="td-journey-stat">
                    <span class="td-journey-icon td-journey-icon-b"><?php echo $statIcons['days']; ?></span>
                    <div>
                        <span class="td-journey-num"><?php echo $daysTraveled; ?></span>
                        <span class="td-journey-label">Days Traveled</span>
                    </div>
                </div>
                <?php if ($favoriteCategory): ?>
                <div class="td-journey-stat">
                    <span class="td-journey-icon td-journey-icon-c"><?php echo $categoryIcons[$favoriteCategoryKey] ?? $statIcons['categories']; ?></span>
                    <div>
                        <span class="td-journey-label td-journey-label-top">Most Visited Category</span>
                        <span class="td-journey-text"><?php echo htmlspecialchars($favoriteCategory); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($mostVisited): ?>
                <div class="td-journey-stat">
                    <span class="td-journey-icon td-journey-icon-d"><?php echo $statIcons['favorites']; ?></span>
                    <div>
                        <span class="td-journey-label td-journey-label-top">Favorite Place</span>
                        <span class="td-journey-text"><?php echo htmlspecialchars($mostVisited['name']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <a href="#td-explorer" class="td-journey-link">Start a Memory →</a>
        </div>

        <?php if (!empty($photoMemories)): ?>
        <div class="td-hero-polaroid">
            <span class="td-hero-clip"><?php echo $statIcons['clip']; ?></span>
            <img src="<?php echo htmlspecialchars($photoMemories[0]['image']); ?>" alt="">
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Travel Statistics ── -->
    <section class="td-section" id="td-stats">
        <div class="td-stats-grid">
            <div class="td-stat-card">
                <span class="td-stat-icon td-stat-icon-green"><?php echo $statIcons['places']; ?></span>
                <span class="td-stat-num"><?php echo $totalPlacesVisited; ?></span>
                <span class="td-stat-label">Places Visited</span>
            </div>
            <div class="td-stat-card">
                <span class="td-stat-icon td-stat-icon-pink"><?php echo $statIcons['photos']; ?></span>
                <span class="td-stat-num"><?php echo $totalPhotos; ?></span>
                <span class="td-stat-label">Photos Uploaded</span>
            </div>
            <div class="td-stat-card">
                <span class="td-stat-icon td-stat-icon-orange"><?php echo $statIcons['days']; ?></span>
                <span class="td-stat-num"><?php echo $daysTraveled; ?></span>
                <span class="td-stat-label">Days Traveled</span>
            </div>
            <div class="td-stat-card">
                <span class="td-stat-icon td-stat-icon-purple"><?php echo $statIcons['memories']; ?></span>
                <span class="td-stat-num"><?php echo $totalMemories; ?></span>
                <span class="td-stat-label">Memories</span>
            </div>
            <div class="td-stat-card">
                <span class="td-stat-icon td-stat-icon-blue"><?php echo $statIcons['categories']; ?></span>
                <span class="td-stat-num"><?php echo $categoriesCompleted; ?>/<?php echo $totalCategories; ?></span>
                <span class="td-stat-label">Badges Collected</span>
            </div>
        </div>

        <div class="td-badges">
            <p class="td-badges-hint">One badge per category — logged your first visit there and it's yours.</p>
            <div class="td-badges-row">
                <?php foreach ($badgeDefs as $badge): ?>
                    <?php $unlocked = ($countByType[$badge['type']] ?? 0) > 0; ?>
                    <div class="td-badge-chip<?php echo $unlocked ? ' is-unlocked' : ''; ?>" style="<?php echo $unlocked ? '--td-badge-color:' . htmlspecialchars($badge['color']) : ''; ?>">
                        <span class="td-badge-chip-icon"><?php echo $badge['icon']; ?></span>
                        <span class="td-badge-chip-label"><?php echo htmlspecialchars($badge['label']); ?></span>
                        <?php if (!$unlocked): ?>
                            <span class="td-badge-chip-lock" aria-label="Locked"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ── Recent Memories + Travel Timeline  |  Visited Places Explorer + Photo Memories ── -->
    <section class="td-section">
        <div class="td-twocol">

            <div class="td-col">
                <div class="td-col-stack">
                    <div class="td-section-head-row">
                        <h2>Recent Memories</h2>
                        <a href="#td-gallery" class="td-view-link">View All Visits →</a>
                    </div>

                    <?php if (empty($photoMemories)): ?>
                        <div class="td-empty">
                            <h3>No memories yet</h3>
                            <p>Upload a photo from the Explorer to start this feed.</p>
                        </div>
                    <?php else: ?>
                    <div class="td-memories-scroll">
                        <?php foreach (array_slice($photoMemories, 0, 8) as $m): ?>
                            <div class="td-memory-card">
                                <div class="td-memory-photo">
                                    <span class="td-memory-icon"><?php echo $categoryIcons[$m['category']] ?? ''; ?></span>
                                    <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="" loading="lazy">
                                </div>
                                <h3 class="td-memory-name"><?php echo htmlspecialchars($m['placeName']); ?></h3>
                                <p class="td-memory-date"><?php echo htmlspecialchars(date('F j, Y', strtotime($m['visitedDate']))); ?></p>
                                <?php if (!empty($m['caption'])): ?>
                                    <p class="td-memory-caption"><?php echo htmlspecialchars($m['caption']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="td-col-stack">
                    <div class="td-section-head-row">
                        <h2>Travel Timeline</h2>
                    </div>
                    <p class="td-col-sub">Every visit you've logged, most recent first.</p>

                    <?php if (empty($timeline)): ?>
                        <div class="td-empty">
                            <h3>Your timeline is empty</h3>
                            <p>Visits you log from the Explorer will show up here.</p>
                        </div>
                    <?php else: ?>
                    <div class="td-timeline">
                        <?php foreach ($timeline as $i => $t): ?>
                            <div class="td-timeline-item<?php echo $i >= 4 ? ' td-hidden-extra' : ''; ?>">
                                <div class="td-timeline-dot td-badge-<?php echo htmlspecialchars($t['category']); ?>"><?php echo $categoryIcons[$t['category']] ?? ''; ?></div>
                                <div class="td-timeline-card">
                                    <p class="td-timeline-name"><?php echo htmlspecialchars($t['verb']); ?> <strong><?php echo htmlspecialchars($t['name']); ?></strong></p>
                                    <p class="td-timeline-meta">
                                        <?php echo htmlspecialchars(date('F j, Y', strtotime($t['visitedDate']))); ?>
                                        <?php if (!empty($t['visitedTime'])): ?> · <?php echo htmlspecialchars(date('g:i A', strtotime($t['visitedTime']))); ?><?php endif; ?>
                                    </p>
                                    <?php if (!empty($t['note'])): ?>
                                        <p class="td-timeline-note"><?php echo htmlspecialchars($t['note']); ?></p>
                                    <?php endif; ?>
                                    <button type="button" class="td-timeline-delete" data-entry-id="<?php echo (int) $t['entryId']; ?>">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($timeline) > 4): ?>
                        <button type="button" class="td-view-link td-expand-btn" id="tdExpandTimeline">View Full Timeline (<?php echo count($timeline); ?>) →</button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="td-col">
                <div class="td-col-stack" id="td-explorer">
                    <div class="td-section-head-row">
                        <h2>Visited Places Explorer</h2>
                    </div>
                    <div class="td-toolbar">
                        <input type="text" id="tdSearch" class="td-search" placeholder="Search places…">
                    </div>
                    <div class="td-filter-bar" id="tdFilterBar">
                        <button type="button" class="td-filter-pill is-active" data-type="all">All</button>
                        <button type="button" class="td-filter-pill" data-type="product">Products</button>
                        <button type="button" class="td-filter-pill" data-type="restaurant">Restaurants</button>
                        <button type="button" class="td-filter-pill" data-type="destination">Destinations</button>
                        <button type="button" class="td-filter-pill" data-type="fiesta">Fiestas</button>
                        <button type="button" class="td-filter-pill" data-type="person">People</button>
                    </div>

                    <div class="td-checklist" id="tdChecklist">
                        <?php foreach ($checklist as $i => $c): ?>
                            <div class="td-check-row<?php echo $i >= 6 ? ' td-hidden-extra' : ''; ?>" data-type="<?php echo htmlspecialchars($c['itemType']); ?>" data-name="<?php echo htmlspecialchars(mb_strtolower($c['name'])); ?>" data-place="<?php echo htmlspecialchars($c['name']); ?>" data-lat="<?php echo $c['lat'] !== null ? $c['lat'] : ''; ?>" data-lng="<?php echo $c['lng'] !== null ? $c['lng'] : ''; ?>">
                                <div class="td-check-thumb">
                                    <?php if (!empty($c['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($c['image']); ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <span class="td-check-initial"><?php echo htmlspecialchars(mb_substr($c['name'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="td-check-body">
                                    <div class="td-check-top">
                                        <span class="td-check-name"><?php echo htmlspecialchars($c['name']); ?></span>
                                        <span class="td-badge td-badge-<?php echo htmlspecialchars($c['category']); ?>"><?php echo htmlspecialchars($c['badgeText']); ?></span>
                                    </div>
                                    <p class="td-check-status">
                                        <?php if ($c['visited']): ?>
                                            Visited on <?php echo htmlspecialchars(date('F j, Y', strtotime($c['lastVisitedDate']))); ?><?php echo $c['visitCount'] > 1 ? ' · ' . $c['visitCount'] . '×' : ''; ?>
                                        <?php else: ?>
                                            Not visited yet
                                        <?php endif; ?>
                                    </p>

                                    <?php if ($c['visited']): ?>
                                        <button type="button" class="td-log-again-btn td-log-again-btn-visible" data-item-type="<?php echo htmlspecialchars($c['itemType']); ?>" data-item-id="<?php echo (int) $c['itemId']; ?>">+ Log Another Visit</button>
                                    <?php endif; ?>

                                    <div class="td-check-expand" hidden>
                                        <textarea class="td-note-input" placeholder="Add a note…" data-item-type="<?php echo htmlspecialchars($c['itemType']); ?>" data-item-id="<?php echo (int) $c['itemId']; ?>"><?php echo htmlspecialchars($c['note']); ?></textarea>
                                        <div class="td-check-actions">
                                            <button type="button" class="td-mini-btn td-upload-btn" data-item-type="<?php echo htmlspecialchars($c['itemType']); ?>" data-item-id="<?php echo (int) $c['itemId']; ?>">Upload Photo</button>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" class="td-check-toggle-note" aria-label="Add note or photo">✎</button>

                                <label class="td-check-box">
                                    <input type="checkbox" class="td-checkbox" data-item-type="<?php echo htmlspecialchars($c['itemType']); ?>" data-item-id="<?php echo (int) $c['itemId']; ?>" <?php echo $c['visited'] ? 'checked' : ''; ?>>
                                    <span class="td-check-mark"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="td-no-results" id="tdNoResults" hidden>No places match your search.</p>
                    <?php if (count($checklist) > 6): ?>
                        <button type="button" class="td-view-link td-expand-btn" id="tdExpandChecklist">View All Places (<?php echo count($checklist); ?>) →</button>
                    <?php endif; ?>
                </div>

                <div class="td-col-stack" id="td-gallery">
                    <div class="td-section-head-row">
                        <h2>Photo Memories</h2>
                    </div>
                    <p class="td-col-sub">Every photo you've uploaded, in one gallery.</p>

                    <?php if (empty($photoMemories)): ?>
                        <div class="td-empty">
                            <h3>No photos yet</h3>
                            <p>Upload a photo from the Explorer to start your gallery.</p>
                        </div>
                    <?php else: ?>
                    <div class="td-gallery">
                        <?php foreach ($photoMemories as $i => $m): ?>
                            <button type="button" class="td-gallery-tile<?php echo $i >= 6 ? ' td-hidden-extra' : ''; ?>" data-image="<?php echo htmlspecialchars($m['image']); ?>" data-caption="<?php echo htmlspecialchars($m['placeName'] . ' · ' . date('F j, Y', strtotime($m['visitedDate']))); ?>">
                                <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="" loading="lazy">
                                <span class="td-gallery-info">
                                    <span class="td-gallery-name"><?php echo htmlspecialchars($m['placeName']); ?></span>
                                    <span class="td-gallery-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($m['visitedDate']))); ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($photoMemories) > 6): ?>
                        <button type="button" class="td-view-link td-expand-btn" id="tdExpandGallery">View Gallery (<?php echo count($photoMemories); ?>) →</button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>

    <!-- ── Wrapped CTA banner ── -->
    <section class="td-section td-section-flush">
        <div class="td-cta-banner">
            <span class="td-cta-leaf td-cta-leaf-1" aria-hidden="true"><?php echo $statIcons['memories']; ?></span>
            <span class="td-cta-leaf td-cta-leaf-2" aria-hidden="true"><?php echo $categoryIcons['nature']; ?></span>
            <span class="td-cta-quote-mark" aria-hidden="true">&ldquo;</span>
            <blockquote class="td-cta-quote">The best stories are the ones we collect<br>along the way. <span class="td-cta-heart">&#9825;</span></blockquote>
            <button type="button" class="td-wrapped-btn td-wrapped-btn-light td-wrapped-trigger">+ Generate My Travel Wrapped</button>
        </div>
    </section>

</main>

<!-- ── Wrapped period picker ── -->
<div class="tw-period-overlay" id="twPeriodOverlay">
    <div class="tw-period-card">
        <button type="button" class="tw-period-close" id="twPeriodClose" aria-label="Close">&times;</button>
        <h3 class="tw-period-title">Choose Your Wrapped</h3>
        <p class="tw-period-sub">Pick a time range to look back on.</p>
        <div class="tw-period-options">
            <a href="?wrapped=weekly" class="tw-period-btn tw-period-weekly<?php echo $wrappedRequested && $wrappedPeriod === 'weekly' ? ' is-active' : ''; ?>">
                <span class="tw-period-btn-dot"></span>
                <span class="tw-period-btn-copy">
                    <span class="tw-period-btn-title">Weekly</span>
                    <span class="tw-period-btn-sub">This week's adventures</span>
                </span>
            </a>
            <a href="?wrapped=monthly" class="tw-period-btn tw-period-monthly<?php echo $wrappedRequested && $wrappedPeriod === 'monthly' ? ' is-active' : ''; ?>">
                <span class="tw-period-btn-dot"></span>
                <span class="tw-period-btn-copy">
                    <span class="tw-period-btn-title">Monthly</span>
                    <span class="tw-period-btn-sub">This month's adventures</span>
                </span>
            </a>
            <a href="?wrapped=yearly" class="tw-period-btn tw-period-yearly<?php echo $wrappedRequested && $wrappedPeriod === 'yearly' ? ' is-active' : ''; ?>">
                <span class="tw-period-btn-dot"></span>
                <span class="tw-period-btn-copy">
                    <span class="tw-period-btn-title">Yearly</span>
                    <span class="tw-period-btn-sub">Your whole <?php echo $currentYear; ?> journey</span>
                </span>
            </a>
        </div>
    </div>
</div>

<!-- ── Travel Wrapped ── -->
<div class="tw-overlay" id="twOverlay">
    <button type="button" class="tw-close" id="twClose" aria-label="Close">&times;</button>

    <?php if ($canGenerateWrapped): ?>
    <div class="tw-panel tw-theme-<?php echo $wrappedPeriod; ?>">
    <span class="tw-badge" id="twBadge">1/5</span>

    <button type="button" class="tw-nav tw-nav-prev" id="twPrev" aria-label="Previous slide">&#8249;</button>
    <button type="button" class="tw-nav tw-nav-next" id="twNext" aria-label="Next slide">&#8250;</button>

    <div class="tw-share-bar">
        <button type="button" class="tw-share-btn" id="twDownloadBtn" aria-label="Save this slide as a photo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg>
            <span>Save Photo</span>
        </button>
        <button type="button" class="tw-share-btn" id="twShareBtn" aria-label="Share this slide">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.8l7.6-4.6M8.2 13.2l7.6 4.6"/></svg>
            <span>Share</span>
        </button>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" rel="noopener" class="tw-share-btn tw-share-fb" aria-label="Share to Facebook">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8.4h2.8l.4-3.3h-3.2V8.1c0-.9.3-1.6 1.7-1.6h1.7V3.5C16.6 3.4 15.5 3.3 14.3 3.3c-2.6 0-4.3 1.6-4.3 4.4v2.6H7.2v3.3h2.8V22h3.5z"/></svg>
            <span>Facebook</span>
        </a>
    </div>

    <div class="tw-slides" id="twSlides">

        <!-- Slide 1: Title -->
        <div class="tw-slide tw-slide-1">
            <div class="tw-slide-inner">
                <div class="tw-brand">KULTOURA<span>MALVAR, BATANGAS</span></div>
                <h1 class="tw-title-1">KULTOURA<br><span class="tw-accent-green">Wrapped</span><br><span class="tw-accent-gold"><?php echo htmlspecialchars($wrappedLabel); ?></span></h1>
                <p class="tw-sub">Your journey. Your story.<br>Malvar is better with you.</p>
                <?php if (!empty($tw['photoMemories'])): ?>
                <div class="tw-collage">
                    <?php foreach (array_slice($tw['photoMemories'], 0, 4) as $i => $m): ?>
                        <div class="tw-collage-photo tw-tilt-<?php echo $i; ?>"><img src="<?php echo htmlspecialchars($m['image']); ?>" alt=""></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="tw-swipe-hint">Swipe to see your adventures! →</p>
            </div>
        </div>

        <!-- Slide 2: By the Numbers -->
        <div class="tw-slide tw-slide-2">
            <div class="tw-slide-inner">
                <h2 class="tw-title-2">By the Numbers</h2>
                <p class="tw-sub-2">A look back at your amazing journey around Malvar.</p>
                <div class="tw-stat-list">
                    <div class="tw-stat-row"><span class="tw-stat-icon tw-icon-green"><?php echo $statIcons['places']; ?></span><div><span class="tw-stat-num"><?php echo $tw['totalPlaces']; ?></span><span class="tw-stat-label">Places Visited</span></div></div>
                    <div class="tw-stat-row"><span class="tw-stat-icon tw-icon-orange"><?php echo $statIcons['days']; ?></span><div><span class="tw-stat-num"><?php echo $tw['daysTraveled']; ?></span><span class="tw-stat-label">Days Traveled</span></div></div>
                    <div class="tw-stat-row"><span class="tw-stat-icon tw-icon-pink"><?php echo $statIcons['photos']; ?></span><div><span class="tw-stat-num"><?php echo $tw['totalPhotos']; ?></span><span class="tw-stat-label">Photos Uploaded</span></div></div>
                    <div class="tw-stat-row"><span class="tw-stat-icon tw-icon-purple"><?php echo $statIcons['favorites']; ?></span><div><span class="tw-stat-num"><?php echo $twFavoritesCount; ?></span><span class="tw-stat-label">Favorites Marked</span></div></div>
                    <div class="tw-stat-row"><span class="tw-stat-icon tw-icon-blue"><?php echo $statIcons['categories']; ?></span><div><span class="tw-stat-num"><?php echo $tw['categoriesCompleted']; ?></span><span class="tw-stat-label">Categories Explored</span></div></div>
                </div>
                <p class="tw-footer-note">Every number has a memory behind it.</p>
            </div>
        </div>

        <!-- Slide 3: Top Picks -->
        <div class="tw-slide tw-slide-3">
            <div class="tw-slide-inner">
                <h2 class="tw-title-2">Your Top Picks</h2>
                <div class="tw-picks-list">
                    <?php if ($tw['favoriteCategory']): ?>
                    <div class="tw-pick-row">
                        <div class="tw-pick-text">
                            <span class="tw-pick-icon"><?php echo $categoryIcons[$tw['favoriteCategoryKey']] ?? ''; ?></span>
                            <span class="tw-pick-copy"><span class="tw-pick-label">Most Visited Category</span><span class="tw-pick-value"><?php echo htmlspecialchars($tw['favoriteCategory']); ?></span></span>
                        </div>
                        <?php if ($tw['favoriteCategoryImage']): ?><div class="tw-pick-photo"><img src="<?php echo htmlspecialchars($tw['favoriteCategoryImage']); ?>" alt=""></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($tw['mostVisited']): ?>
                    <div class="tw-pick-row">
                        <div class="tw-pick-text">
                            <span class="tw-pick-icon"><?php echo $statIcons['places']; ?></span>
                            <span class="tw-pick-copy"><span class="tw-pick-label">Favorite Place</span><span class="tw-pick-value"><?php echo htmlspecialchars($tw['mostVisited']['name']); ?></span></span>
                        </div>
                        <?php if (!empty($tw['mostVisited']['image'])): ?><div class="tw-pick-photo"><img src="<?php echo htmlspecialchars($tw['mostVisited']['image']); ?>" alt=""></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($tw['firstPlaceVisited']): ?>
                    <div class="tw-pick-row">
                        <div class="tw-pick-text">
                            <span class="tw-pick-icon"><?php echo $statIcons['flag']; ?></span>
                            <span class="tw-pick-copy"><span class="tw-pick-label">First Place Visited</span><span class="tw-pick-value"><?php echo htmlspecialchars($tw['firstPlaceVisited']['name']); ?></span></span>
                        </div>
                        <?php if (!empty($tw['firstPlaceVisited']['image'])): ?><div class="tw-pick-photo"><img src="<?php echo htmlspecialchars($tw['firstPlaceVisited']['image']); ?>" alt=""></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($tw['latestAdventure']): ?>
                    <div class="tw-pick-row">
                        <div class="tw-pick-text">
                            <span class="tw-pick-icon"><?php echo $statIcons['sun']; ?></span>
                            <span class="tw-pick-copy"><span class="tw-pick-label">Latest Adventure</span><span class="tw-pick-value"><?php echo htmlspecialchars($tw['latestAdventure']['name']); ?></span></span>
                        </div>
                        <?php if (!empty($tw['latestAdventure']['image'])): ?><div class="tw-pick-photo"><img src="<?php echo htmlspecialchars($tw['latestAdventure']['image']); ?>" alt=""></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($tw['longestStreak'] > 1): ?>
                    <div class="tw-pick-row">
                        <div class="tw-pick-text">
                            <span class="tw-pick-icon"><?php echo $statIcons['fire']; ?></span>
                            <span class="tw-pick-copy"><span class="tw-pick-label">Longest Streak</span><span class="tw-pick-value"><?php echo $tw['longestStreak']; ?> Days</span></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Slide 4: Journey Timeline -->
        <div class="tw-slide tw-slide-4">
            <div class="tw-slide-inner">
                <h2 class="tw-title-2">Your Journey<br>Timeline</h2>
                <p class="tw-sub-2">Some highlights from your travel diary.</p>
                <div class="tw-mini-timeline">
                    <?php foreach (array_slice($tw['timeline'], 0, 4) as $t): ?>
                        <div class="tw-mini-item">
                            <div class="tw-mini-dot td-badge-<?php echo htmlspecialchars($t['category']); ?>"><?php echo $categoryIcons[$t['category']] ?? ''; ?></div>
                            <div class="tw-mini-text">
                                <span class="tw-mini-date"><?php echo htmlspecialchars(date('M j', strtotime($t['visitedDate']))); ?></span>
                                <span class="tw-mini-verb"><?php echo htmlspecialchars($t['verb']); ?></span>
                                <span class="tw-mini-name"><?php echo htmlspecialchars($t['name']); ?></span>
                            </div>
                            <?php if (!empty($t['photos'])): ?>
                                <div class="tw-mini-photo"><img src="<?php echo htmlspecialchars('../assets/uploads/diary/' . basename($t['photos'][0]['image'])); ?>" alt=""></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="tw-footer-note">More memories await next time!</p>
            </div>
        </div>

        <!-- Slide 5: Thank You -->
        <div class="tw-slide tw-slide-5">
            <div class="tw-slide-inner">
                <h2 class="tw-title-2">Here's to more <span class="tw-accent-green">adventures!</span></h2>
                <p class="tw-thanks">Thank you for exploring Malvar with KULTOURA.<br>Let's make more memories together!</p>
                <blockquote class="tw-quote">
                    Every place you've been leaves a story.<br>
                    Keep exploring. Keep supporting local.<br>
                    Keep loving Malvar.
                </blockquote>
                <div class="tw-brand-footer">KULTOURA<span>MALVAR, BATANGAS</span></div>
            </div>
        </div>

    </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Visit confirmation (geofence check / backdated visit) ── -->
<div class="td-visit-overlay" id="tdVisitOverlay">
    <div class="td-visit-card">
        <button type="button" class="td-visit-close" id="tdVisitClose" aria-label="Close">&times;</button>

        <div class="td-visit-spinner" id="tdVisitSpinner"></div>
        <div class="td-visit-icon" id="tdVisitIcon" hidden></div>

        <p class="td-visit-title" id="tdVisitTitle">Checking your location…</p>
        <p class="td-visit-sub" id="tdVisitSub"></p>

        <input type="date" class="td-visit-date" id="tdVisitDate" hidden>

        <div class="td-visit-actions" id="tdVisitAskActions" hidden>
            <button type="button" class="td-visit-btn td-visit-btn-ghost" id="tdVisitNo">No, cancel</button>
            <button type="button" class="td-visit-btn td-visit-btn-primary" id="tdVisitYes">Yes, I've been there</button>
        </div>

        <div class="td-visit-actions" id="tdVisitConfirmActions" hidden>
            <button type="button" class="td-visit-btn td-visit-btn-ghost" id="tdVisitCancel">Cancel</button>
            <button type="button" class="td-visit-btn td-visit-btn-primary" id="tdVisitSubmit">Log Visit</button>
        </div>
    </div>
</div>

<!-- ── Photo lightbox ── -->
<div class="td-lightbox" id="tdLightbox">
    <button type="button" class="td-lightbox-close" id="tdLightboxClose" aria-label="Close">&times;</button>
    <img src="" alt="" id="tdLightboxImg">
    <p id="tdLightboxCaption"></p>
</div>

<!-- ── Toast ── -->
<div class="td-toast" id="tdToast"></div>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<script>
const TD_CAN_WRAP = <?php echo $canGenerateWrapped ? 'true' : 'false'; ?>;
const TD_WRAPPED_REQUESTED = <?php echo $wrappedRequested ? 'true' : 'false'; ?>;
const TD_WRAPPED_PERIOD = <?php echo json_encode($wrappedPeriod); ?>;
</script>
<!-- Renders a slide's DOM into a downloadable/shareable PNG (used by Save Photo / Share). -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/traveldiary.js"></script>

</body>
</html>
