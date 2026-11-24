<?php
/*
 * Admin action: clean every listing address into "Street, Brgy. X, Malvar, Batangas"
 * (config/malvar_address.php). Missing street/barangay are looked up from each listing's map pin.
 * The previous text is kept in address_before_cleanup.
 */
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super admin'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/malvar_address.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/adminsettings.php');
    exit;
}
csrf_verify();
$adminId = (int) $_SESSION['user_id'];
session_write_close();   // don't lock the admin's session during map lookups
ignore_user_abort(true);
@set_time_limit(600);
try {
    $counts = kt_fix_listing_addresses($conn, true);
    error_log('Listing addresses cleaned by admin ' . $adminId . ': ' . json_encode($counts));
    $message = 'Addresses cleaned: ' . $counts['changed'] . ' of ' . $counts['checked'] . ' listings updated'
        . ($counts['looked_up'] ? ', ' . $counts['looked_up'] . ' looked up from map pins' : '') . '.'
        . ($counts['still_missing_barangay'] ? ' ' . $counts['still_missing_barangay'] . ' still need a barangay — open them and re-pick the map pin.' : ' Every listing now has a barangay.');
} catch (Throwable $error) {
    error_log('Listing address clean-up failed: ' . $error->getMessage());
    $message = 'The address clean-up could not finish. Please try again.';
}
session_start();
$_SESSION['flash_message'] = $message;
header('Location: ' . BASE_URL . '/admin/adminsettings.php');
exit;
