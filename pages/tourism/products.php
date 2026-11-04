<?php
require_once __DIR__ . '/../../config/session_boot.php';
require_once '../../config/dbmain.php';
require_once __DIR__ . '/../../config/maintenance.php';
kt_maintenance_gate($conn);
require_once '../../config/analytics.php';
include '../../config/sitecontent.php';
require_once __DIR__ . '/../../config/page_widgets.php';
require_once __DIR__ . '/../../config/product_locations.php';
$pageWidget = kt_page_widget_get($conn, 'products');
analytics_track($conn, 'products');
$_SESSION['history'][] = 'product';
$_SESSION['history'] = array_slice($_SESSION['history'], -30);
$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? '');
$userId     = $isLoggedIn ? (int) $_SESSION['user_id'] : 0;
$categoryChoices = ['Local Food', 'Local Products', 'Crafts'];
$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$sql  = "SELECT product_id, product_name, category, description, summary, price, location, latitude, longitude, image
         FROM products WHERE status = 'active' ORDER BY product_name ASC";
$rows = [];
$res  = $conn->query($sql);
if ($res) {
    $rows = $res->fetch_all(MYSQLI_ASSOC);
}
$storeLocationsByProduct = kt_product_locations_fetch($conn, array_column($rows, 'product_id'));
$favoritedIds = [];
if ($isLoggedIn && !empty($rows)) {
    $ids          = array_column($rows, 'product_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $conn->prepare("SELECT item_id FROM favorites WHERE user_id = ? AND item_type = 'product' AND item_id IN ($placeholders)");
    $bind  = array_merge([$userId], $ids);
    $stmt->bind_param(str_repeat('i', count($bind)), ...$bind);
    $stmt->execute();
    $favRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $favoritedIds = array_column($favRows, 'item_id');
}
$products = [];
foreach ($rows as $r) {
    $products[] = [
        'id'        => (int) $r['product_id'],
        'name'      => $r['product_name'],
        'category'  => $r['category'],
        'tag'       => $r['category'],
        'desc'      => $r['description'],
        'summary'   => $r['summary'] ?: mb_strimwidth((string) ($r['description'] ?? ''), 0, 160, '…'),
        'price'     => $r['price'],
        'address'   => $r['location'],
        'lat'       => $r['latitude'],
        'lng'       => $r['longitude'],
        'locations' => $storeLocationsByProduct[(int) $r['product_id']] ?? [],
        'image'     => $r['image'],
        'favorited' => in_array((int) $r['product_id'], $favoritedIds, true),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products – KULTOURA</title>
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link rel="stylesheet" href="../../assets/css/search.css?v=20261101-2">
    <link rel="stylesheet" href="../../assets/css/tourism.css">
    <link rel="stylesheet" href="../../assets/css/pagewidget.css">
    <link rel="stylesheet" href="../../assets/css/product.css?v=20261104-2">
    <link rel="stylesheet" href="../../assets/css/product-location-map.css?v=20261102-4">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
<header class="navbar navbar-solid">
    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../../assets/images/salakot.png?v=20261008" alt=""></span><span class="navbar-greeting-text">Mabuhay, <?php echo $userName; ?></span></div>
    <?php else: ?>
<div class="user-greeting-left navbar-brand-logo"><img src="../../assets/images/kultoura.png" alt="KulToura"></div>
    <?php endif; ?>
    <nav class="nav-links">
        <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
        <div class="dropdown">
            <a href="../tourism.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR ▾</span></a>
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
                    <a href="churches.php">Churches</a>
                </div>
                <div class="mega-column">
                    <h4>Culture &amp; Services</h4>
                    <a href="fiestas.php">Fiestas</a>
                    <a href="people.php">People of Malvar</a>
                    <a href="services.php">Other Services</a>
                </div>
            </div>
        </div>
        <a href="restaurants.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg></span><span>RESTAURANTS</span></a>
        <a href="accommodation.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 12v8"/><path d="M2 12h20"/><path d="M2 8h6a2 2 0 0 1 2 2v2"/><path d="M22 8h-6a2 2 0 0 0-2 2v2"/></svg></span><span>ACCOMMODATION</span></a>
        <a href="banks.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M2 10h20L12 4z"/><path d="M8 14v4M12 14v4M16 14v4"/></svg></span><span>BANKS</span></a>
        <div class="dropdown">
            <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></span><span>MORE ▾</span></a>
            <div class="mega-menu mega-menu-simple">
                <div class="mega-column">
                    <a href="../foryou.php">For You</a>
                    <a href="../traveldiary.php">Travel Diary</a>
                    <?php if ($isLoggedIn): ?><a href="../profile.php">My Profile</a><?php endif; ?>
                    <a href="../favorites.php">Favorites</a>
                    <a href="../mostpopular.php">Most Popular</a>
                    <a href="../about.php">About</a>
                </div>
            </div>
        </div>
            <?php if ($isLoggedIn): ?><a href="../../auth/logout.php" class="nav-item nav-signout-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span><span>SIGN OUT</span></a><?php else: ?><a href="../../auth/login.php" class="nav-item nav-signin-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg></span><span>SIGN IN</span></a><?php endif; ?>
    </nav>
        <div class="navbar-search" data-api="../search_api.php" data-results="../search.php">
        <button type="button" class="navbar-search-toggle" aria-label="Search" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </button>
        <form class="navbar-search-form" action="../search.php" method="GET">
            <input type="search" name="q" class="navbar-search-input" placeholder="Search KulToura…" autocomplete="off">
        </form>
        <div class="navbar-search-dropdown"></div>
    </div>
<button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</header>
<?php if (!$pageWidget): ?>
<section class="t-hero">
    <div class="t-hero-orb t-orb-1"></div>
    <div class="t-hero-orb t-orb-2"></div>
    <div class="t-hero-inner">
        <p class="t-eyebrow">FOOD · MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Products</h1>
        <p class="t-page-sub">
            Locally made goods, native delicacies, and fresh produce from Malvar's markets.
        </p>
    </div>
</section>
<?php endif; ?>
<?php kt_page_widget_render_from($pageWidget, 'products'); ?>
<main class="t-main">
    <section class="p-search-row">
        <form class="p-search-bar" action="products.php" method="get" id="productSearchForm">
            <input type="text" name="q" id="productSearchInput" placeholder="Search food, places, events…" value="<?= htmlspecialchars($q) ?>" autocomplete="off">
            <select name="category" id="productCategorySelect" class="p-category-select">
                <option<?= $category === '' ? ' selected' : '' ?>>All Categories</option>
                <?php foreach ($categoryChoices as $cat): ?>
                    <option<?= $category === $cat ? ' selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <p class="p-search-empty" id="productSearchEmpty" hidden>No products match your search.</p>
    </section>
    <section class="t-category" id="products-list">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label" id="productCatLabel">Products</p>
                <h2 class="t-cat-title" id="productCatTitle">Products</h2>
                <p class="t-cat-desc" id="productCatDesc">A mix of local products, food, and crafts from Malvar.</p>
            </div>
        </div>
        <?php if (empty($products)): ?>
        <div class="p-empty-state">
            <h3>No products yet</h3>
            <p>Once the admin adds items, they'll show up here as cards.</p>
        </div>
        <?php else: ?>
        <div class="p-card-grid" id="productCardGrid">
            <?php foreach ($products as $item): ?>
            <article class="p-card" data-id="<?= (int) $item['id'] ?>" onclick="openViewDetails(<?= (int) $item['id'] ?>)">
                <div class="p-card-media">
                    <?php if (!empty($item['image'])): ?>
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="p-card-img">
                    <?php endif; ?>
                    <button class="p-fav-btn <?= !empty($item['favorited']) ? 'is-favorited' : '' ?>" type="button"
                            data-item-id="<?= (int) $item['id'] ?>" data-item-type="product"
                            onclick="event.stopPropagation(); toggleFavorite(this)" aria-label="Save to favorites"><?= !empty($item['favorited']) ? '&#9829;' : '&#9825;' ?></button>
                    <span class="p-tag-pill"><?= htmlspecialchars($item['tag']) ?></span>
                </div>
                <div class="p-card-body">
                    <p class="p-card-category"><?= htmlspecialchars($item['category']) ?></p>
                    <h3 class="p-card-title"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="p-card-desc"><?= htmlspecialchars($item['summary']) ?></p>
                    <?php if (!empty($item['locations']) || !empty($item['address'])): ?><button type="button" class="p-card-location" data-item-id="<?= (int) $item['id'] ?>" onclick="event.stopPropagation(); findNearbyShop(this)"><span aria-hidden="true">📍</span><span class="p-card-location-text">Find a shop near you</span></button><?php endif; ?>
                    <?php if ($item['price'] !== null && $item['price'] !== ''): ?>
                        <p class="p-card-price">₱<?= htmlspecialchars(number_format((float) $item['price'], 2)) ?></p>
                    <?php endif; ?>
                    <div class="p-card-actions">
                        <button type="button" class="p-btn p-btn-primary" onclick="event.stopPropagation(); openViewDetails(<?= (int) $item['id'] ?>)">View Details</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</main>
<div class="p-modal-overlay" id="viewDetailsModal" data-item-id="" onclick="closeModalOutsideX(event, 'viewDetailsModal')">
    <div class="p-modal-card">
        <button class="p-modal-close" onclick="closeModalX('viewDetailsModal')">&times;</button>
        <div class="p-modal-media">
            <img id="vdImage" src="" alt="" style="display:none;">
            <button class="p-modal-fav-icon" id="vdFavBtn" type="button" data-item-type="product" onclick="toggleFavorite(this)" aria-label="Add to favorites">&#9825;</button>
        </div>
        <span class="p-modal-eyebrow" id="vdTag">—</span>
        <h3 class="p-modal-title" id="vdTitle">—</h3>
        <p class="p-modal-desc" id="vdDesc">—</p>
        <div class="p-modal-facts" id="vdFacts"></div>
        <div class="p-product-stores" id="vdStores" hidden>
            <div class="p-nearest-shop" id="vdNearestShop" hidden>
                <p class="p-nearest-shop-label">Shop near you</p>
                <p class="p-nearest-shop-name" id="vdNearestShopName"></p>
                <p class="p-nearest-shop-address" id="vdNearestShopAddress"></p>
                <p class="p-nearest-shop-distance" id="vdNearestShopDistance"></p>
            </div>
            <h4 id="vdStoresHeading">Places that sell this product</h4>
            <ul class="p-product-store-addresses" id="vdStoresList"></ul>
            <p class="p-map-hint">Select a pin to see the location, distance, and directions.</p>
            <div class="p-product-locations-map" id="vdStoresMap"></div>
            <div class="p-map-unmapped" id="vdUnmappedLocations" hidden></div>
        </div>
        <div class="p-modal-actions">
            <button type="button" class="p-btn p-btn-primary" id="vdFavToggleBtn" onclick="toggleFavorite(document.getElementById('vdFavBtn'))">
                <span id="vdFavLabel">♡ Add to Favorites</span>
            </button>
        </div>
    </div>
</div>
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>
<script src="../../assets/js/navbar.js"></script>
<script src="../../assets/js/navbar-search.js?v=20261101-2"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../../assets/js/product-location-map.js?v=20261102-4"></script>
<script>
const productsData = <?php echo json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
function findProduct(id) { return productsData.find(p => p.id === id); }
function ktDistanceKm(aLat, aLng, bLat, bLng) {
    const rad = Math.PI / 180;
    const dLat = (bLat - aLat) * rad;
    const dLng = (bLng - aLng) * rad;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(aLat * rad) * Math.cos(bLat * rad) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}
function findNearbyShop(button) {
    const text = button.querySelector('.p-card-location-text');
    const item = findProduct(Number(button.dataset.itemId));
    if (!item || !text) return;
    if (!navigator.geolocation) { text.textContent = 'Location unavailable on this device'; return; }
    text.textContent = 'Finding the nearest shop…';
    navigator.geolocation.getCurrentPosition(position => {
        const shops = (item.locations?.length ? item.locations : (item.address ? [{ address: item.address, lat: item.lat, lng: item.lng }] : []))
            .filter(shop => shop.lat !== null && shop.lat !== '' && shop.lng !== null && shop.lng !== ''
                && Number.isFinite(Number(shop.lat)) && Number.isFinite(Number(shop.lng)));
        if (!shops.length) { text.textContent = 'Shop locations are not pinned yet'; return; }
        const { latitude, longitude } = position.coords;
        window.ktVisitorLocation = { lat: latitude, lng: longitude };
        shops.sort((a, b) => ktDistanceKm(latitude, longitude, Number(a.lat), Number(a.lng))
            - ktDistanceKm(latitude, longitude, Number(b.lat), Number(b.lng)));
        const nearest = shops[0];
        const km = ktDistanceKm(latitude, longitude, Number(nearest.lat), Number(nearest.lng));
        const distance = km < 1 ? `${Math.round(km * 1000)} m away` : `${km.toFixed(1)} km away`;
        text.textContent = `${nearest.store_name ? nearest.store_name + ' · ' : ''}${nearest.address || 'Nearest shop'} · ${distance}`;
        button.title = 'Based on your current location';
    }, () => { text.textContent = 'Allow location access to find the nearest shop'; }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
}
function showNearestShopInDetails(itemId, locations) {
    const mappedLocations = locations.filter(location => location.lat !== null && location.lat !== ''
        && location.lng !== null && location.lng !== ''
        && Number.isFinite(Number(location.lat)) && Number.isFinite(Number(location.lng)));
    if (!navigator.geolocation || !mappedLocations.length) return;
    const renderNearest = position => {
        const modal = document.getElementById('viewDetailsModal');
        if (modal.dataset.itemId !== String(itemId)) return;
        const { latitude, longitude } = position.coords;
        window.ktVisitorLocation = { lat: latitude, lng: longitude };
        const nearbyLocations = mappedLocations.slice();
        nearbyLocations.sort((a, b) => ktDistanceKm(latitude, longitude, Number(a.lat), Number(a.lng))
            - ktDistanceKm(latitude, longitude, Number(b.lat), Number(b.lng)));
        const nearest = nearbyLocations[0];
        const nearestKm = ktDistanceKm(latitude, longitude, Number(nearest.lat), Number(nearest.lng));
        document.getElementById('vdNearestShopName').textContent = nearest.store_name || 'Nearest shop';
        document.getElementById('vdNearestShopAddress').textContent = nearest.address || 'Address unavailable';
        document.getElementById('vdNearestShopDistance').textContent = nearestKm < 1
            ? `${Math.round(nearestKm * 1000)} m away` : `${nearestKm.toFixed(1)} km away`;
        document.getElementById('vdNearestShop').hidden = false;
        const storesList = document.getElementById('vdStoresList');
        storesList.replaceChildren();
        locations.forEach(location => {
            const line = document.createElement('li');
            line.textContent = `${location.store_name ? location.store_name + ' — ' : ''}${location.address || 'Address unavailable'}`;
            storesList.appendChild(line);
        });
        document.getElementById('vdStoresHeading').textContent = locations.length === 1 ? 'Shop location' : 'Places that sell this product';
        renderProductLocationsMap(locations);
    };
    if (window.ktVisitorLocation) {
        renderNearest({ coords: { latitude: window.ktVisitorLocation.lat, longitude: window.ktVisitorLocation.lng } });
    } else {
        navigator.geolocation.getCurrentPosition(renderNearest, () => {}, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    }
}
let productLocationsMap = null;
let productLocationsLayer = null;
function renderProductLocationsMap(locations) {
    const mapEl = document.getElementById('vdStoresMap');
    const unmappedEl = document.getElementById('vdUnmappedLocations');
    const mapped = locations.map((location, index) => ({ location, index }))
        .filter(({ location }) => location.lat !== null && location.lat !== '' && location.lng !== null && location.lng !== ''
            && Number.isFinite(Number(location.lat)) && Number.isFinite(Number(location.lng)));
    const unmapped = locations.map((location, index) => ({ location, index })).filter(({ location }) =>
        location.lat === null || location.lat === '' || location.lng === null || location.lng === ''
        || !Number.isFinite(Number(location.lat)) || !Number.isFinite(Number(location.lng)));
    mapEl.hidden = mapped.length === 0;
    unmappedEl.hidden = unmapped.length === 0;
    unmappedEl.replaceChildren();
    unmapped.forEach(({ location, index }) => {
        const line = document.createElement('p');
        line.textContent = `Location ${index + 1} needs a map pin: ${location.address || 'Address unavailable'}`;
        unmappedEl.appendChild(line);
    });
    if (!mapped.length || typeof L === 'undefined') return;
    if (!productLocationsMap) {
        productLocationsMap = L.map(mapEl, { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(productLocationsMap);
        productLocationsLayer = L.layerGroup().addTo(productLocationsMap);
    }
    productLocationsLayer.clearLayers();
    const bounds = [];
    mapped.forEach(({ location, index }) => {
        const lat = Number(location.lat);
        const lng = Number(location.lng);
        bounds.push([lat, lng]);
        window.ktProductLocationMap.addMarker(productLocationsLayer, location, index);
    });
    setTimeout(() => {
        productLocationsMap.invalidateSize();
        if (bounds.length === 1) productLocationsMap.setView(bounds[0], 16);
        else productLocationsMap.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
    }, 120);
}
/* ---------------- Live search + category filter ---------------- */
/* Filters the already-loaded productsData/cards client-side, so results
   update instantly as you type — no page reload per keystroke. Pressing
   Enter still works, it just re-applies the same filter instead of
   submitting the form (the filter is already live, so nothing changes). */
(function () {
    const searchInput = document.getElementById('productSearchInput');
    const categorySelect = document.getElementById('productCategorySelect');
    const grid = document.getElementById('productCardGrid');
    const emptyMsg = document.getElementById('productSearchEmpty');
    const form = document.getElementById('productSearchForm');
    const catLabel = document.getElementById('productCatLabel');
    const catTitle = document.getElementById('productCatTitle');
    const catDesc = document.getElementById('productCatDesc');
    if (!searchInput || !categorySelect || !grid) return;
    const CATEGORY_META = {
        'Local Food': { label: 'Local Food', desc: "Traditional dishes and delicacies rooted in Malvar's culinary heritage." },
        'Local Products': { label: 'Local Products', desc: 'Everyday goods and specialty items made by local producers in Malvar.' },
        'Crafts': { label: 'Crafts', desc: "Handmade crafts and bamboo creations from Malvar's artisans." },
    };
    const GENERAL_META = { label: 'Products', desc: 'A mix of local products, food, and crafts from Malvar.' };
    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        const category = categorySelect.value;
        let visibleCount = 0;
        const visibleCategories = new Set();
        productsData.forEach((item) => {
            const card = grid.querySelector('.p-card[data-id="' + item.id + '"]');
            if (!card) return;
            const matchesCategory = category === '' || category === 'All Categories' || item.category === category;
            const matchesQuery = query === ''
                || (item.name || '').toLowerCase().includes(query)
                || (item.category || '').toLowerCase().includes(query);
            const matches = matchesCategory && matchesQuery;
            if (matches) {
                visibleCount++;
                visibleCategories.add(item.category);
                if (card.style.display === 'none') {
                    card.style.display = '';
                    card.style.opacity = '0';
                    requestAnimationFrame(() => { card.style.opacity = '1'; });
                }
            } else {
                card.style.display = 'none';
            }
        });
        if (emptyMsg) emptyMsg.hidden = visibleCount > 0;
        // Heading reflects what's actually showing: a single category name
        // when everything visible belongs to just one (whether that's from
        // the dropdown or a search that happens to narrow it down), or a
        // general "Products" heading otherwise.
        const meta = (visibleCategories.size === 1)
            ? (CATEGORY_META[[...visibleCategories][0]] || GENERAL_META)
            : GENERAL_META;
        if (catLabel) catLabel.textContent = meta.label;
        if (catTitle) catTitle.textContent = meta.label;
        if (catDesc) catDesc.textContent = meta.desc;
    }
    searchInput.addEventListener('input', applyFilters);
    categorySelect.addEventListener('change', applyFilters);
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // already live-filtered — no reload needed
            applyFilters();
        });
    }
    applyFilters(); // respect any ?q=/&category= the page was loaded with
})();
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
    ktTrackItemView('product', id);
    document.getElementById('vdTag').textContent = item.category || '—';
    document.getElementById('vdTitle').textContent = item.name;
    document.getElementById('vdDesc').textContent = item.desc || '';
    const img = document.getElementById('vdImage');
    if (item.image) { img.src = item.image; img.style.display = ''; }
    else { img.style.display = 'none'; }
    const favBtn = document.getElementById('vdFavBtn');
    favBtn.dataset.itemId = item.id;
    favBtn.classList.toggle('is-favorited', !!item.favorited);
    favBtn.innerHTML = item.favorited ? '&#9829;' : '&#9825;';
    document.getElementById('vdFavLabel').textContent = item.favorited ? '♥ Saved to Favorites' : '♡ Add to Favorites';
    const locations = item.locations?.length ? [...item.locations] : (item.address ? [{ store_name: '', address: item.address, lat: item.lat, lng: item.lng }] : []);
    let facts = '';
    if (item.price !== null && item.price !== '') {
        facts += `<div><p class="p-fact-label">Price</p><p class="p-fact-value">₱${Number(item.price).toFixed(2)}</p></div>`;
    }
    if (locations.length) facts += `<div><p class="p-fact-label">Shop locations</p><p class="p-fact-value">${locations.length} ${locations.length === 1 ? 'shop' : 'shops'} in Malvar</p></div>`;
    document.getElementById('vdFacts').innerHTML = facts;
    const storesEl = document.getElementById('vdStores');
    document.getElementById('viewDetailsModal').dataset.itemId = String(id);
    document.getElementById('vdNearestShop').hidden = true;
    const storesList = document.getElementById('vdStoresList');
    storesList.replaceChildren();
    locations.forEach(location => {
        const line = document.createElement('li');
        line.textContent = (location.store_name ? `${location.store_name} — ` : '') + (location.address || 'Address unavailable');
        storesList.appendChild(line);
    });
    storesEl.hidden = locations.length === 0;
    document.getElementById('vdStoresHeading').textContent = locations.length === 1 ? 'Shop location' : 'Places that sell this product';
    ktReviewsOpen('product', id);
    openModalX('viewDetailsModal');
    renderProductLocationsMap(locations);
    showNearestShopInDetails(id, locations);
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
                window.location.href = '../../auth/login.php';
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
</script>
<script>document.body.dataset.loggedIn = '<?php echo isset($_SESSION['user_id']) ? '1' : '0'; ?>';</script><script src="../../assets/js/reviews.js?v=20261104-2"></script>
<script src="../../assets/js/open-popular-item.js?v=20261104-1"></script>
</body>
</html>
