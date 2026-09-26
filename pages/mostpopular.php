<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
include '../config/analytics.php';
include '../config/sitecontent.php';
analytics_track($conn, 'mostpopular');

$mostpopularHeroPhoto = sitecontent_get_photo($conn, 'mostpopular_hero');

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

/*
 |--------------------------------------------------------------------
 | MOST POPULAR DATA
 |--------------------------------------------------------------------
 | No content table has a views/popularity column, so this ranks
 | items by how many users have favorited them (the `favorites`
 | table). Items with zero favorites simply won't appear.
 */
$popularRows = [];
$result = $conn->query(
    "SELECT item_type, item_id, COUNT(*) AS fav_count
     FROM favorites
     GROUP BY item_type, item_id
     ORDER BY fav_count DESC
     LIMIT 24"
);
if ($result) {
    $popularRows = $result->fetch_all(MYSQLI_ASSOC);
}

$idsByType = [];
foreach ($popularRows as $r) {
    $idsByType[$r['item_type']][] = (int) $r['item_id'];
}

$typeMap = [
    'product'     => ['table' => 'products',    'idCol' => 'product_id',     'nameCol' => 'product_name'],
    'restaurant'  => ['table' => 'restaurants',  'idCol' => 'restaurant_id',  'nameCol' => 'restaurant_name'],
    'destination' => ['table' => 'destination',  'idCol' => 'destination_id', 'nameCol' => 'destination_name'],
    'fiesta'      => ['table' => 'fiestas',      'idCol' => 'fiesta_id',      'nameCol' => 'fiesta_name'],
    'person'      => ['table' => 'people',       'idCol' => 'person_id',      'nameCol' => 'fullname'],
];

$typeLabels = [
    'product'     => 'Product',
    'restaurant'  => 'Restaurant',
    'destination' => 'Destination',
    'fiesta'      => 'Fiesta',
    'person'      => 'Person',
];

$typeLabelsPlural = [
    'product'     => 'Products',
    'restaurant'  => 'Restaurants',
    'destination' => 'Destinations',
    'fiesta'      => 'Fiestas',
    'person'      => 'People',
];

// Upload subfolder per item type — same mapping used by favorites.php.
$imageSubfolder = [
    'product'     => 'products',
    'restaurant'  => 'food',
    'destination' => 'destinations',
    'fiesta'      => 'fiestas',
    'person'      => 'people',
];

$itemDetails = []; // item_type => [ id => row ]
foreach ($idsByType as $type => $ids) {
    if (!isset($typeMap[$type]) || empty($ids)) {
        continue;
    }
    $map = $typeMap[$type];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT * FROM {$map['table']} WHERE {$map['idCol']} IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $row) {
        $itemDetails[$type][(int) $row[$map['idCol']]] = $row;
    }
}

// Destination items route to a specific mega-menu page based on category.
$destinationPageByCategory = [
    'nature'        => 'nature.php',
    'resort'        => 'resort.php',
    'industry'      => 'industry.php',
    'accommodation' => 'accommodation.php',
    'bank'          => 'banks.php',
    'service'       => 'services.php',
    'church'        => 'churches.php',
];

$popularItems = [];
$rank = 0;
foreach ($popularRows as $r) {
    $type = $r['item_type'];
    $id   = (int) $r['item_id'];

    if (!isset($typeMap[$type]) || !isset($itemDetails[$type][$id])) {
        continue; // source item was deleted — skip
    }

    $rank++;
    $map  = $typeMap[$type];
    $item = $itemDetails[$type][$id];

    $viewPage = match ($type) {
        'product'     => 'products.php',
        'restaurant'  => 'restaurants.php',
        'fiesta'      => 'fiestas.php',
        'person'      => 'people.php',
        'destination' => $destinationPageByCategory[$item['category']] ?? 'tourism.php',
        default       => 'tourism.php',
    };

    // basename() strips any accidentally-stored path/URL prefix down to
    // just the filename (same fix used on favorites.php / restaurants.php).
    $rawImage = $item['image'] ?? '';
    $imageSrc = '';
    if ($rawImage !== '') {
        $imageSrc = '../assets/uploads/' . ($imageSubfolder[$type] ?? $type) . '/' . basename($rawImage);
    }

    $popularItems[] = [
        'rank'      => $rank,
        'type'      => $type,
        'typeLabel' => $typeLabels[$type],
        'id'        => $id,
        'name'      => $item[$map['nameCol']],
        'desc'      => $item['description'] ?? '',
        'image'     => $imageSrc,
        'favCount'  => (int) $r['fav_count'],
        'viewPage'  => 'tourism/' . $viewPage,
    ];
}

// Per-category counts for the filter bar (computed after the loop above,
// since that's what determines which items actually made the final list).
$typeCounts = [];
foreach ($popularItems as $p) {
    $typeCounts[$p['type']] = ($typeCounts[$p['type']] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Most Popular – KULTOURA</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/mostpopular.css">
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

<!-- ── Page Hero ── -->
<section class="t-hero<?php echo $mostpopularHeroPhoto ? ' t-hero-has-photo' : ''; ?>">
    <?php if ($mostpopularHeroPhoto): ?>
        <img class="t-hero-photo" src="<?php echo htmlspecialchars($mostpopularHeroPhoto); ?>" alt="">
        <div class="t-hero-scrim"></div>
    <?php else: ?>
        <div class="t-hero-orb t-orb-1"></div>
        <div class="t-hero-orb t-orb-2"></div>
    <?php endif; ?>
    <div class="t-hero-inner">
        <p class="t-eyebrow">MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Most Popular</h1>
        <p class="t-page-sub">
            What everyone else is loving right now, ranked by community favorites.
        </p>
    </div>

    <?php if (!empty($popularItems)):
        $totalFavs = array_sum(array_column($popularItems, 'favCount'));
    ?>
    <div class="m-stats-row">
        <div class="m-stat">
            <span class="m-stat-num"><?php echo count($popularItems); ?></span>
            <span class="m-stat-label">Trending Spots</span>
        </div>
        <div class="m-stat-divider"></div>
        <div class="m-stat">
            <span class="m-stat-num"><?php echo $totalFavs; ?></span>
            <span class="m-stat-label">Community Favorites</span>
        </div>
        <div class="m-stat-divider"></div>
        <div class="m-stat">
            <span class="m-stat-num"><?php echo count($typeCounts); ?></span>
            <span class="m-stat-label">Categories</span>
        </div>
    </div>
    <?php endif; ?>
</section>

<!-- ── Popular List ── -->
<main class="m-main">

    <div class="m-page-layout">

        <!-- ── Category sidebar ── -->
        <?php if (!empty($popularItems)): ?>
        <aside class="m-sidebar">
            <p class="m-sidebar-label">Category</p>
            <nav class="m-cat-list" id="popCategoryBar">
                <button type="button" class="m-cat-item is-active" data-type="all" onclick="filterPopularByType('all', this)">
                    <span>All</span> <span class="m-cat-count"><?php echo count($popularItems); ?></span>
                </button>
                <?php foreach ($typeLabels as $typeKey => $label):
                    if (empty($typeCounts[$typeKey])) continue;
                ?>
                <button type="button" class="m-cat-item" data-type="<?php echo htmlspecialchars($typeKey); ?>" onclick="filterPopularByType('<?php echo htmlspecialchars($typeKey); ?>', this)">
                    <span><?php echo htmlspecialchars($typeLabelsPlural[$typeKey]); ?></span> <span class="m-cat-count"><?php echo (int) $typeCounts[$typeKey]; ?></span>
                </button>
                <?php endforeach; ?>
            </nav>
        </aside>
        <?php endif; ?>

        <!-- ── Content column ── -->
        <div class="m-content-col">

            <div class="m-toolbar">
                <input class="m-search" type="text" id="popSearch" placeholder="Search most popular…" oninput="filterPopular(this.value)">
            </div>

            <?php if (empty($popularItems)): ?>
                <div class="m-empty">
                    <div class="m-empty-icon">★</div>
                    <h3>Nothing popular yet</h3>
                    <p>Once visitors start favoriting products, restaurants, destinations, fiestas, and people, the most-loved ones will show up here.</p>
                    <a href="tourism.php" class="m-empty-cta">Explore Tourism</a>
                </div>
            <?php else: ?>
                <div class="m-grid" id="popGrid">
                    <?php foreach ($popularItems as $p):
                        $rankClass = $p['rank'] <= 3 ? ' m-rank-top' : '';
                    ?>
                        <div class="m-card" data-type="<?php echo htmlspecialchars($p['type']); ?>">
                            <div class="m-rank<?php echo $rankClass; ?>">#<?php echo (int) $p['rank']; ?></div>
                            <?php if (!empty($p['image'])): ?>
                                <div class="m-card-media">
                                    <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                                </div>
                            <?php endif; ?>
                            <div class="m-card-body">
                                <div class="m-card-top">
                                    <span class="m-badge"><?php echo htmlspecialchars($p['typeLabel']); ?></span>
                                    <span class="m-fav-count">♥ <?php echo (int) $p['favCount']; ?></span>
                                </div>
                                <h3 class="m-card-title"><?php echo htmlspecialchars($p['name']); ?></h3>
                                <?php if (!empty($p['desc'])): ?>
                                    <p class="m-card-desc"><?php echo htmlspecialchars(mb_strimwidth($p['desc'], 0, 120, '…')); ?></p>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($p['viewPage']); ?>" class="m-card-link">View →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

    </div>

</main>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/mostpopular.js"></script>

</body>
</html>