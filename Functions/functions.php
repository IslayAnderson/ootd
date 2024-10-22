<?php
foreach (glob("./Functions/*/*.php") as $filename) {
    include($filename);
}
foreach (glob("./Functions/*.php") as $filename) {
    $filename!="./Functions/functions.php"?include($filename):"";
}