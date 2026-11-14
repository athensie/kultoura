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
$reviewsByRating = [
    1 => ['The visit was disappointing and the information here did not match what I found on arrival.', 'This stop was not worth the time for me. Some important details were missing.', 'I had a frustrating experience and would not choose this again.'],
    2 => ['There were a few good parts, but the visit did not meet my expectations.', 'The place has potential, though the facilities and details need improvement.', 'It was okay, but I ran into issues that made the trip less enjoyable.'],
    3 => ['An average visit overall. There were things I liked and a few things to improve.', 'It was a pleasant enough stop, though it did not stand out from other options.', 'Some useful details, but I still had questions before visiting.'],
    4 => ['A good experience overall. The details were helpful and I would consider coming back.', 'I enjoyed this stop. A couple of small improvements would make it even better.', 'A solid choice for the area, with a welcoming experience and useful information.'],
    5 => ['A wonderful experience and an easy addition to my trip. I would happily recommend it.', 'Everything came together nicely for a memorable visit. I would return.', 'A great local find with a lovely experience from start to finish.'],
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
                $texts = $reviewsByRating[$rating];
                $reviewText = $texts[($seed + $index) % count($texts)];
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
