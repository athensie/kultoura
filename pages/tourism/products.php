<?php
require_once __DIR__ . '/../../config/session_boot.php';
require_once '../../config/dbmain.php';
require_once __DIR__ . '/../../config/maintenance.php';
kt_maintenance_gate($conn);
require_once '../../config/analytics.php';
include '../../config/sitecontent.php';
analytics_track($conn, 'products');

$productsHeroPhoto = sitecontent_get_photo($conn, 'products_hero');

// Logged for foryou.php's "Because You Explored" recommendations.
$_SESSION['history'][] = 'product';
$_SESSION['history'] = array_slice($_SESSION['history'], -30);

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? '');
$userId     = $isLoggedIn ? (int) $_SESSION['user_id'] : 0;

/*
 |--------------------------------------------------------------------
 | PRODUCTS — populated from listings the admin added/approved in
 | adminfoodanddining.php (only 'active' ones are shown here).
 |--------------------------------------------------------------------
 */
$categoryChoices = ['Local Food', 'Local Products', 'Crafts'];
$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

// $q / $category below only pre-fill the search box and category select
// (so a shared link like ?q=rice still lands pre-filtered visually) — the
// actual filtering happens client-side in JS against the full catalog,
// so clearing the search box or switching categories can reveal items
// without a round trip to the server.
$sql  = "SELECT product_id, product_name, category, description, price, location, latitude, longitude, image
         FROM products WHERE status = 'active' ORDER BY product_name ASC";
$rows = [];
$res  = $conn->query($sql);
if ($res) {
    $rows = $res->fetch_all(MYSQLI_ASSOC);
}

// Which of these the logged-in user has already favorited.
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
        'price'     => $r['price'],
        'address'   => $r['location'],
        'lat'       => $r['latitude'],
        'lng'       => $r['longitude'],
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
    <link rel="stylesheet" href="../../assets/css/tourism.css">
    <link rel="stylesheet" href="../../assets/css/product.css">
    <!-- View Details / Navigate modal styles are shared: .p-modal-*, .p-nav-* in tourism.css -->
</head>
<body>

<!-- ── Navbar (shared) ── -->
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
                    <a href="../favorites.php">Favorites</a>
                    <a href="../mostpopular.php">Most Popular</a>
                    <a href="../about.php">About</a>
                </div>
            </div>
        </div>

            <?php if ($isLoggedIn): ?><a href="../../auth/logout.php" class="nav-item nav-signout-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span><span>SIGN OUT</span></a><?php else: ?><a href="../../auth/login.php" class="nav-item nav-signin-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg></span><span>SIGN IN</span></a><?php endif; ?>
    </nav>


    <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

</header>

<!-- ── Page Hero ── -->
<section class="t-hero<?php echo $productsHeroPhoto ? ' t-hero-has-photo' : ''; ?>">
    <?php if ($productsHeroPhoto): ?>
        <img class="t-hero-photo" src="<?php echo htmlspecialchars($productsHeroPhoto); ?>" alt="">
        <div class="t-hero-scrim"></div>
    <?php else: ?>
        <div class="t-hero-orb t-orb-1"></div>
        <div class="t-hero-orb t-orb-2"></div>
    <?php endif; ?>
    <div class="t-hero-inner">
        <p class="t-eyebrow">FOOD · MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Products</h1>
        <p class="t-page-sub">
            Locally made goods, native delicacies, and fresh produce from Malvar's markets.
        </p>
    </div>
</section>

<!-- ── Products Grid ── -->
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
                            onclick="event.stopPropagation(); toggleFavorite(this)" aria-label="Save to favorites">&#9825;</button>
                    <span class="p-tag-pill"><?= htmlspecialchars($item['tag']) ?></span>
                </div>
                <div class="p-card-body">
                    <p class="p-card-category"><?= htmlspecialchars($item['category']) ?></p>
                    <h3 class="p-card-title"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="p-card-desc"><?= htmlspecialchars($item['desc']) ?></p>
                    <?php if ($item['price'] !== null && $item['price'] !== ''): ?>
                        <p class="p-card-price">₱<?= htmlspecialchars(number_format((float) $item['price'], 2)) ?></p>
                    <?php endif; ?>
                    <div class="p-card-actions">
                        <button type="button" class="p-btn p-btn-primary" onclick="event.stopPropagation(); openViewDetails(<?= (int) $item['id'] ?>)">View Details</button>
                        <button type="button" class="p-btn p-btn-outline" onclick="event.stopPropagation(); openNavigate(<?= (int) $item['id'] ?>)">Navigate</button>
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
            <button class="p-modal-fav-icon" id="vdFavBtn" type="button" data-item-type="product" onclick="toggleFavorite(this)" aria-label="Add to favorites">&#9825;</button>
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
<script>
const productsData = <?php echo json_encode($products); ?>;

function findProduct(id) { return productsData.find(p => p.id === id); }

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
    document.getElementById('vdFavLabel').textContent = item.favorited ? '♥ Saved to Favorites' : '♡ Add to Favorites';

    let facts = '';
    if (item.price !== null && item.price !== '') {
        facts += `<div><p class="p-fact-label">Price</p><p class="p-fact-value">₱${Number(item.price).toFixed(2)}</p></div>`;
    }
    facts += `<div><p class="p-fact-label">Location</p><p class="p-fact-value">${item.address || '—'}</p></div>`;
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
                window.location.href = '../../auth/login.php';
                return;
            }
            if (data.success) {
                document.querySelectorAll(`[data-item-id="${itemId}"][data-item-type="${itemType}"]`).forEach(el => {
                    el.classList.toggle('is-favorited', data.favorited);
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
