<?php
/*
 * Admin action: load or remove the five-year demo audience analytics
 * (see config/audience_demo.php). Demo rows are tagged and removed exactly; real visits are never touched.
 */
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/admin_requests.php';
$back = BASE_URL . '/admin/admindashboard.php?aw_tab=audience';
if (!in_array(strtolower((string) ($_SESSION['role'] ?? '')), ['admin', 'super admin'], true)) {
    header('Location: ' . $back . '&demo=error&demo_message=' . rawurlencode('Only admins can load or remove demo data.'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit;
}
csrf_verify();
date_default_timezone_set('Asia/Manila');
@$conn->query("SET time_zone = '+08:00'");
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/audience_demo.php';
session_write_close();   // don't lock the admin's session while seeding
ignore_user_abort(true);
$action = (string) ($_POST['action'] ?? '');
try {
    if ($action === 'load') {
        $totals = kt_audience_demo_seed($conn);
        error_log('Demo audience data loaded by admin ' . (int) $_SESSION['user_id'] . ': ' . json_encode($totals));
        header('Location: ' . $back . '&demo=loaded');
    } elseif ($action === 'remove') {
        $removed = kt_audience_demo_remove($conn);
        error_log('Demo audience data removed by admin ' . (int) $_SESSION['user_id'] . ': ' . json_encode($removed));
        header('Location: ' . $back . '&demo=removed');
    } else {
        header('Location: ' . $back . '&demo=error&demo_message=' . rawurlencode('Unknown demo data action.'));
    }
} catch (Throwable $error) {
    error_log('Demo audience data action failed: ' . $error->getMessage());
    $message = $error instanceof RuntimeException ? $error->getMessage() : 'The demo data action failed. Check the server log for details.';
    header('Location: ' . $back . '&demo=error&demo_message=' . rawurlencode($message));
}
exit;
