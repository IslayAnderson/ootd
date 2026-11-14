<?php

/**
 * "Download your data": a zip of everything an account has put on this server, as JSON plus the
 * full-size photos. Covers GDPR access and portability requests, and is what moving to another
 * instance will start from. Deleted posts are left out; their photos are purged after 30 days (D17).
 */
class Export
{
    /**
     * @param int $user_id
     * @return string path to a temporary zip, the caller deletes it
     * @throws Exception
     */
    public static function build($user_id): string
    {
        $user_id = (int)$user_id;
        $db = new Mysql();
        $rows = function ($sql) use ($db, $user_id) {
            $result = $db->Fetch($sql, array(":a" => $user_id));
            return $result[0] != '00000' ? array_map(function ($row) {
                return (array)$row;
            }, $result) : array();
        };

        $profile = $rows("SELECT `username`, `email`, `first_name`, `last_name`, `media_id` AS avatar, `approve_followers` FROM `users` WHERE `user_id` = :a");
        if (count($profile) == 0) {
            throw new Exception("No such account");
        }
        $profile = $profile[0];
        $profile['instance'] = app_url();
        $profile['exported_at'] = date("c");
        $profile['ootd_version'] = OOTD_VERSION;

        $garments = $rows("SELECT garments.garment_num, garments.garment_id, garments.garment_name, garments.description, garments.tags, brands.brand_name AS brand,
                                  categories.categories AS category, garments.visibility, garments.media_id, garments.timestamp, garments.updated_at
                           FROM `garments`
                           LEFT JOIN `brands` ON brands.brand_id = garments.brand
                           LEFT JOIN `categories` ON categories.category_id = garments.category
                           WHERE garments.user_id = :a AND garments.deleted_at IS NULL ORDER BY garments.garment_num");
        $outfits = $rows("SELECT `outfit_num`, `outfit_id`, `description`, `tags`, `garments`, `visibility`, `media_id`, `timestamp`, `updated_at`
                          FROM `outfits` WHERE `user` = :a AND `deleted_at` IS NULL ORDER BY `outfit_num`");
        $blogs = $rows("SELECT `blog_id`, `title`, `excerpt`, `content`, `tags`, `outfits`, `garments`, `visibility`, `timestamp`, `updated_at`
                        FROM `blogs` WHERE `user` = :a AND `deleted_at` IS NULL ORDER BY `blog_num`");
        //posts point at each other by internal number; the export uses the public ids instead
        $garment_ids = array_column($garments, "garment_id", "garment_num");
        $outfit_ids = array_column($outfits, "outfit_id", "outfit_num");
        $to_ids = function ($list, $ids) {
            $nums = json_decode((string)$list, true);
            return is_array($nums) ? array_values(array_filter(array_map(function ($num) use ($ids) {
                return $ids[$num] ?? null;
            }, $nums))) : array();
        };
        foreach ($garments as $i => $garment) {
            unset($garments[$i]['garment_num']);
        }
        foreach ($outfits as $i => $outfit) {
            $outfits[$i]['garments'] = $to_ids($outfit['garments'], $garment_ids);
            unset($outfits[$i]['outfit_num']);
        }
        foreach ($blogs as $i => $blog) {
            $blogs[$i]['garments'] = $to_ids($blog['garments'], $garment_ids);
            $blogs[$i]['outfits'] = $to_ids($blog['outfits'], $outfit_ids);
        }

        $wears = $rows("SELECT outfits.outfit_id, garments.garment_id, wears.worn_on FROM `wears`
                        LEFT JOIN `outfits` ON outfits.outfit_num = wears.outfit_num
                        LEFT JOIN `garments` ON garments.garment_num = wears.garment_num
                        WHERE wears.user = :a ORDER BY wears.wear_num");
        $following = $rows("SELECT users.username, follows.state FROM `follows` JOIN `users` ON users.user_id = follows.followed_id WHERE follows.follower_id = :a");
        $followers = $rows("SELECT users.username, follows.state FROM `follows` JOIN `users` ON users.user_id = follows.follower_id WHERE follows.followed_id = :a");
        $media = $rows("SELECT `media_id`, `mime`, `extension`, `width`, `height`, `alt_text`, `timestamp` FROM `media` WHERE `user_id` = :a AND `deleted_at` IS NULL");

        $path = tempnam($_SERVER['DOCUMENT_ROOT'] . "/tmp", "export");
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Couldn't create the export");
        }
        $json = function ($data) {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        };
        $zip->addFromString("README.txt", "Your " . instance_name() . " data, exported " . date("j F Y") . " from " . app_url() . ".\n\n"
            . "profile.json    your account (no password)\n"
            . "garments.json   your wardrobe\n"
            . "outfits.json    your outfits; garments are garment_ids\n"
            . "blogs.json      your journal entries\n"
            . "wears.json      every time you logged wearing something\n"
            . "follows.json    who you follow and who follows you\n"
            . "media.json      your photos; the files are in media/, named by media_id\n");
        $zip->addFromString("profile.json", $json($profile));
        $zip->addFromString("garments.json", $json($garments));
        $zip->addFromString("outfits.json", $json($outfits));
        $zip->addFromString("blogs.json", $json($blogs));
        $zip->addFromString("wears.json", $json($wears));
        $zip->addFromString("follows.json", $json(array("following" => $following, "followers" => $followers)));
        $zip->addFromString("media.json", $json($media));

        $storage = media_storage();
        foreach ($media as $item) {
            $contents = $storage->get($item['media_id'] . "/display." . $item['extension']);
            if ($contents !== null) {
                $zip->addFromString("media/" . $item['media_id'] . "." . $item['extension'], $contents);
            }
        }
        $zip->close();
        return $path;
    }
}
