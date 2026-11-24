<?php
/*
 * Review comment sentiment (NLP) for English, Tagalog and Taglish.
 *
 * Lexicon- and rule-based, in the style of VADER (Hutto & Gilbert, 2014):
 *  - each known word has a weight from −3 (very negative) to +3 (very positive)
 *  - negation flips and dampens the next words ("hindi masarap", "not worth it")
 *  - intensifiers / softeners scale the next word ("sobrang", "grabe", "very" / "medyo", "kinda")
 *  - Tagalog affixes are read as intensity ("napakasarap" = very "sarap", "pinakamaganda" = most "maganda")
 *  - after a contrast word ("pero", "kaso", "but") the clause counts more, before it less
 *  - "!!!", ALL-CAPS words and emojis add emphasis
 *  - the total is normalised to a compound score in [−1, +1]:  s / √(s² + 15)
 * Labels: ≥ +0.05 positive, ≤ −0.05 negative, otherwise neutral.
 */

function kt_sentiment_lexicon(): array
{
    static $lexicon = null;
    if ($lexicon !== null) return $lexicon;
    $lexicon = [
        // English positive
        'excellent' => 3, 'amazing' => 3, 'awesome' => 3, 'perfect' => 3, 'outstanding' => 3, 'wonderful' => 3, 'fantastic' => 3,
        'love' => 3, 'loved' => 3, 'best' => 3, 'superb' => 3, 'delicious' => 3, 'beautiful' => 3, 'breathtaking' => 3, 'stunning' => 3,
        'great' => 2.5, 'nice' => 2, 'good' => 1.9, 'tasty' => 2, 'yummy' => 2, 'clean' => 2, 'friendly' => 2, 'helpful' => 2,
        'peaceful' => 2, 'relaxing' => 2, 'cozy' => 2, 'affordable' => 2, 'recommend' => 2, 'recommended' => 2, 'worth' => 2,
        'enjoy' => 2, 'enjoyed' => 2, 'fun' => 2, 'fresh' => 1.5, 'comfortable' => 2, 'accommodating' => 2, 'polite' => 2,
        'convenient' => 1.5, 'happy' => 2, 'satisfied' => 2, 'liked' => 2, 'inviting' => 2, 'welcoming' => 2, 'lovely' => 2.5,
        'pleasant' => 2, 'memorable' => 2, 'fast' => 1, 'quick' => 1, 'easy' => 1, 'useful' => 1.5, 'clear' => 1, 'spacious' => 1,
        'cheap' => 1, 'quiet' => 1, 'safe' => 1.5, 'must' => 0,
        // English negative
        'terrible' => -3, 'awful' => -3, 'horrible' => -3, 'worst' => -3, 'disgusting' => -3, 'hate' => -3, 'rude' => -3, 'unsafe' => -3,
        'bad' => -2.5, 'poor' => -2, 'dirty' => -2, 'slow' => -2, 'overpriced' => -2, 'noisy' => -2, 'boring' => -2, 'bland' => -2,
        'disappointing' => -2, 'disappointed' => -2, 'smelly' => -2, 'waste' => -2, 'broken' => -2, 'unfriendly' => -2, 'wrong' => -2,
        'mess' => -2, 'messy' => -2, 'unclean' => -2, 'hassle' => -2, 'sad' => -2, 'expensive' => -1, 'crowded' => -1, 'late' => -1,
        'problem' => -1, 'issue' => -1, 'cold' => -0.5, 'meh' => -1, 'mediocre' => -1.5,
        // Tagalog positive
        'masarap' => 3, 'sarap' => 3, 'maganda' => 3, 'ganda' => 3, 'panalo' => 3, 'sulit' => 3, 'malinis' => 2, 'linis' => 2,
        'mabait' => 2, 'mababait' => 2, 'bait' => 2, 'masaya' => 2, 'saya' => 2, 'mura' => 2, 'presko' => 2, 'maaliwalas' => 2,
        'komportable' => 2, 'magaling' => 2, 'galing' => 2, 'astig' => 2, 'solid' => 2, 'gusto' => 2, 'nagustuhan' => 2, 'babalik' => 2,
        'bumalik' => 1.5, 'maayos' => 2, 'ayos' => 1, 'okay' => 1, 'ok' => 1, 'mabilis' => 1, 'tahimik' => 1, 'magalang' => 2,
        'masustansya' => 1.5, 'bet' => 1.5, 'nakakatuwa' => 2, 'natuwa' => 2, 'swak' => 2, 'malasa' => 2, 'malamig' => 0.5,
        // Tagalog negative
        'pangit' => -3, 'panget' => -3, 'bastos' => -3, 'kadiri' => -3, 'marumi' => -2, 'madumi' => -2, 'dumi' => -2, 'matagal' => -2,
        'mabagal' => -2, 'bagal' => -2, 'masungit' => -2, 'sungit' => -2, 'maingay' => -2, 'ingay' => -2, 'nakakadismaya' => -2,
        'dismaya' => -2, 'sayang' => -2, 'malungkot' => -2, 'matabang' => -2, 'mabaho' => -2, 'baho' => -2, 'sira' => -2,
        'delikado' => -2, 'nakakainis' => -2, 'inis' => -2, 'mahal' => -1, 'maalat' => -1, 'mapait' => -1, 'siksikan' => -1,
        'mainit' => -0.5, 'kulang' => -1.5, 'palpak' => -2.5,
    ];
    return $lexicon;
}

function kt_sentiment_phrases(): array
{
    return [
        'walang kwenta' => -3, 'walang kuwenta' => -3, 'hindi sulit' => -2.5, 'not worth' => -2.5, 'must try' => 2.5, 'must visit' => 2.5,
        'worth it' => 2.5, 'highly recommend' => 3, 'babalik ulit' => 2.5, 'babalikan' => 2, 'ang sarap' => 3, 'ang ganda' => 3,
        'too expensive' => -2, 'sobrang mahal' => -2, 'never again' => -3, 'hindi na babalik' => -3, 'not bad' => 1.5,
    ];
}

/** Analyses one comment. Returns ['score' => −1..1, 'label' => positive|neutral|negative, 'matched' => int]. */
function kt_sentiment_analyze(string $text): array
{
    $text = trim($text);
    if ($text === '') return ['score' => 0.0, 'label' => 'neutral', 'matched' => 0];
    $lexicon = kt_sentiment_lexicon();
    $boosters = ['very' => 0.3, 'really' => 0.3, 'so' => 0.25, 'super' => 0.35, 'extremely' => 0.4, 'totally' => 0.3, 'absolutely' => 0.35,
        'highly' => 0.3, 'sobrang' => 0.35, 'sobra' => 0.3, 'talaga' => 0.25, 'talagang' => 0.3, 'grabe' => 0.35, 'ang' => 0.15, 'napaka' => 0.35,
        'slightly' => -0.3, 'somewhat' => -0.3, 'kinda' => -0.3, 'bit' => -0.3, 'medyo' => -0.35, 'konti' => -0.3, 'kaunti' => -0.3, 'parang' => -0.2];
    $negators = ['not', 'no', 'never', 'none', 'nobody', 'nothing', 'hindi', 'di', 'wala', 'walang', 'ayaw', 'huwag', 'cannot', 'without'];
    $contrasts = ['but', 'however', 'though', 'although', 'pero', 'kaso', 'kaya lang'];
    $score = 0.0;
    $matched = 0;

    // Emojis
    foreach (['😍' => 3, '❤️' => 3, '❤' => 3, '😋' => 2, '😊' => 2, '👍' => 2, '🙂' => 1, '🥰' => 3, '😡' => -3, '🤮' => -3, '👎' => -2, '😞' => -2, '😢' => -2, '😠' => -2.5] as $emoji => $weight) {
        $count = substr_count($text, $emoji);
        if ($count) { $score += $weight * $count; $matched += $count; }
    }
    $lower = mb_strtolower($text);
    $lower = preg_replace('/n\'t\b/u', ' not', $lower);   // isn't → is not
    // Phrases first, then remove them so their words are not counted twice.
    foreach (kt_sentiment_phrases() as $phrase => $weight) {
        $count = preg_match_all('/(^|[^\p{L}])' . preg_quote($phrase, '/') . '($|[^\p{L}])/u', $lower);
        if ($count) { $score += $weight * $count; $matched += $count; $lower = preg_replace('/(^|[^\p{L}])' . preg_quote($phrase, '/') . '($|[^\p{L}])/u', '$1 $2', $lower); }
    }
    $originalWords = preg_split('/[^\p{L}\p{N}\']+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $tokens = preg_split('/[^\p{L}\p{N}\']+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $mostlyCaps = count(array_filter($originalWords, static fn($w) => mb_strlen($w) > 2 && $w === mb_strtoupper($w))) > count($originalWords) / 2;
    $capsWords = [];
    foreach ($originalWords as $word) if (mb_strlen($word) > 2 && $word === mb_strtoupper($word) && preg_match('/\p{L}/u', $word)) $capsWords[mb_strtolower($word)] = true;

    $contrastIndex = null;
    foreach ($tokens as $i => $token) if (in_array($token, $contrasts, true)) $contrastIndex = $i;   // last contrast word
    $negationLeft = 0;
    $boost = 0.0;
    foreach ($tokens as $i => $token) {
        if (in_array($token, $negators, true)) { $negationLeft = 3; continue; }
        if (isset($boosters[$token]) && !isset($lexicon[$token])) { $boost += $boosters[$token]; continue; }
        $weight = $lexicon[$token] ?? null;
        $affixBoost = 0.0;
        if ($weight === null) {
            // Tagalog intensifying affixes: napaka-/pinaka-/sobrang-/nakaka- + root ("napakasarap", "pinakamaganda")
            foreach (['pinaka' => 0.45, 'napaka' => 0.35, 'sobrang' => 0.35, 'nakaka' => 0.15] as $prefix => $extra) {
                if (str_starts_with($token, $prefix) && mb_strlen($token) > mb_strlen($prefix) + 2) {
                    $root = mb_substr($token, mb_strlen($prefix));
                    $weight = $lexicon[$root] ?? $lexicon['ma' . $root] ?? null;
                    if ($weight !== null) { $affixBoost = $extra; break; }
                }
            }
        }
        if ($weight === null || $weight == 0) { $boost = 0.0; if ($negationLeft > 0) $negationLeft--; continue; }   // an intensifier only affects the next word
        $value = $weight * (1 + $boost + $affixBoost);
        if (isset($capsWords[$token]) && !$mostlyCaps) $value *= 1.25;
        if ($negationLeft > 0) $value *= -0.74;                       // negation flips and dampens
        if ($contrastIndex !== null) $value *= $i > $contrastIndex ? 1.5 : 0.5;
        $score += $value;
        $matched++;
        $boost = 0.0;
        if ($negationLeft > 0) $negationLeft--;
    }
    // Exclamation marks emphasise the overall direction (up to three).
    $exclamations = min(3, substr_count($text, '!'));
    if ($exclamations && $score != 0.0) $score += ($score > 0 ? 1 : -1) * 0.29 * $exclamations;
    $compound = $score / sqrt($score * $score + 15);
    $compound = max(-1.0, min(1.0, $compound));
    return ['score' => round($compound, 3), 'label' => $compound >= 0.05 ? 'positive' : ($compound <= -0.05 ? 'negative' : 'neutral'), 'matched' => $matched];
}
