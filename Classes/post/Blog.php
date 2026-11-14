<?php

namespace Post;

use Exception;
use Mysql;

class Blog
{

    public $blogs;

    public function __construct($user_id = null, $blog_id = null, $limit = 100)
    {
        $this->blogs = array();
        if (empty($user_id)) {
            return;
        }
        $params = array(":a" => $user_id);
        $filter = " ";
        if ($blog_id) {
            is_int($blog_id) ? $id_type = "blog_num" : $id_type = "blog_id";
            $filter = " AND blogs.`" . $id_type . "` = :b ";
            $params[":b"] = $blog_id;
        }
        $sql = "SELECT blogs.*, users.username FROM `blogs`
                JOIN `users` ON blogs.user = users.user_id
                WHERE blogs.`user` = :a AND " . visibility_where("blogs", "user") . $filter . "order by blogs.`timestamp` DESC LIMIT " . (int)$limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);
        if ($row[0] == "00000") {
            return;
        }
        foreach ($row as $index => $blog) {
            $this->blogs[$index]['blog_id'] = $blog->blog_id;
            $this->blogs[$index]['blog_num'] = $blog->blog_num;
            $this->blogs[$index]['visibility'] = $blog->visibility;
            $this->blogs[$index]['outfit_nums'] = json_decode((string)$blog->outfits) ?: array();
            $this->blogs[$index]['user'] = $blog->user;
            $this->blogs[$index]['username'] = $blog->username;
            $this->blogs[$index]['blog_title'] = $blog->title;
            $this->blogs[$index]['blog_excerpt'] = $blog->excerpt;
            $this->blogs[$index]['blog_content'] = $blog->content;
            $this->blogs[$index]['timestamp'] = $blog->timestamp;
            $this->blogs[$index]['blog_outfits'] = array();
            $this->blogs[$index]['blog_garments'] = array();
            $outfits = json_decode((string)$blog->outfits) ?: array();
            $garments = json_decode((string)$blog->garments) ?: array();
            if (count($outfits) > 0) {
                foreach ($outfits as $outfit) {
                    $sql = "SELECT * FROM `outfits` WHERE outfit_num = :a AND " . visibility_where("outfits", "user");
                    $db = new Mysql();
                    $row = $db->Fetch($sql, array(":a" => $outfit));
                    if ($row[0] != '00000') {
                        $this->blogs[$index]['blog_outfits'][] = $row[0];
                    }
                }
            }
            if (count($garments) > 0) {
                foreach ($garments as $garment) {
                    $sql = "SELECT * FROM `garments` WHERE garment_num = :a AND " . visibility_where("garments", "user_id");
                    $db = new Mysql();
                    $row = $db->Fetch($sql, array(":a" => $garment));
                    if ($row[0] != '00000') {
                        $this->blogs[$index]['blog_garments'][] = $row[0];
                    }
                }
            }
        }
    }

    /**
     * @param array $blog
     * @return array|null [src, alt] of the first linked outfit or garment
     */
    private function blog_image($blog)
    {
        if (count($blog['blog_outfits']) > 0) {
            return array(
                media_url($blog['blog_outfits'][0]->media_id, "thumb"),
                $blog['blog_title']
            );
        } elseif (count($blog['blog_garments']) > 0) {
            return array(
                media_url($blog['blog_garments'][0]->media_id, "thumb"),
                $blog['blog_garments'][0]->garment_name
            );
        }
        return null;
    }

    public function render_blogs_profile(): void
    {
        foreach ($this->blogs as $blog):
            $image = $this->blog_image($blog);
            ?>
            <article dataset-blog-id="<?= escape($blog['blog_id']) ?>">
                <?php if ($image): ?>
                    <img loading="lazy" src="<?= $image[0] ?>" alt="<?= escape($image[1]) ?>" width="33%">
                <?php endif; ?>
                <div>
                    <a title="<?= escape($blog['blog_title']) ?>"
                       href="/user/<?= escape($blog['username']) ?>/blog/<?= escape($blog['blog_id']) ?>">
                        <h3><?= escape($blog['blog_title']) ?></h3>
                    </a>
                    <time datetime="<?= escape($blog['timestamp']) ?>"><?= escape(date_display($blog['timestamp'])) ?></time>
                    <p><?= escape($blog['blog_excerpt']) ?></p>
                </div>
            </article>
        <?php

        endforeach;

    }

    /**
     * Full blog post, content is sanitised when it is saved
     *
     * @return void
     */
    public function render_blog(): void
    {
        foreach ($this->blogs as $blog):
            ?>
            <article class="blog-post" dataset-blog-id="<?= escape($blog['blog_id']) ?>">
                <h1><?= escape($blog['blog_title']) ?></h1>
                <div class="info">
                    <time datetime="<?= escape($blog['timestamp']) ?>"><?= escape(date_display($blog['timestamp'])) ?></time>
                    <a href="/user/<?= escape($blog['username']) ?>"><?= escape($blog['username']) ?></a>
                    <?php if ($blog['user'] == ($_SESSION['user_id'] ?? null)): ?>
                        <span class="visibility"><?= escape(visibility_options()[$blog['visibility']] ?? "") ?></span>
                    <?php endif; ?>
                </div>
                <p class="excerpt"><?= escape($blog['blog_excerpt']) ?></p>
                <div class="content">
                    <?= $blog['blog_content'] ?>
                </div>
            </article>
            <?php foreach ($blog['blog_outfits'] as $outfit):
                $post = new Outfit($outfit->outfit_num);
                $post->render_outfit();
            endforeach;
        endforeach;
    }

    /**
     * @param array $request user_id, title, excerpt, content (html), outfits (outfit_nums)
     * @return string public blog_id
     * @throws Exception
     */
    public function create_blog($request = null)
    {
        if (empty($request) || empty($request['user_id']) || trim((string)$request['title']) == "") {
            throw new Exception('Title missing');
        }

        //only the author's own outfits can be attached
        $outfits = self::owned_outfits($request['user_id'], $request['outfits'] ?? array());

        $blog_id = md5(id_gen());

        $params = array(
            ":a" => $blog_id,
            ":b" => $request['user_id'],
            ":c" => mb_substr(trim($request['title']), 0, 256),
            ":d" => mb_substr(trim((string)$request['excerpt']), 0, 1024),
            ":e" => sanitise_html($request['content']),
            ":f" => json_encode(array_values(array_unique($outfits))),
            ":g" => json_encode(array()),
            ":h" => visibility_normalise($request['visibility'] ?? null)
        );

        $sql = 'INSERT INTO `blogs` (`blog_id`, `user`, `title`, `excerpt`, `content`, `outfits`, `garments`, `visibility`)
                VALUES (:a, :b, :c, :d, :e, :f, :g, :h)';

        $db = new Mysql();
        $db->Fetch($sql, $params);

        \Federation::changed("blog", $blog_id, "create");

        return $blog_id;
    }

    /**
     * @param $user_id
     * @param $outfit_nums
     * @return array the outfit_nums that belong to the user
     */
    private static function owned_outfits($user_id, $outfit_nums): array
    {
        $outfits = array();
        foreach ((array)$outfit_nums as $outfit_num) {
            $sql = "SELECT `outfit_num` FROM `outfits` WHERE `user` = :a AND `outfit_num` = :b AND `deleted_at` IS NULL";
            $db = new Mysql();
            $row = $db->Fetch($sql, array(":a" => $user_id, ":b" => (int)$outfit_num));
            if ($row[0] != '00000') {
                $outfits[] = (int)$row[0]->outfit_num;
            }
        }
        return array_values(array_unique($outfits));
    }

    /**
     * @param $user_id
     * @param $blog_id public blog_id
     * @return int|null blog_num when the user owns that blog and it isn't deleted
     */
    public static function owned_num($user_id, $blog_id): ?int
    {
        $sql = "SELECT `blog_num` FROM `blogs` WHERE `blog_id` = :a AND `user` = :b AND `deleted_at` IS NULL";
        $db = new Mysql();
        $row = $db->Fetch($sql, array(":a" => (string)$blog_id, ":b" => (int)$user_id));
        return $row[0] != '00000' ? (int)$row[0]->blog_num : null;
    }

    /**
     * @param array $request user_id, blog_id, title, excerpt, content (html), outfits (outfit_nums), visibility
     * @return void
     * @throws Exception
     */
    public function update_blog($request = null): void
    {
        $blog_num = self::owned_num($request['user_id'] ?? 0, $request['blog_id'] ?? "");
        if (empty($blog_num)) {
            throw new Exception('Journal entry not found');
        }
        if (trim((string)($request['title'] ?? "")) == "") {
            throw new Exception('Title missing');
        }

        $params = array(
            ":a" => $blog_num,
            ":b" => mb_substr(trim($request['title']), 0, 256),
            ":c" => mb_substr(trim((string)($request['excerpt'] ?? "")), 0, 1024),
            ":d" => sanitise_html($request['content'] ?? ""),
            ":e" => json_encode(self::owned_outfits($request['user_id'], $request['outfits'] ?? array())),
            ":f" => visibility_normalise($request['visibility'] ?? null)
        );
        $sql = 'UPDATE `blogs` SET `title` = :b, `excerpt` = :c, `content` = :d, `outfits` = :e, `visibility` = :f, `updated_at` = NOW()
                WHERE `blog_num` = :a';

        $db = new Mysql();
        $was = $db->Fetch("SELECT `visibility` FROM `blogs` WHERE `blog_num` = :a", array(":a" => $blog_num));
        $db->Fetch($sql, $params);
        \Federation::changed("blog", $request['blog_id'], "update", $was[0]->visibility ?? null);
    }

    /**
     * @param $user_id
     * @param $blog_id
     * @return void
     * @throws Exception
     */
    public static function delete_blog($user_id, $blog_id): void
    {
        $blog_num = self::owned_num($user_id, $blog_id);
        if (empty($blog_num)) {
            throw new Exception('Journal entry not found');
        }

        $sql = 'UPDATE `blogs` SET `deleted_at` = NOW() WHERE `blog_num` = :a';
        $db = new Mysql();
        $was = $db->Fetch("SELECT `visibility` FROM `blogs` WHERE `blog_num` = :a", array(":a" => $blog_num));
        $db->Fetch($sql, array(":a" => $blog_num));
        \Federation::changed("blog", $blog_id, "delete", $was[0]->visibility ?? null);
    }

}
