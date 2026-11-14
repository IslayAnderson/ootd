<?php

/**
 * Admin clean-up from the users tab (0.13.4).
 *
 * content(): everything someone posted goes (outfits, garments, articles, comments, wears,
 * likes); posts other blogs have are taken back the usual way (Delete), so the account stays and
 * can start again.
 *
 * account(): their content, then the account itself. The row stays as a tombstone (deleted_at,
 * no email, no password, no session) so the username, which other blogs know them by, can't be
 * taken by someone else. Other blogs that follow them are sent Delete for the whole account, the
 * people they follow elsewhere get Undo, and their profile and actor answer 410 Gone.
 */
class UserRemoval
{
    /**
     * @param int $user_id
     * @return array what was removed, kind => count
     */
    public static function content($user_id): array
    {
        $user_id = (int)$user_id;
        $db = new Mysql();
        $done = array("outfits" => 0, "garments" => 0, "journal" => 0, "comments" => 0, "wears" => 0, "likes" => 0);

        $rows = $db->Fetch("SELECT `outfit_num`, `outfit_id`, `visibility` FROM `outfits` WHERE `user` = :a AND `deleted_at` IS NULL", array(":a" => $user_id));
        foreach ($rows[0] != '00000' ? $rows : array() as $row) {
            $db->Fetch("UPDATE `outfits` SET `deleted_at` = NOW() WHERE `outfit_num` = :a", array(":a" => $row->outfit_num));
            Federation::changed("outfit", $row->outfit_id, "delete", $row->visibility);
            $done['outfits']++;
        }
        $rows = $db->Fetch("SELECT COUNT(*) AS n FROM `garments` WHERE `user_id` = :a AND `deleted_at` IS NULL", array(":a" => $user_id));
        $done['garments'] = (int)$rows[0]->n;
        $db->Fetch("UPDATE `garments` SET `deleted_at` = NOW() WHERE `user_id` = :a AND `deleted_at` IS NULL", array(":a" => $user_id));

        $rows = $db->Fetch("SELECT `blog_num`, `blog_id`, `visibility` FROM `blogs` WHERE `user` = :a AND `deleted_at` IS NULL", array(":a" => $user_id));
        foreach ($rows[0] != '00000' ? $rows : array() as $row) {
            $db->Fetch("UPDATE `blogs` SET `deleted_at` = NOW() WHERE `blog_num` = :a", array(":a" => $row->blog_num));
            Federation::changed("blog", $row->blog_id, "delete", $row->visibility);
            $done['journal']++;
        }

        //their comments, and replies from elsewhere under their (now deleted) outfits go with them
        $rows = $db->Fetch("SELECT `comment_num`, `comment_id`, `federated` FROM `comments` WHERE `user_id` = :a AND `deleted_at` IS NULL", array(":a" => $user_id));
        foreach ($rows[0] != '00000' ? $rows : array() as $row) {
            $db->Fetch("UPDATE `comments` SET `deleted_at` = NOW() WHERE `comment_num` = :a", array(":a" => $row->comment_num));
            if ((int)$row->federated === 1 && Federation::enabled()) {
                Jobs::dispatch("ap_comment", array("comment_id" => $row->comment_id, "action" => "delete"));
            }
            $done['comments']++;
        }

        $rows = $db->Fetch("SELECT COUNT(*) AS n FROM `wears` WHERE `user` = :a", array(":a" => $user_id));
        $done['wears'] = (int)$rows[0]->n;
        $db->Fetch("DELETE FROM `wears` WHERE `user` = :a", array(":a" => $user_id));

        //likes of posts elsewhere are taken back with Undo; Like::toggle removes a like that exists
        $rows = $db->Fetch("SELECT `kind`, `object_num` FROM `likes` WHERE `user_id` = :a", array(":a" => $user_id));
        foreach ($rows[0] != '00000' ? $rows : array() as $row) {
            if ($row->kind == "remote") {
                try {
                    Like::toggle($user_id, "remote", (string)$row->object_num);
                } catch (Exception $e) {
                    //the post is gone already
                }
            }
            $done['likes']++;
        }
        $db->Fetch("DELETE FROM `likes` WHERE `user_id` = :a", array(":a" => $user_id));
        return $done;
    }

    /**
     * @param int $user_id
     * @param int $by the admin doing it, who can't remove themselves
     * @return void
     * @throws Exception
     */
    public static function account($user_id, $by): void
    {
        $user_id = (int)$user_id;
        if ($user_id === (int)$by) {
            throw new Exception("You can't delete your own account from here");
        }
        $db = new Mysql();
        $row = $db->Fetch("SELECT `user_id`, `username`, `media_id` FROM `users` WHERE `user_id` = :a AND `deleted_at` IS NULL", array(":a" => $user_id));
        if ($row[0] == '00000') {
            throw new Exception("User not found");
        }
        $user = $row[0];

        self::content($user_id);

        if (Federation::enabled()) {
            $actor_url = Federation::actor_url($user->username);
            //everyone elsewhere who follows them: the account is gone (Mastodon drops its posts too)
            $body = json_encode(array(
                "@context" => "https://www.w3.org/ns/activitystreams",
                "id" => $actor_url . "#delete",
                "type" => "Delete",
                "actor" => $actor_url,
                "to" => array("https://www.w3.org/ns/activitystreams#Public"),
                "object" => $actor_url
            ), JSON_UNESCAPED_SLASHES);
            foreach (Federation::follower_inboxes($user_id) as $inbox) {
                Jobs::dispatch("ap_deliver", array("inbox" => $inbox, "user_id" => $user_id, "body" => $body));
            }
            //everyone they follow elsewhere: Undo each follow
            foreach (RemoteFollow::following($user_id) as $followed) {
                RemoteFollow::unfollow($user_id, $followed);
            }
        }

        $params = array(":a" => $user_id);
        foreach (array(
                     "DELETE FROM `follows` WHERE `follower_id` = :a OR `followed_id` = :a",
                     "DELETE FROM `remote_followers` WHERE `user_id` = :a",
                     "DELETE FROM `remote_follows` WHERE `user_id` = :a",
                     "DELETE FROM `notifications` WHERE `user_id` = :a OR `actor_id` = :a",
                     "DELETE FROM `password_resets` WHERE `user_id` = :a"
                 ) as $sql) {
            $db->Fetch($sql, $params);
        }
        if (!empty($user->media_id)) {
            Media::remove($user->media_id);
        }
        //the tombstone: no way to log in, nothing personal left, the username kept
        $db->Fetch("UPDATE `users` SET `email` = :b, `password` = '', `first_name` = NULL, `last_name` = NULL, `media_id` = NULL,
                    `session` = NULL, `csrf` = NULL, `deleted_at` = NOW() WHERE `user_id` = :a",
            array(":a" => $user_id, ":b" => "deleted-" . $user_id . "@deleted.invalid"));
    }
}
