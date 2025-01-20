<?php

namespace Post;

use Mysql;

class Wardrobe
{

    public $wardrobe;

    public function __construct($id, $limit = 100)
    {
        $params = array(":a" => $id);
        $sql = "SELECT * FROM `garments` WHERE `user_id` = :a order by `timestamp` LIMIT " . $limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        $this->wardrobe = array();
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
            <figure style="width:33%; float:left;">
                <a href="/garment/<?= $garment['garment_id'] ?>"
                   title="<?= $garment['garment_name'] ?>"
                   dataset-garment-id="<?= $garment['garment_id'] ?>"
                >
                    <h3><?= $garment['garment_name'] ?></h3>
                    <p><?= $garment['garment_description'] ?></p>
                    <img loading="lazy"
                         src="<?= $garment['garment_image'] ?>"
                         alt="<?= $garment['garment_name'] ?>"
                         class=""
                    >
                </a>
            </figure>
        <?php

        endforeach;

    }

}