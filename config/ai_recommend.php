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

function ai_configured(): bool
{
    return defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '' && function_exists('curl_init');
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
