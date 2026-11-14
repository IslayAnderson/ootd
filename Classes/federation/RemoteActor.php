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
            if ($host === strtolower(Federation::domain())) {
                return null;
            }
            $db = new Mysql();
            $row = $db->Fetch("SELECT `ap_id` FROM `remote_actors` WHERE `username` = :a AND `host` = :b", array(":a" => $name, ":b" => explode(":", $host)[0]));
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
        $scheme = !empty($_ENV['AP_ALLOW_HTTP']) && preg_match('/^(127\.|localhost)/', $host) ? "http" : "https";
        $url = $scheme . "://" . $host . "/.well-known/webfinger?resource=" . rawurlencode("acct:" . $name . "@" . explode(":", $host)[0]);
        try {
            list($status, $body) = SafeHttp::get($url, array("Accept" => "application/jrd+json, application/json"));
        } catch (Exception $e) {
            return null;
        }
        $document = json_decode((string)$body, true);
        if ($status != 200 || !is_array($document)) {
            return null;
        }
        foreach ($document['links'] ?? array() as $link) {
            if (($link['rel'] ?? "") == "self" && is_string($link['href'] ?? null)
                && preg_match('#^application/(activity\+json|ld\+json)#', (string)($link['type'] ?? ""))) {
                return $link['href'];
            }
        }
        return null;
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
     * @param object $actor remote_actors row
     * @return string @name@host
     */
    public static function handle($actor): string
    {
        return "@" . ($actor->username ?: "unknown") . "@" . $actor->host;
    }
}
