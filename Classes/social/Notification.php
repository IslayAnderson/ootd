<?php

/**
 * In-app notifications: follow, follow_request, follow_accepted (more arrive with federation)
 */
class Notification
{
    const TYPES = array(
        "follow" => "started following you",
        "follow_request" => "asked to follow you",
        "follow_accepted" => "accepted your follow request"
    );

    public static function add($user_id, $type, $actor_id = null, $object = null): void
    {
        $sql = "INSERT INTO `notifications` (`user_id`, `type`, `actor_id`, `object`) VALUES (:a, :b, :c, :d)";
        $db = new Mysql();
        $db->Fetch($sql, array(":a" => (int)$user_id, ":b" => $type, ":c" => $actor_id, ":d" => $object));
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
