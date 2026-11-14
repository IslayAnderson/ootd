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
