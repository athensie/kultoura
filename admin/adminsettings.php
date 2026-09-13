<?php
session_start();

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 | Keep this in sync with the rest of the admin panel.
 */
define('BASE_URL', '/kultoura');

/*
 |--------------------------------------------------------------------
 | DATABASE
 |--------------------------------------------------------------------
 | Needed to prefill the Admin Account card with this admin's real
 | name/email. Adjust the path if dbmain.php lives somewhere else.
 */
require_once __DIR__ . '/../config/dbmain.php';

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
 | CURRENT ADMIN ACCOUNT
 |--------------------------------------------------------------------
 | Pulled fresh from the admins table (not just the session) so the
 | Admin Account form always shows this admin's real, current name
 | and email — not stale session data.
 */
$currentAdmin = ['first_name' => '', 'last_name' => '', 'email' => ''];
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM admins WHERE admin_id = ? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) {
    $currentAdmin = $row;
}
$displayName = trim(($currentAdmin['first_name'] ?? '') . ' ' . ($currentAdmin['last_name'] ?? '')) ?: $adminName;

/*
 |--------------------------------------------------------------------
 | SITE SETTINGS
 |--------------------------------------------------------------------
 | Sensible defaults for now. Meant to be replaced with real rows from
 | a key/value `settings` table once it exists, e.g.:
 |
 |   CREATE TABLE settings (setting_key VARCHAR(64) PRIMARY KEY, setting_value TINYINT(1));
 |
 |   $result = $conn->query("SELECT setting_key, setting_value FROM settings");
 |   $settings = [];
 |   foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
 |       $settings[$row['setting_key']] = (bool) $row['setting_value'];
 |   }
 |
 | Each toggle below posts immediately to settings_actions.php with
 | its key and new value, so once that table exists this page needs
 | no front-end changes — only the $settings array below needs to
 | start pulling from the database instead of these defaults.
 */
$settings = [
    'public_access'         => true,
    'maintenance_mode'      => false,
    'show_visitor_count'    => true,
    'email_alerts'          => true,
    'new_listing_alert'     => true,
    'flagged_content_alert' => true,
    'weekly_summary'        => false,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings – KULTOURA Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>

    <link rel="stylesheet" href="../assets/css/adminsettings.css">
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminannouncements.php"><span class="nav-icon"><i data-lucide="megaphone" class="lucide"></i></span> Announcements</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/adminsettings.php" class="active"><span class="nav-icon"><i data-lucide="settings" class="lucide"></i></span> Settings</a></li>
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
                <div class="section-label">Admin Panel</div>
                <h1>Site <em>Settings</em></h1>
                <p>Control site visibility, notifications, and your admin account.</p>
            </div>
        </div>

        <div class="settings-grid animate">

            <!-- SITE VISIBILITY -->
            <div class="settings-card">
                <h3><i data-lucide="globe" class="lucide" style="width:1rem;height:1rem;color:var(--gold);"></i> Site Visibility</h3>

                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label">KulToura Public Access</div>
                        <div class="setting-desc">Allow public users to browse the site</div>
                    </div>
                    <button class="toggle <?php echo $settings['public_access'] ? 'on' : ''; ?>" data-key="public_access" onclick="toggleSetting(this)"></button>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label">Maintenance Mode</div>
                        <div class="setting-desc">Redirect all visitors to a maintenance page</div>
                    </div>
                    <button class="toggle <?php echo $settings['maintenance_mode'] ? 'on' : ''; ?>" data-key="maintenance_mode" onclick="toggleSetting(this)"></button>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label">Show Live Visitor Count</div>
                        <div class="setting-desc">Display real-time visitors on homepage</div>
                    </div>
                    <button class="toggle <?php echo $settings['show_visitor_count'] ? 'on' : ''; ?>" data-key="show_visitor_count" onclick="toggleSetting(this)"></button>
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="settings-card">
                <h3><i data-lucide="bell" class="lucide" style="width:1rem;height:1rem;color:var(--gold);"></i> Notifications</h3>

                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label">Email Alerts</div>
                        <div class="setting-desc">Receive admin notifications by email</div>
                    </div>
                    <button class="toggle <?php echo $settings['email_alerts'] ? 'on' : ''; ?>" data-key="email_alerts" onclick="toggleSetting(this)"></button>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label">New Listing Submitted</div>
                        <div class="setting-desc">Alert when a new listing is pending</div>
                    </div>
                    <button class="toggle <?php echo $settings['new_listing_alert'] ? 'on' : ''; ?>" data-key="new_listing_alert" onclick="toggleSetting(this)"></button>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label">Flagged Content Alert</div>
                        <div class="setting-desc">Alert on flagged reviews or users</div>
                    </div>
                    <button class="toggle <?php echo $settings['flagged_content_alert'] ? 'on' : ''; ?>" data-key="flagged_content_alert" onclick="toggleSetting(this)"></button>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label">Weekly Summary Email</div>
                        <div class="setting-desc">Receive weekly analytics digest</div>
                    </div>
                    <button class="toggle <?php echo $settings['weekly_summary'] ? 'on' : ''; ?>" data-key="weekly_summary" onclick="toggleSetting(this)"></button>
                </div>
            </div>

            <!-- ADMIN ACCOUNT -->
            <div class="settings-card">
                <h3><i data-lucide="user-cog" class="lucide" style="width:1rem;height:1rem;color:var(--gold);"></i> Admin Account</h3>

                <form id="accountForm" action="<?php echo BASE_URL; ?>/admin/settings_actions.php" method="POST">
                    <input type="hidden" name="action" value="update_account">

                    <div class="form-group" style="margin-bottom:14px;">
                        <label class="form-label">Display Name</label>
                        <input class="form-input" type="text" name="display_name" value="<?php echo htmlspecialchars($displayName); ?>" placeholder="Display Name" required>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label class="form-label">Email</label>
                        <input class="form-input" type="email" name="email" value="<?php echo htmlspecialchars($currentAdmin['email'] ?? ''); ?>" placeholder="Email" required>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:4px; flex-wrap:wrap;">
                        <button type="submit" class="btn-primary"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Changes</button>
                        <button type="button" class="btn-ghost" onclick="requestPasswordReset()"><i data-lucide="key-round" class="lucide" style="width:.85rem;height:.85rem;"></i> Change Password</button>
                    </div>
                </form>
            </div>

        </div>

        <!-- DANGER ZONE -->
        <div style="margin-top: 20px;" class="animate">
            <div class="settings-card danger">
                <h3><i data-lucide="triangle-alert" class="lucide" style="width:1rem;height:1rem;color:var(--gold);"></i> Danger Zone</h3>

                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label danger-label">Reset All Analytics</div>
                        <div class="setting-desc">Clears all visitor data and statistics. Irreversible.</div>
                    </div>
                    <form action="<?php echo BASE_URL; ?>/admin/settings_actions.php" method="POST" onsubmit="return confirm('Reset all analytics data? This cannot be undone.');">
                        <input type="hidden" name="action" value="reset_analytics">
                        <button type="submit" class="tbl-btn delete" style="padding:8px 16px;"><i data-lucide="rotate-ccw" class="lucide" style="width:.75rem;height:.75rem;"></i> Reset</button>
                    </form>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <div class="setting-label danger-label">Clear All User Sessions</div>
                        <div class="setting-desc">Logs out all currently active users.</div>
                    </div>
                    <form action="<?php echo BASE_URL; ?>/admin/settings_actions.php" method="POST" onsubmit="return confirm('Log out every currently active user?');">
                        <input type="hidden" name="action" value="clear_sessions">
                        <button type="submit" class="tbl-btn delete" style="padding:8px 16px;"><i data-lucide="x-circle" class="lucide" style="width:.75rem;height:.75rem;"></i> Clear</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/adminsettings.js"></script>
<script>initSidebarCollapse();</script>

</body>
</html>