<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/promotions.php';
define('BASE_URL', '/kultoura');

if (!isset($_SESSION['user_id']) || !in_array(strtolower((string) ($_SESSION['role'] ?? '')), ['admin', 'super admin'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

kt_promotions_ensure_schema($conn);
$notice = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $type = (string) ($_POST['item_type'] ?? '');
    $id = (int) ($_POST['item_id'] ?? 0);
    $types = kt_promotion_types();
    if (isset($types[$type]) && $id > 0 && in_array($action, ['promote', 'remove'], true)) {
        $meta = $types[$type];
        if ($action === 'promote') {
            $check = $conn->prepare("SELECT 1 FROM {$meta['table']} WHERE {$meta['id']} = ? AND status = 'active' AND {$meta['category']} LIMIT 1");
            $check->bind_param('i', $id);
            $check->execute();
            $isActive = (bool) $check->get_result()->fetch_row();
            $check->close();
            if ($isActive) {
                $stmt = $conn->prepare('INSERT IGNORE INTO promoted_content (item_type, item_id, promoted_by) VALUES (?, ?, ?)');
                $adminId = (int) $_SESSION['user_id'];
                $stmt->bind_param('sii', $type, $id, $adminId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['admin_flash'] = 'Content promoted to the homepage.';
            }
        } else {
            $stmt = $conn->prepare('DELETE FROM promoted_content WHERE item_type = ? AND item_id = ?');
            $stmt->bind_param('si', $type, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin_flash'] = 'Promotion removed from the homepage.';
        }
    }
    header('Location: ' . BASE_URL . '/admin/adminpromote.php');
    exit;
}

$adminName = htmlspecialchars((string) ($_SESSION['username'] ?? 'Admin'));
$adminRole = htmlspecialchars((string) ($_SESSION['role'] ?? 'Admin'));
$candidateGroups = kt_promotion_candidates($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promote Content · KULTOURA Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/admin-theme.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <link rel="stylesheet" href="../assets/css/adminpromote.css?v=20261102-1">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar"><i data-lucide="chevrons-left" class="lucide"></i></button>
    <div class="sidebar-logo">KulToura</div>
    <div class="sidebar-tagline">Malvar, Batangas</div>
    <div class="sidebar-section">Overview</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/admindashboard.php"><span class="nav-icon"><i data-lucide="bar-chart-2" class="lucide"></i></span> Analytics</a></li>
    </ul>
    <div class="sidebar-section">Content</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpromote.php" class="active"><span class="nav-icon"><i data-lucide="sparkles" class="lucide"></i></span> Promote Content</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/admindestinations.php"><span class="nav-icon"><i data-lucide="map-pin" class="lucide"></i></span> Destinations</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminfoodanddining.php"><span class="nav-icon"><i data-lucide="utensils" class="lucide"></i></span> Food &amp; Dining</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/admineventandfiesta.php"><span class="nav-icon"><i data-lucide="calendar-heart" class="lucide"></i></span> Events &amp; Fiesta</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpeople.php"><span class="nav-icon"><i data-lucide="users-round" class="lucide"></i></span> People of Malvar</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsitecontent.php"><span class="nav-icon"><i data-lucide="image" class="lucide"></i></span> Site Content</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminhomepage.php"><span class="nav-icon"><i data-lucide="layout-grid" class="lucide"></i></span> Homepage Content</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpagewidgets.php"><span class="nav-icon"><i data-lucide="layout-template" class="lucide"></i></span> Page Widgets</a></li>
    </ul>
    <div class="sidebar-section">Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminannouncements.php"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsettings.php"><span class="nav-icon"><i data-lucide="settings" class="lucide"></i></span> Settings</a></li>
    </ul>
    <div class="sidebar-footer"><div class="avatar-name"><?php echo $adminName; ?></div><div class="avatar-role"><?php echo $adminRole; ?></div></div>
</aside>
<main class="promote-main">
    <header class="promote-header">
        <div>
            <p class="promote-eyebrow">HOMEPAGE DISCOVERY</p>
            <h1>Promote low-view content</h1>
            <p>Review active listings by category and feature selected items below For You on the homepage.</p>
        </div>
        <a class="promote-back" href="<?php echo BASE_URL; ?>/index.php">View homepage</a>
    </header>
    <?php if ($notice !== ''): ?><div class="promote-notice"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if (empty($candidateGroups)): ?>
        <div class="promote-empty">No active content is available to promote yet.</div>
    <?php endif; ?>
    <?php foreach ($candidateGroups as $groupName => $items): ?>
    <section class="promote-category">
        <div class="promote-category-head"><h2><?php echo htmlspecialchars($groupName); ?></h2><span><?php echo count($items); ?> active</span></div>
        <div class="promote-list">
            <?php foreach ($items as $item): ?>
            <article class="promote-row">
                <div class="promote-item-thumb">
                    <?php if ($item['image'] !== ''): ?><img src="../assets/uploads/<?php echo htmlspecialchars(kt_promotion_types()[$item['type']]['image_folder']); ?>/<?php echo htmlspecialchars(basename($item['image'])); ?>" alt="">
                    <?php else: ?><i data-lucide="image" class="lucide"></i><?php endif; ?>
                </div>
                <div class="promote-item-info"><h3><?php echo htmlspecialchars($item['name']); ?></h3><p><?php echo htmlspecialchars(mb_strimwidth($item['summary'], 0, 160, '…')); ?></p></div>
                <span class="promote-views"><i data-lucide="eye" class="lucide"></i><?php echo $item['views']; ?> views</span>
                <form method="post" class="promote-action">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="item_type" value="<?php echo htmlspecialchars($item['type']); ?>">
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    <button type="submit" name="action" value="<?php echo $item['promoted'] ? 'remove' : 'promote'; ?>" class="<?php echo $item['promoted'] ? 'is-promoted' : ''; ?>">
                        <i data-lucide="<?php echo $item['promoted'] ? 'check' : 'megaphone'; ?>" class="lucide"></i><?php echo $item['promoted'] ? 'Remove Promotion' : 'Promote'; ?>
                    </button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</main>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
