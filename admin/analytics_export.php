<?php
require_once __DIR__ . '/../config/session_boot.php';
$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super admin'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/activity.php';
date_default_timezone_set('Asia/Manila');
@$conn->query("SET time_zone = '+08:00'");

function kt_export_stmt_rows(mysqli $conn, string $sql, string $types = '', array $values = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types !== '') {
        $args = [$types];
        foreach ($values as $i => &$value) $args[] = &$value;
        call_user_func_array([$stmt, 'bind_param'], $args);
    }
    if (!$stmt->execute()) { $stmt->close(); return []; }
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function kt_export_activity_label(string $type, array $row): string
{
    if ($type === 'view') return 'Viewed ' . (!empty($row['item_type']) ? 'destination or content' : 'page');
    if ($type === 'click' && str_contains(strtolower((string) ($row['page_section'] ?? '')), 'recommend')) return 'Clicked recommendation';
    $labels = ['search' => 'Searched', 'generate' => 'Generated itinerary', 'review' => 'Submitted review', 'rating' => 'Submitted rating', 'comment' => 'Submitted comment', 'map_interaction' => 'Opened map', 'recommendation' => 'Clicked recommendation', 'download' => 'Downloaded or saved', 'language' => 'Changed language'];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function kt_export_xlsx_cell(string $ref, $value, int $style = 0): string
{
    $styleAttr = $style ? ' s="' . $style . '"' : '';
    if (is_int($value) || is_float($value)) {
        if (!is_finite((float) $value)) $value = 0;
        return '<c r="' . $ref . '"' . $styleAttr . '><v>' . (string) $value . '</v></c>';
    }
    if ($value === null || $value === '') return '<c r="' . $ref . '"' . $styleAttr . '/>';
    $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    return '<c r="' . $ref . '" t="inlineStr"' . $styleAttr . '><is><t xml:space="preserve">' . $text . '</t></is></c>';
}

function kt_export_xlsx_column(int $index): string
{
    $name = '';
    while ($index > 0) { $index--; $name = chr(65 + ($index % 26)) . $name; $index = intdiv($index, 26); }
    return $name;
}

function kt_export_xlsx_sheet(array $rows, array $widths = [], int $headerRow = 0): string
{
    $xmlRows = '';
    $maxColumns = 1;
    foreach ($rows as $ri => $row) {
        $values = $row['values'] ?? $row;
        $kind = $row['kind'] ?? '';
        $rowNum = $ri + 1;
        $maxColumns = max($maxColumns, count($values));
        $xmlRows .= '<row r="' . $rowNum . '">';
        foreach ($values as $ci => $value) {
            $style = $kind === 'title' ? 1 : ($kind === 'subtitle' ? 2 : ($kind === 'section' ? 3 : (($kind === 'header' || $rowNum === $headerRow) ? 4 : 0)));
            $xmlRows .= kt_export_xlsx_cell(kt_export_xlsx_column($ci + 1) . $rowNum, $value, $style);
        }
        $xmlRows .= '</row>';
    }
    $cols = '';
    if ($widths) {
        $cols .= '<cols>';
        foreach ($widths as $i => $width) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $width . '" customWidth="1"/>';
        $cols .= '</cols>';
    }
    $pane = $headerRow > 0 ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '';
    $filter = $headerRow > 0 && count($rows) > $headerRow ? '<autoFilter ref="A' . $headerRow . ':' . kt_export_xlsx_column($maxColumns) . count($rows) . '"/>' : '';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . $pane . $cols . '<sheetData>' . $xmlRows . '</sheetData>' . $filter . '</worksheet>';
}

function kt_export_zip_add($handle, string $name, string $contents, array &$directory, int &$offset): void
{
    $compressed = gzdeflate($contents, 6);
    if ($compressed === false) throw new RuntimeException('Unable to compress an Excel workbook part.');
    $nameLength = strlen($name);
    $compressedLength = strlen($compressed);
    $contentLength = strlen($contents);
    $checksum = (int) sprintf('%u', crc32($contents));
    $now = getdate();
    $dosTime = (($now['hours'] & 31) << 11) | (($now['minutes'] & 63) << 5) | ((int) ($now['seconds'] / 2));
    $dosDate = ((max(1980, $now['year']) - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];
    $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, $dosTime, $dosDate, $checksum, $compressedLength, $contentLength, $nameLength, 0) . $name . $compressed;
    if (fwrite($handle, $local) !== strlen($local)) throw new RuntimeException('Unable to write an Excel workbook part.');
    $directory[] = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate, $checksum, $compressedLength, $contentLength, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
    $offset += strlen($local);
}

function kt_export_write_xlsx(array $sheets): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'kultoura-report-');
    $handle = $tmp !== false ? fopen($tmp, 'wb') : false;
    if ($tmp === false || $handle === false) {
        http_response_code(500);
        exit('Unable to create the analytics workbook.');
    }
    $directory = [];
    $offset = 0;
    $count = count($sheets);
    $types = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    for ($i = 1; $i <= $count; $i++) $types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $types .= '</Types>';
    kt_export_zip_add($handle, '[Content_Types].xml', $types, $directory, $offset);
    kt_export_zip_add($handle, '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>', $directory, $offset);
    $workbook = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $i = 1;
    foreach ($sheets as $name => $sheet) {
        $safeName = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $workbook .= '<sheet name="' . $safeName . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        kt_export_zip_add($handle, 'xl/worksheets/sheet' . $i . '.xml', kt_export_xlsx_sheet($sheet['rows'], $sheet['widths'] ?? [], $sheet['header'] ?? 0), $directory, $offset);
        $i++;
    }
    $workbook .= '</sheets></workbook>';
    $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    kt_export_zip_add($handle, 'xl/workbook.xml', $workbook, $directory, $offset);
    kt_export_zip_add($handle, 'xl/_rels/workbook.xml.rels', $rels, $directory, $offset);
    kt_export_zip_add($handle, 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="4"><font><sz val="11"/><name val="Aptos"/></font><font><b/><sz val="20"/><color rgb="FF173B2A"/><name val="Aptos Display"/></font><font><i/><sz val="10"/><color rgb="FF657265"/><name val="Aptos"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF24543A"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFDCE4DA"/></left><right style="thin"><color rgb="FFDCE4DA"/></right><top style="thin"><color rgb="FFDCE4DA"/></top><bottom style="thin"><color rgb="FFDCE4DA"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0"/><xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyAlignment="1" xfId="0"><alignment wrapText="1" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>', $directory, $offset);
    $directoryOffset = $offset;
    $directoryContents = implode('', $directory);
    if (fwrite($handle, $directoryContents) !== strlen($directoryContents)) throw new RuntimeException('Unable to finalize the Excel workbook.');
    $entryCount = count($directory);
    fwrite($handle, pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($directoryContents), $directoryOffset, 0));
    fclose($handle);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="kultoura-analytics-' . date('Ymd') . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

$today = new DateTimeImmutable('today');
$period = (string) ($_GET['period'] ?? 'month');
$fromInput = trim((string) ($_GET['date_from'] ?? ''));
$toInput = trim((string) ($_GET['date_to'] ?? ''));
$from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromInput);
$to = DateTimeImmutable::createFromFormat('!Y-m-d', $toInput);
$custom = $from && $to && $from->format('Y-m-d') === $fromInput && $to->format('Y-m-d') === $toInput && $from <= $to && $to <= $today;
if ($custom) {
    $start = $from;
    $endExclusive = $to->modify('+1 day');
    $periodLabel = $from->format('M j, Y') . ' – ' . $to->format('M j, Y');
} else {
    switch ($period) {
        case 'all': $start = new DateTimeImmutable('1000-01-01'); $periodLabel = 'All Time'; break;
        case 'today': $start = $today; $periodLabel = 'Today'; break;
        case 'yesterday': $start = $today->modify('-1 day'); $periodLabel = 'Yesterday'; break;
        case '7days': $start = $today->modify('-6 days'); $periodLabel = 'Last 7 Days'; break;
        case '30days': $start = $today->modify('-29 days'); $periodLabel = 'Last 30 Days'; break;
        case 'week': $start = new DateTimeImmutable('monday this week'); $periodLabel = 'This Week'; break;
        case 'lastmonth': $start = new DateTimeImmutable('first day of last month'); $periodLabel = 'Last Month'; break;
        case 'year': $start = new DateTimeImmutable('first day of january this year'); $periodLabel = 'This Year'; break;
        case 'month': default: $period = 'month'; $start = new DateTimeImmutable('first day of this month'); $periodLabel = 'This Month'; break;
    }
    $endExclusive = $period === 'yesterday' ? $today : ($period === 'lastmonth' ? new DateTimeImmutable('first day of this month') : $today->modify('+1 day'));
}
$startSql = $start->format('Y-m-d 00:00:00');
$endSql = $endExclusive->format('Y-m-d 00:00:00');
$rangeDays = max(1, (int) $start->diff($endExclusive)->days);
$previousStart = $start->modify('-' . $rangeDays . ' days');
$previousStartSql = $previousStart->format('Y-m-d 00:00:00');
$activityReady = kt_activity_ensure_schema($conn);
$activityRows = $activityReady ? kt_export_stmt_rows($conn, 'SELECT activity_id,user_id,session_hash,activity_type,category,parent_category,item_type,item_id,content_name,element_name,page_section,metadata,activity_date FROM user_activity_log WHERE activity_date>=? AND activity_date<? ORDER BY activity_date,activity_id', 'ss', [$startSql, $endSql]) : [];
$previousRows = $activityReady ? kt_export_stmt_rows($conn, 'SELECT activity_type,page_section FROM user_activity_log WHERE activity_date>=? AND activity_date<?', 'ss', [$previousStartSql, $startSql]) : [];
$reviewRows = [];
$reviewExists = $conn->query("SHOW TABLES LIKE 'item_reviews'");
if ($reviewExists && $reviewExists->num_rows > 0) {
    $reviewRows = kt_export_stmt_rows($conn, "SELECT item_type,item_id,rating,created_at FROM item_reviews WHERE status='visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at>=? AND created_at<? ORDER BY created_at", 'ss', [$startSql, $endSql]);
}
$currentPageViews = analytics_total_views($conn, $startSql, $endSql);
$previousPageViews = analytics_total_views($conn, $previousStartSql, $startSql);
$pageViewsByDay = kt_export_stmt_rows($conn, "SELECT DATE(viewed_at) report_date,COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY DATE(viewed_at)", 'ss', [$startSql, $endSql]);

$daily = $activityTypes = $devices = $visitorKeys = $pseudonyms = [];
$metrics = ['views' => $currentPageViews, 'searches' => 0, 'itineraries' => 0, 'reviews' => count($reviewRows), 'maps' => 0, 'recommendations' => 0];
$previousMetrics = ['views' => 0, 'searches' => 0, 'recommendations' => 0, 'reviews' => 0];
foreach ($previousRows as $row) {
    $type = (string) $row['activity_type'];
    if ($type === 'view') $previousMetrics['views']++;
    if ($type === 'search') $previousMetrics['searches']++;
    if ($type === 'recommendation' || ($type === 'click' && str_contains(strtolower((string) $row['page_section']), 'recommend'))) $previousMetrics['recommendations']++;
}
$previousReview = kt_export_stmt_rows($conn, "SELECT COUNT(*) total FROM item_reviews WHERE status='visible' AND (user_id IS NULL OR user_id NOT BETWEEN 2000000001 AND 2000000050) AND created_at>=? AND created_at<?", 'ss', [$previousStartSql, $startSql]);
$previousMetrics['reviews'] = (int) ($previousReview[0]['total'] ?? 0);
foreach ($activityRows as $row) {
    $type = (string) $row['activity_type'];
    $label = kt_export_activity_label($type, $row);
    $activityTypes[$label] = ($activityTypes[$label] ?? 0) + 1;
    $date = substr((string) $row['activity_date'], 0, 10);
    $daily[$date] ??= ['visitors' => [], 'views' => 0, 'searches' => 0, 'itineraries' => 0, 'reviews' => 0, 'maps' => 0, 'recommendations' => 0];
    $visitorKey = !empty($row['user_id']) ? 'u' . $row['user_id'] : 's' . $row['session_hash'];
    $visitorKeys[$visitorKey] = true;
    $daily[$date]['visitors'][$visitorKey] = true;
    if ($type === 'search') { $metrics['searches']++; $daily[$date]['searches']++; }
    if ($type === 'generate') { $metrics['itineraries']++; $daily[$date]['itineraries']++; }
    if ($type === 'map_interaction') { $metrics['maps']++; $daily[$date]['maps']++; }
    if ($type === 'recommendation' || ($type === 'click' && str_contains(strtolower((string) $row['page_section']), 'recommend'))) { $metrics['recommendations']++; $daily[$date]['recommendations']++; }
    $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
    $device = is_array($metadata) ? (string) ($metadata['device'] ?? $metadata['device_type'] ?? 'Unknown') : 'Unknown';
    $device = in_array(strtolower($device), ['mobile', 'desktop', 'tablet'], true) ? ucfirst(strtolower($device)) : 'Unknown';
    $devices[$device] = ($devices[$device] ?? 0) + 1;
    if (!isset($pseudonyms[$visitorKey])) $pseudonyms[$visitorKey] = 'USR-' . str_pad((string) (count($pseudonyms) + 1), 6, '0', STR_PAD_LEFT);
}
foreach ($reviewRows as $review) {
    $date = substr((string) $review['created_at'], 0, 10);
    $daily[$date] ??= ['visitors' => [], 'views' => 0, 'searches' => 0, 'itineraries' => 0, 'reviews' => 0, 'maps' => 0, 'recommendations' => 0];
    $daily[$date]['reviews']++;
}
$previousMetrics['views'] = $previousPageViews;
foreach ($pageViewsByDay as $row) {
    $date = (string) $row['report_date'];
    $daily[$date] ??= ['visitors' => [], 'views' => 0, 'searches' => 0, 'itineraries' => 0, 'reviews' => 0, 'maps' => 0, 'recommendations' => 0];
    $daily[$date]['views'] = (int) $row['total'];
}
$metrics['active_users'] = count($visitorKeys);
$metrics['destination_views'] = 0;
$itemViewRows = kt_export_stmt_rows($conn, 'SELECT item_type,item_id,COUNT(*) total FROM item_views WHERE viewed_at>=? AND viewed_at<? GROUP BY item_type,item_id', 'ss', [$startSql, $endSql]);
$itemViews = [];
foreach ($itemViewRows as $row) $itemViews[$row['item_type'] . ':' . $row['item_id']] = (int) $row['total'];
$metrics['destination_views'] = array_sum(array_map(static fn($key, $value) => in_array(strtok($key, ':'), ['nature', 'industry', 'resort', 'church', 'accommodation', 'bank', 'service'], true) ? $value : 0, array_keys($itemViews), array_values($itemViews)));
$destinationRatingRows = [];
if ($reviewRows) {
    foreach ($reviewRows as $row) {
        if (in_array($row['item_type'], ['nature', 'industry', 'resort', 'church', 'accommodation', 'bank', 'service'], true)) $destinationRatingRows[] = $row;
    }
}
$averageDestinationRating = $destinationRatingRows ? array_sum(array_column($destinationRatingRows, 'rating')) / count($destinationRatingRows) : 0;

$destinations = kt_export_stmt_rows($conn, "SELECT destination_id,destination_name,category,address FROM destination WHERE status='active' ORDER BY destination_name");
$destinationDaily = [];
$destinationTotals = [];
$knownDestinationTypes = ['nature', 'industry', 'resort', 'church', 'accommodation', 'bank', 'service'];
$destinationLogStats = [];
foreach ($activityRows as $row) {
    if (!in_array($row['item_type'], $knownDestinationTypes, true) || (int) $row['item_id'] <= 0) continue;
    $key = $row['item_type'] . ':' . (int) $row['item_id'];
    $destinationLogStats[$key]['visitors'][(empty($row['user_id']) ? 's' . $row['session_hash'] : 'u' . $row['user_id'])] = true;
    $destinationLogStats[$key]['views'] = ($destinationLogStats[$key]['views'] ?? 0) + ((string) $row['activity_type'] === 'view' ? 1 : 0);
    $d = substr((string) $row['activity_date'], 0, 10);
    $destinationDaily[$d][$key] ??= ['name' => $row['content_name'] ?: 'Destination #' . $row['item_id'], 'views' => 0, 'visitors' => []];
    if ($row['activity_type'] === 'view') {
        $destinationDaily[$d][$key]['views']++;
        $destinationDaily[$d][$key]['visitors'][$empty = (empty($row['user_id']) ? 's' . $row['session_hash'] : 'u' . $row['user_id'])] = true;
    }
}
foreach ($reviewRows as $row) {
    if (!isset($destinationTotals[$row['item_type'] . ':' . $row['item_id']])) $destinationTotals[$row['item_type'] . ':' . $row['item_id']] = ['ratings' => [], 'reviews' => 0];
    $destinationTotals[$row['item_type'] . ':' . $row['item_id']]['ratings'][] = (int) $row['rating'];
    $destinationTotals[$row['item_type'] . ':' . $row['item_id']]['reviews']++;
    if (in_array($row['item_type'], $knownDestinationTypes, true)) {
        $date = substr((string) $row['created_at'], 0, 10);
        $key = $row['item_type'] . ':' . (int) $row['item_id'];
        $destinationDaily[$date][$key] ??= ['name' => 'Destination #' . $row['item_id'], 'views' => 0, 'visitors' => [], 'ratingTotal' => 0, 'reviews' => 0];
        $destinationDaily[$date][$key]['ratingTotal'] = ($destinationDaily[$date][$key]['ratingTotal'] ?? 0) + (int) $row['rating'];
        $destinationDaily[$date][$key]['reviews'] = ($destinationDaily[$date][$key]['reviews'] ?? 0) + 1;
    }
}
$destinationSheetRows = [['kind' => 'title', 'values' => ['KulToura | Destination Analytics']], ['kind' => 'subtitle', 'values' => ['Destination and local establishment performance | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Destination', 'Category', 'Barangay / Address', 'Views', 'Unique Visitors', 'Average Rating', 'Reviews']]];
foreach ($destinations as $d) {
    $type = (string) $d['category']; $key = $type . ':' . (int) $d['destination_id'];
    $rating = $destinationTotals[$key]['ratings'] ?? [];
    $destinationSheetRows[] = [$d['destination_name'], ucfirst($type), $d['address'], $itemViews[$key] ?? 0, count($destinationLogStats[$key]['visitors'] ?? []), $rating ? round(array_sum($rating) / count($rating), 2) : '', count($rating)];
}
$culturalTypes = ['product', 'fiesta', 'person'];
$culturalTables = ['product' => ['products', 'product_id', 'product_name'], 'fiesta' => ['fiestas', 'fiesta_id', 'fiesta_name'], 'person' => ['people', 'person_id', 'fullname']];
$culturalItems = [];
foreach ($culturalTables as $type => [$table, $idCol, $nameCol]) {
    foreach (kt_export_stmt_rows($conn, "SELECT {$idCol} id,{$nameCol} name FROM {$table} WHERE status='active' ORDER BY {$nameCol}") as $item) {
        $key = $type . ':' . (int) $item['id'];
        $culturalItems[$key] = ['type' => $type, 'id' => (int) $item['id'], 'name' => (string) $item['name'], 'views' => $itemViews[$key] ?? 0, 'readers' => [], 'saves' => 0, 'daily' => []];
    }
}
$contentDaily = [];
foreach ($activityRows as $row) {
    $type = (string) ($row['item_type'] ?? ''); $key = $type . ':' . (int) ($row['item_id'] ?? 0);
    if (!isset($culturalItems[$key])) continue;
    $visitorKey = !empty($row['user_id']) ? 'u' . $row['user_id'] : 's' . $row['session_hash'];
    $date = substr((string) $row['activity_date'], 0, 10);
    if ($row['activity_type'] === 'view') {
        $culturalItems[$key]['readers'][$visitorKey] = true;
        $culturalItems[$key]['daily'][$date]['views'] = ($culturalItems[$key]['daily'][$date]['views'] ?? 0) + 1;
        $culturalItems[$key]['daily'][$date]['readers'][$visitorKey] = true;
    }
    if ($row['activity_type'] === 'download') {
        $culturalItems[$key]['saves']++;
        $culturalItems[$key]['daily'][$date]['saves'] = ($culturalItems[$key]['daily'][$date]['saves'] ?? 0) + 1;
    }
}
$culturalSheetRows = [['kind' => 'title', 'values' => ['KulToura | Cultural Content Analytics']], ['kind' => 'subtitle', 'values' => ['Performance of culture, local products, fiestas, and people content | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Content Title', 'Content Type', 'Views', 'Unique Readers', 'Shares / Saves', 'Engagement Rate']]];
foreach ($culturalItems as $item) {
    $views = $item['views'];
    $engagementRate = $views > 0 ? round(($item['saves'] + count($item['readers'])) / $views * 100, 1) . '%' : '0%';
    $culturalSheetRows[] = [$item['name'], ucfirst($item['type']), $views, count($item['readers']), $item['saves'], $engagementRate];
    foreach ($item['daily'] as $date => $values) $contentDaily[] = [$date, $item['name'], ucfirst($item['type']), $values['views'] ?? 0, count($values['readers'] ?? []), $values['saves'] ?? 0];
}

$engagementRows = [['kind' => 'title', 'values' => ['KulToura | Engagement Analytics']], ['kind' => 'subtitle', 'values' => ['Daily platform engagement metrics | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Date', 'Unique Active Users', 'Page Views', 'Searches', 'Itineraries Generated', 'Reviews', 'Map Interactions', 'Recommendation Clicks']]];
foreach ($daily as $date => $d) $engagementRows[] = [$date, count($d['visitors']), $d['views'], $d['searches'], $d['itineraries'], $d['reviews'], $d['maps'], $d['recommendations']];
$rawRows = [['kind' => 'title', 'values' => ['KulToura | Raw Activity Data']], ['kind' => 'subtitle', 'values' => ['Anonymized event-level records | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Event ID', 'Date and Time', 'Anonymized User ID', 'Activity', 'Page / Target', 'Device']]];
foreach ($activityRows as $row) {
    $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
    $device = is_array($metadata) ? ucfirst(strtolower((string) ($metadata['device'] ?? $metadata['device_type'] ?? 'Unknown'))) : 'Unknown';
    $rawRows[] = ['EVT-' . str_pad((string) $row['activity_id'], 8, '0', STR_PAD_LEFT), (string) $row['activity_date'], $pseudonyms[!empty($row['user_id']) ? 'u' . $row['user_id'] : 's' . $row['session_hash']] ?? 'Unknown', kt_export_activity_label((string) $row['activity_type'], $row), $row['content_name'] ?: ($row['element_name'] ?: $row['page_section']), $device];
}
$destinationDailyRows = [['kind' => 'title', 'values' => ['KulToura | Destination Daily Data']], ['kind' => 'subtitle', 'values' => ['Daily destination activity and feedback | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Date', 'Destination', 'Views', 'Unique Visitors', 'Average Rating', 'Reviews']]];
foreach ($destinationDaily as $date => $items) foreach ($items as $values) $destinationDailyRows[] = [$date, $values['name'], $values['views'], count($values['visitors']), !empty($values['reviews']) ? round($values['ratingTotal'] / $values['reviews'], 2) : '', $values['reviews'] ?? 0];
$contentDailyRows = [['kind' => 'title', 'values' => ['KulToura | Cultural Content Daily Data']], ['kind' => 'subtitle', 'values' => ['Daily cultural content activity | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Date', 'Content Title', 'Content Type', 'Views', 'Unique Readers', 'Shares / Saves']]];
foreach ($contentDaily as $row) $contentDailyRows[] = $row;

$behaviorDescriptions = ['Viewed destination or content' => 'Destination listing or detail views', 'Viewed page' => 'Tracked page views', 'Searched' => 'Search queries submitted', 'Generated itinerary' => 'Visitor itinerary generation', 'Submitted review' => 'Ratings and comments submitted', 'Submitted rating' => 'Ratings submitted', 'Submitted comment' => 'Comments submitted', 'Opened map' => 'Map interactions', 'Clicked recommendation' => 'Clicks on recommended destinations or content', 'Downloaded or saved' => 'Content downloaded or saved'];
$behaviorRows = [['kind' => 'title', 'values' => ['KulToura | User Behavior Analytics']], ['kind' => 'subtitle', 'values' => ['Actions, navigation patterns, and device usage | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Activity Type', 'Event Count', 'Share of Tracked Events', 'Interpretation', '', 'Device', 'Events']]];
$allActivityCount = count($activityRows);
foreach ($activityTypes as $label => $count) $behaviorRows[] = [$label, $count, $allActivityCount ? round($count / $allActivityCount * 100, 1) . '%' : '0%', $behaviorDescriptions[$label] ?? 'Recorded visitor activity'];
foreach (['Mobile', 'Desktop', 'Tablet', 'Unknown'] as $device) $behaviorRows[] = ['', '', '', '', '', $device, $devices[$device] ?? 0];

$periodStartPreviousCount = count($previousRows);
$summaryRows = [
    ['kind' => 'title', 'values' => ['KulToura | Executive Analytics Summary']],
    ['kind' => 'subtitle', 'values' => ['Office of Culture and Tourism | Municipality of Malvar, Batangas | Date-filtered report']],
    ['kind' => 'section', 'values' => ['SELECTED REPORT PERIOD', $periodLabel, $start->format('M j, Y'), $endExclusive->modify('-1 day')->format('M j, Y')]],
    ['kind' => 'header', 'values' => ['TOTAL PAGE VIEWS', $metrics['views'], 'UNIQUE ACTIVE USERS', $metrics['active_users'], 'SEARCHES', $metrics['searches'], 'RECOMMENDATION CLICKS', $metrics['recommendations']]],
    ['kind' => 'header', 'values' => ['REVIEWS SUBMITTED', $metrics['reviews'], 'MAP INTERACTIONS', $metrics['maps'], 'DESTINATION VIEWS', $metrics['destination_views'], 'AVERAGE DESTINATION RATING', $averageDestinationRating ? round($averageDestinationRating, 2) : 'No ratings']],
    ['kind' => 'section', 'values' => ['PERIOD-OVER-PERIOD COMPARISON']],
    ['kind' => 'header', 'values' => ['Metric', 'Selected Period', 'Previous Equal Period', 'Change']],
];
foreach ([['Page Views', 'views'], ['Searches', 'searches'], ['Recommendation Clicks', 'recommendations'], ['Reviews', 'reviews']] as [$label, $key]) {
    $current = $metrics[$key]; $previous = $previousMetrics[$key];
    $change = $previous === 0 ? ($current > 0 ? 'New activity' : '0%') : round(($current - $previous) / $previous * 100, 1) . '%';
    $summaryRows[] = [$label, $current, $previous, $change];
}
$topDestination = '';
foreach ($destinations as $d) {
    $type = (string) $d['category']; $key = $type . ':' . (int) $d['destination_id'];
    $count = $itemViews[$key] ?? 0;
    if ($count > 0 && (!$topDestination || $count > $topDestination['views'])) $topDestination = ['name' => $d['destination_name'], 'views' => $count];
}
$topCulture = '';
foreach ($culturalItems as $item) if ($item['views'] > 0 && (!$topCulture || $item['views'] > $topCulture['views'])) $topCulture = ['name' => $item['name'], 'views' => $item['views']];
$summaryRows[] = ['kind' => 'section', 'values' => ['AUTOMATED INSIGHTS']];
$summaryRows[] = [$topDestination ? $topDestination['name'] . ' leads destination views with ' . $topDestination['views'] . ' views.' : 'No destination views were recorded in this period.'];
$summaryRows[] = [$topCulture ? $topCulture['name'] . ' leads cultural content views with ' . $topCulture['views'] . ' views.' : 'No cultural content views were recorded in this period.'];
$summaryRows[] = [$metrics['searches'] > 0 ? $metrics['searches'] . ' searches were submitted; review frequent and zero-result terms to improve discovery.' : 'No searches were recorded in this period.'];
$summaryRows[] = [$metrics['reviews'] > 0 ? $metrics['reviews'] . ' visible reviews were submitted, averaging ' . ($averageDestinationRating ? number_format($averageDestinationRating, 2) . ' stars for destinations' : 'no destination rating available') . '.' : 'No visible reviews were submitted in this period.'];
$summaryRows[] = ['kind' => 'section', 'values' => ['REPORT NOTE']];
$summaryRows[] = ['kind' => 'subtitle', 'values' => ['This workbook is generated from live KulToura records for the selected dates. User identifiers in Raw Activity Data are anonymized within this workbook.']];

$filterRows = [['kind' => 'title', 'values' => ['KULTOURA | REPORT DATE FILTERS']], ['kind' => 'subtitle', 'values' => ['Selected reporting period used by all sheets in this workbook.']], ['kind' => 'section', 'values' => ['Quick date range']], ['kind' => 'header', 'values' => ['Selected period', $periodLabel]], ['Custom start date', $start->format('Y-m-d')], ['Custom end date', $endExclusive->modify('-1 day')->format('Y-m-d')], ['Effective start date', $start->format('Y-m-d')], ['Effective end date', $endExclusive->modify('-1 day')->format('Y-m-d')], ['kind' => 'section', 'values' => ['Filter notes']], ['The selected date range is used across all report sheets.'], ['Comparison metrics use the immediately preceding period of equal length.'], ['User identifiers are anonymized in the raw activity sheet.']];
$recommendationRows = [['kind' => 'title', 'values' => ['KulToura | Tourism Planning Recommendations']], ['kind' => 'subtitle', 'values' => ['Rule-based suggestions based on live activity for ' . $periodLabel . '.']], ['kind' => 'header', 'values' => ['Area', 'Observed Result', 'Suggested Recommendation', 'Priority', 'Basis']],
    ['Destination promotion', $topDestination ? $topDestination['name'] . ' had ' . $topDestination['views'] . ' views.' : 'No destination views recorded.', 'Review listing quality, photos, descriptions, and cross-promotion through related destinations.', 'High', 'Destination views'],
    ['Cultural preservation', $topCulture ? $topCulture['name'] . ' had ' . $topCulture['views'] . ' views.' : 'No cultural content views recorded.', 'Consider refreshing content, adding local stories or multimedia, and featuring it on the homepage.', 'High', 'Cultural content views'],
    ['Recommended content', $metrics['recommendations'] . ' recommendation clicks.', 'Review which recommended destinations and cultural content receive clicks; refine relevance using visitor interests and engagement.', 'Medium', 'Recommendation clicks'],
    ['Search experience', $metrics['searches'] . ' searches submitted.', 'Review common search terms and zero-result searches; improve keywords and content tagging.', 'Medium', 'Search activity'],
    ['Visitor feedback', $metrics['reviews'] . ' visible reviews; destination average ' . ($averageDestinationRating ? number_format($averageDestinationRating, 2) . ' stars.' : 'not available.'), 'Review comments and recurring concerns; coordinate with relevant establishments when appropriate.', 'Medium', 'Ratings and comments'],
];

$sheets = [
    'Report Filters' => ['rows' => $filterRows, 'widths' => [30, 44]],
    'Executive Summary' => ['rows' => $summaryRows, 'widths' => [34, 24, 34, 24, 28, 20, 34, 24]],
    'Raw Data' => ['rows' => $rawRows, 'widths' => [16, 24, 20, 28, 42, 16], 'header' => 3],
    'Engagement Analytics' => ['rows' => $engagementRows, 'widths' => [18, 20, 16, 14, 24, 14, 20, 26], 'header' => 3],
    'Destination Daily Data' => ['rows' => $destinationDailyRows, 'widths' => [18, 38, 14, 20, 18, 14], 'header' => 3],
    'Content Daily Data' => ['rows' => $contentDailyRows, 'widths' => [18, 38, 22, 14, 18, 18], 'header' => 3],
    'Destination Analytics' => ['rows' => $destinationSheetRows, 'widths' => [38, 20, 42, 14, 20, 18, 14], 'header' => 3],
    'Cultural Analytics' => ['rows' => $culturalSheetRows, 'widths' => [42, 24, 14, 18, 18, 18], 'header' => 3],
    'User Behavior' => ['rows' => $behaviorRows, 'widths' => [34, 16, 24, 58, 4, 16, 14], 'header' => 3],
    'Recommendations' => ['rows' => $recommendationRows, 'widths' => [26, 40, 82, 14, 24], 'header' => 3],
];
kt_export_write_xlsx($sheets);
