<?php
// /media/<media_id>/<variant>, served before any page markup by index.php
$path = explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$media = Media::get(rawurldecode($path[2] ?? ""));
$variant = $path[3] ?? "display";

//images don't touch the session, so let the page's other requests run in parallel
session_write_close();

list($allowed, $public) = $media ? Media::access($media) : array(false, false);
//links handed to other servers carry a key, so followers-only photos can be fetched by their servers
if ($media && !$allowed && hash_equals(media_share_key($media->media_id), (string)($_GET['k'] ?? "")) && Media::federated($media)) {
    $allowed = true;
}
$contents = $allowed ? media_storage()->get($media->media_id . "/" . $variant . "." . $media->extension) : null;

if ($contents === null) {
    http_response_code(404);
    header("Content-Type: text/plain");
    echo "Not found";
    exit();
}

//a media_id never changes content (replacing a photo makes a new one), so it can be cached for good
$etag = '"' . $media->media_id . "-" . $variant . '"';
header("Content-Type: " . $media->mime);
header("X-Content-Type-Options: nosniff");
header("ETag: " . $etag);
header("Cache-Control: " . ($public ? "public, max-age=31536000, immutable" : "private, max-age=3600"));
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? "") === $etag) {
    http_response_code(304);
    exit();
}
header("Content-Length: " . strlen($contents));
echo $contents;
exit();
