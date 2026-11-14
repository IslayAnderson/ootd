<?php

/**
 * Posts from people on other servers that someone here follows. They're kept in their own tables
 * (remote_posts, remote_media) rather than mixed into outfits, because they have no garments,
 * wears or local owner. Only signed-in members see them: public ones to every member,
 * followers-only ones to members who follow that person.
 *
 * Replies and direct messages aren't stored (replies arrive with comments in 0.13.0). Photos are
 * copied into the media store by the worker, never hot-linked, and the oldest are dropped when
 * the remote_media_mb setting is exceeded.
 */
class RemotePost
{
    const TYPES = array("Note", "Article", "Image", "Page");
    const MAX_ATTACHMENTS = 4;
    const MAX_CONTENT = 100000;
    const MAX_PIXELS = 50000000;
    const BACKFILL = 20;
    const MAX_GARMENTS = 12;

    /**
     * @param mixed $value
     * @return array a single value or a list, as a list of ids
     */
    public static function ids($value): array
    {
        if ($value === null) {
            return array();
        }
        $list = is_array($value) && array_is_list($value) ? $value : array($value);
        return array_values(array_filter(array_map(array("Inbox", "id"), $list)));
    }

    /**
     * @param object $actor remote_actors row
     * @param array $object
     * @return string|null public, followers, or null when it's addressed to particular people (a DM)
     */
    public static function audience($actor, $object): ?string
    {
        $to = array_merge(self::ids($object['to'] ?? null), self::ids($object['cc'] ?? null));
        if (count(array_intersect($to, array(Federation::PUBLIC_AUDIENCE, "as:Public", "Public"))) > 0) {
            return VISIBILITY_PUBLIC;
        }
        $followers = array_filter(array($actor->followers ?? null, $actor->ap_id . "/followers"));
        return count(array_intersect($to, $followers)) > 0 ? VISIBILITY_FOLLOWERS : null;
    }

    /**
     * @param mixed $url a string, a Link, or a list of either
     * @return string|null the first http(s) address
     */
    private static function href($url): ?string
    {
        foreach (is_array($url) && array_is_list($url) ? $url : array($url) as $candidate) {
            $href = is_string($candidate) ? $candidate : (is_array($candidate) ? ($candidate['href'] ?? null) : null);
            if (is_string($href) && preg_match('#^https?://#i', $href)) {
                return mb_substr($href, 0, 1024);
            }
        }
        return null;
    }

    private static function time($value): ?string
    {
        $time = is_string($value) ? strtotime($value) : false;
        return $time ? date("Y-m-d H:i:s", min($time, time())) : null;
    }

    /**
     * Store (or update) a post someone elsewhere made
     *
     * @param object $actor remote_actors row of who sent it
     * @param array $object Note, Article...
     * @param array $activity the Create/Update around it, whose audience is used when the object has none
     * @return bool stored
     */
    public static function ingest($actor, $object, $activity = array()): bool
    {
        if (!is_array($object) || !in_array($object['type'] ?? null, self::TYPES, true)) {
            return false;
        }
        $id = Inbox::id($object);
        //it must be theirs, and live on their server, so nobody can plant or overwrite someone else's post
        if (!is_string($id) || !in_array($actor->ap_id, self::ids($object['attributedTo'] ?? null), true)
            || strtolower((string)parse_url($id, PHP_URL_HOST)) !== strtolower((string)parse_url($actor->ap_id, PHP_URL_HOST))) {
            return false;
        }
        if (!empty($object['inReplyTo'])) {
            return false;
        }
        if (!isset($object['to']) && !isset($object['cc'])) {
            $object['to'] = $activity['to'] ?? null;
            $object['cc'] = $activity['cc'] ?? null;
        }
        $audience = self::audience($actor, $object);
        if ($audience === null || !RemoteFollow::followed($actor->actor_num)) {
            return false;
        }

        $db = new Mysql();
        $existing = $db->Fetch("SELECT `post_num`, `actor_num` FROM `remote_posts` WHERE `ap_id` = :a", array(":a" => $id));
        if ($existing[0] != '00000' && (int)$existing[0]->actor_num !== (int)$actor->actor_num) {
            return false;
        }
        $params = array(
            ":a" => mb_substr($id, 0, 512),
            ":b" => (int)$actor->actor_num,
            ":c" => $object['type'],
            ":d" => $audience,
            ":e" => self::href($object['url'] ?? null) ?? (preg_match('#^https?://#i', $id) ? mb_substr($id, 0, 512) : null),
            ":f" => is_string($object['name'] ?? null) ? mb_substr(strip_tags($object['name']), 0, 512) : null,
            ":g" => is_string($object['summary'] ?? null) ? mb_substr($object['summary'], 0, 5000) : null,
            ":h" => is_string($object['content'] ?? null) ? mb_substr($object['content'], 0, self::MAX_CONTENT) : null,
            ":i" => !empty($object['sensitive']) ? 1 : 0,
            ":j" => self::time($object['published'] ?? null) ?? date("Y-m-d H:i:s"),
            ":k" => self::time($object['updated'] ?? null)
        );
        if ($existing[0] == '00000') {
            $db->Fetch("INSERT INTO `remote_posts` (`ap_id`, `actor_num`, `type`, `audience`, `url`, `name`, `summary`, `content`, `marked_sensitive`, `published`, `updated_at`)
                        VALUES (:a, :b, :c, :d, :e, :f, :g, :h, :i, :j, :k)", $params);
            $post_num = (int)$db->Fetch("SELECT `post_num` FROM `remote_posts` WHERE `ap_id` = :a", array(":a" => $params[":a"]))[0]->post_num;
        } else {
            $post_num = (int)$existing[0]->post_num;
            unset($params[":b"], $params[":j"]);
            $params[":n"] = $post_num;
            $db->Fetch("UPDATE `remote_posts` SET `ap_id` = :a, `type` = :c, `audience` = :d, `url` = :e, `name` = :f, `summary` = :g, `content` = :h,
                        `marked_sensitive` = :i, `updated_at` = COALESCE(:k, NOW()) WHERE `post_num` = :n", $params);
        }
        self::sync_media($post_num, $object['attachment'] ?? array());
        self::sync_garments($post_num, $object['ootd:garments'] ?? null);
        return true;
    }

    /**
     * Garments on an outfit from another OOTD server (ootd:garments): replaced on every update.
     * Brand and category names are matched to this server's lists; unknown names are kept as text.
     *
     * @param int $post_num
     * @param mixed $garments
     * @return void
     */
    private static function sync_garments($post_num, $garments): void
    {
        $db = new Mysql();
        $old = $db->Fetch("SELECT remote_media.media_id FROM `remote_garments` JOIN `remote_media` ON remote_media.remote_media_num = remote_garments.remote_media_num
                           WHERE remote_garments.post_num = :a AND remote_media.media_id IS NOT NULL", array(":a" => (int)$post_num));
        foreach ($old[0] != '00000' ? $old : array() as $row) {
            Media::remove($row->media_id);
        }
        $db->Fetch("DELETE FROM `remote_media` WHERE `post_num` = :a AND `kind` = 'garment'", array(":a" => (int)$post_num));
        $db->Fetch("DELETE FROM `remote_garments` WHERE `post_num` = :a", array(":a" => (int)$post_num));

        $list = is_array($garments) && array_is_list($garments) ? $garments : array();
        foreach (array_slice($list, 0, self::MAX_GARMENTS) as $position => $garment) {
            if (!is_array($garment) || !in_array($garment['type'] ?? "", array("ootd:Garment", "Garment"), true) || !is_string($garment['name'] ?? null)) {
                continue;
            }
            $brand = self::text($garment['ootd:brand'] ?? null, 100);
            $category = self::text($garment['ootd:category'] ?? null, 100);
            $brand_id = $brand !== null ? $db->Fetch("SELECT `brand_id` FROM `brands` WHERE LOWER(`brand_name`) = LOWER(:a) LIMIT 1", array(":a" => $brand)) : array('00000');
            $category_id = $category !== null ? $db->Fetch("SELECT `category_id` FROM `categories` WHERE LOWER(`categories`) = LOWER(:a) AND `in_moderation` = 0 LIMIT 1", array(":a" => $category)) : array('00000');
            $media_num = null;
            $image = self::href(is_array($garment['image'] ?? null) ? ($garment['image']['url'] ?? null) : ($garment['image'] ?? null));
            if ($image) {
                $db->Fetch("INSERT INTO `remote_media` (`post_num`, `position`, `remote_url`, `alt_text`, `kind`) VALUES (:a, :b, :c, :d, 'garment')",
                    array(":a" => (int)$post_num, ":b" => $position, ":c" => $image, ":d" => self::text($garment['image']['name'] ?? null, Media::ALT_MAX) ?? $garment['name']));
                $media_num = (int)$db->Fetch("SELECT MAX(`remote_media_num`) AS n FROM `remote_media` WHERE `post_num` = :a", array(":a" => (int)$post_num))[0]->n;
                Jobs::dispatch("ap_media", array("remote_media_num" => $media_num));
            }
            $db->Fetch("INSERT INTO `remote_garments` (`post_num`, `position`, `ap_id`, `name`, `brand_name`, `category_name`, `brand_id`, `category_id`, `description`, `url`, `remote_media_num`)
                        VALUES (:a, :b, :c, :d, :e, :f, :g, :h, :i, :j, :k)", array(
                ":a" => (int)$post_num,
                ":b" => $position,
                ":c" => self::text(Inbox::id($garment), 512),
                ":d" => self::text($garment['name'], 128),
                ":e" => $brand,
                ":f" => $category,
                ":g" => $brand_id[0] != '00000' ? (int)$brand_id[0]->brand_id : null,
                ":h" => $category_id[0] != '00000' ? (int)$category_id[0]->category_id : null,
                ":i" => self::text($garment['summary'] ?? null, 1024),
                ":j" => self::href($garment['url'] ?? null) ?? self::href(Inbox::id($garment)),
                ":k" => $media_num
            ));
        }
    }

    private static function text($value, $max): ?string
    {
        if (!is_string($value) || trim(strip_tags($value)) === "") {
            return null;
        }
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }

    /**
     * Make the post's photos match its attachments: unchanged ones are kept, new ones queued
     *
     * @param int $post_num
     * @param mixed $attachments
     * @return void
     */
    private static function sync_media($post_num, $attachments): void
    {
        $wanted = array();
        $list = is_array($attachments) && array_is_list($attachments) ? $attachments : array($attachments);
        foreach ($list as $attachment) {
            if (!is_array($attachment) || count($wanted) >= self::MAX_ATTACHMENTS) {
                continue;
            }
            $media_type = (string)($attachment['mediaType'] ?? "");
            $is_image = str_starts_with($media_type, "image/") || ($media_type === "" && ($attachment['type'] ?? "") === "Image");
            $url = self::href($attachment['url'] ?? null);
            if ($is_image && $url) {
                $wanted[] = array("url" => $url, "alt" => is_string($attachment['name'] ?? null) ? mb_substr($attachment['name'], 0, Media::ALT_MAX) : "");
            }
        }

        $db = new Mysql();
        $rows = $db->Fetch("SELECT * FROM `remote_media` WHERE `post_num` = :a AND `kind` = 'photo' ORDER BY `position`", array(":a" => (int)$post_num));
        $have = $rows[0] != '00000' ? $rows : array();
        if (array_column($wanted, "url") === array_map(function ($row) {
                return $row->remote_url;
            }, $have)) {
            foreach ($have as $i => $row) {
                $db->Fetch("UPDATE `remote_media` SET `alt_text` = :b WHERE `remote_media_num` = :a", array(":a" => $row->remote_media_num, ":b" => $wanted[$i]['alt']));
                if (!empty($row->media_id)) {
                    $db->Fetch("UPDATE `media` SET `alt_text` = :b WHERE `media_id` = :a", array(":a" => $row->media_id, ":b" => $wanted[$i]['alt']));
                }
            }
            return;
        }
        self::drop_media($post_num, "photo");
        foreach ($wanted as $position => $item) {
            $db->Fetch("INSERT INTO `remote_media` (`post_num`, `position`, `remote_url`, `alt_text`) VALUES (:a, :b, :c, :d)",
                array(":a" => (int)$post_num, ":b" => $position, ":c" => $item['url'], ":d" => $item['alt']));
            $num = $db->Fetch("SELECT MAX(`remote_media_num`) AS n FROM `remote_media` WHERE `post_num` = :a", array(":a" => (int)$post_num))[0]->n;
            Jobs::dispatch("ap_media", array("remote_media_num" => (int)$num));
        }
    }

    /**
     * @param int $post_num
     * @param string|null $kind photo or garment, null for both
     * @return void
     */
    private static function drop_media($post_num, $kind = null): void
    {
        $db = new Mysql();
        $where = "`post_num` = :a" . ($kind ? " AND `kind` = :k" : "");
        $params = $kind ? array(":a" => (int)$post_num, ":k" => $kind) : array(":a" => (int)$post_num);
        $rows = $db->Fetch("SELECT `media_id` FROM `remote_media` WHERE " . $where . " AND `media_id` IS NOT NULL", $params);
        if ($rows[0] != '00000') {
            foreach ($rows as $row) {
                Media::remove($row->media_id);
            }
        }
        $db->Fetch("DELETE FROM `remote_media` WHERE " . $where, $params);
    }

    /**
     * @param string $where SQL condition on remote_posts
     * @param array $params
     * @return int how many posts were removed
     */
    private static function drop($where, $params): int
    {
        $db = new Mysql();
        $rows = $db->Fetch("SELECT `post_num` FROM `remote_posts` WHERE " . $where, $params);
        if ($rows[0] == '00000') {
            return 0;
        }
        foreach ($rows as $row) {
            self::drop_media($row->post_num);
            $db->Fetch("DELETE FROM `remote_garments` WHERE `post_num` = :a", array(":a" => $row->post_num));
            $db->Fetch("DELETE FROM `likes` WHERE `kind` = 'remote' AND `object_num` = :a", array(":a" => $row->post_num));
            $db->Fetch("DELETE FROM `comments` WHERE `kind` = 'remote' AND `parent_num` = :a", array(":a" => $row->post_num));
            $db->Fetch("DELETE FROM `remote_posts` WHERE `post_num` = :a", array(":a" => $row->post_num));
        }
        return count($rows);
    }

    /**
     * They deleted a post
     *
     * @param object $actor
     * @param string $id
     * @return void
     */
    public static function remove($actor, $id): void
    {
        self::drop("`ap_id` = :a AND `actor_num` = :b", array(":a" => (string)$id, ":b" => (int)$actor->actor_num));
    }

    /**
     * Their account is gone, or nobody here follows them any more: forget their posts
     *
     * @param int $actor_num
     * @return void
     */
    public static function forget($actor_num): void
    {
        self::drop("`actor_num` = :a", array(":a" => (int)$actor_num));
    }

    /**
     * @param int $actor_num
     * @return void
     */
    public static function forget_unfollowed($actor_num): void
    {
        if (!RemoteFollow::followed($actor_num)) {
            self::forget($actor_num);
        }
    }

    /**
     * Job: copy one photo into the media store
     *
     * @param array $payload remote_media_num
     * @return void
     * @throws Exception to retry after a network error
     */
    public static function fetch_media($payload): void
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT remote_media.* FROM `remote_media` JOIN `remote_posts` ON remote_posts.post_num = remote_media.post_num
                           WHERE remote_media.remote_media_num = :a AND remote_media.state = 'pending'", array(":a" => (int)($payload['remote_media_num'] ?? 0)));
        if ($row[0] == '00000') {
            return;
        }
        $item = $row[0];
        $fail = function () use ($db, $item) {
            $db->Fetch("UPDATE `remote_media` SET `state` = 'failed' WHERE `remote_media_num` = :a", array(":a" => $item->remote_media_num));
        };
        try {
            list($status, $body) = SafeHttp::get($item->remote_url, array("Accept" => "image/*"), SafeHttp::MAX_MEDIA_BYTES);
        } catch (Exception $e) {
            if (str_starts_with($e->getMessage(), "Request failed")) {
                throw $e;
            }
            $fail();
            return;
        }
        if ($status == 429 || $status >= 500) {
            throw new Exception("Photo server answered " . $status);
        }
        $size = $status == 200 ? @getimagesizefromstring($body) : false;
        if (!$size || $size[0] * $size[1] > self::MAX_PIXELS) {
            $fail();
            return;
        }
        try {
            $media_id = Media::store($body, 0, (string)$item->alt_text);
        } catch (Exception $e) {
            $fail();
            return;
        }
        $db->Fetch("UPDATE `remote_media` SET `state` = 'stored', `media_id` = :b WHERE `remote_media_num` = :a", array(":a" => $item->remote_media_num, ":b" => $media_id));
    }

    /**
     * Job: when a follow is accepted, fill their profile with their latest public posts
     *
     * @param array $payload actor_num
     * @return void
     */
    public static function backfill($payload): void
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `ap_id` FROM `remote_actors` WHERE `actor_num` = :a", array(":a" => (int)($payload['actor_num'] ?? 0)));
        if ($row[0] == '00000') {
            return;
        }
        $actor = RemoteActor::get($row[0]->ap_id, true);
        if (!$actor || empty($actor->outbox) || !self::same_host($actor->outbox, $actor->ap_id)) {
            return;
        }
        try {
            $outbox = RemoteActor::fetch($actor->outbox);
            $page = $outbox;
            if (!isset($outbox['orderedItems']) && !isset($outbox['items']) && isset($outbox['first'])) {
                $page = is_array($outbox['first']) ? $outbox['first'] : (self::same_host($outbox['first'], $actor->ap_id) ? RemoteActor::fetch($outbox['first']) : array());
            }
        } catch (Exception $e) {
            return;
        }
        $items = $page['orderedItems'] ?? $page['items'] ?? array();
        foreach (array_slice(is_array($items) ? $items : array(), 0, self::BACKFILL) as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== "Create" || Inbox::id($item['actor'] ?? null) !== $actor->ap_id) {
                continue;
            }
            $object = $item['object'] ?? null;
            if (is_string($object) && self::same_host($object, $actor->ap_id)) {
                try {
                    $object = RemoteActor::fetch($object);
                } catch (Exception $e) {
                    continue;
                }
            }
            self::ingest($actor, $object, $item);
        }
    }

    private static function same_host($a, $b): bool
    {
        $host = strtolower((string)parse_url((string)$a, PHP_URL_HOST));
        return $host !== "" && $host === strtolower((string)parse_url((string)$b, PHP_URL_HOST));
    }

    /**
     * Job (daily): drop the oldest copied photos when they take more room than remote_media_mb.
     * The post stays, with a link to the photo on its own server.
     *
     * @return int photos dropped
     */
    public static function purge_media(): int
    {
        $limit = max(0, (int)setting_get("remote_media_mb", 1024)) * 1048576;
        $db = new Mysql();
        $total = (int)$db->Fetch("SELECT COALESCE(SUM(media.bytes), 0) AS total FROM `remote_media`
                                  JOIN `media` ON media.media_id = remote_media.media_id WHERE remote_media.state = 'stored'", null)[0]->total;
        $dropped = 0;
        while ($total > $limit) {
            $rows = $db->Fetch("SELECT remote_media.remote_media_num, remote_media.media_id, media.bytes FROM `remote_media`
                                JOIN `media` ON media.media_id = remote_media.media_id
                                JOIN `remote_posts` ON remote_posts.post_num = remote_media.post_num
                                WHERE remote_media.state = 'stored' ORDER BY remote_posts.published ASC LIMIT 50", null);
            if ($rows[0] == '00000') {
                break;
            }
            foreach ($rows as $row) {
                if ($total <= $limit) {
                    break;
                }
                Media::remove($row->media_id);
                $db->Fetch("UPDATE `remote_media` SET `state` = 'purged', `media_id` = NULL WHERE `remote_media_num` = :a", array(":a" => $row->remote_media_num));
                $total -= (int)$row->bytes;
                $dropped++;
            }
        }
        return $dropped;
    }

    /**
     * @param int $viewer user_id
     * @return string SQL condition: the viewer may see the remote_posts row
     */
    public static function visible_where($viewer): string
    {
        return "(remote_posts.audience = 'public' OR remote_posts.actor_num IN (SELECT `actor_num` FROM `remote_follows` WHERE `user_id` = " . (int)$viewer . " AND `state` = 'accepted'))";
    }

    /**
     * @param int $viewer
     * @param string $scope following (people the viewer follows), all, or actor
     * @param int $limit
     * @param int|null $actor_num for the actor scope
     * @return array post rows with the author's handle parts, newest first
     */
    public static function latest($viewer, $scope, $limit = 30, $actor_num = null): array
    {
        if (empty($viewer) || !Federation::enabled()) {
            return array();
        }
        $where = self::visible_where($viewer);
        $params = null;
        if ($scope == "following") {
            $where .= " AND remote_posts.actor_num IN (SELECT `actor_num` FROM `remote_follows` WHERE `user_id` = " . (int)$viewer . " AND `state` = 'accepted')";
        } elseif ($scope == "actor") {
            $where .= " AND remote_posts.actor_num = :a";
            $params = array(":a" => (int)$actor_num);
        }
        $db = new Mysql();
        $rows = $db->Fetch("SELECT remote_posts.*, remote_actors.username, remote_actors.host, remote_actors.name AS actor_name, remote_actors.url AS actor_url
                            FROM `remote_posts` JOIN `remote_actors` ON remote_actors.actor_num = remote_posts.actor_num
                            WHERE " . $where . " ORDER BY remote_posts.published DESC LIMIT " . (int)$limit, $params);
        return $rows[0] != '00000' ? $rows : array();
    }

    /**
     * @param array $posts rows from latest()
     * @return array post_num => list of remote_media rows
     */
    public static function media_for($posts): array
    {
        $nums = array_map(function ($post) {
            return (int)$post->post_num;
        }, $posts);
        if (count($nums) == 0) {
            return array();
        }
        $db = new Mysql();
        $rows = $db->Fetch("SELECT * FROM `remote_media` WHERE `post_num` IN (" . implode(",", $nums) . ") ORDER BY `post_num`, `position`", null);
        $media = array();
        foreach ($rows[0] != '00000' ? $rows : array() as $row) {
            $media[(int)$row->post_num][] = $row;
        }
        return $media;
    }

    /**
     * @param int $post_num
     * @return array garments with their photo (media_id, state) and brand/category names, ours when matched
     */
    public static function garments($post_num): array
    {
        $db = new Mysql();
        $rows = $db->Fetch("SELECT remote_garments.*, remote_media.media_id, remote_media.state,
                                   COALESCE(brands.brand_name, remote_garments.brand_name) AS brand,
                                   COALESCE(categories.categories, remote_garments.category_name) AS category
                            FROM `remote_garments`
                            LEFT JOIN `remote_media` ON remote_media.remote_media_num = remote_garments.remote_media_num
                            LEFT JOIN `brands` ON brands.brand_id = remote_garments.brand_id
                            LEFT JOIN `categories` ON categories.category_id = remote_garments.category_id
                            WHERE remote_garments.post_num = :a ORDER BY remote_garments.position", array(":a" => (int)$post_num));
        return $rows[0] != '00000' ? $rows : array();
    }

    /**
     * @param int $viewer
     * @param int $post_num
     * @return object|null one post the viewer may see, in the shape latest() returns
     */
    public static function one($viewer, $post_num)
    {
        if (empty($viewer) || !Federation::enabled()) {
            return null;
        }
        $db = new Mysql();
        $rows = $db->Fetch("SELECT remote_posts.*, remote_actors.username, remote_actors.host, remote_actors.name AS actor_name, remote_actors.url AS actor_url
                            FROM `remote_posts` JOIN `remote_actors` ON remote_actors.actor_num = remote_posts.actor_num
                            WHERE remote_posts.post_num = :a AND " . self::visible_where($viewer), array(":a" => (int)$post_num));
        return $rows[0] != '00000' ? $rows[0] : null;
    }

    /**
     * @param object $post row from latest()
     * @param array $media its remote_media rows
     * @param bool $is_profile leave out the author (on their profile)
     * @return void
     */
    public static function render($post, $media, $is_profile = false): void
    {
        $handle = RemoteActor::display($post);
        $path = "/@" . rawurlencode((string)$post->username) . "@" . rawurlencode((string)$post->host);
        $content = sanitise_html((string)$post->content);
        $photos = array_values(array_filter($media, function ($item) {
            return ($item->kind ?? "photo") == "photo";
        }));
        $garments = self::garments((int)$post->post_num);
        $warning = trim(strip_tags((string)$post->summary));
        $hidden = $post->type != "Article" && ($warning !== "" || (int)$post->marked_sensitive === 1);
        ?>
        <article class="outfit remote">
            <div class="info">
                <a href="<?= escape((string)$post->url) ?>" class="permalink" rel="nofollow noopener" target="_blank"
                   title="Open on <?= escape(RemoteActor::blog_name((string)$post->host)) ?>">
                    <time datetime="<?= escape($post->published) ?>"><?= escape(date_display($post->published)) ?></time>
                </a>
                <?php if ($post->audience == VISIBILITY_FOLLOWERS): ?>
                    <span class="visibility">Followers</span>
                <?php endif; ?>
                <?php if (!$is_profile): ?>
                    <div class="profile">
                        <a href="<?= escape($path) ?>">
                            <img loading="lazy" src="<?= asset_url("/Assets/images/avi.jpg") ?>" alt="">
                            <span class="username"><?= escape($handle) ?></span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($hidden): ?>
            <details class="content-warning">
                <summary><?= escape($warning !== "" ? $warning : "Sensitive") ?></summary>
            <?php endif; ?>
            <?php if (count($photos) > 0): ?>
                <div class="album">
                    <?php foreach ($photos as $item): ?>
                        <figure>
                            <?php if ($item->state == "stored" && !empty($item->media_id)): ?>
                                <img loading="lazy" src="<?= escape(media_url($item->media_id, "display")) ?>"
                                     alt="<?= escape((string)$item->alt_text) ?>">
                            <?php elseif ($item->state == "pending"): ?>
                                <p class="media-placeholder">Photo on its way from <?= escape($post->host) ?></p>
                            <?php else: ?>
                                <p class="media-placeholder">
                                    <a href="<?= escape($item->remote_url) ?>" rel="nofollow noopener" target="_blank">See the photo on <?= escape($post->host) ?></a>
                                </p>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="description">
                <?php if ($post->type == "Article"): ?>
                    <h3><?= escape((string)$post->name) ?></h3>
                    <?php if (trim((string)$post->summary) !== ""): ?>
                        <p><?= escape(strip_tags((string)$post->summary)) ?></p>
                    <?php endif; ?>
                    <p><a href="<?= escape((string)$post->url) ?>" rel="nofollow noopener" target="_blank">Read it on <?= escape($post->host) ?></a></p>
                <?php else: ?>
                    <div class="remote-content"><?= $content ?></div>
                <?php endif; ?>
            </div>
            <?php if ($hidden): ?>
            </details>
            <?php endif; ?>
            <?php if (count($garments) > 0): ?>
                <ul class="garments">
                    <?php foreach ($garments as $garment): ?>
                        <li>
                            <a href="<?= escape((string)($garment->url ?: $post->url)) ?>" rel="nofollow noopener" target="_blank"
                               title="<?= escape(trim($garment->name . " " . ($garment->brand ? "by " . $garment->brand : ""))) ?>">
                                <?php if ($garment->state == "stored" && !empty($garment->media_id)): ?>
                                    <img loading="lazy" src="<?= escape(media_url($garment->media_id, "thumb")) ?>" alt="<?= escape($garment->name) ?>">
                                <?php endif; ?>
                                <span><?= escape($garment->name) ?></span>
                                <?php if ($garment->brand || $garment->category): ?>
                                    <small><?= escape(implode(" · ", array_filter(array($garment->brand, $garment->category)))) ?></small>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php Like::render_bar("remote", (int)$post->post_num, (string)(int)$post->post_num, "/remote/" . (int)$post->post_num); ?>
        </article>
        <?php
    }
}
