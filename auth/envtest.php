<?php
foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'MYSQLHOST'] as $k) {
    $v = getenv($k);
    echo $k . ': ' . (($v !== false && $v !== '') ? 'SET, ' . strlen($v) . ' chars' : 'MISSING') . '<br>';
}