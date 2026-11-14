<?php

namespace Post;

use Exception;
use Mysql;

class Outfit
{
    public $outfit_num;
    public $outfit_id;
    public $timestamp;
    public $user;
    public $description;
    public $image;
    public $image_alt;
    public $media_id;
    public $garments;
    public $garment_nums;
    public $tags;
    public $visibility;

    public function __construct($id = null)
    {
        if (empty($id)) {
            return;
        }

        $params = array(":a" => $id);
        $sql = "SELECT *, 
          users.media_id as user_media,
          outfits.media_id as outfit_media,
          outfit_media.alt_text as outfit_alt,
          garments.media_id as garment_media, 
          garments.tags as garment_tags,
          outfits.description as outfit_description,
          outfits.timestamp as outfit_timestamp,
          outfits.tags as outfit_tags,
          outfits.visibility as outfit_visibility,
          outfits.garments as outfit_garments,
          garments.visibility as garment_visibility,
          garments.description as garment_description FROM `outfits`
         JOIN `users` ON outfits.user = users.user_id
         LEFT JOIN `media` AS outfit_media ON outfit_media.media_id = outfits.media_id
         LEFT JOIN JSON_TABLE(COALESCE(outfits.garments, '[]'), '$[*]' COLUMNS (garment_ref INT PATH '$')) AS GT ON TRUE
         LEFT JOIN `garments` ON garments.garment_num = GT.garment_ref AND garments.deleted_at IS NULL
         WHERE `outfit_num` = :a AND outfits.deleted_at IS NULL;";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        if ($row[0] == '00000') {
            return;
        }

        $this->outfit_num = $row[0]->outfit_num;
        $this->outfit_id = $row[0]->outfit_id;
        $this->timestamp = $row[0]->outfit_timestamp;
        $this->user = array(
            "user_id" => $row[0]->user,
            "username" => $row[0]->username,
            "image" => media_url($row[0]->user_media, "thumb", "/Assets/images/avi.jpg"),
        );
        $this->description = $row[0]->outfit_description;
        $this->tags = $row[0]->outfit_tags;
        $this->visibility = $row[0]->outfit_visibility;
        $this->garment_nums = json_decode((string)$row[0]->outfit_garments) ?: array();
        $this->media_id = $row[0]->outfit_media;
        $this->image = media_url($row[0]->outfit_media);
        $this->image_alt = (string)$row[0]->outfit_alt;
        $garments = array();

        foreach ($row as $index => $garment) {
            //a public outfit can still contain garments the visitor isn't allowed to see
            if (empty($garment->garment_num) || !can_view($garment->user_id, $garment->garment_visibility)) {
                continue;
            }
            $garments[$index] = array(
                "garment_id" => $garment->garment_id,
                "garment_name" => $garment->garment_name,
                "description" => $garment->garment_description,
                "tags" => $garment->garment_tags,
                "image" => media_url($garment->garment_media, "thumb"),
            );
        }
        $this->garments = $garments;

    }

    /**
     * @param string $outfit_id public outfit_id
     * @return int|null outfit_num, null when it doesn't exist or was deleted
     */
    public static function num_from_id($outfit_id): ?int
    {
        $sql = "SELECT `outfit_num` FROM `outfits` WHERE `outfit_id` = :a AND `deleted_at` IS NULL";
        $db = new Mysql();
        $row = $db->Fetch($sql, array(":a" => (string)$outfit_id));
        return $row[0] != '00000' ? (int)$row[0]->outfit_num : null;
    }

    /**
     * @return bool can the current visitor see this outfit
     */
    public function viewable(): bool
    {
        return !empty($this->outfit_num) && can_view($this->user['user_id'], $this->visibility);
    }

    /**
     * @return bool is the signed in user the owner
     */
    public function owned(): bool
    {
        return !empty($this->outfit_num) && $this->user['user_id'] == ($_SESSION['user_id'] ?? null);
    }

    /**
     * @param array $request user_id, outfit_id, description, tags, garments (garment_nums), visibility
     * @return void
     * @throws Exception
     */
    public function update_outfit($request = null): void
    {
        $outfit_num = self::num_from_id($request['outfit_id'] ?? "");
        $outfit = new Outfit($outfit_num);
        if (empty($request['user_id']) || !$outfit->owned()) {
            if (!empty($request['media_id'])) {
                \Media::remove($request['media_id']);
            }
            throw new Exception('Outfit not found');
        }

        $params = array(
            ":a" => $outfit_num,
            ":b" => json_encode(Garment::owned_garments($request['user_id'], $request['garments'] ?? array())),
            ":c" => mb_substr(trim((string)($request['description'] ?? "")), 0, 1024),
            ":d" => tags_normalise($request['tags'] ?? ""),
            ":e" => visibility_normalise($request['visibility'] ?? null)
        );
        $sql = 'UPDATE `outfits` SET `garments` = :b, `description` = :c, `tags` = :d, `visibility` = :e, `updated_at` = NOW()
                WHERE `outfit_num` = :a';

        $db = new Mysql();
        $db->Fetch($sql, $params);

        if (!empty($request['media_id'])) {
            //a new photo replaces the old one
            $db->Fetch('UPDATE `outfits` SET `media_id` = :b WHERE `outfit_num` = :a', array(":a" => $outfit_num, ":b" => $request['media_id']));
            if (!empty($outfit->media_id)) {
                \Media::remove($outfit->media_id);
            }
        } elseif (isset($request['alt_text']) && !empty($outfit->media_id)) {
            \Media::set_alt($outfit->media_id, $request['user_id'], $request['alt_text']);
        }
        \Federation::changed("outfit", $outfit->outfit_id, "update", $outfit->visibility);
    }

    /**
     * Soft delete, the row is kept with deleted_at set so the deletion can be sent to other servers later
     *
     * @param $user_id
     * @param $outfit_id
     * @return void
     * @throws Exception
     */
    public static function delete_outfit($user_id, $outfit_id): void
    {
        $outfit = new Outfit(self::num_from_id($outfit_id));
        if (empty($user_id) || !$outfit->owned()) {
            throw new Exception('Outfit not found');
        }

        $sql = 'UPDATE `outfits` SET `deleted_at` = NOW() WHERE `outfit_num` = :a';
        $db = new Mysql();
        $db->Fetch($sql, array(":a" => $outfit->outfit_num));
        \Federation::changed("outfit", $outfit->outfit_id, "delete", $outfit->visibility);
    }

    /**
     * @param array $request user_id, description, tags, garments (garment_nums), image (binary)
     * @return string public outfit_id
     * @throws Exception
     */
    public function create_outfit($request = null)
    {
        if (empty($request) || empty($request['user_id']) || empty($request['media_id'])) {
            throw new Exception('Outfit photo missing');
        }

        $outfit_id = md5(id_gen());

        $params = array(
            ":a" => $outfit_id,
            ":b" => $request['user_id'],
            ":c" => json_encode(Garment::owned_garments($request['user_id'], $request['garments'])),
            ":d" => mb_substr(trim((string)$request['description']), 0, 1024),
            ":e" => tags_normalise($request['tags']),
            ":f" => $request['media_id'],
            ":g" => visibility_normalise($request['visibility'] ?? null)
        );

        $sql = 'INSERT INTO `outfits` (`outfit_id`, `user`, `garments`, `description`, `tags`, `media_id`, `visibility`)
                VALUES (:a, :b, :c, :d, :e, :f, :g)';

        $db = new Mysql();
        $db->Fetch($sql, $params);
        \Federation::changed("outfit", $outfit_id, "create");

        return $outfit_id;
    }

    public function render_outfit($is_profile = false): void
    {
        if (!$this->viewable()) {
            return;
        }
        ?>
        <article class="outfit" dataset-outfit-id="<?= escape($this->outfit_id) ?>">
            <div class="info">
                <a href="/outfit/<?= escape($this->outfit_id) ?>" class="permalink">
                    <time datetime="<?= escape($this->timestamp) ?>"><?= escape(date_display($this->timestamp)) ?></time>
                </a>
                <?php if ($this->owned()): ?>
                    <span class="visibility"><?= escape(visibility_options()[$this->visibility] ?? "") ?></span>
                <?php endif; ?>
                <?php if (!$is_profile): ?>
                    <div class="profile">
                        <a href="/user/<?= escape($this->user['username']) ?>">
                            <img loading="lazy" src="<?= $this->user['image'] ?>" alt="">
                            <span class="username"><?= escape($this->user['username']) ?></span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="album">
                <figure>
                    <img loading="lazy" src="<?= $this->image ?>" class="primary"
                         alt="<?= escape($this->image_alt ?: ($this->description ?: "Outfit by " . $this->user['username'])) ?>">
                </figure>
            </div>
            <div class="description">
                <?php if (!empty($this->description)): ?>
                    <p><?= escape($this->description) ?></p>
                <?php endif; ?>
                <?php if ($this->user['user_id'] == ($_SESSION['user_id'] ?? null)): ?>
                    <form action="/API/wear/create.php" method="post" class="wear">
                        <?php CSRF_input(); ?>
                        <input type="hidden" name="outfit_num" value="<?= escape($this->outfit_num) ?>">
                        <button type="submit">Wore this today</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (count($this->garments) > 0): ?>
                <ul class="garments">
                    <?php foreach ($this->garments as $garment): ?>
                        <li>
                            <a href="/garment/<?= escape($garment['garment_id']) ?>"
                               title="<?= escape($garment['garment_name']) ?>"
                               dataset-garment-id="<?= escape($garment['garment_id']) ?>"
                            >
                                <img loading="lazy"
                                     src="<?= $garment['image'] ?>"
                                     alt="<?= escape($garment['garment_name']) ?>"
                                     class=""
                                >
                                <span><?= escape($garment['garment_name']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php \Like::render_bar("outfit", (int)$this->outfit_num, (string)$this->outfit_id, "/outfit/" . rawurlencode($this->outfit_id)); ?>
        </article>
        <?php
    }

}