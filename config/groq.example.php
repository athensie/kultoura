<?php
/*
 |--------------------------------------------------------------------
 | GROQ CONFIG — copy this file to config/groq.php and fill in your
 | real key. config/groq.php is gitignored so the key never gets
 | committed.
 |--------------------------------------------------------------------
 | Get a free key at https://console.groq.com/keys — no payment method
 | needed. Used only as a fallback for the TEXT-generation AI features
 | (forecast, monthly summary, recommended actions, chart insights) —
 | not embeddings, which Groq doesn't offer, so pages/foryou.php's
 | recommendation engine stays Gemini-only (falls back to TF-IDF).
 |
 | When Gemini fails or its daily free-tier quota is exhausted,
 | config/ai_recommend.php automatically retries the same prompt
 | against Groq before falling back to the deterministic math/rules.
 | Until config/groq.php exists with a real key, that second fallback
 | step is simply skipped — nothing breaks either way.
 */
define('GROQ_API_KEY', '');
define('GROQ_MODEL', 'openai/gpt-oss-120b');
