<?php
/**
 * @throws \Random\RandomException
 */
function id_gen(): string
{
    $alphanumeric = array_merge(
        range('0', '9'),
        range('A', 'Z'),
        range('a', 'z')
    );

    $seed_gen = '';
    $seed_gen_0 = '';

    for ($i = 0; $i < 32; $i++) {
        $seed_gen .= $alphanumeric[random_int(0, count($alphanumeric) - 1)];
        $seed_gen_0 .= $alphanumeric[random_int(0, count($alphanumeric) - 1)];
    }

    $random_salt = hash_hmac("sha256", $seed_gen, $seed_gen_0);
    return password_hash($random_salt, PASSWORD_DEFAULT);
}