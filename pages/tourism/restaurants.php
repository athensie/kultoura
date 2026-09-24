<?php
session_start();
include '../../config/dbmain.php';
include '../../config/analytics.php';
analytics_track($conn, 'restaurants');

// Logged for foryou.php's "Because You Explored" recommendations.
$_SESSION['history'][] = 'restaurant';
$_SESSION['history'] = array_slice($_SESSION['history'], -30);

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');
$userId     = $isLoggedIn ? (int) $_SESSION['user_id'] : 0;

/*
 |--------------------------------------------------------------------
 | RESTAURANTS — populated from listings the admin added/approved in
 | adminfoodanddining.php (only 'active' ones are shown here).
 |--------------------------------------------------------------------
 */
$categoryChoices = ['Carinderia', 'Family Restaurant', 'Café', 'Fast Food'];
$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql    = "SELECT restaurant_id, restaurant_name, category, description, address, latitude, longitude, contact_number, opening_hours, image, google_map
           FROM restaurants WHERE 1=1";
$params = [];
$types  = '';

if ($q !== '') {
    $sql     .= " AND (restaurant_name LIKE ? OR description LIKE ?)";
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($category !== '' && $category !== 'All Categories' && in_array($category, $categoryChoices, true)) {
    $sql     .= " AND category = ?";
    $params[] = $category;
    $types   .= 's';
}
$sql .= " ORDER BY restaurant_name ASC";

$rows = [];
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Which of these the logged-in user has already favorited.
$favoritedIds = [];
if ($isLoggedIn && !empty($rows)) {
    $ids          = array_column($rows, 'restaurant_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $conn->prepare("SELECT item_id FROM favorites WHERE user_id = ? AND item_type = 'restaurant' AND item_id IN ($placeholders)");
    $bind  = array_merge([$userId], $ids);
    $stmt->bind_param(str_repeat('i', count($bind)), ...$bind);
    $stmt->execute();
    $favRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $favoritedIds = array_column($favRows, 'item_id');
}

$restaurants = [];
foreach ($rows as $r) {
    $restaurants[] = [
        'id'        => (int) $r['restaurant_id'],
        'name'      => $r['restaurant_name'],
        'category'  => $r['category'],
        'tag'       => $r['category'],
        'desc'      => $r['description'],
        'address'   => $r['address'],
        'lat'       => $r['latitude'],
        'lng'       => $r['longitude'],
        'contact'   => $r['contact_number'],
        'hours'     => $r['opening_hours'],
        // basename() strips any accidentally-stored path/URL prefix (e.g. an
        // older full path saved before the upload code was corrected) down
        // to just the filename, so this always works with the current
        // "../../assets/uploads/food/<filename>" convention below.
        'image'     => $r['image'] ? basename($r['image']) : null,
        'map'       => $r['google_map'],
        'favorited' => in_array((int) $r['restaurant_id'], $favoritedIds, true),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurants – KULTOURA</title>
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link rel="stylesheet" href="../../assets/css/tourism.css">
    <link rel="stylesheet" href="../../assets/css/restaurants.css">
    <!-- View Details / Navigate modal styles are shared: .p-modal-*, .p-nav-* in tourism.css -->
</head>
<body>

<!-- ── Navbar (shared) ── -->
<header class="navbar navbar-solid">

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../../assets/images/salakot.png" alt=""></span>Mabuhay, <?php echo $userName; ?></div>
    <?php else: ?>
        <div class="user-greeting-left" style="letter-spacing:2px;font-size:15px;font-weight:900;">
            <span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>
            <a href="index.php" style="text-decoration:none;color:#C8A96E;">KUL<span style="color:#9fb88a">TOURA</span></a>
        </div>
    <?php endif; ?>

    <nav class="nav-links">
        <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
        <div class="dropdown">
            <a href="../tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR ▾</span></a>
            <div class="mega-menu">
                <div class="mega-column">
                    <h4>Local Products</h4>
                    <a href="products.php">Products</a>
                </div>
                <div class="mega-column">
                    <h4>Local Destinations</h4>
                    <a href="nature.php">Nature</a>
                    <a href="industry.php">Industry Zone</a>
                    <a href="resort.php">Resort</a>
                </div>
                <div class="mega-column">
                    <h4>Culture &amp; Services</h4>
                    <a href="fiestas.php">Fiestas</a>
                    <a href="people.php">People of Malvar</a>
                    <a href="services.php">Other Services</a>
                </div>
            </div>
        </div>
        <a href="restaurants.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg></span><span>RESTAURANTS</span></a>
        <a href="accommodation.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 12v8"/><path d="M2 12h20"/><path d="M2 8h6a2 2 0 0 1 2 2v2"/><path d="M22 8h-6a2 2 0 0 0-2 2v2"/></svg></span><span>ACCOMMODATION</span></a>
        <a href="banks.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M2 10h20L12 4z"/><path d="M8 14v4M12 14v4M16 14v4"/></svg></span><span>BANKS</span></a>
        <div class="dropdown">
            <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></span><span>MORE ▾</span></a>
            <div class="mega-menu mega-menu-simple">
                <div class="mega-column">
                    <a href="../foryou.php">For You</a>
                    <a href="../traveldiary.php">Travel Diary</a>
                    <a href="../favorites.php">Favorites</a>
                    <a href="../mostpopular.php">Most Popular</a>
                    <a href="../about.php">About</a>
                </div>
            </div>
        </div>

    </nav>

    <?php if ($isLoggedIn): ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../../auth/logout.php" class="sign-in-btn sign-in-btn-icon-only" aria-label="Sign Out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
    <?php else: ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../../login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN IN</span></a>
    <?php endif; ?>

    <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

</header>

<!-- ── Page Hero ── -->
<section class="t-hero">
    <div class="t-hero-orb t-orb-1"></div>
    <div class="t-hero-orb t-orb-2"></div>
    <div class="t-hero-inner">
        <p class="t-eyebrow">FOOD · MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Restaurants</h1>
        <p class="t-page-sub">
            From roadside eateries to family restaurants — find your next favorite meal.
        </p>
    </div>
</section>

<!-- ── Restaurants Grid ── -->
<main class="t-main">

    <section class="p-search-row">
        <form class="p-search-bar" action="restaurants.php" method="get">
            <input type="text" name="q" placeholder="Search restaurants, cuisines, areas…" value="<?= htmlspecialchars($q) ?>">
            <select name="category" class="p-category-select">
                <option<?= $category === '' ? ' selected' : '' ?>>All Categories</option>
                <?php foreach ($categoryChoices as $cat): ?>
                    <option<?= $category === $cat ? ' selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <section class="t-category" id="restaurants">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Food</p>
                <h2 class="t-cat-title">Restaurants</h2>
                <p class="t-cat-desc">Where to eat around Malvar, from everyday carinderias to sit-down family spots.</p>
            </div>
        </div>

        <?php if (empty($restaurants)): ?>
        <div class="p-empty-state">
            <h3>No restaurants added yet</h3>
            <p>Once the admin adds listings, they'll show up here as cards.</p>
        </div>
        <?php else: ?>
        <div class="p-card-grid">
            <?php foreach ($restaurants as $item): ?>
            <article class="p-card">
                <div class="p-card-media">
                    <?php if (!empty($item['image'])): ?>
                        <img src="../../assets/uploads/food/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="p-card-img">
                    <?php endif; ?>
                    <button class="p-fav-btn <?= !empty($item['favorited']) ? 'is-favorited' : '' ?>" type="button"
                            data-item-id="<?= (int) $item['id'] ?>" data-item-type="restaurant"
                            onclick="toggleFavorite(this)" aria-label="Save to favorites"><?= !empty($item['favorited']) ? '&#9829;' : '&#9825;' ?></button>
                </div>
                <div class="p-card-body">
                    <p class="p-card-category"><?= htmlspecialchars($item['category']) ?></p>
                    <h3 class="p-card-title"><?= htmlspecialchars($item['name']) ?></h3>
                    <?php if (!empty($item['address'])): ?>
                        <p class="p-card-location">📍 <?= htmlspecialchars($item['address']) ?></p>
                    <?php endif; ?>
                    <p class="p-card-desc"><?= htmlspecialchars($item['desc']) ?></p>
                    <?php if (!empty($item['hours'])): ?>
                        <p class="p-card-location">🕒 <?= htmlspecialchars($item['hours']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item['contact'])): ?>
                        <p class="p-card-location">📞 <?= htmlspecialchars($item['contact']) ?></p>
                    <?php endif; ?>
                    <div class="p-card-actions">
                        <button type="button" class="p-btn p-btn-primary" onclick="openViewDetails(<?= (int) $item['id'] ?>)">View Details</button>
                        <button type="button" class="p-btn p-btn-outline" onclick="openNavigate(<?= (int) $item['id'] ?>)">Navigate</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

</main>

<!-- ── View Details Modal ── -->
<div class="p-modal-overlay" id="viewDetailsModal" onclick="closeModalOutsideX(event, 'viewDetailsModal')">
    <div class="p-modal-card">
        <button class="p-modal-close" onclick="closeModalX('viewDetailsModal')">&times;</button>
        <div class="p-modal-media">
            <img id="vdImage" src="" alt="" style="display:none;">
            <button class="p-modal-fav-icon" id="vdFavBtn" type="button" data-item-type="restaurant" onclick="toggleFavorite(this)" aria-label="Add to favorites">&#9825;</button>
        </div>
        <span class="p-modal-eyebrow" id="vdTag">—</span>
        <h3 class="p-modal-title" id="vdTitle">—</h3>
        <p class="p-modal-desc" id="vdDesc">—</p>
        <div class="p-modal-facts" id="vdFacts"></div>
        <div class="p-modal-actions">
            <button type="button" class="p-btn p-btn-primary" id="vdFavToggleBtn" onclick="toggleFavorite(document.getElementById('vdFavBtn'))">
                <span id="vdFavLabel">♡ Add to Favorites</span>
            </button>
            <button type="button" class="p-btn p-btn-outline" id="vdNavigateBtn">Navigate</button>
        </div>
    </div>
</div>

<!-- ── Navigate Modal ── -->
<div class="p-modal-overlay" id="navigateModal" onclick="closeModalOutsideX(event, 'navigateModal')">
    <div class="p-modal-card p-modal-card-wide">
        <button class="p-modal-close" onclick="closeModalX('navigateModal')">&times;</button>
        <p class="p-modal-eyebrow">Navigate to</p>
        <h3 class="p-modal-title" id="navTitle">—</h3>
        <p class="p-nav-distance" id="navDistance">Locating you…</p>
        <iframe class="p-map-frame" id="navFrame" src="" loading="lazy" allowfullscreen></iframe>
        <div class="p-modal-actions">
            <a href="#" target="_blank" rel="noopener" id="navDirectLink" class="p-btn p-btn-primary" style="display:none;">Open Directions in Google Maps</a>
        </div>
    </div>
</div>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<script src="../../assets/js/navbar.js"></script>
<script src="index.js"></script>
<script>
const restaurantsData = <?php echo json_encode($restaurants); ?>;

function findProduct(id) { return restaurantsData.find(p => p.id === id); }

function openModalX(id) { document.getElementById(id).classList.add('open'); }
function closeModalX(id) { document.getElementById(id).classList.remove('open'); }
function closeModalOutsideX(e, id) { if (e.target.id === id) closeModalX(id); }

/* ---------------- Per-item view tracking (fire-and-forget) ---------------- */
function ktTrackItemView(type, id) {
    if (!id) return;
    const body = new URLSearchParams({ item_type: type, item_id: id }).toString();
    const blob = new Blob([body], { type: 'application/x-www-form-urlencoded' });
    if (navigator.sendBeacon) {
        navigator.sendBeacon('../track_item_view.php', blob);
    } else {
        fetch('../track_item_view.php', { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, keepalive: true }).catch(() => {});
    }
}

/* ---------------- View Details ---------------- */
function openViewDetails(id) {
    const item = findProduct(id);
    if (!item) return;

    ktTrackItemView('restaurant', id);

    document.getElementById('vdTag').textContent = item.category || '—';
    document.getElementById('vdTitle').textContent = item.name;
    document.getElementById('vdDesc').textContent = item.desc || '';

    const img = document.getElementById('vdImage');
    if (item.image) { img.src = '../../assets/uploads/food/' + item.image; img.style.display = ''; }
    else { img.style.display = 'none'; }

    const favBtn = document.getElementById('vdFavBtn');
    favBtn.dataset.itemId = item.id;
    favBtn.classList.toggle('is-favorited', !!item.favorited);
    favBtn.innerHTML = item.favorited ? '&#9829;' : '&#9825;';
    document.getElementById('vdFavLabel').textContent = item.favorited ? '♥ Saved to Favorites' : '♡ Add to Favorites';

    let facts = `<div><p class="p-fact-label">Address</p><p class="p-fact-value">${item.address || '—'}</p></div>`;
    if (item.contact) facts += `<div><p class="p-fact-label">Contact</p><p class="p-fact-value">${item.contact}</p></div>`;
    if (item.hours) facts += `<div><p class="p-fact-label">Opening Hours</p><p class="p-fact-value">${item.hours}</p></div>`;
    document.getElementById('vdFacts').innerHTML = facts;

    document.getElementById('vdNavigateBtn').onclick = function () {
        closeModalX('viewDetailsModal');
        openNavigate(item.id);
    };

    openModalX('viewDetailsModal');
}

/* ---------------- Favorites (used by card hearts + modal heart) ---------------- */
function toggleFavorite(btn) {
    const itemId   = btn.dataset.itemId;
    const itemType = btn.dataset.itemType;

    fetch('../favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'toggle', item_type: itemType, item_id: itemId })
    })
        .then(res => res.json())
        .then(data => {
            if (data.needsLogin) {
                window.location.href = '../../login.php';
                return;
            }
            if (data.success) {
                document.querySelectorAll(`[data-item-id="${itemId}"][data-item-type="${itemType}"]`).forEach(el => {
                    el.classList.toggle('is-favorited', data.favorited);
                    el.innerHTML = data.favorited ? '&#9829;' : '&#9825;';
                });
                const item = findProduct(parseInt(itemId, 10));
                if (item) item.favorited = data.favorited;
                const label = document.getElementById('vdFavLabel');
                if (label) label.textContent = data.favorited ? '♥ Saved to Favorites' : '♡ Add to Favorites';
            }
        })
        .catch(() => { /* network hiccup — leave the button state as-is */ });
}

/* ---------------- Navigate (Google Maps embed) ---------------- */
function haversineKm(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function openNavigate(id) {
    const item = findProduct(id);
    if (!item) return;

    document.getElementById('navTitle').textContent = item.name;
    const distEl = document.getElementById('navDistance');
    const frame = document.getElementById('navFrame');
    const directLink = document.getElementById('navDirectLink');

    const hasCoords = !!(item.lat && item.lng);

    openModalX('navigateModal');

    if (!hasCoords) {
        distEl.textContent = "This location hasn't been mapped yet — the admin needs to set it when adding/editing the listing.";
        frame.src = '';
        directLink.style.display = 'none';
        return;
    }

    const destLat = parseFloat(item.lat);
    const destLng = parseFloat(item.lng);

    frame.src = 'https://www.google.com/maps?q=' + destLat + ',' + destLng + '&z=15&output=embed';
    directLink.href = 'https://www.google.com/maps/dir/?api=1&destination=' + destLat + ',' + destLng;
    directLink.style.display = 'inline-block';
    distEl.textContent = 'Locating you…';

    if (!navigator.geolocation) {
        distEl.textContent = "Your browser doesn't support location — showing the destination only.";
        return;
    }

    navigator.geolocation.getCurrentPosition(function (pos) {
        const userLat = pos.coords.latitude;
        const userLng = pos.coords.longitude;
        const km = haversineKm(userLat, userLng, destLat, destLng);
        distEl.textContent = (km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(1) + ' km') + ' away from your current location';
        frame.src = 'https://www.google.com/maps?saddr=' + userLat + ',' + userLng + '&daddr=' + destLat + ',' + destLng + '&output=embed';
        directLink.href = 'https://www.google.com/maps/dir/?api=1&origin=' + userLat + ',' + userLng + '&destination=' + destLat + ',' + destLng;
    }, function () {
        distEl.textContent = 'Enable location access in your browser to see your distance and directions.';
    }, { enableHighAccuracy: true, timeout: 10000 });
}
</script>

</body>
</html>