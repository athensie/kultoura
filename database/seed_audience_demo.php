<?php
/*
 * Five years of DEMO audience analytics (see config/audience_demo.php).
 *
 *   Seed:    KULTOURA_ENABLE_AUDIENCE_DEMO=1 php database/seed_audience_demo.php
 *   Remove:  KULTOURA_ENABLE_AUDIENCE_DEMO=1 php database/seed_audience_demo.php --remove
 *
 * Super Admins can also load/remove it from the Analytics dashboard (Audience tab).
 * Demo numbers are invented. Remove them before using the dashboard for real decisions.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('KULTOURA_ENABLE_AUDIENCE_DEMO') !== '1') {
    fwrite(STDERR, "Set KULTOURA_ENABLE_AUDIENCE_DEMO=1 to seed (or --remove) demo audience analytics.\n");
    exit(1);
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/audience_demo.php';
$conn->query("SET time_zone = '+08:00'");

if (in_array('--remove', $argv, true)) {
    echo 'Removed demo rows: ' . json_encode(kt_audience_demo_remove($conn)) . PHP_EOL;
    exit(0);
}
try {
    $totals = kt_audience_demo_seed($conn, static function (string $year) { echo "Seeding {$year}…" . PHP_EOL; });
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
echo 'Seeded demo audience data: ' . json_encode($totals) . PHP_EOL;
echo 'Remove it any time with --remove.' . PHP_EOL;
