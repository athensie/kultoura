<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/csrf.php';

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 | Mirrors admineventandfiesta.php — anchors redirects regardless of
 | which folder this script is called from.
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

/*
 |--------------------------------------------------------------------
 | PEOPLE OF MALVAR — DB ACTIONS (add / edit / delete)
 |--------------------------------------------------------------------
 | Handled inline, same as admineventandfiesta.php — no separate
 | *_actions.php file. `people` table columns: person_id, fullname,
 | title, achievement, description, image, created_at.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fullname    = trim($_POST['fullname'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $achievement = trim($_POST['achievement'] ?? '');
        $desc        = trim($_POST['desc'] ?? '');

        if ($fullname === '') {
            $_SESSION['flash'] = 'Full name is required.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO people (fullname, title, achievement, description, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssss', $fullname, $title, $achievement, $desc);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '"' . $fullname . '" added.';
        }
        header('Location: adminpeople.php');
        exit;
    }

    if ($action === 'edit') {
        $id          = (int) ($_POST['person_id'] ?? 0);
        $fullname    = trim($_POST['fullname'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $achievement = trim($_POST['achievement'] ?? '');
        $desc        = trim($_POST['desc'] ?? '');

        if ($id > 0 && $fullname !== '') {
            $stmt = $conn->prepare(
                "UPDATE people
                 SET fullname = ?, title = ?, achievement = ?, description = ?
                 WHERE person_id = ?"
            );
            $stmt->bind_param('ssssi', $fullname, $title, $achievement, $desc, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '"' . $fullname . '" updated.';
        } else {
            $_SESSION['flash'] = 'Could not update — full name is required.';
        }
        header('Location: adminpeople.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['person_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM people WHERE person_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = 'Profile deleted.';
        }
        header('Location: adminpeople.php');
        exit;
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/*
 |--------------------------------------------------------------------
 | PEOPLE OF MALVAR — FETCH
 |--------------------------------------------------------------------
 */
$people = [];
if ($result = $conn->query("SELECT * FROM people ORDER BY fullname ASC")) {
    $people = $result->fetch_all(MYSQLI_ASSOC);
}

// Real per-place view counts (see config/analytics.php).
$peopleViews = analytics_item_views_bulk($conn, 'person');

$totalPeople = count($people);
$withTitle   = count(array_filter($people, fn($p) => trim((string) $p['title']) !== ''));
$addedThisMonth = count(array_filter($people, function ($p) {
    $ts = strtotime($p['created_at'] ?? '');
    return $ts && date('Y-m', $ts) === date('Y-m');
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>People of Malvar – KULTOURA Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>

    <link rel="stylesheet" href="../assets/css/adminpeople.css">
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpeople.php" class="active"><span class="nav-icon"><i data-lucide="users-round" class="lucide"></i></span> People of Malvar</a></li>
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
        <div class="page-header animate">
            <div>
                <div class="section-label">Content Management</div>
                <h1>People of <em>Malvar</em></h1>
                <p>Manage profiles of artists, leaders, farmers, and community pillars. Anything added here shows up on the public People of Malvar page.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary" onclick="openAddPerson()"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> New Profile</button>
            </div>
        </div>

        <!-- KPI ROW -->
        <div class="mini-kpi-row animate">
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $totalPeople); ?></div>
                <div class="mini-kpi-label">Total Profiles</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $withTitle); ?></div>
                <div class="mini-kpi-label">With Title Set</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $addedThisMonth); ?></div>
                <div class="mini-kpi-label">Added This Month</div>
            </div>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row animate">
            <input class="search-input" type="text" placeholder="Search people…" oninput="filterPeople(this.value)">
            <div class="view-toggle">
                <button type="button" class="view-btn" data-view="list" onclick="setView('list')" aria-label="List view"><i data-lucide="list" class="lucide"></i></button>
                <button type="button" class="view-btn" data-view="grid" onclick="setView('grid')" aria-label="Grid view"><i data-lucide="layout-grid" class="lucide"></i></button>
            </div>
        </div>

        <!-- PEOPLE TABLE -->
        <div class="data-table-wrap animate">
            <?php if (empty($people)): ?>
                <div class="chart-empty">
                    <i data-lucide="users-round" class="lucide"></i>
                    <div class="chart-empty-title">No profiles yet</div>
                    <div class="chart-empty-sub">Profiles you add will show up here, and on the public People of Malvar page.</div>
                </div>
            <?php else: ?>
                <table class="data-table" id="peopleTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Title</th>
                            <th>Achievement</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($people as $p): ?>
                            <tr data-id="<?php echo htmlspecialchars((string) $p['person_id']); ?>" data-name="<?php echo htmlspecialchars($p['fullname']); ?>">
                                <td><?php echo htmlspecialchars($p['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($p['title'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($p['achievement'] ?: '—'); ?></td>
                                <td><?php echo (int) ($peopleViews[(int) $p['person_id']] ?? 0); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="tbl-btn view" onclick="openViewPerson('<?php echo (int) $p['person_id']; ?>')"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                                        <button class="tbl-btn edit" onclick="openEditPerson('<?php echo (int) $p['person_id']; ?>')"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                        <button class="tbl-btn delete" onclick="confirmDelete('<?php echo (int) $p['person_id']; ?>', '<?php echo htmlspecialchars($p['fullname'], ENT_QUOTES); ?>')"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- GRID VIEW -->
        <div class="grid-view-panel" id="peopleGrid">
            <?php if (empty($people)): ?>
                <div class="chart-empty grid-empty">
                    <i data-lucide="users-round" class="lucide"></i>
                    <div class="chart-empty-title">No profiles yet</div>
                    <div class="chart-empty-sub">Profiles you add will show up here, and on the public People of Malvar page.</div>
                </div>
            <?php else: ?>
                <?php foreach ($people as $p): ?>
                    <div class="grid-card" data-id="<?php echo htmlspecialchars((string) $p['person_id']); ?>" data-name="<?php echo htmlspecialchars($p['fullname']); ?>">
                        <div class="grid-card-media">
                            <?php if (!empty($p['image'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
                            <?php else: ?>
                                <i data-lucide="user-round" class="lucide"></i>
                            <?php endif; ?>
                        </div>
                        <div class="grid-card-body">
                            <div class="grid-card-title"><?php echo htmlspecialchars($p['fullname']); ?></div>
                            <div class="grid-card-sub"><?php echo htmlspecialchars($p['title'] ?: '—'); ?></div>
                            <div class="grid-card-meta">
                                <span><?php echo htmlspecialchars($p['achievement'] ?: '—'); ?></span>
                                <span><i data-lucide="eye" class="lucide"></i> <?php echo (int) ($peopleViews[(int) $p['person_id']] ?? 0); ?></span>
                            </div>
                        </div>
                        <div class="grid-card-actions">
                            <button class="tbl-btn view" onclick="openViewPerson('<?php echo (int) $p['person_id']; ?>')"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                            <button class="tbl-btn edit" onclick="openEditPerson('<?php echo (int) $p['person_id']; ?>')"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                            <button class="tbl-btn delete" onclick="confirmDelete('<?php echo (int) $p['person_id']; ?>', '<?php echo htmlspecialchars($p['fullname'], ENT_QUOTES); ?>')"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pagination" id="peoplePagination"></div>

    </div>
</div>

<!-- ADD PERSON MODAL -->
<div class="modal-overlay" id="addPersonModal" onclick="closeModalOutside(event, 'addPersonModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('addPersonModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add New Profile</div>
        <div class="modal-sub">Fill in the details to add a person of Malvar.</div>
        <form id="addPersonForm" method="POST" action="adminpeople.php">
            <input type="hidden" name="action" value="add">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input class="form-input" type="text" name="fullname" placeholder="e.g. Juan Dela Cruz" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input class="form-input" type="text" name="title" placeholder="e.g. Local Leader, Artist, Farmer">
                </div>
                <div class="form-group">
                    <label class="form-label">Achievement</label>
                    <input class="form-input" type="text" name="achievement" placeholder="e.g. Barangay Captain since 2018">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="desc" placeholder="Tell their story…"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Add Profile</button>
                <button type="button" class="btn-ghost" onclick="closeModal('addPersonModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT PERSON MODAL -->
<div class="modal-overlay" id="editPersonModal" onclick="closeModalOutside(event, 'editPersonModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editPersonModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Profile</div>
        <div class="modal-sub" id="editPersonName">Editing: —</div>
        <form id="editPersonForm" method="POST" action="adminpeople.php">
            <input type="hidden" name="action" value="edit">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="person_id" id="editPersonId">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input class="form-input" type="text" name="fullname" id="editFullnameInput" placeholder="Full name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input class="form-input" type="text" name="title" id="editTitleInput" placeholder="Title">
                </div>
                <div class="form-group">
                    <label class="form-label">Achievement</label>
                    <input class="form-input" type="text" name="achievement" id="editAchievementInput" placeholder="Achievement">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="desc" id="editDescInput" placeholder="Tell their story…"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editPersonModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW PERSON MODAL -->
<div class="modal-overlay" id="viewPersonModal" onclick="closeModalOutside(event, 'viewPersonModal')">
    <div class="modal-card" style="max-width:480px;">
        <button class="modal-close" onclick="closeModal('viewPersonModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title" style="text-align:center;" id="vpName">—</div>
        <div style="display:flex;justify-content:center;gap:10px;margin:10px 0 18px;flex-wrap:wrap;">
            <span style="font-size:.75rem;color:rgba(245,237,216,.4);align-self:center;" id="vpTitle">—</span>
            <span style="font-size:.75rem;color:rgba(245,237,216,.4);align-self:center;" id="vpAchievement">—</span>
        </div>
        <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:16px;margin-bottom:18px;">
            <div style="font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:rgba(245,237,216,.3);margin-bottom:8px;">Description</div>
            <div style="font-size:.85rem;color:rgba(245,237,216,.65);line-height:1.6;" id="vpDesc">—</div>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn-primary" style="flex:1;" id="vpEditBtn"><i data-lucide="pencil" class="lucide" style="width:.85rem;height:.85rem;"></i> Edit Profile</button>
            <button class="btn-ghost" onclick="closeModal('viewPersonModal')">Close</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal" onclick="closeModalOutside(event, 'deleteModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div class="modal-title" id="deleteTitle">Delete Item?</div>
        <div class="modal-sub" id="deleteDesc">This action cannot be undone.</div>
        <form id="deleteForm" method="POST" action="adminpeople.php">
            <input type="hidden" name="action" value="delete">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="person_id" id="deletePersonId">
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="tbl-btn delete" style="flex:1; padding:12px;">Yes, Delete</button>
                <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('deleteModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
    const peopleData = <?php echo json_encode($people); ?>;
</script>
<script src="../assets/js/admin-viewtoggle.js"></script>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/adminpeople.js"></script>
<script>initViewToggle('people', '.data-table-wrap', '#peopleGrid'); initSidebarCollapse();</script>
<script>
    (function initLucide() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        } else {
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        }
    })();
    <?php if ($flash): ?>
    document.addEventListener('DOMContentLoaded', function () {
        showToast(<?php echo json_encode($flash); ?>);
    });
    <?php endif; ?>
</script>

</body>
</html>