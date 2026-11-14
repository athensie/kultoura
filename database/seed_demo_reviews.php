<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('KULTOURA_ENABLE_DEMO_REVIEWS') !== '1') {
    fwrite(STDERR, "Set KULTOURA_ENABLE_DEMO_REVIEWS=1 to seed clearly labeled demo reviews.\n");
    exit(1);
}

require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/reviews.php';

if (!kt_reviews_ensure_schema($conn)) {
    fwrite(STDERR, "Could not initialize the reviews tables.\n");
    exit(1);
}

$catalogQueries = [
    'nature' => "SELECT destination_id AS item_id FROM destination WHERE status='active' AND category='nature'",
    'resort' => "SELECT destination_id AS item_id FROM destination WHERE status='active' AND category='resort'",
    'industry' => "SELECT destination_id AS item_id FROM destination WHERE status='active' AND category='industry'",
    'accommodation' => "SELECT destination_id AS item_id FROM destination WHERE status='active' AND category='accommodation'",
    'church' => "SELECT destination_id AS item_id FROM destination WHERE status='active' AND category='church'",
    'service' => "SELECT destination_id AS item_id FROM destination WHERE status='active' AND category='service'",
    'restaurant' => "SELECT restaurant_id AS item_id FROM restaurants WHERE status='active'",
    'product' => "SELECT product_id AS item_id FROM products WHERE status='active'",
    'fiesta' => "SELECT fiesta_id AS item_id FROM fiestas WHERE status='active'",
];
$firstNames = ['Alex', 'Mika', 'Jordan', 'Sam', 'Taylor', 'Jamie', 'Riley', 'Kai', 'Casey', 'Avery', 'Noel', 'Cameron', 'Dani', 'Robin', 'Patricia', 'Gabriel', 'Morgan', 'Jules', 'Elena', 'Marco'];
$lastNames = ['Reyes', 'Santos', 'Cruz', 'Rivera', 'Garcia', 'Flores', 'Mendoza', 'Navarro', 'Ramos', 'Bautista', 'Lim', 'Villanueva', 'Castillo', 'Lopez', 'Aquino', 'Torres', 'Santiago', 'Dela Cruz'];
$reviewContexts = [
    'nature' => ['en' => 'the trail and outdoor facilities', 'tl' => 'trail at mga pasilidad sa paligid'],
    'resort' => ['en' => 'the resort rooms, amenities, and service', 'tl' => 'mga kuwarto, pasilidad, at serbisyo ng resort'],
    'industry' => ['en' => 'the local workshop and its visitor information', 'tl' => 'lokal na pagawaan at impormasyong pangbisita'],
    'accommodation' => ['en' => 'the room, cleanliness, and check-in', 'tl' => 'kuwarto, kalinisan, at pag-check in'],
    'church' => ['en' => 'the church grounds and visitor information', 'tl' => 'bakuran ng simbahan at impormasyong pangbisita'],
    'service' => ['en' => 'the visitor service and staff guidance', 'tl' => 'serbisyo para sa bisita at gabay ng mga staff'],
    'restaurant' => ['en' => 'the food, service, and dining area', 'tl' => 'pagkain, serbisyo, at kainan'],
    'product' => ['en' => 'the local product, quality, and packaging', 'tl' => 'lokal na produkto, kalidad, at pagkakabalot'],
    'fiesta' => ['en' => 'the event program, activities, and crowd arrangements', 'tl' => 'programa, mga aktibidad, at pagsasaayos ng mga tao sa event'],
];
$reviewTemplates = [
    1 => [
        'en' => 'I was disappointed with %s. Some important details or basic arrangements need attention.',
        'tl' => 'Hindi ako nasiyahan sa %s. May mahahalagang detalye o pangunahing kaayusan pang kailangang ayusin.',
    ],
    2 => [
        'en' => 'There were a few good points about %s, but the overall experience fell short of expectations.',
        'tl' => 'May ilang magandang bahagi ang %s, pero hindi naabot ng kabuuang karanasan ang inaasahan ko.',
    ],
    3 => [
        'en' => 'My experience with %s was average. I enjoyed some parts, though there is room to improve.',
        'tl' => 'Katamtaman lang ang naging karanasan ko sa %s. May nagustuhan ako, pero may puwede pang pagbutihin.',
    ],
    4 => [
        'en' => 'I had a good experience with %s. A few small improvements would make it even better.',
        'tl' => 'Maganda ang naging karanasan ko sa %s. Mas gaganda pa ito kung maaayos ang ilang maliliit na bagay.',
    ],
    5 => [
        'en' => 'I really enjoyed %s and would gladly recommend it to other visitors.',
        'tl' => 'Nagustuhan ko talaga ang %s at mairerekomenda ko ito sa ibang mga bisita.',
    ],
];
// Balanced overall: varied criticism and praise, with an average just above four stars.
$ratingPlan = array_merge(array_fill(0, 20, 5), array_fill(0, 17, 4), array_fill(0, 8, 3), array_fill(0, 4, 2), [1]);
$ratingAdjustments = [
    0 => [[5, 3, 10]],
    1 => [[5, 4, 5], [4, 3, 5]],
    2 => [],
    3 => [[3, 4, 5], [4, 5, 4]],
    4 => [[3, 4, 10], [4, 5, 5]],
];
$firstReservedUserId = 2000000001;
$reservedUserCount = 50;
$upsert = $conn->prepare(
    "INSERT INTO item_reviews (user_id,item_type,item_id,display_name,rating,review_text,status,created_at)
     VALUES (?,?,?,?,?,?,'demo',?)
     ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),rating=VALUES(rating),review_text=VALUES(review_text),status='demo',created_at=VALUES(created_at)"
);
$countOtherReviews = $conn->prepare("SELECT COUNT(*) total FROM item_reviews WHERE item_type=? AND item_id=? AND user_id NOT BETWEEN ? AND ? AND status IN ('visible','demo')");
$deleteStale = $conn->prepare("DELETE FROM item_reviews WHERE item_type=? AND item_id=? AND user_id BETWEEN ? AND ? AND status='demo' AND user_id>=?");
if (!$upsert || !$countOtherReviews || !$deleteStale) {
    fwrite(STDERR, "Could not prepare demo review statements.\n");
    exit(1);
}

$inserted = 0;
$itemCounts = [];
$limitedItems = 0;
$conn->begin_transaction();
try {
    // Keep legacy seeded rows admin-only and remove their old name prefix.
    $legacySamples = $conn->query("SELECT review_id,display_name FROM item_reviews WHERE user_id BETWEEN 2000000001 AND 2000000050 AND status IN ('visible','demo')");
    $legacyUpdate = $conn->prepare("UPDATE item_reviews SET display_name=?,status='demo' WHERE review_id=?");
    if (!$legacySamples || !$legacyUpdate) throw new RuntimeException('Could not normalize existing demo reviews.');
    while ($legacy = $legacySamples->fetch_assoc()) {
        $displayName = preg_replace('/^Sample\\s+.*?\\s+(?=[\\pL\\pN])/u', '', (string)$legacy['display_name']) ?? (string)$legacy['display_name'];
        $reviewId = (int)$legacy['review_id'];
        $legacyUpdate->bind_param('si', $displayName, $reviewId);
        if (!$legacyUpdate->execute()) throw new RuntimeException('Could not normalize an existing demo review.');
    }
    $legacyUpdate->close();
    foreach ($catalogQueries as $itemType => $sql) {
        $result = $conn->query($sql);
        if (!$result) throw new RuntimeException('Could not read active items for ' . $itemType . '.');
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $itemCounts[$itemType] = count($items);
        foreach ($items as $item) {
            $itemId = (int)$item['item_id'];
            $lastReservedUserId = $firstReservedUserId + $reservedUserCount - 1;
            $countOtherReviews->bind_param('siii', $itemType, $itemId, $firstReservedUserId, $lastReservedUserId);
            if (!$countOtherReviews->execute()) throw new RuntimeException('Could not count existing reviews.');
            $otherCount = (int)$countOtherReviews->get_result()->fetch_assoc()['total'];
            $sampleCount = max(0, 50 - $otherCount);
            if ($otherCount >= 50) $limitedItems++;

            $seed = crc32($itemType . ':' . $itemId);
            $itemRatings = $ratingPlan;
            $originalRatings = $itemRatings;
            foreach ($ratingAdjustments[$seed % count($ratingAdjustments)] as [$fromRating, $toRating, $limit]) {
                $changed = 0;
                foreach ($originalRatings as $ratingIndex => $originalRating) {
                    if ($originalRating === $fromRating && $changed < $limit) {
                        $itemRatings[$ratingIndex] = $toRating;
                        $changed++;
                    }
                }
            }
            mt_srand($seed);
            shuffle($itemRatings);
            $nameOffset = $seed % count($firstNames);
            for ($index = 0; $index < $sampleCount; $index++) {
                $userId = $firstReservedUserId + $index;
                $rating = $itemRatings[$index];
                $name = $firstNames[($nameOffset + $index) % count($firstNames)] . ' ' . $lastNames[($seed + $index * 7) % count($lastNames)];
                $language = (($seed + $index) % 2 === 0) ? 'tl' : 'en';
                $reviewText = sprintf($reviewTemplates[$rating][$language], $reviewContexts[$itemType][$language]);
                // Seed across two months so the admin trend view can compare periods.
                $daysAgo = $index < (int)ceil($sampleCount / 2)
                    ? 1 + (($seed + $index * 11) % 29)
                    : 31 + (($seed + $index * 13) % 29);
                $createdAt = (new DateTimeImmutable())->modify('-' . $daysAgo . ' days')->format('Y-m-d H:i:s');
                $upsert->bind_param('isisiss', $userId, $itemType, $itemId, $name, $rating, $reviewText, $createdAt);
                if (!$upsert->execute()) throw new RuntimeException('Could not add demo review for ' . $itemType . ' #' . $itemId . '.');
                $inserted++;
            }
            $firstStaleUserId = $firstReservedUserId + $sampleCount;
            $deleteStale->bind_param('siiii', $itemType, $itemId, $firstReservedUserId, $lastReservedUserId, $firstStaleUserId);
            if (!$deleteStale->execute()) throw new RuntimeException('Could not trim stale demo reviews.');
        }
    }
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    fwrite(STDERR, $error->getMessage() . " All changes were rolled back.\n");
    exit(1);
} finally {
    $upsert->close();
    $countOtherReviews->close();
    $deleteStale->close();
}

echo "Admin-only demo reviews inserted or refreshed: {$inserted}\n";
echo "Items already at the 50-review cap: {$limitedItems}\n";
foreach ($itemCounts as $type => $count) echo $type . ': ' . $count . " active items\n";
