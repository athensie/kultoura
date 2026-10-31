<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once '../config/dbmain.php';
require_once '../config/csrf.php';
require_once '../config/admin_requests.php';
require_once '../config/homepage_content.php';
define('BASE_URL', '/kultoura');
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
kt_homepage_content_ensure_schema($conn);
$pendingRequestCount = kt_requests_pending_count($conn);
$flashMessage = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    csrf_verify();
    $formAction = $_POST['form_action'];
    [$entityType, $verb] = (function () use ($formAction) {
        foreach (['partner', 'event_photo'] as $type) {
            foreach (['add' => 'create', 'edit' => 'update', 'delete' => 'archive'] as $prefix => $action) {
                if ($formAction === "{$prefix}_{$type}") return [$type, $action];
            }
        }
        return [null, null];
    })();
    if (!$entityType) {
        $_SESSION['admin_flash'] = 'Unrecognized action.';
        header("Location: adminhomepage.php");
        exit;
    }
    $id = ($verb === 'update' || $verb === 'archive') ? (int) ($_POST['id'] ?? 0) : null;
    if ($verb === 'archive') {
        $data = ['archive_note' => $_POST['archive_note'] ?? ''];
        $meta = kt_entity_meta($entityType);
        $labelCol = $entityType === 'partner' ? 'name' : 'caption';
        $stmt = $conn->prepare("SELECT `$labelCol` AS name FROM `{$meta['table']}` WHERE `{$meta['idCol']}` = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $label = $row['name'] ?? ('Item #' . $id);
    } elseif ($entityType === 'partner') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['admin_flash'] = 'Please enter a partner name.';
            header("Location: adminhomepage.php");
            exit;
        }
        $logo = kt_homepage_handle_image_upload('logo_file', 'partners', 'partner')
            ?? ($verb === 'update' ? trim($_POST['existing_logo'] ?? '') : '');
        if ($verb === 'create' && $logo === '') {
            $_SESSION['admin_flash'] = 'Please upload a logo.';
            header("Location: adminhomepage.php");
            exit;
        }
        $data = [
            'name'        => $name,
            'logo'        => $logo,
            'website_url' => trim($_POST['website_url'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'status'      => 'live',
        ];
        $label = $name;
    } elseif ($entityType === 'event_photo') {
        $caption = trim($_POST['caption'] ?? '');
        if ($caption === '') {
            $_SESSION['admin_flash'] = 'Please enter a short caption.';
            header("Location: adminhomepage.php");
            exit;
        }
        $image = kt_homepage_handle_image_upload('image_file', 'events', 'event')
            ?? ($verb === 'update' ? trim($_POST['existing_image'] ?? '') : '');
        if ($verb === 'create' && $image === '') {
            $_SESSION['admin_flash'] = 'Please upload a photo.';
            header("Location: adminhomepage.php");
            exit;
        }
        $data = [
            'image'   => $image,
            'caption' => $caption,
            'status'  => 'live',
        ];
        $label = $caption;
    }
    if ($isSuperAdmin) {
        kt_apply_entity_change($conn, $entityType, $verb, $id, $data);
        $_SESSION['admin_flash'] = $verb === 'archive'
            ? '"' . $label . '" archived.'
            : '"' . $label . '" was ' . ($verb === 'create' ? 'added' : 'updated') . '.';
    } else {
        kt_requests_create($conn, $entityType, $verb, $id, $data, $label, (int) $_SESSION['user_id'], $adminName);
        $_SESSION['admin_flash'] = 'Your request to ' . ($verb === 'archive' ? 'archive' : $verb) . ' "' . $label . '" was submitted for Super Admin approval.';
    }
    header("Location: adminhomepage.php");
    exit;
}
$partners = [];
if ($result = $conn->query("SELECT * FROM partners WHERE status = 'live' ORDER BY created_at DESC")) {
    $partners = $result->fetch_all(MYSQLI_ASSOC);
}
$eventPhotos = [];
if ($result = $conn->query("SELECT * FROM event_gallery WHERE status = 'live' ORDER BY created_at DESC")) {
    $eventPhotos = $result->fetch_all(MYSQLI_ASSOC);
}
$pendingPartners = [];
$pendingEventPhotos = [];
foreach (kt_requests_list($conn, 'pending') as $req) {
    if (!$isSuperAdmin && (int) $req['requested_by'] !== (int) $_SESSION['user_id']) {
        continue;
    }
    if ($req['entity_type'] === 'partner') {
        $pendingPartners[] = $req;
    } elseif ($req['entity_type'] === 'event_photo') {
        $pendingEventPhotos[] = $req;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Content – KULTOURA Admin</title>
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
    <link rel="stylesheet" href="../assets/css/admin-theme-toggle.css">
    <link rel="stylesheet" href="../assets/css/admin-viewtoggle.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminhomepage.php" class="active"><span class="nav-icon"><i data-lucide="layout-grid" class="lucide"></i></span> Homepage Content</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpagewidgets.php"><span class="nav-icon"><i data-lucide="layout-template" class="lucide"></i></span> Page Widgets</a></li>
    </ul>
    <div class="sidebar-section">Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminannouncements.php"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
        <?php if ($isSuperAdmin): ?>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminrequests.php"><span class="nav-icon"><i data-lucide="inbox" class="lucide"></i></span> Requests<?php if ($pendingRequestCount > 0): ?> <span style="background:var(--gold,#C8A96E);color:#1a1812;font-size:.62rem;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:4px;"><?php echo $pendingRequestCount; ?></span><?php endif; ?></a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminarchive.php"><span class="nav-icon"><i data-lucide="archive" class="lucide"></i></span> Archive</a></li>
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
<div class="main-content">
    <div class="dash-wrapper">
        <div class="page-header animate">
            <div>
                <div class="section-label">Content Management</div>
                <h1>Homepage <em>Content</em></h1>
                <p>Partner logos and the event photo gallery shown on the homepage.</p>
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
        <div class="admin-tabs animate">
            <button type="button" class="admin-tab-btn active" data-tab="partners" onclick="ktSwitchHomeTab('partners')"><i data-lucide="handshake" class="lucide" style="width:.85rem;height:.85rem;"></i> Partners</button>
            <button type="button" class="admin-tab-btn" data-tab="gallery" onclick="ktSwitchHomeTab('gallery')"><i data-lucide="images" class="lucide" style="width:.85rem;height:.85rem;"></i> Event Gallery</button>
        </div>
        <div class="admin-tab-panel animate" id="homeTab-partners">
            <div class="header-actions-row">
                <p class="tab-sub">Brand/business logos shown in the "Our Partners" section.</p>
                <button type="button" class="btn-primary" onclick="openModal('addPartnerModal')"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> Add Partner</button>
            </div>
            <div class="grid-view-panel">
                <?php if (empty($partners)): ?>
                    <div class="chart-empty grid-empty">
                        <i data-lucide="handshake" class="lucide"></i>
                        <div class="chart-empty-title">No partners yet</div>
                        <div class="chart-empty-sub">Logos added here show up in the homepage's "Our Partners" section.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($partners as $p): ?>
                        <div class="grid-card partner-card">
                            <div class="grid-card-media partner-media"><img src="<?php echo htmlspecialchars($p['logo']); ?>" alt=""></div>
                            <div class="grid-card-body">
                                <div class="grid-card-title"><?php echo htmlspecialchars($p['name']); ?></div>
                                <?php if (!empty($p['website_url'])): ?>
                                    <div class="grid-card-sub"><a href="<?php echo htmlspecialchars($p['website_url']); ?>" target="_blank" rel="noopener" style="color:inherit;"><?php echo htmlspecialchars($p['website_url']); ?></a></div>
                                <?php endif; ?>
                            </div>
                            <div class="grid-card-actions">
                                <button type="button" class="tbl-btn edit" onclick="openEditPartner(<?php echo (int) $p['id']; ?>)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                <button type="button" class="tbl-btn delete" onclick="confirmArchive('partner', <?php echo (int) $p['id']; ?>, '<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>')"><i data-lucide="archive" class="lucide" style="width:.75rem;height:.75rem;"></i> Archive</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($pendingPartners)): ?>
                <h3 class="pending-heading">Pending Super Admin Approval</h3>
                <div class="grid-view-panel">
                    <?php foreach ($pendingPartners as $req): $d = $req['payload']; ?>
                        <div class="grid-card partner-card pending-card">
                            <span class="pending-badge"><?php echo htmlspecialchars(ucfirst($req['action'])); ?> pending</span>
                            <div class="grid-card-media partner-media"><?php if (!empty($d['logo'])): ?><img src="<?php echo htmlspecialchars($d['logo']); ?>" alt=""><?php endif; ?></div>
                            <div class="grid-card-body">
                                <div class="grid-card-title"><?php echo htmlspecialchars($d['name'] ?? $req['entity_label']); ?></div>
                                <div class="grid-card-sub">Submitted by <?php echo htmlspecialchars($req['requested_by_name']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="admin-tab-panel animate" id="homeTab-gallery" hidden>
            <div class="header-actions-row">
                <p class="tab-sub">Photos of events hosted in Malvar, shown as a gallery grid on the homepage.</p>
                <button type="button" class="btn-primary" onclick="openModal('addEventPhotoModal')"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> Add Photo</button>
            </div>
            <div class="grid-view-panel">
                <?php if (empty($eventPhotos)): ?>
                    <div class="chart-empty grid-empty">
                        <i data-lucide="images" class="lucide"></i>
                        <div class="chart-empty-title">No event photos yet</div>
                        <div class="chart-empty-sub">Photos added here show up in the homepage's event gallery.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($eventPhotos as $g): ?>
                        <div class="grid-card">
                            <div class="grid-card-media"><img src="<?php echo htmlspecialchars($g['image']); ?>" alt=""></div>
                            <div class="grid-card-body">
                                <div class="grid-card-title"><?php echo htmlspecialchars($g['caption']); ?></div>
                            </div>
                            <div class="grid-card-actions">
                                <button type="button" class="tbl-btn edit" onclick="openEditEventPhoto(<?php echo (int) $g['id']; ?>)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                <button type="button" class="tbl-btn delete" onclick="confirmArchive('event_photo', <?php echo (int) $g['id']; ?>, '<?php echo htmlspecialchars($g['caption'], ENT_QUOTES); ?>')"><i data-lucide="archive" class="lucide" style="width:.75rem;height:.75rem;"></i> Archive</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($pendingEventPhotos)): ?>
                <h3 class="pending-heading">Pending Super Admin Approval</h3>
                <div class="grid-view-panel">
                    <?php foreach ($pendingEventPhotos as $req): $d = $req['payload']; ?>
                        <div class="grid-card pending-card">
                            <span class="pending-badge"><?php echo htmlspecialchars(ucfirst($req['action'])); ?> pending</span>
                            <div class="grid-card-media"><?php if (!empty($d['image'])): ?><img src="<?php echo htmlspecialchars($d['image']); ?>" alt=""><?php endif; ?></div>
                            <div class="grid-card-body">
                                <div class="grid-card-title"><?php echo htmlspecialchars($d['caption'] ?? $req['entity_label']); ?></div>
                                <div class="grid-card-sub">Submitted by <?php echo htmlspecialchars($req['requested_by_name']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="modal-overlay" id="addPartnerModal" onclick="closeModalOutside(event, 'addPartnerModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('addPartnerModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add Partner</div>
        <div class="modal-sub">Shown as a logo badge in the homepage's "Our Partners" section.</div>
        <form method="POST" action="adminhomepage.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="add_partner">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">Partner / Brand Name</label>
                <input class="form-input" type="text" name="name" placeholder="e.g. CDO Foodsphere" required>
            </div>
            <div class="form-group">
                <label class="form-label">Website (optional)</label>
                <input class="form-input" type="text" name="website_url" placeholder="https://…">
            </div>
            <div class="form-group">
                <label class="form-label">Short description (optional)</label>
                <textarea class="form-input" name="description" rows="3" maxlength="300" placeholder="Shown on the back of the card when it's hovered."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Logo</label>
                <input class="form-input" type="file" name="logo_file" accept="image/*" required>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Add Partner</button>
                <button type="button" class="btn-ghost" onclick="closeModal('addPartnerModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="editPartnerModal" onclick="closeModalOutside(event, 'editPartnerModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editPartnerModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Partner</div>
        <form method="POST" action="adminhomepage.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="edit_partner">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" id="editPartnerId">
            <input type="hidden" name="existing_logo" id="editPartnerExistingLogo">
            <div class="form-group">
                <label class="form-label">Partner / Brand Name</label>
                <input class="form-input" type="text" name="name" id="editPartnerName" required>
            </div>
            <div class="form-group">
                <label class="form-label">Website (optional)</label>
                <input class="form-input" type="text" name="website_url" id="editPartnerWebsite">
            </div>
            <div class="form-group">
                <label class="form-label">Short description (optional)</label>
                <textarea class="form-input" name="description" id="editPartnerDescription" rows="3" maxlength="300" placeholder="Shown on the back of the card when it's hovered."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Replace Logo</label>
                <input class="form-input" type="file" name="logo_file" accept="image/*">
                <p class="image-current-hint">Leave empty to keep the current logo.</p>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editPartnerModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="addEventPhotoModal" onclick="closeModalOutside(event, 'addEventPhotoModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('addEventPhotoModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add Event Photo</div>
        <div class="modal-sub">A short caption works best — it's shown as bold overlay text on the photo, e.g. "EPIC" or "FIESTA".</div>
        <form method="POST" action="adminhomepage.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="add_event_photo">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">Photo</label>
                <input class="form-input" type="file" name="image_file" accept="image/*" required>
            </div>
            <div class="form-group">
                <label class="form-label">Caption</label>
                <input class="form-input" type="text" name="caption" maxlength="40" placeholder="e.g. Fiesta 2026" required>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Add Photo</button>
                <button type="button" class="btn-ghost" onclick="closeModal('addEventPhotoModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="editEventPhotoModal" onclick="closeModalOutside(event, 'editEventPhotoModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editEventPhotoModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Event Photo</div>
        <form method="POST" action="adminhomepage.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="edit_event_photo">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" id="editEventPhotoId">
            <input type="hidden" name="existing_image" id="editEventPhotoExistingImage">
            <div class="form-group">
                <label class="form-label">Replace Photo</label>
                <input class="form-input" type="file" name="image_file" accept="image/*">
                <p class="image-current-hint">Leave empty to keep the current photo.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Caption</label>
                <input class="form-input" type="text" name="caption" id="editEventPhotoCaption" maxlength="40" required>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editEventPhotoModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="archiveModal" onclick="closeModalOutside(event, 'archiveModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div class="modal-title" id="archiveTitle">Archive Item?</div>
        <div class="modal-sub"><?php echo $isSuperAdmin ? 'It will be removed from the homepage, not permanently deleted.' : 'This will be submitted to a Super Admin for approval before it\'s archived.'; ?></div>
        <form method="POST" action="adminhomepage.php">
            <input type="hidden" name="id" id="archiveIdInput">
            <input type="hidden" name="form_action" id="archiveFormAction">
            <?php echo csrf_field(); ?>
            <div class="form-group" style="text-align:left; margin-top:16px;">
                <label class="form-label">Reason for archiving (optional)</label>
                <textarea class="form-textarea" name="archive_note" rows="3" placeholder="e.g. Partnership ended, photo outdated…"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="tbl-btn delete" style="flex:1; padding:12px;"><?php echo $isSuperAdmin ? 'Yes, Archive' : 'Submit Request'; ?></button>
                <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('archiveModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="toast" id="toast"></div>
<script>
    window.partnersData = <?php echo json_encode($partners, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
    window.eventPhotosData = <?php echo json_encode($eventPhotos, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
    <?php if ($flashMessage): ?>
    window.__adminFlash = <?php echo json_encode($flashMessage, JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null'; ?>;
    <?php endif; ?>
</script>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-theme.js"></script>
<script src="../assets/js/admin-notifications.js"></script>
<script src="../assets/js/adminhomepage.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/adminhomepage.js') ?: time(); ?>"></script>
<script>initSidebarCollapse();</script>
</body>
</html>
