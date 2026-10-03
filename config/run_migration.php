<?php
/*
 |--------------------------------------------------------------------
 | ONE-TIME DATA MIGRATION RUNNER — delete this file (and
 | migration_data.sql) immediately after running it once.
 |--------------------------------------------------------------------
 | Runs inside the deployed app so it uses the same working DB
 | connection the live site already has (production's MySQL only
 | accepts connections from inside Railway's private network, and the
 | SSH tunnel used for direct outside access was unreachable).
 |
 | Plain INSERTs from migration_data.sql into the five content tables
 | — confirmed empty beforehand, so no delete/truncate of any kind is
 | needed or performed here. If those tables aren't actually empty
 | when this runs, the inserts will just fail loudly on a duplicate
 | primary key rather than overwrite or remove anything.
 |
 | Gated on a MIGRATION_TOKEN env var (set via `railway variables set`,
 | never committed) so a leaked URL before cleanup can't be used to
 | re-run it, and nothing secret lives in source control.
 */
require_once __DIR__ . '/dbmain.php';

$expectedToken = getenv('MIGRATION_TOKEN');
if (!$expectedToken || ($_GET['token'] ?? '') !== $expectedToken) {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

$sqlFile = __DIR__ . '/migration_data.sql';
if (!is_file($sqlFile)) {
    die("migration_data.sql not found.\n");
}

// Refuse to run against tables that already have data — this script
// only ever inserts, it never deletes, so a non-empty table here means
// stop and look rather than risk a duplicate-key error mid-batch.
foreach (['destination', 'products', 'restaurants', 'announcements', 'about_sections'] as $table) {
    $count = $conn->query("SELECT COUNT(*) c FROM `$table`")->fetch_assoc()['c'];
    if ($count > 0) {
        die("Aborting: `$table` already has $count row(s). This script only inserts and won't run against non-empty tables.\n");
    }
}

$sql = file_get_contents($sqlFile);
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->error) {
    echo "ERROR: " . $conn->error . "\n";
} else {
    echo "Migration statements executed without error.\n";
}

foreach (['destination', 'products', 'restaurants', 'announcements', 'about_sections'] as $table) {
    $r = $conn->query("SELECT COUNT(*) c FROM `$table`");
    echo "$table: " . $r->fetch_assoc()['c'] . " rows\n";
}

echo "\nDone. Delete config/run_migration.php and config/migration_data.sql now.\n";
