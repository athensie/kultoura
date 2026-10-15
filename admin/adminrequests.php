<?php
require_once __DIR__ . '/../config/session_boot.php';

define('BASE_URL', '/kultoura');

require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/admin_requests.php';

/*
 |--------------------------------------------------------------------
 | ACCESS CONTROL — Super Admin only. A plain Admin is the one filing
 | these requests, so they never get to review their own (or anyone
 | else's) — redirect them back to the dashboard instead.
 |--------------------------------------------------------------------
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

if (!kt_is_super_admin()) {
    header("Location: " . BASE_URL . "/admin/admindashboard.php");
    exit;
}

$adminName = $_SESSION['username'] ?? 'Admin';
$adminRole = $_SESSION['role'] ?? 'Super Admin';
$reviewerId = (int) $_SESSION['user_id'];

kt_requests_ensure_schema($conn);

$entityLabels = [
    'destination'   => 'Destination',
    'product'       => 'Product',
    'restaurant'    => 'Restaurant',
    'fiesta'        => 'Event / Fiesta',
    'person'        => 'Person of Malvar',
    'announcement'  => 'Announcement',
    'about_section' => 'About Section',
];
$fieldLabels = [
    'destination_name' => 'Name', 'restaurant_name' => 'Name', 'product_name' => 'Name',
    'fiesta_name' => 'Name', 'fullname' => 'Full Name', 'title' => 'Title', 'body' => 'Message',
    'address' => 'Address', 'location' => 'Location', 'category' => 'Category',
    'subcategory' => 'Subcategory', 'status' => 'Status', 'description' => 'Description',
    'image' => 'Image', 'google_maps' => 'Google Maps Link', 'google_map' => 'Google Maps Link',
    'price' => 'Price', 'latitude' => 'Latitude', 'longitude' => 'Longitude',
    'contact_number' => 'Contact Number', 'opening_hours' => 'Opening Hours',
    'type' => 'Type', 'celebration_date' => 'Date', 'achievement' => 'Achievement',
    'audience' => 'Audience', 'scheduled_at' => 'Scheduled For', 'icon_key' => 'Icon',
    'remove_image' => 'Remove Image',
];

/*
 |--------------------------------------------------------------------
 | APPROVE / REJECT
 |--------------------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    csrf_verify();
    $formAction = $_POST['form_action'];

    if ($formAction === 'restore') {
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        $archiveMeta = kt_archive_types()[$entityType] ?? null;
        if ($archiveMeta && $entityId > 0 && kt_archive_restore($conn, $entityType, $entityId)) {
            $_SESSION['admin_flash'] = 'Restored — it\'s live again on the site.';
        } else {
            $_SESSION['admin_flash'] = 'Could not restore that item.';
        }
        header("Location: " . BASE_URL . "/admin/adminrequests.php#archive");
        exit;
    }

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $request = $requestId > 0 ? kt_requests_find($conn, $requestId) : null;

    if (!$request || $request['status'] !== 'pending') {
        $_SESSION['admin_flash'] = 'That request is no longer pending.';
    } elseif ($formAction === 'approve') {
        kt_apply_entity_change($conn, $request['entity_type'], $request['action'], $request['entity_id'], $request['payload']);
        kt_requests_resolve($conn, $requestId, 'approved', $reviewerId);
        $_SESSION['admin_flash'] = 'Request approved and applied.';
    } elseif ($formAction === 'reject') {
        $note = trim($_POST['review_note'] ?? '') ?: null;
        kt_requests_resolve($conn, $requestId, 'rejected', $reviewerId, $note);
        $_SESSION['admin_flash'] = 'Request rejected.';
    }

    header("Location: " . BASE_URL . "/admin/adminrequests.php");
    exit;
}

$flashMessage = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$requestedStatus = $_GET['status'] ?? 'pending';
$statusFilter = in_array($requestedStatus, ['pending', 'approved', 'rejected'], true) ? $requestedStatus : 'pending';
$requests = kt_requests_list($conn, $statusFilter);
$pendingCount = kt_requests_pending_count($conn);
$archivedGrouped = kt_archived_items_grouped($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests – KULTOURA Admin</title>

    <script>
        (function () {
            var saved = localStorage.getItem('kt-admin-theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-theme', saved);
            }
        })();
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>

    <link rel="stylesheet" href="../assets/css/adminusers.css">
    <link rel="stylesheet" href="../assets/css/admin-theme-toggle.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <style>
        .req-card { background: rgba(255,255,255,.03); border: 1px solid rgba(245,237,216,.08); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
        .req-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
        .req-title { font-family: 'Playfair Display', serif; font-size: 1.05rem; color: var(--cream); }
        .req-meta { font-size: .72rem; color: rgba(245,237,216,.4); margin-top: 4px; }
        .req-tag { display: inline-block; font-size: .65rem; text-transform: uppercase; letter-spacing: .5px; padding: 3px 9px; border-radius: 20px; background: rgba(200,169,110,.15); color: var(--gold,#C8A96E); margin-right: 6px; }
        .req-tag.archive { background: rgba(229,115,115,.15); color: #e57373; }
        .req-tag.create { background: rgba(129,199,132,.15); color: #81c784; }
        .req-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .req-field { background: rgba(255,255,255,.03); border-radius: 8px; padding: 8px 12px; }
        .req-field.changed { background: rgba(200,169,110,.1); border: 1px solid rgba(200,169,110,.35); }
        .req-field.changed .req-field-label { color: var(--gold,#C8A96E); }
        .req-field-label { font-size: .62rem; color: rgba(245,237,216,.35); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
        .req-field-value { font-size: .82rem; color: rgba(245,237,216,.85); word-break: break-word; }
        .req-field-value img { max-width: 100px; border-radius: 6px; margin-top: 4px; display: block; }
        .req-actions { display: flex; gap: 10px; }
        .status-tabs { display: flex; gap: 8px; margin-bottom: 18px; }
        .status-tabs a { padding: 7px 16px; border-radius: 20px; font-size: .78rem; color: rgba(245,237,216,.6); text-decoration: none; background: rgba(255,255,255,.03); }
        .status-tabs a.active { background: var(--gold,#C8A96E); color: #1a1812; font-weight: 600; }

        /* ARCHIVE SECTION — bottom of this page, categorized by content
           type so a dozen archived items of different kinds don't turn
           into one undifferentiated pile. */
        .archive-section { margin-top: 44px; }
        .archive-section-title { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--cream); margin-bottom: 4px; }
        .archive-section-sub { font-size: .8rem; color: rgba(245,237,216,.45); margin-bottom: 20px; }
        .archive-category { background: rgba(255,255,255,.02); border: 1px solid rgba(245,237,216,.08); border-radius: 12px; margin-bottom: 12px; overflow: hidden; }
        .archive-category-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 18px; cursor: pointer; list-style: none; }
        .archive-category-head::-webkit-details-marker { display: none; }
        .archive-category-name { display: flex; align-items: center; gap: 10px; font-size: .9rem; color: var(--cream); font-weight: 600; }
        .archive-category-count { font-size: .68rem; font-weight: 700; padding: 2px 9px; border-radius: 20px; background: rgba(229,115,115,.15); color: #e57373; }
        .archive-category-chevron { transition: transform .2s; color: rgba(245,237,216,.4); }
        details[open] .archive-category-chevron { transform: rotate(90deg); }
        .archive-items { padding: 0 18px 14px; display: flex; flex-direction: column; gap: 8px; }
        .archive-item { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.03); border-radius: 8px; padding: 8px 12px; }
        .archive-item-thumb { flex-shrink: 0; width: 38px; height: 38px; border-radius: 6px; overflow: hidden; background: rgba(255,255,255,.05); display: flex; align-items: center; justify-content: center; }
        .archive-item-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .archive-item-body { flex: 1; min-width: 0; }
        .archive-item-name { font-size: .82rem; color: rgba(245,237,216,.85); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .archive-item-date { font-size: .68rem; color: rgba(245,237,216,.35); margin-top: 1px; }
        .archive-empty { font-size: .82rem; color: rgba(245,237,216,.4); padding: 20px; text-align: center; background: rgba(255,255,255,.02); border: 1px dashed rgba(245,237,216,.1); border-radius: 12px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminrequests.php" class="active"><span class="nav-icon"><i data-lucide="inbox" class="lucide"></i></span> Requests<?php if ($pendingCount > 0): ?> <span style="background:var(--gold,#C8A96E);color:#1a1812;font-size:.62rem;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:4px;"><?php echo $pendingCount; ?></span><?php endif; ?></a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsettings.php"><span class="nav-icon"><i data-lucide="settings" class="lucide"></i></span> Settings</a></li>
    </ul>

    <div class="sidebar-footer">
        <button type="button" class="theme-toggle" id="themeToggle" onclick="toggleTheme()" aria-label="Switch between dark and light mode">
            <span class="theme-toggle-option" data-theme-option="dark"><i data-lucide="moon" class="lucide"></i> Dark</span>
            <span class="theme-toggle-option" data-theme-option="light"><i data-lucide="sun" class="lucide"></i> Light</span>
            <span class="theme-toggle-thumb"></span>
        </button>
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

        <div class="page-header animate">
            <div>
                <div class="section-label">Content Approval</div>
                <h1>Change <em>Requests</em></h1>
                <p>Review the actual content an Admin wants to add, change, or archive before it goes live.</p>
            </div>
        </div>

        <div class="status-tabs">
            <a href="?status=pending" class="<?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Pending<?php echo $pendingCount > 0 ? ' (' . $pendingCount . ')' : ''; ?></a>
            <a href="?status=approved" class="<?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">Approved</a>
            <a href="?status=rejected" class="<?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
        </div>

        <?php if (empty($requests)): ?>
            <div class="chart-empty">
                <i data-lucide="inbox" class="lucide"></i>
                <div class="chart-empty-title">No <?php echo $statusFilter; ?> requests</div>
                <div class="chart-empty-sub">
                    <?php echo $statusFilter === 'pending' ? "Requests an Admin submits will show up here for your review." : "Nothing here yet."; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $r): ?>
                <div class="req-card">
                    <div class="req-head">
                        <div>
                            <span class="req-tag <?php echo htmlspecialchars($r['action']); ?>"><?php echo htmlspecialchars($r['action']); ?></span>
                            <span class="req-tag"><?php echo htmlspecialchars($entityLabels[$r['entity_type']] ?? $r['entity_type']); ?></span>
                            <div class="req-title"><?php echo htmlspecialchars($r['entity_label'] ?? ('#' . $r['entity_id'])); ?></div>
                            <div class="req-meta">
                                Requested by <?php echo htmlspecialchars($r['requested_by_name']); ?> on <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($r['created_at']))); ?>
                                <?php if ($r['status'] !== 'pending'): ?>
                                    &middot; <?php echo htmlspecialchars(ucfirst($r['status'])); ?> <?php if (!empty($r['reviewed_at'])): ?>on <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($r['reviewed_at']))); ?><?php endif; ?>
                                    <?php if (!empty($r['review_note'])): ?> &middot; "<?php echo htmlspecialchars($r['review_note']); ?>"<?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php
                    $currentRow = $r['action'] === 'update' ? kt_fetch_entity_current($conn, $r['entity_type'], $r['entity_id'] !== null ? (int) $r['entity_id'] : null) : null;
                    ?>

                    <?php if ($r['action'] !== 'archive' && !empty($r['payload'])): ?>
                        <?php if ($r['action'] === 'update'): ?>
                            <p style="font-size:.72rem;color:rgba(245,237,216,.4);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Fields highlighted below are what this request actually changes</p>
                        <?php endif; ?>
                        <div class="req-fields">
                            <?php foreach ($r['payload'] as $field => $value): ?>
                                <?php
                                if ($field === 'remove_image' || $value === '' || $value === null) continue;
                                $currentValue = $currentRow[$field] ?? null;
                                $isChanged = $currentRow === null || (string) $currentValue !== (string) $value;
                                ?>
                                <?php if ($field === 'image'): ?>
                                    <div class="req-field<?php echo $isChanged ? ' changed' : ''; ?>">
                                        <div class="req-field-label">Image<?php echo $isChanged ? ' — changed' : ''; ?></div>
                                        <?php if ($isChanged && !empty($currentValue)): ?>
                                            <div style="font-size:.68rem;color:rgba(245,237,216,.4);margin-bottom:3px;">Was:</div>
                                            <img src="<?php echo htmlspecialchars($currentValue); ?>" alt="" style="opacity:.5;">
                                        <?php endif; ?>
                                        <div class="req-field-value"><img src="<?php echo htmlspecialchars($value); ?>" alt=""></div>
                                    </div>
                                <?php else: ?>
                                    <div class="req-field<?php echo $isChanged ? ' changed' : ''; ?>">
                                        <div class="req-field-label"><?php echo htmlspecialchars($fieldLabels[$field] ?? $field); ?><?php echo $isChanged && $currentRow !== null ? ' — changed' : ''; ?></div>
                                        <?php if ($isChanged && $currentRow !== null && $currentValue !== null && $currentValue !== ''): ?>
                                            <div style="font-size:.72rem;color:rgba(245,237,216,.4);text-decoration:line-through;margin-bottom:2px;"><?php echo htmlspecialchars((string) $currentValue); ?></div>
                                        <?php endif; ?>
                                        <div class="req-field-value"><?php echo is_bool($value) ? ($value ? 'Yes' : 'No') : htmlspecialchars((string) $value); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($r['action'] === 'archive'): ?>
                        <p style="font-size:.82rem;color:rgba(245,237,216,.6);margin-bottom:14px;">This will hide "<?php echo htmlspecialchars($r['entity_label']); ?>" from the public site. It stays in the database and can be restored later.</p>
                    <?php endif; ?>

                    <?php if ($r['status'] === 'pending'): ?>
                        <div class="req-actions">
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="form_action" value="approve">
                                <input type="hidden" name="request_id" value="<?php echo (int) $r['request_id']; ?>">
                                <button type="submit" class="btn-primary"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Approve</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this request? Nothing will be changed.');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="form_action" value="reject">
                                <input type="hidden" name="request_id" value="<?php echo (int) $r['request_id']; ?>">
                                <button type="submit" class="tbl-btn delete"><i data-lucide="x" class="lucide" style="width:.85rem;height:.85rem;"></i> Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ARCHIVE SECTION — every archived content item, grouped by type -->
        <div class="archive-section" id="archive">
            <div class="archive-section-title">Archive</div>
            <div class="archive-section-sub">Everything archived across the site — hidden from the public site, never deleted. Restore anything back to live from here.</div>

            <?php if (empty($archivedGrouped)): ?>
                <div class="archive-empty">Nothing archived yet.</div>
            <?php else: ?>
                <?php $archiveIcons = [
                    'destination' => 'map-pin', 'product' => 'shopping-bag', 'restaurant' => 'utensils',
                    'fiesta' => 'calendar-heart', 'person' => 'users-round', 'announcement' => 'megaphone',
                    'about_section' => 'image', 'partner' => 'handshake', 'event_photo' => 'images',
                    'page_widget' => 'layout-template',
                ]; ?>
                <?php foreach ($archivedGrouped as $type => $group): ?>
                    <details class="archive-category">
                        <summary class="archive-category-head">
                            <span class="archive-category-name">
                                <i data-lucide="<?php echo htmlspecialchars($archiveIcons[$type] ?? 'archive'); ?>" class="lucide" style="width:1rem;height:1rem;"></i>
                                <?php echo htmlspecialchars($group['label']); ?>
                                <span class="archive-category-count"><?php echo count($group['items']); ?></span>
                            </span>
                            <i data-lucide="chevron-right" class="lucide archive-category-chevron" style="width:1rem;height:1rem;"></i>
                        </summary>
                        <div class="archive-items">
                            <?php foreach ($group['items'] as $item): ?>
                                <div class="archive-item">
                                    <div class="archive-item-thumb">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="">
                                        <?php else: ?>
                                            <i data-lucide="<?php echo htmlspecialchars($archiveIcons[$type] ?? 'archive'); ?>" class="lucide" style="width:1rem;height:1rem;color:rgba(245,237,216,.3);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="archive-item-body">
                                        <div class="archive-item-name"><?php echo htmlspecialchars($item['name'] ?: ('#' . $item['id'])); ?></div>
                                        <div class="archive-item-date">Archived · <?php echo htmlspecialchars(date('M j, Y', strtotime($item['created_at']))); ?></div>
                                    </div>
                                    <form method="POST" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="form_action" value="restore">
                                        <input type="hidden" name="entity_type" value="<?php echo htmlspecialchars($type); ?>">
                                        <input type="hidden" name="entity_id" value="<?php echo (int) $item['id']; ?>">
                                        <button type="submit" class="tbl-btn edit"><i data-lucide="rotate-ccw" class="lucide" style="width:.72rem;height:.72rem;"></i> Restore</button>
                                    </form>
                                    <a href="<?php echo BASE_URL; ?>/admin/<?php echo htmlspecialchars($group['page']); ?>" class="tbl-btn" style="background:rgba(255,255,255,.05);color:rgba(245,237,216,.6);"><i data-lucide="external-link" class="lucide" style="width:.72rem;height:.72rem;"></i> Open</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<div class="toast" id="toast"></div>

<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-theme.js"></script>
<script src="../assets/js/admin-notifications.js"></script>
<script src="../assets/js/adminusers.js"></script>
<script>initSidebarCollapse();</script>
<?php if ($flashMessage): ?>
<script>document.addEventListener('DOMContentLoaded', function () { showToast(<?php echo json_encode($flashMessage); ?>); });</script>
<?php endif; ?>
<script>
    (function initLucide() {
        if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        else { document.addEventListener('DOMContentLoaded', function () { if (typeof lucide !== 'undefined') lucide.createIcons(); }); }
    })();
</script>

</body>
</html>
