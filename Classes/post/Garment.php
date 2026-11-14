<?php

namespace post;

use Exception;
use Mysql;

class Garment
{
    public $garments;

    public function __construct($user_id = null, $garment_id = null)
    {
        $this->garments = array();
        if (empty($user_id)) {
            return;
        }
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
            $this->garments[$index]['brand'] = $garment->brand;
            $this->garments[$index]['category'] = $garment->category;

        }
    }

    public function render_garment($is_profile = false): void
    {

    }

    /**
     * @param array $request garment_name, description, tags, brand, category, image (binary)
     * @return string public garment_id
     * @throws Exception
     */
    public function create_garment($request = null)
    {
        if (empty($request) || empty($request['user_id']) || trim((string)$request['garment_name']) == "") {
            throw new Exception('Garment name missing');
        }

        $garment_id = md5(id_gen());

        $params = array(
            ":a" => $garment_id,
            ":b" => $request['user_id'],
            ":c" => mb_substr(trim($request['garment_name']), 0, 128),
            ":d" => mb_substr(trim((string)$request['description']), 0, 1024),
            ":e" => tags_normalise($request['tags']),
            ":f" => $request['image'],
            ":g" => brand_lookup($request['brand']),
            ":h" => category_lookup($request['category'])
        );

        $sql = 'INSERT INTO `garments` (`garment_id`, `user_id`, `garment_name`, `description`, `tags`, `image`, `brand`, `category`)
                VALUES (:a, :b, :c, :d, :e, :f, :g, :h)';

        $db = new Mysql();
        $db->Fetch($sql, $params);

        return $garment_id;
    }

    /**
     * Keep only garment_nums that belong to the user
     *
     * @param $user_id
     * @param array $garment_nums
     * @return array
     */
    public static function owned_garments($user_id, $garment_nums): array
    {
        $owned = array();
        foreach ((array)$garment_nums as $garment_num) {
            $params = array(":a" => $user_id, ":b" => (int)$garment_num);
            $sql = "SELECT `garment_num` FROM `garments` WHERE `user_id` = :a AND `garment_num` = :b";
            $db = new Mysql();
            $row = $db->Fetch($sql, $params);
            if ($row[0] != '00000') {
                $owned[] = (int)$row[0]->garment_num;
            }
        }
        return array_values(array_unique($owned));
    }

    public function render_garment_options(): void
    {
        foreach ($this->garments as $garment): ?>
            <option value="<?= escape($garment['garment_num']) ?>"><?= escape($garment['garment_name']) ?></option>
        <?php endforeach;
    }

}