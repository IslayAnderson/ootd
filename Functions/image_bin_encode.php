<?php
/**
 * @param $bin
 * @return string
 */
function image_bin_encode($bin): string
{
    $fi = new finfo();
    $mime_type = explode(" ", $fi->buffer($bin));
    return "data:" . $mime_type[1] . "/" . $mime_type[0] . ";base64, " . base64_encode($bin);
}