<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/password_policy.php';
if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'user') {
    header('Location: ../auth/login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];
$isLoggedIn = true;
$navUserName = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
$error = $_SESSION['profile_error'] ?? '';
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_error'], $_SESSION['profile_success']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_new_password'] ?? '');

        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $passwordHash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
        $stmt->close();

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Enter your current password and complete both new password fields.';
        } elseif ($passwordHash === '' || !password_verify($currentPassword, $passwordHash)) {
            $error = 'Your current password is incorrect.';
        } elseif (!kt_password_meets_policy($newPassword)) {
            $error = 'New password must be at least 8 characters long and include an uppercase letter and a special character.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The new passwords do not match.';
        } elseif (password_verify($newPassword, $passwordHash)) {
            $error = 'Choose a new password that is different from your current password.';
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $newPasswordHash, $userId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['profile_success'] = 'Your password has been changed.';
            header('Location: profile.php');
            exit;
        }

        // Keep the account form populated when password validation fails.
        $stmt = $conn->prepare('SELECT fullname, username, email, promotional_email FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $promotionalEmail = isset($_POST['promotional_email']) ? 1 : 0;
        if ($fullname === '' || mb_strlen($fullname) > 100) {
            $error = 'Enter your name (up to 100 characters).';
        } elseif (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $username)) {
            $error = 'Username must be 4–20 characters and use only letters, numbers, or underscores.';
        } elseif (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address (up to 100 characters).';
        } else {
            try {
                $stmt = $conn->prepare(
                    'UPDATE users SET fullname = ?, username = ?, email = ?, promotional_email = ? WHERE id = ?'
                );
                $stmt->bind_param('sssii', $fullname, $username, $email, $promotionalEmail, $userId);
                $stmt->execute();
                $stmt->close();
                $_SESSION['username'] = $username;
                $_SESSION['user_name'] = $fullname;
                $_SESSION['profile_success'] = 'Your profile has been updated.';
                header('Location: profile.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                $error = $e->getCode() === 1062
                    ? 'That username or email address is already in use.'
                    : 'We could not save your profile. Please try again.';
            }
        }
        $profile = [
            'fullname' => $fullname,
            'username' => $username,
            'email' => $email,
            'promotional_email' => $promotionalEmail,
        ];
    }
} else {
    $stmt = $conn->prepare('SELECT fullname, username, email, promotional_email FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$profile) {
        $_SESSION = [];
        header('Location: ../auth/login.php');
        exit;
    }
}
function profile_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
$nameParts = preg_split('/\s+/u', trim((string) ($profile['fullname'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$profileInitials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $profileInitials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$profileInitials = $profileInitials !== '' ? $profileInitials : 'KT';
$stats = ['visits' => 0, 'places' => 0, 'favorites' => 0, 'photos' => 0, 'streak' => 0];
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS visits, COUNT(DISTINCT CONCAT(item_type, ':', item_id)) AS places
     FROM diary_entries WHERE user_id = ?"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$visitStats = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$stats['visits'] = (int) ($visitStats['visits'] ?? 0);
$stats['places'] = (int) ($visitStats['places'] ?? 0);
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM favorites WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats['favorites'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();
$stmt = $conn->prepare(
    'SELECT COUNT(*) AS total FROM diary_photos p
     INNER JOIN diary_entries d ON d.entry_id = p.entry_id WHERE d.user_id = ?'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats['photos'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();
$stmt = $conn->prepare('SELECT DISTINCT visited_date FROM diary_entries WHERE user_id = ? ORDER BY visited_date ASC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$dateRows = $stmt->get_result();
$stmt->close();
$currentStreak = 0;
$previousDate = null;
while ($dateRow = $dateRows->fetch_assoc()) {
    $date = new DateTimeImmutable($dateRow['visited_date']);
    $currentStreak = $previousDate !== null && $previousDate->diff($date)->days === 1
        ? $currentStreak + 1
        : 1;
    $stats['streak'] = max($stats['streak'], $currentStreak);
    $previousDate = $date;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – KULTOURA</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/search.css">
    <link rel="stylesheet" href="../assets/css/tourism.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
    <header class="navbar navbar-solid">
        <div class="user-greeting-left user-greeting-name">
            <span class="navbar-logo-icon navbar-logo-icon-salakot"><img src="../assets/images/salakot.png?v=20261008" alt=""></span>
            <span class="navbar-greeting-text">Mabuhay, <?php echo $navUserName; ?></span>
        </div>
        <nav class="nav-links">
            <a href="/kultoura/index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
            <div class="dropdown">
                <a href="tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR ▾</span></a>
                <div class="mega-menu">
                    <div class="mega-column"><h4>Local Products</h4><a href="tourism/products.php">Products</a></div>
                    <div class="mega-column"><h4>Local Destinations</h4><a href="tourism/nature.php">Nature</a><a href="tourism/industry.php">Industry Zone</a><a href="tourism/resort.php">Resort</a><a href="tourism/churches.php">Churches</a></div>
                    <div class="mega-column"><h4>Culture &amp; Services</h4><a href="tourism/fiestas.php">Fiestas</a><a href="tourism/people.php">People of Malvar</a><a href="tourism/services.php">Other Services</a></div>
                </div>
            </div>
            <a href="tourism/restaurants.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg></span><span>RESTAURANTS</span></a>
            <a href="tourism/accommodation.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16M22 12v8M2 12h20M2 8h6a2 2 0 0 1 2 2v2M22 8h-6a2 2 0 0 0-2 2v2"/></svg></span><span>ACCOMMODATION</span></a>
            <a href="tourism/banks.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M4 10v11M20 10v11M2 10h20L12 4zM8 14v4M12 14v4M16 14v4"/></svg></span><span>BANKS</span></a>
            <div class="dropdown">
                <a href="#" class="nav-item nav-active"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg></span><span>MORE ▾</span></a>
                <div class="mega-menu mega-menu-simple"><div class="mega-column"><a href="foryou.php">For You</a><a href="traveldiary.php">Travel Diary</a><a href="profile.php" class="nav-active">My Profile</a><a href="favorites.php">Favorites</a><a href="mostpopular.php">Most Popular</a><a href="about.php">About</a></div></div>
            </div>
            <a href="../auth/logout.php" class="nav-item nav-signout-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg></span><span>SIGN OUT</span></a>
        </nav>
        <div class="navbar-search" data-api="search_api.php" data-results="search.php">
            <button type="button" class="navbar-search-toggle" aria-label="Search" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></button>
            <form class="navbar-search-form" action="search.php" method="GET"><input type="search" name="q" class="navbar-search-input" placeholder="Search KulToura…" autocomplete="off"></form>
            <div class="navbar-search-dropdown"></div>
        </div>
        <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false"><span></span><span></span><span></span></button>
    </header>
    <main class="profile-main">
        <section class="profile-card">
            <aside class="profile-summary">
                <div class="profile-avatar" aria-label="Profile initials"><?php echo profile_escape($profileInitials); ?></div>
                <h2><?php echo profile_escape((string) ($profile['fullname'] ?? '')); ?></h2>
                <p class="profile-username">@<?php echo profile_escape((string) ($profile['username'] ?? '')); ?></p>
                <p class="profile-email"><span class="profile-email-icon" aria-hidden="true">✉</span><?php echo profile_escape((string) ($profile['email'] ?? '')); ?></p>
                <div class="profile-stats-heading">
                    <p class="profile-eyebrow">YOUR TRAVEL AT A GLANCE</p>
                    <span>Stats from your Travel Diary</span>
                </div>
                <div class="profile-stats">
                    <div class="profile-stat"><strong><?php echo number_format($stats['places']); ?></strong><span>Places explored</span></div>
                    <div class="profile-stat"><strong><?php echo number_format($stats['visits']); ?></strong><span>Visits logged</span></div>
                    <div class="profile-stat"><strong><?php echo number_format($stats['favorites']); ?></strong><span>Favorites</span></div>
                    <div class="profile-stat"><strong><?php echo number_format($stats['photos']); ?></strong><span>Travel photos</span></div>
                    <div class="profile-stat profile-stat-streak"><strong><?php echo number_format($stats['streak']); ?> <small><?php echo $stats['streak'] === 1 ? 'day' : 'days'; ?></small></strong><span>Longest visit streak</span></div>
                </div>
                <div class="profile-summary-links" aria-label="Quick links">
                    <a class="profile-shortcut" href="traveldiary.php" aria-label="Open Travel Diary" title="Open Travel Diary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H20v17H7.5A2.5 2.5 0 0 0 5 21.5z"/><path d="M5 4.5v17M9 7h7M9 11h7"/></svg>
                    </a>
                    <a class="profile-shortcut" href="favorites.php" aria-label="View favorites" title="View favorites">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8z"/></svg>
                    </a>
                </div>
            </aside>
            <div class="profile-edit">
            <p class="profile-eyebrow">YOUR ACCOUNT</p>
            <h1>Edit your profile</h1>
            <p class="profile-intro">Keep your account details up to date.</p>
            <?php if ($error !== ''): ?><div class="profile-alert profile-alert-error" role="alert"><?php echo profile_escape($error); ?></div><?php endif; ?>
            <?php if ($success !== ''): ?><div class="profile-alert profile-alert-success" role="status"><?php echo profile_escape($success); ?></div><?php endif; ?>
            <form method="post" action="profile.php" class="profile-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">
                <label>
                    <span>Full name</span>
                    <input type="text" name="fullname" maxlength="100" autocomplete="name" required value="<?php echo profile_escape((string) ($profile['fullname'] ?? '')); ?>">
                </label>
                <label>
                    <span>Username</span>
                    <input type="text" name="username" minlength="4" maxlength="20" pattern="[A-Za-z0-9_]{4,20}" autocomplete="username" required value="<?php echo profile_escape((string) ($profile['username'] ?? '')); ?>">
                    <small>4–20 letters, numbers, or underscores.</small>
                </label>
                <label>
                    <span>Email address</span>
                    <input type="email" name="email" maxlength="100" autocomplete="email" required value="<?php echo profile_escape((string) ($profile['email'] ?? '')); ?>">
                </label>
                <label class="profile-check">
                    <input type="checkbox" name="promotional_email" value="1" <?php echo !empty($profile['promotional_email']) ? 'checked' : ''; ?>>
                    <span>Send me KULTOURA news and promotional emails.</span>
                </label>
                <button type="submit">Save changes</button>
            </form>
            <section class="profile-password-section" aria-labelledby="password-heading">
                <p class="profile-eyebrow">ACCOUNT SECURITY</p>
                <h2 id="password-heading">Change your password</h2>
                <p class="profile-intro">Enter your current password, then choose a new one.</p>
                <form method="post" action="profile.php" class="profile-form profile-password-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <label>
                        <span>Current password</span>
                        <input type="password" name="current_password" autocomplete="current-password" required>
                    </label>
                    <label>
                        <span>New password</span>
                        <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                        <small>At least 8 characters, with an uppercase letter and a special character.</small>
                    </label>
                    <label>
                        <span>Confirm new password</span>
                        <input type="password" name="confirm_new_password" minlength="8" autocomplete="new-password" required>
                    </label>
                    <button type="submit">Update password</button>
                </form>
            </section>
            </div>
        </section>
    </main>
    <script src="../assets/js/navbar.js"></script>
    <script src="../assets/js/navbar-search.js"></script>
</body>
</html>
