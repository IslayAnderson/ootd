<?php
/**
 * Pages and API endpoints people can reach without signing in. Profile, outfit, garment and blog
 * pages are open so public posts can be shared; they only show guests what's public.
 *
 * @param string $path
 * @return bool
 */
function guest_path_allowed($path): bool
{
    $paths = array(
        "/login",
        "/signup",
        "/invite",
        "/privacy",
        "/API/user/auth.php",
        "/API/user/create.php",
        "/API/user/invite.php"
    );
    if (in_array($path, $paths, true)) {
        return true;
    }

    $patterns = array(
        '#^/@[^/]+$#',
        '#^/user/[^/]+$#',
        '#^/user/[^/]+/blog/[^/]+$#',
        '#^/outfit/[^/]+$#',
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
