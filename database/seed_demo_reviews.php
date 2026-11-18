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
    'nature' => "SELECT destination_id AS item_id, destination_name AS item_name FROM destination WHERE status='active' AND category='nature'",
    'resort' => "SELECT destination_id AS item_id, destination_name AS item_name FROM destination WHERE status='active' AND category='resort'",
    'industry' => "SELECT destination_id AS item_id, destination_name AS item_name FROM destination WHERE status='active' AND category='industry'",
    'accommodation' => "SELECT destination_id AS item_id, destination_name AS item_name FROM destination WHERE status='active' AND category='accommodation'",
    'church' => "SELECT destination_id AS item_id, destination_name AS item_name FROM destination WHERE status='active' AND category='church'",
    'service' => "SELECT destination_id AS item_id, destination_name AS item_name FROM destination WHERE status='active' AND category='service'",
    'restaurant' => "SELECT restaurant_id AS item_id, restaurant_name AS item_name, category AS item_category FROM restaurants WHERE status='active'",
    'product' => "SELECT product_id AS item_id, product_name AS item_name FROM products WHERE status='active'",
    'fiesta' => "SELECT fiesta_id AS item_id, fiesta_name AS item_name FROM fiestas WHERE status='active'",
];
$firstNames = ['Alex', 'Mika', 'Jordan', 'Sam', 'Taylor', 'Jamie', 'Riley', 'Kai', 'Casey', 'Avery', 'Noel', 'Cameron', 'Dani', 'Robin', 'Patricia', 'Gabriel', 'Morgan', 'Jules', 'Elena', 'Marco'];
$lastNames = ['Reyes', 'Santos', 'Cruz', 'Rivera', 'Garcia', 'Flores', 'Mendoza', 'Navarro', 'Ramos', 'Bautista', 'Lim', 'Villanueva', 'Castillo', 'Lopez', 'Aquino', 'Torres', 'Santiago', 'Dela Cruz'];
$reviewOpeners = [
    'en' => [
        'I stopped by %s while I was out in Malvar.',
        'This was my first visit to %s, with my family.',
        'A friend and I checked out %s over the weekend.',
        'We made time to visit %s during our trip around town.',
        'I went to %s on a recent afternoon.',
        'Our group dropped in at %s before heading home.',
        'I finally got to see %s for myself.',
        'We visited %s on a day out nearby.',
    ],
    'tl' => [
        'Dumaan kami sa %s habang namamasyal sa Malvar.',
        'First time kong bumisita sa %s kasama ang pamilya ko.',
        'Nagpunta kami ng kaibigan ko sa %s nitong weekend.',
        'Isinama namin sa lakad namin ang pagbisita sa %s.',
        'Napasyal ako sa %s isang hapon.',
        'Kasama ko ang barkada nang dumaan kami sa %s.',
        'Matagal ko nang gustong makita nang personal ang %s.',
        'Bumisita kami sa %s bago umuwi.',
    ],
];
$reviewDetails = [
    'nature' => [
        'positive' => ['The scenery made the walk worthwhile, and the route was easy to follow.', 'I liked having a quiet outdoor stop; there were several nice spots to pause and take in the view.', 'The walk felt refreshing, and the surroundings were the best part of the visit.'],
        'mixed' => ['The view was lovely, though a few parts of the path could use clearer signs and a little more upkeep.', 'I enjoyed being outdoors, but some sections were less comfortable to walk than I expected.', 'It was peaceful once we reached the nicer viewpoints; the route there could be better maintained.'],
        'negative' => ['The path was harder to follow than it should be, and some basic upkeep would make the visit safer and more enjoyable.', 'I wanted to enjoy the scenery, but litter and rough spots along the route took away from the visit.', 'The area has potential, though the trail and visitor information need more attention.'],
        'positive_tl' => ['Presko ang hangin at sulit ang lakad dahil maganda ang tanawin sa dulo.', 'Nagustuhan ko na may tahimik na lugar para magpahinga at mag-picture habang naglalakad.', 'Nakakagaan ng pakiramdam ang ikot namin dito; maayos din sundan ang daan.'],
        'mixed_tl' => ['Maganda ang paligid, pero may ilang bahagi ng daan na kailangan ng dagdag na linis at palatandaan.', 'Na-enjoy ko ang nature walk, pero medyo mahirap lakaran ang ibang parte.', 'Tahimik at presko rito; sana lang mas maalagaan ang trail.'],
        'negative_tl' => ['Nalito kami sa dadaanan at kulang ang mga karatula para sa bisita.', 'Sayang ang ganda ng lugar dahil may kalat at hindi pantay ang ilang bahagi ng trail.', 'Kailangan pang ayusin ang daan at mas malinaw na gabay bago ito maging komportableng pasyalan.'],
    ],
    'resort' => [
        'positive' => ['The room was comfortable, the amenities gave us plenty to do, and staff were easy to approach.', 'It was a relaxing stay, especially having the facilities close by instead of needing to leave the property.', 'We had a smooth check-in and found the room comfortable after a full day out.'],
        'mixed' => ['We enjoyed the facilities, but the room could have been cleaner for the price.', 'The stay was pleasant overall, though one amenity was not ready when we wanted to use it.', 'Staff were kind, but check-in took longer than expected and the room felt a little tired.'],
        'negative' => ['Our room was not ready on time, and the facilities did not match what we expected from the listing.', 'We had trouble getting help with a room issue, which made it difficult to relax during the stay.', 'The amenities looked promising, but cleanliness and service both need more consistent attention.'],
        'positive_tl' => ['Komportable ang kuwarto at naging maayos ang pag-asikaso sa amin mula check-in hanggang alis.', 'Nakarelax kami rito; hindi na kailangang lumayo para magamit ang mga pasilidad.', 'Malinis ang tulugan at madaling lapitan ang staff kapag may tanong kami.'],
        'mixed_tl' => ['Nag-enjoy kami sa amenities, pero may parte ng kuwarto na kailangan pang linisin nang mabuti.', 'Okay ang stay namin pero hindi pa handa ang isang pasilidad noong gusto sana naming gamitin.', 'Mabait ang staff, pero natagalan ang check-in at medyo luma na ang kuwarto.'],
        'negative_tl' => ['Hindi pa handa ang kuwarto pagdating namin at kinailangan pa naming maghintay bago makapagpahinga.', 'Nahirapan kaming magpatulong tungkol sa problema sa kuwarto kaya hindi naging kampante ang stay.', 'Kulang sa linis at maayos na maintenance ang mga pasilidad na nakita namin.'],
    ],
    'industry' => [
        'positive' => ['The visit gave us a better feel for the work done here, and the staff explained things in a way that was easy to follow.', 'I appreciated seeing local work up close instead of only reading a short description about it.', 'The stop was interesting and informative; I left with a better understanding of the place.'],
        'mixed' => ['There was plenty to learn, but I would have liked more context about the process and what visitors can see.', 'The visit was interesting in parts, though we were not sure where to go or what was open to visitors.', 'I liked the local angle, but the information on site could be clearer and easier to find.'],
        'negative' => ['We arrived hoping to learn about the local work, but there was very little visitor information available.', 'It was difficult to tell what visitors were allowed to see, and no one was available to guide us.', 'The place could be interesting, but the lack of clear directions and explanations made the visit frustrating.'],
        'positive_tl' => ['Mas naintindihan namin kung paano ginagawa ang mga produkto rito dahil malinaw magpaliwanag ang nag-asikaso.', 'Iba ang dating kapag nakikita mo mismo ang lokal na trabaho at hindi lang binabasa sa karatula.', 'Interesting ang stop na ito at may bagong kaalaman kaming naiuwi.'],
        'mixed_tl' => ['Maraming puwedeng matutunan, pero bitin ang paliwanag tungkol sa proseso at sa puwedeng makita ng bisita.', 'May interesting na bahagi ang tour pero hindi malinaw kung saan kami dapat magsimula.', 'Nagustuhan ko ang lokal na tema; dagdagan sana ang impormasyon sa mismong lugar.'],
        'negative_tl' => ['Walang gaanong impormasyong mabasa pagdating namin kaya hindi namin alam kung ano ang puwedeng bisitahin.', 'Walang mahanap na gagabay sa amin at hindi rin malinaw kung aling bahagi ang bukas sa publiko.', 'Sayang ang potensyal ng lugar dahil kulang ang paliwanag at direksyon para sa mga bisita.'],
    ],
    'accommodation' => [
        'positive' => ['Check-in was straightforward, the room was tidy, and I slept well after a long day out.', 'The room was comfortable and clean, which was exactly what we needed for an overnight stay.', 'We settled in quickly and appreciated how easy it was to get the basics we needed.'],
        'mixed' => ['The room was comfortable enough, though the bathroom could have been cleaner and check-in took a while.', 'I slept fine, but a couple of small maintenance issues should be fixed before the next guest arrives.', 'The location worked for us, although noise from outside made it harder to rest.'],
        'negative' => ['Our room was not properly cleaned when we checked in, and it took too long to get the issue addressed.', 'The noise made it difficult to sleep, and the room needed more attention before it was ready for guests.', 'Check-in was confusing and the room did not feel prepared for our arrival.'],
        'positive_tl' => ['Mabilis ang check-in, maayos ang kuwarto at nakapagpahinga kami nang mabuti.', 'Malinis ang tutuluyan at kumpleto ang basic na kailangan namin para sa isang gabing stay.', 'Hindi na kami nahirapan pagdating; nakapag-ayos agad at nakapagpahinga.'],
        'mixed_tl' => ['Komportable naman ang kuwarto pero sana mas nalinis pa ang banyo bago kami nag-check in.', 'Nakakatulog naman kami pero may ilang sirang gamit na dapat kumpunihin.', 'Okay ang lokasyon pero maingay sa gabi kaya putol-putol ang tulog ko.'],
        'negative_tl' => ['Hindi malinis ang kuwarto nang dumating kami at natagalan bago ito naasikaso.', 'Maingay sa gabi at hindi mukhang handa ang kuwarto para sa bagong bisita.', 'Magulo ang check-in at kinailangan naming magtanong nang ilang beses bago makakuha ng tulong.'],
    ],
    'church' => [
        'positive' => ['The church has a peaceful atmosphere, and I appreciated being able to take a quiet moment during our visit.', 'We admired the architecture and found the grounds calm and welcoming.', 'It was a meaningful stop for our group, with a lovely setting and room to pause respectfully.'],
        'mixed' => ['The church itself was lovely, though visitor guidance about the grounds and service times could be clearer.', 'I appreciated the quiet, but some areas around the grounds could use better upkeep.', 'The visit was peaceful, although we were unsure which areas were open to visitors.'],
        'negative' => ['We could not find clear information about visiting hours, so our group was unsure where we could go.', 'The grounds needed attention, and it was hard to tell how visitors should respectfully move through the site.', 'I hoped to learn more about the church, but there was little information available for visitors.'],
        'positive_tl' => ['Payapa ang paligid at magandang huminto rito para magdasal o magpahinga sandali.', 'Napansin namin ang ganda ng pagkakagawa ng simbahan at maayos ang bakuran.', 'Tahimik at magalang ang atmosphere; nag-enjoy kaming bumisita bilang pamilya.'],
        'mixed_tl' => ['Maganda ang simbahan pero sana may mas malinaw na paalala kung aling bahagi ang bukas sa bisita.', 'Nagustuhan ko ang katahimikan; may ilang bahagi lang ng bakuran na kailangan ng dagdag na linis.', 'Maayos ang pagbisita pero hindi namin alam kung saan puwedeng pumunta habang may misa.'],
        'negative_tl' => ['Walang makita kaming impormasyon tungkol sa oras ng pagbisita kaya hindi namin alam kung kailan babalik.', 'Hindi maayos ang ilang bahagi ng bakuran at kulang ang gabay para sa mga bisita.', 'Gusto sana naming malaman ang kasaysayan ng simbahan pero halos walang mabasang impormasyon.'],
    ],
    'service' => [
        'positive' => ['The staff answered our questions clearly and pointed us in the right direction without making us feel rushed.', 'Getting help was straightforward, and the person assisting us was patient and practical.', 'I appreciated how clearly the staff explained what to do next.'],
        'mixed' => ['The staff were polite once we reached them, though the wait and instructions could both be improved.', 'We eventually got the help we needed, but it took a couple of tries to find the right person.', 'The advice was useful, although clearer signs would have saved us time.'],
        'negative' => ['We had a hard time finding anyone who could answer a basic visitor question.', 'The instructions we received were unclear, and we were sent to the wrong place first.', 'It took too long to get help, and the response did not resolve our question.'],
        'positive_tl' => ['Mabilis at malinaw sumagot ang staff; hindi kami pabalik-balik sa paghahanap ng pupuntahan.', 'Maayos kausap ang tumulong sa amin at binigyan kami ng praktikal na direksyon.', 'Nagustuhan ko ang pasensyang sumagot sila kahit marami kaming tanong.'],
        'mixed_tl' => ['Magalang naman ang staff pero natagalan kami sa pila bago may nakausap.', 'Nakuha rin namin ang kailangan pero ilang beses kaming naipasa sa ibang tao.', 'Nakatulong ang paliwanag; mas makakatipid sana kami ng oras kung mas malinaw ang mga karatula.'],
        'negative_tl' => ['Walang makasagot sa simpleng tanong namin kaya natagalan ang pag-aayos ng lakad.', 'Malabo ang ibinigay na direksyon at napunta muna kami sa maling lugar.', 'Matagal bago kami natulungan at hindi rin nasagot ang concern namin.'],
    ],
    'restaurant' => [
        'positive' => ['The food arrived hot, portions felt fair, and our table was looked after well.', 'We enjoyed the meal and did not have to wait long for our order.', 'The menu had good options for our group, and everyone found something they liked.'],
        'mixed' => ['The meal was satisfying, though one dish was a little bland and service slowed down when it got busy.', 'We liked some of the food, but the wait between ordering and serving could be shorter.', 'The portions were fine for the price; I just hoped for a little more flavor from the dishes we tried.'],
        'negative' => ['Our order took a long time, and one dish arrived cold. The staff seemed stretched during the rush.', 'The food was not as fresh as we expected, and our table had to ask twice for help.', 'There is room to improve the timing and consistency of the food.'],
        'positive_tl' => ['Mainit pa ang pagkain nang ihain at sakto ang serving para sa presyo.', 'Nagustuhan namin ang ulam at maayos din ang asikaso sa mesa namin.', 'Busog at masaya kaming umalis; may mapagpipilian din para sa iba-ibang panlasa.'],
        'mixed_tl' => ['Okay ang pagkain pero may isang ulam na medyo matabang at bumagal ang serbisyo nung dumami ang tao.', 'May nagustuhan kaming order, pero natagalan bago dumating ang iba.', 'Sakto ang serving pero medyo kulang sa lasa ang mga natikman namin.'],
        'negative_tl' => ['Matagal dumating ang order at malamig na ang isang pagkain nang maihain.', 'Hindi sariwa ang lasa ng pagkain at dalawang beses pa kaming nagpaalala sa staff.', 'Kailangan pang pagbutihin ang bilis at consistency ng kusina lalo na kapag maraming tao.'],
    ],
    'cafe' => [
        'positive' => ['The coffee tasted balanced, and it was a comfortable place to sit and catch up for a while.', 'I liked the drinks and the relaxed pace; we ended up staying longer than planned.', 'The coffee was enjoyable, and the seating made it easy to take a proper break.'],
        'mixed' => ['The drink was decent, but I wanted a stronger coffee flavor and a little more care with the wait time.', 'It is a nice place to pause, though my drink was sweeter than I expected.', 'We liked the atmosphere, but the coffee itself was just okay for the price.'],
        'negative' => ['We waited a while for our drinks, and the coffee did not taste as fresh as we hoped.', 'The seating was pleasant, but my order was not quite right and took too long to replace.', 'I expected better coffee for the price; the drink was weak and the service felt distracted.'],
        'positive_tl' => ['Masarap ang timpla ng kape at presko tumambay dito kahit saglit lang.', 'Nagtagal kami sa kuwentuhan dahil komportable ang upuan at relaxing ang lugar.', 'Masarap ang drinks at maayos na pahingahan habang namamasyal.'],
        'mixed_tl' => ['Okay ang kape pero mas gusto ko sana na mas matapang ang timpla.', 'Maganda sanang tambayan pero sobrang tamis ng inorder kong inumin.', 'Maaliwalas ang cafe; hindi lang sulit sa presyo ang lasa ng drinks para sa akin.'],
        'negative_tl' => ['Natagalan ang drinks at parang matagal nang naihanda ang kape ko.', 'Maayos ang upuan pero mali ang order at matagal bago ito napalitan.', 'Mahina ang timpla at hindi sulit sa presyo; sana mas tutukan din ang serbisyo kapag matao.'],
    ],
    'product' => [
        'positive' => ['It was nice finding something locally made, and the quality felt worth bringing home.', 'The product was well presented and made a thoughtful souvenir for family.', 'I liked being able to ask about how it was made before choosing one to take home.'],
        'mixed' => ['The product was interesting, though I wanted more information about its ingredients and how to care for it.', 'I liked the idea, but the price felt a little high for the finish and packaging.', 'There were some good local options, although labels and product details could be clearer.'],
        'negative' => ['The item did not hold up as well as I expected, and there was not much information about its materials.', 'I had trouble understanding the label and expected better quality for the price.', 'The packaging was damaged, and I could not get a clear answer about replacing it.'],
        'positive_tl' => ['Masaya akong may nabili akong gawa rito sa atin at maayos ang kalidad pag-uwi ko.', 'Magandang pasalubong ito; maayos ang pagkakabalot at presentasyon.', 'Nasagot naman ang mga tanong ko tungkol sa produkto bago ako pumili.'],
        'mixed_tl' => ['Interesting ang produkto pero kulang ang detalye sa label tungkol sa laman at paggamit.', 'Gusto ko ang idea pero medyo mahal para sa kalidad ng pagkakagawa.', 'May magagandang lokal na pagpipilian pero sana mas malinaw ang impormasyon sa bawat item.'],
        'negative_tl' => ['Hindi tumagal ang nabili ko at wala ring malinaw na detalye kung anong materyales ang ginamit.', 'Hindi ko maintindihan ang label at hindi sulit ang kalidad sa presyong binayaran ko.', 'Sira ang balot ng napili ko at walang malinaw na sagot kung puwede itong palitan.'],
    ],
    'fiesta' => [
        'positive' => ['There was a lively atmosphere, and our group enjoyed seeing the local activities and stalls.', 'The program gave us a good reason to stay and explore more of the event.', 'It felt welcoming, and there was plenty for our group to look at and enjoy.'],
        'mixed' => ['We enjoyed the performances, but the schedule was hard to follow and the busiest areas felt crowded.', 'The event had a fun atmosphere, though more signs and waste bins would have helped.', 'There were nice activities, but some parts of the program started later than we expected.'],
        'negative' => ['It was difficult to find the event schedule, and crowd flow around the stalls became uncomfortable.', 'We could not tell where some activities were happening, and basic event information was missing.', 'The event had potential, but poor organization made it hard for our group to enjoy it.'],
        'positive_tl' => ['Masigla ang paligid at maraming lokal na pagkain at aktibidad na puwedeng puntahan.', 'Nag-enjoy kami sa programa at hindi nakaka-bore mag-ikot sa event.', 'Masaya ang vibe at welcoming ang mga tao sa mga bisita.'],
        'mixed_tl' => ['Maganda ang mga pagtatanghal pero mahirap sundan ang oras ng programa at siksikan sa ilang bahagi.', 'Masaya ang event pero kulang ang basurahan at palatandaan sa paligid.', 'May nagustuhan kaming activities pero may ilang nagsimula nang mas late sa inaasahan.'],
        'negative_tl' => ['Walang malinaw na schedule kaya hindi namin naabutan ang ilan sa mga aktibidad.', 'Hindi maayos ang daloy ng tao sa may mga tindahan at nahirapan kaming maglakad.', 'Kulang ang impormasyon at organisasyon kaya hindi naging maayos ang experience namin.'],
    ],
];
$reviewClosings = [
    1 => [
        'en' => ['I would not plan another visit unless those issues are addressed.', 'The problems made this a one-time visit for me.', 'I hope the owners take this feedback seriously.'],
        'tl' => ['Sana maayos ito bago kami bumalik; nakasira talaga sa karanasan ang mga problemang iyon.', 'Hindi muna ito uulitin ng pamilya ko hangga’t hindi nagbabago ang serbisyo.', 'Sana mabigyan ng pansin ng namamahala ang mga concern ng bisita.'],
    ],
    2 => [
        'en' => ['There were a couple of bright spots, but I would hesitate to come back soon.', 'I left wishing more care had gone into the parts that did not work.', 'It might be better on another day, but this visit fell short for me.'],
        'tl' => ['May nagustuhan naman ako, pero hindi muna ako babalik hangga’t hindi naaayos ang mga ito.', 'Sayang dahil may potential naman, pero may ilang aberyang mahirap balewalain.', 'Baka mas maayos sa susunod, pero bitin at nakakadismaya ang punta namin ngayon.'],
    ],
    3 => [
        'en' => ['It was an okay visit, and a few thoughtful improvements could make a real difference.', 'I would consider giving it another try if those small issues are sorted out.', 'Nothing was terrible, but there was not much that stood out either.'],
        'tl' => ['Pwede na ang naging experience, pero may ilang bagay pa sanang pagbutihin.', 'Babalik siguro ako kapag naayos na ang mga nabanggit ko.', 'Hindi naman pangit ang punta, pero may kulang para masabi kong memorable.'],
    ],
    4 => [
        'en' => ['Overall, I had a good time and would consider coming back.', 'It was a solid visit; fixing that small detail would make it even better.', 'I would recommend it, with just a little room left for improvement.'],
        'tl' => ['Masaya naman ang punta namin at posible kaming bumalik kapag may pagkakataon.', 'Maayos ang experience; konting ayos pa sa maliliit na detalye at mas gaganda ito.', 'Maaari ko itong irekomenda, may ilang bagay lang na puwede pang hasain.'],
    ],
    5 => [
        'en' => ['I would gladly come back and tell friends to give it a try.', 'This made for a lovely stop on our day out; I would happily return.', 'One of the nicer parts of our trip, and easy to recommend.'],
        'tl' => ['Babalik ako rito at isasama ko ang mga kaibigan ko sa susunod.', 'Isa ito sa nagpasaya sa lakad namin; gusto kong bumalik ulit.', 'Masaya ang experience at ikukuwento ko ito sa mga kakilala ko.'],
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
            $itemName = trim((string)($item['item_name'] ?? '')) ?: ucfirst($itemType);
            $reviewType = $itemType;
            if ($itemType === 'restaurant' && preg_match('/coffee|cafe|café|espresso/i', $itemName . ' ' . (string)($item['item_category'] ?? ''))) {
                $reviewType = 'cafe';
            }
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
                $tone = $rating >= 4 ? 'positive' : ($rating === 3 ? 'mixed' : 'negative');
                $choiceSeed = crc32($seed . ':' . $index . ':' . $rating);
                $openingPool = $reviewOpeners[$language];
                $detailPool = $reviewDetails[$reviewType][$tone . ($language === 'tl' ? '_tl' : '')];
                $closingPool = $reviewClosings[$rating][$language];
                $opening = sprintf($openingPool[$choiceSeed % count($openingPool)], $itemName);
                $detail = $detailPool[intdiv($choiceSeed, 7) % count($detailPool)];
                $closing = $closingPool[intdiv($choiceSeed, 13) % count($closingPool)];
                $reviewText = $opening . ' ' . $detail . ' ' . $closing;
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
