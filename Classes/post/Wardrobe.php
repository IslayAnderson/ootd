<?php

namespace Post;

use Mysql;

class Wardrobe
{

    public $wardrobe;

    /**
     * @param $id user_id
     * @param int $limit
     * @param array $filters optional category (category_id), tag, search
     */
    public function __construct($id, $limit = 100, $filters = array())
    {
        $params = array(":a" => $id);
        $where = "";

        if (!empty($filters['category'])) {
            $where .= " AND garments.`category` = :b";
            $params[":b"] = (int)$filters['category'];
        }
        if (!empty($filters['tag'])) {
            $where .= " AND FIND_IN_SET(:c, garments.`tags`)";
            $params[":c"] = tags_normalise($filters['tag']);
        }
        if (!empty($filters['search'])) {
            $where .= " AND (garments.`garment_name` LIKE :d OR garments.`description` LIKE :d OR brands.`brand_name` LIKE :d)";
            $params[":d"] = "%" . addcslashes(trim($filters['search']), "%_\\") . "%";
        }

        $sql = "SELECT garments.*, brands.brand_name, categories.categories AS category_name FROM `garments`
                LEFT JOIN `brands` ON garments.brand = brands.brand_id
                LEFT JOIN `categories` ON garments.category = categories.category_id
                WHERE garments.`user_id` = :a AND " . visibility_where("garments", "user_id") . $where . "
                order by garments.`timestamp` DESC LIMIT " . (int)$limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        $this->wardrobe = array();
        if ($row[0] == '00000') {
            return;
        }
        foreach ($row as $index => $garment) {
            $this->wardrobe[$index]['garment_id'] = $garment->garment_id;
            $this->wardrobe[$index]['garment_name'] = $garment->garment_name;
            $this->wardrobe[$index]['garment_description'] = $garment->description;
            $this->wardrobe[$index]['garment_tags'] = $garment->tags;
            $this->wardrobe[$index]['garment_image'] = media_url($garment->media_id, "thumb");
            $this->wardrobe[$index]['brand'] = $garment->brand_name;
            $this->wardrobe[$index]['category'] = $garment->category_name;
            $this->wardrobe[$index]['timestamp'] = $garment->timestamp;
        }

    }

    /**
     * @param $user_id
     * @return array every tag used on the user's garments
     */
    public static function tags($user_id): array
    {
        $params = array(":a" => $user_id);
        $sql = "SELECT `tags` FROM `garments` WHERE `user_id` = :a AND `tags` != '' AND " . visibility_where("garments", "user_id");

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        $tags = array();
        if ($row[0] != '00000') {
            foreach ($row as $garment) {
                $tags = array_merge($tags, tags_split($garment->tags));
            }
        }
        $tags = array_unique($tags);
        sort($tags);
        return $tags;
    }

    public function render_wardrobe(): void
    {
        foreach ($this->wardrobe as $garment):

            ?>
            <figure>
                <a href="/garment/<?= escape($garment['garment_id']) ?>"
                   title="<?= escape($garment['garment_name']) ?>"
                   dataset-garment-id="<?= escape($garment['garment_id']) ?>"
                >
                    <h3><?= escape(strlen($garment['garment_name']) > 35 ? str_split($garment['garment_name'], 35)[0] . "..." : str_split($garment['garment_name'], 35)[0]) ?></h3>
                    <p><?= escape(strlen((string)$garment['garment_description']) > 80 ? str_split($garment['garment_description'], 80)[0] . "..." : $garment['garment_description']) ?></p>
                    <img loading="lazy"
                         src="<?= $garment['garment_image'] ?>"
                         alt="<?= escape($garment['garment_name']) ?>"
                         class=""
                    >
                </a>
            </figure>
        <?php

        endforeach;

    }

    /**
     * Wardrobe page card, with brand, category and tags
     *
     * @return void
     */
    public function render_wardrobe_detailed(): void
    {
        foreach ($this->wardrobe as $garment):

            ?>
            <figure>
                <a href="/garment/<?= escape($garment['garment_id']) ?>"
                   title="<?= escape($garment['garment_name']) ?>"
                   dataset-garment-id="<?= escape($garment['garment_id']) ?>"
                >
                    <h3>
                        <?php if (!empty($garment['brand'])): ?>
                            <span class="brand"><?= escape($garment['brand']) ?></span>
                        <?php endif; ?>
                        <?= escape($garment['garment_name']) ?>
                    </h3>
                    <img loading="lazy"
                         src="<?= $garment['garment_image'] ?>"
                         alt="<?= escape($garment['garment_name']) ?>"
                         class=""
                    >
                    <div class="category">
                        <?php if (!empty($garment['category'])): ?>
                            <span><?= escape($garment['category']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="tags">
                        <?php foreach (tags_split($garment['garment_tags']) as $tag): ?>
                            <span>#<?= escape($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </a>
            </figure>
        <?php

        endforeach;

    }

}
