<?php
foreach (glob("./Classes/*.php") as $filename) {
    echo $filename;
    $filename!="./Classes/classes.php"?include_once($filename):"";
}
foreach (glob("./Classes/*/*.php") as $filename) {
    include_once($filename);
}