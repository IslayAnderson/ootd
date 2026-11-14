<?php

/**
 * Local users following people on other servers. A follow is "pending" until their server sends
 * Accept; once accepted, their posts are delivered here and appear in the Following feed.
 */
class RemoteFollow
{
    /**
     * @param int $user_id
     * @param int $actor_num
     * @return string|null pending, accepted, or null when not following
     */
    public static function state($user_id, $actor_num): ?string
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `state` FROM `remote_follows` WHERE `user_id` = :a AND `actor_num` = :b",
            array(":a" => (int)$user_id, ":b" => (int)$actor_num));
        return $row[0] != '00000' ? $row[0]->state : null;
    }

    /**
     * Ask to follow someone elsewhere; their server answers with Accept or Reject later
     *
     * @param int $user_id
     * @param object $actor remote_actors row
     * @return string the state
     * @throws Exception
     */
    public static function follow($user_id, $actor): string
    {
        if (!Federation::enabled()) {
            throw new Exception("Following people on other servers is turned off here");
        }
        if (empty($actor->inbox)) {
            throw new Exception("That account can't be followed");
        }
        $existing = self::state($user_id, $actor->actor_num);
        if ($existing !== null) {
            return $existing;
        }
        $user = self::user($user_id);
        $actor_url = Federation::actor_url($user->username);
        $follow_id = $actor_url . "#follow-" . bin2hex(random_bytes(8));

        $db = new Mysql();
        $db->Fetch("INSERT INTO `remote_follows` (`user_id`, `actor_num`, `state`, `follow_id`) VALUES (:a, :b, :c, :d)",
            array(":a" => (int)$user_id, ":b" => (int)$actor->actor_num, ":c" => Follow::PENDING, ":d" => $follow_id));

        Federation::send((int)$user_id, $actor->inbox, array(
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $follow_id,
            "type" => "Follow",
            "actor" => $actor_url,
            "object" => $actor->ap_id
        ));
        return Follow::PENDING;
    }

    /**
     * Unfollow, or withdraw a request: tells their server with Undo
     *
     * @param int $user_id
     * @param object $actor remote_actors row
     * @return void
     */
    public static function unfollow($user_id, $actor): void
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `follow_id` FROM `remote_follows` WHERE `user_id` = :a AND `actor_num` = :b",
            array(":a" => (int)$user_id, ":b" => (int)$actor->actor_num));
        if ($row[0] == '00000') {
            return;
        }
        $db->Fetch("DELETE FROM `remote_follows` WHERE `user_id` = :a AND `actor_num` = :b",
            array(":a" => (int)$user_id, ":b" => (int)$actor->actor_num));

        $user = self::user($user_id);
        $actor_url = Federation::actor_url($user->username);
        if (Federation::enabled() && !empty($actor->inbox)) {
            Federation::send((int)$user_id, $actor->inbox, array(
                "@context" => "https://www.w3.org/ns/activitystreams",
                "id" => $actor_url . "#undo-" . bin2hex(random_bytes(8)),
                "type" => "Undo",
                "actor" => $actor_url,
                "object" => array("id" => $row[0]->follow_id, "type" => "Follow", "actor" => $actor_url, "object" => $actor->ap_id)
            ));
        }
        RemotePost::forget_unfollowed($actor->actor_num);
    }

    /**
     * Their server accepted (or refused) a follow
     *
     * @param object $actor remote_actors row of the sender
     * @param mixed $follow the Follow, embedded or by id
     * @param bool $accepted
     * @return void
     */
    public static function answered($actor, $follow, $accepted): void
    {
        $db = new Mysql();
        $follow_id = Inbox::id($follow);
        $row = $db->Fetch("SELECT `user_id`, `state` FROM `remote_follows` WHERE `actor_num` = :a AND `follow_id` = :b",
            array(":a" => (int)$actor->actor_num, ":b" => (string)$follow_id));
        //some servers answer with a Follow of their own making: match it by who asked
        if ($row[0] == '00000' && is_array($follow) && Inbox::id($follow['object'] ?? null) === $actor->ap_id) {
            $user = self::user_for_actor_url(Inbox::id($follow['actor'] ?? null));
            if ($user) {
                $row = $db->Fetch("SELECT `user_id`, `state` FROM `remote_follows` WHERE `actor_num` = :a AND `user_id` = :b",
                    array(":a" => (int)$actor->actor_num, ":b" => (int)$user->user_id));
            }
        }
        if ($row[0] == '00000') {
            return;
        }
        $user_id = (int)$row[0]->user_id;
        if (!$accepted) {
            $db->Fetch("DELETE FROM `remote_follows` WHERE `actor_num` = :a AND `user_id` = :b", array(":a" => (int)$actor->actor_num, ":b" => $user_id));
            RemotePost::forget_unfollowed($actor->actor_num);
            return;
        }
        if ($row[0]->state === Follow::ACCEPTED) {
            return;
        }
        $db->Fetch("UPDATE `remote_follows` SET `state` = 'accepted' WHERE `actor_num` = :a AND `user_id` = :b", array(":a" => (int)$actor->actor_num, ":b" => $user_id));
        Notification::add($user_id, "follow_accepted", null, RemoteActor::handle($actor), RemoteActor::local_path($actor));
        Jobs::dispatch("ap_backfill", array("actor_num" => (int)$actor->actor_num));
    }

    /**
     * @param int $actor_num
     * @return bool does anyone here follow them (accepted)
     */
    public static function followed($actor_num): bool
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT 1 AS yes FROM `remote_follows` WHERE `actor_num` = :a AND `state` = 'accepted' LIMIT 1", array(":a" => (int)$actor_num));
        return $row[0] != '00000';
    }

    /**
     * @param int $user_id
     * @return array people elsewhere the user follows or asked to: actor rows plus state
     */
    public static function following($user_id): array
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT remote_actors.*, remote_follows.state FROM `remote_follows`
                           JOIN `remote_actors` ON remote_actors.actor_num = remote_follows.actor_num
                           WHERE remote_follows.user_id = :a ORDER BY remote_actors.host, remote_actors.username", array(":a" => (int)$user_id));
        return $row[0] != '00000' ? $row : array();
    }

    /**
     * An account elsewhere is gone: drop every follow of it
     *
     * @param int $actor_num
     * @return void
     */
    public static function actor_gone($actor_num): void
    {
        $db = new Mysql();
        $db->Fetch("DELETE FROM `remote_follows` WHERE `actor_num` = :a", array(":a" => (int)$actor_num));
    }

    private static function user($user_id)
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `user_id`, `username` FROM `users` WHERE `user_id` = :a", array(":a" => (int)$user_id));
        if ($row[0] == '00000') {
            throw new Exception("Account not found");
        }
        return $row[0];
    }

    private static function user_for_actor_url($url)
    {
        $prefix = absolute_url("/user/");
        if (!is_string($url) || strpos($url, $prefix) !== 0) {
            return null;
        }
        return Federation::local_user(rawurldecode(substr($url, strlen($prefix))));
    }
}
