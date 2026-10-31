<?php
require_once __DIR__ . '/config/session_boot.php';
include 'config/dbmain.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/config/newsletter.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'newsletter_subscribe') {
    header('Content-Type: application/json');
    echo json_encode(kt_newsletter_subscribe($conn, (string) ($_POST['email'] ?? '')));
    exit;
}
if (isset($_GET['unsubscribe'])) {
    $done = kt_newsletter_unsubscribe($conn, (string) $_GET['unsubscribe']);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>KULTOURA</title></head>'
       . '<body style="font-family:Arial,sans-serif;text-align:center;padding:60px">'
       . '<h2>' . ($done ? 'You have been unsubscribed.' : 'Link invalid or already unsubscribed.') . '</h2>'
       . '<p><a href="index.php">Back to KULTOURA</a></p></body></html>';
    exit;
}
require_once __DIR__ . '/config/maintenance.php';
kt_maintenance_gate($conn);
include 'config/sitecontent.php';
include 'config/analytics.php';
include 'config/announcements.php';
require_once __DIR__ . '/config/homepage_content.php';
kt_homepage_content_ensure_schema($conn);
analytics_track($conn, 'home');
$siteName = "KULTOURA";
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = in_array(strtolower((string) ($_SESSION['role'] ?? '')), ['admin', 'super admin'], true);
$userName   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? '');
$homeImageSubfolder = [
    'product'       => 'products',
    'restaurant'    => 'food',
    'nature'        => 'destinations',
    'resort'        => 'destinations',
    'industry'      => 'destinations',
    'accommodation' => 'destinations',
    'bank'          => 'destinations',
    'service'       => 'destinations',
    'church'        => 'destinations',
    'fiesta'        => 'fiestas',
    'person'        => 'people',
];
function home_img(string $category, ?string $raw, array $subfolders): string
{
    if (empty($raw)) return '';
    return 'assets/uploads/' . ($subfolders[$category] ?? $category) . '/' . basename($raw);
}
$homeBadgeColors = [
    'product'       => '#a9843f',
    'restaurant'    => '#8b2e1a',
    'nature'        => '#5f7d4c',
    'resort'        => '#3f7d82',
    'industry'      => '#6b6357',
    'accommodation' => '#7d5fa3',
    'bank'          => '#2f6690',
    'service'       => '#7a6a53',
    'church'        => '#8b3a3a',
    'fiesta'        => '#a8433a',
    'person'        => '#4a5a78',
];
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
$adminHero1 = sitecontent_get_photo($conn, 'home_hero_1');
$adminHero2 = sitecontent_get_photo($conn, 'home_hero_2');
if ($adminHero1) $heroPhotos[0] = ['name' => 'Malvar, Batangas', 'image' => $adminHero1];
if ($adminHero2) $heroPhotos[1] = ['name' => 'Malvar, Batangas', 'image' => $adminHero2];
$heroBgPhoto = sitecontent_get_photo($conn, 'home_hero_bg');
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
if ($result = $conn->query("SELECT fiesta_name AS name, image, description FROM fiestas WHERE status = 'active' AND image IS NOT NULL AND image <> '' ORDER BY fiesta_id ASC LIMIT 1")) {
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
if ($result = $conn->query("SELECT restaurant_name AS name, image, description FROM restaurants WHERE status = 'active' AND image IS NOT NULL AND image <> '' ORDER BY restaurant_id ASC LIMIT 1")) {
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
$homeNews = [];
$homeNewsTypeMeta = announcements_type_meta();
if ($result = $conn->query(
    "SELECT id, title, body, type, image, published_at, created_at
     FROM announcements
     WHERE status = 'live'
     ORDER BY COALESCE(published_at, created_at) DESC
     LIMIT 5"
)) {
    $homeNews = $result->fetch_all(MYSQLI_ASSOC);
}
$homeUpcoming = [];
if ($result = $conn->query(
    "SELECT fiesta_id, fiesta_name, type, celebration_date, location, image
     FROM fiestas
     WHERE status = 'active' AND celebration_date >= CURDATE()
     ORDER BY celebration_date ASC
     LIMIT 10"
)) {
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $fiesta) {
        $homeUpcoming[] = [
            'kind' => 'fiesta',
            'title' => $fiesta['fiesta_name'],
            'type' => $fiesta['type'],
            'date' => $fiesta['celebration_date'],
            'time' => '',
            'location' => $fiesta['location'],
            'image' => home_img('fiesta', $fiesta['image'], $homeImageSubfolder),
            'href' => 'pages/tourism/fiestas.php',
        ];
    }
}
$homeNow = new DateTimeImmutable();
$homeChurches = $conn->query(
    "SELECT destination_name, address, image, schedule_month, schedule_text
     FROM destination
     WHERE category = 'church' AND status = 'active'
       AND schedule_month >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);
if ($homeChurches) {
    while ($church = $homeChurches->fetch_assoc()) {
        $services = json_decode($church['schedule_text'] ?? '', true);
        if (!is_array($services)) continue;
        foreach ($services as $service) {
            $date = (string) ($service['date'] ?? '');
            $time = (string) ($service['time'] ?? '');
            $serviceDateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
            if (!$serviceDateTime || $serviceDateTime < $homeNow) continue;
            $homeUpcoming[] = [
                'kind' => 'mass',
                'title' => trim((string) ($service['title'] ?? 'Mass')) ?: 'Mass',
                'type' => 'Mass · ' . $church['destination_name'],
                'date' => $date,
                'time' => $serviceDateTime->format('g:i A'),
                'location' => $church['address'] ?? '',
                'image' => home_img('church', $church['image'], $homeImageSubfolder),
                'href' => 'pages/tourism/churches.php',
                'sort' => $serviceDateTime->getTimestamp(),
            ];
        }
    }
}
foreach ($homeUpcoming as &$upcomingItem) {
    $upcomingItem['sort'] ??= strtotime($upcomingItem['date']);
}
unset($upcomingItem);
usort($homeUpcoming, fn($a, $b) => $a['sort'] <=> $b['sort']);
$homeUpcoming = array_slice($homeUpcoming, 0, 10);
$homeCalendarMonth = new DateTimeImmutable('first day of this month');
$homeCalendarMonthKey = $homeCalendarMonth->format('Y-m');
$homeCalendarOffset = (int) $homeCalendarMonth->format('w');
$homeCalendarDays = (int) $homeCalendarMonth->format('t');
$homeCalendarToday = (new DateTimeImmutable())->format('Y-m-d');
$homeCalendarScheduledDays = [];
foreach ($homeUpcoming as $scheduleItem) {
    if (substr((string) $scheduleItem['date'], 0, 7) === $homeCalendarMonthKey) {
        $day = (int) date('j', strtotime($scheduleItem['date']));
        $homeCalendarScheduledDays[$day][] = $scheduleItem['title'];
    }
}
$homeSchedulePreview = array_slice($homeUpcoming, 0, 3);
$homePartners = [];
if ($result = $conn->query("SELECT name, logo, website_url, description FROM partners WHERE status = 'live' ORDER BY created_at DESC")) {
    $homePartners = $result->fetch_all(MYSQLI_ASSOC);
}
$homeEventGallery = [];
if ($result = $conn->query("SELECT image, caption FROM event_gallery WHERE status = 'live' ORDER BY created_at DESC LIMIT 18")) {
    $homeEventGallery = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteName; ?></title>
    <link rel="stylesheet" href="assets/css/index.css?v=20261031-3">
    <link rel="stylesheet" href="assets/css/search.css">
</head>
<body>
<div class="hero<?php echo $heroBgPhoto ? '' : ' hero-no-bg-photo'; ?>">
    <?php if ($heroBgPhoto): ?>
        <img class="hero-bg-photo" src="<?php echo htmlspecialchars($heroBgPhoto); ?>" alt="">
        <div class="hero-bg-scrim"></div>
    <?php endif; ?>
<div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <header class="navbar">
        <?php if ($isLoggedIn): ?>
            <div class="user-greeting-left user-greeting-name"><span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="assets/images/salakot.png?v=20261008" alt=""></span><span class="navbar-greeting-text">Mabuhay, <?php echo $userName; ?></span></div>
        <?php else: ?>
            <div class="user-greeting-left navbar-brand-logo"><img src="assets/images/kultoura.png" alt="KulToura"></div>
        <?php endif; ?>
        <nav class="nav-links">
            <a href="index.php" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
            <div class="dropdown">
                <a href="pages/tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR ▾</span></a>
                <div class="mega-menu">
                    <div class="mega-column">
                        <h4>Local Products</h4>
                        <a href="pages/tourism/products.php">Products</a>
                    </div>
                    <div class="mega-column">
                        <h4>Local Destinations</h4>
                        <a href="pages/tourism/nature.php">Nature</a>
                        <a href="pages/tourism/industry.php">Industry Zone</a>
                        <a href="pages/tourism/resort.php">Resort</a>
                        <a href="pages/tourism/churches.php">Churches</a>
                    </div>
                    <div class="mega-column">
                        <h4>Culture &amp; Services</h4>
                        <a href="pages/tourism/fiestas.php">Fiestas</a>
                        <a href="pages/tourism/people.php">People of Malvar</a>
                        <a href="pages/tourism/services.php">Other Services</a>
                    </div>
                </div>
            </div>
            <a href="pages/tourism/restaurants.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg></span><span>RESTAURANTS</span></a>
            <a href="pages/tourism/accommodation.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 12v8"/><path d="M2 12h20"/><path d="M2 8h6a2 2 0 0 1 2 2v2"/><path d="M22 8h-6a2 2 0 0 0-2 2v2"/></svg></span><span>ACCOMMODATION</span></a>
            <a href="pages/tourism/banks.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M2 10h20L12 4z"/><path d="M8 14v4M12 14v4M16 14v4"/></svg></span><span>BANKS</span></a>
            <div class="dropdown">
                <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></span><span>MORE ▾</span></a>
                <div class="mega-menu mega-menu-simple">
                    <div class="mega-column">
                        <a href="pages/foryou.php">For You</a>
                        <a href="pages/traveldiary.php">Travel Diary</a>
                        <?php if ($isLoggedIn): ?><a href="pages/profile.php">My Profile</a><?php endif; ?>
                        <a href="pages/favorites.php">Favorites</a>
                        <a href="pages/mostpopular.php">Most Popular</a>
                        <a href="pages/about.php">About</a>
                    </div>
                </div>
            </div>
            <?php if ($isAdmin): ?><a href="admin/admindashboard.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg></span><span>ADMIN</span></a><?php endif; ?>
            <?php if ($isLoggedIn): ?><a href="auth/logout.php" class="nav-item nav-signout-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span><span>SIGN OUT</span></a><?php else: ?><a href="auth/login.php" class="nav-item nav-signin-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg></span><span>SIGN IN</span></a><?php endif; ?>
        </nav>
<div class="navbar-search" data-api="pages/search_api.php" data-results="pages/search.php">
        <button type="button" class="navbar-search-toggle" aria-label="Search" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </button>
        <form class="navbar-search-form" action="pages/search.php" method="GET">
            <input type="search" name="q" class="navbar-search-input" placeholder="Search KulToura…" autocomplete="off">
        </form>
        <div class="navbar-search-dropdown"></div>
    </div>
<button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </header>
    <main class="hero-content hero-content-split">
        <div class="hero-text-col">
            <h1 class="brand-title brand-title-logo">
                <img src="assets/images/kultoura.png" alt="KulToura — Malvar, Batangas · Cultural Tourism">
            </h1>
            <p class="hero-mobile-tagline">Discover the culture, people, and local experiences of Malvar, Batangas.</p>
            <div class="cta-buttons">
                <button class="btn btn-filled" id="exploreBtn">
                    EXPLORE CULTURE
                </button>
                <button class="btn btn-outline" id="recommendBtn">
                    GET RECOMMENDATIONS
                </button>
            </div>
        </div>
</main>
    <button type="button" class="scroll-hint" id="scrollHintBtn" aria-label="Scroll to explore">
        <span class="scroll-line"></span>
        <span class="scroll-hint-label-desktop">DISCOVER</span>
        <span class="scroll-hint-label-mobile">Scroll</span>
        <span class="scroll-hint-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
    </button>
    <span class="hero-corner-leaf hero-corner-leaf-left" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
    <span class="hero-corner-leaf hero-corner-leaf-right" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
    <div class="torn-edge" aria-hidden="true">
        <svg viewBox="0 0 1440 48" preserveAspectRatio="none">
            <path d="M0,24 Q60,4 120,24 Q180,44 240,24 Q300,4 360,24 Q420,44 480,24 Q540,4 600,24 Q660,44 720,24 Q780,4 840,24 Q900,44 960,24 Q1020,4 1080,24 Q1140,44 1200,24 Q1260,4 1320,24 Q1380,44 1440,24 L1440,48 L0,48 Z" fill="#f0ebd8"/>
        </svg>
    </div>
</div>
<section class="home-foryou">
    <div class="home-foryou-top reveal">
        <div class="home-foryou-intro">
            <p class="home-eyebrow home-eyebrow-rust">CURATED FOR YOU</p>
            <h2 class="home-heading home-heading-barabara">For You</h2>
        </div>
    </div>
    <?php if (!empty($forYouPreview)): ?>
    <div class="home-instagram-grid reveal">
            <?php foreach ($forYouPreview as $item): ?>
            <article class="home-instagram-post">
                <div class="home-instagram-post-head">
                    <span class="home-instagram-avatar" aria-hidden="true">K</span>
                    <div class="home-instagram-account"><strong>kultoura</strong><span><?php echo htmlspecialchars($item['badge']); ?> · Malvar, Batangas</span></div>
                    <span class="home-instagram-more" aria-hidden="true">•••</span>
                </div>
                <a href="<?php echo htmlspecialchars($item['link']); ?>" class="home-instagram-media" aria-label="Explore <?php echo htmlspecialchars($item['name']); ?>">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy">
                    <span class="home-instagram-category" style="--post-accent:<?php echo htmlspecialchars($item['badgeColor']); ?>"><?php echo htmlspecialchars(strtoupper($item['badge'])); ?></span>
                </a>
                <div class="home-instagram-caption">
                    <p><strong><?php echo htmlspecialchars($item['name']); ?></strong> <?php echo htmlspecialchars(mb_strimwidth(trim((string) $item['desc']), 0, 125, '…')); ?></p>
                </div>
            </article>
            <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php if (!empty($homeUpcoming)): ?>
<section class="home-upcoming">
    <div class="home-upcoming-top reveal">
        <div>
            <p class="home-eyebrow home-eyebrow-rust">UPCOMING</p>
            <h2 class="home-heading home-heading-barabara">Discover What's Next</h2>
            <p class="home-upcoming-desc">See upcoming fiestas and Mass schedules around Malvar.</p>
        </div>
        <aside class="home-schedule-panel" aria-label="Upcoming schedule">
            <div class="home-schedule-heading">
                <span class="home-schedule-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span>
                <div><strong>Schedule</strong><span><?php echo htmlspecialchars($homeCalendarMonth->format('F Y')); ?></span></div>
            </div>
            <div class="home-calendar-grid" role="grid" aria-label="<?php echo htmlspecialchars($homeCalendarMonth->format('F Y')); ?>">
                <?php foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $weekday): ?><span class="home-calendar-weekday" role="columnheader"><?php echo $weekday; ?></span><?php endforeach; ?>
                <?php
                    $calendarCellCount = (int) (ceil(($homeCalendarOffset + $homeCalendarDays) / 7) * 7);
                    for ($cell = 0; $cell < $calendarCellCount; $cell++):
                        $day = $cell - $homeCalendarOffset + 1;
                        if ($day < 1 || $day > $homeCalendarDays):
                            echo '<span class="home-calendar-day is-empty" role="gridcell"></span>';
                            continue;
                        endif;
                        $dateIso = $homeCalendarMonth->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                        $scheduledTitles = $homeCalendarScheduledDays[$day] ?? [];
                        $dayClasses = ['home-calendar-day'];
                        if ($dateIso === $homeCalendarToday) $dayClasses[] = 'is-today';
                        if ($scheduledTitles) $dayClasses[] = 'has-schedule';
                        $dayLabel = $homeCalendarMonth->format('F') . ' ' . $day;
                        if ($scheduledTitles) $dayLabel .= ': ' . implode(', ', $scheduledTitles);
                ?>
                    <span class="<?php echo implode(' ', $dayClasses); ?>" role="gridcell" aria-label="<?php echo htmlspecialchars($dayLabel); ?>">
                        <?php echo $day; ?><?php if ($scheduledTitles): ?><i aria-hidden="true"></i><?php endif; ?>
                    </span>
                <?php endfor; ?>
            </div>
            <div class="home-schedule-list">
                <?php foreach ($homeSchedulePreview as $scheduleItem): $scheduleDate = new DateTimeImmutable($scheduleItem['date']); ?>
                    <a class="home-schedule-item" href="<?php echo htmlspecialchars($scheduleItem['href']); ?>">
                        <time datetime="<?php echo htmlspecialchars($scheduleDate->format('Y-m-d')); ?>"><strong><?php echo htmlspecialchars($scheduleDate->format('d')); ?></strong><span><?php echo htmlspecialchars(strtoupper($scheduleDate->format('M'))); ?></span></time>
                        <span class="home-schedule-item-copy"><strong><?php echo htmlspecialchars($scheduleItem['title']); ?></strong><small><?php echo htmlspecialchars($scheduleItem['time'] !== '' ? $scheduleItem['time'] : $scheduleItem['type']); ?></small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
    <div class="home-upcoming-carousel reveal" id="homeUpcomingCarousel">
        <?php foreach ($homeUpcoming as $u):
            $uImg = $u['image'];
            $uDay = date('d', strtotime($u['date']));
            $uMon = date('M', strtotime($u['date']));
            $uYear = date('Y', strtotime($u['date']));
        ?>
            <a class="home-upcoming-card<?php echo $u['kind'] === 'mass' ? ' home-upcoming-card-mass' : ''; ?>" href="<?php echo htmlspecialchars($u['href']); ?>">
                <div class="home-upcoming-media">
                    <?php if ($uImg): ?>
                        <img src="<?php echo htmlspecialchars($uImg); ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="home-upcoming-media-fallback"></span>
                    <?php endif; ?>
                    <span class="home-upcoming-date-badge">
                        <span class="home-upcoming-date-day"><?php echo htmlspecialchars($uDay); ?></span>
                        <span class="home-upcoming-date-mon"><?php echo htmlspecialchars(strtoupper($uMon)); ?><br><?php echo htmlspecialchars($uYear); ?></span>
                    </span>
                </div>
                <div class="home-upcoming-body">
                    <p class="home-upcoming-type"><?php echo htmlspecialchars(strtoupper($u['type'])); ?></p>
                    <h3 class="home-upcoming-title"><?php echo htmlspecialchars($u['title']); ?></h3>
                    <?php if ($u['time'] !== ''): ?><p class="home-upcoming-time"><?php echo htmlspecialchars($u['time']); ?></p><?php endif; ?>
                    <p class="home-upcoming-loc"><?php echo htmlspecialchars($u['location']); ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php if (!empty($homeNews)): ?>
<section class="home-news">
    <div class="home-news-top reveal">
        <p class="home-eyebrow home-eyebrow-gold">LATEST NEWS</p>
        <h2 class="home-heading home-heading-barabara">News &amp; Announcements</h2>
    </div>
    <?php
        $homeNewsFeatured = $homeNews[0];
        $homeNewsRest = array_slice($homeNews, 1);
        $fMeta = $homeNewsTypeMeta[$homeNewsFeatured['type']] ?? ['label' => ucfirst($homeNewsFeatured['type']), 'color' => '#8a8a5c'];
    ?>
    <div class="home-news-featured reveal">
        <div class="home-news-featured-media">
            <?php if (!empty($homeNewsFeatured['image'])): ?>
                <img src="<?php echo htmlspecialchars($homeNewsFeatured['image']); ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="home-news-photo-fallback" style="background:<?php echo htmlspecialchars($fMeta['color']); ?>1a;color:<?php echo htmlspecialchars($fMeta['color']); ?>;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M7 14v5a2 2 0 0 0 2 2h1"/></svg>
                </span>
            <?php endif; ?>
            <span class="home-news-badge" style="background:<?php echo htmlspecialchars($fMeta['color']); ?>"><?php echo htmlspecialchars($fMeta['label']); ?></span>
        </div>
        <div class="home-news-featured-body">
            <p class="home-news-date"><?php echo htmlspecialchars(date('F j, Y', strtotime($homeNewsFeatured['published_at'] ?? $homeNewsFeatured['created_at']))); ?></p>
            <h3 class="home-news-featured-title"><?php echo htmlspecialchars($homeNewsFeatured['title']); ?></h3>
            <p class="home-news-excerpt"><?php echo htmlspecialchars(mb_strimwidth(trim((string) $homeNewsFeatured['body']), 0, 220, '…')); ?></p>
        </div>
    </div>
    <?php if (!empty($homeNewsRest)): ?>
    <div class="home-news-grid reveal">
        <?php foreach ($homeNewsRest as $n): ?>
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
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="home-about">
    <div class="home-about-text reveal">
        <p class="home-eyebrow home-eyebrow-gold">ABOUT MALVAR</p>
        <h2 class="home-heading home-heading-light home-heading-barabara">About Malvar</h2>
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
    <div class="home-about-photo reveal">
        <img src="<?php echo htmlspecialchars($aboutPhoto['image']); ?>" alt="<?php echo htmlspecialchars($aboutPhoto['name']); ?>" loading="lazy">
        <span class="home-about-leaf home-about-leaf-1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
        <span class="home-about-leaf home-about-leaf-2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14z"/><path d="M5 19c2-4 5-7 9-9"/></svg></span>
    </div>
    <?php endif; ?>
</section>
<?php if (!empty($homePartners)): ?>
<section class="home-partners">
    <div class="home-partners-top reveal">
        <p class="home-eyebrow home-eyebrow-gold">OUR PARTNERS</p>
        <h2 class="home-heading home-heading-barabara">Together with Malvar</h2>
        <p class="home-partners-desc">Together in the shared vision of a stronger, forward-looking tourism industry, our valued partners help bring Malvar closer to everyone who visits.</p>
    </div>
    <div class="home-partners-grid reveal">
        <?php foreach ($homePartners as $p): ?>
            <?php
                $backInner = '<p class="home-partner-back-name">' . htmlspecialchars($p['name']) . '</p>'
                    . (!empty($p['description']) ? '<p class="home-partner-back-desc">' . htmlspecialchars($p['description']) . '</p>' : '')
                    . (!empty($p['website_url']) ? '<span class="home-partner-back-cta">Visit website →</span>' : '');
            ?>
            <?php if (!empty($p['website_url'])): ?>
                <a class="home-partner-card" href="<?php echo htmlspecialchars($p['website_url']); ?>" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($p['name']); ?>">
                    <span class="home-partner-flip-inner">
                        <span class="home-partner-face home-partner-face-front"><img src="<?php echo htmlspecialchars($p['logo']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy"></span>
                        <span class="home-partner-face home-partner-face-back"><?php echo $backInner; ?></span>
                    </span>
                </a>
            <?php else: ?>
                <div class="home-partner-card" title="<?php echo htmlspecialchars($p['name']); ?>">
                    <span class="home-partner-flip-inner">
                        <span class="home-partner-face home-partner-face-front"><img src="<?php echo htmlspecialchars($p['logo']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy"></span>
                        <span class="home-partner-face home-partner-face-back"><?php echo $backInner; ?></span>
                    </span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php if (!empty($homeEventGallery)): ?>
<section class="home-gallery">
    <div class="home-gallery-top reveal">
        <p class="home-eyebrow home-eyebrow-rust">EVENTS IN MALVAR</p>
        <h2 class="home-heading home-heading-barabara">Moments We've Shared</h2>
    </div>
    <div class="home-gallery-grid reveal">
        <?php foreach ($homeEventGallery as $g): ?>
            <button type="button" class="home-gallery-tile" aria-label="View moment: <?php echo htmlspecialchars($g['caption']); ?>" data-gallery-image="<?php echo htmlspecialchars($g['image']); ?>" data-gallery-caption="<?php echo htmlspecialchars($g['caption']); ?>">
                <img src="<?php echo htmlspecialchars($g['image']); ?>" alt="" loading="lazy">
                <span class="home-gallery-overlay"><span class="home-gallery-view-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></span><span class="home-gallery-caption"><?php echo htmlspecialchars($g['caption']); ?></span></span>
            </button>
        <?php endforeach; ?>
    </div>
    <dialog class="home-gallery-dialog" id="homeGalleryDialog" aria-label="Event moment">
        <button type="button" class="home-gallery-dialog-close" aria-label="Close photo">&times;</button>
        <img id="homeGalleryDialogImage" src="" alt="">
        <p id="homeGalleryDialogCaption"></p>
    </dialog>
</section>
<?php endif; ?>
<footer class="home-footer">
    <div class="home-footer-top">
        <div class="home-footer-brand">
            <div class="home-footer-logo"><img src="assets/images/kultoura.png" alt="KulToura"></div>
            <div class="home-footer-social">
                <a href="https://www.facebook.com/MalvarBatangasOfficial" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8.4h2.8l.4-3.3h-3.2V8.1c0-.9.3-1.6 1.7-1.6h1.7V3.5C16.6 3.4 15.5 3.3 14.3 3.3c-2.6 0-4.3 1.6-4.3 4.4v2.6H7.2v3.3h2.8V22h3.5z"/></svg></a>
                <a href="https://maps.app.goo.gl/jnx86pEBNWb8op8QA" target="_blank" rel="noopener" aria-label="Location"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></a>
                <a href="mailto:malvartourismoffice@gmail.com" aria-label="Email"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></a>
            </div>
        </div>
        <div class="home-footer-col footer-accordion">
            <h4 onclick="toggleFooterAccordion(this)">Explore <span class="footer-accordion-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></span></h4>
            <div class="footer-accordion-body">
                <a href="index.php">Home</a>
                <a href="pages/foryou.php">For You</a>
                <a href="pages/tourism.php">Tourism</a>
                <a href="pages/traveldiary.php">Travel Diary</a>
                <a href="pages/favorites.php">Favorites</a>
                <a href="pages/mostpopular.php">Most Popular</a>
            </div>
        </div>
        <div class="home-footer-col footer-accordion">
            <h4 onclick="toggleFooterAccordion(this)">Resources <span class="footer-accordion-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></span></h4>
            <div class="footer-accordion-body">
                <a href="pages/about.php">About</a>
                <a href="#">Contact Us</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Help Center</a>
            </div>
        </div>
        <div class="home-footer-col home-footer-newsletter">
    <h4>Newsletter</h4>
    <p>Enter your email and we'll send you the latest and upcoming announcements from KULTOURA.</p>
    <form class="home-newsletter-form" id="homeNewsletterForm">
        <input type="email" name="email" placeholder="Enter your email" required>
        <button type="submit" aria-label="Subscribe"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
    </form>
    <p class="home-newsletter-msg" id="homeNewsletterMsg" hidden></p>
</div>
    </div>
    <div class="home-footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> KULTOURA. All rights reserved.</p>
        <button type="button" class="home-scroll-top" id="homeScrollTop" aria-label="Scroll to top"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M6 11l6-6 6 6"/></svg></button>
    </div>
</footer>
<script src="assets/js/navbar.js"></script>
<script src="assets/js/navbar-search.js"></script>
<script src="assets/js/home.js"></script>
</body>
</html>
<?php
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); } else { @ob_flush(); flush(); }
kt_newsletter_dispatch_pending($conn);
?>
