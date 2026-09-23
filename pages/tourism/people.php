<?php
session_start();
include '../../config/dbmain.php';
include '../../config/analytics.php';
analytics_track($conn, 'people');

// Logged for foryou.php's "Because You Explored" recommendations.
$_SESSION['history'][] = 'person';
$_SESSION['history'] = array_slice($_SESSION['history'], -30);

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

// People profiles are added by the admin via adminpeople.php.
$people = [];
if ($result = $conn->query("SELECT * FROM people ORDER BY fullname ASC")) {
    $people = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>People of Malvar – KULTOURA</title>
    <link rel="stylesheet" href="../../assets/css/index.css">
    <link rel="stylesheet" href="../../assets/css/tourism.css">
    <link rel="stylesheet" href="../../assets/css/people.css">
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
        <p class="t-eyebrow">CULTURE · MALVAR, BATANGAS</p>
        <h1 class="t-page-title">People of Malvar</h1>
        <p class="t-page-sub">
            Artists, leaders, farmers, and community pillars who define the Malvareño spirit.
        </p>
    </div>
</section>

<!-- ── People Grid ── -->
<main class="t-main">

    <section class="p-search-row">
        <form class="p-search-bar" action="people.php" method="get">
            <input type="text" name="q" placeholder="Search names, roles, barangays…">
            <select name="category" class="p-category-select">
                <option>All Categories</option>
                <option>Local Leader</option>
                <option>Artist</option>
                <option>Farmer</option>
                <option>Community Pillar</option>
            </select>
        </form>
    </section>

    <section class="t-category" id="people">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Culture</p>
                <h2 class="t-cat-title">People of Malvar</h2>
                <p class="t-cat-desc">The individuals whose work and stories shape the Malvareño community.</p>
            </div>
        </div>

        <?php if (empty($people)): ?>
        <div class="p-empty-state">
            <h3>No profiles added yet</h3>
            <p>Once the admin adds profiles, they'll show up here as cards.</p>
        </div>
        <?php else: ?>
        <div class="p-card-grid">
            <?php foreach ($people as $item): ?>
            <article class="p-card">
                <div class="p-card-media" <?= !empty($item['image']) ? 'style="background-image:url(\'' . htmlspecialchars($item['image']) . '\')"' : '' ?>>
                    <button class="p-fav-btn" type="button" aria-label="Save to favorites">&#9825;</button>
                    <?php if (!empty($item['title'])): ?><span class="p-tag-pill"><?= htmlspecialchars($item['title']) ?></span><?php endif; ?>
                </div>
                <div class="p-card-body">
                    <?php if (!empty($item['achievement'])): ?><p class="p-card-category"><?= htmlspecialchars($item['achievement']) ?></p><?php endif; ?>
                    <h3 class="p-card-title"><?= htmlspecialchars($item['fullname']) ?></h3>
                    <p class="p-card-desc"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                    <div class="p-card-actions">
                        <a href="view-details.php?item=<?= urlencode($item['fullname']) ?>" class="p-btn p-btn-primary" onclick="ktTrackItemView('person', <?= (int) $item['person_id'] ?>)">View Details</a>
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
    <p>© KULTOURA · Malvar, Batangas · <a href="index.php">Back to Home</a></p>
</footer>

<script>
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
</script>
<script src="../../assets/js/navbar.js"></script>
<script src="index.js"></script>

</body>
</html>