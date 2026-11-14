<?php
/**
 * Which sites may show this one in a frame (0.14.7). Always this site itself, plus the origins in
 * FRAME_ANCESTORS (space- or comma-separated, e.g. "https://example.com https://www.example.com";
 * "https://*.example.com" for every subdomain). Anything else is refused by the browser, so nobody
 * can wrap the login page in their own (clickjacking).
 *
 * @param array $env
 * @return string the Content-Security-Policy value
 */
function frame_policy($env): string
{
    $allowed = array("'self'");
    foreach (preg_split('/[\s,]+/', trim((string)($env['FRAME_ANCESTORS'] ?? ""))) as $origin) {
        $origin = strtolower(rtrim($origin, "/"));
        if (preg_match('#^https?://(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)*(:[0-9]{1,5})?$#', $origin) && !in_array($origin, $allowed, true)) {
            $allowed[] = $origin;
        }
    }
    //themes (0.15.0): stylesheets only from here and Google Fonts, fonts only from here and
    //Google's font files, so a theme can't load anything else even if a rule slipped through
    return "frame-ancestors " . implode(" ", $allowed)
        . "; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com";
}

function send_frame_policy(): void
{
    if (!headers_sent()) {
        header("Content-Security-Policy: " . frame_policy($_ENV));
    }
}
