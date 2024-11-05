<?php
ini_set('display_errors', 'off');

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Functions/*.php") as $filename) {
    $filename!=$_SERVER['DOCUMENT_ROOT']."/Functions/functions.php"?include($filename):"";
}

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Functions/*/*.php") as $filename) {
    include($filename);
}

ini_set('display_errors', 'on');