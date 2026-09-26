<?php
require_once __DIR__ . '/../../config/session_boot.php';
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