<?php
/**
 * @return string the site's address without a trailing slash, from APP_URL in .env
 * falls back to the current request for installs from before 0.7.0
 */
function app_url(): string
{
    if (!empty($_ENV['APP_URL'])) {
        return rtrim($_ENV['APP_URL'], "/");
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off") ? "https" : "http";
    return $scheme . "://" . ($_SERVER['HTTP_HOST'] ?? "localhost");
}

/**
 * @param string $path site-relative path, e.g. /outfit/<outfit_id>
 * @return string
 */
function absolute_url($path = "/"): string
{
    return app_url() . "/" . ltrim($path, "/");
}
