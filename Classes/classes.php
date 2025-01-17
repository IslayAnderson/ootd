<?php

// errrm maybe don't do this TODO: fix
ini_set('display_errors', 'off');

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Classes/*.php") as $filename) {
    $filename!=$_SERVER['DOCUMENT_ROOT']."/Classes/classes.php"?require $filename:"";
    $classloc = explode('/',$filename);
    eval( "use ".str_replace(".php", "", $classloc[count($classloc)-1]).";");
}

foreach (glob($_SERVER['DOCUMENT_ROOT']."/Classes/*/*.php") as $filename) {
    require $filename;
    $classloc = explode('/',$filename);
    eval( "use ".str_replace(".php", "", $classloc[count($classloc)-1]).";");
}

ini_set('display_errors', 'on');