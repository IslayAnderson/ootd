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
                WHERE blogs.`user` = :a" . $filter . "order by blogs.`timestamp` DESC LIMIT " . (int)$limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);
        if ($row[0] == "00000") {
            return;
        }
        foreach ($row as $index => $blog) {
            $this->blogs[$index]['blog_id'] = $blog->blog_id;
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
                    $sql = "SELECT * FROM `outfits` WHERE outfit_num = :a";
                    $db = new Mysql();
                    $row = $db->Fetch($sql, array(":a" => $outfit));
                    if ($row[0] != '00000') {
                        $this->blogs[$index]['blog_outfits'][] = $row[0];
                    }
                }
            }
            if (count($garments) > 0) {
                foreach ($garments as $garment) {
                    $sql = "SELECT * FROM `garments` WHERE garment_num = :a";
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
                image_bin_encode($blog['blog_outfits'][0]->image),
                $blog['blog_title']
            );
        } elseif (count($blog['blog_garments']) > 0) {
            return array(
                image_bin_encode($blog['blog_garments'][0]->image),
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
                    <time datetime="<?= escape($blog['timestamp']) ?>"><?= escape($blog['timestamp']) ?></time>
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
                    <time datetime="<?= escape($blog['timestamp']) ?>"><?= escape($blog['timestamp']) ?></time>
                    <a href="/user/<?= escape($blog['username']) ?>"><?= escape($blog['username']) ?></a>
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
            throw new Exception('Blog title missing');
        }

        //only the author's own outfits can be attached
        $outfits = array();
        foreach ((array)$request['outfits'] as $outfit_num) {
            $sql = "SELECT `outfit_num` FROM `outfits` WHERE `user` = :a AND `outfit_num` = :b";
            $db = new Mysql();
            $row = $db->Fetch($sql, array(":a" => $request['user_id'], ":b" => (int)$outfit_num));
            if ($row[0] != '00000') {
                $outfits[] = (int)$row[0]->outfit_num;
            }
        }

        $blog_id = md5(id_gen());

        $params = array(
            ":a" => $blog_id,
            ":b" => $request['user_id'],
            ":c" => mb_substr(trim($request['title']), 0, 256),
            ":d" => mb_substr(trim((string)$request['excerpt']), 0, 1024),
            ":e" => sanitise_html($request['content']),
            ":f" => json_encode(array_values(array_unique($outfits))),
            ":g" => json_encode(array())
        );

        $sql = 'INSERT INTO `blogs` (`blog_id`, `user`, `title`, `excerpt`, `content`, `outfits`, `garments`)
                VALUES (:a, :b, :c, :d, :e, :f, :g)';

        $db = new Mysql();
        $db->Fetch($sql, $params);

        return $blog_id;
    }

}
