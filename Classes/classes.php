<?php
//every class file is loaded up front, in alphabetical order (interfaces sort before the classes that use them)
foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/Classes/*.php") as $filename) {
    $filename != $_SERVER['DOCUMENT_ROOT'] . "/Classes/classes.php" ? require $filename : "";
}

foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/Classes/*/*.php") as $filename) {
    require $filename;
}
