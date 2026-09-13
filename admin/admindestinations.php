<?php
session_start();
include '../config/dbmain.php';
include '../config/analytics.php';

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 | Keep this in sync with admindashboard.php / login.php / auth.php.
 */
define('BASE_URL', '/kultoura');

/*
 |--------------------------------------------------------------------
 | ACCESS CONTROL
 |--------------------------------------------------------------------
 | Same guard as admindashboard.php: only admin / super admin may view.
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
 | ONE SHARED TABLE FOR ALL THREE PUBLIC PAGES
 |--------------------------------------------------------------------
 | industry.php, nature.php, and resort.php all read from this same
 | `destination` table, filtered by `category`. Matches the actual
 | columns in kultoura_db.destination:
 |
 |   destination_id    INT AUTO_INCREMENT PRIMARY KEY
 |   destination_name  VARCHAR(100)
 |   category           ENUM('nature','industry','resort')
 |   status             ENUM('active','pending','inactive')
 |   views              INT
 |   favorited          TINYINT(1)
 |   address            VARCHAR(255)
 |   description         TEXT
 |   image               VARCHAR(255)
 |   opening_hours       VARCHAR(100)
 |   google_maps         VARCHAR(255)
 |   created_at          TIMESTAMP
 |
 | NOTE: this assumes dbmain.php gives you a mysqli connection in $conn.
 | If your dbmain.php exposes a different variable name (e.g. $pdo),
 | swap it in below.
 */

/*
 |--------------------------------------------------------------------
 | IMAGE UPLOAD HELPER
 |--------------------------------------------------------------------
 | Saves an uploaded file into /assets/uploads/destinations/ and
 | returns the relative path to store in the database, or null if
 | no file was uploaded (or the upload failed).
 */
function handleDestinationImageUpload(): ?string
{
    if (empty($_FILES['image']['name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/destinations/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('dest_', true) . '.' . $ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
        // Path stored in the DB, relative to the site root, for use in <img src="...">
        return BASE_URL . '/assets/uploads/destinations/' . $filename;
    }

    return null;
}

/*
 |--------------------------------------------------------------------
 | HANDLE ADD / EDIT / DELETE — right here, no separate actions file
 |--------------------------------------------------------------------
 | The three modals below all POST back to this same page. We process
 | the request before any HTML is echoed, then redirect (POST/Redirect/GET)
 | so refreshing the page doesn't resubmit the form.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create') {
        $imagePath = handleDestinationImageUpload();

        $googleMaps = $_POST['google_maps'] ?? '';

        $stmt = $conn->prepare(
            "INSERT INTO destination (destination_name, address, category, status, description, image, google_maps)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssssss',
            $_POST['name'],
            $_POST['location'],
            $_POST['category'],
            $_POST['status'],
            $_POST['description'],
            $imagePath,
            $googleMaps
        );
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'update') {
        $newImagePath = handleDestinationImageUpload();
        $googleMaps   = $_POST['google_maps'] ?? '';

        if ($newImagePath !== null) {
            // A new image was uploaded — replace it.
            $stmt = $conn->prepare(
                "UPDATE destination
                 SET destination_name = ?, address = ?, category = ?, status = ?, description = ?, image = ?, google_maps = ?
                 WHERE destination_id = ?"
            );
            $stmt->bind_param(
                'sssssssi',
                $_POST['name'],
                $_POST['location'],
                $_POST['category'],
                $_POST['status'],
                $_POST['description'],
                $newImagePath,
                $googleMaps,
                $_POST['id']
            );
        } else {
            // No new file chosen — leave the existing image untouched.
            $stmt = $conn->prepare(
                "UPDATE destination
                 SET destination_name = ?, address = ?, category = ?, status = ?, description = ?, google_maps = ?
                 WHERE destination_id = ?"
            );
            $stmt->bind_param(
                'ssssssi',
                $_POST['name'],
                $_POST['location'],
                $_POST['category'],
                $_POST['status'],
                $_POST['description'],
                $googleMaps,
                $_POST['id']
            );
        }
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM destination WHERE destination_id = ?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: " . BASE_URL . "/admin/admindestinations.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | DESTINATIONS DATA (for the table below)
 |--------------------------------------------------------------------
 */
$destinations = [];
$result = $conn->query("SELECT * FROM destination ORDER BY created_at DESC");
if ($result) {
    $destinations = $result->fetch_all(MYSQLI_ASSOC);
}

// Real per-place view counts (see config/analytics.php) — destination_id
// is unique across the whole table regardless of category, so merging
// the three category buckets by id is safe.
$destinationViews = array_merge(
    analytics_item_views_bulk($conn, 'nature'),
    analytics_item_views_bulk($conn, 'resort'),
    analytics_item_views_bulk($conn, 'industry')
);

$categoryLabels = [
    'nature'   => 'Nature',
    'industry' => 'Industry Zone',
    'resort'   => 'Resort',
];

$totalCount    = count($destinations);
$activeCount   = count(array_filter($destinations, fn($d) => $d['status'] === 'active'));
$pendingCount  = count(array_filter($destinations, fn($d) => $d['status'] === 'pending'));
$inactiveCount = count(array_filter($destinations, fn($d) => $d['status'] === 'inactive'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destinations – KULTOURA Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>

    <link rel="stylesheet" href="../assets/css/admindestinations.css">
    <link rel="stylesheet" href="../assets/css/admin-viewtoggle.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">

    <!-- Free address picker: Leaflet (map) + OpenStreetMap tiles + Nominatim
         search — no API key, no billing account required. -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .kt-map-field { position: relative; }
        .kt-map-status { font-size: .7rem; color: rgba(245,237,216,.4); margin-top: 4px; }
        .kt-suggest-box {
            display: none;
            position: absolute;
            z-index: 20;
            left: 0; right: 0; top: 100%;
            background: #1e1d1a;
            border: 1px solid rgba(245,237,216,.12);
            border-radius: 8px;
            margin-top: 4px;
            max-height: 220px;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,.4);
        }
        .kt-suggest-item {
            padding: 10px 12px;
            font-size: .8rem;
            color: rgba(245,237,216,.85);
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,.05);
        }
        .kt-suggest-item:last-child { border-bottom: none; }
        .kt-suggest-item:hover { background: rgba(255,255,255,.06); }
        .kt-suggest-empty { padding: 10px 12px; font-size: .75rem; color: rgba(245,237,216,.35); }
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
        <li><a href="<?php echo BASE_URL; ?>/admin/admindestinations.php" class="active"><span class="nav-icon"><i data-lucide="map-pin" class="lucide"></i></span> Destinations</a></li>
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
                <div class="section-label">Content Management</div>
                <h1>Destinations <em>Listings</em></h1>
                <p>Add, review, and manage every destination listed on KulToura.</p>
            </div>
            <div class="header-actions">
                <?php if (!empty($destinations)): ?>
                    <button class="btn-export-excel" id="exportExcelBtn" onclick="exportDestinationsExcel()">
                        <i data-lucide="file-spreadsheet" class="lucide" style="width:.9rem;height:.9rem;"></i>
                        <span>Export as Excel</span>
                    </button>
                <?php endif; ?>
                <button class="btn-primary" onclick="openAddDestination()"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> New Destination</button>
            </div>
        </div>

        <!-- MINI KPI ROW -->
        <div class="mini-kpi-row animate">
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $totalCount; ?></div>
                <div class="mini-kpi-label">Total Destinations</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $activeCount; ?></div>
                <div class="mini-kpi-label">Active</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $pendingCount; ?></div>
                <div class="mini-kpi-label">Pending Review</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $inactiveCount; ?></div>
                <div class="mini-kpi-label">Inactive</div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-row">
            <input class="search-input" type="text" id="destSearch" placeholder="Search destinations…" oninput="filterDestinations(this.value)">
            <select class="filter-select" id="destCategoryFilter" onchange="filterByCategory(this.value)">
                <option value="all">All Categories</option>
                <option value="nature">Nature</option>
                <option value="industry">Industry Zone</option>
                <option value="resort">Resort</option>
            </select>
            <select class="filter-select" id="destStatusFilter" onchange="filterByStatus(this.value)">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="inactive">Inactive</option>
            </select>
            <div class="view-toggle">
                <button type="button" class="view-btn" data-view="list" onclick="setView('list')" aria-label="List view"><i data-lucide="list" class="lucide"></i></button>
                <button type="button" class="view-btn" data-view="grid" onclick="setView('grid')" aria-label="Grid view"><i data-lucide="layout-grid" class="lucide"></i></button>
            </div>
        </div>

        <!-- TABLE -->
        <div class="data-table-wrap">
            <table class="data-table" id="destinationsTable">
                <thead>
                    <tr>
                        <th>Destination</th>
                        <th>Category</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($destinations)): ?>
                    <tr id="destEmptyRow">
                        <td colspan="6">
                            <div class="table-empty">
                                <i data-lucide="map-pin" class="lucide"></i>
                                <div class="table-empty-title">No destinations yet</div>
                                <div class="table-empty-sub">Click "New Destination" to add the first listing.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($destinations as $d): ?>
                        <tr data-category="<?php echo htmlspecialchars($d['category'] ?? ''); ?>">
                            <td>
                                <?php if (!empty($d['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($d['image']); ?>" alt="" style="width:28px;height:28px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px;">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($d['destination_name']); ?>
                            </td>
                            <td><span class="category-badge <?php echo htmlspecialchars($d['category'] ?? ''); ?>"><?php echo htmlspecialchars($categoryLabels[$d['category']] ?? ucfirst($d['category'] ?? '—')); ?></span></td>
                            <td><?php echo htmlspecialchars($d['address']); ?></td>
                            <td><span class="status <?php echo htmlspecialchars($d['status']); ?>"><?php echo htmlspecialchars(ucfirst($d['status'])); ?></span></td>
                            <td><?php echo (int) ($destinationViews[(int) $d['destination_id']] ?? 0); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button class="tbl-btn view"
                                        data-id="<?php echo (int) $d['destination_id']; ?>"
                                        data-image="<?php echo htmlspecialchars($d['image'] ?? ''); ?>"
                                        data-name="<?php echo htmlspecialchars($d['destination_name']); ?>"
                                        data-location="<?php echo htmlspecialchars($d['address']); ?>"
                                        data-category="<?php echo htmlspecialchars($d['category'] ?? ''); ?>"
                                        data-views="<?php echo htmlspecialchars((string) ($d['views'] ?? 0)); ?>"
                                        data-status="<?php echo htmlspecialchars($d['status']); ?>"
                                        data-desc="<?php echo htmlspecialchars($d['description'] ?? ''); ?>"
                                        data-google-maps="<?php echo htmlspecialchars($d['google_maps'] ?? ''); ?>"
                                        onclick="openViewDestination(this)"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                                    <button class="tbl-btn edit"
                                        data-id="<?php echo (int) $d['destination_id']; ?>"
                                        data-image="<?php echo htmlspecialchars($d['image'] ?? ''); ?>"
                                        data-name="<?php echo htmlspecialchars($d['destination_name']); ?>"
                                        data-location="<?php echo htmlspecialchars($d['address']); ?>"
                                        data-category="<?php echo htmlspecialchars($d['category'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($d['status']); ?>"
                                        data-desc="<?php echo htmlspecialchars($d['description'] ?? ''); ?>"
                                        data-google-maps="<?php echo htmlspecialchars($d['google_maps'] ?? ''); ?>"
                                        onclick="openEditDestination(this)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                    <button class="tbl-btn delete"
                                        data-id="<?php echo (int) $d['destination_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($d['destination_name']); ?>"
                                        onclick="confirmDeleteDestination(this)"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- GRID VIEW -->
        <div class="grid-view-panel" id="destinationsGrid">
            <?php if (empty($destinations)): ?>
                <div class="table-empty grid-empty">
                    <i data-lucide="map-pin" class="lucide"></i>
                    <div class="table-empty-title">No destinations yet</div>
                    <div class="table-empty-sub">Click "New Destination" to add the first listing.</div>
                </div>
            <?php else: ?>
                <?php foreach ($destinations as $d): ?>
                    <div class="grid-card" data-category="<?php echo htmlspecialchars($d['category'] ?? ''); ?>">
                        <div class="grid-card-media">
                            <?php if (!empty($d['image'])): ?>
                                <img src="<?php echo htmlspecialchars($d['image']); ?>" alt="">
                            <?php else: ?>
                                <i data-lucide="image" class="lucide"></i>
                            <?php endif; ?>
                            <span class="category-badge grid-card-badge <?php echo htmlspecialchars($d['category'] ?? ''); ?>"><?php echo htmlspecialchars($categoryLabels[$d['category']] ?? ucfirst($d['category'] ?? '—')); ?></span>
                        </div>
                        <div class="grid-card-body">
                            <div class="grid-card-title"><?php echo htmlspecialchars($d['destination_name']); ?></div>
                            <div class="grid-card-sub"><?php echo htmlspecialchars($d['address']); ?></div>
                            <div class="grid-card-meta">
                                <span class="status <?php echo htmlspecialchars($d['status']); ?>"><?php echo htmlspecialchars(ucfirst($d['status'])); ?></span>
                                <span><i data-lucide="eye" class="lucide"></i> <?php echo (int) ($destinationViews[(int) $d['destination_id']] ?? 0); ?></span>
                            </div>
                        </div>
                        <div class="grid-card-actions">
                            <button class="tbl-btn view"
                                data-id="<?php echo (int) $d['destination_id']; ?>"
                                data-image="<?php echo htmlspecialchars($d['image'] ?? ''); ?>"
                                data-name="<?php echo htmlspecialchars($d['destination_name']); ?>"
                                data-location="<?php echo htmlspecialchars($d['address']); ?>"
                                data-category="<?php echo htmlspecialchars($d['category'] ?? ''); ?>"
                                data-views="<?php echo htmlspecialchars((string) ($d['views'] ?? 0)); ?>"
                                data-status="<?php echo htmlspecialchars($d['status']); ?>"
                                data-desc="<?php echo htmlspecialchars($d['description'] ?? ''); ?>"
                                data-google-maps="<?php echo htmlspecialchars($d['google_maps'] ?? ''); ?>"
                                onclick="openViewDestination(this)"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                            <button class="tbl-btn edit"
                                data-id="<?php echo (int) $d['destination_id']; ?>"
                                data-image="<?php echo htmlspecialchars($d['image'] ?? ''); ?>"
                                data-name="<?php echo htmlspecialchars($d['destination_name']); ?>"
                                data-location="<?php echo htmlspecialchars($d['address']); ?>"
                                data-category="<?php echo htmlspecialchars($d['category'] ?? ''); ?>"
                                data-status="<?php echo htmlspecialchars($d['status']); ?>"
                                data-desc="<?php echo htmlspecialchars($d['description'] ?? ''); ?>"
                                data-google-maps="<?php echo htmlspecialchars($d['google_maps'] ?? ''); ?>"
                                onclick="openEditDestination(this)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                            <button class="tbl-btn delete"
                                data-id="<?php echo (int) $d['destination_id']; ?>"
                                data-name="<?php echo htmlspecialchars($d['destination_name']); ?>"
                                onclick="confirmDeleteDestination(this)"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pagination" id="destinationsPagination"></div>

    </div>
</div>

<!-- ADD DESTINATION MODAL -->
<div class="modal-overlay" id="addDestinationModal" onclick="closeModalOutside(event, 'addDestinationModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('addDestinationModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add New Destination</div>
        <div class="modal-sub">Fill in the details to create a new destination listing.</div>

        <form id="addDestinationForm" action="<?php echo BASE_URL; ?>/admin/admindestinations.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Destination Name</label>
                <input class="form-input" type="text" name="name" placeholder="e.g. Malvar Rice Terraces" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="filter-select" name="category" style="width:100%; padding:11px 14px; border-radius:8px;" required>
                        <option value="" disabled selected>Select a category…</option>
                        <option value="nature">Nature</option>
                        <option value="industry">Industry Zone</option>
                        <option value="resort">Resort</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <div class="kt-map-field">
                    <input class="form-input" type="text" name="location" id="addLocationInput" placeholder="Start typing an address and pick it from the list…" autocomplete="off" required>
                </div>
                <input type="hidden" name="google_maps" id="addGoogleMaps" value="">
                <div id="addMapPicker" style="height:220px;border-radius:8px;margin-top:8px;background:rgba(255,255,255,.04);overflow:hidden;"></div>
                <div class="kt-map-status" id="addMapStatus">Search an address, or click/drag the pin to set the exact spot.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="filter-select" name="status" style="width:100%; padding:11px 14px; border-radius:8px;">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" placeholder="Describe this destination…"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Image</label>
                <input class="form-input" type="file" name="image" accept="image/png, image/jpeg, image/webp, image/gif">
            </div>

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Add Destination</button>
                <button type="button" class="btn-ghost" onclick="closeModal('addDestinationModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT DESTINATION MODAL -->
<div class="modal-overlay" id="editDestinationModal" onclick="closeModalOutside(event, 'editDestinationModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editDestinationModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Destination</div>
        <div class="modal-sub" id="editDestinationName">Editing: —</div>

        <form id="editDestinationForm" action="<?php echo BASE_URL; ?>/admin/admindestinations.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editDestinationId" value="">

            <div class="form-group">
                <label class="form-label">Destination Name</label>
                <input class="form-input" type="text" name="name" id="editDestinationNameInput" placeholder="Destination name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="filter-select" name="category" id="editDestinationCategoryInput" style="width:100%; padding:11px 14px; border-radius:8px;" required>
                        <option value="nature">Nature</option>
                        <option value="industry">Industry Zone</option>
                        <option value="resort">Resort</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <div class="kt-map-field">
                    <input class="form-input" type="text" name="location" id="editDestinationLocationInput" placeholder="Start typing an address and pick it from the list…" autocomplete="off" required>
                </div>
                <input type="hidden" name="google_maps" id="editGoogleMaps" value="">
                <div id="editMapPicker" style="height:220px;border-radius:8px;margin-top:8px;background:rgba(255,255,255,.04);overflow:hidden;"></div>
                <div class="kt-map-status" id="editMapStatus">Search an address, or click/drag the pin to set the exact spot.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="filter-select" name="status" id="editDestinationStatusInput" style="width:100%; padding:11px 14px; border-radius:8px;">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" id="editDestinationDescInput" placeholder="Description…"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Image</label>
                <img id="editDestinationCurrentImage" src="" alt="" style="display:none; width:60px; height:60px; object-fit:cover; border-radius:8px; margin-bottom:8px;">
                <input class="form-input" type="file" name="image" accept="image/png, image/jpeg, image/webp, image/gif">
                <div style="font-size:.7rem;color:rgba(245,237,216,.4);margin-top:4px;">Leave empty to keep the current image.</div>
            </div>

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editDestinationModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW DESTINATION MODAL -->
<div class="modal-overlay" id="viewDestinationModal" onclick="closeModalOutside(event,'viewDestinationModal')">
    <div class="modal-card" style="max-width:520px;">
        <button class="modal-close" onclick="closeModal('viewDestinationModal')"><i data-lucide="x" class="lucide"></i></button>
        <img id="vdImage" src="" alt="" style="display:none; width:100%; max-height:180px; object-fit:cover; border-radius:10px; margin-bottom:12px;">
        <div id="vdIcon" style="font-size:2rem;text-align:center;margin-bottom:8px;">📍</div>
        <div class="modal-title" style="text-align:center;" id="vdName">—</div>
        <div style="display:flex;justify-content:center;gap:10px;margin:10px 0 18px;flex-wrap:wrap;">
            <span class="status active" id="vdStatus">Active</span>
            <span class="category-badge nature" id="vdCategory">—</span>
            <span style="font-size:.75rem;color:rgba(245,237,216,.4);align-self:center;" id="vdLocation">—</span>
        </div>
        <div style="text-align:center;margin:-6px 0 16px;">
            <a href="#" target="_blank" id="vdMapLink" class="btn-ghost" style="display:none;">📍 Open in Google Maps</a>
        </div>
        <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:16px;margin-bottom:16px;">
            <div style="font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:rgba(245,237,216,.3);margin-bottom:8px;">Description</div>
            <div style="font-size:.85rem;color:rgba(245,237,216,.65);line-height:1.6;" id="vdDesc">—</div>
        </div>
        <div style="display:flex;gap:10px;margin-bottom:18px;">
            <div style="flex:1;background:rgba(255,255,255,.04);border-radius:8px;padding:12px;text-align:center;">
                <div style="font-family:'Bebas Neue',sans-serif;font-size:1.5rem;color:var(--gold);" id="vdViews">—</div>
                <div style="font-size:.6rem;color:rgba(245,237,216,.3);text-transform:uppercase;letter-spacing:1px;">Views</div>
            </div>
            <div style="flex:1;background:rgba(255,255,255,.04);border-radius:8px;padding:12px;text-align:center;">
                <div style="font-family:'Bebas Neue',sans-serif;font-size:1.5rem;color:var(--gold);" id="vdReviews">—</div>
                <div style="font-size:.6rem;color:rgba(245,237,216,.3);text-transform:uppercase;letter-spacing:1px;">Reviews</div>
            </div>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn-primary" style="flex:1;" id="vdEditBtn" onclick="closeModal('viewDestinationModal')"><i data-lucide="pencil" class="lucide" style="width:.85rem;height:.85rem;"></i> Edit Destination</button>
            <button class="btn-ghost" onclick="closeModal('viewDestinationModal')">Close</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteDestinationModal" onclick="closeModalOutside(event, 'deleteDestinationModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div style="font-size:3rem; margin-bottom:12px;">🗑️</div>
        <div class="modal-title" id="deleteDestinationTitle">Delete destination?</div>
        <div class="modal-sub">This action cannot be undone.</div>
        <form id="deleteDestinationForm" action="<?php echo BASE_URL; ?>/admin/admindestinations.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteDestinationId" value="">
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="tbl-btn delete" style="flex:1; padding:12px;">Yes, Delete</button>
                <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('deleteDestinationModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script src="../assets/js/admin-viewtoggle.js"></script>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admindestinations.js"></script>
<script>initViewToggle('destinations', '.data-table-wrap', '#destinationsGrid'); initSidebarCollapse();</script>

</body>
</html>