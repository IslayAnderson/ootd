<?php
function title_update($title){
    ob_start("title_replace");
}

function title_replace($title, $buffer){
    return str_replace("{title_placeholder}", $title, $buffer );
}

function decsription_update($title){
    ob_start("title_replace");
}

function description_replace($title, $buffer){
    return str_replace("{description_placeholder}", $title, $buffer );
}