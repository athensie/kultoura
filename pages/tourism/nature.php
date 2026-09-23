<?php
session_start();
include '../../config/dbmain.php';
include '../../config/analytics.php';
analytics_track($conn, 'nature');

// Logged for foryou.php's "Because You Explored" recommendations.
$_SESSION['history'][] = 'nature';
$_SESSION['history'] = array_slice($_SESSION['history'], -30);

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

// Industry zone listings come from the same `destination` table the admin
// panel (admindestinations.php) writes to, filtered to this category.
// The EXISTS subquery checks THIS user's favorites table row (not the
// destination table's own `favorited` column, which isn't user-specific).
$zones = [];
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
        <div class="user-greeting-left"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>Mabuhay, <?php echo $userName; ?></div>
    <?php else: ?>
        <div class="user-greeting-left" style="letter-spacing:2px;font-size:15px;font-weight:900;">
            <span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>
            <a href="index.php" style="text-decoration:none;color:#C8A96E;">KUL<span style="color:#9fb88a">TOURA</span></a>
        </div>
    <?php endif; ?>

    <nav class="nav-links">
        <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
        <div class="dropdown">
            <a href="../tourism.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>TOURISM ▾</span></a>
            <div class="mega-menu">
                <div class="mega-column">
                    <h4>Food</h4>
                    <a href="products.php">Products</a>
                    <a href="restaurants.php">Restaurants</a>
                </div>
                <div class="mega-column">
                    <h4>Local Destinations</h4>
                    <a href="nature.php">Nature</a>
                    <a href="industry.php">Industry Zone</a>
                    <a href="resort.php">Resort</a>
                </div>
                <div class="mega-column">
                    <h4>Others</h4>
                    <a href="fiestas.php">Fiestas</a>
                    <a href="people.php">People of Malvar</a>
                </div>
            </div>
        </div>
        <a href="../foryou.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/></svg></span><span>FOR YOU</span></a>
        <a href="../traveldiary.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M16 4v3h3"/><path d="M8 10h8M8 14h8M8 18h5"/></svg></span><span>TRAVEL DIARY</span></a>
        <a href="../favorites.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.5-9.5-9C.5 7 2 3.5 5.5 3.5c2 0 3.5 1 4.5 2.5 1-1.5 2.5-2.5 4.5-2.5C18 3.5 19.5 7 19.5 11 17 15.5 12 20 12 20z"/></svg></span><span>FAVORITES</span></a>
        <a href="../mostpopular.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4 0 6-3 6-6.5 0-2.5-1.5-4-2.5-5.5.5 2-1 3-2 2 0-2.5-1.5-4-3-6-.5 3-3 4.5-3 8 0 1-1 1.5-2 1-.5 3 2 7 6.5 7z"/></svg></span><span>MOST POPULAR</span></a>
        <a href="../about.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.5v5"/><circle cx="12" cy="7.8" r="0.9" fill="currentColor" stroke="none"/></svg></span><span>ABOUT</span></a>

    </nav>

    <?php if ($isLoggedIn): ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../../auth/logout.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
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
        <form class="p-search-bar" action="nature.php" method="get">
            <input type="text" name="q" placeholder="Search parks, trails, viewpoints…">
            <select name="category" class="p-category-select">
                <option>All Categories</option>
                <option>Park</option>
                <option>Trail</option>
                <option>Viewpoint</option>
                <option>River / Falls</option>
            </select>
        </form>
    </section>

    <section class="t-category" id="nature">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Local Destinations</p>
                <h2 class="t-cat-title">Nature</h2>
                <p class="t-cat-desc">Green spaces and natural landmarks worth the trip around Malvar.</p>
            </div>
        </div>

        <?php if (empty($spots)): ?>
        <div class="p-empty-state">
            <h3>No nature spots added yet</h3>
            <p>Once the admin adds listings, they'll show up here as cards.</p>
        </div>
        <?php else: ?>
        <div class="p-card-grid">
            <?php foreach ($spots as $item): ?>
            <article class="p-card">
                <div class="p-card-media">
                    <?php if (!empty($item['image'])): ?><img src="<?= htmlspecialchars($item['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?>
                    <button class="p-fav-btn <?= $item['isFavorited'] ? 'is-favorited' : '' ?>" type="button"
                        data-id="<?= (int) $item['id'] ?>"
                        aria-label="Save to favorites"
                        onclick="ktToggleFavorite(<?= (int) $item['id'] ?>)"><?= $item['isFavorited'] ? '&#9829;' : '&#9825;' ?></button>
                </div>
                <div class="p-card-body">
                    <p class="p-card-category"><?= htmlspecialchars($item['category']) ?></p>
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
                            onclick="ktOpenViewDetails(this)">View Details</button>
                        <button type="button" class="p-btn p-btn-outline"
                            data-name="<?= htmlspecialchars($item['name']) ?>"
                            data-gmaps="<?= htmlspecialchars($item['gmaps']) ?>"
                            onclick="ktOpenNavigate(this)">Navigate</button>
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
<script src="index.js"></script>

</body>
</html>