<?php

// errrm maybe don't do this TODO: fix

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Classes/*.php") as $filename) {
    $filename!=$_SERVER['DOCUMENT_ROOT']."/Classes/classes.php"?include($filename):"";
    $classloc = explode('/',$filename);
    eval( "use ".str_replace(".php", "", $classloc[count($classloc)-1]).";");
}

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Classes/*/*.php") as $filename) {
    include($filename);
    $classloc = explode('/',$filename);
    eval( "use ".str_replace(".php", "", $classloc[count($classloc)-1]).";");
}
