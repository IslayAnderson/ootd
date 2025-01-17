<?php
ini_set('display_errors', 'off');

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Functions/*.php") as $filename) {
    $filename!=$_SERVER['DOCUMENT_ROOT']."/Functions/functions.php"?require $filename:"";
}

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Functions/*/*.php") as $filename) {
    require $filename;
}

ini_set('display_errors', 'on');