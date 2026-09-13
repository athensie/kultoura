<?php
session_start();
include 'config/dbmain.php';
include 'config/sitecontent.php';
include 'config/analytics.php';
include 'config/announcements.php';
analytics_track($conn, 'home');

$siteName = "KULTOURA";
$tagline  = "Your Digital Gateway to Malvar's Culture & Local Destinations";

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

/* ============================================================
   HOMEPAGE PREVIEWS
   ------------------------------------------------------------
   Real data for the hero photo collage, the "For You" preview
   carousel, and the About Malvar photo — same image-subfolder
   convention used by pages/traveldiary.php and pages/foryou.php.
============================================================ */
$homeImageSubfolder = [
    'product'    => 'products',
    'restaurant' => 'food',
    'nature'     => 'destinations',
    'resort'     => 'destinations',
    'industry'   => 'destinations',
    'fiesta'     => 'fiestas',
    'person'     => 'people',
];
function home_img(string $category, ?string $raw, array $subfolders): string
{
    if (empty($raw)) return '';
    return 'assets/uploads/' . ($subfolders[$category] ?? $category) . '/' . basename($raw);
}

// Badge colors reused from the Travel Diary category palette, so the
// same category reads the same color everywhere on the site.
$homeBadgeColors = [
    'product'    => '#a9843f',
    'restaurant' => '#8b2e1a',
    'nature'     => '#5f7d4c',
    'resort'     => '#3f7d82',
    'industry'   => '#6b6357',
    'fiesta'     => '#a8433a',
    'person'     => '#4a5a78',
];

// Up to 3 real destination photos: first 2 for the hero collage, the
// 3rd (or a repeat of the 1st if there's only one) for the About photo.
$homeDestPhotos = [];
if ($result = $conn->query("SELECT destination_name, category, image FROM destination WHERE status = 'active' AND image IS NOT NULL AND image <> '' ORDER BY destination_id ASC LIMIT 3")) {
    while ($row = $result->fetch_assoc()) {
        $homeDestPhotos[] = [
            'name'  => $row['destination_name'],
            'image' => home_img($row['category'], $row['image'], $homeImageSubfolder),
        ];
    }
}
$heroPhotos = array_slice($homeDestPhotos, 0, 2);
$aboutPhoto = $homeDestPhotos[2] ?? ($homeDestPhotos[0] ?? null);

// Admin-curated hero photos (Admin → Site Content) take priority over the
// automatic destination-photo fallback above, slot by slot.
$adminHero1 = sitecontent_get_photo($conn, 'home_hero_1');
$adminHero2 = sitecontent_get_photo($conn, 'home_hero_2');
if ($adminHero1) $heroPhotos[0] = ['name' => 'Malvar, Batangas', 'image' => $adminHero1];
if ($adminHero2) $heroPhotos[1] = ['name' => 'Malvar, Batangas', 'image' => $adminHero2];

// A small real-data sample for the "For You" preview carousel — one
// destination, one fiesta, one restaurant, so the teaser is genuine
// catalog content rather than placeholder copy.
$forYouPreview = [];
if ($result = $conn->query("SELECT destination_name AS name, category, image, description FROM destination WHERE status = 'active' AND image IS NOT NULL AND image <> '' ORDER BY destination_id ASC LIMIT 1")) {
    if ($row = $result->fetch_assoc()) {
        $forYouPreview[] = [
            'itemType'   => 'destination',
            'badge'      => ucfirst($row['category']),
            'badgeColor' => $homeBadgeColors[$row['category']] ?? '#5f7d4c',
            'name'       => $row['name'],
            'desc'       => $row['description'],
            'image'      => home_img($row['category'], $row['image'], $homeImageSubfolder),
            'link'       => 'pages/tourism/' . $row['category'] . '.php',
        ];
    }
}
if ($result = $conn->query("SELECT fiesta_name AS name, image, description FROM fiestas WHERE image IS NOT NULL AND image <> '' ORDER BY fiesta_id ASC LIMIT 1")) {
    if ($row = $result->fetch_assoc()) {
        $forYouPreview[] = [
            'itemType'   => 'fiesta',
            'badge'      => 'Fiesta',
            'badgeColor' => $homeBadgeColors['fiesta'],
            'name'       => $row['name'],
            'desc'       => $row['description'],
            'image'      => home_img('fiesta', $row['image'], $homeImageSubfolder),
            'link'       => 'pages/tourism/fiestas.php',
        ];
    }
}
if ($result = $conn->query("SELECT restaurant_name AS name, image, description FROM restaurants WHERE image IS NOT NULL AND image <> '' ORDER BY restaurant_id ASC LIMIT 1")) {
    if ($row = $result->fetch_assoc()) {
        $forYouPreview[] = [
            'itemType'   => 'restaurant',
            'badge'      => 'Food',
            'badgeColor' => $homeBadgeColors['restaurant'],
            'name'       => $row['name'],
            'desc'       => $row['description'],
            'image'      => home_img('restaurant', $row['image'], $homeImageSubfolder),
            'link'       => 'pages/tourism/restaurants.php',
        ];
    }
}

// ── News (mirrors admin/adminannouncements.php) ──
// Only announcements the admin has actually published show up here —
// drafts and not-yet-due scheduled posts never reach this query.
// config/announcements.php (included above) already promotes any
// scheduled post whose time has passed, so 'live' is enough here.
$homeNews = [];
$homeNewsTypeMeta = announcements_type_meta();
if ($result = $conn->query(
    "SELECT id, title, body, type, image, published_at, created_at
     FROM announcements
     WHERE status = 'live'
     ORDER BY COALESCE(published_at, created_at) DESC
     LIMIT 3"
)) {
    $homeNews = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteName; ?></title>
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>

<div class="hero">

    <!-- Ambient orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <header class="navbar">

        <?php if ($isLoggedIn): ?>
            <div class="user-greeting-left"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>Hi, <?php echo $userName; ?></div>
        <?php else: ?>
            <div class="user-greeting-left" style="color:#C8A96E;letter-spacing:2px;font-size:15px;font-weight:900;"><span class="navbar-logo-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>KUL<span style="color:#9fb88a">TOURA</span></div>
        <?php endif; ?>

        <nav class="nav-links">
            <a href="index.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
            <div class="dropdown">
                <a href="pages/tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>TOURISM ▾</span></a>
                <div class="mega-menu">
                    <div class="mega-column">
                        <h4>Food</h4>
                        <a href="pages/tourism/products.php">Products</a>
                        <a href="pages/tourism/restaurants.php">Restaurants</a>
                    </div>
                    <div class="mega-column">
                        <h4>Local Destinations</h4>
                        <a href="pages/tourism/nature.php">Nature</a>
                        <a href="pages/tourism/industry.php">Industry Zone</a>
                        <a href="pages/tourism/resort.php">Resort</a>
                    </div>
                    <div class="mega-column">
                        <h4>Others</h4>
                        <a href="pages/tourism/fiestas.php">Fiestas</a>
                        <a href="pages/tourism/people.php">People of Malvar</a>
                    </div>
                </div>
            </div>

            <a href="../kultoura/pages/foryou.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/></svg></span><span>FOR YOU</span></a>
            <a href="../kultoura/pages/traveldiary.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M16 4v3h3"/><path d="M8 10h8M8 14h8M8 18h5"/></svg></span><span>TRAVEL DIARY</span></a>
            <a href="../kultoura/pages/favorites.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.5-9.5-9C.5 7 2 3.5 5.5 3.5c2 0 3.5 1 4.5 2.5 1-1.5 2.5-2.5 4.5-2.5C18 3.5 19.5 7 19.5 11 17 15.5 12 20 12 20z"/></svg></span><span>FAVORITES</span></a>
            <a href="../kultoura/pages/mostpopular.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4 0 6-3 6-6.5 0-2.5-1.5-4-2.5-5.5.5 2-1 3-2 2 0-2.5-1.5-4-3-6-.5 3-3 4.5-3 8 0 1-1 1.5-2 1-.5 3 2 7 6.5 7z"/></svg></span><span>MOST POPULAR</span></a>
            <a href="../kultoura/pages/about.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.5v5"/><circle cx="12" cy="7.8" r="0.9" fill="currentColor" stroke="none"/></svg></span><span>ABOUT</span></a>


        </nav>

        <?php if ($isLoggedIn): ?>
            <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
            <a href="auth/logout.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg><span>SIGN OUT</span></a>
        <?php else: ?>
            <span class="navbar-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
            <a href="auth/login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN IN</span></a>
        <?php endif; ?>

    </header>

    <main class="hero-content hero-content-split">

        <div class="hero-text-col">

            <p class="hero-eyebrow">
                <span class="hero-eyebrow-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C7 4 4 8 4 13c0 3 2 5 5 5 1 0 2-.3 2.8-.8C10 19 8 21 6 22c4-.3 7-2 8.5-5C16 15 17 12 17 9c0-3-2-5-5-7z"/></svg></span>
                WELCOME TO
            </p>

            <h1 class="brand-title">
                <span class="brand-light">KUL</span><span class="brand-accent">TOURA</span>
            </h1>

            <p class="brand-sub">Malvar, Batangas · Est. Culture</p>

            <p class="tagline">
                <?php echo $tagline; ?>
            </p>

            <div class="cta-buttons">
                <button class="btn btn-filled" id="exploreBtn">
                    EXPLORE CULTURE
                </button>
                <button class="btn btn-outline" id="recommendBtn">
                    GET RECOMMENDATIONS
                </button>
            </div>

        </div>

        <?php if (!empty($heroPhotos)): ?>
        <div class="hero-collage" aria-hidden="true">
            <span class="hero-collage-pin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
            <svg class="hero-collage-path" viewBox="0 0 220 90" preserveAspectRatio="none">
                <path d="M14 14 Q100 8 130 40 T206 66" fill="none" stroke="#C8A96E" stroke-width="2" stroke-dasharray="1 9" stroke-linecap="round"/>
            </svg>
            <div class="hero-photo hero-photo-1">
                <img src="<?php echo htmlspecialchars($heroPhotos[0]['image']); ?>" alt="<?php echo htmlspecialchars($heroPhotos[0]['name']); ?>" loading="lazy">
            </div>
            <?php if (!empty($heroPhotos[1])): ?>
            <div class="hero-photo hero-photo-2">
                <img src="<?php echo htmlspecialchars($heroPhotos[1]['image']); ?>" alt="<?php echo htmlspecialchars($heroPhotos[1]['name']); ?>" loading="lazy">
            </div>
            <?php endif; ?>
            <span class="hero-collage-leaf hero-collage-leaf-1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
            <span class="hero-collage-leaf hero-collage-leaf-2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
        </div>
        <?php endif; ?>

    </main>

    <div class="scroll-hint">
        <span class="scroll-line"></span>
        DISCOVER
    </div>

    <div class="torn-edge" aria-hidden="true">
        <svg viewBox="0 0 1440 48" preserveAspectRatio="none">
            <path d="M0,18 L48,30 L96,10 L144,34 L192,14 L240,38 L288,16 L336,40 L384,20 L432,36 L480,12 L528,32 L576,18 L624,40 L672,22 L720,34 L768,14 L816,38 L864,20 L912,36 L960,16 L1008,40 L1056,22 L1104,34 L1152,12 L1200,32 L1248,18 L1296,38 L1344,20 L1392,34 L1440,18 L1440,48 L0,48 Z" fill="#f0ebd8"/>
        </svg>
    </div>

</div>

<!-- ── For You Preview (mirrors pages/foryou.php) ── -->
<section class="home-foryou">
    <div class="home-foryou-top">
        <div class="home-foryou-intro">
            <p class="home-eyebrow home-eyebrow-rust">CURATED FOR YOU</p>
            <h2 class="home-heading">For You</h2>
            <p class="home-foryou-desc">Discover handpicked experiences and places based on your interests.</p>
            <a href="pages/foryou.php" class="home-btn home-btn-dark">Explore Now →</a>
        </div>
    </div>

    <?php if (!empty($forYouPreview)): ?>
    <div class="home-pills" id="homePills">
        <button type="button" class="home-pill is-active" data-category="all">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/></svg>
            All
        </button>
        <button type="button" class="home-pill" data-category="product">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
            Products
        </button>
        <button type="button" class="home-pill" data-category="restaurant">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg>
            Restaurants
        </button>
        <button type="button" class="home-pill" data-category="destination">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg>
            Destinations
        </button>
        <button type="button" class="home-pill" data-category="fiesta">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            Fiestas
        </button>
        <button type="button" class="home-pill" data-category="person">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
            People
        </button>
    </div>

    <div class="home-carousel-wrap">
        <button type="button" class="home-carousel-nav home-carousel-prev" id="homeCarouselPrev" aria-label="Previous">&#8249;</button>
        <div class="home-carousel" id="homeCarousel">
            <?php foreach ($forYouPreview as $item): ?>
            <a href="<?php echo htmlspecialchars($item['link']); ?>" class="home-card" data-category="<?php echo htmlspecialchars($item['itemType']); ?>">
                <div class="home-card-photo">
                    <span class="home-card-badge" style="background:<?php echo htmlspecialchars($item['badgeColor']); ?>"><?php echo htmlspecialchars(strtoupper($item['badge'])); ?></span>
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" loading="lazy">
                </div>
                <h3 class="home-card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                <p class="home-card-desc"><?php echo htmlspecialchars(mb_strimwidth(trim((string) $item['desc']), 0, 90, '…')); ?></p>
                <span class="home-card-link">View More →</span>
            </a>
            <?php endforeach; ?>
        </div>
        <button type="button" class="home-carousel-nav home-carousel-next" id="homeCarouselNext" aria-label="Next">&#8250;</button>
    </div>
    <p class="home-no-results" id="homeNoResults" hidden>Nothing in that category yet — check the full For You page for more.</p>
    <?php endif; ?>
</section>

<?php if (!empty($homeNews)): ?>
<!-- ── News & Announcements (mirrors admin/adminannouncements.php) ── -->
<section class="home-news">
    <div class="home-news-top">
        <p class="home-eyebrow home-eyebrow-gold">LATEST NEWS</p>
        <h2 class="home-heading">News &amp; Announcements</h2>
    </div>

    <div class="home-news-grid">
        <?php foreach ($homeNews as $n): ?>
            <?php $meta = $homeNewsTypeMeta[$n['type']] ?? ['label' => ucfirst($n['type']), 'color' => '#8a8a5c']; ?>
            <article class="home-news-card">
                <div class="home-news-photo">
                    <?php if (!empty($n['image'])): ?>
                        <img src="<?php echo htmlspecialchars($n['image']); ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="home-news-photo-fallback" style="background:<?php echo htmlspecialchars($meta['color']); ?>1a;color:<?php echo htmlspecialchars($meta['color']); ?>;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M7 14v5a2 2 0 0 0 2 2h1"/></svg>
                        </span>
                    <?php endif; ?>
                    <span class="home-news-badge" style="background:<?php echo htmlspecialchars($meta['color']); ?>"><?php echo htmlspecialchars($meta['label']); ?></span>
                </div>
                <div class="home-news-body">
                    <p class="home-news-date"><?php echo htmlspecialchars(date('F j, Y', strtotime($n['published_at'] ?? $n['created_at']))); ?></p>
                    <h3 class="home-news-title"><?php echo htmlspecialchars($n['title']); ?></h3>
                    <p class="home-news-excerpt"><?php echo htmlspecialchars(mb_strimwidth(trim((string) $n['body']), 0, 110, '…')); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ── About Malvar Preview (mirrors pages/about.php) ── -->
<section class="home-about">
    <div class="home-about-text">
        <p class="home-eyebrow home-eyebrow-gold">ABOUT MALVAR</p>
        <h2 class="home-heading home-heading-light">About Malvar</h2>
        <p class="home-about-desc">A town of farmers and factories, waterfalls and church bells — this is the story of Malvar.</p>

        <div class="home-facts">
            <div class="home-fact">
                <span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1"/><path d="M10 21v-4h4v4"/></svg></span>
                <div>
                    <div class="home-fact-num">1919</div>
                    <div class="home-fact-label">Founded as a Municipality</div>
                </div>
            </div>
            <div class="home-fact">
                <span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                <div>
                    <div class="home-fact-num">15</div>
                    <div class="home-fact-label">Barangays</div>
                </div>
            </div>
            <div class="home-fact">
                <span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
                <div>
                    <div class="home-fact-num">33 km²</div>
                    <div class="home-fact-label">Land Area</div>
                </div>
            </div>
            <div class="home-fact">
                <span class="home-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M4 21c0-4.5 3.5-7 8-7s8 2.5 8 7"/></svg></span>
                <div>
                    <div class="home-fact-num">~74,500</div>
                    <div class="home-fact-label">Residents (2024 Census)</div>
                </div>
            </div>
        </div>

        <a href="pages/about.php" class="home-btn home-btn-light">Learn More About Malvar →</a>
    </div>

    <?php if ($aboutPhoto): ?>
    <div class="home-about-photo">
        <img src="<?php echo htmlspecialchars($aboutPhoto['image']); ?>" alt="<?php echo htmlspecialchars($aboutPhoto['name']); ?>" loading="lazy">
        <span class="home-about-leaf home-about-leaf-1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
        <span class="home-about-leaf home-about-leaf-2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
    </div>
    <?php endif; ?>
</section>

<!-- ── Footer ── -->
<footer class="home-footer">
    <div class="home-footer-top">
        <div class="home-footer-brand">
            <div class="home-footer-logo">KUL<span>TOURA</span></div>
            <p>Your Digital Gateway to Malvar's Culture &amp; Local Destinations</p>
            <div class="home-footer-social">
                <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8.4h2.8l.4-3.3h-3.2V8.1c0-.9.3-1.6 1.7-1.6h1.7V3.5C16.6 3.4 15.5 3.3 14.3 3.3c-2.6 0-4.3 1.6-4.3 4.4v2.6H7.2v3.3h2.8V22h3.5z"/></svg></a>
                <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg></a>
                <a href="pages/tourism.php" aria-label="Explore destinations"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></a>
            </div>
        </div>
        <div class="home-footer-col">
            <h4>Explore</h4>
            <a href="index.php">Home</a>
            <a href="pages/foryou.php">For You</a>
            <a href="pages/tourism.php">Tourism</a>
            <a href="pages/traveldiary.php">Travel Diary</a>
            <a href="pages/favorites.php">Favorites</a>
            <a href="pages/mostpopular.php">Most Popular</a>
        </div>
        <div class="home-footer-col">
            <h4>Resources</h4>
            <a href="pages/about.php">About</a>
            <a href="#">Contact Us</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Help Center</a>
        </div>
        <div class="home-footer-col home-footer-newsletter">
            <h4>Newsletter</h4>
            <p>Get updates on events, festivals, and hidden gems in Malvar.</p>
            <form class="home-newsletter-form" id="homeNewsletterForm">
                <input type="email" placeholder="Enter your email" required>
                <button type="submit" aria-label="Subscribe"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
            </form>
        </div>
    </div>
    <div class="home-footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> KULTOURA. All rights reserved.</p>
        <button type="button" class="home-scroll-top" id="homeScrollTop" aria-label="Scroll to top"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M6 11l6-6 6 6"/></svg></button>
    </div>
</footer>

<script src="index.js"></script>
<script src="assets/js/home.js"></script>
</body>
</html> 