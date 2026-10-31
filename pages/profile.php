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
        </section>
    </main>
</body>
</html>
