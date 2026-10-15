<?php
/*
 |--------------------------------------------------------------------
 | AI RECOMMENDATIONS — Gemini embeddings, with a DB cache
 |--------------------------------------------------------------------
 | Backs the content-similarity half of pages/foryou.php's "Because
 | You Explored" section. When a Gemini API key is configured (see
 | config/gemini.example.php — Google AI Studio's free tier, no
 | payment method needed), place descriptions and the visitor's
 | interaction history are compared as real semantic embedding
 | vectors instead of the local TF-IDF fallback.
 |
 | Embeddings are cached per place in `place_embeddings`, keyed by a
 | hash of the text that produced them — so a place only ever costs
 | one live API call, the first time it's recommended after being
 | added or edited. Every request after that is a plain DB read: no
 | per-visitor API cost or latency.
 |
 | If no key is configured, or a call fails (network error, bad key,
 | rate limit, timeout), every function here returns null and the
 | caller in foryou.php falls back to TF-IDF — this file never throws
 | and never takes the page down.
 |
 | Include AFTER config/dbmain.php (needs $conn).
 */

// Key source, in order: config/gemini.php (gitignored local file — see
// config/gemini.example.php) for XAMPP, else GEMINI_* environment
// variables for a hosted deployment (e.g. Railway) where that file
// was never deployed. Neither present just means AI stays off.
if (!defined('GEMINI_API_KEY')) {
    if (file_exists(__DIR__ . '/gemini.php')) {
        include __DIR__ . '/gemini.php';
    } elseif (getenv('GEMINI_API_KEY') !== false) {
        define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
        define('GEMINI_EMBEDDING_MODEL', getenv('GEMINI_EMBEDDING_MODEL') ?: 'gemini-embedding-2');
        define('GEMINI_EMBEDDING_DIMENSIONS', (int) (getenv('GEMINI_EMBEDDING_DIMENSIONS') ?: 768));
    }
}

// Same two-source pattern for Groq — see config/groq.example.php. Text-
// generation only (no embeddings endpoint), so this only ever backs
// ai_generate_json()'s fallback, never the embeddings path above.
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

// Caches admindashboard.php's AI page-view forecast so a day of admin
// visits costs at most one live Gemini call, not one per page load —
// same spirit as place_embeddings, keyed by a hash of the actual input
// series so a cache from before today's numbers changed never leaks in.
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

// Whether ai_generate_json() has anywhere to go — Gemini, Groq, or
// both. Embeddings are Gemini-only (see ai_configured() above), so
// this is deliberately a separate check used only by the text-
// generation features (forecast, summary, recommended actions, chart
// insights), not by the recommendation engine's embedding calls.
function ai_text_configured(): bool
{
    return ai_configured() || groq_configured();
}

// Raw call to Gemini's embedContent endpoint. Never throws — any
// failure (missing key, network error, non-200 response, malformed
// JSON) just returns null so the caller can fall back to TF-IDF.
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
                'parts' => [['text' => mb_substr($text, 0, 8000)]], // stay well under the model's input limit
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

// DB-only lookup — never calls the API, so it's free to call for every
// place on every request without spending any fetch budget.
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

// Cached-or-fetch embedding for one place. Returns null (never throws)
// if AI isn't configured or the API call fails — callers must handle
// that by treating the place as having no content vector this request.
//
// Always calls the live API on a cache miss — callers that need to cap
// how many *fresh* fetches one page load can trigger (foryou.php does,
// so a cold cache can't stall a request behind dozens of sequential
// API round-trips) should check ai_get_cached_embedding() first and
// only call this once their fetch budget confirms a miss is worth it.
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

/*
 |--------------------------------------------------------------------
 | AI FORECASTING — admindashboard.php's "Forecasts & Momentum"
 |--------------------------------------------------------------------
 | A real Gemini call (generateContent, not embeddings) reasons over
 | the last several months of page_views totals and returns a
 | projected next-month number, a growth %, and a one-sentence
 | explanation. Cached in analytics_forecasts so a whole day of admin
 | dashboard visits costs at most one live call. Falls back to null
 | (never throws) on any failure — admindashboard.php's own
 | least-squares trend line is the fallback when this returns null,
 | same pattern as embeddings falling back to TF-IDF.
 */
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
                'temperature'      => 0.2, // low — this should read as analysis, not creative writing
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

// Groq's OpenAI-compatible chat completions endpoint — same "one
// prompt in, one parsed JSON object out, null on any failure" contract
// as the Gemini call above, just a different request/response shape.
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

// Shared low-level call: one prompt in, one parsed JSON object out (or
// null if neither provider could answer). Tries Gemini first, and
// only on failure — bad/missing key, network error, non-200 (including
// a quota 429), malformed JSON — retries the exact same prompt against
// Groq. Every AI-generated analytics feature below is built on this,
// so there's exactly one place that owns the provider fallback order.
function ai_generate_json(string $prompt): ?array
{
    $result = ai_generate_json_gemini($prompt);
    if ($result !== null) {
        return $result;
    }
    return ai_generate_json_groq($prompt);
}

// Reads/writes the shared analytics_forecasts cache by an arbitrary key
// (one row per distinct AI-generated analytics feature) so a whole
// day of admin dashboard visits costs at most one live call per
// feature, not one per page load.
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
        // Same clamp as the trend-line fallback — a model shouldn't be
        // able to headline a wilder swing than the deterministic math does.
        'growth_pct'           => max(-90, min(200, (int) $parsed['growth_pct'])),
        'insight'              => mb_substr((string) $parsed['insight'], 0, 200),
    ];
}

// A short "here's what happened this month and why" narrative for the
// top of the dashboard — descriptive, not predictive (it explains the
// real numbers already on the page, it doesn't project anything new).
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

// Prescriptive: AI-reasoned "what to actually do about it" actions,
// replacing admindashboard.php's rule-based $recommendations list when
// available. Returns the same [{icon, level, text}] shape that list
// already uses, so it drops straight into the existing rec-list
// markup — the rule-based version is still what renders whenever AI
// isn't configured or the call fails, same fallback pattern as the
// forecast.
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

// Per-chart "what this means / what to do about it" — one Gemini call
// covering every Descriptive chart at once (not one call per chart:
// that would mean ~10 sequential network round-trips on a cold cache).
// $charts is [chart_key => ['title' => ..., 'data' => [...]]]. Returns
// [chart_key => ['interpretation' => ..., 'solution' => ...]] — a key
// is simply absent if the model skipped it or returned something
// malformed, so callers just check isset() per chart.
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
