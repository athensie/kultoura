<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!in_array($role, ['admin', 'super admin'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
$adminName = (string) ($_SESSION['username'] ?? 'Admin');
$adminRole = (string) ($_SESSION['role'] ?? 'Admin');
$isSuperAdmin = $role === 'super admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | KULTOURA Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="../assets/css/admindashboard.css?v=20261102-1">
    <link rel="stylesheet" href="../assets/css/admin-theme.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <style>
        .analytics-empty { min-height: calc(100vh - 110px); display:grid; place-items:center; text-align:center; }
        .analytics-empty p { color:rgba(var(--cream-rgb),.55); font:400 .95rem 'DM Sans',sans-serif; }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar"><i data-lucide="chevrons-left" class="lucide"></i></button>
    <div class="sidebar-logo">KulToura</div>
    <div class="sidebar-tagline">Malvar, Batangas</div>
    <div class="sidebar-section">Overview</div>
    <ul class="sidebar-nav">
        <li><a href="<?= BASE_URL ?>/admin/admindashboard.php" class="active"><span class="nav-icon"><i data-lucide="bar-chart-2" class="lucide"></i></span> Analytics</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminreviews.php"><span class="nav-icon"><i data-lucide="star" class="lucide"></i></span> Ratings &amp; Reviews</a></li>
    </ul>
    <div class="sidebar-section">Content</div>
    <ul class="sidebar-nav">
        <li><a href="<?= BASE_URL ?>/admin/admindestinations.php"><span class="nav-icon"><i data-lucide="map-pin" class="lucide"></i></span> Destinations</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminpromote.php"><span class="nav-icon"><i data-lucide="sparkles" class="lucide"></i></span> Promote Content</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminfoodanddining.php"><span class="nav-icon"><i data-lucide="utensils" class="lucide"></i></span> Food &amp; Dining</a></li>
        <li><a href="<?= BASE_URL ?>/admin/admineventandfiesta.php"><span class="nav-icon"><i data-lucide="calendar-heart" class="lucide"></i></span> Events &amp; Fiesta</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminpeople.php"><span class="nav-icon"><i data-lucide="users-round" class="lucide"></i></span> People of Malvar</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminsitecontent.php"><span class="nav-icon"><i data-lucide="image" class="lucide"></i></span> Site Content</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminhomepage.php"><span class="nav-icon"><i data-lucide="layout-grid" class="lucide"></i></span> Homepage Content</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminpagewidgets.php"><span class="nav-icon"><i data-lucide="layout-template" class="lucide"></i></span> Page Widgets</a></li>
    </ul>
    <div class="sidebar-section">Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?= BASE_URL ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminannouncements.php"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
        <?php if ($isSuperAdmin): ?>
        <li><a href="<?= BASE_URL ?>/admin/adminrequests.php"><span class="nav-icon"><i data-lucide="inbox" class="lucide"></i></span> Requests</a></li>
        <li><a href="<?= BASE_URL ?>/admin/adminarchive.php"><span class="nav-icon"><i data-lucide="archive" class="lucide"></i></span> Archive</a></li>
        <?php endif; ?>
        <li><a href="<?= BASE_URL ?>/admin/adminsettings.php"><span class="nav-icon"><i data-lucide="settings" class="lucide"></i></span> Settings</a></li>
    </ul>
    <div class="sidebar-footer">
        <div class="admin-avatar"><div class="avatar-circle"><i data-lucide="user" class="lucide"></i></div><div><div class="avatar-name"><?= htmlspecialchars($adminName) ?></div><div class="avatar-role"><?= htmlspecialchars($adminRole) ?></div></div></div>
        <a href="<?= BASE_URL ?>/auth/auth.php?action=logout" class="logout-link"><i data-lucide="log-out" class="lucide"></i> Log Out</a>
    </div>
</aside>
<button class="hamburger-btn" onclick="toggleSidebar()"><i data-lucide="menu" class="lucide"></i></button>
<main class="main-content"><div class="dash-wrapper">
    <div class="admin-site-switch-row"><a class="admin-site-switch" href="<?= BASE_URL ?>/index.php"><i data-lucide="external-link" class="lucide"></i> View user site</a></div>
    <header class="page-header"><div><div class="section-label">Admin</div><h1>Analytics</h1></div></header>
    <section class="analytics-empty" aria-label="Analytics content removed"><p>Analytics have been removed from this page.</p></section>
</div></main>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-notifications.js"></script>
<script>
function toggleSidebar() { document.getElementById('sidebar')?.classList.toggle('open'); }
if (typeof lucide !== 'undefined') lucide.createIcons();
if (typeof initSidebarCollapse === 'function') initSidebarCollapse();
</script>
</body>
</html>
