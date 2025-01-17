<?php
function id_gen(){
    $alphanumeric = array_merge(
        range('0', '9'),
        range('A', 'Z'),
        range('a', 'z')
    );

    $seedgen = '';
    $seedgen_0 = '';

    for($i = 0; $i < 32; $i++){
        $seedgen .= $alphanumeric[random_int(0, count($alphanumeric)-1)];
        $seedgen_0 .= $alphanumeric[random_int(0, count($alphanumeric)-1)];
    }

    $randomsalt = hash_hmac("sha256", $seedgen, $seedgen_0);
    return password_hash($randomsalt, PASSWORD_DEFAULT);
}