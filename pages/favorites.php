<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
include '../config/analytics.php';
include '../config/sitecontent.php';
analytics_track($conn, 'favorites');

$favoritesHeroPhoto = sitecontent_get_photo($conn, 'favorites_hero');

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

/*
 |--------------------------------------------------------------------
 | AJAX: TOGGLE A FAVORITE (called from the heart button on
 | products.php, restaurants.php, and any other listing page)
 |--------------------------------------------------------------------
 | POST action=toggle, item_type, item_id -> JSON { success, favorited }
 | This runs BEFORE the "must be logged in" redirect below, since it's
 | called via fetch() from pages guests can still browse.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'needsLogin' => true, 'message' => 'Please sign in to save favorites.']);
        exit;
    }

    $toggleUserId = (int) $_SESSION['user_id'];
    $toggleType   = $_POST['item_type'] ?? '';
    $toggleId     = (int) ($_POST['item_id'] ?? 0);
    $validTypes   = ['product', 'restaurant', 'destination', 'fiesta', 'person'];

    if (!in_array($toggleType, $validTypes, true) || $toggleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT favorite_id FROM favorites WHERE user_id = ? AND item_type = ? AND item_id = ?");
    $stmt->bind_param('isi', $toggleUserId, $toggleType, $toggleId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $stmt = $conn->prepare("DELETE FROM favorites WHERE favorite_id = ?");
        $stmt->bind_param('i', $existing['favorite_id']);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'favorited' => false]);
    } else {
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, item_type, item_id) VALUES (?, ?, ?)");
        $stmt->bind_param('isi', $toggleUserId, $toggleType, $toggleId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'favorited' => true]);
    }
    exit;
}

if (!$isLoggedIn) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/*
 |--------------------------------------------------------------------
 | REMOVE A FAVORITE (posts back to this same page)
 |--------------------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $removeType = $_POST['item_type'] ?? '';
    $removeId   = (int) ($_POST['item_id'] ?? 0);
    $validTypes = ['product', 'restaurant', 'destination', 'fiesta', 'person'];

    if (in_array($removeType, $validTypes, true) && $removeId > 0) {
        $stmt = $conn->prepare(
            "DELETE FROM favorites WHERE user_id = ? AND item_type = ? AND item_id = ?"
        );
        $stmt->bind_param('isi', $userId, $removeType, $removeId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash'] = 'Removed from your favorites.';
    } else {
        $_SESSION['flash'] = 'Something went wrong — invalid item.';
    }

    header("Location: favorites.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | FLASH MESSAGE (set above after a remove, then redirected here)
 |--------------------------------------------------------------------
 */
$flashMessage = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/*
 |--------------------------------------------------------------------
 | FAVORITES DATA
 |--------------------------------------------------------------------
 | The `favorites` table just links a user to an item_type + item_id.
 | We fetch the ids first, then batch-fetch the real item details
 | from whichever table each item_type maps to.
 |
 | Run this once if the table doesn't exist yet:
 |
 |   CREATE TABLE `favorites` (
 |     `favorite_id` int(11) NOT NULL AUTO_INCREMENT,
 |     `user_id` int(11) NOT NULL,
 |     `item_type` enum('product','restaurant','destination','fiesta','person') NOT NULL,
 |     `item_id` int(11) NOT NULL,
 |     `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
 |     PRIMARY KEY (`favorite_id`),
 |     UNIQUE KEY `uniq_favorite` (`user_id`,`item_type`,`item_id`)
 |   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 */
$favoriteRows = [];
$stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$favoriteRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$idsByType = [];
foreach ($favoriteRows as $r) {
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

/*
 |--------------------------------------------------------------------
 | Upload subfolder per item type.
 |--------------------------------------------------------------------
 | 'food' and 'destinations' are confirmed from the actual project
 | structure; 'products', 'fiestas', and 'people' are a best guess
 | matching the table name and unverified — check those if a saved
 | product/fiesta/person's picture doesn't show up here.
 */
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

// Destination favorites route to a specific mega-menu page based on category.
// Keys match the raw `category` ENUM values in the `destination` table
// (nature / industry / resort) — NOT the ucfirst()'d display labels.
$destinationPageByCategory = [
    'nature'        => 'nature.php',
    'resort'        => 'resort.php',
    'industry'      => 'industry.php',
    'accommodation' => 'accommodation.php',
    'bank'          => 'banks.php',
    'service'       => 'services.php',
    'church'        => 'churches.php',
];

$favorites = [];
foreach ($favoriteRows as $r) {
    $type = $r['item_type'];
    $id   = (int) $r['item_id'];

    if (!isset($typeMap[$type]) || !isset($itemDetails[$type][$id])) {
        continue; // source item was deleted — skip the orphaned favorite
    }

    $map  = $typeMap[$type];
    $item = $itemDetails[$type][$id];

    // All 5 item types live under pages/tourism/ (destination's category
    // maps to nature/industry/resort there too) — this page (favorites.php)
    // sits one level up, so every link needs the 'tourism/' prefix.
    $viewPage = 'tourism/' . match ($type) {
        'product'     => 'products.php',
        'restaurant'  => 'restaurants.php',
        'fiesta'      => 'fiestas.php',
        'person'      => 'people.php',
        'destination' => $destinationPageByCategory[$item['category']] ?? 'tourism.php',
        default       => 'tourism.php',
    };

    // basename() strips any accidentally-stored path/URL prefix down to
    // just the filename, then we build the real working src ourselves —
    // same fix already applied to tourism.php's hub tiles and
    // restaurants.php's cards.
    $rawImage = $item['image'] ?? '';
    $imageSrc = '';
    if ($rawImage !== '') {
        $imageSrc = '../assets/uploads/' . ($imageSubfolder[$type] ?? $type) . '/' . basename($rawImage);
    }

    // Coordinates for the Navigate map: product/restaurant/fiesta store
    // separate latitude/longitude columns; destination stores one combined
    // "lat,lng" google_maps string instead; person has no location at all.
    $lat = null;
    $lng = null;
    if (!empty($item['latitude']) && !empty($item['longitude'])) {
        $lat = (float) $item['latitude'];
        $lng = (float) $item['longitude'];
    } elseif (!empty($item['google_maps']) && str_contains($item['google_maps'], ',')) {
        [$gLat, $gLng] = array_map('trim', explode(',', $item['google_maps'], 2));
        if (is_numeric($gLat) && is_numeric($gLng)) {
            $lat = (float) $gLat;
            $lng = (float) $gLng;
        }
    }

    $favorites[] = [
        'type'     => $type,
        'typeLabel'=> $typeLabels[$type],
        'id'       => $id,
        'name'     => $item[$map['nameCol']],
        'desc'     => $item['description'] ?? '',
        'image'    => $imageSrc,
        'tag'      => $item['address'] ?? $item['location'] ?? '',
        'lat'      => $lat,
        'lng'      => $lng,
        'category' => $type === 'destination' ? ucfirst($item['category'] ?? '') : $typeLabels[$type],
        'viewPage' => $viewPage,
        'savedAt'  => $r['created_at'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorites – KULTOURA</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/favorites.css">
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
<section class="t-hero<?php echo $favoritesHeroPhoto ? ' t-hero-has-photo' : ''; ?>">
    <?php if ($favoritesHeroPhoto): ?>
        <img class="t-hero-photo" src="<?php echo htmlspecialchars($favoritesHeroPhoto); ?>" alt="">
        <div class="t-hero-scrim"></div>
    <?php else: ?>
        <div class="t-hero-orb t-orb-1"></div>
        <div class="t-hero-orb t-orb-2"></div>
    <?php endif; ?>
    <div class="t-hero-inner">
        <p class="t-eyebrow">MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Your Favorites</h1>
        <p class="t-page-sub">
            Everything you've saved across KulToura, in one place.
        </p>
    </div>
</section>

<!-- ── Favorites List ── -->
<main class="f-main">

    <div class="f-toolbar">
        <input class="f-search" type="text" id="favSearch" placeholder="Search your favorites…" oninput="filterFavorites(this.value)">
        <select class="f-filter" id="favTypeFilter" onchange="filterFavoritesByType(this.value)">
            <option value="all">All Types</option>
            <option value="product">Products</option>
            <option value="restaurant">Restaurants</option>
            <option value="destination">Destinations</option>
            <option value="fiesta">Fiestas</option>
            <option value="person">People</option>
        </select>
    </div>

    <?php if (empty($favorites)): ?>
        <div class="f-empty">
            <div class="f-empty-heart">♡</div>
            <h3>No favorites yet</h3>
            <p>Tap the heart icon on any product, restaurant, destination, fiesta, or person to save it here.</p>
            <a href="tourism.php" class="f-empty-cta">Explore Tourism</a>
        </div>
    <?php else: ?>
        <div class="f-grid" id="favGrid">
            <?php foreach ($favorites as $f): ?>
                <div class="f-card" data-type="<?php echo htmlspecialchars($f['type']); ?>" data-fav-key="<?php echo htmlspecialchars($f['type']); ?>-<?php echo (int) $f['id']; ?>"
                    data-id="<?php echo (int) $f['id']; ?>"
                    data-name="<?php echo htmlspecialchars($f['name']); ?>"
                    data-category="<?php echo htmlspecialchars($f['category']); ?>"
                    data-tag="<?php echo htmlspecialchars($f['tag']); ?>"
                    data-desc="<?php echo htmlspecialchars($f['desc']); ?>"
                    data-image="<?php echo htmlspecialchars($f['image']); ?>"
                    data-lat="<?php echo htmlspecialchars((string) ($f['lat'] ?? '')); ?>"
                    data-lng="<?php echo htmlspecialchars((string) ($f['lng'] ?? '')); ?>"
                    data-view-page="<?php echo htmlspecialchars($f['viewPage']); ?>"
                    onclick="ktOpenViewDetails(this)">
                    <?php if (!empty($f['image'])): ?>
                        <div class="f-card-media">
                            <img src="<?php echo htmlspecialchars($f['image']); ?>" alt="<?php echo htmlspecialchars($f['name']); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="f-card-body">
                    <div class="f-card-top">
                        <span class="f-badge"><?php echo htmlspecialchars($f['typeLabel']); ?></span>
                        <form action="favorites.php" method="POST" class="f-remove-form" onclick="event.stopPropagation()">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="item_type" value="<?php echo htmlspecialchars($f['type']); ?>">
                            <input type="hidden" name="item_id" value="<?php echo (int) $f['id']; ?>">
                            <button type="submit" class="f-heart-btn" title="Remove from favorites">♥</button>
                        </form>
                    </div>
                    <h3 class="f-card-title"><?php echo htmlspecialchars($f['name']); ?></h3>
                    <?php if (!empty($f['desc'])): ?>
                        <p class="f-card-desc"><?php echo htmlspecialchars(mb_strimwidth($f['desc'], 0, 120, '…')); ?></p>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── View Details Popup (shared .p-modal-* styles in tourism.css) ── -->
<style>
.p-btn { font-family:'Helvetica Neue', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:.3px; text-decoration:none; padding:9px 16px; border-radius:24px; cursor:pointer; display:inline-block; }
.p-btn-primary { background:#8b2e1a; color:#fff; border:1px solid #8b2e1a; }
.p-btn-primary:hover { background:#a83520; border-color:#a83520; }
.p-btn-outline { background:#fff; color:#2c1a0e; border:1px solid rgba(44,26,14,.15); }
.p-btn-outline:hover { border-color:#8b2e1a; color:#8b2e1a; }
</style>

<div class="p-modal-overlay" id="ktViewDetailsModal" data-item-id="" data-item-type="" onclick="if(event.target===this) ktCloseModal('ktViewDetailsModal')">
    <div class="p-modal-card">
        <button class="p-modal-close" onclick="ktCloseModal('ktViewDetailsModal')" aria-label="Close">&times;</button>
        <div class="p-modal-media">
            <img id="ktVdImage" src="" alt="" style="display:none;">
            <button type="button" class="p-modal-fav-icon is-favorited" id="ktVdFavIcon" onclick="ktUnfavoriteFromModal()" aria-label="Remove from favorites">&#9829;</button>
        </div>
        <p class="p-modal-eyebrow" id="ktVdCategory"></p>
        <h3 class="p-modal-title" id="ktVdName"></h3>
        <p class="p-modal-desc" id="ktVdDesc"></p>
        <div class="p-modal-facts" id="ktVdFacts"></div>
        <div class="p-modal-actions">
            <button type="button" id="ktVdFavBtn" class="p-btn p-btn-primary" onclick="ktUnfavoriteFromModal()">♥ Remove from Favorites</button>
            <button type="button" class="p-btn p-btn-outline" id="ktVdNavigateBtn">Navigate</button>
            <a href="#" id="ktVdFullPageLink" class="p-btn p-btn-outline">View Full Page</a>
        </div>
    </div>
</div>

<!-- ── Navigate Modal ── -->
<div class="p-modal-overlay" id="ktNavigateModal" onclick="if(event.target===this) ktCloseModal('ktNavigateModal')">
    <div class="p-modal-card p-modal-card-wide">
        <button class="p-modal-close" onclick="ktCloseModal('ktNavigateModal')" aria-label="Close">&times;</button>
        <p class="p-modal-eyebrow">Navigate to</p>
        <h3 class="p-modal-title" id="ktNavName">—</h3>
        <p class="p-nav-distance" id="ktNavDistance">Locating you…</p>
        <iframe class="p-map-frame" id="ktNavFrame" src="" loading="lazy" allowfullscreen></iframe>
        <div class="p-modal-actions">
            <a href="#" target="_blank" rel="noopener" id="ktNavDirectLink" class="p-btn p-btn-primary" style="display:none;">Open Directions in Google Maps</a>
        </div>
    </div>
</div>

</main>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<!-- ── TOAST ── -->
<div class="f-toast" id="favToast"></div>

<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/favorites.js"></script>
<script>
function ktShowModal(id) { document.getElementById(id).classList.add('open'); }
function ktCloseModal(id) { document.getElementById(id).classList.remove('open'); }

function ktOpenViewDetails(el) {
    const d = el.dataset;
    const imgEl = document.getElementById('ktVdImage');
    imgEl.src = d.image || '';
    imgEl.style.display = d.image ? 'block' : 'none';
    document.getElementById('ktVdCategory').textContent = d.category || '';
    document.getElementById('ktVdName').textContent = d.name || '';
    document.getElementById('ktVdDesc').textContent = d.desc || 'No description available yet.';

    let facts = '';
    if (d.tag) facts += `<div><p class="p-fact-label">Address</p><p class="p-fact-value">${d.tag}</p></div>`;
    document.getElementById('ktVdFacts').innerHTML = facts;

    document.getElementById('ktVdFullPageLink').href = d.viewPage || '#';

    const modal = document.getElementById('ktViewDetailsModal');
    modal.dataset.itemId = d.id || '';
    modal.dataset.itemType = d.type || '';

    const navName = d.name || '';
    const navLat = d.lat || '';
    const navLng = d.lng || '';
    document.getElementById('ktVdNavigateBtn').onclick = function () {
        ktCloseModal('ktViewDetailsModal');
        setTimeout(() => ktOpenNavigate(navName, navLat, navLng), 200);
    };

    ktShowModal('ktViewDetailsModal');
}

/* ---------- NAVIGATE (Google Maps embed) ---------- */
function ktHaversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function ktOpenNavigate(name, lat, lng) {
    document.getElementById('ktNavName').textContent = name || 'this location';
    const distanceEl = document.getElementById('ktNavDistance');
    const frame = document.getElementById('ktNavFrame');
    const directLink = document.getElementById('ktNavDirectLink');

    const destLat = parseFloat(lat);
    const destLng = parseFloat(lng);
    const hasCoords = !isNaN(destLat) && !isNaN(destLng);

    ktShowModal('ktNavigateModal');

    if (!hasCoords) {
        distanceEl.textContent = "This location hasn't been mapped yet.";
        frame.src = '';
        directLink.style.display = 'none';
        return;
    }

    frame.src = 'https://www.google.com/maps?q=' + destLat + ',' + destLng + '&z=15&output=embed';
    directLink.href = 'https://www.google.com/maps/dir/?api=1&destination=' + destLat + ',' + destLng;
    directLink.style.display = 'inline-block';
    distanceEl.textContent = 'Locating you…';

    if (!navigator.geolocation) {
        distanceEl.textContent = "Your browser doesn't support location — showing the destination only.";
        return;
    }

    navigator.geolocation.getCurrentPosition(function (pos) {
        const userLat = pos.coords.latitude;
        const userLng = pos.coords.longitude;
        const dist = ktHaversineKm(userLat, userLng, destLat, destLng);
        distanceEl.textContent = dist.toFixed(1) + ' km away from your current location';
        frame.src = 'https://www.google.com/maps?saddr=' + userLat + ',' + userLng + '&daddr=' + destLat + ',' + destLng + '&output=embed';
        directLink.href = 'https://www.google.com/maps/dir/?api=1&origin=' + userLat + ',' + userLng + '&destination=' + destLat + ',' + destLng;
    }, function () {
        distanceEl.textContent = 'Enable location access in your browser to see distance and directions.';
    }, { enableHighAccuracy: true, timeout: 10000 });
}

async function ktUnfavoriteFromModal() {
    const modal = document.getElementById('ktViewDetailsModal');
    const itemId = modal.dataset.itemId;
    const itemType = modal.dataset.itemType;
    if (!itemId || !itemType) return;

    try {
        const res = await fetch('favorites.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'toggle', item_type: itemType, item_id: itemId })
        });
        const data = await res.json();

        if (data.needsLogin) {
            showFavToast(data.message || 'Please sign in to manage favorites.');
            return;
        }
        if (data.success && !data.favorited) {
            ktCloseModal('ktViewDetailsModal');
            const card = document.querySelector('.f-card[data-fav-key="' + itemType + '-' + itemId + '"]');
            if (card) card.remove();
            showFavToast('Removed from favorites');

            const grid = document.getElementById('favGrid');
            if (grid && !grid.querySelector('.f-card')) {
                location.reload();
            }
        }
    } catch (err) {
        console.error('Unfavorite failed:', err);
        showFavToast('Something went wrong. Please try again.');
    }
}
</script>
<?php if ($flashMessage): ?>
<script>
document.addEventListener('DOMContentLoaded', function () { showFavToast(<?php echo json_encode($flashMessage); ?>); });
</script>
<?php endif; ?>

</body>
</html>