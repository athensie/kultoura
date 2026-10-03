<?php
require_once __DIR__ . '/../../config/session_boot.php';
include '../../config/dbmain.php';
include '../../config/analytics.php';
include '../../config/sitecontent.php';
analytics_track($conn, 'nature');

$natureHeroPhoto = sitecontent_get_photo($conn, 'nature_hero');

// Logged for foryou.php's "Because You Explored" recommendations.
$_SESSION['history'][] = 'nature';
$_SESSION['history'] = array_slice($_SESSION['history'], -30);

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username'] ?? '');
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

// Industry zone listings come from the same `destination` table the admin
// panel (admindestinations.php) writes to, filtered to this category.
// The EXISTS subquery checks THIS user's favorites table row (not the
// destination table's own `favorited` column, which isn't user-specific).
$spots = [];
$stmt = $conn->prepare(
    "SELECT d.*,
        EXISTS (
            SELECT 1 FROM favorites f
            WHERE f.user_id = ? AND f.item_type = 'destination' AND f.item_id = d.destination_id
        ) AS is_favorited
     FROM destination d
     WHERE d.category = 'nature' AND d.status = 'active'
     ORDER BY d.created_at DESC"
);
$stmt->bind_param('i', $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $spots[] = [
        'id'          => (int) $row['destination_id'],
        'name'        => $row['destination_name'],
        'category'    => ucfirst($row['category']),
        'subcategory' => $row['subcategory'] ?? '',
        'tag'         => $row['address'],
        'desc'        => $row['description'],
        'image'       => $row['image'],
        'isFavorited' => (bool) $row['is_favorited'],
        'gmaps'       => $row['google_maps'] ?? '',
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nature – KULTOURA</title>
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link rel="stylesheet" href="../../assets/css/tourism.css">
    <link rel="stylesheet" href="../../assets/css/nature.css">
</head>
<body>

<!-- ── Navbar (shared) ── -->
<header class="navbar navbar-solid">

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../../assets/images/salakot.png" alt=""></span>Mabuhay, <?php echo $userName; ?></div>
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

    </nav>

    <?php if ($isLoggedIn): ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../../auth/logout.php" class="sign-in-btn sign-in-btn-icon-only" aria-label="Sign Out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
    <?php else: ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../../auth/login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN IN</span></a>
    <?php endif; ?>

    <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

</header>

<!-- ── Page Hero ── -->
<section class="t-hero<?php echo $natureHeroPhoto ? ' t-hero-has-photo' : ''; ?>">
    <?php if ($natureHeroPhoto): ?>
        <img class="t-hero-photo" src="<?php echo htmlspecialchars($natureHeroPhoto); ?>" alt="">
        <div class="t-hero-scrim"></div>
    <?php else: ?>
        <div class="t-hero-orb t-orb-1"></div>
        <div class="t-hero-orb t-orb-2"></div>
    <?php endif; ?>
    <div class="t-hero-inner">
        <p class="t-eyebrow">LOCAL DESTINATIONS · MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Nature</h1>
        <p class="t-page-sub">
            Parks, trails, and scenic views that showcase Malvar's natural beauty.
        </p>
    </div>
</section>

<!-- ── Nature Grid ── -->
<main class="t-main">

    <section class="p-search-row">
        <form class="p-search-bar" id="natureSearchForm" action="nature.php" method="get">
            <input type="text" name="q" id="natureSearchInput" placeholder="Search parks, river and falls…" autocomplete="off">
            <select name="category" id="natureCategorySelect" class="p-category-select">
                <option>All Categories</option>
                <option>Park</option>
                <option>River / Falls</option>
            </select>
        </form>
        <p class="p-search-empty" id="natureSearchEmpty" hidden>No nature spots match your search.</p>
    </section>

    <section class="t-category" id="nature">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label" id="natureCatLabel">Local Destinations</p>
                <h2 class="t-cat-title" id="natureCatTitle">Nature</h2>
                <p class="t-cat-desc" id="natureCatDesc">Green spaces and natural landmarks worth the trip around Malvar.</p>
            </div>
        </div>

        <?php if (empty($spots)): ?>
        <div class="p-empty-state">
            <h3>No nature spots added yet</h3>
            <p>Once the admin adds listings, they'll show up here as cards.</p>
        </div>
        <?php else: ?>
        <div class="p-card-grid" id="natureCardGrid">
            <?php foreach ($spots as $item): ?>
            <article class="p-card" data-id="<?= (int) $item['id'] ?>" data-subcategory="<?= htmlspecialchars($item['subcategory']) ?>" onclick="ktOpenViewDetails(this.querySelector('.p-btn-primary'))">
                <div class="p-card-media">
                    <?php if (!empty($item['image'])): ?><img src="<?= htmlspecialchars($item['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?>
                    <button class="p-fav-btn <?= $item['isFavorited'] ? 'is-favorited' : '' ?>" type="button"
                        data-id="<?= (int) $item['id'] ?>"
                        aria-label="Save to favorites"
                        onclick="event.stopPropagation(); ktToggleFavorite(<?= (int) $item['id'] ?>)"><?= $item['isFavorited'] ? '&#9829;' : '&#9825;' ?></button>
                </div>
                <div class="p-card-body">
                    <p class="p-card-category"><?= htmlspecialchars($item['subcategory'] ?: $item['category']) ?></p>
                    <h3 class="p-card-title"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="p-card-location">📍 <?= htmlspecialchars($item['tag']) ?></p>
                    <p class="p-card-desc"><?= htmlspecialchars($item['desc']) ?></p>
                    <div class="p-card-actions">
                        <button type="button" class="p-btn p-btn-primary"
                            data-id="<?= (int) $item['id'] ?>"
                            data-name="<?= htmlspecialchars($item['name']) ?>"
                            data-category="<?= htmlspecialchars($item['category']) ?>"
                            data-tag="<?= htmlspecialchars($item['tag']) ?>"
                            data-desc="<?= htmlspecialchars($item['desc']) ?>"
                            data-image="<?= htmlspecialchars($item['image']) ?>"
                            data-gmaps="<?= htmlspecialchars($item['gmaps']) ?>"
                            data-favorited="<?= $item['isFavorited'] ? '1' : '0' ?>"
                            onclick="event.stopPropagation(); ktOpenViewDetails(this)">View Details</button>
                        <button type="button" class="p-btn p-btn-outline"
                            data-name="<?= htmlspecialchars($item['name']) ?>"
                            data-gmaps="<?= htmlspecialchars($item['gmaps']) ?>"
                            onclick="event.stopPropagation(); ktOpenNavigate(this)">Navigate</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

</main>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<!-- ── View Details / Navigate popups (shared .p-modal-* styles in tourism.css) ── -->

<!-- VIEW DETAILS MODAL -->
<div class="p-modal-overlay" id="ktViewDetailsModal" data-item-id="" data-favorited="0" onclick="if(event.target===this) ktCloseModal('ktViewDetailsModal')">
    <div class="p-modal-card">
        <button class="p-modal-close" onclick="ktCloseModal('ktViewDetailsModal')" aria-label="Close">&times;</button>
        <div class="p-modal-media">
            <img id="ktVdImage" src="" alt="" style="display:none;">
            <button type="button" class="p-modal-fav-icon" id="ktVdFavIcon" onclick="ktToggleFavorite(document.getElementById('ktViewDetailsModal').dataset.itemId)" aria-label="Save to favorites">&#9825;</button>
        </div>
        <p class="p-modal-eyebrow" id="ktVdCategory"></p>
        <h3 class="p-modal-title" id="ktVdName"></h3>
        <p class="p-modal-desc" id="ktVdDesc"></p>
        <div class="p-modal-facts" id="ktVdFacts"></div>
        <div class="p-modal-actions">
            <button type="button" class="p-btn p-btn-primary" id="ktVdFavBtn" onclick="ktToggleFavorite(document.getElementById('ktViewDetailsModal').dataset.itemId)">
                <span id="ktVdFavLabel">♡ Add to Favorites</span>
            </button>
            <button type="button" class="p-btn p-btn-outline" id="ktVdNavigateBtn">Navigate</button>
        </div>
    </div>
</div>

<!-- NAVIGATE MODAL -->
<div class="p-modal-overlay" id="ktNavigateModal" onclick="if(event.target===this) ktCloseModal('ktNavigateModal')">
    <div class="p-modal-card p-modal-card-wide">
        <button class="p-modal-close" onclick="ktCloseModal('ktNavigateModal')" aria-label="Close">&times;</button>
        <p class="p-modal-eyebrow">Navigate to</p>
        <h3 class="p-modal-title" id="ktNavName"></h3>
        <p class="p-nav-distance" id="ktNavDistance">Locating you…</p>
        <iframe class="p-map-frame" id="ktNavFrame" src="" loading="lazy" allowfullscreen></iframe>
        <div class="p-modal-actions">
            <a href="#" target="_blank" rel="noopener" id="ktNavDirectLink" class="p-btn p-btn-primary" style="display:none;">Open Directions in Google Maps</a>
        </div>
    </div>
</div>
<div class="p-toast" id="ktFavToast"></div>
<script>
function ktShowModal(id) { document.getElementById(id).classList.add('open'); }
function ktCloseModal(id) { document.getElementById(id).classList.remove('open'); }

/* ---------------- Live search + subcategory filter ---------------- */
/* Same approach as products.php: filters the already-loaded cards
   client-side (by name and subcategory — not description), updating
   instantly as you type. No page reload per keystroke; Enter is
   harmless since the filter's already live. */
(function () {
    const searchInput = document.getElementById('natureSearchInput');
    const categorySelect = document.getElementById('natureCategorySelect');
    const grid = document.getElementById('natureCardGrid');
    const emptyMsg = document.getElementById('natureSearchEmpty');
    const form = document.getElementById('natureSearchForm');
    const catLabel = document.getElementById('natureCatLabel');
    const catTitle = document.getElementById('natureCatTitle');
    const catDesc = document.getElementById('natureCatDesc');
    if (!searchInput || !categorySelect || !grid) return;

    const SUBCATEGORY_META = {
        'Park': { label: 'Park', desc: 'Open green spaces and recreational parks around Malvar.' },
        'River / Falls': { label: 'River / Falls', desc: 'Rivers, waterfalls, and natural water spots to explore.' },
    };
    const GENERAL_META = { label: 'Nature', desc: 'Green spaces and natural landmarks worth the trip around Malvar.' };

    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        const subcategory = categorySelect.value;
        let visibleCount = 0;
        const visibleSubcats = new Set();

        grid.querySelectorAll('.p-card[data-id]').forEach((card) => {
            const name = (card.querySelector('.p-card-title')?.textContent || '').toLowerCase();
            const cardSubcategory = card.dataset.subcategory || '';

            const matchesCategory = subcategory === '' || subcategory === 'All Categories' || cardSubcategory === subcategory;
            const matchesQuery = query === ''
                || name.includes(query)
                || cardSubcategory.toLowerCase().includes(query);
            const matches = matchesCategory && matchesQuery;

            if (matches) {
                visibleCount++;
                visibleSubcats.add(cardSubcategory);
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

        // Heading reflects what's actually showing: a single subcategory
        // name when everything visible shares one (from the dropdown or
        // a search that happens to narrow it down), general "Nature"
        // otherwise (including spots with no subcategory set at all).
        const meta = (visibleSubcats.size === 1)
            ? (SUBCATEGORY_META[[...visibleSubcats][0]] || GENERAL_META)
            : GENERAL_META;
        if (catLabel) catLabel.textContent = meta.label;
        if (catTitle) catTitle.textContent = meta.label;
        if (catDesc) catDesc.textContent = meta.desc;
    }

    searchInput.addEventListener('input', applyFilters);
    categorySelect.addEventListener('change', applyFilters);
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            applyFilters();
        });
    }

    applyFilters();
})();

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

function ktOpenViewDetails(btn) {
    ktTrackItemView('nature', btn.dataset.id);
    const image = btn.dataset.image || '';
    const imgEl = document.getElementById('ktVdImage');
    imgEl.src = image;
    imgEl.style.display = image ? 'block' : 'none';
    document.getElementById('ktVdCategory').textContent = btn.dataset.category || '';
    document.getElementById('ktVdName').textContent = btn.dataset.name || '';
    document.getElementById('ktVdDesc').textContent = btn.dataset.desc || 'No description available yet.';

    let facts = '';
    if (btn.dataset.tag) facts += `<div><p class="p-fact-label">Address</p><p class="p-fact-value">${btn.dataset.tag}</p></div>`;
    document.getElementById('ktVdFacts').innerHTML = facts;

    // Wire up the modal's own "Add to Favorites" button to this item.
    const modal = document.getElementById('ktViewDetailsModal');
    const itemId = btn.dataset.id || '';
    const isFav = btn.dataset.favorited === '1';
    modal.dataset.itemId = itemId;
    modal.dataset.favorited = isFav ? '1' : '0';

    const favIcon = document.getElementById('ktVdFavIcon');
    favIcon.classList.toggle('is-favorited', isFav);
    favIcon.innerHTML = isFav ? '&#9829;' : '&#9825;';
    document.getElementById('ktVdFavLabel').textContent = isFav ? '♥ Added to Favorites' : '♡ Add to Favorites';

    document.getElementById('ktVdNavigateBtn').onclick = function () {
        ktCloseModal('ktViewDetailsModal');
        setTimeout(() => ktOpenNavigate(btn), 200);
    };

    ktShowModal('ktViewDetailsModal');
}

/* ---------- FAVORITES ---------- */
let ktFavToastTimer = null;
function ktShowFavToast(msg) {
    const toast = document.getElementById('ktFavToast');
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(ktFavToastTimer);
    ktFavToastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}

// Keeps the card's heart button AND the modal's "Add to Favorites"
// button in sync, since both can represent the same destination.
function ktSetFavState(itemId, isFav) {
    document.querySelectorAll('.p-fav-btn[data-id="' + itemId + '"]').forEach(function (b) {
        b.classList.toggle('is-favorited', isFav);
        b.innerHTML = isFav ? '&#9829;' : '&#9825;';
    });

    const modal = document.getElementById('ktViewDetailsModal');
    if (modal && modal.dataset.itemId === String(itemId)) {
        modal.dataset.favorited = isFav ? '1' : '0';
        const favIcon = document.getElementById('ktVdFavIcon');
        if (favIcon) {
            favIcon.classList.toggle('is-favorited', isFav);
            favIcon.innerHTML = isFav ? '&#9829;' : '&#9825;';
        }
        const favLabel = document.getElementById('ktVdFavLabel');
        if (favLabel) favLabel.textContent = isFav ? '♥ Added to Favorites' : '♡ Add to Favorites';
    }

    // Also keep the View Details button's own data-favorited in sync,
    // so reopening the modal for this card shows the right state.
    document.querySelectorAll('.p-btn-primary[data-id="' + itemId + '"]').forEach(function (b) {
        b.dataset.favorited = isFav ? '1' : '0';
    });
}

async function ktToggleFavorite(itemId) {
    if (!itemId) return;
    try {
        const res = await fetch('../favorites.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'toggle', item_type: 'destination', item_id: itemId })
        });
        const data = await res.json();

        if (data.needsLogin) {
            ktShowFavToast(data.message || 'Please sign in to save favorites.');
            return;
        }
        if (data.success) {
            ktSetFavState(itemId, data.favorited);
            ktShowFavToast(data.favorited ? 'Added to favorites' : 'Removed from favorites');
        } else {
            ktShowFavToast(data.message || 'Something went wrong.');
        }
    } catch (err) {
        console.error('Favorite toggle failed:', err);
        ktShowFavToast('Something went wrong. Please try again.');
    }
}

function ktHaversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function ktOpenNavigate(btn) {
    const name = btn.dataset.name || 'this destination';
    const gmaps = btn.dataset.gmaps || '';
    document.getElementById('ktNavName').textContent = name;

    const distanceEl = document.getElementById('ktNavDistance');
    const frame = document.getElementById('ktNavFrame');
    const directLink = document.getElementById('ktNavDirectLink');

    if (!gmaps.includes(',')) {
        distanceEl.textContent = "This destination's location hasn't been mapped yet.";
        frame.src = '';
        directLink.style.display = 'none';
        ktShowModal('ktNavigateModal');
        return;
    }

    const [destLat, destLng] = gmaps.split(',').map(Number);

    frame.src = 'https://www.google.com/maps?q=' + destLat + ',' + destLng + '&z=15&output=embed';
    directLink.href = 'https://www.google.com/maps/dir/?api=1&destination=' + destLat + ',' + destLng;
    directLink.style.display = 'inline-block';
    distanceEl.textContent = 'Locating you…';
    ktShowModal('ktNavigateModal');

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            const userLat = pos.coords.latitude;
            const userLng = pos.coords.longitude;
            const dist = ktHaversineKm(userLat, userLng, destLat, destLng);
            distanceEl.textContent = dist.toFixed(1) + ' km away from your current location';
            frame.src = 'https://www.google.com/maps?saddr=' + userLat + ',' + userLng + '&daddr=' + destLat + ',' + destLng + '&output=embed';
            directLink.href = 'https://www.google.com/maps/dir/?api=1&origin=' + userLat + ',' + userLng + '&destination=' + destLat + ',' + destLng;
        }, function () {
            distanceEl.textContent = 'Enable location access in your browser to see distance and directions.';
        });
    } else {
        distanceEl.textContent = 'Geolocation is not supported by your browser.';
    }
}
</script>

<script src="../../assets/js/navbar.js"></script>

</body>
</html>