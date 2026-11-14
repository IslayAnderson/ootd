<?php
/**
 * @param string $fallback local path
 * @return string the page the form was sent from, when it's on this site, else the fallback
 */
function back_path($fallback): string
{
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? "");
    $host = parse_url($referer, PHP_URL_HOST);
    $path = (string)parse_url($referer, PHP_URL_PATH);
    if ($referer !== "" && $host !== null && strtolower($host) === strtolower((string)parse_url(app_url(), PHP_URL_HOST)) && str_starts_with($path, "/") && !str_starts_with($path, "//")) {
        $query = parse_url($referer, PHP_URL_QUERY);
        return $path . ($query ? "?" . $query : "");
    }
    return $fallback;
}
