<?php
require_once __DIR__ . '/../config/session_boot.php';
session_unset();
session_destroy();
header("Location: /kultoura/index.php");
exit;
?>