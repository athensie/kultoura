<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/admin_requests.php';
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
kt_requests_ensure_schema($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && in_array($_POST['form_action'], ['restore', 'delete'], true)) {
    csrf_verify();
    $formAction = $_POST['form_action'];
    $entityType = $_POST['entity_type'] ?? '';
    $entityId = (int) ($_POST['entity_id'] ?? 0);
    $archiveMeta = kt_archive_types()[$entityType] ?? null;
    if ($formAction === 'restore') {
        if ($archiveMeta && $entityId > 0 && kt_archive_restore($conn, $entityType, $entityId)) {
            $_SESSION['admin_flash'] = 'Restored — it\'s live again on the site.';
        } else {
            $_SESSION['admin_flash'] = 'Could not restore that item.';
        }
    } else {
        if ($archiveMeta && $entityId > 0 && kt_archive_permanently_delete($conn, $entityType, $entityId)) {
            $_SESSION['admin_flash'] = 'Permanently deleted — this cannot be undone.';
        } else {
            $_SESSION['admin_flash'] = 'Could not delete that item.';
        }
    }
    header("Location: " . BASE_URL . "/admin/adminarchive.php");
    exit;
}
$flashMessage = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
$pendingRequestCount = kt_requests_pending_count($conn);
$archivedGrouped = kt_archived_items_grouped($conn);
$archiveFieldSkip = ['status', 'created_at', 'archive_note', 'views', 'favorited'];
foreach ($archivedGrouped as $type => &$group) {
    $meta = kt_archive_types()[$type];
    $skip = array_merge($archiveFieldSkip, [$meta['idCol'], 'id', $meta['nameCol'], 'name', $meta['imageCol'], 'image']);
    foreach ($group['items'] as &$item) {
        $details = [];
        foreach ($item as $col => $val) {
            if (in_array($col, $skip, true) || $val === null || $val === '') continue;
            $label = ucwords(str_replace('_', ' ', $col));
            $details[] = ['label' => $label, 'value' => (string) $val];
        }
        $item['details'] = $details;
    }
    unset($item);
}
unset($group);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive – KULTOURA Admin</title>
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
        .archive-section-sub { font-size: .8rem; color: rgba(245,237,216,.45); margin-bottom: 24px; }
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
        .archive-details-thumb { width: 100%; height: 160px; border-radius: 10px; overflow: hidden; background: rgba(255,255,255,.04); margin-bottom: 16px; display: flex; align-items: center; justify-content: center; }
        .archive-details-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .archive-note-box { text-align: left; background: rgba(200,169,110,.08); border: 1px solid rgba(200,169,110,.25); border-radius: 10px; padding: 12px 14px; margin: 16px 0; }
        .archive-note-label { font-size: .68rem; letter-spacing: 1px; text-transform: uppercase; color: #C8A96E; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
        .archive-note-text { font-size: .85rem; color: rgba(245,237,216,.85); line-height: 1.5; white-space: pre-wrap; }
        .archive-note-by { font-size: .68rem; color: rgba(245,237,216,.4); margin-top: 8px; }
        .archive-details-list { text-align: left; display: flex; flex-direction: column; gap: 10px; max-height: 280px; overflow-y: auto; }
        .archive-detail-row { border-bottom: 1px solid rgba(255,255,255,.06); padding-bottom: 8px; }
        .archive-detail-label { font-size: .65rem; letter-spacing: 1px; text-transform: uppercase; color: rgba(245,237,216,.35); margin-bottom: 2px; }
        .archive-detail-value { font-size: .82rem; color: rgba(245,237,216,.8); word-break: break-word; }
    </style>
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminrequests.php"><span class="nav-icon"><i data-lucide="inbox" class="lucide"></i></span> Requests<?php if ($pendingRequestCount > 0): ?> <span style="background:var(--gold,#C8A96E);color:#1a1812;font-size:.62rem;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:4px;"><?php echo $pendingRequestCount; ?></span><?php endif; ?></a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminarchive.php" class="active"><span class="nav-icon"><i data-lucide="archive" class="lucide"></i></span> Archive</a></li>
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
<div class="main-content">
    <div class="dash-wrapper">
        <div class="page-header animate">
            <div>
                <div class="section-label">Content Management</div>
                <h1><em>Archive</em></h1>
                <p>Everything archived across the site — hidden from the public site. Restore anything back to live, or delete it permanently.</p>
            </div>
        </div>
        <?php if (empty($archivedGrouped)): ?>
            <div class="chart-empty">
                <i data-lucide="archive" class="lucide"></i>
                <div class="chart-empty-title">Nothing archived yet</div>
                <div class="chart-empty-sub">Anything archived from the content pages will show up here, grouped by type.</div>
            </div>
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
                                    <div class="archive-item-date">
                                        Archived · <?php echo htmlspecialchars(date('M j, Y', strtotime($item['created_at']))); ?>
                                        <?php if (!empty($item['archive_note']['note'])): ?>
                                            <span title="A reason was left for this archive" style="color:#C8A96E;"> · <i data-lucide="message-square" class="lucide" style="width:.65rem;height:.65rem;vertical-align:-1px;"></i> Note</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="restore">
                                    <input type="hidden" name="entity_type" value="<?php echo htmlspecialchars($type); ?>">
                                    <input type="hidden" name="entity_id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" class="tbl-btn edit"><i data-lucide="rotate-ccw" class="lucide" style="width:.72rem;height:.72rem;"></i> Restore</button>
                                </form>
                                <button type="button" class="tbl-btn" style="background:rgba(255,255,255,.05);color:rgba(245,237,216,.6);" onclick="openArchiveDetails('<?php echo htmlspecialchars($type); ?>', <?php echo (int) $item['id']; ?>)"><i data-lucide="eye" class="lucide" style="width:.72rem;height:.72rem;"></i> Open</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete &quot;<?php echo htmlspecialchars(addslashes($item['name'] ?: ('#' . $item['id'])), ENT_QUOTES); ?>&quot;? This cannot be undone — it will be gone for good, not just archived.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="entity_type" value="<?php echo htmlspecialchars($type); ?>">
                                    <input type="hidden" name="entity_id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" class="tbl-btn delete"><i data-lucide="trash-2" class="lucide" style="width:.72rem;height:.72rem;"></i> Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div class="modal-overlay" id="archiveDetailsModal" onclick="closeModalOutside(event, 'archiveDetailsModal')">
    <div class="modal-card" style="max-width:480px;">
        <button class="modal-close" onclick="closeModal('archiveDetailsModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="archive-details-thumb" id="adThumb"></div>
        <div class="modal-title" id="adName">—</div>
        <div class="modal-sub" id="adMeta">—</div>
        <div class="archive-note-box" id="adNoteBox" hidden>
            <div class="archive-note-label"><i data-lucide="message-square" class="lucide" style="width:.8rem;height:.8rem;"></i> Reason for archiving</div>
            <div class="archive-note-text" id="adNoteText">—</div>
            <div class="archive-note-by" id="adNoteBy"></div>
        </div>
        <div class="archive-details-list" id="adDetailsList"></div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('archiveDetailsModal')">Close</button>
        </div>
    </div>
</div>
<div class="toast" id="toast"></div>
<script>
    const archivedGroupedData = <?php echo json_encode($archivedGrouped); ?>;
    // Every value here is free-text admin input (names, notes,
    // descriptions…), so it's built with DOM nodes / textContent
    // rather than innerHTML string concatenation — never trust it as HTML.
    function openArchiveDetails(type, id) {
        const group = archivedGroupedData[type];
        const item = group ? group.items.find(i => Number(i.id) === Number(id)) : null;
        if (!item) return;
        document.getElementById('adName').textContent = item.name || ('#' + item.id);
        document.getElementById('adMeta').textContent = 'Archived ' + new Date(item.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        const thumb = document.getElementById('adThumb');
        thumb.textContent = '';
        if (item.image) {
            const img = document.createElement('img');
            img.src = item.image;
            img.alt = '';
            thumb.appendChild(img);
        } else {
            const icon = document.createElement('i');
            icon.setAttribute('data-lucide', 'archive');
            icon.className = 'lucide';
            icon.style.cssText = 'width:1.4rem;height:1.4rem;color:rgba(245,237,216,.25);';
            thumb.appendChild(icon);
        }
        const noteBox = document.getElementById('adNoteBox');
        if (item.archive_note && item.archive_note.note) {
            noteBox.hidden = false;
            document.getElementById('adNoteText').textContent = item.archive_note.note;
            const by = item.archive_note.archived_by_name;
            const at = item.archive_note.archived_at ? new Date(item.archive_note.archived_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
            document.getElementById('adNoteBy').textContent = (by ? 'By ' + by : '') + (by && at ? ' · ' : '') + at;
        } else {
            noteBox.hidden = true;
        }
        const list = document.getElementById('adDetailsList');
        list.textContent = '';
        const details = item.details || [];
        if (!details.length) {
            const empty = document.createElement('div');
            empty.className = 'archive-empty';
            empty.textContent = 'No other details on file.';
            list.appendChild(empty);
        } else {
            details.forEach(d => {
                const row = document.createElement('div');
                row.className = 'archive-detail-row';
                const label = document.createElement('div');
                label.className = 'archive-detail-label';
                label.textContent = d.label;
                const value = document.createElement('div');
                value.className = 'archive-detail-value';
                value.textContent = d.value;
                row.appendChild(label);
                row.appendChild(value);
                list.appendChild(row);
            });
        }
        openModal('archiveDetailsModal');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
</script>
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
