<?php

namespace post;

use Mysql;

class Garment
{
    public $garments;

    public function __construct($user_id, $garment_id = null)
    {
        $this->garments = array();
        $params = array(":a" => $user_id);
        $filter = " ";
        if ($garment_id) {
            is_int($garment_id) ? $id_type = "garment_num" : $id_type = "garment_id";
            $filter = " AND `" . $id_type . "` = :b ";
            $params[":b"] = $garment_id;
        }
        $sql = "SELECT * FROM `garments` WHERE `user_id` = :a" . $filter . "order by `garment_name` ASC ";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        if ($row[0] == '00000') {
            return;
        }

        foreach ($row as $index => $garment) {
            $this->garments[$index]['garment_id'] = $garment->garment_id;
            $this->garments[$index]['garment_name'] = $garment->garment_name;
            $this->garments[$index]['garment_description'] = $garment->description;
            $this->garments[$index]['garment_tags'] = $garment->tags;
            $this->garments[$index]['garment_image'] = image_bin_encode($garment->image);
            $this->garments[$index]['garment_num'] = $garment->garment_num;
            $this->garments[$index]['user'] = $garment->user_id;
            $this->garments[$index]['timestamp'] = $garment->timestamp;

        }
    }

    public function render_garment($is_profile = false): void
    {

    }

    public function render_garment_options(): void
    {
        foreach ($this->garments as $garment): ?>
            <option value="<?= escape($garment['garment_num']) ?>"><?= escape($garment['garment_name']) ?></option>
        <?php endforeach;
    }

}