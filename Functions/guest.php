<?php
/**
 * Pages and API endpoints people can reach without signing in. Profile, outfit, garment and blog
 * pages are open so public posts can be shared; they only show guests what's public.
 *
 * @param string $path
 * @param bool|null $public_feed Settings → Public feed; looked up when not given
 * @return bool
 */
function guest_path_allowed($path, $public_feed = null): bool
{
    $paths = array(
        "/login",
        "/signup",
        "/invite",
        "/privacy",
        "/about",
        "/health",
        "/API/user/auth.php",
        "/API/user/create.php",
        "/API/user/invite.php",
        "/forgot",
        "/reset",
        "/API/user/forgot.php",
        "/API/user/reset.php"
    );
    if (in_array($path, $paths, true)) {
        return true;
    }
    //Settings → Public feed: the Local tab, public posts only
    if ($path == "/feed" || $path == "/") {
        if ($public_feed === null) {
            try {
                $public_feed = (int)setting_get("public_feed", 0) === 1;
            } catch (Throwable $e) {
                $public_feed = false;
            }
        }
        return $public_feed;
    }

    $patterns = array(
        '#^/@[^/]+$#',
        '#^/user/[^/]+$#',
        '#^/user/[^/]+/blog/[^/]+$#',
        '#^/outfit/[^/]+$#',
        '#^/comment/[0-9a-f]+$#',
        '#^/garment/[^/]+$#',
        //media checks visibility itself
        '#^/media/[A-Za-z0-9]+/(display|thumb)$#'
    );
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $path)) {
            return true;
        }
    }
    return false;
}
