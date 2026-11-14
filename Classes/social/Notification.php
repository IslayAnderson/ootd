<?php

/**
 * In-app notifications. Someone on another server is named by handle in `object`; `link` points at
 * what it's about (a post, or a remote profile).
 */
class Notification
{
    const TYPES = array(
        "follow" => "started following you",
        "follow_request" => "asked to follow you",
        "follow_accepted" => "accepted your follow request",
        "like" => "liked your post",
        "announce" => "boosted your post",
        "reply" => "commented on your post"
    );

    /**
     * @param int $user_id who is told
     * @param string $type a key of TYPES
     * @param int|null $actor_id local user who did it
     * @param string|null $object handle of someone elsewhere who did it
     * @param string|null $link local path it's about
     * @return void
     */
    public static function add($user_id, $type, $actor_id = null, $object = null, $link = null): void
    {
        $sql = "INSERT INTO `notifications` (`user_id`, `type`, `actor_id`, `object`, `link`) VALUES (:a, :b, :c, :d, :e)";
        $db = new Mysql();
        $db->Fetch($sql, array(":a" => (int)$user_id, ":b" => $type, ":c" => $actor_id, ":d" => $object === null ? null : mb_substr($object, 0, 128), ":e" => $link));
    }

    public static function unread_count($user_id): int
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT COUNT(*) AS unread FROM `notifications` WHERE `user_id` = :a AND `read_at` IS NULL", array(":a" => (int)$user_id));
        return $row[0] != '00000' ? (int)$row[0]->unread : 0;
    }

    /**
     * @param int $user_id
     * @param int $limit
     * @return array newest first, with the actor's username
     */
    public static function latest($user_id, $limit = 50): array
    {
        $sql = "SELECT notifications.*, users.username AS actor FROM `notifications`
                LEFT JOIN `users` ON users.user_id = notifications.actor_id
                WHERE notifications.user_id = :a
                ORDER BY notifications.notification_num DESC LIMIT " . (int)$limit;
        $db = new Mysql();
        $row = $db->Fetch($sql, array(":a" => (int)$user_id));
        return $row[0] != '00000' ? $row : array();
    }

    public static function mark_read($user_id): void
    {
        $db = new Mysql();
        $db->Fetch("UPDATE `notifications` SET `read_at` = NOW() WHERE `user_id` = :a AND `read_at` IS NULL", array(":a" => (int)$user_id));
    }
}
