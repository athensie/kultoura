<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
include '../config/sitecontent.php';
include '../config/analytics.php';
analytics_track($conn, 'about');

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['username'] ?? '');

/* ============================================================
   SECTION PHOTOS
   ------------------------------------------------------------
   Real catalog photos to illustrate the hero and each section
   below — same image-subfolder convention used by
   pages/traveldiary.php and index.php. Every lookup degrades to
   null gracefully (see about_fetch_row) so a sparse catalog never
   renders a broken <img>; the CSS just drops the photo column.
============================================================ */
$aboutImageSubfolder = [
    'product'    => 'products',
    'restaurant' => 'food',
    'nature'     => 'destinations',
    'resort'     => 'destinations',
    'industry'   => 'destinations',
    'fiesta'     => 'fiestas',
    'person'     => 'people',
];
function about_img(string $category, ?string $raw, array $subfolders): string
{
    if (empty($raw)) return '';
    return '../assets/uploads/' . ($subfolders[$category] ?? $category) . '/' . basename($raw);
}
function about_fetch_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['image'])) $rows[] = $row;
        }
    }
    return $rows;
}
function about_photo(?array $row, array $subfolders): ?array
{
    if (!$row) return null;
    return ['name' => $row['name'], 'image' => about_img($row['category'], $row['image'], $subfolders)];
}

$natureRows     = about_fetch_rows($conn, "SELECT destination_name AS name, image, category FROM destination WHERE status = 'active' AND category = 'nature' ORDER BY destination_id ASC LIMIT 3");
$industryRows   = about_fetch_rows($conn, "SELECT destination_name AS name, image, category FROM destination WHERE status = 'active' AND category = 'industry' ORDER BY destination_id ASC LIMIT 1");
$anyDestRows    = about_fetch_rows($conn, "SELECT destination_name AS name, image, category FROM destination WHERE status = 'active' ORDER BY destination_id ASC LIMIT 3");
$fiestaRows     = about_fetch_rows($conn, "SELECT fiesta_name AS name, image, 'fiesta' AS category FROM fiestas ORDER BY fiesta_id ASC LIMIT 1");
$personRows     = about_fetch_rows($conn, "SELECT fullname AS name, image, 'person' AS category FROM people ORDER BY person_id ASC LIMIT 1");
$productRows    = about_fetch_rows($conn, "SELECT product_name AS name, image, 'product' AS category FROM products ORDER BY product_id ASC LIMIT 1");
$restaurantRows = about_fetch_rows($conn, "SELECT restaurant_name AS name, image, 'restaurant' AS category FROM restaurants ORDER BY restaurant_id ASC LIMIT 1");

$heroPhoto      = about_photo($natureRows[0] ?? $anyDestRows[0] ?? $industryRows[0] ?? null, $aboutImageSubfolder);
$historyPhoto   = about_photo($anyDestRows[0] ?? $industryRows[0] ?? $productRows[0] ?? null, $aboutImageSubfolder);
$geographyPhoto = about_photo($natureRows[0] ?? $anyDestRows[0] ?? null, $aboutImageSubfolder);
$economyPhoto   = about_photo($industryRows[0] ?? $productRows[0] ?? $restaurantRows[0] ?? $anyDestRows[1] ?? null, $aboutImageSubfolder);
$culturePhoto   = about_photo($fiestaRows[0] ?? $personRows[0] ?? $anyDestRows[2] ?? null, $aboutImageSubfolder);
$naturePhoto    = about_photo($natureRows[1] ?? $natureRows[0] ?? $anyDestRows[2] ?? $anyDestRows[0] ?? null, $aboutImageSubfolder);

// Admin-curated hero photo (Admin → Site Content) takes priority over the
// automatic destination-photo fallback above.
$adminAboutHero = sitecontent_get_photo($conn, 'about_hero');
if ($adminAboutHero) {
    $heroPhoto = ['name' => 'Malvar, Batangas', 'image' => $adminAboutHero];
}

/* ============================================================
   ABOUT SECTIONS (admin-managed via Admin → Site Content)
   ------------------------------------------------------------
   Each row picks its own admin-uploaded photo if it has one;
   otherwise the original 5 seeded sections fall back to the
   catalog photos derived above, and any brand-new section an
   admin adds just renders without a photo until they add one.
============================================================ */
$autoSectionPhotoByTitle = [
    'history'               => $historyPhoto,
    'geography'              => $geographyPhoto,
    'economy & industry'    => $economyPhoto,
    'culture & festivals'   => $culturePhoto,
    'nature & attractions'  => $naturePhoto,
];

$iconSvgs = sitecontent_icons();
$aboutSections = sitecontent_get_about_sections($conn);
foreach ($aboutSections as &$sec) {
    if (!empty($sec['image'])) {
        $sec['photo'] = ['name' => $sec['title'], 'image' => $sec['image']];
    } else {
        $sec['photo'] = $autoSectionPhotoByTitle[mb_strtolower($sec['title'])] ?? null;
    }
    $sec['slug'] = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($sec['title'])), '-');
}
unset($sec);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About – KULTOURA</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/about.css">
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
            <a href="#" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></span><span>MORE ▾</span></a>
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
<section class="a-hero<?php echo $heroPhoto ? '' : ' a-hero-no-photo'; ?>">
    <?php if ($heroPhoto): ?>
        <img class="a-hero-photo" src="<?php echo htmlspecialchars($heroPhoto['image']); ?>" alt="<?php echo htmlspecialchars($heroPhoto['name']); ?>">
        <div class="a-hero-scrim"></div>
    <?php endif; ?>
    <span class="a-hero-leaf" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
    <div class="a-hero-inner">
        <p class="a-eyebrow">ABOUT</p>
        <h1 class="a-page-title">Malvar</h1>
        <span class="a-title-arrow" aria-hidden="true"></span>
        <p class="a-page-sub">
            A town of farmers and factories, waterfalls and church bells — this is the story of Malvar.
        </p>
    </div>
</section>

<!-- ── Quick Facts card (pulled up over the hero's bottom edge via negative margin — sits in normal flow, so it sizes itself instead of relying on guessed padding) ── -->
<div class="a-facts-wrap">
    <div class="a-facts">
        <div class="a-fact">
            <span class="a-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v3M9 5h6l-1 3h-4z"/><path d="M6 21V10l6-4 6 4v11"/><path d="M10 21v-5h4v5"/></svg></span>
            <div class="a-fact-num">1919</div>
            <div class="a-fact-label">Year Established</div>
            <div class="a-fact-desc">Malvar was officially established.</div>
        </div>
        <div class="a-fact">
            <span class="a-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6"/><circle cx="17" cy="9" r="2.3"/><path d="M15.5 12c2.5 0 5 1.7 5.5 4.5"/></svg></span>
            <div class="a-fact-num">15</div>
            <div class="a-fact-label">Barangays</div>
            <div class="a-fact-desc">A vibrant community across 15 barangays.</div>
        </div>
        <div class="a-fact">
            <span class="a-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3 3 5v16l6-2 6 2 6-2V3l-6 2-6-2z"/><path d="M9 3v16M15 5v16"/></svg></span>
            <div class="a-fact-num">33 km²</div>
            <div class="a-fact-label">Land Area</div>
            <div class="a-fact-desc">Total land area of Malvar, Batangas.</div>
        </div>
        <div class="a-fact">
            <span class="a-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M4 21c0-4.5 3.5-7 8-7s8 2.5 8 7"/></svg></span>
            <div class="a-fact-num">~74,500</div>
            <div class="a-fact-label">Residents</div>
            <div class="a-fact-desc">2024 Census — a growing population.</div>
        </div>
        <div class="a-fact">
            <span class="a-fact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M9 12.5 7 21l5-3 5 3-2-8.5"/></svg></span>
            <div class="a-fact-num">3rd</div>
            <div class="a-fact-label">District</div>
            <div class="a-fact-desc">One of Batangas' progressive municipalities.</div>
        </div>
    </div>
</div>

<!-- ── Content ── -->
<main class="a-main">

    <!-- ABOUT SECTIONS (admin-managed: Admin → Site Content) -->
    <?php foreach ($aboutSections as $sec): ?>
    <section class="a-section" id="<?php echo htmlspecialchars($sec['slug']); ?>">
        <?php if ($sec['photo']): ?>
        <div class="a-section-photo">
            <img src="<?php echo htmlspecialchars($sec['photo']['image']); ?>" alt="<?php echo htmlspecialchars($sec['photo']['name']); ?>" loading="lazy">
        </div>
        <?php endif; ?>
        <div class="a-section-content">
            <div class="a-section-header">
                <span class="a-section-icon"><?php echo $iconSvgs[$sec['icon_key']] ?? $iconSvgs['history']; ?></span>
                <h2><?php echo htmlspecialchars($sec['title']); ?></h2>
            </div>
            <div class="a-section-body">
                <?php foreach ($sec['paragraphs'] as $para): ?>
                    <p><?php echo nl2br(htmlspecialchars($para)); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endforeach; ?>

    <div class="a-cta">
        <span class="a-cta-leaf a-cta-leaf-1" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
        <span class="a-cta-leaf a-cta-leaf-2" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
        <div class="a-cta-text">
            <h3>See Malvar for yourself!</h3>
            <p>From cultural heritage to nature escapes, discover unforgettable places and stories that make Malvar special.</p>
        </div>
        <a href="tourism.php" class="a-cta-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg><span>Explore Malvar</span></a>
    </div>

</main>

<!-- ── Footer ── -->
<footer class="a-footer">
    <p>© <?php echo date('Y'); ?> KulToura. All rights reserved.</p>
    <div class="a-footer-social">
        <a href="https://www.facebook.com/MalvarBatangasOfficial" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8.4h2.8l.4-3.3h-3.2V8.1c0-.9.3-1.6 1.7-1.6h1.7V3.5C16.6 3.4 15.5 3.3 14.3 3.3c-2.6 0-4.3 1.6-4.3 4.4v2.6H7.2v3.3h2.8V22h3.5z"/></svg></a>
        <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5.5" width="19" height="13" rx="3"/><path d="M10.5 9.5v5l4.5-2.5z" fill="currentColor" stroke="none"/></svg></a>
        <a href="mailto:malvartourismoffice@gmail.com" aria-label="Email"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></a>
    </div>
    <p class="a-footer-tag">Promoting culture. Preserving heritage. Inspiring journeys.</p>
</footer>

<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/about.js"></script>

</body>
</html>
