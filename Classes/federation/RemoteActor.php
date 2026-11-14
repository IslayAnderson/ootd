<?php

/**
 * People on other servers. Their actor documents (inbox, public key, name) are fetched once and
 * cached in remote_actors for a day; a signature that fails with the cached key refetches it.
 */
class RemoteActor
{
    const CACHE = 86400;

    /**
     * @param string $ap_id actor id (URL)
     * @param bool $refresh fetch even when cached
     * @return object|null remote_actors row
     */
    public static function get($ap_id, $refresh = false)
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT * FROM `remote_actors` WHERE `ap_id` = :a", array(":a" => (string)$ap_id));
        $cached = $row[0] != '00000' ? $row[0] : null;
        if ($cached && !$refresh && strtotime((string)$cached->fetched_at) > time() - self::CACHE) {
            return $cached;
        }
        try {
            $document = self::fetch($ap_id);
        } catch (Exception $e) {
            return $cached;
        }
        if (($document['id'] ?? null) !== $ap_id || !isset($document['inbox'])) {
            return $cached;
        }
        self::store($document);
        self::note_blog($ap_id);
        $row = $db->Fetch("SELECT * FROM `remote_actors` WHERE `ap_id` = :a", array(":a" => (string)$ap_id));
        return $row[0] != '00000' ? $row[0] : null;
    }

    /**
     * @param string $url
     * @return array the JSON document
     * @throws Exception
     */
    public static function fetch($url): array
    {
        list($public, $private) = ActorKey::get(null);
        $headers = HttpSignature::sign("GET", $url, null, Federation::instance_actor_url() . "#main-key", $private);
        $headers['Accept'] = 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        list($status, $body) = SafeHttp::get($url, $headers);
        if ($status != 200) {
            throw new Exception("Fetch failed (" . $status . ")");
        }
        $document = json_decode($body, true);
        if (!is_array($document)) {
            throw new Exception("Not JSON");
        }
        return $document;
    }

    /**
     * @param array $document actor JSON
     * @return void
     */
    private static function store($document): void
    {
        $host = parse_url($document['id'], PHP_URL_HOST);
        $key = $document['publicKey'] ?? array();
        //a key must belong to this actor
        if (($key['owner'] ?? $document['id']) !== $document['id']) {
            $key = array();
        }
        $params = array(
            ":a" => $document['id'],
            ":b" => mb_substr((string)($document['preferredUsername'] ?? ""), 0, 128),
            ":c" => $host,
            ":d" => mb_substr(strip_tags((string)($document['name'] ?? "")), 0, 255),
            ":e" => is_string($document['url'] ?? null) ? mb_substr($document['url'], 0, 512) : null,
            ":f" => mb_substr((string)$document['inbox'], 0, 512),
            ":g" => is_string($document['endpoints']['sharedInbox'] ?? null) ? mb_substr($document['endpoints']['sharedInbox'], 0, 512) : null,
            ":h" => $key['id'] ?? null,
            ":i" => $key['publicKeyPem'] ?? null,
            ":j" => is_string($document['outbox'] ?? null) ? mb_substr($document['outbox'], 0, 512) : null,
            ":k" => is_string($document['followers'] ?? null) ? mb_substr($document['followers'], 0, 512) : null
        );
        $db = new Mysql();
        $db->Fetch("INSERT INTO `remote_actors` (`ap_id`, `username`, `host`, `name`, `url`, `inbox`, `shared_inbox`, `key_id`, `public_key`, `outbox`, `followers`, `fetched_at`)
                    VALUES (:a, :b, :c, :d, :e, :f, :g, :h, :i, :j, :k, NOW())
                    ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `host` = VALUES(`host`), `name` = VALUES(`name`), `url` = VALUES(`url`),
                      `inbox` = VALUES(`inbox`), `shared_inbox` = VALUES(`shared_inbox`), `key_id` = VALUES(`key_id`),
                      `public_key` = VALUES(`public_key`), `outbox` = VALUES(`outbox`), `followers` = VALUES(`followers`), `fetched_at` = NOW()", $params);
        self::check_account_host($document);
    }

    /**
     * For HttpSignature::verify: the public key behind a keyId
     *
     * @param string $key_id
     * @param bool $refresh
     * @return string|null PEM
     */
    public static function key_for($key_id, $refresh = false): ?string
    {
        $db = new Mysql();
        if (!$refresh) {
            $row = $db->Fetch("SELECT `public_key` FROM `remote_actors` WHERE `key_id` = :a", array(":a" => (string)$key_id));
            if ($row[0] != '00000' && !empty($row[0]->public_key)) {
                return $row[0]->public_key;
            }
        }
        $actor = self::get(self::owner_of($key_id), true);
        return $actor && $actor->key_id === $key_id ? $actor->public_key : null;
    }

    /**
     * @param string $key_id
     * @return string the actor id a keyId belongs to
     */
    public static function owner_of($key_id): string
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `ap_id` FROM `remote_actors` WHERE `key_id` = :a", array(":a" => (string)$key_id));
        if ($row[0] != '00000') {
            return $row[0]->ap_id;
        }
        //Mastodon's keys are the actor's URL plus #main-key
        return explode("#", (string)$key_id)[0];
    }

    /**
     * @param string $handle name@host, with or without the leading @
     * @return array [name, host] or null when it isn't a handle
     */
    public static function parse_handle($handle): ?array
    {
        if (!preg_match('/^@?([a-z0-9_.\-]{1,128})@([a-z0-9.\-]{1,253}(?::[0-9]{1,5})?)$/i', trim((string)$handle), $m)) {
            return null;
        }
        return array($m[1], strtolower($m[2]));
    }

    /**
     * Find someone on another server by handle (WebFinger) or by the address of their profile
     *
     * @param string $query @name@host or https://host/...
     * @return object|null remote_actors row
     */
    public static function find($query)
    {
        $query = trim((string)$query);
        $handle = self::parse_handle($query);
        if ($handle) {
            list($name, $host) = $handle;
            if (Federation::is_own_domain($host)) {
                return null;
            }
            $db = new Mysql();
            $row = $db->Fetch("SELECT `ap_id` FROM `remote_actors` WHERE `username` = :a AND (`host` = :b OR `account_host` = :c) ORDER BY `account_host` = :c DESC LIMIT 1",
                array(":a" => $name, ":b" => explode(":", $host)[0], ":c" => $host));
            if ($row[0] != '00000') {
                return self::get($row[0]->ap_id);
            }
            $actor_id = self::webfinger($name, $host);
            return $actor_id ? self::get($actor_id) : null;
        }
        if (preg_match('#^https?://#i', $query)) {
            //a profile page (https://host/@name) usually answers its actor document when asked for JSON
            try {
                $document = self::fetch($query);
            } catch (Exception $e) {
                return null;
            }
            $id = Inbox::id($document['id'] ?? null);
            if (!$id || !in_array($document['type'] ?? "", array("Person", "Service", "Organization", "Group", "Application"), true)) {
                return null;
            }
            return self::get($id);
        }
        return null;
    }

    /**
     * @param string $name
     * @param string $host
     * @return string|null the actor id the handle points at
     */
    public static function webfinger($name, $host): ?string
    {
        $document = self::webfinger_document($name, $host);
        foreach ($document['links'] ?? array() as $link) {
            if (($link['rel'] ?? "") == "self" && is_string($link['href'] ?? null)
                && preg_match('#^application/(activity\+json|ld\+json)#', (string)($link['type'] ?? ""))) {
                return $link['href'];
            }
        }
        return null;
    }

    /**
     * The domain in someone's address when it isn't their server's: their server's WebFinger names
     * name@other.domain, and other.domain's WebFinger points back at the same actor. Checked weekly.
     *
     * @param array $document actor JSON, already stored
     * @return void
     */
    private static function check_account_host($document): void
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `account_checked_at` FROM `remote_actors` WHERE `ap_id` = :a AND `account_checked_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            array(":a" => $document['id']));
        if ($row[0] != '00000') {
            return;
        }
        $name = (string)($document['preferredUsername'] ?? "");
        $parts = parse_url($document['id']);
        $host = strtolower($parts['host'] ?? "") . (isset($parts['port']) ? ":" . $parts['port'] : "");
        $account = null;
        if ($name !== "" && $host !== "") {
            $first = self::webfinger_document($name, $host);
            if (preg_match('/^acct:([^@]+)@([a-z0-9.\-]{1,253}(?::[0-9]{1,5})?)$/i', (string)($first['subject'] ?? ""), $m)
                && strtolower($m[2]) !== $host && !Federation::is_own_domain($m[2])) {
                $back = self::webfinger_document($m[1], strtolower($m[2]));
                foreach ($back['links'] ?? array() as $link) {
                    if (($link['rel'] ?? "") == "self" && ($link['href'] ?? null) === $document['id']) {
                        $account = mb_substr(strtolower($m[2]), 0, 260);
                    }
                }
            }
        }
        $db->Fetch("UPDATE `remote_actors` SET `account_host` = :b, `account_checked_at` = NOW() WHERE `ap_id` = :a",
            array(":a" => $document['id'], ":b" => $account));
    }

    /**
     * @param string $name
     * @param string $host
     * @return array the WebFinger answer for name@host, empty when there's none
     */
    private static function webfinger_document($name, $host): array
    {
        $scheme = !empty($_ENV['AP_ALLOW_HTTP']) && preg_match('/^(127\.|localhost)/', $host) ? "http" : "https";
        $url = $scheme . "://" . $host . "/.well-known/webfinger?resource=" . rawurlencode("acct:" . $name . "@" . $host);
        try {
            list($status, $body) = SafeHttp::get($url, array("Accept" => "application/jrd+json, application/json"));
        } catch (Exception $e) {
            return array();
        }
        $document = json_decode((string)$body, true);
        return $status == 200 && is_array($document) ? $document : array();
    }

    /**
     * @param object $actor remote_actors row
     * @return string the address of their profile here, /@name@host
     */
    public static function local_path($actor): string
    {
        return "/@" . rawurlencode($actor->username ?: "unknown") . "@" . rawurlencode($actor->host);
    }

    /**
     * The handle as other servers write it. Stored in notifications and used in the protocol;
     * people here see display() instead.
     *
     * @param object $actor remote_actors row
     * @return string @name@host
     */
    public static function handle($actor): string
    {
        return "@" . ($actor->username ?: "unknown") . "@" . $actor->host;
    }

    /**
     * @param object $actor remote_actors row
     * @return string name@host, their address without Mastodon's leading @
     */
    public static function address($actor): string
    {
        return ($actor->username ?: "unknown") . "@" . (!empty($actor->account_host) ? $actor->account_host : $actor->host);
    }

    /**
     * How someone elsewhere is shown here: "mia from Mia's Closet"
     *
     * @param object $actor remote_actors row (or anything with username and host)
     * @return string
     */
    public static function display($actor): string
    {
        return ($actor->username ?: "unknown") . " from " . self::blog_name((string)$actor->host);
    }

    /**
     * @param string $handle @name@host, as stored in notifications
     * @return string "name from Blog", or the text as it was when it isn't a handle
     */
    public static function display_handle($handle): string
    {
        $parsed = self::parse_handle($handle);
        return $parsed ? $parsed[0] . " from " . self::blog_name(explode(":", $parsed[1])[0]) : (string)$handle;
    }

    //names servers get by default, which say nothing about which blog it is
    const GENERIC_NAMES = array("ootd", "treat blog", "treat", "mastodon", "misskey", "pleroma", "akkoma", "gotosocial", "pixelfed",
        "friendica", "lemmy", "sharkey", "firefish", "iceshrimp", "hubzilla", "wordpress", "writefreely");

    private static $blogs = null;

    /**
     * The name another blog gives itself, or its address when it has none, still has the default
     * name, or shares its name with another blog (or with this one)
     *
     * @param string $host
     * @return string
     */
    public static function blog_name($host): string
    {
        $host = strtolower((string)$host);
        if (self::$blogs === null) {
            self::$blogs = array();
            $db = new Mysql();
            $rows = $db->Fetch("SELECT `host`, `name` FROM `remote_blogs` WHERE `name` IS NOT NULL AND `name` <> ''", null);
            foreach ($rows[0] != '00000' ? $rows : array() as $row) {
                self::$blogs[strtolower($row->host)] = $row->name;
            }
        }
        $name = self::$blogs[$host] ?? "";
        if ($name === "" || in_array(strtolower($name), self::GENERIC_NAMES, true)
            || strcasecmp($name, instance_name()) === 0) {
            return $host;
        }
        $same = 0;
        foreach (self::$blogs as $other) {
            if (strcasecmp($other, $name) === 0) {
                $same++;
            }
        }
        return $same > 1 ? $host : $name;
    }

    /**
     * Remember the blog an actor lives on and look its name up in the background (again after a week)
     *
     * @param string $ap_id
     * @return void
     */
    public static function note_blog($ap_id): void
    {
        $parts = parse_url((string)$ap_id);
        if (empty($parts['host']) || empty($parts['scheme'])) {
            return;
        }
        $host = strtolower($parts['host']);
        $base = strtolower($parts['scheme']) . "://" . $host . (isset($parts['port']) ? ":" . (int)$parts['port'] : "");
        $db = new Mysql();
        $row = $db->Fetch("SELECT `fetched_at` FROM `remote_blogs` WHERE `host` = :a", array(":a" => $host));
        if ($row[0] == '00000') {
            $db->Fetch("INSERT IGNORE INTO `remote_blogs` (`host`, `base_url`, `fetched_at`) VALUES (:a, :b, NOW())", array(":a" => $host, ":b" => $base));
        } elseif (strtotime((string)$row[0]->fetched_at) > time() - 7 * 86400) {
            return;
        } else {
            $db->Fetch("UPDATE `remote_blogs` SET `fetched_at` = NOW() WHERE `host` = :a", array(":a" => $host));
        }
        Jobs::dispatch("remote_blog", array("host" => $host));
    }

    /**
     * Job: read another blog's name from its nodeinfo (metadata.nodeName, which Mastodon and OOTD publish)
     *
     * @param string $host
     * @return void
     */
    public static function fetch_blog_name($host): void
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `base_url` FROM `remote_blogs` WHERE `host` = :a", array(":a" => (string)$host));
        if ($row[0] == '00000') {
            return;
        }
        $base = $row[0]->base_url;
        $name = null;
        try {
            list($status, $body) = SafeHttp::get($base . "/.well-known/nodeinfo", array("Accept" => "application/json"), 65536);
            $index = $status == 200 ? json_decode((string)$body, true) : null;
            $href = null;
            foreach (is_array($index) ? ($index['links'] ?? array()) : array() as $link) {
                if (is_string($link['href'] ?? null) && strpos((string)($link['rel'] ?? ""), "nodeinfo.diaspora.software/ns/schema/2") !== false
                    && parse_url($link['href'], PHP_URL_HOST) === parse_url($base, PHP_URL_HOST)) {
                    $href = $link['href'];
                }
            }
            if ($href) {
                list($status, $body) = SafeHttp::get($href, array("Accept" => "application/json"), 262144);
                $info = $status == 200 ? json_decode((string)$body, true) : null;
                $node_name = is_array($info) ? ($info['metadata']['nodeName'] ?? null) : null;
                if (is_string($node_name)) {
                    $name = mb_substr(trim(preg_replace('/\s+/u', " ", strip_tags(html_entity_decode($node_name, ENT_QUOTES)))), 0, 80);
                }
            }
        } catch (Exception $e) {
            //unreachable: keep the address until next week's try
        }
        $db->Fetch("UPDATE `remote_blogs` SET `name` = :b, `fetched_at` = NOW() WHERE `host` = :a",
            array(":a" => (string)$host, ":b" => $name !== "" ? $name : null));
        self::$blogs = null;
    }

    /**
     * "mia from Mia's Closet" (or "mia from closet.example") as a handle find() understands
     *
     * @param string $query
     * @return string|null name@host[:port], or name@ this domain for someone here; null when it isn't that form
     */
    public static function from_display($query): ?string
    {
        if (!preg_match('/^@?([a-z0-9_.\-]{1,128})\s+from\s+(.{1,255})$/iu', trim((string)$query), $m)) {
            return null;
        }
        list(, $name, $blog) = $m;
        $blog = trim($blog);
        if (strcasecmp($blog, instance_name()) === 0 || Federation::is_own_domain($blog)) {
            return $name . "@" . Federation::domain();
        }
        $db = new Mysql();
        $rows = $db->Fetch("SELECT `base_url` FROM `remote_blogs` WHERE LOWER(`name`) = LOWER(:a) OR `host` = LOWER(:a)", array(":a" => $blog));
        if ($rows[0] != '00000' && count($rows) == 1) {
            return $name . "@" . parse_url($rows[0]->base_url, PHP_URL_HOST) . (parse_url($rows[0]->base_url, PHP_URL_PORT) ? ":" . parse_url($rows[0]->base_url, PHP_URL_PORT) : "");
        }
        //not a blog anyone here has met yet: it may be its address
        return preg_match('/^[a-z0-9.\-]+(:[0-9]{1,5})?$/i', $blog) ? $name . "@" . strtolower($blog) : null;
    }
}
