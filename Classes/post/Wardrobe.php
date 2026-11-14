<?php

namespace Post;

use Mysql;

class Wardrobe
{

    public $wardrobe;

    public function __construct($id, $limit = 100)
    {
        $params = array(":a" => $id);
        $sql = "SELECT * FROM `garments` WHERE `user_id` = :a order by `timestamp` DESC LIMIT " . (int)$limit;

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
            $this->wardrobe[$index]['garment_image'] = image_bin_encode($garment->image);
        }

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

}