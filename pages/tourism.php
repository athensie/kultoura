<?php
require_once __DIR__ . '/../config/session_boot.php';
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
    'nature'      => kt_latest_image($conn, 'destination', 'category', 'nature'),
    'resort'      => kt_latest_image($conn, 'destination', 'category', 'resort'),
    'industry'    => kt_latest_image($conn, 'destination', 'category', 'industry'),
    'fiestas'     => kt_latest_image($conn, 'fiestas'),
    'people'      => kt_latest_image($conn, 'people'),
    'services'    => kt_latest_image($conn, 'destination', 'category', 'service'),
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
    'nature'      => 'destinations',
    'resort'      => 'destinations',
    'industry'    => 'destinations',
    'fiestas'     => 'fiestas',
    'people'      => 'people',
    'services'    => 'destinations',
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
        <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../assets/images/salakot.png" alt=""></span>Mabuhay, <?php echo $userName; ?></div>
    <?php else: ?>
        <div class="user-greeting-left" style="letter-spacing:2px;font-size:15px;font-weight:900;">
            <span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>
            <a href="../index.php" style="text-decoration:none;color:#C8A96E;">KUL<span style="color:#9fb88a">TOURA</span></a>
        </div>
    <?php endif; ?>

    <nav class="nav-links">
        <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
        <div class="dropdown">
            <a href="tourism.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR ▾</span></a>
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
    </nav>

    <?php if ($isLoggedIn): ?>
        <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        <a href="../auth/logout.php" class="sign-in-btn sign-in-btn-icon-only" aria-label="Sign Out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
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

    <!-- LOCAL PRODUCTS -->
    <section class="t-category" id="products">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Category 01</p>
                <h2 class="t-cat-title">Local Products</h2>
                <p class="t-cat-desc">
                    Taste what Malvar is made of — farm-fresh local products and native delicacies.
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

    <div class="t-divider"></div>

    <!-- OTHER SERVICES -->
    <section class="t-category" id="services">
        <div class="t-category-header">
            <div>
                <p class="t-cat-label">Category 04</p>
                <h2 class="t-cat-title">Other Services</h2>
                <p class="t-cat-desc">
                    The primary places a tourist will actually use — hospitals, terminals, and everyday essentials.
                </p>
            </div>
        </div>

        <div class="t-card-row">
            <a href="tourism/services.php" class="t-card">
                <div class="t-card-media">
                    <?php if (!empty($hubImages['services'])): ?>
                        <img src="<?php echo htmlspecialchars($hubImages['services']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="t-card-body">
                    <h3>Other Services</h3>
                    <p>Hospitals, terminals, and everyday essentials to help you get around Malvar.</p>
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