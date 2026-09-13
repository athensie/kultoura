<?php
session_start();

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 */
define('BASE_URL', '/kultoura');

/*
 |--------------------------------------------------------------------
 | DATABASE
 |--------------------------------------------------------------------
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
 | EDIT / DELETE (handled right here — no separate actions file)
 |--------------------------------------------------------------------
 | Every account row is tagged with 'source' => 'admin' or 'user', so
 | we know which table to write back to.
 */
$flashMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';
    $source     = $_POST['source'] ?? '';
    $id         = (int) ($_POST['id'] ?? 0);

    if ($formAction === 'delete' && $id > 0) {
        if ($source === 'admin') {
            $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
        } elseif ($source === 'user') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        } else {
            $stmt = null;
        }
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $flashMessage = 'Account deleted.';
        }
    }

    if ($formAction === 'edit' && $id > 0) {
        if ($source === 'admin') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name'] ?? '');
            $username  = trim($_POST['username'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $adminRoleField = trim($_POST['admin_role'] ?? 'Admin');

            if ($firstName !== '' && $username !== '' && $email !== '') {
                $stmt = $conn->prepare(
                    "UPDATE admins SET first_name=?, last_name=?, username=?, email=?, role=? WHERE admin_id=?"
                );
                $stmt->bind_param('sssssi', $firstName, $lastName, $username, $email, $adminRoleField, $id);
                $stmt->execute();
                $stmt->close();
                $flashMessage = 'Admin account updated.';
            } else {
                $flashMessage = 'Could not update — missing required fields.';
            }
        } elseif ($source === 'user') {
            $fullname = trim($_POST['fullname'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $promo    = ((int) ($_POST['promotional_email'] ?? 0)) === 1 ? 1 : 0;

            if ($fullname !== '' && $username !== '' && $email !== '') {
                $stmt = $conn->prepare(
                    "UPDATE users SET fullname=?, username=?, email=?, promotional_email=? WHERE id=?"
                );
                $stmt->bind_param('sssii', $fullname, $username, $email, $promo, $id);
                $stmt->execute();
                $stmt->close();
                $flashMessage = 'User account updated.';
            } else {
                $flashMessage = 'Could not update — missing required fields.';
            }
        }
    }
}

/*
 |--------------------------------------------------------------------
 | ACCOUNTS DATA (Users + Admins combined)
 |--------------------------------------------------------------------
 | Two separate, independent queries — one per table. Each row keeps
 | its own role straight from its own table (no cross-referencing by
 | email). "Active" means last_activity was within the last 5 minutes.
 */
$accounts = [];
$onlineThreshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));

// ---- Admin accounts ----
$adminResult = $conn->query("SELECT admin_id, first_name, last_name, username, email, role, last_activity, created_at FROM admins ORDER BY created_at DESC");
if ($adminResult) {
    foreach ($adminResult->fetch_all(MYSQLI_ASSOC) as $a) {
        $normalizedRole = strtolower(trim($a['role'] ?? ''));
        if ($normalizedRole === 'super admin') {
            $roleLabel = 'Super Admin';
            $roleClass = 'super-admin';
        } else {
            $roleLabel = 'Admin';
            $roleClass = 'admin';
        }

        $isOnline = !empty($a['last_activity']) && $a['last_activity'] >= $onlineThreshold;

        $accounts[] = [
            'source'      => 'admin',
            'id'          => (int) $a['admin_id'],
            'name'        => trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
            'firstName'   => $a['first_name'],
            'lastName'    => $a['last_name'],
            'username'    => $a['username'],
            'email'       => $a['email'],
            'role'        => $roleLabel,
            'roleClass'   => $roleClass,
            'promo'       => null,
            'online'      => $isOnline,
            'created_at'  => $a['created_at'],
        ];
    }
}

// ---- Regular user accounts ----
$userResult = $conn->query("SELECT id, fullname, username, email, promotional_email, last_activity, created_at FROM users ORDER BY created_at DESC");
if ($userResult) {
    foreach ($userResult->fetch_all(MYSQLI_ASSOC) as $u) {
        $isOnline = !empty($u['last_activity']) && $u['last_activity'] >= $onlineThreshold;

        $accounts[] = [
            'source'      => 'user',
            'id'          => (int) $u['id'],
            'name'        => $u['fullname'],
            'firstName'   => null,
            'lastName'    => null,
            'username'    => $u['username'],
            'email'       => $u['email'],
            'role'        => 'User',
            'roleClass'   => 'user',
            'promo'       => (int) $u['promotional_email'] === 1,
            'online'      => $isOnline,
            'created_at'  => $u['created_at'],
        ];
    }
}

usort($accounts, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

$totalAccounts    = count($accounts);
$totalAdmins      = count(array_filter($accounts, fn($acc) => $acc['source'] === 'admin'));
$totalUsers       = count(array_filter($accounts, fn($acc) => $acc['source'] === 'user'));
$subscribedCount  = count(array_filter($accounts, fn($acc) => $acc['promo'] === true));
$onlineCount      = count(array_filter($accounts, fn($acc) => $acc['online']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users – KULTOURA Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500&family=Bebas+Neue&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.462.0/dist/umd/lucide.min.js"></script>

    <link rel="stylesheet" href="../assets/css/adminusers.css">
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
        <li><a href="<?php echo BASE_URL; ?>/admin/adminusers.php" class="active"><span class="nav-icon"><i data-lucide="users" class="lucide"></i></span> Users</a></li>
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
                <div class="section-label">User Management</div>
                <h1>Registered <em>Accounts</em></h1>
                <p>Everyone with an account on KulToura — visitors and admins alike.</p>
            </div>
        </div>

        <!-- KPI ROW -->
        <div class="mini-kpi-row animate" style="grid-template-columns: repeat(5, 1fr); max-width: 900px;">
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $totalAccounts); ?></div>
                <div class="mini-kpi-label">Total Accounts</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $totalUsers); ?></div>
                <div class="mini-kpi-label">Users</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $totalAdmins); ?></div>
                <div class="mini-kpi-label">Admins</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $onlineCount); ?></div>
                <div class="mini-kpi-label">Active Now</div>
            </div>
            <div class="mini-kpi">
                <div class="mini-kpi-num"><?php echo htmlspecialchars((string) $subscribedCount); ?></div>
                <div class="mini-kpi-label">Subscribed to Emails</div>
            </div>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row animate">
            <input class="search-input" type="text" placeholder="Search by name, username, or email…" oninput="filterUsers(this.value)">
            <select class="filter-select" id="roleFilter" onchange="filterByRole(this.value)">
                <option value="all">All Roles</option>
                <option value="user">User</option>
                <option value="admin">Admin</option>
                <option value="super-admin">Super Admin</option>
            </select>
            <select class="filter-select" id="statusFilter" onchange="filterByOnlineStatus(this.value)">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="offline">Offline</option>
            </select>
        </div>

        <!-- ACCOUNTS TABLE -->
        <div class="data-table-wrap animate">
            <?php if (empty($accounts)): ?>
                <div class="chart-empty">
                    <i data-lucide="users" class="lucide"></i>
                    <div class="chart-empty-title">No accounts yet</div>
                    <div class="chart-empty-sub">Registered accounts will show up here once people start signing up.</div>
                </div>
            <?php else: ?>
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Promo Emails</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                            <tr data-role="<?php echo $acc['roleClass']; ?>" data-online="<?php echo $acc['online'] ? 'active' : 'offline'; ?>">
                                <td>
                                    <div class="user-cell">
                                        <div class="user-mini-avatar" style="background:linear-gradient(135deg,#C9572A,#E8A842)"><i data-lucide="user" class="lucide" style="width:.9rem;height:.9rem;"></i></div>
                                        <div>
                                            <div><?php echo htmlspecialchars($acc['name']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($acc['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($acc['username']); ?></td>
                                <td><span class="role-badge <?php echo $acc['roleClass']; ?>"><?php echo htmlspecialchars($acc['role']); ?></span></td>
                                <td><span class="status <?php echo $acc['online'] ? 'active' : 'inactive'; ?>"><?php echo $acc['online'] ? 'Active' : 'Offline'; ?></span></td>
                                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($acc['created_at']))); ?></td>
                                <td><?php echo $acc['promo'] === null ? '—' : ($acc['promo'] ? 'Yes' : 'No'); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="tbl-btn view"
                                            data-source="<?php echo $acc['source']; ?>"
                                            data-id="<?php echo $acc['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($acc['name'], ENT_QUOTES); ?>"
                                            data-firstname="<?php echo htmlspecialchars($acc['firstName'] ?? '', ENT_QUOTES); ?>"
                                            data-lastname="<?php echo htmlspecialchars($acc['lastName'] ?? '', ENT_QUOTES); ?>"
                                            data-username="<?php echo htmlspecialchars($acc['username'], ENT_QUOTES); ?>"
                                            data-email="<?php echo htmlspecialchars($acc['email'], ENT_QUOTES); ?>"
                                            data-role="<?php echo htmlspecialchars($acc['role'], ENT_QUOTES); ?>"
                                            data-online="<?php echo $acc['online'] ? 'Active' : 'Offline'; ?>"
                                            data-joined="<?php echo htmlspecialchars(date('M j, Y', strtotime($acc['created_at'])), ENT_QUOTES); ?>"
                                            data-promo="<?php echo $acc['promo'] === null ? '' : (int) $acc['promo']; ?>"
                                            onclick="openViewAccount(this)"><i data-lucide="eye" class="lucide" style="width:.75rem;height:.75rem;"></i> View</button>
                                        <button class="tbl-btn edit"
                                            data-source="<?php echo $acc['source']; ?>"
                                            data-id="<?php echo $acc['id']; ?>"
                                            data-firstname="<?php echo htmlspecialchars($acc['firstName'] ?? '', ENT_QUOTES); ?>"
                                            data-lastname="<?php echo htmlspecialchars($acc['lastName'] ?? '', ENT_QUOTES); ?>"
                                            data-fullname="<?php echo htmlspecialchars($acc['name'], ENT_QUOTES); ?>"
                                            data-username="<?php echo htmlspecialchars($acc['username'], ENT_QUOTES); ?>"
                                            data-email="<?php echo htmlspecialchars($acc['email'], ENT_QUOTES); ?>"
                                            data-adminrole="<?php echo $acc['source'] === 'admin' ? htmlspecialchars($acc['role'], ENT_QUOTES) : ''; ?>"
                                            data-promo="<?php echo $acc['promo'] === null ? '' : (int) $acc['promo']; ?>"
                                            onclick="openEditAccount(this)"><i data-lucide="pencil" class="lucide" style="width:.75rem;height:.75rem;"></i> Edit</button>
                                        <button class="tbl-btn delete"
                                            data-source="<?php echo $acc['source']; ?>"
                                            data-id="<?php echo $acc['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($acc['name'], ENT_QUOTES); ?>"
                                            onclick="confirmDeleteAccount(this)"><i data-lucide="trash-2" class="lucide" style="width:.75rem;height:.75rem;"></i> Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="pagination" id="usersPagination"></div>

    </div>
</div>

<!-- VIEW ACCOUNT MODAL -->
<div class="modal-overlay" id="viewAccountModal" onclick="closeModalOutside(event, 'viewAccountModal')">
    <div class="modal-card" style="max-width:480px;">
        <button class="modal-close" onclick="closeModal('viewAccountModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title" style="text-align:center;" id="vaName">—</div>
        <div style="text-align:center;font-size:.78rem;color:rgba(245,237,216,.4);margin-bottom:14px;" id="vaEmail">—</div>
        <div style="display:flex;justify-content:center;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
            <span class="role-badge" id="vaRole">—</span>
            <span class="status active" id="vaOnline">—</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;">
            <div style="background:rgba(255,255,255,.04);border-radius:8px;padding:12px;">
                <div style="font-size:.6rem;color:rgba(245,237,216,.3);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Username</div>
                <div style="font-size:.85rem;color:var(--cream);" id="vaUsername">—</div>
            </div>
            <div style="background:rgba(255,255,255,.04);border-radius:8px;padding:12px;">
                <div style="font-size:.6rem;color:rgba(245,237,216,.3);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Joined</div>
                <div style="font-size:.85rem;color:var(--cream);" id="vaJoined">—</div>
            </div>
        </div>
        <div style="background:rgba(255,255,255,.04);border-radius:8px;padding:12px;margin-bottom:18px;" id="vaPromoWrap">
            <div style="font-size:.6rem;color:rgba(245,237,216,.3);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Promotional Emails</div>
            <div style="font-size:.85rem;color:var(--cream);" id="vaPromo">—</div>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn-primary" style="flex:1;" id="vaEditBtn"><i data-lucide="pencil" class="lucide" style="width:.85rem;height:.85rem;"></i> Edit Account</button>
            <button class="btn-ghost" onclick="closeModal('viewAccountModal')">Close</button>
        </div>
    </div>
</div>

<!-- EDIT ACCOUNT MODAL -->
<div class="modal-overlay" id="editAccountModal" onclick="closeModalOutside(event, 'editAccountModal')">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal('editAccountModal')"><i data-lucide="x" class="lucide"></i></button>
        <div class="modal-title">Edit Account</div>
        <div class="modal-sub" id="editAccountSubtitle">Editing account</div>
        <form id="editAccountForm" method="POST">
            <input type="hidden" name="form_action" value="edit">
            <input type="hidden" name="source" id="editSource" value="">
            <input type="hidden" name="id" id="editId" value="">

            <!-- ADMIN FIELDS -->
            <div id="editAdminFields" style="display:none;">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input class="form-input" type="text" name="first_name" id="editFirstName" placeholder="First name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input class="form-input" type="text" name="last_name" id="editLastName" placeholder="Last name">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select class="filter-select" name="admin_role" id="editAdminRole" style="width:100%; padding:11px 14px; border-radius:8px;">
                        <option value="Admin">Admin</option>
                        <option value="Super Admin">Super Admin</option>
                    </select>
                </div>
            </div>

            <!-- USER FIELDS -->
            <div id="editUserFields" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input class="form-input" type="text" name="fullname" id="editFullname" placeholder="Full name">
                </div>
                <div class="form-group">
                    <label class="form-label">Promotional Emails</label>
                    <select class="filter-select" name="promotional_email" id="editPromo" style="width:100%; padding:11px 14px; border-radius:8px;">
                        <option value="1">Subscribed</option>
                        <option value="0">Not Subscribed</option>
                    </select>
                </div>
            </div>

            <!-- SHARED FIELDS -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input class="form-input" type="text" name="username" id="editUsername" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-input" type="email" name="email" id="editEmail" placeholder="Email address" required>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="submit" class="btn-primary" style="flex:1"><i data-lucide="check" class="lucide" style="width:.85rem;height:.85rem;"></i> Save Changes</button>
                <button type="button" class="btn-ghost" onclick="closeModal('editAccountModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal" onclick="closeModalOutside(event, 'deleteModal')">
    <div class="modal-card" style="max-width:380px; text-align:center;">
        <div class="modal-title" id="deleteTitle">Delete Account?</div>
        <div class="modal-sub">This action cannot be undone.</div>
        <form id="deleteAccountForm" method="POST">
            <input type="hidden" name="form_action" value="delete">
            <input type="hidden" name="source" id="deleteSource" value="">
            <input type="hidden" name="id" id="deleteId" value="">
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="tbl-btn delete" style="flex:1; padding:12px;">Yes, Delete</button>
                <button type="button" class="btn-ghost" style="flex:1;" onclick="closeModal('deleteModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script src="../assets/js/admin-sidebar-collapse.js"></script>
<script src="../assets/js/adminusers.js"></script>
<script>initSidebarCollapse();</script>
<?php if ($flashMessage): ?>
<script>
document.addEventListener('DOMContentLoaded', function () { showToast(<?php echo json_encode($flashMessage); ?>); });
</script>
<?php endif; ?>
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
</script>

</body>
</html>