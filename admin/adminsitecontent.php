<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
include '../config/sitecontent.php';
require_once '../config/csrf.php';

/*
 |--------------------------------------------------------------------
 | BASE URL / ACCESS CONTROL — same guard as the other admin pages.
 */
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

/*
 |--------------------------------------------------------------------
 | IMAGE UPLOAD HELPER — same convention as admindestinations.php.
 */
function handleSiteContentImageUpload(string $field): ?string
{
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return null;
    }

    // Confirm it's actually an image, not just a renamed file.
    if (@getimagesize($_FILES[$field]['tmp_name']) === false) {
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/sitecontent/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('site_', true) . '.' . $ext;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $filename)) {
        return BASE_URL . '/assets/uploads/sitecontent/' . $filename;
    }

    return null;
}

/*
 |--------------------------------------------------------------------
 | HANDLE ADD / EDIT / DELETE / REORDER — POST back to this same page.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $action = $_POST['action'];

    // ---- Hero / singleton photo slots (home_hero_1, home_hero_2, about_hero) ----
    if ($action === 'save_photo') {
        $key = (string) ($_POST['photo_key'] ?? '');
        if (in_array($key, ['home_hero_1', 'home_hero_2', 'home_hero_bg', 'about_hero'], true)) {
            if (!empty($_POST['remove_image'])) {
                $stmt = $conn->prepare("INSERT INTO site_photos (photo_key, image) VALUES (?, NULL) ON DUPLICATE KEY UPDATE image = NULL");
                $stmt->bind_param('s', $key);
                $stmt->execute();
                $stmt->close();
            } else {
                $imagePath = handleSiteContentImageUpload('image');
                if ($imagePath !== null) {
                    $stmt = $conn->prepare("INSERT INTO site_photos (photo_key, image) VALUES (?, ?) ON DUPLICATE KEY UPDATE image = VALUES(image)");
                    $stmt->bind_param('ss', $key, $imagePath);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    // ---- About sections ----
    if ($action === 'create_section') {
        $imagePath = handleSiteContentImageUpload('image');
        $maxRow = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS m FROM about_sections")->fetch_assoc();
        $nextOrder = (int) $maxRow['m'] + 1;

        $stmt = $conn->prepare("INSERT INTO about_sections (title, icon_key, image, body, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssi', $_POST['title'], $_POST['icon_key'], $imagePath, $_POST['body'], $nextOrder);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'update_section') {
        $id = (int) $_POST['id'];
        $newImage = handleSiteContentImageUpload('image');

        if (!empty($_POST['remove_image'])) {
            $stmt = $conn->prepare("UPDATE about_sections SET title = ?, icon_key = ?, body = ?, image = NULL WHERE section_id = ?");
            $stmt->bind_param('sssi', $_POST['title'], $_POST['icon_key'], $_POST['body'], $id);
        } elseif ($newImage !== null) {
            $stmt = $conn->prepare("UPDATE about_sections SET title = ?, icon_key = ?, body = ?, image = ? WHERE section_id = ?");
            $stmt->bind_param('ssssi', $_POST['title'], $_POST['icon_key'], $_POST['body'], $newImage, $id);
        } else {
            $stmt = $conn->prepare("UPDATE about_sections SET title = ?, icon_key = ?, body = ? WHERE section_id = ?");
            $stmt->bind_param('sssi', $_POST['title'], $_POST['icon_key'], $_POST['body'], $id);
        }
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'delete_section') {
        $stmt = $conn->prepare("DELETE FROM about_sections WHERE section_id = ?");
        $id = (int) $_POST['id'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'move_section') {
        $id  = (int) $_POST['id'];
        $dir = $_POST['direction'] ?? '';
        $all = sitecontent_get_about_sections($conn);
        $index = null;
        foreach ($all as $i => $s) {
            if ((int) $s['section_id'] === $id) { $index = $i; break; }
        }
        if ($index !== null) {
            $swapWith = $dir === 'up' ? $index - 1 : $index + 1;
            if (isset($all[$swapWith])) {
                $a = $all[$index];
                $b = $all[$swapWith];
                $stmt = $conn->prepare("UPDATE about_sections SET sort_order = ? WHERE section_id = ?");
                $stmt->bind_param('ii', $b['sort_order'], $a['section_id']);
                $stmt->execute();
                $stmt->bind_param('ii', $a['sort_order'], $b['section_id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    header("Location: " . BASE_URL . "/admin/adminsitecontent.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | DATA FOR THE PAGE
 */
$heroPhotoSlots = [
    'home_hero_bg' => ['label' => 'Homepage — Hero Background Photo', 'hint' => 'The full-width background photo behind the "Welcome to KulToura" hero. Leave empty to keep the green gradient.'],
    'home_hero_1'  => ['label' => 'Homepage — Hero Photo 1', 'hint' => 'The top photo in the landing page collage.'],
    'home_hero_2'  => ['label' => 'Homepage — Hero Photo 2', 'hint' => 'The second, smaller photo in the landing page collage.'],
    'about_hero'   => ['label' => 'About Page — Hero Photo', 'hint' => 'The full-width background photo behind "About / Malvar".'],
];
foreach ($heroPhotoSlots as $key => &$slot) {
    $slot['image'] = sitecontent_get_photo($conn, $key);
}
unset($slot);

$aboutSections = sitecontent_get_about_sections($conn);
$iconSvgs   = sitecontent_icons();
$iconLabels = sitecontent_icon_labels();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Content – KULTOURA Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>

    <link rel="stylesheet" href="../assets/css/adminsitecontent.css">
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" class="active"><span class="nav-icon"><i data-lucide="image" class="lucide"></i></span> Site Content</a></li>
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
                <div class="section-label">Content Management</div>
                <h1>Site <em>Content</em></h1>
                <p>Control the photos on the homepage and the About page — including the History, Geography, and other story sections.</p>
            </div>
        </div>

        <!-- HERO PHOTOS -->
        <h2 class="content-h2">Hero Photos</h2>
        <p class="content-sub">These are fixed photo slots used directly on the public pages.</p>

        <div class="photo-card-grid animate">
            <?php foreach ($heroPhotoSlots as $key => $slot): ?>
            <div class="photo-card">
                <div class="photo-card-preview">
                    <?php if ($slot['image']): ?>
                        <img src="<?php echo htmlspecialchars($slot['image']); ?>" alt="">
                    <?php else: ?>
                        <div class="photo-card-empty"><i data-lucide="image-off" class="lucide"></i><span>No photo set — using an automatic fallback</span></div>
                    <?php endif; ?>
                </div>
                <div class="photo-card-body">
                    <div class="photo-card-label"><?php echo htmlspecialchars($slot['label']); ?></div>
                    <div class="photo-card-hint"><?php echo htmlspecialchars($slot['hint']); ?></div>
                    <div class="photo-card-actions">
                        <button type="button" class="btn-ghost btn-sm" onclick="document.getElementById('file-<?php echo $key; ?>').click()">
                            <i data-lucide="upload" class="lucide" style="width:.8rem;height:.8rem;"></i> <?php echo $slot['image'] ? 'Replace' : 'Upload'; ?> Photo
                        </button>
                        <?php if ($slot['image']): ?>
                        <form action="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="save_photo">
                            <input type="hidden" name="photo_key" value="<?php echo htmlspecialchars($key); ?>">
                            <input type="hidden" name="remove_image" value="1">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn-ghost btn-sm btn-danger-text" onclick="return confirm('Remove this photo and fall back to the automatic default?');">
                                <i data-lucide="trash-2" class="lucide" style="width:.8rem;height:.8rem;"></i> Remove
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <form action="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" method="POST" enctype="multipart/form-data" id="form-<?php echo $key; ?>">
                    <input type="hidden" name="action" value="save_photo">
                    <input type="hidden" name="photo_key" value="<?php echo htmlspecialchars($key); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="file" name="image" id="file-<?php echo $key; ?>" accept="image/png,image/jpeg,image/webp,image/gif" hidden onchange="document.getElementById('form-<?php echo $key; ?>').submit()">
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ABOUT SECTIONS -->
        <div class="content-h2-row">
            <div>
                <h2 class="content-h2">About Page Sections</h2>
                <p class="content-sub">The story rows on the About page (History, Geography, …). Add as many as you like.</p>
            </div>
            <button class="btn-primary" onclick="openAddSection()"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> Add Section</button>
        </div>

        <div class="section-list animate">
            <?php if (empty($aboutSections)): ?>
                <div class="table-empty">
                    <i data-lucide="layout-list" class="lucide"></i>
                    <div class="table-empty-title">No sections yet</div>
                    <div class="table-empty-sub">Click "Add Section" to create the first one.</div>
                </div>
            <?php else: ?>
                <?php foreach ($aboutSections as $i => $s): ?>
                <div class="section-row">
                    <div class="section-row-order">
                        <form action="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" method="POST">
                            <input type="hidden" name="action" value="move_section">
                            <input type="hidden" name="id" value="<?php echo (int) $s['section_id']; ?>">
                            <input type="hidden" name="direction" value="up">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="order-btn" <?php echo $i === 0 ? 'disabled' : ''; ?> aria-label="Move up"><i data-lucide="chevron-up" class="lucide"></i></button>
                        </form>
                        <form action="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" method="POST">
                            <input type="hidden" name="action" value="move_section">
                            <input type="hidden" name="id" value="<?php echo (int) $s['section_id']; ?>">
                            <input type="hidden" name="direction" value="down">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="order-btn" <?php echo $i === count($aboutSections) - 1 ? 'disabled' : ''; ?> aria-label="Move down"><i data-lucide="chevron-down" class="lucide"></i></button>
                        </form>
                    </div>

                    <div class="section-row-thumb">
                        <?php if (!empty($s['image'])): ?>
                            <img src="<?php echo htmlspecialchars($s['image']); ?>" alt="">
                        <?php else: ?>
                            <div class="section-row-thumb-empty"><i data-lucide="image-off" class="lucide"></i></div>
                        <?php endif; ?>
                    </div>

                    <div class="section-row-icon"><?php echo $iconSvgs[$s['icon_key']] ?? $iconSvgs['history']; ?></div>

                    <div class="section-row-body">
                        <div class="section-row-title"><?php echo htmlspecialchars($s['title']); ?></div>
                        <div class="section-row-preview"><?php echo htmlspecialchars(mb_strimwidth(str_replace(["\r", "\n"], ' ', $s['body']), 0, 140, '…')); ?></div>
                    </div>

                    <div class="section-row-actions">
                        <button type="button" class="tbl-btn edit" onclick="openEditSection(<?php echo htmlspecialchars(json_encode([
                            'id' => $s['section_id'],
                            'title' => $s['title'],
                            'icon_key' => $s['icon_key'],
                            'body' => $s['body'],
                            'hasImage' => !empty($s['image']),
                        ]), ENT_QUOTES); ?>)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                        <form action="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete the &quot;<?php echo htmlspecialchars(addslashes($s['title'])); ?>&quot; section? This can\'t be undone.');">
                            <input type="hidden" name="action" value="delete_section">
                            <input type="hidden" name="id" value="<?php echo (int) $s['section_id']; ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="tbl-btn delete"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ADD SECTION MODAL -->
<div class="modal-overlay" id="addSectionModal" onclick="closeModalOutside(event, 'addSectionModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('addSectionModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add About Section</div>
        <div class="modal-sub">Add a new story row to the About page — its own photo, icon, and text.</div>

        <form action="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_section">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label class="form-label">Section Title</label>
                <input class="form-input" type="text" name="title" placeholder="e.g. Local Cuisine" required>
            </div>

            <div class="form-group">
                <label class="form-label">Icon</label>
                <select class="form-input" name="icon_key">
                    <?php foreach ($iconLabels as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Photo</label>
                <input class="form-input" type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
            </div>

            <div class="form-group">
                <label class="form-label">Story Text</label>
                <textarea class="form-textarea" name="body" rows="6" placeholder="Write the section's text. Leave a blank line between paragraphs." required></textarea>
            </div>

            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Add Section</button>
        </form>
    </div>
</div>

<!-- EDIT SECTION MODAL -->
<div class="modal-overlay" id="editSectionModal" onclick="closeModalOutside(event, 'editSectionModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editSectionModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Section</div>
        <div class="modal-sub">Update the title, icon, photo, or text for this section.</div>

        <form action="<?php echo BASE_URL; ?>/admin/adminsitecontent.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_section">
            <input type="hidden" name="id" id="editSectionId">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label class="form-label">Section Title</label>
                <input class="form-input" type="text" name="title" id="editSectionTitle" required>
            </div>

            <div class="form-group">
                <label class="form-label">Icon</label>
                <select class="form-input" name="icon_key" id="editSectionIcon">
                    <?php foreach ($iconLabels as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Photo</label>
                <input class="form-input" type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
                <label class="form-checkbox-row" id="editSectionRemoveImageRow" hidden>
                    <input type="checkbox" name="remove_image" value="1"> Remove the current photo
                </label>
            </div>

            <div class="form-group">
                <label class="form-label">Story Text</label>
                <textarea class="form-textarea" name="body" id="editSectionBody" rows="6" required></textarea>
            </div>

            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">Save Changes</button>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/adminsitecontent.js"></script>
<script>lucide.createIcons(); initSidebarCollapse();</script>
</body>
</html>
