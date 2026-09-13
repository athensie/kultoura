<?php
session_start();
include '../../config/dbmain.php';
include '../../config/analytics.php';
analytics_track($conn, 'fiestas');

// Logged for foryou.php's "Because You Explored" recommendations.
// Skipped on the ajax_calendar ping so flipping through months doesn't
// flood the history with repeat 'fiesta' entries.
if (!isset($_GET['ajax_calendar'])) {
    $_SESSION['history'][] = 'fiesta';
    $_SESSION['history'] = array_slice($_SESSION['history'], -30);
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

// Fiestas/Events are added by the admin via admineventandfiesta.php.
// Only upcoming items (today onward), soonest first.
$fiestas = [];
if ($result = $conn->query("SELECT * FROM fiestas WHERE celebration_date >= CURDATE() ORDER BY celebration_date ASC")) {
    $fiestas = $result->fetch_all(MYSQLI_ASSOC);
}

// Which of these fiestas has the current user already favorited?
// (favorites table: user_id, item_type, item_id — shared across the whole site)
$favoritedFiestaIds = [];
if ($isLoggedIn) {
    $favUserId = (int) $_SESSION['user_id'];
    if ($favStmt = $conn->prepare("SELECT item_id FROM favorites WHERE user_id = ? AND item_type = 'fiesta'")) {
        $favStmt->bind_param('i', $favUserId);
        $favStmt->execute();
        $favRows = $favStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $favStmt->close();
        $favoritedFiestaIds = array_map(fn($r) => (string) $r['item_id'], $favRows);
    }
}

// ── Small calendar (left side) ──
// Supports ?cal_month=YYYY-MM navigation; defaults to current month.
$calMonthParam = $_GET['cal_month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $calMonthParam)) {
    $calMonthParam = date('Y-m');
}
$calTimestamp   = strtotime($calMonthParam . '-01');
$calMonthLabel  = date('F Y', $calTimestamp);
$calFirstDow    = (int) date('w', $calTimestamp); // 0 = Sunday
$calDaysInMonth = (int) date('t', $calTimestamp);
$today          = date('Y-m-d');
$prevMonth      = date('Y-m', strtotime('-1 month', $calTimestamp));
$nextMonth      = date('Y-m', strtotime('+1 month', $calTimestamp));

// Map day-of-month => event(s) for the month currently being viewed.
$calEventsByDay = [];
foreach ($fiestas as $item) {
    if (empty($item['celebration_date'])) continue;
    if (date('Y-m', strtotime($item['celebration_date'])) === $calMonthParam) {
        $dayNum = (int) date('j', strtotime($item['celebration_date']));
        $calEventsByDay[$dayNum][] = $item;
    }
}

function renderCalendarHtml($calMonthLabel, $calFirstDow, $calDaysInMonth, $today, $calMonthParam, $prevMonth, $nextMonth, $calEventsByDay) {
    ob_start();
    ?>
    <div class="p-calendar-header">
        <button type="button" class="p-calendar-nav" onclick="loadCalendarMonth('<?= htmlspecialchars($prevMonth) ?>')" aria-label="Previous month">&#8249;</button>
        <span class="p-calendar-month"><?= htmlspecialchars($calMonthLabel) ?></span>
        <button type="button" class="p-calendar-nav" onclick="loadCalendarMonth('<?= htmlspecialchars($nextMonth) ?>')" aria-label="Next month">&#8250;</button>
    </div>
    <div class="p-calendar-grid">
        <?php foreach (['S','M','T','W','T','F','S'] as $wd): ?>
            <span class="p-calendar-weekday"><?= $wd ?></span>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $calFirstDow; $i++): ?>
            <span class="p-calendar-day p-calendar-blank"></span>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $calDaysInMonth; $d++):
            $cellDate  = sprintf('%s-%02d', $calMonthParam, $d);
            $hasEvent  = isset($calEventsByDay[$d]);
            $isToday   = ($cellDate === $today);
            $classes   = 'p-calendar-day';
            if ($hasEvent) $classes .= ' has-event';
            if ($isToday)  $classes .= ' is-today';
            $titleAttr = $hasEvent
                ? ' title="' . htmlspecialchars(implode(', ', array_map(fn($e) => $e['fiesta_name'], $calEventsByDay[$d]))) . '"'
                : '';
        ?>
            <span class="<?= $classes ?>"<?= $titleAttr ?>><?= $d ?></span>
        <?php endfor; ?>
    </div>
    <div class="p-calendar-legend">
        <span class="p-calendar-dot"></span> Has a fiesta / event
    </div>
    <?php
    return ob_get_clean();
}

// AJAX: month navigation swaps just this markup in via fetch(), no full reload.
if (isset($_GET['ajax_calendar'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo renderCalendarHtml($calMonthLabel, $calFirstDow, $calDaysInMonth, $today, $calMonthParam, $prevMonth, $nextMonth, $calEventsByDay);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiestas – KULTOURA</title>
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link rel="stylesheet" href="../../assets/css/tourism.css">
    <link rel="stylesheet" href="../../assets/css/fiestas.css">
</head>
<body>

<!-- ── Navbar (shared) ── -->
<header class="navbar navbar-solid">

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>Hi, <?php echo $userName; ?></div>
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

</header>

<!-- ── Page Hero ── -->
<section class="t-hero">
    <div class="t-hero-orb t-orb-1"></div>
    <div class="t-hero-orb t-orb-2"></div>
    <div class="t-hero-inner">
        <p class="t-eyebrow">CULTURE · MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Fiestas</h1>
        <p class="t-page-sub">
            Barangay fiestas, patron saint celebrations, and the vibrant street culture of Malvar.
        </p>
    </div>
</section>

<!-- ── Fiestas Grid ── -->
<main class="t-main">

    <div class="p-page-layout">

        <!-- ── Calendar column ── -->
        <aside class="p-calendar-col">
            <div class="p-calendar-wrap" id="pCalendarWrap">
                <?= renderCalendarHtml($calMonthLabel, $calFirstDow, $calDaysInMonth, $today, $calMonthParam, $prevMonth, $nextMonth, $calEventsByDay) ?>
            </div>
        </aside>

        <!-- ── Content column ── -->
        <div class="p-content-col">

            <section class="p-search-row">
                <form class="p-search-bar" action="fiestas.php" method="get">
                    <input type="text" name="q" placeholder="Search fiestas, barangays, dates…">
                    <select name="category" class="p-category-select">
                        <option>All Categories</option>
                        <option>Fiesta</option>
                        <option>Event</option>
                    </select>
                </form>
            </section>

            <section class="t-category" id="fiestas">
                <div class="t-category-header">
                    <div>
                        <p class="t-cat-label">Culture</p>
                        <h2 class="t-cat-title">Fiestas</h2>
                        <p class="t-cat-desc">Upcoming celebrations that bring Malvar's barangays together, soonest first.</p>
                    </div>
                </div>

                <?php if (empty($fiestas)): ?>
                <div class="p-empty-state">
                    <h3>No fiestas added yet</h3>
                    <p>Once the admin adds listings, they'll show up here as cards.</p>
                </div>
                <?php else: ?>
                <div class="p-card-grid">
                    <?php foreach ($fiestas as $item):
                        $fid   = (int) $item['fiesta_id'];
                        $isFav = in_array((string) $fid, $favoritedFiestaIds, true);
                    ?>
                    <article class="p-card">
                        <div class="p-card-body">
                            <div class="p-card-top-row">
                                <?php if (!empty($item['type'])): ?><span class="p-tag-pill"><?= htmlspecialchars($item['type']) ?></span><?php else: ?><span></span><?php endif; ?>
                                <button class="p-fav-btn<?= $isFav ? ' is-favorited' : '' ?>" type="button"
                                        data-fiesta-id="<?= $fid ?>"
                                        onclick="toggleFavoriteRequest(<?= $fid ?>)"
                                        aria-label="Save to favorites"><?= $isFav ? '&#9829;' : '&#9825;' ?></button>
                            </div>
                            <?php if (!empty($item['celebration_date'])): ?><p class="p-card-category"><?= htmlspecialchars(date('F j, Y', strtotime($item['celebration_date']))) ?></p><?php endif; ?>
                            <h3 class="p-card-title"><?= htmlspecialchars($item['fiesta_name']) ?></h3>
                            <p class="p-card-desc"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                            <div class="p-card-actions">
                                <button type="button" class="p-btn p-btn-primary" onclick="openViewDetails(<?= $fid ?>)">View Details</button>
                                <button type="button" class="p-btn p-btn-outline" onclick="openNavigate(<?= $fid ?>)">Navigate</button>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

        </div>

    </div>

</main>

<!-- ── View Details Modal ── -->
<div class="p-modal-overlay" id="pViewModal" onclick="if(event.target===this) closePModal('pViewModal')">
    <div class="p-modal-card">
        <button class="p-modal-close" onclick="closePModal('pViewModal')" aria-label="Close">&times;</button>
        <div class="p-modal-media" id="pvMedia">
            <button type="button" class="p-modal-fav-icon" id="pvFavIcon" onclick="toggleFavoriteFromModal()" aria-label="Save to favorites">&#9825;</button>
        </div>
        <p class="p-modal-eyebrow" id="pvType">Fiesta</p>
        <h3 class="p-modal-title" id="pvName">—</h3>
        <p class="p-modal-desc" id="pvDesc">—</p>
        <div class="p-modal-facts" id="pvFacts"></div>
        <div class="p-modal-actions">
            <button type="button" class="p-btn p-btn-primary" id="pvFavBtn" onclick="toggleFavoriteFromModal()">
                <span id="pvFavLabel">&#9825; Add to Favorites</span>
            </button>
            <button type="button" class="p-btn p-btn-outline" onclick="openNavigateFromView()">Navigate</button>
        </div>
    </div>
</div>

<!-- ── Navigate Modal ── -->
<div class="p-modal-overlay" id="pNavModal" onclick="if(event.target===this) closePModal('pNavModal')">
    <div class="p-modal-card p-modal-card-wide">
        <button class="p-modal-close" onclick="closePModal('pNavModal')" aria-label="Close">&times;</button>
        <p class="p-modal-eyebrow">Navigate to</p>
        <h3 class="p-modal-title" id="pnName">—</h3>
        <p class="p-nav-distance" id="pnDistance">Getting your location…</p>
        <iframe class="p-map-frame" id="pnFrame" src="" loading="lazy" allowfullscreen></iframe>
        <div class="p-modal-actions">
            <a href="#" target="_blank" rel="noopener" id="pnDirectLink" class="p-btn p-btn-primary" style="display:none;">Open Directions in Google Maps</a>
        </div>
    </div>
</div>

<!-- ── Toast ── -->
<div class="p-toast" id="pToast"></div>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas ·
</footer>

<script src="index.js"></script>

<script>
// Data for every fiesta currently on the page (mirrors the eventsData
// pattern used in admineventandfiesta.php) so modals don't need extra
// round trips to the server.
const fiestasData  = <?php echo json_encode($fiestas); ?>;
const isLoggedIn    = <?php echo json_encode($isLoggedIn); ?>;
const favoritedIds  = new Set(<?php echo json_encode($favoritedFiestaIds); ?>);

function findFiesta(id) {
    return fiestasData.find(f => String(f.fiesta_id) === String(id));
}

function openPModal(id) { document.getElementById(id).classList.add('open'); }
function closePModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Calendar month navigation (no page reload) ──
async function loadCalendarMonth(month) {
    const wrap = document.getElementById('pCalendarWrap');
    try {
        const res = await fetch('fiestas.php?ajax_calendar=1&cal_month=' + encodeURIComponent(month));
        const html = await res.text();
        wrap.innerHTML = html;
        history.replaceState(null, '', '?cal_month=' + encodeURIComponent(month));
    } catch (err) {
        console.error('Calendar load failed:', err);
    }
}

// ── Favorites ──
function isFavorited(id) { return favoritedIds.has(String(id)); }

function updateFavButtonState(btn, fav) {
    btn.classList.toggle('is-favorited', fav);
    btn.innerHTML = fav ? '&#9829;' : '&#9825;';
}

function refreshFavButtons(id) {
    document.querySelectorAll('[data-fiesta-id="' + id + '"]').forEach(btn => {
        updateFavButtonState(btn, isFavorited(id));
    });
    if (document.getElementById('pViewModal').dataset.fiestaId === String(id)) {
        const label = document.getElementById('pvFavLabel');
        if (label) label.textContent = isFavorited(id) ? '♥ Saved to Favorites' : '♡ Add to Favorites';
    }
}

async function toggleFavoriteRequest(fiestaId) {
    try {
        const res = await fetch('../favorites.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'toggle', item_type: 'fiesta', item_id: fiestaId })
        });
        const data = await res.json();

        if (data.needsLogin) {
            showPToast(data.message || 'Please sign in to save favorites.');
            return;
        }
        if (data.success) {
            if (data.favorited) favoritedIds.add(String(fiestaId));
            else favoritedIds.delete(String(fiestaId));
            refreshFavButtons(fiestaId);
            showPToast(data.favorited ? 'Added to favorites' : 'Removed from favorites');
        }
    } catch (err) {
        console.error('Favorite toggle failed:', err);
        showPToast('Something went wrong. Please try again.');
    }
}

function toggleFavoriteFromModal() {
    const id = document.getElementById('pViewModal').dataset.fiestaId;
    if (id) toggleFavoriteRequest(id);
}

// ── Per-item view tracking (fire-and-forget) ──
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

// ── View Details ──
function openViewDetails(id) {
    const f = findFiesta(id);
    if (!f) return;

    ktTrackItemView('fiesta', id);

    const media = document.getElementById('pvMedia');
    media.style.backgroundImage = f.image ? `url('${f.image}')` : '';

    document.getElementById('pvType').textContent = f.type || 'Fiesta';
    document.getElementById('pvName').textContent = f.fiesta_name;
    document.getElementById('pvDesc').textContent = f.description || 'No description available yet.';

    const dateLabel = f.celebration_date
        ? new Date(f.celebration_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        : '';
    let facts = '';
    if (dateLabel) facts += `<div><p class="p-fact-label">Date</p><p class="p-fact-value">${dateLabel}</p></div>`;
    if (f.location) facts += `<div><p class="p-fact-label">Location</p><p class="p-fact-value">${f.location}</p></div>`;
    document.getElementById('pvFacts').innerHTML = facts;

    const fav = isFavorited(f.fiesta_id);
    const favIcon = document.getElementById('pvFavIcon');
    favIcon.dataset.fiestaId = f.fiesta_id;
    updateFavButtonState(favIcon, fav);
    document.getElementById('pvFavLabel').textContent = fav ? '♥ Saved to Favorites' : '♡ Add to Favorites';

    document.getElementById('pViewModal').dataset.fiestaId = f.fiesta_id;
    openPModal('pViewModal');
}

function openNavigateFromView() {
    const id = document.getElementById('pViewModal').dataset.fiestaId;
    closePModal('pViewModal');
    setTimeout(() => openNavigate(id), 200);
}

// ── Navigate (Google Maps embed) ──
function haversineDistanceKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function openNavigate(id) {
    const f = findFiesta(id);
    if (!f) return;

    document.getElementById('pnName').textContent = f.fiesta_name;
    const distEl = document.getElementById('pnDistance');
    const frame = document.getElementById('pnFrame');
    const directLink = document.getElementById('pnDirectLink');

    const hasCoords = f.latitude !== null && f.latitude !== '' && f.longitude !== null && f.longitude !== '';

    openPModal('pNavModal');

    if (!hasCoords) {
        distEl.textContent = 'This location has no map pin yet — the admin needs to set it when adding/editing the event.';
        frame.src = '';
        directLink.style.display = 'none';
        return;
    }

    const destLat = parseFloat(f.latitude);
    const destLng = parseFloat(f.longitude);

    frame.src = 'https://www.google.com/maps?q=' + destLat + ',' + destLng + '&z=15&output=embed';
    directLink.href = 'https://www.google.com/maps/dir/?api=1&destination=' + destLat + ',' + destLng;
    directLink.style.display = 'inline-block';
    distEl.textContent = 'Locating you…';

    if (!navigator.geolocation) {
        distEl.textContent = "Your browser doesn't support location — showing the event location only.";
        return;
    }

    navigator.geolocation.getCurrentPosition(function (pos) {
        const userLat = pos.coords.latitude;
        const userLng = pos.coords.longitude;
        const distanceKm = haversineDistanceKm(userLat, userLng, destLat, destLng);
        distEl.textContent = distanceKm.toFixed(1) + ' km away from your current location';
        frame.src = 'https://www.google.com/maps?saddr=' + userLat + ',' + userLng + '&daddr=' + destLat + ',' + destLng + '&output=embed';
        directLink.href = 'https://www.google.com/maps/dir/?api=1&origin=' + userLat + ',' + userLng + '&destination=' + destLat + ',' + destLng;
    }, function (err) {
        distEl.textContent = "Couldn't get your location (" + (err.message || 'permission denied') + ') — showing the event location only.';
    }, { enableHighAccuracy: true, timeout: 8000 });
}

// ── Toast ──
let _pToastTimer;
function showPToast(msg) {
    const t = document.getElementById('pToast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(_pToastTimer);
    _pToastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}
</script>

</body>
</html>