<?php
/**
 * URL for a file under Assets/ with its modification time as a version, so caches
 * (browsers, Cloudflare) fetch the new file after a deploy instead of keeping the old one.
 *
 * @param string $path e.g. "/Assets/stylesheets/css/main.css"
 * @return string
 */
function asset_url(string $path): string
{
    $file = $_SERVER['DOCUMENT_ROOT'] . $path;
    $time = is_file($file) ? filemtime($file) : 0;
    return htmlspecialchars($path . ($time ? "?v=" . $time : ""), ENT_QUOTES);
}
