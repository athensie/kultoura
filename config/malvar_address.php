<?php
/*
 * One clean, specific address format for every listing:
 *     "<street / building>, Brgy. <Barangay>, Malvar, Batangas"
 * - kt_normalize_malvar_address(): cleans any typed or imported address text (removes Google plus codes,
 *   coordinates, zip codes, "Philippines", region names, duplicates; standardises the barangay name).
 * - kt_reverse_geocode_malvar(): looks up the street and barangay for a map pin (OpenStreetMap Nominatim),
 *   used by the address clean-up tool for listings whose text has no barangay.
 */
require_once __DIR__ . '/malvar_locations.php';

/** Lower-case spellings found in addresses → official barangay name. */
function kt_malvar_barangay_aliases(): array
{
    $aliases = [];
    foreach (kt_malvar_barangays() as $name) $aliases[mb_strtolower($name)] = $name;
    return $aliases + [
        'luta sur' => 'Luta Del Sur', 'luta norte' => 'Luta Del Norte',
        'san isidro west' => 'Bilucao',
        'san pedro i east' => 'San Pedro I', 'san pedro ii west' => 'San Pedro II',
        'san pedro 1' => 'San Pedro I', 'san pedro 2' => 'San Pedro II',
        'bagongpook' => 'Bagong Pook', 'pioquinto' => 'San Pioquinto',
    ];
}

/** Matches a single address part against the barangay names (longest first). */
function kt_match_barangay_part(string $part): ?string
{
    // The barangay mentioned FIRST wins ("Brgy. Santiago cor. San Andres" → Santiago);
    // at the same position the longer name wins ("San Pedro II" over "San Pedro I").
    $haystack = ' ' . mb_strtolower(preg_replace('/\s+/', ' ', $part)) . ' ';
    $best = null;
    foreach (kt_malvar_barangay_aliases() as $alias => $name) {
        if (!preg_match('/(^|[^a-z])' . preg_quote($alias, '/') . '($|[^a-z])/u', $haystack, $match, PREG_OFFSET_CAPTURE)) continue;
        $position = $match[0][1];
        if ($best === null || $position < $best[0] || ($position === $best[0] && mb_strlen($alias) > $best[1])) $best = [$position, mb_strlen($alias), $name];
    }
    return $best[2] ?? null;
}

/** True when an address part names a road ("San Andres Rd", "Santiago St"), not a barangay. */
function kt_is_road_part(string $part): bool
{
    return (bool) preg_match('/\b(rd|road|st|street|ave|avenue|hwy|highway|drive|dr|blvd|boulevard|lane|ln|extension|ext)\b\.?$/i', trim($part));
}

/**
 * The barangay an address is in, or null. Trusts, in order: a part that is only a barangay ("Bulihan",
 * "Brgy. Santiago"), a part with a Brgy./Barangay prefix inside it, then any non-road part naming one.
 * Roads named after barangays ("San Andres Rd") are not taken as the barangay.
 */
function kt_find_malvar_barangay(string $text): ?string
{
    $aliases = kt_malvar_barangay_aliases();
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $text))));
    foreach ($parts as $part) {
        $bare = mb_strtolower(trim(preg_replace('/^(brgy\.?|barangay|bgy\.?)\s+/i', '', $part), ' .'));
        if (isset($aliases[$bare])) return $aliases[$bare];
    }
    foreach ($parts as $part) {
        if (preg_match('/\b(brgy\.?|barangay|bgy\.?)\s+(.+)$/i', $part, $m) && ($name = kt_match_barangay_part($m[2]))) return $name;
    }
    foreach ($parts as $part) {
        if (!kt_is_road_part($part) && ($name = kt_match_barangay_part($part))) return $name;
    }
    return null;
}

/** Street / building part of an address: everything except plus codes, coordinates, barangay, town, province, country, zip. */
function kt_malvar_street_part(string $text, ?string $barangay): string
{
    $text = preg_replace('/\b[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}\b/i', '', $text);   // Google plus codes
    $text = preg_replace('/-?\d{1,3}\.\d{3,}\s*,\s*-?\d{1,3}\.\d{3,}/', '', $text);                           // "14.0123, 121.1567"
    $aliases = kt_malvar_barangay_aliases();
    $parts = [];
    foreach (preg_split('/\s*,\s*/', $text) as $part) {
        $part = trim(preg_replace('/\s+/', ' ', $part), " \t\n\r\0\x0B.-");
        $part = trim(preg_replace('/\b(4233|Philippines|Calabarzon|Region IV-?A|IV-?A)\b/i', '', $part));
        // Drop the town/province only when they stand alone ("Malvar", "Batangas"), not inside names ("Camella Malvar").
        if ($part === '' || preg_match('/^(malvar|batangas|malvar\s+batangas)$/i', $part)) continue;
        $bare = mb_strtolower(trim(preg_replace('/^(brgy\.?|barangay|bgy\.?)\s+/i', '', $part), ' .'));
        if (isset($aliases[$bare])) continue;                                                // the barangay itself
        // "Lima Technology Center Brgy. Santiago" → "Lima Technology Center" (but keep roads like "San Isidro Barangay Rd")
        if (!kt_is_road_part($part)) $part = trim(preg_replace('/\s*\b(brgy\.?|barangay|bgy\.?)\s+[a-z .]+$/i', '', $part));
        if ($part !== '' && !in_array(mb_strtolower($part), array_map('mb_strtolower', $parts), true)) $parts[] = $part;
    }
    return implode(', ', $parts);
}

function kt_compose_malvar_address(string $street, ?string $barangay): string
{
    $pieces = array_filter([trim($street), $barangay ? 'Brgy. ' . $barangay : '', 'Malvar, Batangas']);
    return implode(', ', $pieces);
}

/** Clean any address text into the standard format (no network calls). */
function kt_normalize_malvar_address(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    $barangay = kt_find_malvar_barangay($raw);
    return kt_compose_malvar_address(kt_malvar_street_part($raw, $barangay), $barangay);
}

/**
 * Street and barangay for a map pin, from OpenStreetMap Nominatim (max one request per second).
 * Returns ['street' => string, 'barangay' => ?string] or null if the lookup failed.
 */
function kt_reverse_geocode_malvar(float $lat, float $lng): ?array
{
    static $lastCall = 0.0;
    $wait = 1.1 - (microtime(true) - $lastCall);
    if ($wait > 0) usleep((int) ($wait * 1000000));
    $lastCall = microtime(true);
    $url = 'https://nominatim.openstreetmap.org/reverse?format=json&addressdetails=1&zoom=18&lat=' . rawurlencode((string) $lat) . '&lon=' . rawurlencode((string) $lng);
    $context = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: KulToura address clean-up (Malvar tourism site)\r\nAccept-Language: en\r\n"]]);
    $body = @file_get_contents($url, false, $context);
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data) || empty($data['address'])) return null;
    $address = $data['address'];
    $barangay = null;
    foreach (['village', 'suburb', 'quarter', 'neighbourhood', 'hamlet', 'city_district'] as $field) {
        if (!empty($address[$field]) && ($barangay = kt_match_barangay_part((string) $address[$field]))) break;
    }
    // Only the road and house number: landmarks near the pin (chapels, shops) are not the listing itself.
    return ['street' => trim(($address['house_number'] ?? '') . ' ' . ($address['road'] ?? '')), 'barangay' => $barangay];
}

/**
 * Cleans every listing address. Uses each listing's map pin to fill in a missing barangay/street.
 * Keeps the original text in address_before_cleanup the first time. Returns counts.
 */
function kt_fix_listing_addresses(mysqli $conn, bool $useMapLookup = true): array
{
    $tables = [
        ['destination', 'destination_id', 'address', true],
        ['restaurants', 'restaurant_id', 'address', true],
        ['products', 'product_id', 'location', false],
        ['fiestas', 'fiesta_id', 'location', false],
    ];
    $counts = ['checked' => 0, 'changed' => 0, 'looked_up' => 0, 'still_missing_barangay' => 0];
    foreach ($tables as [$table, $idColumn, $addressColumn, $hasPin]) {
        $exists = $conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$exists || $exists->num_rows === 0) continue;
        $backup = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'address_before_cleanup'");
        if ($backup && $backup->num_rows === 0) $conn->query("ALTER TABLE `{$table}` ADD COLUMN address_before_cleanup VARCHAR(255) NULL");
        $pinSql = $hasPin ? ', latitude, longitude' : ', NULL latitude, NULL longitude';
        $rows = $conn->query("SELECT {$idColumn} id, {$addressColumn} address{$pinSql} FROM `{$table}`");
        if (!$rows) continue;
        foreach ($rows->fetch_all(MYSQLI_ASSOC) as $row) {
            $counts['checked']++;
            $original = (string) ($row['address'] ?? '');
            $barangay = kt_find_malvar_barangay($original);
            $street = kt_malvar_street_part($original, $barangay);
            if ($useMapLookup && ($barangay === null || $street === '') && is_numeric($row['latitude']) && is_numeric($row['longitude']) && (float) $row['latitude'] != 0.0) {
                $lookup = kt_reverse_geocode_malvar((float) $row['latitude'], (float) $row['longitude']);
                $counts['looked_up']++;
                if ($lookup) {
                    $barangay = $barangay ?? $lookup['barangay'];
                    if ($street === '') $street = $lookup['street'];
                }
            }
            if ($barangay === null) $counts['still_missing_barangay']++;
            $clean = kt_compose_malvar_address($street, $barangay);
            if ($clean === $original) continue;
            $stmt = $conn->prepare("UPDATE `{$table}` SET address_before_cleanup = COALESCE(address_before_cleanup, {$addressColumn}), {$addressColumn} = ? WHERE {$idColumn} = ?");
            $id = (int) $row['id'];
            $stmt->bind_param('si', $clean, $id);
            $stmt->execute();
            $stmt->close();
            $counts['changed']++;
        }
    }
    return $counts;
}

/** How many listing addresses still need attention (no barangay, plus codes, or not in the standard format). */
function kt_listing_address_health(mysqli $conn): array
{
    $health = ['total' => 0, 'missing_barangay' => 0, 'not_standard' => 0];
    foreach ([['destination', 'address'], ['restaurants', 'address'], ['products', 'location'], ['fiestas', 'location']] as [$table, $column]) {
        $exists = $conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$exists || $exists->num_rows === 0) continue;
        $rows = $conn->query("SELECT {$column} address FROM `{$table}` WHERE status = 'active'");
        if (!$rows) continue;
        foreach ($rows->fetch_all(MYSQLI_ASSOC) as $row) {
            $address = (string) ($row['address'] ?? '');
            $health['total']++;
            if (kt_find_malvar_barangay($address) === null) $health['missing_barangay']++;
            if ($address !== kt_normalize_malvar_address($address) || $address === '') $health['not_standard']++;
        }
    }
    return $health;
}
