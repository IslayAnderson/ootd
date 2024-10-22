<?php

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Classes/*.php") as $filename) {
    $filename!=$_SERVER['DOCUMENT_ROOT']."/Classes/classes.php"?include($filename):"";
}

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Classes/*/*.php") as $filename) {
    echo "<br>" . $filename;
    include($filename);
}
