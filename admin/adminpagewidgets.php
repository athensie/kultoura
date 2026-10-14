<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once '../config/dbmain.php';
require_once '../config/csrf.php';
require_once '../config/admin_requests.php';
require_once '../config/page_widgets.php';

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 */
define('BASE_URL', '/kultoura');

/*
 |--------------------------------------------------------------------
 | ACCESS CONTROL
 |--------------------------------------------------------------------
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
$isSuperAdmin = kt_is_super_admin();
kt_requests_ensure_schema($conn);
kt_page_widgets_ensure_schema($conn);
$pendingRequestCount = kt_requests_pending_count($conn);
$pageChoices = kt_page_widget_pages();

/*
 |--------------------------------------------------------------------
 | PAGE WIDGETS — one admin-customizable promo banner per tourism page
 |--------------------------------------------------------------------
 | Singleton per page_slug (the table's UNIQUE key): "Add" on a page
 | that already has a row (even an archived one) quietly becomes an
 | update instead of a duplicate insert, so re-adding after archiving
 | just works. Goes through the same Admin/Super Admin approval queue
 | as the rest of the site (see config/admin_requests.php).
 */
$flashMessage = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    csrf_verify();
    $formAction = $_POST['form_action'];

    $verb = match ($formAction) {
        'add_page_widget'    => 'create',
        'edit_page_widget'   => 'update',
        'delete_page_widget' => 'archive',
        default               => null,
    };

    if (!$verb) {
        $_SESSION['admin_flash'] = 'Unrecognized action.';
        header("Location: adminpagewidgets.php");
        exit;
    }

    $id = ($verb === 'update' || $verb === 'archive') ? (int) ($_POST['id'] ?? 0) : null;

    if ($verb === 'archive') {
        $data = [];
        $stmt = $conn->prepare("SELECT page_slug FROM page_widgets WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $label = $pageChoices[$row['page_slug'] ?? ''] ?? ('Widget #' . $id);
    } else {
        $pageSlug = trim($_POST['page_slug'] ?? '');
        if (!isset($pageChoices[$pageSlug])) {
            $_SESSION['admin_flash'] = 'Please choose a valid page.';
            header("Location: adminpagewidgets.php");
            exit;
        }

        // Re-adding a widget for a page that already has a (possibly
        // archived) row updates that row instead of hitting the
        // UNIQUE(page_slug) constraint with a second insert.
        if ($verb === 'create') {
            $existing = $conn->prepare("SELECT id FROM page_widgets WHERE page_slug = ?");
            $existing->bind_param('s', $pageSlug);
            $existing->execute();
            $existingRow = $existing->get_result()->fetch_assoc();
            $existing->close();
            if ($existingRow) {
                $verb = 'update';
                $id = (int) $existingRow['id'];
            }
        }

        // Headline/subtext are no longer admin-typed — the banner always
        // shows that page's own title/description (kt_page_widget_page_meta()),
        // these columns are just kept populated to satisfy the NOT NULL
        // schema without a migration.
        $meta = kt_page_widget_page_meta()[$pageSlug] ?? ['title' => $pageSlug, 'description' => ''];

        $data = [
            'page_slug'          => $pageSlug,
            'bg_color'           => trim($_POST['bg_color'] ?? '') ?: '#8b2e1a',
            'headline'           => $meta['title'],
            'subtext'            => $meta['description'] ?: null,
            'card1_title'        => trim($_POST['card1_title'] ?? '') ?: null,
            'card1_text'         => trim($_POST['card1_text'] ?? '') ?: null,
            'card2_title'        => trim($_POST['card2_title'] ?? '') ?: null,
            'card2_text'         => trim($_POST['card2_text'] ?? '') ?: null,
            'gallery_heading'    => trim($_POST['gallery_heading'] ?? '') ?: null,
            'gallery_text'       => trim($_POST['gallery_text'] ?? '') ?: null,
            'main_image'         => kt_page_widget_handle_image_upload('main_image_file'),
            'gallery_main_image' => kt_page_widget_handle_image_upload('gallery_main_image_file'),
            'gallery_image_1'    => kt_page_widget_handle_image_upload('gallery_image_1_file'),
            'gallery_image_2'    => kt_page_widget_handle_image_upload('gallery_image_2_file'),
            'gallery_image_3'    => kt_page_widget_handle_image_upload('gallery_image_3_file'),
            'gallery_image_4'    => kt_page_widget_handle_image_upload('gallery_image_4_file'),
            'status'             => 'live',
        ];
        $label = $pageChoices[$pageSlug];
    }

    if ($isSuperAdmin) {
        kt_apply_entity_change($conn, 'page_widget', $verb, $id, $data);
        $_SESSION['admin_flash'] = $verb === 'archive'
            ? '"' . $label . '" widget archived.'
            : '"' . $label . '" widget was ' . ($verb === 'create' ? 'added' : 'updated') . '.';
    } else {
        kt_requests_create($conn, 'page_widget', $verb, $id, $data, $label, (int) $_SESSION['user_id'], $adminName);
        $_SESSION['admin_flash'] = 'Your request to ' . ($verb === 'archive' ? 'archive' : $verb) . ' the "' . $label . '" widget was submitted for Super Admin approval.';
    }

    header("Location: adminpagewidgets.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | READ
 |--------------------------------------------------------------------
 */
$widgetsBySlug = [];
if ($result = $conn->query("SELECT * FROM page_widgets WHERE status = 'live'")) {
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $w) {
        $widgetsBySlug[$w['page_slug']] = $w;
    }
}

// Same "show the admin their own pending submissions" pattern as
// admin/adminhomepage.php — adminrequests.php is Super Admin only.
$pendingWidgets = [];
foreach (kt_requests_list($conn, 'pending') as $req) {
    if (!$isSuperAdmin && (int) $req['requested_by'] !== (int) $_SESSION['user_id']) {
        continue;
    }
    if ($req['entity_type'] === 'page_widget') {
        $pendingWidgets[] = $req;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Widgets – KULTOURA Admin</title>

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

    <link rel="stylesheet" href="../assets/css/adminhomepage.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/adminhomepage.css') ?: time(); ?>">
    <link rel="stylesheet" href="../assets/css/adminpagewidgets.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/adminpagewidgets.css') ?: time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin-theme-toggle.css">
    <link rel="stylesheet" href="../assets/css/admin-viewtoggle.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpagewidgets.php" class="active"><span class="nav-icon"><i data-lucide="layout-template" class="lucide"></i></span> Page Widgets</a></li>
    </ul>

    <div class="sidebar-section">Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminannouncements.php"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
        <?php if ($isSuperAdmin): ?>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminrequests.php"><span class="nav-icon"><i data-lucide="inbox" class="lucide"></i></span> Requests<?php if ($pendingRequestCount > 0): ?> <span style="background:var(--gold,#C8A96E);color:#1a1812;font-size:.62rem;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:4px;"><?php echo $pendingRequestCount; ?></span><?php endif; ?></a></li>
        <?php endif; ?>
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

        <!-- PAGE HEADER -->
        <div class="page-header animate">
            <div>
                <div class="section-label">Content Management</div>
                <h1>Page <em>Widgets</em></h1>
                <p>A customizable promo banner + photo strip, one per tourism page.</p>
            </div>
        </div>

        <?php if (!$isSuperAdmin): ?>
        <div style="background:rgba(200,169,110,.08);border:1px solid rgba(200,169,110,.3);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:.8rem;color:rgba(245,237,216,.75);display:flex;align-items:center;gap:10px;">
            <i data-lucide="shield-alert" class="lucide" style="width:1rem;height:1rem;color:var(--gold,#C8A96E);flex-shrink:0;"></i>
            <span>You're signed in as <strong>Admin</strong> — changes you submit here are sent to a Super Admin for approval before they go live.</span>
        </div>
        <?php elseif ($pendingRequestCount > 0): ?>
        <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:10px 16px;margin-bottom:18px;font-size:.8rem;color:rgba(245,237,216,.6);display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <span><?php echo $pendingRequestCount; ?> pending request<?php echo $pendingRequestCount === 1 ? '' : 's'; ?> awaiting your review.</span>
            <a href="<?php echo BASE_URL; ?>/admin/adminrequests.php" style="color:var(--gold,#C8A96E);font-weight:600;text-decoration:none;">Review →</a>
        </div>
        <?php endif; ?>

        <div class="pw-admin-list">
            <?php foreach ($pageChoices as $slug => $label): $w = $widgetsBySlug[$slug] ?? null; ?>
                <div class="pw-admin-row">
                    <div class="pw-admin-swatch" style="background:<?php echo htmlspecialchars($w['bg_color'] ?? '#f0ebe3'); ?>">
                        <?php if ($w && !empty($w['main_image'])): ?>
                            <img src="<?php echo htmlspecialchars($w['main_image']); ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="pw-admin-row-body">
                        <div class="pw-admin-row-title"><?php echo htmlspecialchars($label); ?></div>
                        <?php if ($w): ?>
                            <div class="pw-admin-row-sub">Live on this page<?php echo !empty($w['card1_text']) ? ' · ' . htmlspecialchars(mb_strimwidth($w['card1_text'], 0, 40, '…')) : ''; ?></div>
                        <?php else: ?>
                            <div class="pw-admin-row-sub pw-admin-row-empty">Not set up yet</div>
                        <?php endif; ?>
                    </div>
                    <div class="pw-admin-row-actions">
                        <?php if ($w): ?>
                            <button type="button" class="tbl-btn edit" onclick="openEditWidget(<?php echo (int) $w['id']; ?>)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                            <button type="button" class="tbl-btn delete" onclick="confirmArchiveWidget(<?php echo (int) $w['id']; ?>, '<?php echo htmlspecialchars($label, ENT_QUOTES); ?>')"><i data-lucide="archive" class="lucide" style="width:.75rem;height:.75rem;"></i> Archive</button>
                        <?php else: ?>
                            <button type="button" class="btn-primary" onclick="openAddWidget('<?php echo htmlspecialchars($slug, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($label, ENT_QUOTES); ?>')"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> Add Widget</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($pendingWidgets)): ?>
            <h3 class="pending-heading">Pending Super Admin Approval</h3>
            <div class="pw-admin-list">
                <?php foreach ($pendingWidgets as $req): $d = $req['payload']; ?>
                    <div class="pw-admin-row pending-card">
                        <span class="pending-badge"><?php echo htmlspecialchars(ucfirst($req['action'])); ?> pending</span>
                        <div class="pw-admin-swatch" style="background:<?php echo htmlspecialchars($d['bg_color'] ?? '#f0ebe3'); ?>"></div>
                        <div class="pw-admin-row-body">
                            <div class="pw-admin-row-title"><?php echo htmlspecialchars($pageChoices[$d['page_slug'] ?? ''] ?? $req['entity_label']); ?></div>
                            <div class="pw-admin-row-sub">Submitted by <?php echo htmlspecialchars($req['requested_by_name']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- ADD WIDGET MODAL -->
<div class="modal-overlay" id="addWidgetModal" onclick="closeModalOutside(event, 'addWidgetModal')">
    <div class="modal-card pw-modal-card">
        <button class="modal-close" onclick="closeModal('addWidgetModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add Widget — <span id="addWidgetPageLabel"></span></div>
        <div class="modal-sub">A photo grid shown near the top of that page, below the hero. The center photo's title/location come from the page itself — not editable here.</div>
        <form method="POST" action="adminpagewidgets.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="add_page_widget">
            <input type="hidden" name="page_slug" id="addWidgetPageSlug">
            <?php echo csrf_field(); ?>

            <p class="pw-form-section">Left Text Panel</p>
            <div class="form-group">
                <label class="form-label">Panel Background Color</label>
                <input class="form-input" type="color" name="bg_color" value="#1877f2" style="height:44px;padding:4px;">
            </div>
            <div class="form-group">
                <label class="form-label">Panel Text</label>
                <textarea class="form-input" name="card1_text" rows="2" maxlength="300" placeholder="e.g. Curated picks from Malvar's local favorites."></textarea>
            </div>

            <p class="pw-form-section">Photos</p>
            <div class="form-group">
                <label class="form-label">Center Photo</label>
                <input class="form-input" type="file" name="main_image_file" accept="image/*">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Left-Bottom Photo</label>
                    <input class="form-input" type="file" name="gallery_image_1_file" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Top-Right Photo</label>
                    <input class="form-input" type="file" name="gallery_main_image_file" accept="image/*">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Bottom-Right Photo</label>
                <input class="form-input" type="file" name="gallery_image_2_file" accept="image/*">
            </div>

            <p class="pw-form-section">"Powered By" Panel (optional)</p>
            <div class="form-group">
                <label class="form-label">Small Logo Image</label>
                <input class="form-input" type="file" name="gallery_image_3_file" accept="image/*">
            </div>
            <div class="form-group">
                <label class="form-label">Caption (e.g. "Powered by KulToura")</label>
                <input class="form-input" type="text" name="card2_text" maxlength="60">
            </div>

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Add Widget</button>
                <button type="button" class="btn-ghost" onclick="closeModal('addWidgetModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT WIDGET MODAL -->
<div class="modal-overlay" id="editWidgetModal" onclick="closeModalOutside(event, 'editWidgetModal')">
    <div class="modal-card pw-modal-card">
        <button class="modal-close" onclick="closeModal('editWidgetModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Widget — <span id="editWidgetPageLabel"></span></div>
        <div class="modal-sub">The center photo's title/location come from the page itself — not editable here.</div>
        <form method="POST" action="adminpagewidgets.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="edit_page_widget">
            <input type="hidden" name="id" id="editWidgetId">
            <input type="hidden" name="page_slug" id="editWidgetPageSlug">
            <?php echo csrf_field(); ?>

            <p class="pw-form-section">Left Text Panel</p>
            <div class="form-group">
                <label class="form-label">Panel Background Color</label>
                <input class="form-input" type="color" name="bg_color" id="editWidgetBgColor" style="height:44px;padding:4px;">
            </div>
            <div class="form-group">
                <label class="form-label">Panel Text</label>
                <textarea class="form-input" name="card1_text" id="editWidgetCard1Text" rows="2" maxlength="300"></textarea>
            </div>

            <p class="pw-form-section">Photos</p>
            <div class="form-group">
                <label class="form-label">Replace Center Photo</label>
                <input class="form-input" type="file" name="main_image_file" accept="image/*">
                <p class="image-current-hint">Leave empty to keep the current photo.</p>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Replace Left-Bottom Photo</label>
                    <input class="form-input" type="file" name="gallery_image_1_file" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Replace Top-Right Photo</label>
                    <input class="form-input" type="file" name="gallery_main_image_file" accept="image/*">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Replace Bottom-Right Photo</label>
                <input class="form-input" type="file" name="gallery_image_2_file" accept="image/*">
            </div>

            <p class="pw-form-section">"Powered By" Panel (optional)</p>
            <div class="form-group">
                <label class="form-label">Replace Small Logo Image</label>
                <input class="form-input" type="file" name="gallery_image_3_file" accept="image/*">
            </div>
            <div class="form-group">
                <label class="form-label">Caption (e.g. "Powered by KulToura")</label>
                <input class="form-input" type="text" name="card2_text" id="editWidgetCard2Text" maxlength="60">
            </div>

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editWidgetModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ARCHIVE CONFIRM MODAL -->
<div class="modal-overlay" id="archiveModal" onclick="closeModalOutside(event, 'archiveModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div class="modal-title" id="archiveTitle">Archive Widget?</div>
        <div class="modal-sub"><?php echo $isSuperAdmin ? 'It will stop showing on that page, not be permanently deleted.' : 'This will be submitted to a Super Admin for approval before it\'s archived.'; ?></div>
        <form method="POST" action="adminpagewidgets.php">
            <input type="hidden" name="id" id="archiveIdInput">
            <input type="hidden" name="form_action" value="delete_page_widget">
            <?php echo csrf_field(); ?>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="tbl-btn delete" style="flex:1; padding:12px;"><?php echo $isSuperAdmin ? 'Yes, Archive' : 'Submit Request'; ?></button>
                <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('archiveModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
    window.pageWidgetsData = <?php echo json_encode(array_values($widgetsBySlug), JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
    window.pageWidgetLabels = <?php echo json_encode($pageChoices, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
    <?php if ($flashMessage): ?>
    window.__adminFlash = <?php echo json_encode($flashMessage, JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null'; ?>;
    <?php endif; ?>
</script>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-theme.js"></script>
<script src="../assets/js/admin-notifications.js"></script>
<script src="../assets/js/adminhomepage.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/adminhomepage.js') ?: time(); ?>"></script>
<script src="../assets/js/adminpagewidgets.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/adminpagewidgets.js') ?: time(); ?>"></script>
<script>initSidebarCollapse();</script>

</body>
</html>
