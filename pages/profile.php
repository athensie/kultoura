<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'user') {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$error = $_SESSION['profile_error'] ?? '';
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_error'], $_SESSION['profile_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

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

    // Keep submitted values visible when validation fails.
    $profile = [
        'fullname' => $fullname,
        'username' => $username,
        'email' => $email,
        'promotional_email' => $promotionalEmail,
    ];
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
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
    <header class="profile-header">
        <a href="../index.php" class="profile-brand">KULTOURA</a>
        <a href="../index.php" class="profile-back">Back to exploring</a>
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
                <div class="profile-summary-links">
                    <a href="traveldiary.php">Open Travel Diary <span aria-hidden="true">→</span></a>
                    <a href="favorites.php">View favorites <span aria-hidden="true">→</span></a>
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
            </div>
        </section>
    </main>
</body>
</html>
