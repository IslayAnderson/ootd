<?php

/**
 * Uploaded images: resized, re-encoded (which strips EXIF, GPS included) and kept in a Storage driver.
 * Rows live in the `media` table; garments, outfits and users point at one by `media_id`.
 * Files are served by /media/<media_id>/<variant>, which only shows them to people who can see what uses them.
 */
class Media
{
    /** longest edge in pixels for each stored size */
    const VARIANTS = array("display" => 1600, "thumb" => 480);
    const JPEG_QUALITY = 85;
    const ALT_MAX = 1500;

    /**
     * @param string $binary image file contents
     * @param int $user_id owner
     * @param string $alt_text
     * @return string public media_id
     * @throws Exception
     */
    public static function store($binary, $user_id, $alt_text = ""): string
    {
        $image = @imagecreatefromstring((string)$binary);
        if ($image === false) {
            throw new Exception('Image could not be read');
        }
        $image = self::orient($image, $binary);
        imagepalettetotruecolor($image);

        $source_mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $thumb = self::resize($image, self::VARIANTS['thumb']);
        //photos become JPEG; only images that really use transparency (cut-out garments) stay PNG
        $png = $source_mime != "image/jpeg" && self::has_alpha($thumb);
        $extension = $png ? "png" : "jpg";
        $mime = $png ? "image/png" : "image/jpeg";

        $media_id = bin2hex(random_bytes(16));
        $storage = media_storage();
        $width = $height = $bytes = 0;
        foreach (self::VARIANTS as $variant => $size) {
            $resized = $variant == "thumb" ? $thumb : self::resize($image, $size);
            $contents = self::encode($resized, $png);
            $storage->put($media_id . "/" . $variant . "." . $extension, $contents, $mime);
            if ($variant == "display") {
                $width = imagesx($resized);
                $height = imagesy($resized);
                $bytes = strlen($contents);
            }
        }

        $params = array(
            ":a" => $media_id,
            ":b" => (int)$user_id,
            ":c" => $mime,
            ":d" => $extension,
            ":e" => $width,
            ":f" => $height,
            ":g" => $bytes,
            ":h" => mb_substr(trim((string)$alt_text), 0, self::ALT_MAX)
        );
        $sql = 'INSERT INTO `media` (`media_id`, `user_id`, `mime`, `extension`, `width`, `height`, `bytes`, `alt_text`)
                VALUES (:a, :b, :c, :d, :e, :f, :g, :h)';
        $db = new Mysql();
        $db->Fetch($sql, $params);

        return $media_id;
    }

    /**
     * @param array|null $file entry from $_FILES
     * @param int $user_id
     * @param string $alt_text
     * @param bool $required
     * @return string|null media_id, null when optional and nothing was uploaded
     * @throws Exception
     */
    public static function store_upload($file, $user_id, $alt_text = "", $required = true): ?string
    {
        $binary = image_upload($file, $required);
        return $binary === null ? null : self::store($binary, $user_id, $alt_text);
    }

    /**
     * Phone photos are often stored sideways with an EXIF orientation flag; bake the rotation in
     * before re-encoding drops the flag
     */
    private static function orient($image, $binary)
    {
        if (!function_exists("exif_read_data") || substr($binary, 0, 2) != "\xFF\xD8") {
            return $image;
        }
        $exif = @exif_read_data("data://image/jpeg;base64," . base64_encode($binary));
        switch ((int)($exif['Orientation'] ?? 1)) {
            case 3:
                return imagerotate($image, 180, 0);
            case 6:
                return imagerotate($image, -90, 0);
            case 8:
                return imagerotate($image, 90, 0);
        }
        return $image;
    }

    private static function resize($image, $longest)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $longest / max($width, $height));
        $new_width = max(1, (int)round($width * $scale));
        $new_height = max(1, (int)round($height * $scale));

        $resized = imagecreatetruecolor($new_width, $new_height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        return $resized;
    }

    private static function has_alpha($image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int)floor(min($width, $height) / 120));
        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function encode($image, $png): string
    {
        ob_start();
        if ($png) {
            imagepng($image, null, 6);
        } else {
            //flatten onto white so transparent areas of a jpeg-bound image aren't black
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imageinterlace($flat, true);
            imagejpeg($flat, null, self::JPEG_QUALITY);
        }
        return (string)ob_get_clean();
    }

    /**
     * @param string $media_id
     * @return object|null media row, null when missing or deleted
     */
    public static function get($media_id)
    {
        if (empty($media_id)) {
            return null;
        }
        $sql = "SELECT * FROM `media` WHERE `media_id` = :a AND `deleted_at` IS NULL";
        $db = new Mysql();
        $row = $db->Fetch($sql, array(":a" => (string)$media_id));
        return $row[0] != '00000' ? $row[0] : null;
    }

    /**
     * An image is visible when whatever uses it is: avatars always (they're on public profiles),
     * garment and outfit photos follow that post's visibility, photos from other servers are for
     * members only, anything unattached only to its owner
     *
     * @param object $media row from get()
     * @return array [bool can view, bool public]
     */
    public static function access($media): array
    {
        //the site's logo is on every page, signed in or not
        if ((string)setting_get("logo_media", "") === $media->media_id) {
            return array(true, true);
        }
        $db = new Mysql();
        $row = $db->Fetch("SELECT `user_id` FROM `users` WHERE `media_id` = :a", array(":a" => $media->media_id));
        if ($row[0] != '00000') {
            return array(true, true);
        }
        foreach (array("outfits" => "user", "garments" => "user_id") as $table => $owner) {
            $sql = "SELECT `" . $owner . "` AS owner, `visibility` FROM `" . $table . "` WHERE `media_id` = :a AND `deleted_at` IS NULL";
            $row = $db->Fetch($sql, array(":a" => $media->media_id));
            if ($row[0] != '00000') {
                return array(can_view($row[0]->owner, $row[0]->visibility), $row[0]->visibility == VISIBILITY_PUBLIC);
            }
        }
        //a photo copied from another server: members who can see that post
        $row = $db->Fetch("SELECT remote_posts.audience, remote_posts.actor_num FROM `remote_media`
                           JOIN `remote_posts` ON remote_posts.post_num = remote_media.post_num WHERE remote_media.media_id = :a", array(":a" => $media->media_id));
        if ($row[0] != '00000') {
            $viewer = (int)($_SESSION['user_id'] ?? 0);
            $can = $viewer > 0 && ($row[0]->audience == VISIBILITY_PUBLIC || RemoteFollow::state($viewer, $row[0]->actor_num) === Follow::ACCEPTED);
            return array($can, false);
        }
        return array($media->user_id == ($_SESSION['user_id'] ?? null), false);
    }

    /**
     * Can other servers be given this image? Avatars, and photos of public or followers-only outfits and garments
     * (followers-only posts are delivered to followers' servers, which fetch the photo themselves).
     *
     * @param object $media row from get()
     * @return bool
     */
    public static function federated($media): bool
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `user_id` FROM `users` WHERE `media_id` = :a", array(":a" => $media->media_id));
        if ($row[0] != '00000') {
            return true;
        }
        //outfit photos, and since 0.13.0 garment photos (other OOTD servers show an outfit's garments)
        foreach (array("outfits", "garments") as $table) {
            $row = $db->Fetch("SELECT `visibility` FROM `" . $table . "` WHERE `media_id` = :a AND `deleted_at` IS NULL", array(":a" => $media->media_id));
            if ($row[0] != '00000' && in_array($row[0]->visibility, array(VISIBILITY_PUBLIC, VISIBILITY_FOLLOWERS), true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $media_id
     * @param int $user_id must own the media
     * @param string $alt_text
     * @return void
     */
    public static function set_alt($media_id, $user_id, $alt_text): void
    {
        $sql = "UPDATE `media` SET `alt_text` = :c WHERE `media_id` = :a AND `user_id` = :b";
        $db = new Mysql();
        $db->Fetch($sql, array(":a" => (string)$media_id, ":b" => (int)$user_id, ":c" => mb_substr(trim((string)$alt_text), 0, self::ALT_MAX)));
    }

    /**
     * Delete the files and mark the row deleted, used when a photo is replaced
     *
     * @param string $media_id
     * @return void
     */
    public static function remove($media_id): void
    {
        $media = self::get($media_id);
        if (!$media) {
            return;
        }
        foreach (array_keys(self::VARIANTS) as $variant) {
            media_storage()->delete($media->media_id . "/" . $variant . "." . $media->extension);
        }
        $db = new Mysql();
        $db->Fetch("UPDATE `media` SET `deleted_at` = NOW() WHERE `media_id` = :a", array(":a" => $media->media_id));
    }
}
