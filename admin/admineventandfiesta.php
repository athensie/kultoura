<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/csrf.php';

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 | This page lives one folder deep (/kultoura/admin/admineventandfiesta.php),
 | so a plain header("Location: index.php") would resolve relative to
 | /admin/ and break. Anchoring every redirect to a fixed base path
 | avoids that, regardless of which folder a script is called from.
 |
 | Change this if your project folder name/path is different.
 */
define('BASE_URL', '/kultoura');

/*
 |--------------------------------------------------------------------
 | ACCESS CONTROL
 |--------------------------------------------------------------------
 | Only logged-in users with the "admin" or "super admin" role may
 | view this page. Anyone else (including guests) is bounced to
 | the real site root index.php — mirrors admindashboard.php.
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
 | EVENTS & FIESTA — DB ACTIONS (add / edit / delete)
 |--------------------------------------------------------------------
 | Handled inline (no separate *_actions.php) so this stays a single
 | file. `fiestas` table columns: fiesta_id, fiesta_name,
 | celebration_date, location, description, image, created_at, plus
 | `type` ENUM('Fiesta','Event') and `latitude`/`longitude` — run these
 | once if you haven't yet:
 |
 |   ALTER TABLE fiestas
 |     ADD COLUMN type ENUM('Fiesta','Event') NOT NULL DEFAULT 'Fiesta'
 |     AFTER fiesta_name;
 |
 |   ALTER TABLE fiestas
 |     ADD COLUMN latitude DECIMAL(10,7) NULL AFTER location,
 |     ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $type     = ($_POST['type'] ?? '') === 'Event' ? 'Event' : 'Fiesta';
        $location = trim($_POST['location'] ?? '');
        $date     = $_POST['date'] ?? null;
        $desc     = trim($_POST['desc'] ?? '');
        $lat      = ($_POST['latitude'] ?? '') !== '' ? (float) $_POST['latitude'] : null;
        $lng      = ($_POST['longitude'] ?? '') !== '' ? (float) $_POST['longitude'] : null;

        if ($name === '') {
            $_SESSION['flash'] = 'Event name is required.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO fiestas (fiesta_name, type, celebration_date, location, latitude, longitude, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssssdds', $name, $type, $date, $location, $lat, $lng, $desc);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '"' . $name . '" added.';
        }
        header('Location: admineventandfiesta.php');
        exit;
    }

    if ($action === 'edit') {
        $id       = (int) ($_POST['fiesta_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $type     = ($_POST['type'] ?? '') === 'Event' ? 'Event' : 'Fiesta';
        $location = trim($_POST['location'] ?? '');
        $date     = $_POST['date'] ?? null;
        $desc     = trim($_POST['desc'] ?? '');
        $lat      = ($_POST['latitude'] ?? '') !== '' ? (float) $_POST['latitude'] : null;
        $lng      = ($_POST['longitude'] ?? '') !== '' ? (float) $_POST['longitude'] : null;

        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare(
                "UPDATE fiestas
                 SET fiesta_name = ?, type = ?, celebration_date = ?, location = ?, latitude = ?, longitude = ?, description = ?
                 WHERE fiesta_id = ?"
            );
            $stmt->bind_param('ssssddsi', $name, $type, $date, $location, $lat, $lng, $desc, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = '"' . $name . '" updated.';
        } else {
            $_SESSION['flash'] = 'Could not update — event name is required.';
        }
        header('Location: admineventandfiesta.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['fiesta_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM fiestas WHERE fiesta_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = 'Event deleted.';
        }
        header('Location: admineventandfiesta.php');
        exit;
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/*
 |--------------------------------------------------------------------
 | EVENTS & FIESTA — FETCH
 |--------------------------------------------------------------------
 */
$eventTypes = ['Fiesta', 'Event'];

$events = [];
if ($result = $conn->query("SELECT * FROM fiestas ORDER BY celebration_date ASC")) {
    $events = $result->fetch_all(MYSQLI_ASSOC);
}

// Real per-place view counts (see config/analytics.php).
$eventViews = analytics_item_views_bulk($conn, 'fiesta');

$totalEvents   = count($events);
$fiestaCount   = count(array_filter($events, fn($e) => $e['type'] === 'Fiesta'));
$eventCount    = count(array_filter($events, fn($e) => $e['type'] === 'Event'));
$upcomingCount = count(array_filter($events, function ($e) {
    $ts = strtotime($e['celebration_date'] ?? '');
    return $ts && $ts >= strtotime('today') && $ts <= strtotime('+30 days');
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events &amp; Fiesta – KULTOURA Admin</title>

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

    <!-- Leaflet (OpenStreetMap) — free, no API key, used for the location picker -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <link rel="stylesheet" href="../assets/css/admineventandfiesta.css">
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
        <li><a href="<?php echo BASE_URL; ?>/admin/admineventandfiesta.php" class="active"><span class="nav-icon"><i data-lucide="calendar-heart" class="lucide"></i></span> Events &amp; Fiesta</a></li>
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
                <h1>Events &amp; <em>Fiesta</em></h1>
                <p>Manage fiestas and community events for Malvar, Batangas. Anything added here shows up on the public Fiestas page.</p>
            </div>
            <div class="header-actions">
                <?php if (!empty($events)): ?>
                    <button class="btn-export-excel" id="exportExcelBtn" onclick="exportEventsExcel()">
                        <i data-lucide="file-spreadsheet" class="lucide" style="width:.9rem;height:.9rem;"></i>
                        <span>Export as Excel</span>
                    </button>
                <?php endif; ?>
                <button class="btn-primary" onclick="openAddEvent()"><i data-lucide="plus" class="lucide" style="width:.85rem;height:.85rem;"></i> New Event</button>
            </div>
        </div>

        <!-- KPI ROW -->
        <div class="mini-kpi-row animate">
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $totalEvents); ?></div>
                <div class="mini-kpi-label">Total Events</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $fiestaCount); ?></div>
                <div class="mini-kpi-label">Fiestas</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $eventCount); ?></div>
                <div class="mini-kpi-label">Events</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $upcomingCount); ?></div>
                <div class="mini-kpi-label">Upcoming This Month</div>
            </div>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row animate">
            <input class="search-input" type="text" placeholder="Search events…" oninput="filterEvents(this.value)">
            <select class="filter-select" onchange="filterEventsByType(this.value)">
                <option>All Types</option>
                <?php foreach ($eventTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="view-toggle">
                <button type="button" class="view-btn" data-view="list" onclick="setView('list')" aria-label="List view"><i data-lucide="list" class="lucide"></i></button>
                <button type="button" class="view-btn" data-view="grid" onclick="setView('grid')" aria-label="Grid view"><i data-lucide="layout-grid" class="lucide"></i></button>
            </div>
        </div>

        <!-- EVENTS TABLE -->
        <div class="data-table-wrap animate">
            <?php if (empty($events)): ?>
                <div class="chart-empty">
                    <i data-lucide="calendar-heart" class="lucide"></i>
                    <div class="chart-empty-title">No events yet</div>
                    <div class="chart-empty-sub">Fiestas and community events you add will show up here, and on the public Fiestas page.</div>
                </div>
            <?php else: ?>
                <table class="data-table" id="eventsTable">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                            <tr data-id="<?php echo htmlspecialchars((string) $e['fiesta_id']); ?>" data-name="<?php echo htmlspecialchars($e['fiesta_name']); ?>" data-type="<?php echo htmlspecialchars($e['type']); ?>">
                                <td><?php echo htmlspecialchars($e['fiesta_name']); ?></td>
                                <td><span class="type-pill <?php echo strtolower($e['type']); ?>"><?php echo htmlspecialchars($e['type']); ?></span></td>
                                <td><?php echo htmlspecialchars($e['location'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($e['celebration_date'] ?: '—'); ?></td>
                                <td><?php echo (int) ($eventViews[(int) $e['fiesta_id']] ?? 0); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="tbl-btn view" onclick="openViewEvent('<?php echo (int) $e['fiesta_id']; ?>')"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                                        <button class="tbl-btn edit" onclick="openEditEvent('<?php echo (int) $e['fiesta_id']; ?>')"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                        <button class="tbl-btn delete" onclick="confirmDelete('<?php echo (int) $e['fiesta_id']; ?>', '<?php echo htmlspecialchars($e['fiesta_name'], ENT_QUOTES); ?>')"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- GRID VIEW -->
        <div class="grid-view-panel" id="eventsGrid">
            <?php if (empty($events)): ?>
                <div class="chart-empty grid-empty">
                    <i data-lucide="calendar-heart" class="lucide"></i>
                    <div class="chart-empty-title">No events yet</div>
                    <div class="chart-empty-sub">Fiestas and community events you add will show up here, and on the public Fiestas page.</div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $e): ?>
                    <div class="grid-card" data-id="<?php echo htmlspecialchars((string) $e['fiesta_id']); ?>" data-name="<?php echo htmlspecialchars($e['fiesta_name']); ?>" data-type="<?php echo htmlspecialchars($e['type']); ?>">
                        <div class="grid-card-media">
                            <?php if (!empty($e['image'])): ?>
                                <img src="<?php echo htmlspecialchars($e['image']); ?>" alt="">
                            <?php else: ?>
                                <i data-lucide="calendar-heart" class="lucide"></i>
                            <?php endif; ?>
                            <span class="type-pill grid-card-badge <?php echo strtolower($e['type']); ?>"><?php echo htmlspecialchars($e['type']); ?></span>
                        </div>
                        <div class="grid-card-body">
                            <div class="grid-card-title"><?php echo htmlspecialchars($e['fiesta_name']); ?></div>
                            <div class="grid-card-sub"><?php echo htmlspecialchars($e['location'] ?: '—'); ?> &middot; <?php echo htmlspecialchars($e['celebration_date'] ?: '—'); ?></div>
                            <div class="grid-card-meta">
                                <span><i data-lucide="eye" class="lucide"></i> <?php echo (int) ($eventViews[(int) $e['fiesta_id']] ?? 0); ?> views</span>
                            </div>
                        </div>
                        <div class="grid-card-actions">
                            <button class="tbl-btn view" onclick="openViewEvent('<?php echo (int) $e['fiesta_id']; ?>')"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                            <button class="tbl-btn edit" onclick="openEditEvent('<?php echo (int) $e['fiesta_id']; ?>')"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                            <button class="tbl-btn delete" onclick="confirmDelete('<?php echo (int) $e['fiesta_id']; ?>', '<?php echo htmlspecialchars($e['fiesta_name'], ENT_QUOTES); ?>')"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pagination" id="eventsPagination"></div>

    </div>
</div>

<!-- ADD EVENT MODAL -->
<div class="modal-overlay" id="addEventModal" onclick="closeModalOutside(event, 'addEventModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('addEventModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Add New Event</div>
        <div class="modal-sub">Fill in the details to add a new fiesta or community event.</div>
        <form id="addEventForm" method="POST" action="admineventandfiesta.php">
            <input type="hidden" name="action" value="add">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label">Event Name</label>
                <input class="form-input" type="text" name="name" placeholder="e.g. Pista ng Malvar" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Event Type</label>
                    <select class="filter-select" name="type" style="width:100%; padding:11px 14px; border-radius:8px;">
                        <?php foreach ($eventTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Event Date</label>
                    <input class="form-input" type="date" name="date">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <div class="map-picker">
                    <input class="form-input map-search" type="text" id="addMapSearch" placeholder="Search a place… or just click the map" onkeydown="if(event.key==='Enter'){event.preventDefault();searchMapPlace('add');}">
                    <div class="map-canvas" id="addMapCanvas"></div>
                    <div class="map-picked-label" id="addMapLabel">No location selected yet — click the map.</div>
                </div>
                <input type="hidden" name="location" id="addLocationInput">
                <input type="hidden" name="latitude" id="addLatInput">
                <input type="hidden" name="longitude" id="addLngInput">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="desc" placeholder="Describe this event…"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1">Add Event</button>
                <button type="button" class="btn-ghost" onclick="closeModal('addEventModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT EVENT MODAL -->
<div class="modal-overlay" id="editEventModal" onclick="closeModalOutside(event, 'editEventModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editEventModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Event</div>
        <div class="modal-sub" id="editEventName">Editing: —</div>
        <form id="editEventForm" method="POST" action="admineventandfiesta.php">
            <input type="hidden" name="action" value="edit">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="fiesta_id" id="editFiestaId">
            <div class="form-group">
                <label class="form-label">Event Name</label>
                <input class="form-input" type="text" name="name" id="editNameInput" placeholder="Event name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Event Type</label>
                    <select class="filter-select" name="type" id="editTypeInput" style="width:100%; padding:11px 14px; border-radius:8px;">
                        <?php foreach ($eventTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Event Date</label>
                    <input class="form-input" type="date" name="date" id="editDateInput">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <div class="map-picker">
                    <input class="form-input map-search" type="text" id="editMapSearch" placeholder="Search a place… or just click the map" onkeydown="if(event.key==='Enter'){event.preventDefault();searchMapPlace('edit');}">
                    <div class="map-canvas" id="editMapCanvas"></div>
                    <div class="map-picked-label" id="editMapLabel">No location selected yet — click the map.</div>
                </div>
                <input type="hidden" name="location" id="editLocationInput">
                <input type="hidden" name="latitude" id="editLatInput">
                <input type="hidden" name="longitude" id="editLngInput">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="desc" id="editDescInput" placeholder="Description…"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editEventModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW EVENT MODAL -->
<div class="modal-overlay" id="viewEventModal" onclick="closeModalOutside(event, 'viewEventModal')">
    <div class="modal-card" style="max-width:480px;">
        <button class="modal-close" onclick="closeModal('viewEventModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title" style="text-align:center;" id="veName">—</div>
        <div style="display:flex;justify-content:center;gap:10px;margin:10px 0 18px;flex-wrap:wrap;">
            <span class="type-pill fiesta" id="veType">—</span>
            <span style="font-size:.75rem;color:rgba(245,237,216,.4);align-self:center;" id="veLocation">—</span>
            <span style="font-size:.75rem;color:rgba(245,237,216,.4);align-self:center;" id="veDate">—</span>
        </div>
        <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:16px;margin-bottom:18px;">
            <div style="font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:rgba(245,237,216,.3);margin-bottom:8px;">Description</div>
            <div style="font-size:.85rem;color:rgba(245,237,216,.65);line-height:1.6;" id="veDesc">—</div>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn-primary" style="flex:1;" id="veEditBtn"><i data-lucide="pencil" class="lucide" style="width:.85rem;height:.85rem;"></i> Edit Event</button>
            <button class="btn-ghost" onclick="closeModal('viewEventModal')">Close</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal" onclick="closeModalOutside(event, 'deleteModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div class="modal-title" id="deleteTitle">Delete Item?</div>
        <div class="modal-sub" id="deleteDesc">This action cannot be undone.</div>
        <form id="deleteForm" method="POST" action="admineventandfiesta.php">
            <input type="hidden" name="action" value="delete">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="fiesta_id" id="deleteFiestaId">
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
    const eventsData = <?php echo json_encode($events); ?>;
</script>
<script src="../assets/js/admin-viewtoggle.js"></script>
<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-theme.js"></script>
<script src="../assets/js/admineventandfiesta.js"></script>
<script>initViewToggle('events', '.data-table-wrap', '#eventsGrid'); initSidebarCollapse();</script>
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