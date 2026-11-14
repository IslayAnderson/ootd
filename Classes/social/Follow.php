<?php

/**
 * Who follows whom. A follow is "pending" until accepted when the followed account approves
 * followers (users.approve_followers), otherwise "accepted" straight away.
 * Following someone decides whose posts reach your Following feed and unlocks their
 * followers-only posts.
 */
class Follow
{
    const PENDING = "pending";
    const ACCEPTED = "accepted";

    /** per-request cache of follower:followed => state */
    private static $states = array();

    /**
     * @param int $follower_id
     * @param int $followed_id
     * @return string|null pending, accepted, or null when not following
     */
    public static function state($follower_id, $followed_id): ?string
    {
        $key = (int)$follower_id . ":" . (int)$followed_id;
        if (!array_key_exists($key, self::$states)) {
            $sql = "SELECT `state` FROM `follows` WHERE `follower_id` = :a AND `followed_id` = :b";
            $db = new Mysql();
            $row = $db->Fetch($sql, array(":a" => (int)$follower_id, ":b" => (int)$followed_id));
            self::$states[$key] = $row[0] != '00000' ? $row[0]->state : null;
        }
        return self::$states[$key];
    }

    /**
     * @param int $follower_id
     * @param int $followed_id
     * @return string the new state
     * @throws Exception
     */
    public static function follow($follower_id, $followed_id): string
    {
        if (empty($follower_id) || empty($followed_id) || (int)$follower_id == (int)$followed_id) {
            throw new Exception("You can't follow that account");
        }
        $existing = self::state($follower_id, $followed_id);
        if ($existing !== null) {
            return $existing;
        }

        $db = new Mysql();
        $row = $db->Fetch("SELECT `approve_followers` FROM `users` WHERE `user_id` = :a", array(":a" => (int)$followed_id));
        if ($row[0] == '00000') {
            throw new Exception("You can't follow that account");
        }
        $state = (int)$row[0]->approve_followers === 1 ? self::PENDING : self::ACCEPTED;

        $sql = "INSERT INTO `follows` (`follower_id`, `followed_id`, `state`) VALUES (:a, :b, :c)";
        $db->Fetch($sql, array(":a" => (int)$follower_id, ":b" => (int)$followed_id, ":c" => $state));
        self::$states[(int)$follower_id . ":" . (int)$followed_id] = $state;

        Notification::add($followed_id, $state == self::PENDING ? "follow_request" : "follow", $follower_id);
        return $state;
    }

    /**
     * Unfollow, or cancel a request that hasn't been answered
     */
    public static function unfollow($follower_id, $followed_id): void
    {
        $sql = "DELETE FROM `follows` WHERE `follower_id` = :a AND `followed_id` = :b";
        $db = new Mysql();
        $db->Fetch($sql, array(":a" => (int)$follower_id, ":b" => (int)$followed_id));
        unset(self::$states[(int)$follower_id . ":" . (int)$followed_id]);
    }

    /**
     * @param int $followed_id the account answering
     * @param int $follower_id the account that asked
     * @param bool $accept
     * @return void
     * @throws Exception
     */
    public static function respond($followed_id, $follower_id, $accept): void
    {
        if (self::state($follower_id, $followed_id) !== self::PENDING) {
            throw new Exception('Follow request not found');
        }
        if (!$accept) {
            self::unfollow($follower_id, $followed_id);
            return;
        }
        $sql = "UPDATE `follows` SET `state` = :c WHERE `follower_id` = :a AND `followed_id` = :b";
        $db = new Mysql();
        $db->Fetch($sql, array(":a" => (int)$follower_id, ":b" => (int)$followed_id, ":c" => self::ACCEPTED));
        self::$states[(int)$follower_id . ":" . (int)$followed_id] = self::ACCEPTED;
        Notification::add($follower_id, "follow_accepted", $followed_id);
    }

    /**
     * @param int $user_id
     * @return array [followers, following] counts of accepted follows
     */
    public static function counts($user_id): array
    {
        $db = new Mysql();
        $sql = "SELECT
                  (SELECT COUNT(*) FROM `follows` WHERE `followed_id` = :a AND `state` = 'accepted') AS followers,
                  (SELECT COUNT(*) FROM `follows` WHERE `follower_id` = :b AND `state` = 'accepted') AS following";
        $row = $db->Fetch($sql, array(":a" => (int)$user_id, ":b" => (int)$user_id));
        return array((int)$row[0]->followers, (int)$row[0]->following);
    }

    /**
     * @param int $user_id
     * @return array pending requests to follow the user: follower_id, username
     */
    public static function requests($user_id): array
    {
        $sql = "SELECT follows.follower_id, users.username FROM `follows`
                JOIN `users` ON users.user_id = follows.follower_id
                WHERE follows.followed_id = :a AND follows.state = 'pending'
                ORDER BY follows.timestamp ASC";
        $db = new Mysql();
        $row = $db->Fetch($sql, array(":a" => (int)$user_id));
        return $row[0] != '00000' ? $row : array();
    }

    /**
     * @param int $user_id
     * @return bool does the user follow anyone
     */
    public static function follows_anyone($user_id): bool
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT 1 AS yes FROM `follows` WHERE `follower_id` = :a AND `state` = 'accepted' LIMIT 1", array(":a" => (int)$user_id));
        return $row[0] != '00000';
    }
}
