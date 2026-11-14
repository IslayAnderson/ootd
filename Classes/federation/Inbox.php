<?php

/**
 * Incoming activities. 0.11.0 handles what's needed to be followed from elsewhere: Follow,
 * Undo Follow, and Delete of an account that's gone. Anything else is accepted and ignored until
 * 0.12.0 (following out) handles it.
 */
class Inbox
{
    /**
     * Verify and handle one POST to /inbox or /user/<name>/inbox
     *
     * @param string $target path and query as requested
     * @param array $headers lower-case name => value
     * @param string $body
     * @return int HTTP status to answer with
     */
    public static function receive($target, $headers, $body): int
    {
        if (!Federation::enabled()) {
            return 403;
        }
        $activity = json_decode($body, true);
        if (!is_array($activity) || !is_string($activity['type'] ?? null) || !is_string(self::id($activity['actor'] ?? null))) {
            return 400;
        }
        $actor_id = self::id($activity['actor']);

        try {
            $key_id = HttpSignature::verify("POST", $target, $headers, $body, function ($key_id, $refresh) use ($activity, $actor_id) {
                //a deleted account can't be fetched any more; its Delete can only be checked against the cached key
                if ($refresh && $activity['type'] == "Delete" && self::id($activity['object'] ?? null) === $actor_id) {
                    return null;
                }
                return RemoteActor::key_for($key_id, $refresh);
            });
        } catch (Exception $e) {
            //someone we've never heard of deleting their account: nothing to do
            if ($activity['type'] == "Delete" && self::id($activity['object'] ?? null) === $actor_id) {
                return 202;
            }
            error_log("OOTD inbox: " . $e->getMessage() . " (" . $actor_id . ")");
            return 401;
        }

        //the key must belong to the actor the activity claims to be from
        if (RemoteActor::owner_of($key_id) !== $actor_id) {
            return 401;
        }
        $actor = RemoteActor::get($actor_id);
        if (!$actor) {
            return 401;
        }

        switch ($activity['type']) {
            case "Follow":
                return self::follow($actor, $activity);
            case "Undo":
                if (($activity['object']['type'] ?? null) == "Follow") {
                    return self::unfollow($actor, $activity['object']);
                }
                return 202;
            case "Delete":
                if (self::id($activity['object'] ?? null) === $actor_id) {
                    $db = new Mysql();
                    $db->Fetch("DELETE FROM `remote_followers` WHERE `actor_num` = :a", array(":a" => $actor->actor_num));
                }
                return 202;
            default:
                return 202;
        }
    }

    /**
     * @param mixed $value an id or an object with an id
     * @return string|null
     */
    public static function id($value): ?string
    {
        if (is_string($value)) {
            return $value;
        }
        return is_array($value) && is_string($value['id'] ?? null) ? $value['id'] : null;
    }

    /**
     * @param string $actor_url
     * @return object|null local user an actor URL on this server belongs to
     */
    private static function local_target($actor_url)
    {
        $prefix = absolute_url("/user/");
        if (!is_string($actor_url) || strpos($actor_url, $prefix) !== 0) {
            return null;
        }
        return Federation::local_user(rawurldecode(substr($actor_url, strlen($prefix))));
    }

    private static function follow($actor, $activity): int
    {
        $user = self::local_target(self::id($activity['object'] ?? null));
        if (!$user || !is_string($activity['id'] ?? null)) {
            return 404;
        }
        $db = new Mysql();
        $existing = $db->Fetch("SELECT `state` FROM `remote_followers` WHERE `user_id` = :a AND `actor_num` = :b",
            array(":a" => $user->user_id, ":b" => $actor->actor_num));
        $state = $existing[0] != '00000' ? $existing[0]->state : ((int)$user->approve_followers === 1 ? Follow::PENDING : Follow::ACCEPTED);

        $db->Fetch("INSERT INTO `remote_followers` (`user_id`, `actor_num`, `state`, `follow_id`) VALUES (:a, :b, :c, :d)
                    ON DUPLICATE KEY UPDATE `follow_id` = VALUES(`follow_id`)",
            array(":a" => $user->user_id, ":b" => $actor->actor_num, ":c" => $state, ":d" => mb_substr($activity['id'], 0, 512)));

        if ($state === Follow::ACCEPTED) {
            self::answer($user, $actor, $activity, "Accept");
        }
        if ($existing[0] == '00000') {
            Notification::add($user->user_id, $state === Follow::ACCEPTED ? "follow" : "follow_request", null, RemoteActor::handle($actor));
        }
        return 202;
    }

    private static function unfollow($actor, $follow): int
    {
        $user = self::local_target(self::id($follow['object'] ?? null));
        if ($user) {
            $db = new Mysql();
            $db->Fetch("DELETE FROM `remote_followers` WHERE `user_id` = :a AND `actor_num` = :b", array(":a" => $user->user_id, ":b" => $actor->actor_num));
        }
        return 202;
    }

    /**
     * Send Accept or Reject for a Follow
     *
     * @param object $user local user row
     * @param object $actor remote_actors row
     * @param array|string $follow the Follow activity, or its id
     * @param string $type Accept or Reject
     * @return void
     */
    public static function answer($user, $actor, $follow, $type): void
    {
        $actor_url = Federation::actor_url($user->username);
        if (is_string($follow)) {
            $follow = array("id" => $follow, "type" => "Follow", "actor" => $actor->ap_id, "object" => $actor_url);
        }
        Federation::send((int)$user->user_id, $actor->inbox, array(
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $actor_url . "#" . strtolower($type) . "-" . bin2hex(random_bytes(8)),
            "type" => $type,
            "actor" => $actor_url,
            "object" => array("id" => $follow['id'], "type" => "Follow", "actor" => $actor->ap_id, "object" => $actor_url)
        ));
    }
}
