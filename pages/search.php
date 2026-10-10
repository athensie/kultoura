<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
require_once __DIR__ . '/../config/maintenance.php';
kt_maintenance_gate($conn);
include '../config/analytics.php';
require_once __DIR__ . '/../config/search.php';
analytics_track($conn, 'search');

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? '');

$query   = trim($_GET['q'] ?? '');
$results = $query !== '' ? kt_search_all($conn, $query, 20) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $query !== '' ? htmlspecialchars($query) . ' · Search' : 'Search'; ?> · KULTOURA</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/foryou.css">
    <link rel="stylesheet" href="../assets/css/search.css">
</head>
<body>

<header class="navbar">

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../assets/images/salakot.png?v=20261008" alt=""></span><span class="navbar-greeting-text">Mabuhay, <?php echo $userName; ?></span></div>
    <?php else: ?>
        <div class="user-greeting-left navbar-brand-logo"><img src="../assets/images/kultoura.png" alt="KulToura"></div>
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
            <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></span><span>MORE ▾</span></a>
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

        <?php if ($isLoggedIn): ?><a href="../auth/logout.php" class="nav-item nav-signout-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span><span>SIGN OUT</span></a><?php else: ?><a href="../auth/login.php" class="nav-item nav-signin-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg></span><span>SIGN IN</span></a><?php endif; ?>
    </nav>

    <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

</header>

<main class="fy-page">
    <section class="search-results-header">
        <p class="section-label">SEARCH</p>
        <h1 class="search-results-title">
            <?php if ($query !== ''): ?>
                Results for &ldquo;<?php echo htmlspecialchars($query); ?>&rdquo;
            <?php else: ?>
                Search KulToura
            <?php endif; ?>
        </h1>

        <form action="search.php" method="GET" class="search-results-form">
            <input type="search" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search destinations, food, events, people…" autofocus>
            <button type="submit">Search</button>
        </form>

        <?php if ($query !== ''): ?>
            <p class="search-results-count"><?php echo count($results); ?> result<?php echo count($results) === 1 ? '' : 's'; ?></p>
        <?php endif; ?>
    </section>

    <?php if ($query === ''): ?>
        <div class="fy-empty-state">
            <p>Type something above to search across every destination, restaurant, product, fiesta, person, and announcement on the site.</p>
        </div>
    <?php elseif (empty($results)): ?>
        <div class="fy-empty-state">
            <p>Nothing matched &ldquo;<?php echo htmlspecialchars($query); ?>&rdquo;. Try a different word, or browse by category from the menu above.</p>
        </div>
    <?php else: ?>
        <div class="fy-grid search-results-grid">
            <?php foreach ($results as $r): ?>
                <div class="fy-card">
                    <a class="fy-card-link" href="<?php echo htmlspecialchars($r['link']); ?>">
                        <div class="fy-gcard-media">
                            <span class="fy-gcard-badge fy-badge-<?php echo htmlspecialchars($r['category']); ?>"><?php echo htmlspecialchars($r['badgeText']); ?></span>
                            <?php if (!empty($r['image'])): ?>
                                <img src="<?php echo htmlspecialchars($r['image']); ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="fy-gcard-initial"><?php echo htmlspecialchars(mb_substr($r['name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="fy-gcard-body">
                            <h3 class="fy-card-name"><?php echo htmlspecialchars($r['name']); ?></h3>
                            <?php if (!empty($r['snippet'])): ?>
                                <p class="fy-card-desc"><?php echo htmlspecialchars($r['snippet']); ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<script src="../assets/js/navbar.js"></script>
</body>
</html>
