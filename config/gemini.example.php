<?php
/*
 |--------------------------------------------------------------------
 | GEMINI CONFIG — copy this file to config/gemini.php and fill in
 | your real key. config/gemini.php is gitignored so the key never
 | gets committed.
 |--------------------------------------------------------------------
 | Get a free key at https://aistudio.google.com/apikey — Google AI
 | Studio's free tier needs no payment method and includes the
 | embedding model used here. Until config/gemini.php exists with a
 | real key, the site automatically falls back to the local TF-IDF
 | recommendation engine — nothing breaks either way.
 */
define('GEMINI_API_KEY', '');
define('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-2');
define('GEMINI_EMBEDDING_DIMENSIONS', 768); // smaller than the 3072 default — plenty for ranking similarity, cheaper to store/compare
