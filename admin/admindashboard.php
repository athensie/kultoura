<?php
session_start();

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 | This page lives one folder deep (/kultoura/admin/admindashboard.php),
 | so a plain header("Location: index.php") would resolve relative to
 | /admin/ and break. Anchoring every redirect to a fixed base path
 | avoids that, regardless of which folder a script is called from.
 |
 | Change this if your project folder name/path is different.
 */
define('BASE_URL', '/kultoura');

/*
 |--------------------------------------------------------------------
 | ACCESS CONTROL
 |--------------------------------------------------------------------
 | Only logged-in users with the "admin" or "super admin" role may
 | view this page. Anyone else (including guests) is bounced to
 | the real site root index.php — mirrors the redirect logic already
 | used in login.php.
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');

if (!in_array($role, ['admin', 'super admin'], true)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminRole = $_SESSION['role'] ?? 'Admin';

/*
 |--------------------------------------------------------------------
 | DATABASE CONNECTION
 |--------------------------------------------------------------------
 | Same connection admindestinations.php / adminfoodanddining.php use,
 | giving a mysqli handle in $conn.
 */
include '../config/dbmain.php';
include '../config/analytics.php';

/*
 |--------------------------------------------------------------------
 | PERIOD SELECTOR
 |--------------------------------------------------------------------
 | This is a plain PHP page (no AJAX endpoint), so "Today / This Week /
 | This Month / This Year" works via a real query string + page reload
 | rather than a client-side-only toggle. See the "Recently Added"
 | block further down for the one panel this genuinely drives with
 | real data — the rest of the note there explains why the other
 | panels (Trending, Traffic Sources, Monthly Visitor Trend) can't be
 | period-filtered yet.
 */
$validPeriods = ['today', 'week', 'month', 'year'];
$period = $_GET['period'] ?? 'month';
if (!in_array($period, $validPeriods, true)) {
    $period = 'month';
}
$periodLabels = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];

$periodStart = new DateTime('today');
switch ($period) {
    case 'week':  $periodStart = new DateTime('monday this week'); break;
    case 'month': $periodStart = new DateTime('first day of this month'); break;
    case 'year':  $periodStart = new DateTime('first day of january this year'); break;
    // 'today' keeps the default (today at midnight)
}
$periodStartSql = $periodStart->format('Y-m-d 00:00:00');

/*
 |--------------------------------------------------------------------
 | ANALYTICS DATA — wired to the real tables behind Destinations,
 | Food & Dining, Fiestas, and (as of config/analytics.php) real
 | page-view logging across every public page.
 |--------------------------------------------------------------------
 */

// Active Users — visitors with a heartbeat (config/dbmain.php stamps
// this on every request from a logged-in session) in the last 5 minutes.
$activeUsers = analytics_active_users($conn, 5);

// Listed Destinations — currently-active destinations right now (a
// snapshot total, not sliced by period — this answers "how many are
// live", not "how many this month").
$listedDestinations = 0;
if ($res = $conn->query("SELECT COUNT(*) c FROM destination WHERE status = 'active'")) {
    $listedDestinations = (int) ($res->fetch_assoc()['c'] ?? 0);
}

// Most Popular Category — scoped to Food & Dining (Products +
// Restaurants), matching the utensils icon on this card. Whichever
// category has the most listings across both tables wins.
$topCategory = '—';
$categoryCounts = [];
if ($res = $conn->query("SELECT category, COUNT(*) c FROM products WHERE category IS NOT NULL AND category <> '' GROUP BY category")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $categoryCounts[$row['category']] = ($categoryCounts[$row['category']] ?? 0) + (int) $row['c'];
    }
}
if ($res = $conn->query("SELECT category, COUNT(*) c FROM restaurants WHERE category IS NOT NULL AND category <> '' GROUP BY category")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        $categoryCounts[$row['category']] = ($categoryCounts[$row['category']] ?? 0) + (int) $row['c'];
    }
}
if (!empty($categoryCounts)) {
    arsort($categoryCounts);
    $topCategory = array_key_first($categoryCounts);
}
$topCategoryCount = $categoryCounts[$topCategory] ?? 0;

// Total Page Views — real, sitewide, period-aware. Every public page now
// calls analytics_track() on load (see config/analytics.php).
$totalPageViews = analytics_total_views($conn, $periodStartSql);

// Most Viewed Pages — replaces the old "Top Destinations by Visits"
// panel, which relied on a `destination.views` counter that nothing in
// the codebase ever actually incremented (always 0). This is genuinely
// real and period-aware, and it covers every content type, not just
// destinations.
$topPages = analytics_page_breakdown($conn, $periodStartSql, 5);
$topPagesMax = !empty($topPages) ? max(array_column($topPages, 'views')) : 0;
foreach ($topPages as &$tp) {
    $tp['pct'] = $topPagesMax > 0 ? (int) round($tp['views'] / $topPagesMax * 100) : 0;
}
unset($tp);

// Most Viewed Places — real per-listing view counts (e.g. "how many
// visitors did BFC have?"), joined back to actual names across every
// catalog type. See config/analytics.php's item_views table, logged
// whenever a visitor opens a listing's "View Details" action.
$itemTypeLabels = ['nature' => 'Nature', 'resort' => 'Resort', 'industry' => 'Industry', 'product' => 'Product', 'restaurant' => 'Restaurant', 'fiesta' => 'Fiesta', 'person' => 'Person'];
$topItems = analytics_top_items($conn, 6, $periodStartSql);

// Recently Added — genuinely period-aware, since created_at exists on
// all four content tables. Pulls the newest listings across
// Destinations, Products, Restaurants, and Fiestas within the
// selected period.
$trending = [];
$recentUnion = [];
if ($res = $conn->query("SELECT destination_name AS name, category AS sub, created_at FROM destination WHERE created_at >= '$periodStartSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Destination · ' . ucfirst($r['sub']), 'created_at' => $r['created_at']];
    }
}
if ($res = $conn->query("SELECT product_name AS name, category AS sub, created_at FROM products WHERE created_at >= '$periodStartSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Product' . (!empty($r['sub']) ? ' · ' . $r['sub'] : ''), 'created_at' => $r['created_at']];
    }
}
if ($res = $conn->query("SELECT restaurant_name AS name, category AS sub, created_at FROM restaurants WHERE created_at >= '$periodStartSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Restaurant' . (!empty($r['sub']) ? ' · ' . $r['sub'] : ''), 'created_at' => $r['created_at']];
    }
}
if ($res = $conn->query("SELECT fiesta_name AS name, location AS sub, created_at FROM fiestas WHERE created_at >= '$periodStartSql' ORDER BY created_at DESC")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
        $recentUnion[] = ['name' => $r['name'], 'sub' => 'Fiesta' . (!empty($r['sub']) ? ' · ' . $r['sub'] : ''), 'created_at' => $r['created_at']];
    }
}
usort($recentUnion, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
$recentUnion = array_slice($recentUnion, 0, 6);
foreach ($recentUnion as $r) {
    $trending[] = [
        'name'   => $r['name'],
        'sub'    => $r['sub'],
        'visits' => date('M j', strtotime($r['created_at'])), // repurposed: "added on" date, not a visit count
    ];
}

// Content Engagement by Category — real content mix across all four
// tables (all-time share, not period-filtered — a composition metric).
$categoryShare = [];
$destCount = 0;    if ($r = $conn->query("SELECT COUNT(*) c FROM destination"))  $destCount    = (int) ($r->fetch_assoc()['c'] ?? 0);
$prodCount = 0;    if ($r = $conn->query("SELECT COUNT(*) c FROM products"))     $prodCount    = (int) ($r->fetch_assoc()['c'] ?? 0);
$restCount = 0;    if ($r = $conn->query("SELECT COUNT(*) c FROM restaurants"))  $restCount    = (int) ($r->fetch_assoc()['c'] ?? 0);
$fiestaCount = 0;  if ($r = $conn->query("SELECT COUNT(*) c FROM fiestas"))      $fiestaCount  = (int) ($r->fetch_assoc()['c'] ?? 0);
$contentTotal = $destCount + $prodCount + $restCount + $fiestaCount;
if ($contentTotal > 0) {
    $categoryShare = [
        ['label' => 'Destinations', 'pct' => (int) round($destCount   / $contentTotal * 100), 'color' => '#C9572A'],
        ['label' => 'Products',     'pct' => (int) round($prodCount   / $contentTotal * 100), 'color' => '#6b8f47'],
        ['label' => 'Restaurants',  'pct' => (int) round($restCount   / $contentTotal * 100), 'color' => '#4a90e2'],
        ['label' => 'Fiestas',      'pct' => (int) round($fiestaCount / $contentTotal * 100), 'color' => '#C8A96E'],
    ];
}

// Donut gradient stops for the Content Engagement chart (real CSS
// conic-gradient, computed from $categoryShare above).
$donutStops = [];
$cursor = 0;
foreach ($categoryShare as $c) {
    $start = $cursor;
    $cursor += $c['pct'];
    $donutStops[] = "{$c['color']} {$start}% {$cursor}%";
}
$donutGradient = !empty($donutStops) ? implode(', ', $donutStops) : '#333 0% 100%';

// Monthly Visitor Trend — real, from the page_views log (last 6 months).
// analytics_monthly_trend() always returns one row per month even when
// its count is 0, so empty($monthlyVisitors) alone can't detect "no
// data yet" — check whether any month actually has views instead.
$monthlyVisitors = analytics_monthly_trend($conn, 6);
$hasMonthlyData = array_sum(array_column($monthlyVisitors, 'value')) > 0;

// Traffic Sources — real, from the page_views log's referrer, scoped to
// the selected period.
$trafficSources = analytics_traffic_sources($conn, $periodStartSql);

// User Activity Summary — real counts for the selected period: new
// visitor accounts created, and admin logins recorded.
$newSignups = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM users WHERE created_at >= ?");
$stmt->bind_param('s', $periodStartSql);
$stmt->execute();
$newSignups = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$adminLogins = 0;
$stmt = $conn->prepare("SELECT COUNT(*) c FROM admins WHERE last_login >= ?");
$stmt->bind_param('s', $periodStartSql);
$stmt->execute();
$adminLogins = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

/* ============================================================
   PREDICTIVE ANALYTICS
   ------------------------------------------------------------
   Transparent trend math over the real page_views log — no
   black-box model, so the number is always explainable: it's
   just the average month-over-month change applied forward, and
   week-over-week growth per page.
============================================================ */

// Next month's projected page views — a least-squares trend line over
// the real monthly totals (all 6 months, zero-filled, in chronological
// order). The old method averaged month-over-month % changes after
// dropping zero-view months, which (a) compared non-adjacent months as
// if they were consecutive, and (b) let a single jump between small
// numbers (e.g. 2 views -> 6 views = "+200%") dominate the whole
// forecast. A trend line over the full series — plus a minimum sample
// size before showing a number at all — keeps the projection from
// overreacting to sparse/noisy traffic.
$projectedNextMonth = null;
$projectedGrowthPct = null;
$monthsWithData = array_filter($monthlyVisitors, fn($m) => $m['value'] > 0);
$totalRecentViews = array_sum(array_column($monthlyVisitors, 'value'));
if (count($monthsWithData) >= 3 && $totalRecentViews >= 10) {
    $n = count($monthlyVisitors);
    $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
    foreach (array_values($monthlyVisitors) as $i => $m) {
        $sumX  += $i;
        $sumY  += $m['value'];
        $sumXY += $i * $m['value'];
        $sumXX += $i * $i;
    }
    $denominator = ($n * $sumXX) - ($sumX * $sumX);
    $slope = $denominator != 0 ? ((($n * $sumXY) - ($sumX * $sumY)) / $denominator) : 0;
    $intercept = ($sumY - ($slope * $sumX)) / $n;

    $projectedNextMonth = max(0, (int) round($intercept + ($slope * $n)));

    // % change vs. the latest actual month (falling back to the series
    // average if the latest month happens to be zero), clamped so a
    // noisy trend line can't produce a triple-digit headline number.
    $latest = end($monthlyVisitors)['value'];
    $baseline = $latest > 0 ? $latest : ($sumY / $n);
    $projectedGrowthPct = $baseline > 0
        ? (int) round((($projectedNextMonth - $baseline) / $baseline) * 100)
        : 0;
    $projectedGrowthPct = max(-90, min(200, $projectedGrowthPct));
}

// Pages gaining momentum: this week's views vs last week's, per page.
$trendingPages = [];
$thisWeekStart = (new DateTime('monday this week'))->format('Y-m-d 00:00:00');
$lastWeekStart = (new DateTime('monday last week'))->format('Y-m-d 00:00:00');
$thisWeekCounts = [];
$lastWeekCounts = [];
if ($res = $conn->query("SELECT page, COUNT(*) c FROM page_views WHERE viewed_at >= '$thisWeekStart' GROUP BY page")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) $thisWeekCounts[$r['page']] = (int) $r['c'];
}
if ($res = $conn->query("SELECT page, COUNT(*) c FROM page_views WHERE viewed_at >= '$lastWeekStart' AND viewed_at < '$thisWeekStart' GROUP BY page")) {
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) $lastWeekCounts[$r['page']] = (int) $r['c'];
}
// A page going from 1 view to 3 views is technically "+200%" but it's
// noise, not momentum — require a minimum combined view count before a
// page's week-over-week swing counts as a real trend, and cap the
// displayed % so a small-number spike can't read as a huge one.
$pageLabels = analytics_page_labels();
$MIN_MOMENTUM_VIEWS = 5;
foreach ($thisWeekCounts as $page => $count) {
    $prevCount = $lastWeekCounts[$page] ?? 0;
    if (($count + $prevCount) < $MIN_MOMENTUM_VIEWS) {
        continue;
    }
    if ($prevCount > 0) {
        $growth = (int) round((($count - $prevCount) / $prevCount) * 100);
    } else {
        $growth = 100; // brand-new activity this week, past the minimum-views gate above
    }
    if ($growth > 0) {
        $trendingPages[] = ['label' => $pageLabels[$page] ?? ucfirst($page), 'growth' => min($growth, 300), 'views' => $count];
    }
}
usort($trendingPages, fn($a, $b) => $b['growth'] <=> $a['growth']);
$trendingPages = array_slice($trendingPages, 0, 4);

/* ============================================================
   PRESCRIPTIVE ANALYTICS
   ------------------------------------------------------------
   Rule-based recommendations derived from the real numbers
   above. Each one only appears if its condition is actually
   true right now — nothing here is a fixed, static list.
============================================================ */
$recommendations = [];

if ($activeUsers === 0) {
    $recommendations[] = ['icon' => 'users', 'level' => 'info',
        'text' => 'No visitors are active right now — consider timing social posts or announcements for higher-traffic hours.'];
}
if ($totalPageViews === 0) {
    $recommendations[] = ['icon' => 'eye-off', 'level' => 'warn',
        'text' => 'No page views logged ' . strtolower($periodLabels[$period]) . ' yet — share the site link to start building traffic data.'];
} elseif (!empty($topPages) && $topPagesMax > 0) {
    $lowest = end($topPages);
    if ($lowest['views'] > 0 && $lowest['views'] < $topPagesMax * 0.2) {
        $recommendations[] = ['icon' => 'megaphone', 'level' => 'info',
            'text' => '"' . $lowest['label'] . '" is getting comparatively little traffic — consider featuring it on the homepage or in an announcement.'];
    }
}
if ($listedDestinations < 5) {
    $recommendations[] = ['icon' => 'map-pin', 'level' => 'warn',
        'text' => 'Only ' . $listedDestinations . ' destination(s) are listed — adding more gives tourists more reasons to keep exploring the site.'];
}
if ($topCategoryCount === 0) {
    $recommendations[] = ['icon' => 'utensils', 'level' => 'warn',
        'text' => 'No Food & Dining listings yet — this is often the most-browsed category on a tourism site.'];
}
foreach ($categoryShare as $c) {
    if ($c['pct'] >= 60) {
        $recommendations[] = ['icon' => 'pie-chart', 'level' => 'info',
            'text' => $c['label'] . ' makes up ' . $c['pct'] . '% of all listings — balancing content across categories could broaden appeal.'];
    }
}
if (!empty($trendingPages)) {
    $top = $trendingPages[0];
    $recommendations[] = ['icon' => 'trending-up', 'level' => 'good',
        'text' => '"' . $top['label'] . '" is trending up ' . $top['growth'] . '% week-over-week — consider spotlighting it while interest is high.'];
}
if (empty($recommendations)) {
    $recommendations[] = ['icon' => 'check-circle', 'level' => 'good',
        'text' => 'No urgent issues detected right now — content mix and traffic look healthy for the current data.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics – KULTOURA Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>

    <link rel="stylesheet" href="../assets/css/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <style>.period-selector .period-btn { text-decoration: none; display: inline-block; }</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar"><i data-lucide="chevrons-left" class="lucide"></i></button>
    <div class="sidebar-logo">KulToura</div>
    <div class="sidebar-tagline">Malvar, Batangas</div>

    <div class="sidebar-section">Overview</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindashboard.php" class="active"><span class="nav-icon"><i data-lucide="bar-chart-2" class="lucide"></i></span> Analytics</a></li>
    </ul>

    <div class="sidebar-section">Content</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindestinations.php"><span class="nav-icon"><i data-lucide="map-pin" class="lucide"></i></span> Destinations</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminfoodanddining.php"><span class="nav-icon"><i data-lucide="utensils" class="lucide"></i></span> Food &amp; Dining</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/admineventandfiesta.php"><span class="nav-icon"><i data-lucide="calendar-heart" class="lucide"></i></span> Events &amp; Fiesta</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpeople.php"><span class="nav-icon"><i data-lucide="users-round" class="lucide"></i></span> People of Malvar</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsitecontent.php"><span class="nav-icon"><i data-lucide="image" class="lucide"></i></span> Site Content</a></li>
    </ul>

    <div class="sidebar-section">Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminannouncements.php"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsettings.php"><span class="nav-icon"><i data-lucide="settings" class="lucide"></i></span> Settings</a></li>
    </ul>

    <div class="sidebar-footer">
        <div class="admin-avatar">
            <div class="avatar-circle"><i data-lucide="user" class="lucide" style="width:1.1rem;height:1.1rem;"></i></div>
            <div>
                <div class="avatar-name"><?php echo htmlspecialchars($adminName); ?></div>
                <div class="avatar-role"><?php echo htmlspecialchars($adminRole); ?></div>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/auth/auth.php?action=logout" class="logout-link">
            <i data-lucide="log-out" class="lucide" style="width:.9rem;height:.9rem;"></i> Log Out
        </a>
    </div>
</aside>

<button class="hamburger-btn" onclick="toggleSidebar()"><i data-lucide="menu" class="lucide"></i></button>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="dash-wrapper">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <div class="section-label">Tourism Insights</div>
                <h1>Analytics <em>Dashboard</em></h1>
                <p>Overview of tourism activity, engagement, and content performance for Malvar, Batangas. Figures update automatically as visitors use the site.</p>
            </div>
            <div class="period-selector">
                <a href="?period=today" class="period-btn<?php echo $period === 'today' ? ' active' : ''; ?>">Today</a>
                <a href="?period=week" class="period-btn<?php echo $period === 'week' ? ' active' : ''; ?>">This Week</a>
                <a href="?period=month" class="period-btn<?php echo $period === 'month' ? ' active' : ''; ?>">This Month</a>
                <a href="?period=year" class="period-btn<?php echo $period === 'year' ? ' active' : ''; ?>">This Year</a>
            </div>
        </div>

        <!-- 1. TRENDING & HIGHLY VISITED DESTINATIONS -->
        <div class="alabel-desc">Trending &amp; Highly Visited Destinations <span class="analytics-tag">Descriptive</span></div>

        <div class="kpi-grid animate">
            <div class="kpi-card accent-clay">
                <div class="kpi-icon"><i data-lucide="users" class="lucide"></i></div>
                <div class="kpi-num"><?php echo htmlspecialchars((string) $activeUsers); ?></div>
                <div class="kpi-label">Active Users</div>
                <div class="kpi-change"><?php echo $activeUsers > 0 ? 'Online in the last 5 minutes' : 'No one online right now'; ?></div>
            </div>
            <div class="kpi-card accent-green">
                <div class="kpi-icon"><i data-lucide="map-pin" class="lucide"></i></div>
                <div class="kpi-num"><?php echo htmlspecialchars((string) $listedDestinations); ?></div>
                <div class="kpi-label">Listed Destinations</div>
                <div class="kpi-change"><?php echo $listedDestinations > 0 ? 'Currently live on the site' : 'No destinations yet'; ?></div>
            </div>
            <div class="kpi-card accent-gold">
                <div class="kpi-icon"><i data-lucide="utensils" class="lucide"></i></div>
                <div class="kpi-num" style="font-size:1.6rem;"><?php echo htmlspecialchars($topCategory); ?></div>
                <div class="kpi-label">Most Popular Category</div>
                <div class="kpi-change"><?php echo $topCategoryCount > 0 ? $topCategoryCount . ' Food & Dining listings' : 'No Food & Dining listings yet'; ?></div>
            </div>
            <div class="kpi-card accent-blue">
                <div class="kpi-icon"><i data-lucide="eye" class="lucide"></i></div>
                <div class="kpi-num"><?php echo htmlspecialchars((string) $totalPageViews); ?></div>
                <div class="kpi-label">Total Page Views</div>
                <div class="kpi-change"><?php echo $totalPageViews > 0 ? 'Across every public page · ' . htmlspecialchars($periodLabels[$period]) : 'No page views logged yet'; ?></div>
            </div>
        </div>

        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Most Viewed Pages &mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></h4>
                <?php if (empty($topPages)): ?>
                    <div class="chart-empty">
                        <i data-lucide="bar-chart-3" class="lucide"></i>
                        <div class="chart-empty-title">No visit data yet</div>
                        <div class="chart-empty-sub">This chart will populate automatically as tourists browse the site.</div>
                    </div>
                <?php else: ?>
                    <div class="bar-chart">
                        <?php foreach ($topPages as $d): ?>
                            <div class="bar-row">
                                <span class="bar-name"><?php echo htmlspecialchars($d['label']); ?></span>
                                <div class="bar-track"><div class="bar-fill" style="width:<?php echo (int) $d['pct']; ?>%"></div></div>
                                <span class="bar-pct"><?php echo (int) $d['views']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-box">
                <h4>Recently Added &mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></h4>
                <?php if (empty($trending)): ?>
                    <div class="chart-empty">
                        <i data-lucide="trending-up" class="lucide"></i>
                        <div class="chart-empty-title">Nothing added <?php echo htmlspecialchars(strtolower($periodLabels[$period])); ?></div>
                        <div class="chart-empty-sub">New destinations and fiestas added in this period will show up here.</div>
                    </div>
                <?php else: ?>
                    <div class="trending-list">
                        <?php foreach ($trending as $i => $t): ?>
                            <div class="trending-row">
                                <div class="trending-rank">#<?php echo $i + 1; ?></div>
                                <div class="trending-info">
                                    <div class="trending-name"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <div class="trending-sub"><?php echo htmlspecialchars($t['sub']); ?></div>
                                </div>
                                <div class="trending-visits"><?php echo htmlspecialchars((string) $t['visits']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="chart-box chart-box-full animate">
            <h4>Most Viewed Places <span class="chart-sub">&mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></span></h4>
            <?php if (empty($topItems)): ?>
                <div class="chart-empty">
                    <i data-lucide="flame" class="lucide"></i>
                    <div class="chart-empty-title">No listing views yet</div>
                    <div class="chart-empty-sub">Opens a real per-place view count for every listing (e.g. "how many visitors did BFC have?") as soon as tourists start clicking "View Details."</div>
                </div>
            <?php else: ?>
                <div class="trending-list">
                    <?php foreach ($topItems as $i => $it): ?>
                        <div class="trending-row">
                            <div class="trending-rank">#<?php echo $i + 1; ?></div>
                            <div class="trending-info">
                                <div class="trending-name"><?php echo htmlspecialchars($it['name']); ?></div>
                                <div class="trending-sub"><?php echo htmlspecialchars($itemTypeLabels[$it['type']] ?? ucfirst($it['type'])); ?></div>
                            </div>
                            <div class="trending-visits"><?php echo (int) $it['views']; ?> views</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. TOURIST ENGAGEMENT & INTERACTIONS -->
        <div class="alabel-desc">Tourist Engagement &amp; Content Interactions <span class="analytics-tag">Descriptive</span></div>

        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Content Engagement by Category</h4>
                <?php if (empty($categoryShare)): ?>
                    <div class="chart-empty">
                        <i data-lucide="pie-chart" class="lucide"></i>
                        <div class="chart-empty-title">No engagement data yet</div>
                        <div class="chart-empty-sub">Category breakdown appears once users start browsing content.</div>
                    </div>
                <?php else: ?>
                    <div class="donut-wrapper">
                        <div class="donut-chart" style="background:conic-gradient(<?php echo htmlspecialchars($donutGradient); ?>)"><div class="donut-hole"><?php echo (int) $contentTotal; ?><span>listings</span></div></div>
                        <div class="donut-legend">
                            <?php foreach ($categoryShare as $c): ?>
                                <div class="legend-item">
                                    <div class="legend-dot" style="background:<?php echo htmlspecialchars($c['color']); ?>"></div>
                                    <?php echo htmlspecialchars($c['label']); ?>
                                    <span class="legend-pct"><?php echo (int) $c['pct']; ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-box">
                <h4>Traffic Sources</h4>
                <?php if (empty($trafficSources)): ?>
                    <div class="chart-empty">
                        <i data-lucide="globe" class="lucide"></i>
                        <div class="chart-empty-title">No traffic data yet</div>
                        <div class="chart-empty-sub">Where visitors come from will be tracked here.</div>
                    </div>
                <?php else: ?>
                    <div class="source-list">
                        <?php foreach ($trafficSources as $s): ?>
                            <div class="source-row">
                                <div class="source-info">
                                    <div class="source-name"><?php echo htmlspecialchars($s['label']); ?></div>
                                    <div class="source-bar-wrap"><div class="source-bar" style="width:<?php echo (int) $s['pct']; ?>%"></div></div>
                                </div>
                                <div class="source-pct"><?php echo (int) $s['pct']; ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. VISUAL REPORTS -->
        <div class="alabel-desc">Visual Reports &amp; Tourism Insights <span class="analytics-tag">Descriptive</span></div>

        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Monthly Visitor Trend</h4>
                <?php if (!$hasMonthlyData): ?>
                    <div class="chart-empty">
                        <i data-lucide="calendar" class="lucide"></i>
                        <div class="chart-empty-title">No visitor history yet</div>
                        <div class="chart-empty-sub">Monthly totals will build up automatically over time.</div>
                    </div>
                <?php else: ?>
                    <div class="month-chart">
                        <?php foreach ($monthlyVisitors as $m): ?>
                            <div class="month-row">
                                <span class="month-label"><?php echo htmlspecialchars($m['label']); ?></span>
                                <div class="month-track"><div class="month-fill" style="width:<?php echo (int) $m['pct']; ?>%"></div></div>
                                <span class="month-val"><?php echo htmlspecialchars((string) $m['value']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-box">
                <h4>User Activity Summary &mdash; <?php echo htmlspecialchars($periodLabels[$period]); ?></h4>
                <?php if ($newSignups === 0 && $adminLogins === 0 && $activeUsers === 0): ?>
                    <div class="chart-empty">
                        <i data-lucide="activity" class="lucide"></i>
                        <div class="chart-empty-title">No activity recorded <?php echo htmlspecialchars(strtolower($periodLabels[$period])); ?></div>
                        <div class="chart-empty-sub">Login frequency and session activity will appear here once users start signing in.</div>
                    </div>
                <?php else: ?>
                    <div class="activity-summary">
                        <div class="activity-stat">
                            <i data-lucide="user-plus" class="lucide"></i>
                            <div><div class="activity-num"><?php echo (int) $newSignups; ?></div><div class="activity-label">New Visitor Accounts</div></div>
                        </div>
                        <div class="activity-stat">
                            <i data-lucide="log-in" class="lucide"></i>
                            <div><div class="activity-num"><?php echo (int) $adminLogins; ?></div><div class="activity-label">Admin Logins</div></div>
                        </div>
                        <div class="activity-stat">
                            <i data-lucide="radio" class="lucide"></i>
                            <div><div class="activity-num"><?php echo (int) $activeUsers; ?></div><div class="activity-label">Online Right Now</div></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. PREDICTIVE ANALYTICS -->
        <div class="alabel-desc">Forecasts &amp; Momentum <span class="analytics-tag tag-predictive">Predictive</span></div>

        <div class="charts-2col animate">
            <div class="chart-box">
                <h4>Next Month&rsquo;s Projected Views</h4>
                <?php if ($projectedNextMonth === null): ?>
                    <div class="chart-empty">
                        <i data-lucide="line-chart" class="lucide"></i>
                        <div class="chart-empty-title">Not enough history yet</div>
                        <div class="chart-empty-sub">Once at least 3 months of page views have been logged, a projection will appear here.</div>
                    </div>
                <?php else: ?>
                    <div class="forecast-box">
                        <div class="forecast-num"><?php echo number_format($projectedNextMonth); ?></div>
                        <div class="forecast-label">projected page views next month</div>
                        <div class="forecast-change <?php echo $projectedGrowthPct >= 0 ? 'up' : 'down'; ?>">
                            <i data-lucide="<?php echo $projectedGrowthPct >= 0 ? 'trending-up' : 'trending-down'; ?>" class="lucide"></i>
                            <?php echo ($projectedGrowthPct >= 0 ? '+' : '') . $projectedGrowthPct; ?>% vs. last month (trend-line estimate)
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chart-box">
                <h4>Pages Gaining Momentum</h4>
                <?php if (empty($trendingPages)): ?>
                    <div class="chart-empty">
                        <i data-lucide="trending-up" class="lucide"></i>
                        <div class="chart-empty-title">No upward trend yet</div>
                        <div class="chart-empty-sub">Pages with more views this week than last week will show up here.</div>
                    </div>
                <?php else: ?>
                    <div class="trending-list">
                        <?php foreach ($trendingPages as $tp): ?>
                            <div class="trending-row">
                                <div class="trending-rank up"><i data-lucide="arrow-up" class="lucide" style="width:.8rem;height:.8rem;"></i></div>
                                <div class="trending-info">
                                    <div class="trending-name"><?php echo htmlspecialchars($tp['label']); ?></div>
                                    <div class="trending-sub"><?php echo (int) $tp['views']; ?> views this week</div>
                                </div>
                                <div class="trending-visits up-pct">+<?php echo (int) $tp['growth']; ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 5. PRESCRIPTIVE ANALYTICS -->
        <div class="alabel-desc">Recommended Actions <span class="analytics-tag tag-prescriptive">Prescriptive</span></div>

        <div class="rec-list animate">
            <?php foreach ($recommendations as $rec): ?>
                <div class="rec-row rec-<?php echo htmlspecialchars($rec['level']); ?>">
                    <div class="rec-icon"><i data-lucide="<?php echo htmlspecialchars($rec['icon']); ?>" class="lucide"></i></div>
                    <div class="rec-text"><?php echo htmlspecialchars($rec['text']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admindashboard.js"></script>
<script>initSidebarCollapse();</script>

</body>
</html>