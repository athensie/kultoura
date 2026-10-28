<?php
$s = (string) getenv('GOOGLE_CLIENT_SECRET');
echo 'Secret length: ' . strlen($s) . '<br>';
echo 'Starts with GOCSPX-: ' . (strpos($s, 'GOCSPX-') === 0 ? 'yes' : 'NO') . '<br>';
echo 'Has spaces or quotes: ' . (preg_match('/[\s"\']/', $s) ? 'YES (remove them)' : 'no');