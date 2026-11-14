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
            ":i" => $key['publicKeyPem'] ?? null
        );
        $db = new Mysql();
        $db->Fetch("INSERT INTO `remote_actors` (`ap_id`, `username`, `host`, `name`, `url`, `inbox`, `shared_inbox`, `key_id`, `public_key`, `fetched_at`)
                    VALUES (:a, :b, :c, :d, :e, :f, :g, :h, :i, NOW())
                    ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `host` = VALUES(`host`), `name` = VALUES(`name`), `url` = VALUES(`url`),
                      `inbox` = VALUES(`inbox`), `shared_inbox` = VALUES(`shared_inbox`), `key_id` = VALUES(`key_id`),
                      `public_key` = VALUES(`public_key`), `fetched_at` = NOW()", $params);
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
     * @param object $actor remote_actors row
     * @return string @name@host
     */
    public static function handle($actor): string
    {
        return "@" . ($actor->username ?: "unknown") . "@" . $actor->host;
    }
}
