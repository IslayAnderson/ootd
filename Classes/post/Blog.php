<?php

namespace Post;

use Mysql;

class Blog
{

    public $blogs;

    public function __construct($user_id, $blog_id = null, $limit = 100)
    {
        $params = array(":a" => $user_id);
        is_int($blog_id) ? $id_type = "blog_num" : $id_type = "blog_id";
        $blog = $blog_id ? " `" . $id_type . "` = " . $blog_id . " " : " ";
        $sql = "SELECT * FROM `blogs` WHERE `user` = :a" . $blog . "order by `timestamp` LIMIT " . $limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);
        if ($row[0] == "00000") {
            return false;
        }
        $this->blogs = array();
        foreach ($row as $index => $blog) {
            $this->blogs[$index]['blog_id'] = $blog->blog_id;
            $this->blogs[$index]['user'] = $blog->user;
            $this->blogs[$index]['blog_title'] = $blog->title;
            $this->blogs[$index]['blog_excerpt'] = $blog->excerpt;
            $this->blogs[$index]['blog_content'] = $blog->content;
            $this->blogs[$index]['blog_outfits'] = array();
            $this->blogs[$index]['blog_garment'] = array();
            $outfits = json_decode($blog->outfits);
            $garments = json_decode($blog->garments);
            if (count($outfits) > 0) {
                foreach ($outfits as $i => $garment) {
                    $sql = "SELECT * FROM `outfits` WHERE outfit_num = :a";
                    $db = new Mysql();
                    $row = $db->Fetch($sql, array(":a" => $garment));
                    $this->blogs[$index]['blog_outfits'][$i] = $row[0];
                }
            }
            if (count($garments) > 0) {
                foreach ($garments as $ii => $garment) {
                    $sql = "SELECT * FROM `garments` WHERE garment_num = :a";
                    $db = new Mysql();
                    $row = $db->Fetch($sql, array(":a" => $garment));
                    $this->blogs[$index]['blog_garments'][$ii] = $row[0];
                }
            }
        }
    }

    public function render_blogs_profile(): void
    {
        foreach ($this->blogs as $blog):
            if (count($blog['blog_outfits']) > 0) {
                $image = array(
                    image_bin_encode($blog['blog_outfits'][0]->image),
                    $blog['blog_outfits'][0]->outfit_title
                );
            } elseif (count($blog['blog_garments']) > 0) {
                $image = array(
                    image_bin_encode($blog['blog_garments'][0]->image),
                    $blog['blog_garments'][0]->garment_name
                );
            }
            ?>
            <article dataset-outfit-id="<?= $blog['blog_id'] ?>" style="
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: center;
            ">
                <img src="<?= $image[0] ?>" alt="<?= $image[1] ?>" width="33%">
                <div>
                    <a title="<?= $blog['blog_title'] ?>"
                       href="/user/<?= $blog['user'] ?>/blog/<?= $blog['blog_id'] ?>">
                        <h3><?= $blog['blog_title'] ?></h3>
                    </a>
                    <p><?= $blog['blog_excerpt'] ?></p>
                </div>
            </article>
        <?php

        endforeach;

    }

}