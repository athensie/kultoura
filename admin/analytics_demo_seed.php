<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'super admin'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/admindashboard.php');
    exit;
}
csrf_verify();
$demoSource = 'demo-seed.kultoura.local';
$mode = $_POST['mode'] ?? 'load';
if ($mode === 'remove') {
    $stmt = $conn->prepare('DELETE FROM page_views WHERE referrer_host = ?');
    if ($stmt) {
        $stmt->bind_param('s', $demoSource);
        $stmt->execute();
        $removed = $stmt->affected_rows;
        $stmt->close();
        $_SESSION['flash_message'] = number_format($removed) . ' labeled sample page views removed. Real visitor data was left unchanged.';
    } else {
        $_SESSION['flash_message'] = 'Could not remove the sample page views.';
    }
    header('Location: ' . BASE_URL . '/admin/admindashboard.php');
    exit;
}
$existing = $conn->prepare('SELECT COUNT(*) AS total FROM page_views WHERE referrer_host = ?');
$existing->bind_param('s', $demoSource);
$existing->execute();
$demoCount = (int) ($existing->get_result()->fetch_assoc()['total'] ?? 0);
$existing->close();
if ($demoCount > 0) {
    $_SESSION['flash_message'] = 'Sample history is already loaded. Remove it before loading it again.';
    header('Location: ' . BASE_URL . '/admin/admindashboard.php');
    exit;
}
$todayResult = $conn->query('SELECT CURDATE() AS today');
$today = new DateTimeImmutable($todayResult->fetch_assoc()['today'] ?? 'today');
$pages = ['home', 'tourism', 'foryou', 'traveldiary', 'restaurants'];
$stmt = $conn->prepare('INSERT INTO page_views (page, referrer_host, viewed_at) VALUES (?, ?, ?)');
if (!$stmt) {
    $_SESSION['flash_message'] = 'Unable to prepare demo analytics data.';
    header('Location: ' . BASE_URL . '/admin/admindashboard.php');
    exit;
}
$conn->begin_transaction();
try {
    for ($day = 0; $day < 90; $day++) {
        $date = $today->modify('-' . (89 - $day) . ' days');
        $dayOfYear = (int) $date->format('z') + 1;
        foreach ($pages as $pageIndex => $page) {
            $viewCount = 5 + (($dayOfYear * 13 + ($pageIndex + 1) * 7) % 9);
            for ($visit = 1; $visit <= $viewCount; $visit++) {
                $seconds = ($dayOfYear * 997 + $visit * 3109 + ($pageIndex + 1) * 721) % 86400;
                $viewedAt = $date->setTime(0, 0)->modify('+' . $seconds . ' seconds')->format('Y-m-d H:i:s');
                $stmt->bind_param('sss', $page, $demoSource, $viewedAt);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Could not insert sample analytics rows.');
                }
            }
        }
    }
    $conn->commit();
    $_SESSION['flash_message'] = 'Sample history was loaded. Labeled demo visits are included in analytics until removed.';
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Analytics demo seed failed: ' . $error->getMessage());
    $_SESSION['flash_message'] = 'Could not load sample history. Please try again or check the server logs.';
}
$stmt->close();
header('Location: ' . BASE_URL . '/admin/admindashboard.php?period=year');
exit;
