<?php
/**
 * The site's logo (top bar, login page). Settings → Logo picks the OOTD wordmark, an uploaded
 * image (logo_media) or the blog name set in type. An uploaded image that is missing falls back
 * to the wordmark.
 *
 * @param string $class extra class for the element
 * @return string HTML
 */
function site_logo($class = ""): string
{
    $name = instance_name();
    $style = setting_get("logo", "wordmark");
    $media_id = (string)setting_get("logo_media", "");
    $class = trim("site-logo " . $class);
    if ($style == "name") {
        return '<span class="' . escape($class) . ' site-logo--text">' . escape($name) . '</span>';
    }
    if ($style == "image" && $media_id !== "" && Media::get($media_id)) {
        return '<img class="' . escape($class) . '" alt="' . escape($name) . '" src="' . escape(media_url($media_id, "display")) . '">';
    }
    return '<img class="' . escape($class) . '" alt="' . escape($name) . '" src="' . asset_url("/Assets/images/logo.svg") . '">';
}
