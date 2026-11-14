<?php

/**
 * Comments on outfits here and on posts from other servers. A comment is seen by whoever can see
 * the post. Comments on public outfits, and on posts from elsewhere, federate as replies (a Note
 * with inReplyTo); replies from other servers to posts here, or to posts copied here, arrive
 * through the inbox and are stored as comments.
 */
class Comment
{
    const MAX = 2000;

    /**
     * What a comment or like hangs off, if the viewer can see it
     *
     * @param string $kind outfit or remote
     * @param string $id outfit_id, or post_num for a remote post
     * @param int|null $viewer
     * @return array|null num, owner (local user_id), link, ap_id, federates, and for remote posts inbox/actor/audience
     */
    public static function parent($kind, $id, $viewer = null): ?array
    {
        if ($kind == "outfit") {
            $num = \Post\Outfit::num_from_id((string)$id);
            $outfit = $num ? new \Post\Outfit($num) : null;
            if (!$outfit || !$outfit->viewable()) {
                return null;
            }
            return array(
                "kind" => "outfit",
                "num" => (int)$num,
                "owner" => (int)$outfit->user['user_id'],
                "link" => "/outfit/" . rawurlencode($outfit->outfit_id),
                "ap_id" => absolute_url("/outfit/" . $outfit->outfit_id),
                "visibility" => $outfit->visibility,
                "federates" => $outfit->visibility == VISIBILITY_PUBLIC
            );
        }
        if ($kind == "remote" && !empty($viewer) && Federation::enabled()) {
            $db = new Mysql();
            $row = $db->Fetch("SELECT remote_actors.*, remote_posts.post_num, remote_posts.ap_id AS post_ap_id, remote_posts.audience
                               FROM `remote_posts` JOIN `remote_actors` ON remote_actors.actor_num = remote_posts.actor_num
                               WHERE remote_posts.post_num = :a AND " . RemotePost::visible_where($viewer), array(":a" => (int)$id));
            if ($row[0] == '00000') {
                return null;
            }
            return array(
                "kind" => "remote",
                "num" => (int)$row[0]->post_num,
                "owner" => null,
                "link" => "/remote/" . (int)$row[0]->post_num,
                "ap_id" => $row[0]->post_ap_id,
                "inbox" => $row[0]->inbox,
                "actor" => $row[0],
                "audience" => $row[0]->audience,
                "federates" => true
            );
        }
        return null;
    }

    /**
     * @param int $user_id
     * @param string $kind
     * @param string $id
     * @param string $content plain text
     * @return string comment_id
     * @throws Exception
     */
    public static function create($user_id, $kind, $id, $content): string
    {
        $content = trim(str_replace("\r\n", "\n", (string)$content));
        if ($content === "") {
            throw new Exception("Write something first");
        }
        $parent = self::parent($kind, $id, $user_id);
        if (!$parent) {
            throw new Exception("Post not found");
        }
        $comment_id = bin2hex(random_bytes(16));
        $db = new Mysql();
        $db->Fetch("INSERT INTO `comments` (`comment_id`, `kind`, `parent_num`, `user_id`, `content`) VALUES (:a, :b, :c, :d, :e)",
            array(":a" => $comment_id, ":b" => $parent['kind'], ":c" => $parent['num'], ":d" => (int)$user_id, ":e" => mb_substr($content, 0, self::MAX)));
        if (!empty($parent['owner']) && (int)$parent['owner'] !== (int)$user_id) {
            Notification::add($parent['owner'], "reply", (int)$user_id, null, $parent['link'] . "#comment-" . $comment_id);
        }
        if ($parent['federates'] && Federation::enabled()) {
            Jobs::dispatch("ap_comment", array("comment_id" => $comment_id, "action" => "create"));
        }
        return $comment_id;
    }

    /**
     * The author, the post's owner, or an admin can delete a comment
     *
     * @param int $user_id
     * @param string $comment_id
     * @return void
     * @throws Exception
     */
    public static function delete($user_id, $comment_id): void
    {
        $comment = self::get($comment_id);
        if (!$comment) {
            throw new Exception("Comment not found");
        }
        $owner = null;
        if ($comment->kind == "outfit") {
            $db = new Mysql();
            $row = $db->Fetch("SELECT `user` FROM `outfits` WHERE `outfit_num` = :a", array(":a" => $comment->parent_num));
            $owner = $row[0] != '00000' ? (int)$row[0]->user : null;
        }
        if ((int)$comment->user_id !== (int)$user_id && $owner !== (int)$user_id && !is_admin()) {
            throw new Exception("That isn't yours to delete");
        }
        $db = new Mysql();
        $db->Fetch("UPDATE `comments` SET `deleted_at` = NOW() WHERE `comment_num` = :a", array(":a" => $comment->comment_num));
        if ((int)$comment->federated === 1 && !empty($comment->user_id) && Federation::enabled()) {
            Jobs::dispatch("ap_comment", array("comment_id" => $comment->comment_id, "action" => "delete"));
        }
    }

    /**
     * @param string $comment_id
     * @return object|null comments row (not deleted) with the local author's username
     */
    public static function get($comment_id)
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT comments.*, users.username FROM `comments` LEFT JOIN `users` ON users.user_id = comments.user_id
                           WHERE comments.comment_id = :a AND comments.deleted_at IS NULL", array(":a" => (string)$comment_id));
        return $row[0] != '00000' ? $row[0] : null;
    }

    /**
     * @param object $comment row from get()
     * @return string where it's shown: its post's page, at the comment
     */
    public static function link($comment): string
    {
        if ($comment->kind == "remote") {
            return "/remote/" . (int)$comment->parent_num . "#comment-" . $comment->comment_id;
        }
        $db = new Mysql();
        $row = $db->Fetch("SELECT `outfit_id` FROM `outfits` WHERE `outfit_num` = :a AND `deleted_at` IS NULL", array(":a" => (int)$comment->parent_num));
        return $row[0] != '00000' ? "/outfit/" . rawurlencode($row[0]->outfit_id) . "#comment-" . $comment->comment_id : "/feed";
    }

    /**
     * @param string $kind
     * @param int $num
     * @return array oldest first, with username (local) or handle parts (remote)
     */
    public static function for_parent($kind, $num): array
    {
        $db = new Mysql();
        $rows = $db->Fetch("SELECT comments.*, users.username, users.media_id AS user_media, remote_actors.username AS remote_username,
                                   remote_actors.host AS remote_host, remote_actors.url AS remote_url
                            FROM `comments`
                            LEFT JOIN `users` ON users.user_id = comments.user_id
                            LEFT JOIN `remote_actors` ON remote_actors.actor_num = comments.actor_num
                            WHERE comments.kind = :k AND comments.parent_num = :a AND comments.deleted_at IS NULL
                            ORDER BY comments.timestamp ASC, comments.comment_num ASC LIMIT 500", array(":k" => $kind, ":a" => (int)$num));
        return $rows[0] != '00000' ? $rows : array();
    }

    public static function count($kind, $num): int
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT COUNT(*) AS n FROM `comments` WHERE `kind` = :k AND `parent_num` = :a AND `deleted_at` IS NULL", array(":k" => $kind, ":a" => (int)$num));
        return (int)$row[0]->n;
    }

    /**
     * @param object $comment row from get()
     * @return array|null the Note for a local comment, null when it doesn't federate
     */
    public static function note($comment): ?array
    {
        if (empty($comment->user_id) || empty($comment->username)) {
            return null;
        }
        $actor = Federation::actor_url($comment->username);
        $db = new Mysql();
        $mention = "";
        $tag = array();
        if ($comment->kind == "outfit") {
            $row = $db->Fetch("SELECT outfits.outfit_id, outfits.visibility, users.username FROM `outfits` JOIN `users` ON users.user_id = outfits.user
                               WHERE outfits.outfit_num = :a AND outfits.deleted_at IS NULL", array(":a" => $comment->parent_num));
            if ($row[0] == '00000' || $row[0]->visibility != VISIBILITY_PUBLIC) {
                return null;
            }
            $in_reply_to = absolute_url("/outfit/" . $row[0]->outfit_id);
            $to = array(Federation::PUBLIC_AUDIENCE);
            $cc = array(Federation::actor_url($row[0]->username), $actor . "/followers");
            $url = $in_reply_to . "#comment-" . $comment->comment_id;
        } else {
            $row = $db->Fetch("SELECT remote_posts.ap_id AS post_ap_id, remote_posts.audience, remote_actors.ap_id, remote_actors.username, remote_actors.host, remote_actors.url
                               FROM `remote_posts` JOIN `remote_actors` ON remote_actors.actor_num = remote_posts.actor_num
                               WHERE remote_posts.post_num = :a", array(":a" => $comment->parent_num));
            if ($row[0] == '00000') {
                return null;
            }
            $in_reply_to = $row[0]->post_ap_id;
            //a reply to a followers-only post goes to its author only
            $to = $row[0]->audience == VISIBILITY_PUBLIC ? array(Federation::PUBLIC_AUDIENCE) : array($row[0]->ap_id);
            $cc = $row[0]->audience == VISIBILITY_PUBLIC ? array($row[0]->ap_id, $actor . "/followers") : array();
            $handle = "@" . $row[0]->username . "@" . $row[0]->host;
            $mention = '<span class="h-card"><a href="' . escape($row[0]->url ?: $row[0]->ap_id) . '" class="u-url mention">@' . escape($row[0]->username) . '</a></span> ';
            $tag[] = array("type" => "Mention", "href" => $row[0]->ap_id, "name" => $handle);
            $url = absolute_url("/remote/" . (int)$comment->parent_num) . "#comment-" . $comment->comment_id;
        }
        return array(
            "id" => absolute_url("/comment/" . $comment->comment_id),
            "type" => "Note",
            "attributedTo" => $actor,
            "inReplyTo" => $in_reply_to,
            "url" => $url,
            "published" => Federation::time($comment->timestamp),
            "to" => $to,
            "cc" => $cc,
            "content" => "<p>" . $mention . nl2br(escape($comment->content), false) . "</p>",
            "tag" => $tag
        );
    }

    /**
     * Job: send a local comment (or its deletion) to the servers that should see it
     *
     * @param array $payload comment_id, action create or delete
     * @return void
     */
    public static function publish($payload): void
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT comments.*, users.username FROM `comments` JOIN `users` ON users.user_id = comments.user_id
                           WHERE comments.comment_id = :a", array(":a" => (string)($payload['comment_id'] ?? "")));
        if ($row[0] == '00000') {
            return;
        }
        $comment = $row[0];
        $note = self::note($comment);
        if (!$note) {
            return;
        }
        $inboxes = Federation::follower_inboxes((int)$comment->user_id);
        if ($comment->kind == "outfit") {
            $owner = $db->Fetch("SELECT `user` FROM `outfits` WHERE `outfit_num` = :a", array(":a" => $comment->parent_num));
            if ($owner[0] != '00000') {
                $inboxes = array_merge($inboxes, Federation::follower_inboxes((int)$owner[0]->user));
            }
        } else {
            $author = $db->Fetch("SELECT remote_actors.inbox, remote_posts.audience FROM `remote_posts` JOIN `remote_actors` ON remote_actors.actor_num = remote_posts.actor_num
                                  WHERE remote_posts.post_num = :a", array(":a" => $comment->parent_num));
            if ($author[0] == '00000') {
                return;
            }
            if ($author[0]->audience != VISIBILITY_PUBLIC) {
                $inboxes = array();
            }
            $inboxes[] = $author[0]->inbox;
        }
        $type = ($payload['action'] ?? "create") == "delete" ? "Delete" : "Create";
        $activity = Federation::wrap($type, $note);
        foreach (array_unique(array_filter($inboxes)) as $inbox) {
            Federation::send((int)$comment->user_id, $inbox, $activity);
        }
        if ($type == "Create") {
            $db->Fetch("UPDATE `comments` SET `federated` = 1 WHERE `comment_num` = :a", array(":a" => $comment->comment_num));
        }
    }

    /**
     * A reply from elsewhere to a post here, or to a post copied here: stored as a comment
     *
     * @param object $actor sender
     * @param array $object the Note
     * @return bool it was a reply to something here (handled, stored or not)
     */
    public static function ingest_reply($actor, $object): bool
    {
        $parent_url = Inbox::id($object['inReplyTo'] ?? null);
        $id = Inbox::id($object);
        if (!$parent_url || !$id) {
            return false;
        }
        $db = new Mysql();
        $parent = null;
        $local = Inbox::local_post($parent_url);
        if ($local && str_starts_with($local['link'], "/outfit/")) {
            $num = \Post\Outfit::num_from_id(rawurldecode(substr($local['link'], strlen("/outfit/"))));
            $parent = array("kind" => "outfit", "num" => (int)$num, "owner" => $local['owner'], "link" => $local['link']);
        } else {
            $row = $db->Fetch("SELECT `post_num` FROM `remote_posts` WHERE `ap_id` = :a", array(":a" => $parent_url));
            if ($row[0] != '00000') {
                $parent = array("kind" => "remote", "num" => (int)$row[0]->post_num, "owner" => null, "link" => "/remote/" . (int)$row[0]->post_num);
            }
        }
        if (!$parent) {
            return false;
        }
        //by the sender, on the sender's server, and public: private replies aren't shown to everyone who can see the post
        if (!in_array($actor->ap_id, RemotePost::ids($object['attributedTo'] ?? null), true)
            || strtolower((string)parse_url($id, PHP_URL_HOST)) !== strtolower((string)parse_url($actor->ap_id, PHP_URL_HOST))
            || RemotePost::audience($actor, $object) !== VISIBILITY_PUBLIC
            || !is_string($object['content'] ?? null)) {
            return true;
        }
        $content = mb_substr($object['content'], 0, 20000);
        $existing = $db->Fetch("SELECT `comment_num`, `actor_num` FROM `comments` WHERE `ap_id` = :a", array(":a" => $id));
        if ($existing[0] != '00000') {
            if ((int)$existing[0]->actor_num === (int)$actor->actor_num) {
                $db->Fetch("UPDATE `comments` SET `content` = :b, `updated_at` = NOW() WHERE `comment_num` = :a", array(":a" => $existing[0]->comment_num, ":b" => $content));
            }
            return true;
        }
        $comment_id = bin2hex(random_bytes(16));
        $db->Fetch("INSERT INTO `comments` (`comment_id`, `kind`, `parent_num`, `actor_num`, `ap_id`, `content`) VALUES (:a, :b, :c, :d, :e, :f)",
            array(":a" => $comment_id, ":b" => $parent['kind'], ":c" => $parent['num'], ":d" => (int)$actor->actor_num, ":e" => mb_substr($id, 0, 512), ":f" => $content));
        if (!empty($parent['owner'])) {
            Notification::add($parent['owner'], "reply", null, RemoteActor::handle($actor), $parent['link'] . "#comment-" . $comment_id);
        }
        return true;
    }

    /**
     * @param object $actor
     * @param string $ap_id
     * @return bool a comment was removed
     */
    public static function remove_remote($actor, $ap_id): bool
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `comment_num` FROM `comments` WHERE `ap_id` = :a AND `actor_num` = :b AND `deleted_at` IS NULL",
            array(":a" => (string)$ap_id, ":b" => (int)$actor->actor_num));
        if ($row[0] == '00000') {
            return false;
        }
        $db->Fetch("UPDATE `comments` SET `deleted_at` = NOW() WHERE `comment_num` = :a", array(":a" => $row[0]->comment_num));
        return true;
    }

    /**
     * @param string $kind
     * @param int $num
     * @param string $id what the form sends (outfit_id or post_num)
     * @param int|null $owner user_id of the post's owner, who may delete comments
     * @return void
     */
    public static function render_section($kind, $num, $id, $owner = null): void
    {
        $viewer = (int)($_SESSION['user_id'] ?? 0);
        $comments = self::for_parent($kind, $num);
        ?>
        <section class="comments" id="comments">
            <h2>Comments</h2>
            <?php if (count($comments) == 0): ?>
                <p class="empty">No comments yet.</p>
            <?php else: ?>
                <ol>
                    <?php foreach ($comments as $comment): ?>
                        <li id="comment-<?= escape($comment->comment_id) ?>">
                            <p class="byline">
                                <?php if (!empty($comment->user_id)): ?>
                                    <a href="/user/<?= escape(rawurlencode((string)$comment->username)) ?>"><?= escape((string)$comment->username) ?></a>
                                <?php else: ?>
                                    <a href="/@<?= escape(rawurlencode((string)$comment->remote_username) . "@" . rawurlencode((string)$comment->remote_host)) ?>" title="<?= escape($comment->remote_username . "@" . $comment->remote_host) ?>"><?= escape(RemoteActor::display((object)array("username" => $comment->remote_username, "host" => $comment->remote_host))) ?></a>
                                <?php endif; ?>
                                <time datetime="<?= escape($comment->timestamp) ?>"><?= escape(date_display($comment->timestamp)) ?></time>
                            </p>
                            <div class="comment-body">
                                <?php if (!empty($comment->user_id)): ?>
                                    <p><?= nl2br(escape($comment->content), false) ?></p>
                                <?php else: ?>
                                    <?= sanitise_html($comment->content) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($viewer > 0 && ((int)$comment->user_id === $viewer || (int)$owner === $viewer || is_admin())): ?>
                                <form action="/API/comment/delete.php" method="post" class="delete" data-confirm="Delete this comment?">
                                    <?php CSRF_input(); ?>
                                    <input type="hidden" name="comment_id" value="<?= escape($comment->comment_id) ?>">
                                    <button type="submit" class="link-button">Delete</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if ($viewer > 0): ?>
                <form action="/API/comment/create.php" method="post" class="comment-form">
                    <?php CSRF_input(); ?>
                    <input type="hidden" name="kind" value="<?= escape($kind) ?>">
                    <input type="hidden" name="id" value="<?= escape($id) ?>">
                    <label for="comment-content">Add a comment</label>
                    <textarea id="comment-content" name="content" maxlength="<?= self::MAX ?>" rows="3" required></textarea>
                    <button type="submit">Post comment</button>
                </form>
            <?php else: ?>
                <p class="notice"><a href="/login">Log in</a> to comment.</p>
            <?php endif; ?>
        </section>
        <?php
    }
}
