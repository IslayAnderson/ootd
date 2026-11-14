<?php
/**
 * @param $bin
 * @param string $fallback url used when there is no image
 * @return string
 */
function image_bin_encode($bin, $fallback = "/Assets/images/icon.svg"): string
{
    if (empty($bin)) {
        return $fallback;
    }

    $fi = new finfo(FILEINFO_MIME_TYPE);
    return "data:" . $fi->buffer($bin) . ";base64," . base64_encode($bin);
}
