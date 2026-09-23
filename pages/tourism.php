<?php
session_start();
include '../config/dbmain.php';
include '../config/analytics.php';
analytics_track($conn, 'tourism');

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

/*
 |--------------------------------------------------------------------
 | HUB TILE IMAGES
 |--------------------------------------------------------------------
 | Each category tile below shows the newest entry's photo from its
 | source table. `destination` categories are stored lowercase
 | ('nature', 'resort', 'industrial zone'), matching how nature.php
 | already queries it — double check resort.php / industry.php use
 | the same casing if their tiles don't pick up an image.
 */
function kt_latest_image(mysqli $conn, string $table, ?string $categoryColumn = null, ?string $categoryValue = null): ?string
{
    if ($categoryColumn !== null) {
        $sql = "SELECT image FROM {$table} WHERE {$categoryColumn} = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $categoryValue);
    } else {
        $sql = "SELECT image FROM {$table} ORDER BY created_at DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['image'] ?? null;
}

$hubImages = [
    'products'    => kt_latest_image($conn, 'products'),
    'restaurants' => kt_latest_image($conn, 'restaurants'),
    'nature'      => kt_latest_image($conn, 'destination', 'category', 'nature'),
    'resort'      => kt_latest_image($conn, 'destination', 'category', 'resort'),
    'industry'    => kt_latest_image($conn, 'destination', 'category', 'industrial zone'),
    'fiestas'     => kt_latest_image($conn, 'fiestas'),
    'people'      => kt_latest_image($conn, 'people'),
];

/*
 |--------------------------------------------------------------------
 | Turn each raw `image` value into a working <img> src.
 |--------------------------------------------------------------------
 | basename() strips any accidentally-stored path/URL prefix down to
 | just the filename, so this is robust to older rows saved before the
 | upload code settled on "bare filename only" as the convention.
 |
 | Subfolder per source table — 'food' and 'destinations' are confirmed
 | from the actual project structure; 'products', 'fiestas', and
 | 'people' are a best guess matching the table name and haven't been
 | verified — check those three if their tiles don't pick up a photo.
 */
$hubImageSubfolder = [
    'products'    => 'products',
    'restaurants' => 'food',
    'nature'      => 'destinations',
    'resort'      => 'destinations',
    'industry'    => 'destinations',
    'fiestas'     => 'fiestas',
    'people'      => 'people',
];

foreach ($hubImages as $key => $value) {
    if (!empty($value)) {
        $hubImages[$key] = '../assets/uploads/' . $hubImageSubfolder[$key] . '/' . basename($value);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tourism – KULTOURA</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
</head>
<body>

<!-- ── Navbar (shared) ── -->
<header class="navbar navbar-solid">

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting-left"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>Mabuhay, <?php echo $userName; ?></div>
    <?php else: ?>
        <div class="user-greeting-left" style="letter-spacing:2px;font-size:15px;font-weight:900;">
            <span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>
            <a href="../index.php" style="text-decoration:none;color:#C8A96E;">KUL<span style="color:#9fb88a">TOURA</span></a>
        </div>
    <?php endif; ?>

    <nav class="nav-links">
        <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
        <div class="dropdown">
            <a href="tourism.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>TOURISM ▾</span></a>
            <div class="mega-menu">
                <div class="mega-column">
                    <h4>Food</h4>
                    <a href="../pages/tourism/products.php">Products</a>
                    <a href="../pages/tourism/restaurants.php">Restaurants</a>
                </div>
                <div class="mega-column">
                    <h4>Local Destinations</h4>
                    <a href="../pages/tourism/nature.php">Nature</a>
                    <a href="../pages/tourism/industry.php">Industry Zone</a>
                    <a href="../pages/tourism/resort.php">Resort</a>
                </div>
                <div class="mega-column">
                    <h4>Others</h4>
                    <a href="../pages/tourism/fiestas.php">Fiestas</a>
                    <a href="../pages/tourism/people.php">People of Malvar</a>
                </div>
            </div>
        </div>
        <a href="foryou.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/></svg></span><span>FOR YOU</span></a>
        <a href="traveldiary.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M16 4v3h3"/><path d="M8 10h8M8 14h8M8 18h5"/></svg></span><span>TRAVEL DIARY</span></a>
        <a href="favorites.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.5-9.5-9C.5 7 2 3.5 5.5 3.5c2 0 3.5 1 4.5 2.5 1-1.5 2.5-2.5 4.5-2.5C18 3.5 19.5 7 19.5 11 17 15.5 12 20 12 20z"/></svg></span><span>FAVORITES</span></a>
        <a href="mostpopular.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4 0 6-3 6-6.5 0-2.5-1.5-4-2.5-5.5.5 2-1 3-2 2 0-2.5-1.5-4-3-6-.5 3-3 4.5-3 8 0 1-1 1.5-2 1-.5 3 2 7 6.5 7z"/></svg></span><span>MOST POPULAR</span></a>
        <a href="about.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.5v5"/><circle cx="12" cy="7.8" r="0.9" fill="currentColor" stroke="none"/></svg></span><span>ABOUT</span></a>
    </nav>

    <?php if ($isLoggedIn): ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../auth/logout.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
    <?php else: ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../auth/login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN IN</span></a>
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
        <p class="t-eyebrow">MALVAR, BATANGAS</p>
        <h1 class="t-page-title">Tourism</h1>
        <p class="t-page-sub">
            Everything Malvar has to offer — food, nature, culture, and people — all in one place.
        </p>
    </div>
</section>

<!-- ── Category Hub ── -->
<main class="t-main">

    <!-- FOOD -->
    <section class="t-category" id="food">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Category 01</p>
                <h2 class="t-cat-title">Food</h2>
                <p class="t-cat-desc">
                    Taste what Malvar is made of — from farm-fresh local products to restaurants serving Batangueño classics.
                </p>
            </div>
        </div>

        <div class="t-card-row">
            <a href="tourism/products.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['products'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['products']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>Products</h3>
                    <p>Locally made goods, native delicacies, and fresh produce from Malvar's markets.</p>
                </div>
                <div class="t-card-arrow">→</div>
            </a>

            <a href="tourism/restaurants.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['restaurants'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['restaurants']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>Restaurants</h3>
                    <p>From roadside eateries to family restaurants — find your next favorite meal.</p>
                </div>
                <div class="t-card-arrow">→</div>
            </a>
        </div>
    </section>

    <div class="t-divider"></div>

    <!-- LOCAL DESTINATIONS -->
    <section class="t-category" id="destinations">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Category 02</p>
                <h2 class="t-cat-title">Local Destinations</h2>
                <p class="t-cat-desc">
                    Explore Malvar's landscapes — green nature spots, thriving industrial zones, and refreshing resorts.
                </p>
            </div>
        </div>

        <div class="t-card-row">
            <a href="tourism/nature.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['nature'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['nature']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>Nature</h3>
                    <p>Parks, trails, and scenic views that showcase Malvar's natural beauty.</p>
                </div>
                <div class="t-card-arrow">→</div>
            </a>

            <a href="tourism/resort.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['resort'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['resort']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>Resorts</h3>
                    <p>Poolside relaxation and weekend getaways — Malvar's best leisure destinations.</p>
                </div>
                <div class="t-card-arrow">→</div>
            </a>

            <a href="tourism/industry.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['industry'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['industry']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>Industry Zone</h3>
                    <p>Discover the commercial and industrial districts that drive Malvar forward.</p>
                </div>
                <div class="t-card-arrow">→</div>
            </a>
        </div>
    </section>

    <div class="t-divider"></div>

    <!-- CULTURE -->
    <section class="t-category" id="culture">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Category 03</p>
                <h2 class="t-cat-title">Culture</h2>
                <p class="t-cat-desc">
                    The soul of Malvar lives in its festivals and its people. Meet both.
                </p>
            </div>
        </div>

        <div class="t-card-row">
            <a href="tourism/fiestas.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['fiestas'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['fiestas']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>Fiestas</h3>
                    <p>Barangay fiestas, patron saint celebrations, and the vibrant street culture of Malvar.</p>
                </div>
                <div class="t-card-arrow">→</div>
            </a>

            <a href="tourism/people.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['people'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['people']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>People of Malvar</h3>
                    <p>Artists, leaders, farmers, and community pillars who define the Malvareño spirit.</p>
                </div>
                <div class="t-card-arrow">→</div>
            </a>
        </div>
    </section>

</main>

<!-- ── Footer ── -->
<footer class="t-footer">
    <p>© KULTOURA · Malvar, Batangas · </p>
</footer>

<script src="../assets/js/navbar.js"></script>
<script src="index.js"></script>

</body>
</html>