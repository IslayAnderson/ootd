<?php

/**
 * "Love this look": likes on outfits here and on posts from other servers.
 * Liking a post from elsewhere sends Like to its author's server (Undo when taken back); likes
 * from other servers on outfits here arrive through the inbox.
 */
class Like
{
    const KINDS = array("outfit", "remote");

    /**
     * @param int $user_id
     * @param string $kind outfit or remote
     * @param string $id outfit_id, or post_num for a remote post
     * @return bool liked after the toggle
     * @throws Exception
     */
    public static function toggle($user_id, $kind, $id): bool
    {
        $parent = Comment::parent($kind, $id, $user_id);
        if (!$parent) {
            throw new Exception("Post not found");
        }
        $db = new Mysql();
        $existing = $db->Fetch("SELECT `like_num`, `activity_id` FROM `likes` WHERE `user_id` = :a AND `kind` = :b AND `object_num` = :c",
            array(":a" => (int)$user_id, ":b" => $kind, ":c" => $parent['num']));
        $user = $db->Fetch("SELECT `username` FROM `users` WHERE `user_id` = :a", array(":a" => (int)$user_id))[0];
        $actor_url = Federation::actor_url($user->username);

        if ($existing[0] != '00000') {
            $db->Fetch("DELETE FROM `likes` WHERE `like_num` = :a", array(":a" => $existing[0]->like_num));
            if ($kind == "remote" && !empty($existing[0]->activity_id) && Federation::enabled()) {
                Federation::send((int)$user_id, $parent['inbox'], array(
                    "@context" => "https://www.w3.org/ns/activitystreams",
                    "id" => $actor_url . "#undo-" . bin2hex(random_bytes(8)),
                    "type" => "Undo",
                    "actor" => $actor_url,
                    "object" => array("id" => $existing[0]->activity_id, "type" => "Like", "actor" => $actor_url, "object" => $parent['ap_id'])
                ));
            }
            return false;
        }

        $activity_id = $kind == "remote" ? $actor_url . "#like-" . bin2hex(random_bytes(8)) : null;
        $db->Fetch("INSERT INTO `likes` (`user_id`, `kind`, `object_num`, `activity_id`) VALUES (:a, :b, :c, :d)",
            array(":a" => (int)$user_id, ":b" => $kind, ":c" => $parent['num'], ":d" => $activity_id));
        if ($kind == "remote") {
            if (Federation::enabled()) {
                Federation::send((int)$user_id, $parent['inbox'], array(
                    "@context" => "https://www.w3.org/ns/activitystreams",
                    "id" => $activity_id,
                    "type" => "Like",
                    "actor" => $actor_url,
                    "object" => $parent['ap_id']
                ));
            }
        } elseif ((int)$parent['owner'] !== (int)$user_id) {
            Notification::add($parent['owner'], "like", (int)$user_id, null, $parent['link']);
        }
        return true;
    }

    /**
     * Someone elsewhere liked an outfit here
     *
     * @param object $actor
     * @param array $activity
     * @return bool new (so worth a notification)
     */
    public static function remote_like($actor, $activity): bool
    {
        $num = self::local_outfit_num(Inbox::id($activity['object'] ?? null));
        if ($num === null) {
            //blog posts aren't counted, only notified (Inbox)
            return true;
        }
        $db = new Mysql();
        $existing = $db->Fetch("SELECT 1 AS yes FROM `likes` WHERE `actor_num` = :a AND `kind` = 'outfit' AND `object_num` = :b",
            array(":a" => (int)$actor->actor_num, ":b" => $num));
        if ($existing[0] != '00000') {
            return false;
        }
        $db->Fetch("INSERT INTO `likes` (`actor_num`, `kind`, `object_num`, `activity_id`) VALUES (:a, 'outfit', :b, :c)",
            array(":a" => (int)$actor->actor_num, ":b" => $num, ":c" => is_string($activity['id'] ?? null) ? mb_substr($activity['id'], 0, 512) : null));
        return true;
    }

    /**
     * @param object $actor
     * @param array $like the Like being undone
     * @return void
     */
    public static function remote_undo($actor, $like): void
    {
        $db = new Mysql();
        $num = self::local_outfit_num(Inbox::id($like['object'] ?? null));
        if ($num !== null) {
            $db->Fetch("DELETE FROM `likes` WHERE `actor_num` = :a AND `kind` = 'outfit' AND `object_num` = :b", array(":a" => (int)$actor->actor_num, ":b" => $num));
        } elseif (is_string(Inbox::id($like))) {
            $db->Fetch("DELETE FROM `likes` WHERE `actor_num` = :a AND `activity_id` = :b", array(":a" => (int)$actor->actor_num, ":b" => Inbox::id($like)));
        }
    }

    private static function local_outfit_num($url): ?int
    {
        $prefix = absolute_url("/outfit/");
        if (!is_string($url) || strpos($url, $prefix) !== 0) {
            return null;
        }
        return \Post\Outfit::num_from_id(rawurldecode(substr($url, strlen($prefix))));
    }

    /**
     * @param string $kind
     * @param int $num
     * @param int|null $viewer
     * @return array [count, liked by the viewer]
     */
    public static function summary($kind, $num, $viewer = null): array
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT COUNT(*) AS n, COALESCE(SUM(`user_id` = :b), 0) AS mine FROM `likes` WHERE `kind` = :k AND `object_num` = :a",
            array(":a" => (int)$num, ":b" => (int)$viewer, ":k" => $kind));
        return array((int)$row[0]->n, (int)$row[0]->mine > 0);
    }

    /**
     * Likes and comment count under a post
     *
     * @param string $kind outfit or remote
     * @param int $num outfit_num or post_num
     * @param string $id what the forms send: outfit_id or post_num
     * @param string $comments_link
     * @return void
     */
    public static function render_bar($kind, $num, $id, $comments_link): void
    {
        $viewer = (int)($_SESSION['user_id'] ?? 0);
        list($count, $mine) = self::summary($kind, $num, $viewer);
        $comments = Comment::count($kind, $num);
        ?>
        <div class="reactions">
            <?php if ($viewer > 0): ?>
                <form action="/API/like/toggle.php" method="post" class="like">
                    <?php CSRF_input(); ?>
                    <input type="hidden" name="kind" value="<?= escape($kind) ?>">
                    <input type="hidden" name="id" value="<?= escape($id) ?>">
                    <button type="submit" class="link-button" aria-pressed="<?= $mine ? "true" : "false" ?>">
                        <?= $mine ? "&hearts; Loved" : "&#9825; Love this look" ?><?= $count > 0 ? " &middot; " . $count : "" ?>
                    </button>
                </form>
            <?php elseif ($count > 0): ?>
                <span>&hearts; <?= $count ?></span>
            <?php endif; ?>
            <a href="<?= escape($comments_link) ?>#comments"><?= $comments == 0 ? "Comment" : $comments . " " . ($comments == 1 ? "comment" : "comments") ?></a>
        </div>
        <?php
    }
}
