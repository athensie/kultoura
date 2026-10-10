<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once '../config/dbmain.php';
require_once '../config/analytics.php';
require_once '../config/csrf.php';
require_once '../config/admin_requests.php';

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
$pendingRequestCount = kt_requests_pending_count($conn);

/*
 |--------------------------------------------------------------------
 | FOOD & DINING DATA — backed by the `products` and `restaurants` tables
 |--------------------------------------------------------------------
 | Every listing has a TYPE ("product" or "restaurant"), which decides
 | which table it's saved to and which fields apply:
 |
 |   products:    product_id, product_name, category, description,
 |                price, location, image, created_at
 |   restaurants: restaurant_id, restaurant_name, category, description,
 |                address, contact_number, opening_hours, image,
 |                google_map, created_at
 |
 | There is no approval workflow (no `status` column) and no map-based
 | lat/lng picker — listings go live as soon as they're added, and
 | location is a plain text field (or a pasted Google Maps link for
 | restaurants).
 */
// Both listing types use a fixed category dropdown in the Add/Edit forms
// (see assets/js/adminfoodanddining.js's toggleCategoryField) — these are
// the only choices offered for a new entry.
$categoriesByType = [
    'product'    => ['Local Food', 'Local Products', 'Crafts'],
    'restaurant' => ['Restaurant', 'Karinderya', 'Cafe', 'Fast Food', 'Bulalo & Lomi House', 'Bakery', 'Milk Tea & Beverage Shop', 'Dessert Shop'],
];

// Which table/columns a given "type" writes to.
$tableMap = [
    'product'    => ['table' => 'products',    'idCol' => 'product_id',    'nameCol' => 'product_name'],
    'restaurant' => ['table' => 'restaurants', 'idCol' => 'restaurant_id', 'nameCol' => 'restaurant_name'],
];

$flashMessage = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

/*
 |--------------------------------------------------------------------
 | IMAGE UPLOAD
 |--------------------------------------------------------------------
 | Saves the uploaded file into /assets/uploads/food/ and returns the
 | path to store in the DB (as an absolute site path, so it resolves
 | correctly whether it's displayed from /admin/, /pages/, or
 | /pages/tourism/). Returns null if no file was uploaded (so the
 | caller can fall back to keeping the existing image on edit).
 */
function kt_handle_image_upload(string $fieldName = 'image_file'): ?string
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null; // silently skip on upload error — existing/blank image is kept
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $originalName = $_FILES[$fieldName]['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }

    // Confirm it's actually an image, not just a renamed file.
    if (@getimagesize($_FILES[$fieldName]['tmp_name']) === false) {
        return null;
    }

    // Physical uploads folder is /kultoura/assets/uploads/food/ — confirmed
    // from the actual project structure. restaurants.php's <img> src was the
    // thing pointing at the wrong place (fixed separately), not this path.
    $uploadDir = __DIR__ . '/../assets/uploads/food/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeName = preg_replace('/[^a-z0-9_-]/i', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $filename = uniqid('food_', true) . '_' . $safeName . '.' . $ext;

    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadDir . $filename)) {
        // Absolute site path, matching admindestinations.php's convention —
        // this was returning a bare filename before, which the DB's one
        // existing image row (an absolute path) didn't match, and broke
        // any <img src> that used the stored value directly.
        return BASE_URL . '/assets/uploads/food/' . $filename;
    }

    return null;
}

/*
 |--------------------------------------------------------------------
 | WRITE — Add / Edit / Delete
 |--------------------------------------------------------------------
 | Classic POST -> redirect -> GET so a page refresh never re-submits
 | the form. Every write is routed to `products` or `restaurants`
 | based on the submitted listing_type.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    csrf_verify();
    $formAction  = $_POST['form_action'];
    $listingType = $_POST['listing_type'] ?? '';
    $map         = $tableMap[$listingType] ?? null;
    $entityType  = $listingType === 'product' ? 'product' : 'restaurant';

    if (!$map) {
        $_SESSION['admin_flash'] = 'Please choose a listing type (Product or Restaurant).';
        header("Location: adminfoodanddining.php");
        exit;
    }

    $name     = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $entityAction = $formAction === 'add_listing' ? 'create' : ($formAction === 'edit_listing' ? 'update' : 'archive');
    $id = ($formAction === 'edit_listing' || $formAction === 'delete_listing') ? (int) ($_POST['id'] ?? 0) : null;

    if ($entityAction !== 'archive' && ($name === '' || $category === '')) {
        $_SESSION['admin_flash'] = 'Please enter a name and a category.';
        header("Location: adminfoodanddining.php");
        exit;
    }

    if ($entityAction === 'archive') {
        $data = [];
        // The delete form only posts the id/type — look the name up for
        // the flash message and (if this becomes a request) its label.
        $nameCol = $map['nameCol'];
        $stmt = $conn->prepare("SELECT `$nameCol` AS name FROM `{$map['table']}` WHERE `{$map['idCol']}` = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $name = $row['name'] ?? ('Listing #' . $id);
    } elseif ($listingType === 'product') {
        $image = kt_handle_image_upload() ?? ($entityAction === 'update' ? trim($_POST['existing_image'] ?? '') : '');
        $data = [
            'product_name' => $name,
            'category'     => $category,
            'description'  => trim($_POST['desc'] ?? ''),
            'price'        => $_POST['price'] ?? '',
            'location'     => trim($_POST['location'] ?? ''),
            'latitude'     => $_POST['latitude'] ?? '',
            'longitude'    => $_POST['longitude'] ?? '',
            'image'        => $image,
        ];
    } else {
        $image = kt_handle_image_upload() ?? ($entityAction === 'update' ? trim($_POST['existing_image'] ?? '') : '');
        $data = [
            'restaurant_name' => $name,
            'category'        => $category,
            'description'     => trim($_POST['desc'] ?? ''),
            'address'         => trim($_POST['address'] ?? ''),
            'latitude'        => $_POST['latitude'] ?? '',
            'longitude'       => $_POST['longitude'] ?? '',
            'contact_number'  => trim($_POST['contact_number'] ?? ''),
            'opening_hours'   => trim($_POST['opening_hours'] ?? ''),
            'google_map'      => trim($_POST['google_map'] ?? ''),
            'image'           => $image,
        ];
    }

    if ($isSuperAdmin) {
        kt_apply_entity_change($conn, $entityType, $entityAction, $id, $data);
        $_SESSION['admin_flash'] = $entityAction === 'archive' ? 'Listing archived.' : ('"' . $name . '" was ' . ($entityAction === 'create' ? 'added' : 'updated') . '.');
    } else {
        $label = $name !== '' ? $name : (ucfirst($listingType) . ' #' . $id);
        kt_requests_create($conn, $entityType, $entityAction, $id, $data, $label, (int) $_SESSION['user_id'], $adminName);
        $_SESSION['admin_flash'] = 'Your request to ' . ($entityAction === 'archive' ? 'archive' : $entityAction) . ' "' . $label . '" was submitted for Super Admin approval.';
    }

    header("Location: adminfoodanddining.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | READ — union products + restaurants into one table view
 |--------------------------------------------------------------------
 | Columns that don't exist on one side of the union (e.g. price on
 | restaurants, contact_number on products) come through as NULL.
 */
$foodListings = [];
$sql = "
    SELECT product_id AS id, product_name AS name, category, description AS `desc`,
           price, location AS address, latitude, longitude, NULL AS contact_number, NULL AS opening_hours,
           NULL AS google_map, image, created_at, 'product' AS type
    FROM products
    UNION ALL
    SELECT restaurant_id AS id, restaurant_name AS name, category, description AS `desc`,
           NULL AS price, address, latitude, longitude, contact_number, opening_hours,
           google_map, image, created_at, 'restaurant' AS type
    FROM restaurants
    ORDER BY created_at DESC
";
if ($result = $conn->query($sql)) {
    $foodListings = $result->fetch_all(MYSQLI_ASSOC);
}

// Real per-place view counts (see config/analytics.php). product_id and
// restaurant_id can collide (they're PKs on different tables), so these
// stay as two separate buckets keyed by `type` — never merged.
$foodViewsByType = [
    'product'    => analytics_item_views_bulk($conn, 'product'),
    'restaurant' => analytics_item_views_bulk($conn, 'restaurant'),
];

$totalListings  = count($foodListings);
$productCount   = count(array_filter($foodListings, fn($l) => $l['type'] === 'product'));
$restaurantCount = count(array_filter($foodListings, fn($l) => $l['type'] === 'restaurant'));
$addedThisMonth = count(array_filter($foodListings, fn($l) => date('Y-m', strtotime($l['created_at'])) === date('Y-m')));

// Flat list of every category actually in use (for the table's filter dropdown) —
// pulled from the real data, since older rows may still carry a category
// outside the current preset lists below.
$allCategories = array_values(array_unique(array_filter(array_column($foodListings, 'category'))));
sort($allCategories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food &amp; Dining – KULTOURA Admin</title>

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

    <!-- Leaflet + OpenStreetMap: free, no API key required, used for the map-based location picker -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <link rel="stylesheet" href="../assets/css/adminfoodanddining.css">
    <link rel="stylesheet" href="../assets/css/admin-theme-toggle.css">
    <link rel="stylesheet" href="../assets/css/admin-viewtoggle.css">
    <link rel="stylesheet" href="../assets/css/admin-sidebar-collapse.css">
    <style>
        /* Map picker — kept inline so no extra CSS file is needed */
        .map-picker { margin-top: 6px; }
        .map-picker-canvas { width: 100%; height: 220px; border-radius: 10px; overflow: hidden; border: 1px solid rgba(245,237,216,.15); }
        .map-picker-hint { font-size: .7rem; color: rgba(245,237,216,.45); margin-top: 6px; }
        .map-search-row { display: flex; gap: 8px; margin-bottom: 8px; }
        .map-search-row .form-input { flex: 1; }
        .map-search-btn { white-space: nowrap; padding: 0 14px; border-radius: 8px; border: 1px solid rgba(245,237,216,.2); background: rgba(255,255,255,.06); color: #f5edd8; cursor: pointer; }
        .map-search-btn:hover { background: rgba(255,255,255,.12); }
        .image-current-hint { font-size: .7rem; color: rgba(245,237,216,.45); margin-top: 6px; }
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminfoodanddining.php" class="active"><span class="nav-icon"><i data-lucide="utensils" class="lucide"></i></span> Food &amp; Dining</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/admineventandfiesta.php"><span class="nav-icon"><i data-lucide="calendar-heart" class="lucide"></i></span> Events &amp; Fiesta</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminpeople.php"><span class="nav-icon"><i data-lucide="users-round" class="lucide"></i></span> People of Malvar</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsitecontent.php"><span class="nav-icon"><i data-lucide="image" class="lucide"></i></span> Site Content</a></li>
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
                <h1>Food &amp; <em>Dining</em></h1>
                <p>Manage restaurant, café, eatery and local product listings for Malvar, Batangas.</p>
            </div>
            <div class="header-actions">
                <?php if (!empty($foodListings)): ?>
                    <button class="btn-export-excel" id="exportExcelBtn" onclick="exportFoodListingsExcel()">
                        <i data-lucide="file-spreadsheet" class="lucide" style="width:.9rem;height:.9rem;"></i>
                        <span>Export as Excel</span>
                    </button>
                <?php endif; ?>
                <button class="btn-primary" onclick="openAddListing()"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> New Listing</button>
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

        <!-- KPI ROW -->
        <div class="mini-kpi-row animate">
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $totalListings); ?></div>
                <div class="mini-kpi-label">Total Listings</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $productCount); ?></div>
                <div class="mini-kpi-label">Products</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $restaurantCount); ?></div>
                <div class="mini-kpi-label">Restaurants</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $addedThisMonth); ?></div>
                <div class="mini-kpi-label">Added This Month</div>
            </div>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row animate">
            <input class="search-input" type="text" placeholder="Search listings…" oninput="filterListings()" id="searchInput">
            <select class="filter-select" onchange="filterListings()" id="typeFilter">
                <option value="">All Listing Types</option>
                <option value="product">Products</option>
                <option value="restaurant">Restaurants</option>
            </select>
            <select class="filter-select" onchange="filterListings()" id="categoryFilter">
                <option value="">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="view-toggle">
                <button type="button" class="view-btn" data-view="list" onclick="setView('list')" aria-label="List view"><i data-lucide="list" class="lucide"></i></button>
                <button type="button" class="view-btn" data-view="grid" onclick="setView('grid')" aria-label="Grid view"><i data-lucide="layout-grid" class="lucide"></i></button>
            </div>
        </div>

        <!-- LISTINGS TABLE -->
        <div class="data-table-wrap animate">
            <?php if (empty($foodListings)): ?>
                <div class="chart-empty">
                    <i data-lucide="utensils" class="lucide"></i>
                    <div class="chart-empty-title">No food &amp; dining listings yet</div>
                    <div class="chart-empty-sub">Restaurant, café, eatery and product submissions will show up here once added.</div>
                </div>
            <?php else: ?>
                <table class="data-table" id="foodListingsTable">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Price / Contact</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($foodListings as $l): ?>
                            <tr data-id="<?php echo (int) $l['id']; ?>" data-type="<?php echo htmlspecialchars($l['type']); ?>" data-category="<?php echo htmlspecialchars($l['category']); ?>" data-name="<?php echo htmlspecialchars(strtolower($l['name'])); ?>">
                                <td><?php echo htmlspecialchars($l['name']); ?></td>
                                <td><?php echo $l['type'] === 'product' ? 'Product' : 'Restaurant'; ?></td>
                                <td><?php echo htmlspecialchars($l['category']); ?></td>
                                <td><?php echo htmlspecialchars($l['address'] ?: '—'); ?></td>
                                <td>
                                    <?php if ($l['type'] === 'product'): ?>
                                        <?php echo $l['price'] !== null ? '₱' . htmlspecialchars(number_format((float) $l['price'], 2)) : '—'; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($l['contact_number'] ?: '—'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) ($foodViewsByType[$l['type']][(int) $l['id']] ?? 0); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="tbl-btn view" onclick="openViewListing(<?php echo (int) $l['id']; ?>, '<?php echo htmlspecialchars($l['type'], ENT_QUOTES); ?>')"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                                        <button type="button" class="tbl-btn edit" onclick="openEditListing(<?php echo (int) $l['id']; ?>, '<?php echo htmlspecialchars($l['type'], ENT_QUOTES); ?>')"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                        <button type="button" class="tbl-btn delete" onclick="confirmDelete(<?php echo (int) $l['id']; ?>, '<?php echo htmlspecialchars($l['type'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($l['name'], ENT_QUOTES); ?>')"><i data-lucide="archive" class="lucide" style="width:.75rem;height:.75rem;"></i> Archive</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- GRID VIEW -->
        <div class="grid-view-panel" id="foodListingsGrid">
            <?php if (empty($foodListings)): ?>
                <div class="chart-empty grid-empty">
                    <i data-lucide="utensils" class="lucide"></i>
                    <div class="chart-empty-title">No food &amp; dining listings yet</div>
                    <div class="chart-empty-sub">Restaurant, café, eatery and product submissions will show up here once added.</div>
                </div>
            <?php else: ?>
                <?php foreach ($foodListings as $l): ?>
                    <div class="grid-card" data-type="<?php echo htmlspecialchars($l['type']); ?>" data-category="<?php echo htmlspecialchars($l['category']); ?>" data-name="<?php echo htmlspecialchars(strtolower($l['name'])); ?>">
                        <div class="grid-card-media">
                            <?php if (!empty($l['image'])): ?>
                                <img src="<?php echo htmlspecialchars($l['image']); ?>" alt="">
                            <?php else: ?>
                                <i data-lucide="<?php echo $l['type'] === 'product' ? 'shopping-bag' : 'utensils'; ?>" class="lucide"></i>
                            <?php endif; ?>
                            <span class="grid-card-badge tbl-btn <?php echo $l['type'] === 'product' ? 'edit' : 'view'; ?>"><?php echo $l['type'] === 'product' ? 'Product' : 'Restaurant'; ?></span>
                        </div>
                        <div class="grid-card-body">
                            <div class="grid-card-title"><?php echo htmlspecialchars($l['name']); ?></div>
                            <div class="grid-card-sub"><?php echo htmlspecialchars($l['category']); ?> &middot; <?php echo htmlspecialchars($l['address'] ?: '—'); ?></div>
                            <div class="grid-card-meta">
                                <span>
                                    <?php if ($l['type'] === 'product'): ?>
                                        <?php echo $l['price'] !== null ? '₱' . htmlspecialchars(number_format((float) $l['price'], 2)) : '—'; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($l['contact_number'] ?: '—'); ?>
                                    <?php endif; ?>
                                </span>
                                <span><i data-lucide="eye" class="lucide"></i> <?php echo (int) ($foodViewsByType[$l['type']][(int) $l['id']] ?? 0); ?></span>
                            </div>
                        </div>
                        <div class="grid-card-actions">
                            <button type="button" class="tbl-btn view" onclick="openViewListing(<?php echo (int) $l['id']; ?>, '<?php echo htmlspecialchars($l['type'], ENT_QUOTES); ?>')"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                            <button type="button" class="tbl-btn edit" onclick="openEditListing(<?php echo (int) $l['id']; ?>, '<?php echo htmlspecialchars($l['type'], ENT_QUOTES); ?>')"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                            <button type="button" class="tbl-btn delete" onclick="confirmDelete(<?php echo (int) $l['id']; ?>, '<?php echo htmlspecialchars($l['type'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($l['name'], ENT_QUOTES); ?>')"><i data-lucide="archive" class="lucide" style="width:.75rem;height:.75rem;"></i> Archive</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pagination" id="foodListingsPagination"></div>

    </div>
</div>

<!-- ADD LISTING MODAL -->
<div class="modal-overlay" id="addListingModal" onclick="closeModalOutside(event, 'addListingModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('addListingModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add New Listing</div>
        <div class="modal-sub">Fill in the details to add a new restaurant, eatery, or local product.</div>
        <form id="addListingForm" method="POST" action="adminfoodanddining.php" enctype="multipart/form-data" onsubmit="return validateListingForm('add');">
            <input type="hidden" name="form_action" value="add_listing">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="latitude" id="addLatInput" value="">
            <input type="hidden" name="longitude" id="addLngInput" value="">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Listing Type</label>
                    <select class="filter-select" name="listing_type" id="addTypeInput" style="width:100%; padding:11px 14px; border-radius:8px;" onchange="updateCategoryOptions('addTypeInput', 'addCategoryInput'); toggleTypeFields('add');">
                        <option value="product">Product</option>
                        <option value="restaurant">Restaurant</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <!-- Hidden value carrier only — toggleCategoryField() builds and
                         shows the real <select> below, options set per listing type. -->
                    <input class="form-input" type="hidden" name="category" id="addCategoryInput">
                    <select class="filter-select" name="category" id="addCategorySelect" style="width:100%; padding:11px 14px; border-radius:8px;"></select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Listing Name</label>
                <input class="form-input" type="text" name="name" placeholder="e.g. Barako Brew Café">
            </div>

            <!-- Product-only fields -->
            <div class="form-row add-product-fields">
                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input class="form-input" type="number" step="0.01" min="0" name="price" placeholder="e.g. 150.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-input" type="text" name="location" placeholder="e.g. Town Proper, Malvar">
                </div>
            </div>

            <!-- Restaurant-only fields -->
            <div class="add-restaurant-fields" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input class="form-input" type="text" name="address" placeholder="e.g. Poblacion, Malvar, Batangas">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input class="form-input" type="text" name="contact_number" placeholder="e.g. 0917 123 4567">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Opening Hours</label>
                        <input class="form-input" type="text" name="opening_hours" placeholder="e.g. 8:00 AM – 8:00 PM">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Google Maps Link</label>
                    <input class="form-input" type="text" name="google_map" placeholder="Paste a Google Maps share link">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Image</label>
                <input class="form-input" type="file" name="image_file" accept="image/*">
            </div>

            <div class="form-group">
                <label class="form-label">Pin Location on Map</label>
                <div class="map-search-row">
                    <input class="form-input" type="text" id="addMapSearchInput" placeholder="Search an address, e.g. Town Proper, Malvar">
                    <button type="button" class="map-search-btn" onclick="searchAddress('add')">Find</button>
                    <button type="button" class="map-search-btn" onclick="useMyLocation('add')" title="Use my current location"><i data-lucide="locate-fixed" class="lucide" style="width:.8rem;height:.8rem;"></i></button>
                </div>
                <div class="map-picker">
                    <div class="map-picker-canvas" id="addMapCanvas"></div>
                    <p class="map-picker-hint" id="addMapHint">Search an address, or click/drag the pin directly on the map to set the exact spot. This is what powers the "Navigate" button on the site.</p>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="desc" placeholder="Describe this listing…"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Add Listing</button>
                <button type="button" class="btn-ghost" onclick="closeModal('addListingModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT LISTING MODAL -->
<div class="modal-overlay" id="editListingModal" onclick="closeModalOutside(event, 'editListingModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editListingModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Listing</div>
        <div class="modal-sub" id="editListingName">Editing: —</div>
        <form id="editListingForm" method="POST" action="adminfoodanddining.php" enctype="multipart/form-data" onsubmit="return validateListingForm('edit');">
            <input type="hidden" name="form_action" value="edit_listing">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" id="editIdInput" value="">
            <input type="hidden" name="listing_type" id="editTypeInput" value="">
            <input type="hidden" name="latitude" id="editLatInput" value="">
            <input type="hidden" name="longitude" id="editLngInput" value="">
            <!-- Holds the CURRENT image path. adminfoodanddining.js's openEditListing()
                 already sets this element's .value = l.image — unchanged, still works,
                 since this is just a hidden field now instead of a visible text one. -->
            <input type="hidden" name="existing_image" id="editImageInput" value="">

            <div class="form-group">
                <label class="form-label">Listing Name</label>
                <input class="form-input" type="text" name="name" id="editNameInput" placeholder="Listing name">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <!-- Hidden value carrier only — toggleCategoryField() builds and
                     shows the real <select> below, options set per listing type. -->
                <input class="form-input" type="hidden" name="category" id="editCategoryInput">
                <select class="filter-select" name="category" id="editCategorySelect" style="width:100%; padding:11px 14px; border-radius:8px;"></select>
            </div>

            <!-- Product-only fields -->
            <div class="form-row edit-product-fields">
                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input class="form-input" type="number" step="0.01" min="0" name="price" id="editPriceInput" placeholder="e.g. 150.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input class="form-input" type="text" name="location" id="editLocationInput" placeholder="e.g. Town Proper, Malvar">
                </div>
            </div>

            <!-- Restaurant-only fields -->
            <div class="edit-restaurant-fields" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input class="form-input" type="text" name="address" id="editAddressInput" placeholder="Address">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input class="form-input" type="text" name="contact_number" id="editContactInput" placeholder="Contact number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Opening Hours</label>
                        <input class="form-input" type="text" name="opening_hours" id="editHoursInput" placeholder="Opening hours">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Google Maps Link</label>
                    <input class="form-input" type="text" name="google_map" id="editGoogleMapInput" placeholder="Google Maps link">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Replace Image</label>
                <input class="form-input" type="file" name="image_file" accept="image/*">
                <p class="image-current-hint">Leave empty to keep the current image.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Pin Location on Map</label>
                <div class="map-search-row">
                    <input class="form-input" type="text" id="editMapSearchInput" placeholder="Search an address…">
                    <button type="button" class="map-search-btn" onclick="searchAddress('edit')">Find</button>
                    <button type="button" class="map-search-btn" onclick="useMyLocation('edit')" title="Use my current location"><i data-lucide="locate-fixed" class="lucide" style="width:.8rem;height:.8rem;"></i></button>
                </div>
                <div class="map-picker">
                    <div class="map-picker-canvas" id="editMapCanvas"></div>
                    <p class="map-picker-hint" id="editMapHint">Search an address, or click/drag the pin directly on the map to set the exact spot.</p>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="desc" id="editDescInput" placeholder="Description…"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editListingModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW LISTING MODAL -->
<div class="modal-overlay" id="viewListingModal" onclick="closeModalOutside(event, 'viewListingModal')">
    <div class="modal-card" style="max-width:480px;">
        <button class="modal-close" onclick="closeModal('viewListingModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title" style="text-align:center;" id="vlName">—</div>
        <div style="display:flex;justify-content:center;gap:10px;margin:10px 0 18px;flex-wrap:wrap;">
            <span style="font-size:.75rem;color:rgba(245,237,216,.4);align-self:center;" id="vlType">—</span>
            <span style="font-size:.75rem;color:rgba(245,237,216,.4);align-self:center;" id="vlCategory">—</span>
        </div>
        <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:16px;margin-bottom:12px;">
            <div style="font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:rgba(245,237,216,.3);margin-bottom:8px;">Details</div>
            <div style="font-size:.85rem;color:rgba(245,237,216,.65);line-height:1.8;" id="vlDetails">—</div>
        </div>
        <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:16px;margin-bottom:18px;">
            <div style="font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:rgba(245,237,216,.3);margin-bottom:8px;">Description</div>
            <div style="font-size:.85rem;color:rgba(245,237,216,.65);line-height:1.6;" id="vlDesc">—</div>
        </div>
        <div class="map-picker-canvas" id="viewMapCanvas" style="margin-bottom:18px;"></div>
        <div style="display:flex;gap:10px;">
            <button class="btn-primary" style="flex:1;" onclick="reopenAsEdit()"><i data-lucide="pencil" class="lucide" style="width:.85rem;height:.85rem;"></i> Edit Listing</button>
            <button class="btn-ghost" onclick="closeModal('viewListingModal')">Close</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal" onclick="closeModalOutside(event, 'deleteModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div class="modal-title" id="deleteTitle">Archive Item?</div>
        <div class="modal-sub" id="deleteDesc"><?php echo $isSuperAdmin ? 'It will be hidden from the public site, not permanently deleted.' : 'This will be submitted to a Super Admin for approval before it\'s archived.'; ?></div>
        <form method="POST" action="adminfoodanddining.php">
            <input type="hidden" name="form_action" value="delete_listing">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" id="deleteIdInput" value="">
            <input type="hidden" name="listing_type" id="deleteTypeInput" value="">
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="tbl-btn delete" style="flex:1; padding:12px;"><?php echo $isSuperAdmin ? 'Yes, Archive' : 'Submit Request'; ?></button>
                <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('deleteModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
    // Data for the JS file (../assets/js/adminfoodanddining.js) to read from.
    // Explicitly assigned onto `window` — a top-level `const`/`let` does NOT
    // become a `window` property in a classic script, so adminfoodanddining.js's
    // `window.listingsData` / `window.categoriesByType` lookups were always
    // undefined before this, which is why View/Edit always fell through to
    // their empty fallback objects despite the table itself rendering fine.
    //
    // JSON_INVALID_UTF8_SUBSTITUTE guards against json_encode() silently
    // returning false (printing nothing) if any stored text isn't valid
    // UTF-8, which would otherwise also break this line.
    window.listingsData = <?php echo json_encode($foodListings, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
    window.categoriesByType = <?php echo json_encode($categoriesByType, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
    <?php if ($flashMessage): ?>
    window.__adminFlash = <?php echo json_encode($flashMessage, JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null'; ?>;
    <?php endif; ?>
</script>
<script src="../assets/js/admin-viewtoggle.js"></script>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-theme.js"></script>
<script src="../assets/js/admin-notifications.js"></script>
<script src="../assets/js/adminfoodanddining.js"></script>
<script>initViewToggle('food', '.data-table-wrap', '#foodListingsGrid'); initSidebarCollapse();</script>

</body>
</html>