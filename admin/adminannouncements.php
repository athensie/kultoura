<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/csrf.php';

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
 | Same guard as the rest of the admin panel: admin / super admin only.
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

include '../config/dbmain.php';
include '../config/announcements.php';

$flashMessage = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

/*
 |--------------------------------------------------------------------
 | ANNOUNCEMENTS DATA — real query.
 |--------------------------------------------------------------------
 */
$announcements = [];
if ($result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC")) {
    $announcements = $result->fetch_all(MYSQLI_ASSOC);
}

$totalCount    = count($announcements);
$liveCount     = count(array_filter($announcements, fn($a) => $a['status'] === 'live'));
$scheduledCount = count(array_filter($announcements, fn($a) => $a['status'] === 'scheduled'));
$draftCount    = count(array_filter($announcements, fn($a) => $a['status'] === 'draft'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – KULTOURA Admin</title>

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

    <link rel="stylesheet" href="../assets/css/adminannouncements.css">
    <link rel="stylesheet" href="../assets/css/admin-theme-toggle.css">
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
    </ul>

    <div class="sidebar-section">Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminannouncements.php" class="active"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
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
                <div class="section-label">Site Communication</div>
                <h1>Announcements <em>&amp; Notices</em></h1>
                <p>Publish updates, alerts, and event notices to KulToura visitors.</p>
            </div>
        </div>

        <!-- MINI KPI ROW -->
        <div class="mini-kpi-row animate">
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $totalCount; ?></div>
                <div class="mini-kpi-label">Total Announcements</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $liveCount; ?></div>
                <div class="mini-kpi-label">Live</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $scheduledCount; ?></div>
                <div class="mini-kpi-label">Scheduled</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo (int) $draftCount; ?></div>
                <div class="mini-kpi-label">Drafts</div>
            </div>
        </div>

        <!-- COMPOSER -->
        <div class="announce-box animate">
            <h3><i data-lucide="megaphone" class="lucide" style="width:1rem;height:1rem;color:var(--gold);"></i> New Announcement</h3>

            <form id="announceForm" action="<?php echo BASE_URL; ?>/admin/announcements_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="type" id="announceTypeInput" value="info">
                <input type="hidden" name="status" id="announceStatusInput" value="live">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input class="form-input" type="text" name="title" placeholder="Announcement title…" id="announceTitle" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Audience</label>
                        <select class="filter-select" name="audience" style="width:100%; padding:11px 16px; border-radius:8px;">
                            <option>All Users</option>
                            <option>Registered Users Only</option>
                            <option>Admins Only</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea class="form-textarea" name="body" placeholder="Write your announcement here…" id="announceBody" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Image (optional)</label>
                    <input class="form-input" type="file" name="image_file" accept="image/png,image/jpeg,image/webp,image/gif">
                    <div class="image-current-hint">Shown on the homepage News card if this announcement goes live.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Type</label>
                    <div class="type-selector">
                        <button type="button" class="type-chip active" onclick="selectType(this, 'info')"><i data-lucide="info" class="lucide" style="width:.75rem;height:.75rem;"></i> Info</button>
                        <button type="button" class="type-chip" onclick="selectType(this, 'alert')"><i data-lucide="triangle-alert" class="lucide" style="width:.75rem;height:.75rem;"></i> Alert</button>
                        <button type="button" class="type-chip" onclick="selectType(this, 'event')"><i data-lucide="calendar-heart" class="lucide" style="width:.75rem;height:.75rem;"></i> Event</button>
                        <button type="button" class="type-chip" onclick="selectType(this, 'update')"><i data-lucide="refresh-cw" class="lucide" style="width:.75rem;height:.75rem;"></i> Update</button>
                        <button type="button" class="type-chip" onclick="selectType(this, 'maintenance')"><i data-lucide="wrench" class="lucide" style="width:.75rem;height:.75rem;"></i> Maintenance</button>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px; flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" onclick="document.getElementById('announceStatusInput').value='live'"><i data-lucide="send" class="lucide" style="width:.85rem;height:.85rem;"></i> Publish Now</button>
                    <button type="button" class="btn-ghost" onclick="openScheduleModal()"><i data-lucide="calendar-clock" class="lucide" style="width:.85rem;height:.85rem;"></i> Schedule</button>
                    <button type="submit" class="btn-ghost" onclick="document.getElementById('announceStatusInput').value='draft'"><i data-lucide="save" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Draft</button>
                </div>
            </form>
        </div>

        <!-- PUBLISHED LIST -->
        <div class="alabel-desc">Published <span class="analytics-tag">History</span></div>

        <div class="data-table-wrap animate">
            <table class="data-table" id="announcementsTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Audience</th>
                        <th>Published</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($announcements)): ?>
                    <tr id="annEmptyRow">
                        <td colspan="6">
                            <div class="table-empty">
                                <i data-lucide="megaphone" class="lucide"></i>
                                <div class="table-empty-title">No announcements yet</div>
                                <div class="table-empty-sub">Whatever you publish above will show up here.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($announcements as $a): ?>
                        <tr>
                            <td>
                                <?php if (!empty($a['image'])): ?>
                                    <img class="ann-thumb" src="<?php echo htmlspecialchars($a['image']); ?>" alt="">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($a['title']); ?>
                            </td>
                            <td><span class="type-badge <?php echo htmlspecialchars($a['type']); ?>"><?php echo htmlspecialchars(ucfirst($a['type'])); ?></span></td>
                            <td><?php echo htmlspecialchars($a['audience']); ?></td>
                            <td style="color:rgba(245,237,216,.4)"><?php echo $a['published_at'] ? htmlspecialchars(date('M j, Y', strtotime($a['published_at']))) : '—'; ?></td>
                            <td>
                                <?php
                                    $statusMap = ['live' => 'active', 'archived' => 'inactive', 'draft' => 'pending', 'scheduled' => 'pending'];
                                    $statusClass = $statusMap[$a['status']] ?? 'pending';
                                ?>
                                <span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($a['status'])); ?></span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="tbl-btn edit"
                                        data-id="<?php echo (int) $a['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($a['title']); ?>"
                                        data-body="<?php echo htmlspecialchars($a['body'] ?? ''); ?>"
                                        data-type="<?php echo htmlspecialchars($a['type']); ?>"
                                        data-status="<?php echo htmlspecialchars($a['status']); ?>"
                                        data-image="<?php echo htmlspecialchars($a['image'] ?? ''); ?>"
                                        onclick="openEditAnnouncement(this)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                    <button class="tbl-btn delete"
                                        data-id="<?php echo (int) $a['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($a['title']); ?>"
                                        onclick="confirmDeleteAnnouncement(this)"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Remove</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination" id="announcementsPagination"></div>

    </div>
</div>

<!-- SCHEDULE MODAL -->
<div class="modal-overlay" id="scheduleModal" onclick="closeModalOutside(event,'scheduleModal')">
    <div class="modal-card" style="max-width:400px;">
        <button class="modal-close" onclick="closeModal('scheduleModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Schedule Announcement</div>
        <div class="modal-sub">Choose when to publish this announcement.</div>
        <div class="form-group">
            <label class="form-label">Publish Date &amp; Time</label>
            <input class="form-input" type="datetime-local" id="scheduleDateTime">
        </div>
        <div class="form-group">
            <label class="form-label">Timezone</label>
            <select class="filter-select" id="scheduleTimezone" style="width:100%;padding:11px 14px;border-radius:8px;">
                <option>Asia/Manila (PHT, UTC+8)</option>
                <option>UTC</option>
            </select>
        </div>
        <div style="background:rgba(232,168,66,.08);border:1px solid rgba(232,168,66,.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:.78rem;color:var(--gold);" id="schedulePreview">Select a date to preview.</div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn-primary" style="flex:1;" onclick="confirmSchedule()"><i data-lucide="calendar-check" class="lucide" style="width:.85rem;height:.85rem;"></i> Confirm Schedule</button>
            <button type="button" class="btn-ghost" onclick="closeModal('scheduleModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- EDIT ANNOUNCEMENT MODAL -->
<div class="modal-overlay" id="editAnnouncementModal" onclick="closeModalOutside(event,'editAnnouncementModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editAnnouncementModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Announcement</div>
        <div class="modal-sub" id="editAnnSubtitle">Editing published announcement</div>

        <form id="editAnnouncementForm" action="<?php echo BASE_URL; ?>/admin/announcements_actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editAnnId" value="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="existing_image" id="editAnnExistingImage" value="">

            <div class="form-group">
                <label class="form-label">Title</label>
                <input class="form-input" type="text" name="title" id="editAnnTitle" required>
            </div>
            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea class="form-textarea" name="body" id="editAnnBody" style="min-height:100px;"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Image</label>
                <img id="editAnnImagePreview" class="ann-thumb" style="width:60px;height:60px;display:none;margin-bottom:8px;" alt="">
                <input class="form-input" type="file" name="image_file" accept="image/png,image/jpeg,image/webp,image/gif">
                <div class="image-current-hint">Leave blank to keep the current image.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="filter-select" name="type" id="editAnnType" style="width:100%;padding:11px 14px;border-radius:8px;">
                        <option value="info">Info</option>
                        <option value="alert">Alert</option>
                        <option value="event">Event</option>
                        <option value="update">Update</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="filter-select" name="status" id="editAnnStatus" style="width:100%;padding:11px 14px;border-radius:8px;">
                        <option value="live">Live</option>
                        <option value="archived">Archived</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1;"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editAnnouncementModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteAnnouncementModal" onclick="closeModalOutside(event, 'deleteAnnouncementModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div style="font-size:3rem; margin-bottom:12px;">🗑️</div>
        <div class="modal-title" id="deleteAnnouncementTitle">Remove announcement?</div>
        <div class="modal-sub">This action cannot be undone.</div>
        <form id="deleteAnnouncementForm" action="<?php echo BASE_URL; ?>/admin/announcements_actions.php" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteAnnId" value="">
            <?php echo csrf_field(); ?>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="tbl-btn delete" style="flex:1; padding:12px;">Yes, Remove</button>
                <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('deleteAnnouncementModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/admin-theme.js"></script>
<script src="../assets/js/adminannouncements.js"></script>
<script>
initSidebarCollapse();
<?php if ($flashMessage): ?>
document.addEventListener('DOMContentLoaded', function () {
    showToast(<?php echo json_encode($flashMessage); ?>);
});
<?php endif; ?>
</script>

</body>
</html>