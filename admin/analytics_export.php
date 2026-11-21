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
    if ($type === 'view') {
        $itemType = (string) ($row['item_type'] ?? '');
        if (in_array($itemType, ['nature', 'industry', 'resort', 'church', 'accommodation', 'bank', 'service', 'restaurant'], true)) return 'Viewed destination';
        if (in_array($itemType, ['product', 'fiesta', 'person'], true)) return 'Viewed cultural content';
        return 'Viewed page';
    }
    $labels = ['search' => 'Searched', 'review' => 'Submitted review', 'rating' => 'Submitted rating', 'comment' => 'Submitted comment', 'map_interaction' => 'Opened map', 'recommendation' => 'Clicked recommendation', 'download' => 'Downloaded or saved', 'language' => 'Changed language'];
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
    $periodLabel = $from->format('M j, Y') . ' to ' . $to->format('M j, Y');
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
$previousStart = $period === 'all' ? $start : $start->modify('-' . $rangeDays . ' days');
$previousStartSql = $previousStart->format('Y-m-d 00:00:00');
$activityReady = kt_activity_ensure_schema($conn);
$activityRows = $activityReady ? kt_export_stmt_rows($conn, "SELECT activity_id,user_id,session_hash,activity_type,category,parent_category,item_type,item_id,content_name,element_name,page_section,metadata,activity_date FROM user_activity_log WHERE activity_type<>'generate' AND activity_date>=? AND activity_date<? ORDER BY activity_date,activity_id", 'ss', [$startSql, $endSql]) : [];
$previousRows = $activityReady ? kt_export_stmt_rows($conn, 'SELECT activity_type,page_section FROM user_activity_log WHERE activity_date>=? AND activity_date<?', 'ss', [$previousStartSql, $startSql]) : [];
$reviewRows = [];
$reviewExists = $conn->query("SHOW TABLES LIKE 'item_reviews'");
if ($reviewExists && $reviewExists->num_rows > 0) {
    $reviewRows = kt_export_stmt_rows($conn, "SELECT item_type,item_id,rating,review_text,created_at FROM item_reviews WHERE status IN ('visible','demo') AND created_at>=? AND created_at<? ORDER BY created_at", 'ss', [$startSql, $endSql]);
}
$currentPageViews = analytics_total_views($conn, $startSql, $endSql);
$previousPageViews = analytics_total_views($conn, $previousStartSql, $startSql);
$pageViewsByDay = kt_export_stmt_rows($conn, "SELECT DATE(viewed_at) report_date,COUNT(*) total FROM page_views WHERE (referrer_host IS NULL OR referrer_host <> 'demo-seed.kultoura.local') AND viewed_at>=? AND viewed_at<? GROUP BY DATE(viewed_at)", 'ss', [$startSql, $endSql]);
$searchesByDay = kt_export_stmt_rows($conn, 'SELECT DATE(searched_at) report_date,COUNT(*) total FROM search_log WHERE searched_at>=? AND searched_at<? GROUP BY DATE(searched_at)', 'ss', [$startSql, $endSql]);
$searchTermRows = kt_export_stmt_rows($conn, 'SELECT LOWER(query) query,COUNT(*) searches,SUM(result_count=0) zero_results,SUM(result_count>0) with_results FROM search_log WHERE searched_at>=? AND searched_at<? GROUP BY LOWER(query) ORDER BY searches DESC,query ASC', 'ss', [$startSql, $endSql]);
$previousSearchCount = kt_export_stmt_rows($conn, 'SELECT COUNT(*) total FROM search_log WHERE searched_at>=? AND searched_at<?', 'ss', [$previousStartSql, $startSql]);
$currentSearchCount = array_sum(array_column($searchesByDay, 'total'));
$previousSearchCount = (int) ($previousSearchCount[0]['total'] ?? 0);

$daily = $activityTypes = $devices = $visitorKeys = $pseudonyms = [];
$metrics = ['views' => $currentPageViews, 'searches' => $currentSearchCount, 'ratings' => count($reviewRows), 'comments' => 0, 'maps' => 0, 'recommendations' => 0];
$previousMetrics = ['views' => 0, 'searches' => 0, 'recommendations' => 0, 'ratings' => 0, 'comments' => 0];
foreach ($previousRows as $row) {
    $type = (string) $row['activity_type'];
    if ($type === 'recommendation') $previousMetrics['recommendations']++;
}
$previousMetrics['searches'] = $previousSearchCount;
$previousReview = $reviewExists && $reviewExists->num_rows > 0
    ? kt_export_stmt_rows($conn, "SELECT COUNT(*) ratings,SUM(review_text IS NOT NULL AND TRIM(review_text)<>'') comments FROM item_reviews WHERE status IN ('visible','demo') AND created_at>=? AND created_at<?", 'ss', [$previousStartSql, $startSql])
    : [];
$previousMetrics['ratings'] = (int) ($previousReview[0]['ratings'] ?? 0);
$previousMetrics['comments'] = (int) ($previousReview[0]['comments'] ?? 0);
$metrics['comments'] = count(array_filter($reviewRows, static fn($row) => trim((string) ($row['review_text'] ?? '')) !== ''));
foreach ($activityRows as $row) {
    $type = (string) $row['activity_type'];
    $label = kt_export_activity_label($type, $row);
    $activityTypes[$label] = ($activityTypes[$label] ?? 0) + 1;
    $date = substr((string) $row['activity_date'], 0, 10);
    $daily[$date] ??= ['visitors' => [], 'views' => 0, 'searches' => 0, 'ratings' => 0, 'comments' => 0, 'maps' => 0, 'recommendations' => 0];
    $visitorKey = !empty($row['user_id']) ? 'u' . $row['user_id'] : 's' . $row['session_hash'];
    $visitorKeys[$visitorKey] = true;
    $daily[$date]['visitors'][$visitorKey] = true;
    if ($type === 'map_interaction') { $metrics['maps']++; $daily[$date]['maps']++; }
    if ($type === 'recommendation') { $metrics['recommendations']++; $daily[$date]['recommendations']++; }
    $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
    $device = is_array($metadata) ? (string) ($metadata['device'] ?? $metadata['device_type'] ?? 'Unknown') : 'Unknown';
    $device = in_array(strtolower($device), ['mobile', 'desktop', 'tablet'], true) ? ucfirst(strtolower($device)) : 'Unknown';
    $devices[$device] = ($devices[$device] ?? 0) + 1;
    if (!isset($pseudonyms[$visitorKey])) $pseudonyms[$visitorKey] = 'USR-' . str_pad((string) (count($pseudonyms) + 1), 6, '0', STR_PAD_LEFT);
}
foreach ($reviewRows as $review) {
    $date = substr((string) $review['created_at'], 0, 10);
    $daily[$date] ??= ['visitors' => [], 'views' => 0, 'searches' => 0, 'ratings' => 0, 'comments' => 0, 'maps' => 0, 'recommendations' => 0];
    $daily[$date]['ratings']++;
    if (trim((string) ($review['review_text'] ?? '')) !== '') $daily[$date]['comments']++;
}
$previousMetrics['views'] = $previousPageViews;
foreach ($pageViewsByDay as $row) {
    $date = (string) $row['report_date'];
    $daily[$date] ??= ['visitors' => [], 'views' => 0, 'searches' => 0, 'ratings' => 0, 'comments' => 0, 'maps' => 0, 'recommendations' => 0];
    $daily[$date]['views'] = (int) $row['total'];
}
foreach ($searchesByDay as $row) {
    $date = (string) $row['report_date'];
    $daily[$date] ??= ['visitors' => [], 'views' => 0, 'searches' => 0, 'ratings' => 0, 'comments' => 0, 'maps' => 0, 'recommendations' => 0];
    $daily[$date]['searches'] = (int) $row['total'];
}
$metrics['active_users'] = count($visitorKeys);
$itemViewRows = kt_export_stmt_rows($conn, 'SELECT item_type,item_id,COUNT(*) total FROM item_views WHERE viewed_at>=? AND viewed_at<? GROUP BY item_type,item_id', 'ss', [$startSql, $endSql]);
$itemViewDailyRows = kt_export_stmt_rows($conn, 'SELECT DATE(viewed_at) report_date,item_type,item_id,COUNT(*) total FROM item_views WHERE viewed_at>=? AND viewed_at<? GROUP BY DATE(viewed_at),item_type,item_id', 'ss', [$startSql, $endSql]);
$itemViews = [];
foreach ($itemViewRows as $row) $itemViews[$row['item_type'] . ':' . $row['item_id']] = (int) $row['total'];
$metrics['destination_views'] = 0;
$destinationRatingRows = [];
if ($reviewRows) {
    foreach ($reviewRows as $row) {
        if (in_array($row['item_type'], ['nature', 'industry', 'resort', 'church'], true)) $destinationRatingRows[] = $row;
    }
}
$averageDestinationRating = $destinationRatingRows ? array_sum(array_column($destinationRatingRows, 'rating')) / count($destinationRatingRows) : 0;

$destinations = kt_export_stmt_rows($conn, "SELECT destination_id id,destination_name name,category,address FROM destination WHERE status='active' ORDER BY destination_name");
foreach ($destinations as &$destination) $destination['item_type'] = (string) $destination['category'];
unset($destination);
$restaurantListings = kt_export_stmt_rows($conn, "SELECT restaurant_id id,restaurant_name name,category,address FROM restaurants WHERE status='active' ORDER BY restaurant_name");
foreach ($restaurantListings as &$restaurant) $restaurant['item_type'] = 'restaurant';
unset($restaurant);
$destinations = array_merge($destinations, $restaurantListings);
$destinationDaily = [];
$destinationTotals = [];
$knownDestinationTypes = ['nature', 'industry', 'resort', 'church', 'accommodation', 'bank', 'service', 'restaurant'];
$destinationLogStats = [];
$destinationNames = [];
foreach ($destinations as $destination) {
    $key = $destination['item_type'] . ':' . (int) $destination['id'];
    $destinationNames[$key] = $destination['name'];
    if (in_array($destination['item_type'], ['nature', 'industry', 'resort', 'church'], true)) $metrics['destination_views'] += $itemViews[$key] ?? 0;
}
foreach ($itemViewDailyRows as $viewRow) {
    if (!in_array($viewRow['item_type'], $knownDestinationTypes, true)) continue;
    $key = $viewRow['item_type'] . ':' . (int) $viewRow['item_id'];
    if (!isset($destinationNames[$key])) continue;
    $date = (string) $viewRow['report_date'];
    $destinationDaily[$date][$key] = ['name' => $destinationNames[$key] ?? 'Destination #' . $viewRow['item_id'], 'views' => (int) $viewRow['total'], 'visitors' => []];
}
foreach ($activityRows as $row) {
    if (!in_array($row['item_type'], $knownDestinationTypes, true) || (int) $row['item_id'] <= 0 || $row['activity_type'] !== 'view') continue;
    $key = $row['item_type'] . ':' . (int) $row['item_id'];
    if (!isset($destinationNames[$key])) continue;
    $destinationLogStats[$key]['visitors'][(empty($row['user_id']) ? 's' . $row['session_hash'] : 'u' . $row['user_id'])] = true;
    $d = substr((string) $row['activity_date'], 0, 10);
    $destinationDaily[$d][$key] ??= ['name' => $destinationNames[$key] ?? ($row['content_name'] ?: 'Destination #' . $row['item_id']), 'views' => 0, 'visitors' => []];
    $destinationDaily[$d][$key]['visitors'][empty($row['user_id']) ? 's' . $row['session_hash'] : 'u' . $row['user_id']] = true;
}
foreach ($reviewRows as $row) {
    if (!isset($destinationTotals[$row['item_type'] . ':' . $row['item_id']])) $destinationTotals[$row['item_type'] . ':' . $row['item_id']] = ['ratings' => [], 'reviews' => 0];
    $destinationTotals[$row['item_type'] . ':' . $row['item_id']]['ratings'][] = (int) $row['rating'];
    $destinationTotals[$row['item_type'] . ':' . $row['item_id']]['reviews']++;
    if (in_array($row['item_type'], $knownDestinationTypes, true)) {
        $key = $row['item_type'] . ':' . (int) $row['item_id'];
        if (!isset($destinationNames[$key])) continue;
        $date = substr((string) $row['created_at'], 0, 10);
        $destinationDaily[$date][$key] ??= ['name' => 'Destination #' . $row['item_id'], 'views' => 0, 'visitors' => [], 'ratingTotal' => 0, 'reviews' => 0];
        $destinationDaily[$date][$key]['ratingTotal'] = ($destinationDaily[$date][$key]['ratingTotal'] ?? 0) + (int) $row['rating'];
        $destinationDaily[$date][$key]['reviews'] = ($destinationDaily[$date][$key]['reviews'] ?? 0) + 1;
    }
}
$destinationSheetRows = [['kind' => 'title', 'values' => ['KulToura | Destination Analytics']], ['kind' => 'subtitle', 'values' => ['Destination and local establishment performance | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Destination', 'Category', 'Barangay / Address', 'Views', 'Unique Visitors', 'Average Rating', 'Reviews']]];
foreach ($destinations as $d) {
    $type = (string) $d['item_type']; $key = $type . ':' . (int) $d['id'];
    $rating = $destinationTotals[$key]['ratings'] ?? [];
    $categoryLabel = $type === 'restaurant' ? 'Restaurant - ' . $d['category'] : ucfirst($type);
    $destinationSheetRows[] = [$d['name'], $categoryLabel, $d['address'], $itemViews[$key] ?? 0, count($destinationLogStats[$key]['visitors'] ?? []), $rating ? round(array_sum($rating) / count($rating), 2) : '', count($rating)];
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
foreach ($itemViewDailyRows as $viewRow) {
    $key = $viewRow['item_type'] . ':' . (int) $viewRow['item_id'];
    if (!isset($culturalItems[$key])) continue;
    $culturalItems[$key]['daily'][$viewRow['report_date']] = ['views' => (int) $viewRow['total'], 'readers' => [], 'saves' => 0];
}
$favoriteRows = [];
$favoritesExists = $conn->query("SHOW TABLES LIKE 'favorites'");
if ($favoritesExists && $favoritesExists->num_rows > 0) {
    $favoriteRows = kt_export_stmt_rows($conn, "SELECT item_type,item_id,DATE(created_at) report_date,COUNT(*) total FROM favorites WHERE user_id NOT BETWEEN 2000000001 AND 2000000050 AND item_type IN ('product','fiesta','person') AND created_at>=? AND created_at<? GROUP BY item_type,item_id,DATE(created_at)", 'ss', [$startSql, $endSql]);
}
$favoriteCounts = [];
foreach ($favoriteRows as $favorite) {
    $key = $favorite['item_type'] . ':' . (int) $favorite['item_id'];
    if (!isset($culturalItems[$key])) continue;
    $count = (int) $favorite['total'];
    $favoriteCounts[$key] = ($favoriteCounts[$key] ?? 0) + $count;
    $culturalItems[$key]['daily'][$favorite['report_date']] ??= ['views' => 0, 'readers' => [], 'saves' => 0];
    $culturalItems[$key]['daily'][$favorite['report_date']]['saves'] += $count;
}
$downloadCounts = [];
$contentDaily = [];
foreach ($activityRows as $row) {
    $type = (string) ($row['item_type'] ?? ''); $key = $type . ':' . (int) ($row['item_id'] ?? 0);
    if (!isset($culturalItems[$key])) continue;
    $visitorKey = !empty($row['user_id']) ? 'u' . $row['user_id'] : 's' . $row['session_hash'];
    $date = substr((string) $row['activity_date'], 0, 10);
    if ($row['activity_type'] === 'view') {
        $culturalItems[$key]['readers'][$visitorKey] = true;
        $culturalItems[$key]['daily'][$date] ??= ['views' => 0, 'readers' => [], 'saves' => 0];
        $culturalItems[$key]['daily'][$date]['readers'][$visitorKey] = true;
    }
    if ($row['activity_type'] === 'download') {
        $downloadCounts[$key] = ($downloadCounts[$key] ?? 0) + 1;
        $culturalItems[$key]['daily'][$date] ??= ['views' => 0, 'readers' => [], 'saves' => 0];
        $culturalItems[$key]['daily'][$date]['saves']++;
    }
}
$culturalSheetRows = [['kind' => 'title', 'values' => ['KulToura | Cultural Content Analytics']], ['kind' => 'subtitle', 'values' => ['Performance of culture, local products, fiestas, and people content | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Content Title', 'Content Type', 'Views', 'Unique Readers', 'Saves / Downloads', 'Save Rate']]];
foreach ($culturalItems as $item) {
    $views = $item['views'];
    $saves = ($favoriteCounts[$item['type'] . ':' . $item['id']] ?? 0) + ($downloadCounts[$item['type'] . ':' . $item['id']] ?? 0);
    $engagementRate = $views > 0 ? round($saves / $views * 100, 1) . '%' : '0%';
    $culturalSheetRows[] = [$item['name'], ucfirst($item['type']), $views, count($item['readers']), $saves, $engagementRate];
    foreach ($item['daily'] as $date => $values) $contentDaily[] = [$date, $item['name'], ucfirst($item['type']), $values['views'] ?? 0, count($values['readers'] ?? []), $values['saves'] ?? 0];
}

$engagementRows = [['kind' => 'title', 'values' => ['KulToura | Engagement Analytics']], ['kind' => 'subtitle', 'values' => ['Daily platform engagement metrics | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Date', 'Unique Tracked Visitors', 'Page Views', 'Searches', 'Ratings', 'Written Comments', 'Map Interactions', 'Recommendation Clicks']]];
foreach ($daily as $date => $d) $engagementRows[] = [$date, count($d['visitors']), $d['views'], $d['searches'], $d['ratings'], $d['comments'], $d['maps'], $d['recommendations']];
$rawRows = [['kind' => 'title', 'values' => ['KulToura | Raw Activity Data']], ['kind' => 'subtitle', 'values' => ['Anonymized event-level records | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Event ID', 'Date and Time', 'Anonymized User ID', 'Activity', 'Page / Target', 'Device']]];
foreach ($activityRows as $row) {
    $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
    $device = is_array($metadata) ? ucfirst(strtolower((string) ($metadata['device'] ?? $metadata['device_type'] ?? 'Unknown'))) : 'Unknown';
    $rawRows[] = ['EVT-' . str_pad((string) $row['activity_id'], 8, '0', STR_PAD_LEFT), (string) $row['activity_date'], $pseudonyms[!empty($row['user_id']) ? 'u' . $row['user_id'] : 's' . $row['session_hash']] ?? 'Unknown', kt_export_activity_label((string) $row['activity_type'], $row), $row['content_name'] ?: ($row['element_name'] ?: $row['page_section']), $device];
}
$destinationDailyRows = [['kind' => 'title', 'values' => ['KulToura | Destination Daily Data']], ['kind' => 'subtitle', 'values' => ['Daily destination and restaurant activity and feedback | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Date', 'Destination / Establishment', 'Views', 'Unique Visitors', 'Average Rating', 'Ratings']]];
foreach ($destinationDaily as $date => $items) foreach ($items as $values) $destinationDailyRows[] = [$date, $values['name'], $values['views'], count($values['visitors']), !empty($values['reviews']) ? round($values['ratingTotal'] / $values['reviews'], 2) : '', $values['reviews'] ?? 0];
$contentDailyRows = [['kind' => 'title', 'values' => ['KulToura | Cultural Content Daily Data']], ['kind' => 'subtitle', 'values' => ['Daily cultural content activity | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Date', 'Content Title', 'Content Type', 'Views', 'Unique Readers', 'Saves / Downloads']]];
foreach ($contentDaily as $row) $contentDailyRows[] = $row;

$behaviorDescriptions = ['Viewed destination' => 'Destination and local establishment detail views', 'Viewed cultural content' => 'Products, fiestas, and people detail views', 'Viewed page' => 'Tracked page views', 'Searched' => 'Search queries submitted', 'Submitted review' => 'Ratings and comments submitted', 'Submitted rating' => 'Ratings submitted', 'Submitted comment' => 'Comments submitted', 'Opened map' => 'Map interactions', 'Clicked recommendation' => 'Clicks on recommended destinations or content', 'Downloaded or saved' => 'Content downloaded or saved'];
$behaviorRows = [['kind' => 'title', 'values' => ['KulToura | User Behavior Analytics']], ['kind' => 'subtitle', 'values' => ['Device class is inferred from browser user-agent data and may be approximate; older records or missing data show Unknown. Period: ' . $periodLabel]], ['kind' => 'header', 'values' => ['Activity Type', 'Event Count', 'Share of Tracked Events', 'Interpretation', '', 'Device', 'Events']]];
$allActivityCount = count($activityRows);
foreach ($activityTypes as $label => $count) $behaviorRows[] = [$label, $count, $allActivityCount ? round($count / $allActivityCount * 100, 1) . '%' : '0%', $behaviorDescriptions[$label] ?? 'Recorded visitor activity'];
foreach (['Mobile', 'Desktop', 'Tablet', 'Unknown'] as $device) $behaviorRows[] = ['', '', '', '', '', $device, $devices[$device] ?? 0];

$periodStartPreviousCount = count($previousRows);
$summaryRows = [
    ['kind' => 'title', 'values' => ['KulToura | Executive Analytics Summary']],
    ['kind' => 'subtitle', 'values' => ['Office of Culture and Tourism | Municipality of Malvar, Batangas | Date-filtered report']],
    ['kind' => 'section', 'values' => ['SELECTED REPORT PERIOD', $periodLabel, $start->format('M j, Y'), $endExclusive->modify('-1 day')->format('M j, Y')]],
    ['kind' => 'header', 'values' => ['TOTAL PAGE VIEWS', $metrics['views'], 'UNIQUE TRACKED VISITORS', $metrics['active_users'], 'SEARCH QUERIES', $metrics['searches'], 'RECORDED RECOMMENDATION CLICKS', $metrics['recommendations']]],
    ['kind' => 'header', 'values' => ['RATINGS SUBMITTED', $metrics['ratings'], 'WRITTEN COMMENTS', $metrics['comments'], 'DESTINATION VIEWS', $metrics['destination_views'], 'AVERAGE DESTINATION RATING', $averageDestinationRating ? round($averageDestinationRating, 2) : 'No ratings']],
    ['kind' => 'section', 'values' => ['PERIOD-OVER-PERIOD COMPARISON']],
    ['kind' => 'header', 'values' => ['Metric', 'Selected Period', 'Previous Equal Period', 'Change']],
];
foreach ([['Page Views', 'views'], ['Searches', 'searches'], ['Recommendation Clicks', 'recommendations'], ['Ratings', 'ratings'], ['Written Comments', 'comments']] as [$label, $key]) {
    $current = $metrics[$key]; $previous = $previousMetrics[$key];
    if ($period === 'all') $summaryRows[] = [$label, $current, 'N/A', 'N/A'];
    else {
        $change = $previous === 0 ? ($current > 0 ? 'New activity' : '0%') : round(($current - $previous) / $previous * 100, 1) . '%';
        $summaryRows[] = [$label, $current, $previous, $change];
    }
}
$topDestination = '';
foreach ($destinations as $d) {
    $type = (string) $d['item_type']; $key = $type . ':' . (int) $d['id'];
    $count = $itemViews[$key] ?? 0;
    if ($count > 0 && (!$topDestination || $count > $topDestination['views'])) $topDestination = ['name' => $d['name'], 'views' => $count];
}
$topCulture = '';
foreach ($culturalItems as $item) if ($item['views'] > 0 && (!$topCulture || $item['views'] > $topCulture['views'])) $topCulture = ['name' => $item['name'], 'views' => $item['views']];
$topZeroResultSearch = null;
foreach ($searchTermRows as $searchRow) {
    if ((int) $searchRow['zero_results'] > 0 && (!$topZeroResultSearch || (int) $searchRow['zero_results'] > $topZeroResultSearch['zero_results'])) {
        $topZeroResultSearch = ['query' => (string) $searchRow['query'], 'zero_results' => (int) $searchRow['zero_results']];
    }
}
$summaryRows[] = ['kind' => 'section', 'values' => ['AUTOMATED INSIGHTS']];
$summaryRows[] = [$topDestination ? $topDestination['name'] . ' leads destination views with ' . $topDestination['views'] . ' views.' : 'No destination views were recorded in this period.'];
$summaryRows[] = [$topCulture ? $topCulture['name'] . ' leads cultural content views with ' . $topCulture['views'] . ' views.' : 'No cultural content views were recorded in this period.'];
$summaryRows[] = [$metrics['searches'] > 0 ? $metrics['searches'] . ' search queries were submitted.' . ($topZeroResultSearch ? ' Most zero-result query: "' . $topZeroResultSearch['query'] . '" (' . $topZeroResultSearch['zero_results'] . ' times).' : ' No zero-result searches were recorded.') : 'No searches were recorded in this period.'];
$summaryRows[] = [$metrics['ratings'] > 0 ? $metrics['ratings'] . ' visible ratings were submitted, including ' . $metrics['comments'] . ' written comments; the destination average is ' . ($averageDestinationRating ? number_format($averageDestinationRating, 2) . ' stars' : 'not available') . '.' : 'No visible ratings were submitted in this period.'];
$summaryRows[] = ['kind' => 'section', 'values' => ['REPORT NOTE']];
$summaryRows[] = ['kind' => 'subtitle', 'values' => ['Live records for the selected dates. Visitor totals combine registered account IDs and anonymous session IDs, not verified people. Raw IDs are temporary workbook labels. Device class is a best-effort browser user-agent inference; older or missing records may show Unknown. Favorite removals are not retained in save history. See Report Filters for each metric source.']];

$filterRows = [['kind' => 'title', 'values' => ['KULTOURA | EXPORT DATE RANGE']], ['kind' => 'subtitle', 'values' => ['All report data is already filtered to these dates. Change the analytics date filter and export again to create a different range.']], ['kind' => 'section', 'values' => ['Selected reporting period']], ['kind' => 'header', 'values' => ['Selected period', $periodLabel]], ['Start date', $start->format('Y-m-d')], ['End date', $endExclusive->modify('-1 day')->format('Y-m-d')], ['Effective start date', $start->format('Y-m-d')], ['Effective end date', $endExclusive->modify('-1 day')->format('Y-m-d')], ['kind' => 'section', 'values' => ['Metric source and interpretation notes']], ['Executive page views', 'page_views table, excluding the demo seed referrer.'], ['Search counts', 'search_log; the User Behavior sheet counts activity events from user_activity_log.'], ['Destination and cultural views', 'item_views; unique visitors/readers are distinct registered account IDs or anonymous session hashes in user_activity_log.'], ['Reviews and ratings', 'Visible and admin demo item_reviews are included, within the selected dates.'], ['Raw activity and behavior', 'user_activity_log events; device class is inferred from the browser user-agent and may be approximate. Older or missing records can show Unknown.'], ['Saves / downloads', 'Existing favorite records created in range plus tracked download events. Removed favorite records are not retained.'], ['Comparison', $period === 'all' ? 'All-time reports have no prior equal-length period; comparison cells show N/A.' : 'Compared with the immediately preceding period of equal length.'], ['Privacy', 'Raw Data uses temporary anonymized labels instead of account IDs.']];
$searchAnalyticsRows = [['kind' => 'title', 'values' => ['KulToura | Search Analytics']], ['kind' => 'subtitle', 'values' => ['Search query counts and result outcomes from search_log | ' . $periodLabel]], ['kind' => 'header', 'values' => ['Search Query', 'Searches', 'Zero-Result Searches', 'Searches With Results', 'Result Rate']]];
foreach ($searchTermRows as $searchRow) {
    $searchCount = (int) $searchRow['searches'];
    $searchAnalyticsRows[] = [(string) $searchRow['query'], $searchCount, (int) $searchRow['zero_results'], (int) $searchRow['with_results'], $searchCount ? round((int) $searchRow['with_results'] / $searchCount * 100, 1) . '%' : '0%'];
}
$searchZeroTotal = array_sum(array_map(static fn($row) => (int) $row['zero_results'], $searchTermRows));
$recommendationRows = [['kind' => 'title', 'values' => ['KulToura | Tourism Planning Recommendations']], ['kind' => 'subtitle', 'values' => ['Rule-based suggestions based on live activity for ' . $periodLabel . '.']], ['kind' => 'header', 'values' => ['Area', 'Observed Result', 'Suggested Recommendation', 'Priority', 'Basis']],
    ['Destination promotion', $topDestination ? $topDestination['name'] . ' had ' . $topDestination['views'] . ' views.' : 'No destination views recorded.', 'Review listing quality, photos, descriptions, and cross-promotion through related destinations.', 'High', 'Destination views'],
    ['Cultural preservation', $topCulture ? $topCulture['name'] . ' had ' . $topCulture['views'] . ' views.' : 'No cultural content views recorded.', 'Consider refreshing content, adding local stories or multimedia, and featuring it on the homepage.', 'High', 'Cultural content views'],
    ['Recommended content', $metrics['recommendations'] . ' recommendation clicks.', 'Review which recommended destinations and cultural content receive clicks; refine relevance using visitor interests and engagement.', 'Medium', 'Recommendation clicks'],
    ['Search experience', $metrics['searches'] . ' searches; ' . $searchZeroTotal . ' returned no results.' . ($topZeroResultSearch ? ' Most frequent: ' . $topZeroResultSearch['query'] . ' (' . $topZeroResultSearch['zero_results'] . ').' : ''), 'Review the listed zero-result queries and update keywords or content tagging where relevant.', 'Medium', 'Search Analytics'],
    ['Visitor feedback', $metrics['ratings'] . ' visible ratings and ' . $metrics['comments'] . ' written comments; destination average ' . ($averageDestinationRating ? number_format($averageDestinationRating, 2) . ' stars.' : 'not available.'), 'Review written comments and recurring concerns; coordinate with relevant establishments when appropriate.', 'Medium', 'Ratings and comments'],
];

$sheets = [
    'Report Filters' => ['rows' => $filterRows, 'widths' => [30, 44]],
    'Executive Summary' => ['rows' => $summaryRows, 'widths' => [34, 24, 34, 24, 28, 20, 34, 24]],
    'Raw Data' => ['rows' => $rawRows, 'widths' => [16, 24, 20, 28, 42, 16], 'header' => 3],
    'Engagement Analytics' => ['rows' => $engagementRows, 'widths' => [18, 20, 16, 14, 14, 20, 20, 26], 'header' => 3],
    'Destination Daily Data' => ['rows' => $destinationDailyRows, 'widths' => [18, 38, 14, 20, 18, 14], 'header' => 3],
    'Content Daily Data' => ['rows' => $contentDailyRows, 'widths' => [18, 38, 22, 14, 18, 18], 'header' => 3],
    'Destination Analytics' => ['rows' => $destinationSheetRows, 'widths' => [38, 20, 42, 14, 20, 18, 14], 'header' => 3],
    'Cultural Analytics' => ['rows' => $culturalSheetRows, 'widths' => [42, 24, 14, 18, 18, 18], 'header' => 3],
    'Search Analytics' => ['rows' => $searchAnalyticsRows, 'widths' => [42, 14, 24, 24, 16], 'header' => 3],
    'User Behavior' => ['rows' => $behaviorRows, 'widths' => [34, 16, 24, 58, 4, 16, 14], 'header' => 3],
    'Recommendations' => ['rows' => $recommendationRows, 'widths' => [26, 40, 82, 14, 24], 'header' => 3],
];
kt_export_write_xlsx($sheets);
