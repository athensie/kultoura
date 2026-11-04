<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'super admin'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
$adminName = $_SESSION['username'] ?? 'Admin';
$adminRole = $_SESSION['role'] ?? 'Admin';
$isSuperAdmin = strtolower((string) $adminRole) === 'super admin';
include '../config/dbmain.php';
require_once '../config/reviews.php';
kt_reviews_ensure_schema($conn);

$validPeriods = ['today', 'week', 'month', 'year'];
$period = $_GET['period'] ?? 'month';
if (!in_array($period, $validPeriods, true)) $period = 'month';
$periodLabels = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
$periodStart = new DateTime('today');
switch ($period) {
    case 'week': $periodStart = new DateTime('monday this week'); break;
    case 'month': $periodStart = new DateTime('first day of this month'); break;
    case 'year': $periodStart = new DateTime('first day of january this year'); break;
}
$periodStartSql = $periodStart->format('Y-m-d 00:00:00');
$ratingFilter = (int) ($_GET['rating'] ?? 0);
if ($ratingFilter < 0 || $ratingFilter > 5) $ratingFilter = 0;
$typeFilter = strtolower(trim((string) ($_GET['type'] ?? '')));
if (!in_array($typeFilter, kt_reviews_allowed_types(), true)) $typeFilter = '';
$reviewSummaryStmt = $conn->prepare('SELECT COUNT(*) total, COALESCE(AVG(rating), 0) average_rating, COUNT(DISTINCT item_type) categories FROM item_reviews WHERE created_at >= ?');
$reviewSummaryStmt->bind_param('s', $periodStartSql);
$reviewSummaryStmt->execute();
$summary = $reviewSummaryStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'average_rating' => 0, 'categories' => 0];
$reviewSummaryStmt->close();
$ratingCounts = array_fill(1, 5, 0);
$ratingStmt = $conn->prepare('SELECT rating, COUNT(*) total FROM item_reviews WHERE created_at >= ? GROUP BY rating');
$ratingStmt->bind_param('s', $periodStartSql);
$ratingStmt->execute();
foreach ($ratingStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $ratingCounts[(int) $row['rating']] = (int) $row['total'];
$ratingStmt->close();
$typeStats = [];
$typeStmt = $conn->prepare('SELECT item_type, COUNT(*) review_count, ROUND(AVG(rating), 1) average_rating FROM item_reviews WHERE created_at >= ? GROUP BY item_type ORDER BY review_count DESC');
$typeStmt->bind_param('s', $periodStartSql);
$typeStmt->execute();
$typeStats = $typeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$typeStmt->close();
$categoryLabels = [
    'restaurant' => 'Restaurants & Cafés', 'product' => 'Local Products', 'fiesta' => 'Fiestas & Events',
    'nature' => 'Nature', 'resort' => 'Resorts', 'industry' => 'Industry Zones',
    'accommodation' => 'Accommodation', 'church' => 'Churches', 'service' => 'Visitor Services',
];
$sql = "SELECT r.display_name, r.item_type, r.item_id, r.rating, r.review_text, r.created_at,
    COALESCE(d.destination_name, rs.restaurant_name, p.product_name, f.fiesta_name, CONCAT('Item #', r.item_id)) item_name
    FROM item_reviews r
    LEFT JOIN destination d ON r.item_type IN ('nature','resort','industry','accommodation','church','service') AND d.destination_id = r.item_id
    LEFT JOIN restaurants rs ON r.item_type = 'restaurant' AND rs.restaurant_id = r.item_id
    LEFT JOIN products p ON r.item_type = 'product' AND p.product_id = r.item_id
    LEFT JOIN fiestas f ON r.item_type = 'fiesta' AND f.fiesta_id = r.item_id
    WHERE r.created_at >= ?";
$params = [$periodStartSql];
$paramTypes = 's';
if ($ratingFilter) { $sql .= ' AND r.rating = ?'; $params[] = $ratingFilter; $paramTypes .= 'i'; }
if ($typeFilter !== '') { $sql .= ' AND r.item_type = ?'; $params[] = $typeFilter; $paramTypes .= 's'; }
$sql .= ' ORDER BY r.created_at DESC LIMIT 100';
$reviewsStmt = $conn->prepare($sql);
$bindArgs = [$paramTypes];
foreach ($params as $index => &$param) $bindArgs[] = &$param;
call_user_func_array([$reviewsStmt, 'bind_param'], $bindArgs);
$reviewsStmt->execute();
$reviews = $reviewsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reviewsStmt->close();
$ratingMax = max(1, max($ratingCounts));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratings &amp; Reviews — KulToura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;700&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="../assets/css/admindashboard.css?v=20261102-1">
    <link rel="stylesheet" href="../assets/css/admin-theme.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <style>
        .review-page-filters { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; margin:0 0 24px; }
        .review-page-period { display:flex; flex-wrap:wrap; gap:8px; }
        .review-page-select { background:var(--sidebar); color:var(--cream); border:1px solid rgba(var(--overlay-rgb),.12); border-radius:999px; padding:9px 14px; }
        .review-page-table { display:grid; gap:12px; }
        .review-row { padding:17px 18px; border:1px solid rgba(var(--overlay-rgb),.08); border-radius:14px; background:rgba(var(--overlay-rgb),.025); }
        .review-row-head { display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px; color:var(--gold); font-size:.78rem; }
        .review-row-stars { color:#e6b647; letter-spacing:.08em; font-size:1rem; margin-top:7px; }
        .review-row p { color:rgba(var(--cream-rgb),.76); line-height:1.6; margin-top:7px; white-space:pre-wrap; overflow-wrap:anywhere; }
        .review-count { color:rgba(var(--cream-rgb),.4); font-size:.78rem; }
        @media(max-width:700px) { .dash-wrapper{padding:76px 18px 50px}.kpi-grid{grid-template-columns:1fr}.charts-2col{grid-template-columns:1fr} }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar"><i data-lucide="chevrons-left" class="lucide"></i></button>
    <div class="sidebar-logo">KulToura</div><div class="sidebar-tagline">Malvar, Batangas</div>
    <div class="sidebar-section">Overview</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindashboard.php"><span class="nav-icon"><i data-lucide="bar-chart-2" class="lucide"></i></span> Analytics</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminreviews.php" class="active"><span class="nav-icon"><i data-lucide="star" class="lucide"></i></span> Ratings &amp; Reviews</a></li>
    </ul>
    <div class="sidebar-section">Content</div><ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindestinations.php"><span class="nav-icon"><i data-lucide="map-pin" class="lucide"></i></span> Destinations</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminfoodanddining.php"><span class="nav-icon"><i data-lucide="utensils" class="lucide"></i></span> Food &amp; Dining</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/admineventandfiesta.php"><span class="nav-icon"><i data-lucide="calendar-heart" class="lucide"></i></span> Events &amp; Fiesta</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsitecontent.php"><span class="nav-icon"><i data-lucide="image" class="lucide"></i></span> Site Content</a></li>
    </ul>
    <div class="sidebar-section">Management</div><ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsettings.php"><span class="nav-icon"><i data-lucide="settings" class="lucide"></i></span> Settings</a></li>
    </ul>
    <div class="sidebar-footer"><div class="admin-avatar"><div class="avatar-circle"><i data-lucide="user" class="lucide"></i></div><div><div class="avatar-name"><?php echo htmlspecialchars($adminName); ?></div><div class="avatar-role"><?php echo htmlspecialchars($adminRole); ?></div></div></div><a href="<?php echo BASE_URL; ?>/auth/auth.php?action=logout" class="logout-link"><i data-lucide="log-out" class="lucide"></i> Log Out</a></div>
</aside>
<button class="hamburger-btn" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')"><i data-lucide="menu" class="lucide"></i></button>
<div class="main-content"><div class="dash-wrapper">
    <div class="admin-site-switch-row"><a class="admin-site-switch" href="<?php echo BASE_URL; ?>/index.php"><i data-lucide="external-link" class="lucide"></i> View user site</a></div>
    <header class="page-header"><div><div class="section-label">Tourism Insights</div><h1>Ratings <em>&amp; Reviews</em></h1><p>Visitor feedback across listings, including star trends and recent comments.</p></div></header>
    <div class="review-page-filters">
        <nav class="review-page-period" aria-label="Choose reporting period">
            <?php foreach ($periodLabels as $key => $label): ?><a class="period-btn<?php echo $period === $key ? ' active' : ''; ?>" href="?period=<?php echo urlencode($key); ?>&amp;rating=<?php echo $ratingFilter; ?>&amp;type=<?php echo urlencode($typeFilter); ?>"><?php echo htmlspecialchars($label); ?></a><?php endforeach; ?>
        </nav>
        <form method="get" class="review-page-period">
            <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
            <select class="review-page-select" name="rating" aria-label="Filter by star rating"><option value="0">All star ratings</option><?php for ($star = 5; $star >= 1; $star--): ?><option value="<?php echo $star; ?>"<?php echo $ratingFilter === $star ? ' selected' : ''; ?>><?php echo $star; ?> star<?php echo $star > 1 ? 's' : ''; ?></option><?php endfor; ?></select>
            <select class="review-page-select" name="type" aria-label="Filter by content type"><option value="">All categories</option><?php foreach ($categoryLabels as $key => $label): ?><option value="<?php echo htmlspecialchars($key); ?>"<?php echo $typeFilter === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select>
            <button class="period-btn active" type="submit">Apply filters</button>
        </form>
    </div>
    <div class="kpi-grid">
        <div class="kpi-card accent-gold"><div class="kpi-icon"><i data-lucide="star" class="lucide"></i></div><div class="kpi-num"><?php echo number_format((int) $summary['total']); ?></div><div class="kpi-label">Ratings &amp; Comments</div><div class="kpi-change"><?php echo htmlspecialchars($periodLabels[$period]); ?></div></div>
        <div class="kpi-card accent-clay"><div class="kpi-icon"><i data-lucide="message-square-text" class="lucide"></i></div><div class="kpi-num"><?php echo (int) $summary['total'] ? number_format((float) $summary['average_rating'], 1) . ' / 5' : '—'; ?></div><div class="kpi-label">Average Rating</div><div class="kpi-change">All submitted ratings this period</div></div>
        <div class="kpi-card accent-green"><div class="kpi-icon"><i data-lucide="layers" class="lucide"></i></div><div class="kpi-num"><?php echo (int) $summary['categories']; ?></div><div class="kpi-label">Reviewed Categories</div><div class="kpi-change">Types with visitor feedback</div></div>
    </div>
    <div class="charts-2col">
        <div class="chart-box"><h4>Star Rating Distribution</h4><?php foreach ([5,4,3,2,1] as $star): ?><div class="bar-row"><span class="bar-name"><?php echo $star; ?> stars</span><div class="bar-track"><div class="bar-fill" style="width:<?php echo (int) round($ratingCounts[$star] / $ratingMax * 100); ?>%"></div></div><span class="bar-pct"><?php echo $ratingCounts[$star]; ?></span></div><?php endforeach; ?></div>
        <div class="chart-box"><h4>Ratings by Category</h4><?php if (!$typeStats): ?><div class="chart-empty"><div class="chart-empty-title">No reviews yet</div><div class="chart-empty-sub">Category averages will appear here as visitors leave feedback.</div></div><?php else: ?><?php foreach ($typeStats as $typeRow): ?><div class="bar-row"><span class="bar-name"><?php echo htmlspecialchars($categoryLabels[$typeRow['item_type']] ?? ucfirst($typeRow['item_type'])); ?></span><div class="bar-track"><div class="bar-fill" style="width:<?php echo (int) round(((float) $typeRow['average_rating'] / 5) * 100); ?>%"></div></div><span class="bar-pct"><?php echo number_format((float) $typeRow['average_rating'], 1); ?> · <?php echo (int) $typeRow['review_count']; ?></span></div><?php endforeach; ?><?php endif; ?></div>
    </div>
    <section class="chart-box"><h4>Visitor Comments <span class="review-count">Showing up to 100 latest reviews for <?php echo htmlspecialchars($periodLabels[$period]); ?></span></h4>
        <?php if (!$reviews): ?><div class="chart-empty"><div class="chart-empty-title">No reviews match these filters</div><div class="chart-empty-sub">Try a different period, star rating, or category.</div></div><?php else: ?>
        <div class="review-page-table"><?php foreach ($reviews as $review): ?><article class="review-row">
            <div class="review-row-head"><span><?php echo htmlspecialchars($review['item_name']); ?> · <?php echo htmlspecialchars($categoryLabels[$review['item_type']] ?? ucfirst($review['item_type'])); ?></span><span><?php echo htmlspecialchars($review['display_name']); ?> · <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($review['created_at']))); ?></span></div>
            <div class="review-row-stars" aria-label="<?php echo (int) $review['rating']; ?> out of 5 stars"><?php echo str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']); ?></div>
            <p><?php echo htmlspecialchars($review['review_text']); ?></p>
        </article><?php endforeach; ?></div><?php endif; ?>
    </section>
</div></div>
<script src="../assets/js/admin-sidebar-collapse.js"></script><script>initSidebarCollapse();if(window.lucide)lucide.createIcons();</script>
</body></html>
