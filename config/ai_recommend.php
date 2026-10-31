<?php
if (!defined('GEMINI_API_KEY')) {
    if (file_exists(__DIR__ . '/gemini.php')) {
        include __DIR__ . '/gemini.php';
    } elseif (getenv('GEMINI_API_KEY') !== false) {
        define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
        define('GEMINI_EMBEDDING_MODEL', getenv('GEMINI_EMBEDDING_MODEL') ?: 'gemini-embedding-2');
        define('GEMINI_EMBEDDING_DIMENSIONS', (int) (getenv('GEMINI_EMBEDDING_DIMENSIONS') ?: 768));
    }
}
if (!defined('GROQ_API_KEY')) {
    if (file_exists(__DIR__ . '/groq.php')) {
        include __DIR__ . '/groq.php';
    } elseif (getenv('GROQ_API_KEY') !== false) {
        define('GROQ_API_KEY', getenv('GROQ_API_KEY'));
        define('GROQ_MODEL', getenv('GROQ_MODEL') ?: 'openai/gpt-oss-120b');
    }
}
$conn->query(
    "CREATE TABLE IF NOT EXISTS place_embeddings (
        item_type    VARCHAR(20) NOT NULL,
        item_id      INT NOT NULL,
        content_hash CHAR(32) NOT NULL,
        model        VARCHAR(60) NOT NULL,
        embedding    LONGTEXT NOT NULL,
        updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (item_type, item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$conn->query(
    "CREATE TABLE IF NOT EXISTS analytics_forecasts (
        forecast_key VARCHAR(40) NOT NULL PRIMARY KEY,
        input_hash   CHAR(32) NOT NULL,
        result       TEXT NOT NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
if (!defined('GEMINI_TEXT_MODEL')) {
    define('GEMINI_TEXT_MODEL', getenv('GEMINI_TEXT_MODEL') ?: 'gemini-3.8-flash');
}
function ai_configured(): bool
{
    return defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '' && function_exists('curl_init');
}
function groq_configured(): bool
{
    return defined('GROQ_API_KEY') && GROQ_API_KEY !== '' && function_exists('curl_init');
}
function ai_text_configured(): bool
{
    return ai_configured() || groq_configured();
}
function ai_fetch_embedding(string $text): ?array
{
    if (!ai_configured()) {
        return null;
    }
    $dimensions = defined('GEMINI_EMBEDDING_DIMENSIONS') ? GEMINI_EMBEDDING_DIMENSIONS : 768;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_EMBEDDING_MODEL . ':embedContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'content' => [
                'parts' => [['text' => mb_substr($text, 0, 8000)]],
            ],
            'embedContentConfig' => [
                'output_dimensionality' => $dimensions,
            ],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if ($response === false || $curlError || $httpCode !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    $embedding = $data['embedding']['values'] ?? null;
    return is_array($embedding) ? $embedding : null;
}
function ai_get_cached_embedding(mysqli $conn, string $itemType, int $itemId, string $text): ?array
{
    if (!ai_configured()) {
        return null;
    }
    $hash = md5($text);
    $model = GEMINI_EMBEDDING_MODEL;
    $stmt = $conn->prepare("SELECT embedding FROM place_embeddings WHERE item_type = ? AND item_id = ? AND content_hash = ? AND model = ?");
    $stmt->bind_param('siss', $itemType, $itemId, $hash, $model);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $cached = json_decode($row['embedding'], true);
        if (is_array($cached)) {
            return $cached;
        }
    }
    return null;
}
function ai_get_place_embedding(mysqli $conn, string $itemType, int $itemId, string $text): ?array
{
    $cached = ai_get_cached_embedding($conn, $itemType, $itemId, $text);
    if ($cached !== null) {
        return $cached;
    }
    if (!ai_configured()) {
        return null;
    }
    $hash = md5($text);
    $model = GEMINI_EMBEDDING_MODEL;
    $embedding = ai_fetch_embedding($text);
    if ($embedding === null) {
        return null;
    }
    $json = json_encode($embedding);
    $stmt = $conn->prepare(
        "INSERT INTO place_embeddings (item_type, item_id, content_hash, model, embedding)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE content_hash = VALUES(content_hash), model = VALUES(model), embedding = VALUES(embedding)"
    );
    $stmt->bind_param('sssss', $itemType, $itemId, $hash, $model, $json);
    $stmt->execute();
    $stmt->close();
    return $embedding;
}
function ai_generate_json_gemini(string $prompt): ?array
{
    if (!ai_configured()) {
        return null;
    }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_TEXT_MODEL . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0.2,
            ],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if ($response === false || $curlError || $httpCode !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text)) {
        return null;
    }
    $parsed = json_decode($text, true);
    return is_array($parsed) ? $parsed : null;
}
function ai_generate_json_groq(string $prompt): ?array
{
    if (!groq_configured()) {
        return null;
    }
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'           => GROQ_MODEL,
            'messages'        => [['role' => 'user', 'content' => $prompt]],
            'temperature'     => 0.2,
            'response_format' => ['type' => 'json_object'],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if ($response === false || $curlError || $httpCode !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    if (!is_string($text)) {
        return null;
    }
    $parsed = json_decode($text, true);
    return is_array($parsed) ? $parsed : null;
}
function ai_generate_json(string $prompt): ?array
{
    $result = ai_generate_json_gemini($prompt);
    if ($result !== null) {
        return $result;
    }
    return ai_generate_json_groq($prompt);
}
function ai_cached_or_generate(mysqli $conn, string $cacheKey, array $inputForHash, string $prompt): ?array
{
    $inputHash = md5(json_encode($inputForHash));
    $stmt = $conn->prepare("SELECT input_hash, result FROM analytics_forecasts WHERE forecast_key = ?");
    $stmt->bind_param('s', $cacheKey);
    $stmt->execute();
    $cached = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($cached && $cached['input_hash'] === $inputHash) {
        $decoded = json_decode($cached['result'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    $parsed = ai_generate_json($prompt);
    if ($parsed === null) {
        return null;
    }
    $json = json_encode($parsed);
    $stmt = $conn->prepare(
        "INSERT INTO analytics_forecasts (forecast_key, input_hash, result)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE input_hash = VALUES(input_hash), result = VALUES(result), created_at = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param('sss', $cacheKey, $inputHash, $json);
    $stmt->execute();
    $stmt->close();
    return $parsed;
}
function ai_forecast_pageviews(mysqli $conn, array $monthlySeries): ?array
{
    if (empty($monthlySeries)) {
        return null;
    }
    $series = array_map(fn($m) => ['month' => $m['label'], 'views' => (int) $m['value']], $monthlySeries);
    $prompt = "You are a web analytics forecaster for a small-town tourism website. "
        . "Here is the real monthly page view total for the last " . count($series) . " months, oldest first:\n"
        . json_encode($series) . "\n\n"
        . "Project next month's total page views. Consider the trend direction, recent momentum, "
        . "and that small absolute numbers can swing wildly in percentage terms — don't let noise dominate. "
        . "Respond with ONLY this JSON shape, no other text: "
        . '{"projected_next_month": <integer>, "growth_pct": <integer, vs. the latest month>, '
        . '"insight": "<one short plain-English sentence explaining the projection>"}';
    $parsed = ai_cached_or_generate($conn, 'pageviews_' . GEMINI_TEXT_MODEL, $series, $prompt);
    if ($parsed === null || !isset($parsed['projected_next_month'], $parsed['growth_pct'], $parsed['insight'])) {
        return null;
    }
    return [
        'projected_next_month' => max(0, (int) $parsed['projected_next_month']),
        'growth_pct'           => max(-90, min(200, (int) $parsed['growth_pct'])),
        'insight'              => mb_substr((string) $parsed['insight'], 0, 200),
    ];
}
function ai_monthly_summary(mysqli $conn, array $stats): ?string
{
    $prompt = "You are writing a one-paragraph monthly summary for the admin of a small-town tourism website "
        . "(Malvar, Batangas, Philippines). Here are this month's real stats:\n"
        . json_encode($stats) . "\n\n"
        . "Write 2-3 plain-English sentences a busy admin can read in 5 seconds: what happened, what stood out "
        . "(best performer, notable shift), and one thing worth paying attention to. No greeting, no sign-off, "
        . "no markdown. Respond with ONLY this JSON shape, no other text: "
        . '{"summary": "<2-3 sentence paragraph>"}';
    $parsed = ai_cached_or_generate($conn, 'monthly_summary_' . GEMINI_TEXT_MODEL, $stats, $prompt);
    if ($parsed === null || !isset($parsed['summary']) || !is_string($parsed['summary'])) {
        return null;
    }
    return mb_substr(trim($parsed['summary']), 0, 500);
}
function ai_recommended_actions(mysqli $conn, array $stats): ?array
{
    $prompt = "You are a data analyst advising the admin of a small-town tourism website "
        . "(Malvar, Batangas, Philippines). Here are this month's real stats:\n"
        . json_encode($stats) . "\n\n"
        . "Write 3-5 prioritized, specific, actionable recommendations based ONLY on patterns actually visible "
        . "in this data — never invent a number that isn't given. Each is one concise sentence a busy admin "
        . "can act on today. Respond with ONLY this JSON shape, no other text: "
        . '{"actions": [{"level": "good|info|warn", '
        . '"icon": "<one of: trending-up, megaphone, map-pin, utensils, pie-chart, users, eye-off, search, heart, alert-triangle, check-circle>", '
        . '"text": "<one sentence>"}]}';
    $parsed = ai_cached_or_generate($conn, 'recommended_actions_' . GEMINI_TEXT_MODEL, $stats, $prompt);
    if ($parsed === null || !isset($parsed['actions']) || !is_array($parsed['actions'])) {
        return null;
    }
    $allowedIcons = ['trending-up', 'megaphone', 'map-pin', 'utensils', 'pie-chart', 'users', 'eye-off', 'search', 'heart', 'alert-triangle', 'check-circle'];
    $out = [];
    foreach ($parsed['actions'] as $a) {
        if (!is_array($a) || !isset($a['level'], $a['icon'], $a['text'])) {
            continue;
        }
        $out[] = [
            'icon'  => in_array($a['icon'], $allowedIcons, true) ? $a['icon'] : 'sparkles',
            'level' => in_array($a['level'], ['good', 'info', 'warn'], true) ? $a['level'] : 'info',
            'text'  => mb_substr((string) $a['text'], 0, 240),
        ];
    }
    return !empty($out) ? array_slice($out, 0, 5) : null;
}
function ai_chart_insights(mysqli $conn, array $charts): array
{
    if (empty($charts)) {
        return [];
    }
    $payload = array_map(fn($c) => ['title' => $c['title'], 'data' => $c['data']], $charts);
    $prompt = "You are analyzing several charts on a small-town tourism website's admin analytics dashboard "
        . "(Malvar, Batangas, Philippines). Each key below is a chart id with its title and real data:\n"
        . json_encode($payload) . "\n\n"
        . "For EVERY chart key present above, give a one-sentence interpretation (what the data actually shows) "
        . "and a one-sentence solution (one specific, actionable next step for the admin). Base this only on the "
        . "real data given — never invent numbers. If a chart has no meaningful data yet, say so plainly instead "
        . "of guessing. Respond with ONLY this JSON shape, no other text, one entry per chart key: "
        . '{"<chart_key>": {"interpretation": "<one sentence>", "solution": "<one sentence>"}}';
    $parsed = ai_cached_or_generate($conn, 'chart_insights_' . GEMINI_TEXT_MODEL, $payload, $prompt);
    if ($parsed === null) {
        return [];
    }
    $out = [];
    foreach ($charts as $key => $meta) {
        $entry = $parsed[$key] ?? null;
        if (is_array($entry) && isset($entry['interpretation'], $entry['solution'])) {
            $out[$key] = [
                'interpretation' => mb_substr((string) $entry['interpretation'], 0, 200),
                'solution'       => mb_substr((string) $entry['solution'], 0, 200),
            ];
        }
    }
    return $out;
}
function ai_travel_wrapped_captions(mysqli $conn, int $userId, string $period, string $periodLabel, array $stats): ?array
{
    $facts = [
        'period'              => $periodLabel,
        'total_places'        => $stats['totalPlaces'],
        'days_traveled'       => $stats['daysTraveled'],
        'total_photos'        => $stats['totalPhotos'],
        'categories_explored' => $stats['categoriesCompleted'],
        'favorite_category'   => $stats['favoriteCategory'],
        'category_visits'     => $stats['categoryVisitCounts'] ?? [],
        'most_visited_place'  => $stats['mostVisited']['name'] ?? null,
        'first_place_visited' => $stats['firstPlaceVisited']['name'] ?? null,
        'latest_adventure'    => $stats['latestAdventure']['name'] ?? null,
        'longest_streak_days' => $stats['longestStreak'],
        'places_visited'      => array_map(static fn($place) => [
            'name' => $place['name'],
            'category' => $place['category'],
            'visits' => $place['visitCount'] ?? 1,
        ], array_slice($stats['distinctPlaces'], 0, 12)),
    ];
    $facts['template_tie_breaker'] = hexdec(substr(hash(
        'sha256',
        $userId . '|' . $period . '|' . json_encode($facts['category_visits'])
    ), 0, 7));
    $moments = [];
    foreach ($stats['timeline'] ?? [] as $visit) {
        $note = trim((string) ($visit['note'] ?? ''));
        $photoCaptions = [];
        foreach ($visit['photos'] ?? [] as $photo) {
            $caption = trim((string) ($photo['caption'] ?? ''));
            if ($caption !== '') $photoCaptions[] = mb_substr($caption, 0, 160);
        }
        if ($note === '' && !$photoCaptions) continue;
        $moments[] = [
            'place' => $visit['name'],
            'date' => $visit['visitedDate'],
            'note' => mb_substr($note, 0, 240),
            'photo_captions' => array_slice($photoCaptions, 0, 2),
        ];
        if (count($moments) >= 8) break;
    }
    $facts['personal_moments'] = $moments;
    $facts['layout_version'] = 'slide-layouts-v1';
    $voices = [
        'a thoughtful keepsake, warm and reflective',
        'a lively field journal, bright and conversational',
        'a local culture notebook, vivid and observant',
        'a photo-album storyteller, concise and sensory',
        'a playful travel postcard, light and specific',
        'a quiet year-in-review, simple and heartfelt',
    ];
    $voiceSeed = hash('sha256', $userId . '|' . $period . '|' . json_encode($facts, JSON_UNESCAPED_UNICODE));
    $facts['writing_voice'] = $voices[hexdec(substr($voiceSeed, 0, 7)) % count($voices)];
    $prompt = "You are writing the short captions for one visitor's personal \"Travel Wrapped\" recap on "
        . "KULTOURA, a tourism site for Malvar, Batangas, Philippines (a Spotify-Wrapped-style slideshow of "
        . "their own travel diary). Here are THIS visitor's real stats for \"{$periodLabel}\" — use only these "
        . "facts, never invent a place name or number that isn't given:\n"
        . json_encode($facts) . "\n\n"
        . "Write captions in the supplied writing_voice. Make this recap distinct from a generic travel "
        . "template: vary sentence openings and rhythm, and use personal_moments when present. Keep every field short (it's a caption, "
        . "not a paragraph) and avoid repeating the same place name in more than two fields. Reference only "
        . "supplied facts, places, notes, and captions; do not invent feelings, companions, weather, or events. "
        . "Also choose one visual personality template that fits this visitor's strongest real interaction pattern. "
        . "Use category_visits, place categories and names, visit counts, and personal_moments as evidence. "
        . "Available interaction-based templates: culture for churches, heritage, and local traditions; "
        . "fiesta for repeated fiesta/event visits; sports for clear sports places or activities; "
        . "coffee for repeated coffee-shop visits; food for repeated food or restaurant visits. "
        . "Choose coffee only when coffee-shop evidence is clear; a generic restaurant visit alone is not enough. "
        . "Choose sports only when sports activity is explicit. If one theme is clearly dominant, use it. "
        . "When there is no clear category-specific pattern, choose among botanical, sky, sunset, neutral, and moonlit "
        . "using template_tie_breaker. This gives similar users different general looks while keeping strong activity patterns specific. "
        . "When evidence is close, use template_tie_breaker to vary the choice; keep the choice grounded in the visitor's actual activity. "
        . "Design each Wrapped slide individually. Choose one of the listed layouts for every slide ID, "
        . "based on that slide's content and this visitor's actual data. Do not use one layout for every slide. "
        . "Allowed choices: slide 1 cover_collage|cover_type; 2 stats_rows|stats_tiles; 3 picks_editorial|picks_cards; "
        . "4 timeline_rail|timeline_cards; 5 places_grid|places_featured; 6 favorites_stack|favorites_grid; "
        . "7 map_focus|map_compact; 8 photos_grid|photos_story; 9 glance_scatter|glance_strip; "
        . "10 closing_quote|closing_minimal. Replace every placeholder below with exactly one allowed value; "
        . "do not include angle brackets or the word choose. Respond with ONLY this JSON shape, no other text: "
        . '{"template":"<culture|fiesta|sports|coffee|food|botanical|sky|sunset|neutral|moonlit>", "slide_layouts":{"1":"<choose cover_collage or cover_type>",'
        . '"2":"<choose stats_rows or stats_tiles>","3":"<choose picks_editorial or picks_cards>",'
        . '"4":"<choose timeline_rail or timeline_cards>","5":"<choose places_grid or places_featured>",'
        . '"6":"<choose favorites_stack or favorites_grid>","7":"<choose map_focus or map_compact>",'
        . '"8":"<choose photos_grid or photos_story>","9":"<choose glance_scatter or glance_strip>",'
        . '"10":"<choose closing_quote or closing_minimal>"}, '
        . '"title_sub": "<two short lines, \\n between them>", '
        . '"numbers_sub": "<one sentence>", "numbers_footer": "<under 8 words>", '
        . '"timeline_sub": "<one sentence>", "timeline_footer": "<under 8 words>", '
        . '"places_sub": "<one sentence>", "liked_sub": "<one sentence>", '
        . '"map_sub": "<one sentence>", "photos_sub": "<one sentence>", "glance_sub": "<one sentence>", '
        . '"sticky_note": "<three short words/phrases, \\n between them>", '
        . '"thanks_sub": "<one or two short sentences, \\n between them>", '
        . '"quote": "<three short lines, \\n between them>"}';
    $cacheKey = 'tw_cap_' . md5($userId . '|' . $period);
    $parsed = ai_cached_or_generate($conn, $cacheKey, $facts, $prompt);
    if ($parsed === null) {
        return null;
    }
    $fields = [
        'title_sub' => 200, 'numbers_sub' => 160, 'numbers_footer' => 80,
        'timeline_sub' => 160, 'timeline_footer' => 80, 'places_sub' => 160,
        'liked_sub' => 160, 'map_sub' => 160, 'photos_sub' => 160, 'glance_sub' => 160,
        'sticky_note' => 120, 'thanks_sub' => 200, 'quote' => 200,
    ];
    $out = [];
    foreach ($fields as $key => $maxLen) {
        if (!empty($parsed[$key]) && is_string($parsed[$key])) {
            $out[$key] = mb_substr(trim($parsed[$key]), 0, $maxLen);
        }
    }
    $allowedLayouts = [
        '1' => ['cover_collage', 'cover_type'],
        '2' => ['stats_rows', 'stats_tiles'],
        '3' => ['picks_editorial', 'picks_cards'],
        '4' => ['timeline_rail', 'timeline_cards'],
        '5' => ['places_grid', 'places_featured'],
        '6' => ['favorites_stack', 'favorites_grid'],
        '7' => ['map_focus', 'map_compact'],
        '8' => ['photos_grid', 'photos_story'],
        '9' => ['glance_scatter', 'glance_strip'],
        '10' => ['closing_quote', 'closing_minimal'],
    ];
    $slideLayouts = [];
    foreach ($allowedLayouts as $slide => $choices) {
        $choice = $parsed['slide_layouts'][$slide] ?? '';
        if (in_array($choice, $choices, true)) $slideLayouts[$slide] = $choice;
    }
    if ($slideLayouts) $out['slide_layouts'] = $slideLayouts;
    $allowedTemplates = ['culture', 'fiesta', 'sports', 'coffee', 'food', 'botanical', 'sky', 'sunset', 'neutral', 'moonlit'];
    if (isset($parsed['template']) && in_array($parsed['template'], $allowedTemplates, true)) {
        $out['template'] = $parsed['template'];
    }
    return !empty($out) ? $out : null;
}
function ai_wrapped_caption_html(string $text): string
{
    $lines = preg_split('/\\\\n|\n/', $text);
    return implode('<br>', array_map('htmlspecialchars', $lines));
}
