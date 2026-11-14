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
    'nature' => "SELECT destination_id AS item_id,destination_name AS item_name FROM destination WHERE status='active' AND category='nature'",
    'resort' => "SELECT destination_id AS item_id,destination_name AS item_name FROM destination WHERE status='active' AND category='resort'",
    'industry' => "SELECT destination_id AS item_id,destination_name AS item_name FROM destination WHERE status='active' AND category='industry'",
    'accommodation' => "SELECT destination_id AS item_id,destination_name AS item_name FROM destination WHERE status='active' AND category='accommodation'",
    'church' => "SELECT destination_id AS item_id,destination_name AS item_name FROM destination WHERE status='active' AND category='church'",
    'service' => "SELECT destination_id AS item_id,destination_name AS item_name FROM destination WHERE status='active' AND category='service'",
    'restaurant' => "SELECT restaurant_id AS item_id,restaurant_name AS item_name FROM restaurants WHERE status='active'",
    'product' => "SELECT product_id AS item_id,product_name AS item_name FROM products WHERE status='active'",
    'fiesta' => "SELECT fiesta_id AS item_id,fiesta_name AS item_name FROM fiestas WHERE status='active'",
];

$sampleReviews = [
    'nature' => [
        'The nature listing was easy to understand and helped me plan a relaxing stop.',
        'Clear details and a welcoming description made this nature spot stand out.',
        'A useful listing for anyone looking to add an outdoor stop to their trip.',
    ],
    'resort' => [
        'The resort details were clear and made comparing places to stay much easier.',
        'A helpful listing with the kind of information I need when planning a break.',
        'This resort page was easy to browse and worth keeping on my shortlist.',
    ],
    'industry' => [
        'I liked learning about this local industry and its place in the community.',
        'The information made this an interesting stop to consider on a local tour.',
        'A clear and useful introduction to an important part of the area.',
    ],
    'accommodation' => [
        'The accommodation information was clear and useful for planning a stay.',
        'Helpful details made it easier to compare this place with other options.',
        'A straightforward listing that made planning overnight travel simpler.',
    ],
    'church' => [
        'The church listing gave a helpful introduction to a local cultural stop.',
        'I appreciated having clear details about this place in one listing.',
        'A meaningful stop to consider when exploring local history and culture.',
    ],
    'service' => [
        'This visitor service listing was easy to find and useful for trip planning.',
        'Clear information made this service feel approachable for visitors.',
        'A helpful local resource to keep in mind while exploring the area.',
    ],
    'restaurant' => [
        'The restaurant listing was inviting and made it easy to decide where to eat.',
        'I liked having the dining details together in one easy-to-read place.',
        'A useful food stop to add to a local itinerary.',
    ],
    'product' => [
        'The local product listing was interesting and easy to browse.',
        'I enjoyed learning about this product and its connection to the community.',
        'A nice local find to consider while exploring Malvar.',
    ],
    'fiesta' => [
        'The event listing made it easy to understand what the celebration is about.',
        'A lively local event that would be fun to include in a visit.',
        'Clear event information helped me plan around this community celebration.',
    ],
];
$reviewDates = [-3, -12, -40];
$ratings = [5, 4, 5];
$reviewerIds = [2000000001, 2000000002, 2000000003];
$reviewerNames = [
    'Alex Reyes', 'Mika Santos', 'Jordan Cruz', 'Sam Rivera', 'Taylor Garcia', 'Jamie Flores',
    'Riley Mendoza', 'Kai Navarro', 'Casey Ramos', 'Avery Dela Cruz', 'Noel Bautista', 'Cameron Lim',
    'Dani Villanueva', 'Robin Castillo', 'Patricia Lopez', 'Gabriel Aquino', 'Morgan Torres', 'Jules Santiago',
];
$upsert = $conn->prepare(
    "INSERT INTO item_reviews (user_id,item_type,item_id,display_name,rating,review_text,status,created_at)
     VALUES (?,?,?,?,?,?,'visible',?)
     ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),rating=VALUES(rating),review_text=VALUES(review_text),status='visible',created_at=VALUES(created_at)"
);
if (!$upsert) {
    fwrite(STDERR, "Could not prepare the demo review insert.\n");
    exit(1);
}

$inserted = 0;
$itemCounts = [];
$conn->begin_transaction();
try {
    foreach ($catalogQueries as $itemType => $sql) {
        $result = $conn->query($sql);
        if (!$result) {
            throw new RuntimeException('Could not read active items for ' . $itemType . '.');
        }
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $itemCounts[$itemType] = count($items);
        foreach ($items as $item) {
            $itemId = (int)$item['item_id'];
            foreach ($sampleReviews[$itemType] as $index => $reviewText) {
                $userId = $reviewerIds[$index];
                $nameOffset = abs(crc32($itemType . ':' . $itemId)) % count($reviewerNames);
                $displayName = 'Sample · ' . $reviewerNames[($nameOffset + $index) % count($reviewerNames)];
                $rating = $ratings[$index];
                $createdAt = (new DateTimeImmutable())->modify($reviewDates[$index] . ' days')->format('Y-m-d H:i:s');
                $upsert->bind_param('isisiss', $userId, $itemType, $itemId, $displayName, $rating, $reviewText, $createdAt);
                if (!$upsert->execute()) {
                    throw new RuntimeException('Could not add demo review for ' . $itemType . ' #' . $itemId . '.');
                }
                $inserted++;
            }
        }
    }
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    fwrite(STDERR, $error->getMessage() . " All inserts were rolled back.\n");
    exit(1);
} finally {
    $upsert->close();
}

echo "Sample demo reviews inserted or refreshed: {$inserted}\n";
foreach ($itemCounts as $type => $count) {
    echo $type . ': ' . $count . " active items\n";
}
