<?php
session_start();
include '../config/dbmain.php';
include '../config/analytics.php';
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
    'product'    => 'products',
    'restaurant' => 'food',
    'nature'     => 'destinations',
    'resort'     => 'destinations',
    'industry'   => 'destinations',
    'fiesta'     => 'fiestas',
    'person'     => 'people',
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
    'nature'   => ['link' => 'tourism/nature.php'],
    'resort'   => ['link' => 'tourism/resort.php'],
    'industry' => ['link' => 'tourism/industry.php'],
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
    'product'    => 'Local Products',
    'restaurant' => 'Local Cuisine',
    'nature'     => 'Natural Wonders',
    'resort'     => 'Resorts & Relaxation',
    'industry'   => 'Industry Zones',
    'fiesta'     => 'Fiestas & Events',
    'person'     => 'Faces of Malvar',
];

/* ============================================================
   RECOMMENDATION LOGIC
   ------------------------------------------------------------
   Count how often each category appears in the session history,
   rank categories by frequency, then surface places from the
   top categories. If there's no history yet, fall back to a
   trending mix so the page never looks empty.
============================================================ */
$history = $_SESSION['history'];
$hasHistory = count($history) > 0;

$freq = [];
foreach ($history as $cat) {
    if (!isset($freq[$cat])) $freq[$cat] = 0;
    $freq[$cat]++;
}
arsort($freq);
$topCategories = array_keys($freq);

$recommended = [];
if ($hasHistory) {
    foreach ($topCategories as $cat) {
        foreach ($places as $p) {
            if ($p['category'] === $cat) {
                $recommended[] = $p;
            }
        }
    }
    // de-duplicate while preserving order
    $seen = [];
    $recommended = array_filter($recommended, function ($p) use (&$seen) {
        if (isset($seen[$p['id']])) return false;
        $seen[$p['id']] = true;
        return true;
    });
    $recommended = array_slice(array_values($recommended), 0, 6);
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
        <div class="user-greeting-left"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>Hi, <?php echo $userName; ?></div>
    <?php else: ?>
        <div class="user-greeting-left" style="color:#C8A96E;letter-spacing:2px;font-size:15px;font-weight:900;"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>KUL<span style="color:#9fb88a">TOURA</span></div>
    <?php endif; ?>

    <nav class="nav-links">
        <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>

        <div class="dropdown">
            <a href="tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>TOURISM ▾</span></a>
            <div class="mega-menu">
                <div class="mega-column">
                    <h4>Food</h4>
                    <a href="../pages/tourism/products.php">Products</a>
                    <a href="../pages/tourism/restaurants.php">Restaurants</a>
                </div>
                <div class="mega-column">
                    <h4>Local Destinations</h4>
                    <a href="../pages/tourism/nature.php">Nature</a>
                    <a href="../pages/tourism/industry.php">Industry Zone</a>
                    <a href="../pages/tourism/resort.php">Resort</a>
                </div>
                <div class="mega-column">
                    <h4>Others</h4>
                    <a href="../pages/tourism/fiestas.php">Fiestas</a>
                    <a href="../pages/tourism/people.php">People of Malvar</a>
                </div>
            </div>
        </div>

        <a href="foryou.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/></svg></span><span>FOR YOU</span></a>
        <a href="traveldiary.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M16 4v3h3"/><path d="M8 10h8M8 14h8M8 18h5"/></svg></span><span>TRAVEL DIARY</span></a>
        <a href="favorites.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.5-9.5-9C.5 7 2 3.5 5.5 3.5c2 0 3.5 1 4.5 2.5 1-1.5 2.5-2.5 4.5-2.5C18 3.5 19.5 7 19.5 11 17 15.5 12 20 12 20z"/></svg></span><span>FAVORITES</span></a>
        <a href="mostpopular.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4 0 6-3 6-6.5 0-2.5-1.5-4-2.5-5.5.5 2-1 3-2 2 0-2.5-1.5-4-3-6-.5 3-3 4.5-3 8 0 1-1 1.5-2 1-.5 3 2 7 6.5 7z"/></svg></span><span>MOST POPULAR</span></a>
        <a href="about.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.5v5"/><circle cx="12" cy="7.8" r="0.9" fill="currentColor" stroke="none"/></svg></span><span>ABOUT</span></a>

    </nav>

    <?php if ($isLoggedIn): ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../auth/logout.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
    <?php else: ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../auth/login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN IN</span></a>
    <?php endif; ?>

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
<script src="../assets/js/foryou.js"></script>
</body>
</html>