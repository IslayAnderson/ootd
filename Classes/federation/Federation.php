<?php

/**
 * ActivityPub: what this server says about its people and posts, and sending it to followers on
 * other servers. Outfits (Note) and blog posts (Article) federate when they are Public or
 * Followers; Members and Only me never leave this server. Garments, wears and insights never
 * federate (DECISIONS R2).
 */
class Federation
{
    const PUBLIC_AUDIENCE = "https://www.w3.org/ns/activitystreams#Public";
    const PAGE_SIZE = 20;
    /** OOTD's own vocabulary (garments, brands, categories), the same on every OOTD server; described at /ns */
    const NS = "https://github.com/IslayAnderson/ootd/ns#";
    const WARDROBE_SIZE = 100;

    /**
     * @return bool is federation turned on in /admin
     */
    public static function enabled(): bool
    {
        return setting_get("federation", 1) == 1;
    }

    public static function context(): array
    {
        return array(
            "https://www.w3.org/ns/activitystreams",
            "https://w3id.org/security/v1",
            array(
                "manuallyApprovesFollowers" => "as:manuallyApprovesFollowers",
                "sensitive" => "as:sensitive",
                "Hashtag" => "as:Hashtag",
                "toot" => "http://joinmastodon.org/ns#",
                "discoverable" => "toot:discoverable",
                "ootd" => self::NS
            )
        );
    }

    public static function actor_url($username): string
    {
        return absolute_url("/user/" . rawurlencode($username));
    }

    public static function instance_actor_url(): string
    {
        return absolute_url("/actor");
    }

    /**
     * @return string host (and port, when it isn't the default) used in handles: @name@host
     */
    public static function domain(): string
    {
        $parts = parse_url(app_url());
        return $parts['host'] . (isset($parts['port']) ? ":" . $parts['port'] : "");
    }

    /**
     * @param string $visibility
     * @return bool does a post with this visibility go to other servers
     */
    public static function federates($visibility): bool
    {
        return in_array($visibility, array(VISIBILITY_PUBLIC, VISIBILITY_FOLLOWERS), true);
    }

    /**
     * @param string $username
     * @return object|null users row (user_id, username, media_id, approve_followers, timestamp)
     */
    public static function local_user($username)
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `user_id`, `username`, `media_id`, `approve_followers` FROM `users` WHERE `username` = :a", array(":a" => (string)$username));
        return $row[0] != '00000' ? $row[0] : null;
    }

    /**
     * @param object $user row from local_user()
     * @return array Person
     */
    public static function person($user): array
    {
        list($public) = ActorKey::get((int)$user->user_id);
        $id = self::actor_url($user->username);
        $person = array(
            "@context" => self::context(),
            "id" => $id,
            "type" => "Person",
            "preferredUsername" => $user->username,
            //real names stay inside the instance (D3)
            "name" => $user->username,
            "summary" => "",
            "url" => $id,
            "inbox" => $id . "/inbox",
            "outbox" => $id . "/outbox",
            "followers" => $id . "/followers",
            "following" => $id . "/following",
            "endpoints" => array("sharedInbox" => absolute_url("/inbox")),
            "manuallyApprovesFollowers" => (int)$user->approve_followers === 1,
            "discoverable" => false,
            "ootd:wardrobe" => $id . "/wardrobe",
            "publicKey" => array("id" => $id . "#main-key", "owner" => $id, "publicKeyPem" => $public)
        );
        if (!empty($user->media_id) && ($media = Media::get($user->media_id))) {
            $person['icon'] = array("type" => "Image", "mediaType" => $media->mime, "url" => media_absolute_url($user->media_id, "thumb"));
        }
        return $person;
    }

    /**
     * @return array the instance actor, which signs requests made on behalf of the server itself
     */
    public static function instance_actor(): array
    {
        list($public) = ActorKey::get(null);
        $id = self::instance_actor_url();
        return array(
            "@context" => self::context(),
            "id" => $id,
            "type" => "Application",
            "preferredUsername" => self::domain(),
            "name" => instance_name(),
            "url" => absolute_url("/about"),
            "inbox" => $id . "/inbox",
            "outbox" => $id . "/outbox",
            "manuallyApprovesFollowers" => true,
            "publicKey" => array("id" => $id . "#main-key", "owner" => $id, "publicKeyPem" => $public)
        );
    }

    /**
     * @param string $username
     * @param string $visibility public or followers
     * @return array [to, cc]
     */
    public static function audience($username, $visibility): array
    {
        $followers = self::actor_url($username) . "/followers";
        return $visibility == VISIBILITY_PUBLIC ? array(array(self::PUBLIC_AUDIENCE), array($followers)) : array(array($followers), array());
    }

    /**
     * @param string $outfit_id
     * @param bool $any_visibility include posts that don't federate (for building Delete)
     * @return array|null Note, null when it doesn't exist or doesn't federate
     */
    public static function outfit_note($outfit_id, $any_visibility = false): ?array
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT outfits.outfit_id, outfits.description, outfits.tags, outfits.visibility, outfits.media_id, outfits.timestamp, outfits.garments,
                                  outfits.updated_at, users.username, media.mime, media.width, media.height, media.alt_text
                           FROM `outfits` JOIN `users` ON users.user_id = outfits.user
                           LEFT JOIN `media` ON media.media_id = outfits.media_id
                           WHERE outfits.outfit_id = :a AND outfits.deleted_at IS NULL", array(":a" => (string)$outfit_id));
        if ($row[0] == '00000' || (!$any_visibility && !self::federates($row[0]->visibility))) {
            return null;
        }
        $outfit = $row[0];
        list($to, $cc) = self::audience($outfit->username, $outfit->visibility);
        $tags = tags_split($outfit->tags);
        $content = "";
        if (trim((string)$outfit->description) !== "") {
            $content .= "<p>" . nl2br(escape($outfit->description), false) . "</p>";
        }
        //garments that may go wherever the outfit goes: public ones, or followers-only ones on a followers-only outfit
        $garments = array();
        foreach (self::garment_rows(json_decode((string)$outfit->garments) ?: array()) as $garment) {
            if ($garment->visibility == VISIBILITY_PUBLIC || ($garment->visibility == VISIBILITY_FOLLOWERS && $outfit->visibility == VISIBILITY_FOLLOWERS)) {
                $garments[] = $garment;
            }
        }
        if (count($garments) > 0) {
            //for Mastodon and other apps that don't know garments
            $content .= "<p>Wearing: " . escape(implode(", ", array_map(function ($garment) {
                    return $garment->garment_name . (!empty($garment->brand_name) ? " (" . $garment->brand_name . ")" : "");
                }, $garments))) . "</p>";
        }
        if (count($tags) > 0) {
            $content .= "<p>" . escape(tags_display($outfit->tags)) . "</p>";
        }
        $note = array(
            "id" => absolute_url("/outfit/" . $outfit->outfit_id),
            "type" => "Note",
            "attributedTo" => self::actor_url($outfit->username),
            "url" => absolute_url("/outfit/" . $outfit->outfit_id),
            "published" => self::time($outfit->timestamp),
            "to" => $to,
            "cc" => $cc,
            "sensitive" => false,
            "content" => $content,
            "tag" => array_map(function ($tag) {
                return array("type" => "Hashtag", "name" => "#" . $tag);
            }, array_values($tags)),
            "attachment" => array()
        );
        if (!empty($outfit->updated_at)) {
            $note['updated'] = self::time($outfit->updated_at);
        }
        if (count($garments) > 0) {
            $note['ootd:garments'] = array_map(function ($garment) use ($outfit) {
                return self::garment_object($garment, $outfit->visibility != VISIBILITY_PUBLIC);
            }, $garments);
        }
        if (!empty($outfit->media_id) && !empty($outfit->mime)) {
            $note['attachment'][] = array(
                "type" => "Image",
                "mediaType" => $outfit->mime,
                "url" => media_absolute_url($outfit->media_id, "display", $outfit->visibility != VISIBILITY_PUBLIC),
                "name" => (string)$outfit->alt_text,
                "width" => (int)$outfit->width,
                "height" => (int)$outfit->height
            );
        }
        return $note;
    }

    /**
     * @param string $blog_id
     * @param bool $any_visibility
     * @return array|null Article
     */
    public static function blog_article($blog_id, $any_visibility = false): ?array
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT blogs.blog_id, blogs.user, blogs.outfits, blogs.title, blogs.excerpt, blogs.content, blogs.tags, blogs.visibility, blogs.timestamp, blogs.updated_at, users.username
                           FROM `blogs` JOIN `users` ON users.user_id = blogs.user
                           WHERE blogs.blog_id = :a AND blogs.deleted_at IS NULL", array(":a" => (string)$blog_id));
        if ($row[0] == '00000' || (!$any_visibility && !self::federates($row[0]->visibility))) {
            return null;
        }
        $blog = $row[0];
        list($to, $cc) = self::audience($blog->username, $blog->visibility);
        $url = absolute_url("/user/" . rawurlencode($blog->username) . "/blog/" . $blog->blog_id);
        $article = array(
            "id" => $url,
            "type" => "Article",
            "attributedTo" => self::actor_url($blog->username),
            "url" => $url,
            "name" => (string)$blog->title,
            "summary" => (string)$blog->excerpt,
            "published" => self::time($blog->timestamp),
            "to" => $to,
            "cc" => $cc,
            "content" => sanitise_html($blog->content),
            "tag" => array_map(function ($tag) {
                return array("type" => "Hashtag", "name" => "#" . $tag);
            }, array_values(tags_split($blog->tags)))
        );
        if (!empty($blog->updated_at)) {
            $article['updated'] = self::time($blog->updated_at);
        }
        //linked outfits that federate at least as widely as the post
        $outfits = array();
        foreach (json_decode((string)$blog->outfits) ?: array() as $outfit_num) {
            $row = $db->Fetch("SELECT `outfit_id`, `visibility` FROM `outfits` WHERE `outfit_num` = :a AND `user` = :b AND `deleted_at` IS NULL",
                array(":a" => (int)$outfit_num, ":b" => (int)$blog->user));
            if ($row[0] != '00000' && ($row[0]->visibility == VISIBILITY_PUBLIC || ($row[0]->visibility == VISIBILITY_FOLLOWERS && $blog->visibility == VISIBILITY_FOLLOWERS))) {
                $outfits[] = absolute_url("/outfit/" . $row[0]->outfit_id);
            }
        }
        if (count($outfits) > 0) {
            $article['ootd:outfits'] = $outfits;
        }
        return $article;
    }

    /**
     * @param array $garment_nums
     * @return array garment rows (with brand_name, category_name, owner username, image details), in the given order
     */
    public static function garment_rows($garment_nums): array
    {
        $nums = array_values(array_filter(array_map("intval", (array)$garment_nums)));
        if (count($nums) == 0) {
            return array();
        }
        $db = new Mysql();
        $rows = $db->Fetch("SELECT garments.*, brands.brand_name, categories.categories AS category_name, users.username,
                                   media.mime, media.alt_text, media.width, media.height
                            FROM `garments` JOIN `users` ON users.user_id = garments.user_id
                            LEFT JOIN `brands` ON brands.brand_id = garments.brand
                            LEFT JOIN `categories` ON categories.category_id = garments.category
                            LEFT JOIN `media` ON media.media_id = garments.media_id
                            WHERE garments.garment_num IN (" . implode(",", $nums) . ") AND garments.deleted_at IS NULL", null);
        $by_num = array();
        foreach ($rows[0] != '00000' ? $rows : array() as $row) {
            $by_num[(int)$row->garment_num] = $row;
        }
        $ordered = array();
        foreach ($nums as $num) {
            if (isset($by_num[$num])) {
                $ordered[] = $by_num[$num];
            }
        }
        return $ordered;
    }

    /**
     * @param object $garment row from garment_rows()
     * @param bool $keyed photo link carries a key (the garment or its outfit isn't public)
     * @return array ootd:Garment
     */
    public static function garment_object($garment, $keyed = false): array
    {
        $url = absolute_url("/garment/" . $garment->garment_id);
        $object = array(
            "id" => $url,
            "type" => "ootd:Garment",
            "attributedTo" => self::actor_url($garment->username),
            "name" => (string)$garment->garment_name,
            "url" => $url
        );
        if (trim((string)$garment->description) !== "") {
            $object['summary'] = (string)$garment->description;
        }
        if (!empty($garment->brand_name)) {
            $object['ootd:brand'] = (string)$garment->brand_name;
        }
        if (!empty($garment->category_name)) {
            $object['ootd:category'] = (string)$garment->category_name;
        }
        if (!empty($garment->media_id) && !empty($garment->mime)) {
            $object['image'] = array(
                "type" => "Image",
                "mediaType" => $garment->mime,
                "url" => media_absolute_url($garment->media_id, "thumb", $keyed || $garment->visibility != VISIBILITY_PUBLIC),
                "name" => (string)$garment->alt_text
            );
        }
        return $object;
    }

    /**
     * @param string $garment_id
     * @return array|null a public garment, with @context, for /garment/<id>
     */
    public static function garment($garment_id): ?array
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `garment_num` FROM `garments` WHERE `garment_id` = :a AND `deleted_at` IS NULL AND `visibility` = 'public'", array(":a" => (string)$garment_id));
        if ($row[0] == '00000') {
            return null;
        }
        $garment = self::garment_rows(array($row[0]->garment_num))[0] ?? null;
        return $garment ? array_merge(array("@context" => self::context()), self::garment_object($garment)) : null;
    }

    /**
     * @param object $user row from local_user()
     * @return array the public wardrobe as an OrderedCollection of ootd:Garment
     */
    public static function wardrobe($user): array
    {
        $db = new Mysql();
        $rows = $db->Fetch("SELECT `garment_num` FROM `garments` WHERE `user_id` = :a AND `deleted_at` IS NULL AND `visibility` = 'public'
                            ORDER BY `timestamp` DESC LIMIT " . self::WARDROBE_SIZE, array(":a" => (int)$user->user_id));
        $nums = array_map(function ($row) {
            return (int)$row->garment_num;
        }, $rows[0] != '00000' ? $rows : array());
        $items = array_map(function ($garment) {
            return self::garment_object($garment);
        }, self::garment_rows($nums));
        return array(
            "@context" => self::context(),
            "id" => self::actor_url($user->username) . "/wardrobe",
            "type" => "OrderedCollection",
            "attributedTo" => self::actor_url($user->username),
            "totalItems" => count($items),
            "orderedItems" => $items
        );
    }

    /**
     * @return array the JSON-LD context for OOTD's vocabulary, served at /ns
     */
    public static function vocabulary(): array
    {
        return array("@context" => array(
            "ootd" => self::NS,
            "Garment" => "ootd:Garment",
            "brand" => "ootd:brand",
            "category" => "ootd:category",
            "garments" => array("@id" => "ootd:garments", "@container" => "@list"),
            "outfits" => array("@id" => "ootd:outfits", "@type" => "@id"),
            "wardrobe" => array("@id" => "ootd:wardrobe", "@type" => "@id")
        ));
    }

    /**
     * @param string $type Create, Update or Delete
     * @param array $object
     * @return array the activity
     */
    public static function wrap($type, $object): array
    {
        $activity = array(
            "@context" => self::context(),
            "id" => $object['id'] . "#" . strtolower($type) . "-" . ($type == "Create" ? "1" : time()),
            "type" => $type,
            "actor" => $object['attributedTo'],
            "published" => $object['updated'] ?? $object['published'] ?? self::time(null),
            "to" => $object['to'],
            "cc" => $object['cc'],
            "object" => $object
        );
        unset($activity['object']['@context']);
        if ($type == "Delete") {
            $activity['object'] = array("id" => $object['id'], "type" => "Tombstone");
        }
        return $activity;
    }

    public static function time($timestamp): string
    {
        return gmdate("Y-m-d\\TH:i:s\\Z", $timestamp ? strtotime($timestamp) : time());
    }

    /**
     * Called whenever an outfit or blog post is created, edited or deleted; works out what to
     * send in a background job, so posting never waits on other servers.
     *
     * @param string $kind outfit or blog
     * @param string $id outfit_id or blog_id
     * @param string $action create, update or delete
     * @param string|null $was the visibility before an update or delete
     * @return void
     */
    public static function changed($kind, $id, $action, $was = null): void
    {
        if (!self::enabled()) {
            return;
        }
        Jobs::dispatch("ap_publish", array("kind" => $kind, "id" => $id, "action" => $action, "was" => $was));
    }

    /**
     * Job: build the activity for a change and queue it for every follower's server
     *
     * @param array $payload from changed()
     * @return void
     */
    public static function publish($payload): void
    {
        $kind = $payload['kind'];
        $id = (string)$payload['id'];
        $was_federated = self::federates((string)($payload['was'] ?? ""));

        $build = function ($any) use ($kind, $id) {
            return $kind == "blog" ? self::blog_article($id, $any) : self::outfit_note($id, $any);
        };

        if ($payload['action'] == "delete") {
            if (!$was_federated) {
                return;
            }
            //the row is soft-deleted, so rebuild the bits a Delete needs from what's left
            $object = self::deleted_stub($kind, $id, $payload['was']);
            if ($object) {
                self::send_to_followers($object['attributedTo'], self::wrap("Delete", $object));
            }
            return;
        }

        $object = $build(false);
        if ($object === null) {
            //stopped federating (made Members or Only me): take it back
            if ($payload['action'] == "update" && $was_federated && ($stub = $build(true))) {
                list($stub['to'], $stub['cc']) = self::audience(self::username_from_actor($stub['attributedTo']), $payload['was']);
                self::send_to_followers($stub['attributedTo'], self::wrap("Delete", $stub));
            }
            return;
        }
        $type = ($payload['action'] == "create" || !$was_federated) ? "Create" : "Update";
        self::send_to_followers($object['attributedTo'], self::wrap($type, $object));
    }

    private static function deleted_stub($kind, $id, $was): ?array
    {
        $db = new Mysql();
        $sql = $kind == "blog"
            ? "SELECT users.username FROM `blogs` JOIN `users` ON users.user_id = blogs.user WHERE blogs.blog_id = :a"
            : "SELECT users.username FROM `outfits` JOIN `users` ON users.user_id = outfits.user WHERE outfits.outfit_id = :a";
        $row = $db->Fetch($sql, array(":a" => $id));
        if ($row[0] == '00000') {
            return null;
        }
        $username = $row[0]->username;
        list($to, $cc) = self::audience($username, $was);
        $url = $kind == "blog" ? absolute_url("/user/" . rawurlencode($username) . "/blog/" . $id) : absolute_url("/outfit/" . $id);
        return array("id" => $url, "attributedTo" => self::actor_url($username), "to" => $to, "cc" => $cc);
    }

    private static function username_from_actor($actor_url): string
    {
        return rawurldecode(basename(parse_url($actor_url, PHP_URL_PATH)));
    }

    /**
     * @param string $actor_url local actor
     * @param array $activity
     * @return void
     */
    public static function send_to_followers($actor_url, $activity): void
    {
        $user = self::local_user(self::username_from_actor($actor_url));
        if (!$user) {
            return;
        }
        $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach (self::follower_inboxes((int)$user->user_id) as $inbox) {
            Jobs::dispatch("ap_deliver", array("inbox" => $inbox, "user_id" => (int)$user->user_id, "body" => $body));
        }
    }

    /**
     * @param int $user_id
     * @return array the inboxes of a local user's followers elsewhere (shared inboxes once each)
     */
    public static function follower_inboxes($user_id): array
    {
        $db = new Mysql();
        $rows = $db->Fetch("SELECT DISTINCT COALESCE(remote_actors.shared_inbox, remote_actors.inbox) AS inbox
                            FROM `remote_followers` JOIN `remote_actors` ON remote_actors.actor_num = remote_followers.actor_num
                            WHERE remote_followers.user_id = :a AND remote_followers.state = 'accepted'", array(":a" => (int)$user_id));
        $inboxes = array();
        foreach ($rows[0] != '00000' ? $rows : array() as $row) {
            if (!empty($row->inbox)) {
                $inboxes[] = $row->inbox;
            }
        }
        return $inboxes;
    }

    /**
     * @param int|null $user_id who signs it, null for the instance actor
     * @param string $inbox
     * @param array $activity
     * @return void
     */
    public static function send($user_id, $inbox, $activity): void
    {
        Jobs::dispatch("ap_deliver", array(
            "inbox" => $inbox,
            "user_id" => $user_id,
            "body" => json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));
    }

    /**
     * Job: POST one signed activity to one inbox. Server errors and timeouts are retried by the job
     * runner; a server that refuses it (4xx) isn't.
     *
     * @param array $payload inbox, user_id, body
     * @return void
     * @throws Exception
     */
    public static function deliver($payload): void
    {
        $user_id = $payload['user_id'] ?? null;
        if ($user_id === null) {
            $key_id = self::instance_actor_url() . "#main-key";
        } else {
            $db = new Mysql();
            $row = $db->Fetch("SELECT `username` FROM `users` WHERE `user_id` = :a", array(":a" => (int)$user_id));
            if ($row[0] == '00000') {
                return;
            }
            $key_id = self::actor_url($row[0]->username) . "#main-key";
        }
        list($public, $private) = ActorKey::get($user_id === null ? null : (int)$user_id);
        $headers = HttpSignature::sign("POST", $payload['inbox'], $payload['body'], $key_id, $private);
        $headers['Accept'] = "application/activity+json";
        list($status, $response) = SafeHttp::post($payload['inbox'], $headers, $payload['body']);
        if ($status >= 200 && $status < 300) {
            return;
        }
        if ($status == 429 || $status >= 500) {
            throw new Exception("Inbox answered " . $status);
        }
        error_log("OOTD: " . $payload['inbox'] . " refused an activity (" . $status . "): " . mb_substr($response, 0, 200));
    }
}
