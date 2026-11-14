<?php
/**
 * @return Storage the driver picked by MEDIA_DRIVER in .env: local (default) or s3
 */
function media_storage(): Storage
{
    static $storage = null;
    if ($storage === null) {
        $storage = strtolower($_ENV['MEDIA_DRIVER'] ?? "local") == "s3" ? new StorageS3() : new StorageLocal();
    }
    return $storage;
}

/**
 * @param string|null $media_id
 * @param string $variant display or thumb
 * @param string $fallback used when there's no image
 * @return string
 */
function media_url($media_id, $variant = "display", $fallback = "/Assets/images/icon.svg"): string
{
    if (empty($media_id)) {
        return $fallback;
    }
    return "/media/" . rawurlencode($media_id) . "/" . (array_key_exists($variant, Media::VARIANTS) ? $variant : "display");
}

/**
 * @param string $media_id
 * @return string key that lets anyone holding the link fetch a federated image (see Pages/media.php)
 */
function media_share_key($media_id): string
{
    return substr(hash_hmac("sha256", "media " . $media_id, (string)($_ENV['PWD_SEED'] ?? "")), 0, 32);
}

/**
 * @param string $media_id
 * @param string $variant
 * @param bool $keyed add the share key, for photos of followers-only posts
 * @return string absolute address for other servers
 */
function media_absolute_url($media_id, $variant = "display", $keyed = false): string
{
    return absolute_url("/media/" . rawurlencode($media_id) . "/" . $variant) . ($keyed ? "?k=" . media_share_key($media_id) : "");
}
