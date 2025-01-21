<?php

namespace Post;

use Mysql;

class Wardrobe
{

    public $wardrobe;

    public function __construct($id, $limit = 100)
    {
        $params = array(":a" => $id);
        $sql = "SELECT * FROM `garments` WHERE `user_id` = :a order by `timestamp` DESC LIMIT " . $limit;

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
            <figure>
                <a href="/garment/<?= $garment['garment_id'] ?>"
                   title="<?= $garment['garment_name'] ?>"
                   dataset-garment-id="<?= $garment['garment_id'] ?>"
                >
                    <h3><?= strlen($garment['garment_name']) > 35 ? str_split($garment['garment_name'], 35)[0] . "..." : str_split($garment['garment_name'], 35)[0] ?></h3>
                    <p><?= strlen($garment['garment_description']) > 80 ? str_split($garment['garment_description'], 80)[0] . "..." : str_split($garment['garment_description'], 80)[0] ?></p>
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