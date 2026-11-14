<?php

namespace post;

use Exception;
use Mysql;

class Garment
{
    public $garments;

    /**
     * @param $user_id all of a user's garments, or null with a $garment_id to load any one garment
     * @param $garment_id garment_num (int) or public garment_id (string)
     */
    public function __construct($user_id = null, $garment_id = null)
    {
        $this->garments = array();
        if (empty($user_id) && empty($garment_id)) {
            return;
        }
        $params = array();
        $where = array();
        if ($user_id) {
            $where[] = "garments.`user_id` = :a";
            $params[":a"] = $user_id;
        }
        if ($garment_id) {
            is_int($garment_id) ? $id_type = "garment_num" : $id_type = "garment_id";
            $where[] = "garments.`" . $id_type . "` = :b";
            $params[":b"] = $garment_id;
        }
        $where[] = visibility_where("garments", "user_id");
        $sql = "SELECT garments.*, brands.brand_name, categories.categories AS category_name, users.username, media.alt_text FROM `garments`
                LEFT JOIN `media` ON media.media_id = garments.media_id
                LEFT JOIN `brands` ON garments.brand = brands.brand_id
                LEFT JOIN `categories` ON garments.category = categories.category_id
                JOIN `users` ON garments.user_id = users.user_id
                WHERE " . implode(" AND ", $where) . " order by `garment_name` ASC ";

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
            $this->garments[$index]['garment_image'] = media_url($garment->media_id);
            $this->garments[$index]['media_id'] = $garment->media_id;
            $this->garments[$index]['alt_text'] = (string)$garment->alt_text;
            $this->garments[$index]['garment_num'] = $garment->garment_num;
            $this->garments[$index]['user'] = $garment->user_id;
            $this->garments[$index]['timestamp'] = $garment->timestamp;
            $this->garments[$index]['brand'] = $garment->brand;
            $this->garments[$index]['category'] = $garment->category;
            $this->garments[$index]['brand_name'] = $garment->brand_name;
            $this->garments[$index]['category_name'] = $garment->category_name;
            $this->garments[$index]['username'] = $garment->username;
            $this->garments[$index]['visibility'] = $garment->visibility;

        }
    }

    public function render_garment($is_profile = false): void
    {
        foreach ($this->garments as $garment):
            ?>
            <article class="garment" dataset-garment-id="<?= escape($garment['garment_id']) ?>">
                <div class="description">
                    <h1>
                        <?php if (!empty($garment['brand_name'])): ?>
                            <span class="brand"><?= escape($garment['brand_name']) ?></span>
                        <?php endif; ?>
                        <?= escape($garment['garment_name']) ?>
                    </h1>
                    <p><?= text_html($garment['garment_description']) ?></p>
                </div>
                <figure>
                    <img src="<?= $garment['garment_image'] ?>" alt="<?= escape($garment['alt_text'] ?: $garment['garment_name']) ?>">
                </figure>
                <div class="info">
                    <span>Added <time datetime="<?= escape($garment['timestamp']) ?>"><?= escape(date_display($garment['timestamp'])) ?></time></span>
                    <?php if ($garment['user'] == ($_SESSION['user_id'] ?? null)): ?>
                        <?php //wears are private, only the owner sees them ?>
                        <?php $last_worn = \Insights::last_worn($garment['garment_num']); ?>
                        <span>Last worn <?php if ($last_worn): ?><time datetime="<?= escape($last_worn) ?>"><?= escape(date_display($last_worn)) ?></time><?php else: ?>never<?php endif; ?></span>
                        <span class="visibility"><?= escape(visibility_options()[$garment['visibility']] ?? "") ?></span>
                    <?php endif; ?>
                    <?php if (!$is_profile): ?>
                        <span>By <a href="/user/<?= escape($garment['username']) ?>"><?= escape($garment['username']) ?></a></span>
                    <?php endif; ?>
                </div>
                <div class="category">
                    <?php if (!empty($garment['category_name'])): ?>
                        <span><?= escape($garment['category_name']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="tags">
                    <?php foreach (tags_split($garment['garment_tags']) as $tag): ?>
                        <span>#<?= escape($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if ($garment['user'] == ($_SESSION['user_id'] ?? null)): ?>
                    <form action="/API/wear/create.php" method="post" class="wear">
                        <?php CSRF_input(); ?>
                        <input type="hidden" name="garment_num" value="<?= escape($garment['garment_num']) ?>">
                        <button type="submit">Wore this today</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php
        endforeach;
    }

    /**
     * @param $garment_num
     * @param int $limit
     * @return array outfit_nums of outfits that include the garment, newest first
     */
    public static function outfits_with($garment_num, $limit = 30): array
    {
        $params = array(":a" => json_encode((int)$garment_num));
        $sql = "SELECT `outfit_num` FROM `outfits` WHERE JSON_CONTAINS(`garments`, :a) AND " . visibility_where("outfits", "user") . "
                order by `timestamp` DESC LIMIT " . (int)$limit;

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        $outfits = array();
        if ($row[0] != '00000') {
            foreach ($row as $outfit) {
                $outfits[] = (int)$outfit->outfit_num;
            }
        }
        return $outfits;
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
            ":f" => $request['media_id'] ?? null,
            ":g" => brand_lookup($request['brand']),
            ":h" => category_lookup($request['category']),
            ":i" => visibility_normalise($request['visibility'] ?? null)
        );

        $sql = 'INSERT INTO `garments` (`garment_id`, `user_id`, `garment_name`, `description`, `tags`, `media_id`, `brand`, `category`, `visibility`)
                VALUES (:a, :b, :c, :d, :e, :f, :g, :h, :i)';

        $db = new Mysql();
        $db->Fetch($sql, $params);

        return $garment_id;
    }

    /**
     * @param $user_id
     * @param $garment_id public garment_id
     * @return int|null garment_num when the user owns that garment and it isn't deleted
     */
    public static function owned_num($user_id, $garment_id): ?int
    {
        $sql = "SELECT `garment_num` FROM `garments` WHERE `garment_id` = :a AND `user_id` = :b AND `deleted_at` IS NULL";
        $db = new Mysql();
        $row = $db->Fetch($sql, array(":a" => (string)$garment_id, ":b" => (int)$user_id));
        return $row[0] != '00000' ? (int)$row[0]->garment_num : null;
    }

    /**
     * @param array $request user_id, garment_id, garment_name, description, tags, brand, category, visibility
     * @return void
     * @throws Exception
     */
    public function update_garment($request = null): void
    {
        $garment_num = self::owned_num($request['user_id'] ?? 0, $request['garment_id'] ?? "");
        if (empty($garment_num) || trim((string)($request['garment_name'] ?? "")) == "") {
            //don't leave an uploaded replacement photo behind
            if (!empty($request['media_id'])) {
                \Media::remove($request['media_id']);
            }
            throw new Exception(empty($garment_num) ? 'Garment not found' : 'Garment name missing');
        }

        $params = array(
            ":a" => $garment_num,
            ":b" => mb_substr(trim($request['garment_name']), 0, 128),
            ":c" => mb_substr(trim((string)($request['description'] ?? "")), 0, 1024),
            ":d" => tags_normalise($request['tags'] ?? ""),
            ":e" => brand_lookup($request['brand'] ?? ""),
            ":f" => category_lookup($request['category'] ?? ""),
            ":g" => visibility_normalise($request['visibility'] ?? null)
        );
        $sql = 'UPDATE `garments` SET `garment_name` = :b, `description` = :c, `tags` = :d, `brand` = :e, `category` = :f,
                `visibility` = :g, `updated_at` = NOW() WHERE `garment_num` = :a';

        $db = new Mysql();
        $db->Fetch($sql, $params);

        $old_media = $db->Fetch('SELECT `media_id` FROM `garments` WHERE `garment_num` = :a', array(":a" => $garment_num))[0]->media_id ?? null;
        if (!empty($request['media_id'])) {
            //a new photo replaces the old one
            $db->Fetch('UPDATE `garments` SET `media_id` = :b WHERE `garment_num` = :a', array(":a" => $garment_num, ":b" => $request['media_id']));
            if (!empty($old_media)) {
                \Media::remove($old_media);
            }
        } elseif (isset($request['alt_text']) && !empty($old_media)) {
            \Media::set_alt($old_media, $request['user_id'], $request['alt_text']);
        }
    }

    /**
     * Soft delete, outfits that include the garment keep working and just stop showing it
     *
     * @param $user_id
     * @param $garment_id
     * @return void
     * @throws Exception
     */
    public static function delete_garment($user_id, $garment_id): void
    {
        $garment_num = self::owned_num($user_id, $garment_id);
        if (empty($garment_num)) {
            throw new Exception('Garment not found');
        }

        $sql = 'UPDATE `garments` SET `deleted_at` = NOW() WHERE `garment_num` = :a';
        $db = new Mysql();
        $db->Fetch($sql, array(":a" => $garment_num));
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
            $sql = "SELECT `garment_num` FROM `garments` WHERE `user_id` = :a AND `garment_num` = :b AND `deleted_at` IS NULL";
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