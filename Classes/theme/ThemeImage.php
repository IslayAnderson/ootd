<?php

/**
 * Images named in a theme's CSS (0.15.0, D70). Each is fetched once, when the theme is saved, and
 * only kept when everything about it says "just an image":
 *
 * - the address is https, on a public IP that isn't this server's own (SafeHttp, which also pins the
 *   connection to the checked address and re-checks every redirect, 3 at most);
 * - no suspicious headers: the type must be PNG, JPEG, GIF or WebP (never SVG, which can carry
 *   script); no Content-Disposition: attachment, Set-Cookie or Refresh; no content encoding; no
 *   Content-Length over 2 MB, and the download stops at 2 MB whatever the headers say;
 * - the body must be only an image: it starts with that type's signature, the file's own type
 *   matches the header, it ends where that format ends (nothing hidden after it, such as a zip),
 *   and it decodes. Media::store() then re-encodes it, which drops metadata and anything else.
 *
 * Visitors are served the stored copy, never the original address, so it can't be swapped for
 * something else after the check, and the other site can't see who visits.
 */
class ThemeImage
{
    const MAX_BYTES = 2097152;
    const MAX_PIXELS = 40000000;
    const TYPES = array("image/png", "image/jpeg", "image/gif", "image/webp");

    /**
     * @param string $url
     * @param int $user_id who saved the theme (owns the stored copy)
     * @return string /media/<id>/display
     * @throws Exception saying what was wrong
     */
    public static function import($url, $user_id): string
    {
        $binary = self::fetch($url);
        $media_id = Media::store($binary, $user_id, "Theme image");
        return "/media/" . $media_id . "/display";
    }

    /**
     * @param string $url
     * @return string the image file
     * @throws Exception
     */
    public static function fetch($url): string
    {
        list(, , $ip) = SafeHttp::check($url);
        if (self::is_own_ip($ip, $_ENV)) {
            throw new Exception("that address is this server");
        }
        try {
            list($status, $body, $headers) = SafeHttp::get($url, array("Accept" => implode(", ", self::TYPES)), self::MAX_BYTES);
        } catch (Exception $e) {
            throw new Exception($e->getMessage() == "Response too large" ? "it's over 2 MB" : "it couldn't be fetched (" . $e->getMessage() . ")");
        }
        if ($status != 200) {
            throw new Exception("the server answered " . $status);
        }
        $type = self::check_headers($headers);
        self::check_body($body, $type);
        return $body;
    }

    /**
     * @param array $headers lower-case name => value
     * @return string the image type
     * @throws Exception
     */
    public static function check_headers($headers): string
    {
        $type = strtolower(trim(explode(";", (string)($headers['content-type'] ?? ""))[0]));
        if (!in_array($type, self::TYPES, true)) {
            throw new Exception("it isn't a PNG, JPEG, GIF or WebP image" . ($type !== "" ? " (it says " . $type . ")" : ""));
        }
        if (stripos((string)($headers['content-disposition'] ?? ""), "attachment") !== false) {
            throw new Exception("it's sent as a download");
        }
        foreach (array("set-cookie" => "it sets a cookie", "refresh" => "it asks to refresh the page") as $name => $why) {
            if (isset($headers[$name])) {
                throw new Exception($why);
            }
        }
        $encoding = strtolower(trim((string)($headers['content-encoding'] ?? "")));
        if ($encoding !== "" && $encoding !== "identity") {
            throw new Exception("it's sent encoded (" . $encoding . ")");
        }
        if (isset($headers['content-length']) && (!ctype_digit(trim($headers['content-length'])) || (int)$headers['content-length'] > self::MAX_BYTES)) {
            throw new Exception("it's over 2 MB");
        }
        return $type;
    }

    /**
     * @param string $body
     * @param string $type from the headers
     * @return void
     * @throws Exception
     */
    public static function check_body($body, $type): void
    {
        $body = (string)$body;
        if (strlen($body) == 0) {
            throw new Exception("it's empty");
        }
        if (strlen($body) > self::MAX_BYTES) {
            throw new Exception("it's over 2 MB");
        }
        $signatures = array(
            "image/png" => "\x89PNG\r\n\x1a\n",
            "image/jpeg" => "\xFF\xD8\xFF",
            "image/gif" => "GIF8",
            "image/webp" => "RIFF"
        );
        if (strpos($body, $signatures[$type]) !== 0 || ($type == "image/webp" && substr($body, 8, 4) !== "WEBP")) {
            throw new Exception("the file isn't the image type it claims to be");
        }
        $sniffed = (new finfo(FILEINFO_MIME_TYPE))->buffer($body);
        if ($sniffed !== $type) {
            throw new Exception("the file isn't the image type it claims to be");
        }
        if (!self::ends_cleanly($body, $type)) {
            throw new Exception("there's something more than an image in the file");
        }
        $size = @getimagesizefromstring($body);
        if ($size === false || ($size['mime'] ?? "") !== $type) {
            throw new Exception("it couldn't be read as an image");
        }
        if ($size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw new Exception("it's too large in pixels");
        }
    }

    /**
     * Does the file stop where its format says it stops? Data after the end (a zip or script tacked
     * on to make a "polyglot") means it isn't only an image.
     */
    public static function ends_cleanly($body, $type): bool
    {
        switch ($type) {
            case "image/png":
                //chunks: 4-byte length, 4-byte type, data, 4-byte CRC; IEND is last
                $offset = 8;
                $length = strlen($body);
                while ($offset + 12 <= $length) {
                    $chunk = unpack("N", substr($body, $offset, 4))[1];
                    $name = substr($body, $offset + 4, 4);
                    $offset += 12 + $chunk;
                    if ($name === "IEND") {
                        return $offset === $length;
                    }
                }
                return false;
            case "image/jpeg":
                //some cameras pad with zero bytes after the end marker; anything else is extra
                return (bool)preg_match('/\xFF\xD9\x00*$/', substr($body, -4096));
            case "image/gif":
                return substr($body, -1) === "\x3B";
            case "image/webp":
                return strlen($body) === unpack("V", substr($body, 4, 4))[1] + 8;
        }
        return false;
    }

    /**
     * @param string $ip
     * @param array $env
     * @return bool is it this server (CLOUD_SERVER_IP, or what APP_URL's host resolves to)
     */
    public static function is_own_ip($ip, $env): bool
    {
        $own = array();
        if (!empty($env['CLOUD_SERVER_IP'])) {
            $own[] = $env['CLOUD_SERVER_IP'];
        }
        //local tests fetch from this machine on purpose (AP_ALLOW_PRIVATE)
        if (empty($env['AP_ALLOW_PRIVATE'])) {
            $host = (string)parse_url((string)($env['APP_URL'] ?? ""), PHP_URL_HOST);
            if ($host !== "") {
                $own = array_merge($own, filter_var(trim($host, "[]"), FILTER_VALIDATE_IP) ? array(trim($host, "[]")) : (gethostbynamel($host) ?: array()));
            }
        }
        $packed = @inet_pton($ip);
        foreach ($own as $address) {
            if ($packed !== false && @inet_pton($address) === $packed) {
                return true;
            }
        }
        return false;
    }
}
