<?php

namespace Post;

use Mysql;

class Outfit
{
    public $outfit_id;
    public $timestamp;
    public $user;
    public $description;
    public $image;
    public $garments;

    public function __construct($id)
    {
        $params = array(":a" => $id);
        $sql = "SELECT *, 
          users.image as user_image, 
          outfits.image as outfit_image,
          garments.image as garment_image, 
          garments.tags as garment_tags,
          outfits.description as outfit_description,
          garments.description as garment_description FROM `outfits`
         JOIN `users` ON outfits.user = users.user_id
         JOIN JSON_TABLE(outfits.garments, '$[*]' COLUMNS (garment_id INT PATH '$')) AS GT
         JOIN `garments` ON garments.garment_num = GT.garment_id
         WHERE `outfit_num` = :a;";

        $db = new Mysql();
        $row = $db->Fetch($sql, $params);

        $this->outfit_id = $row[0]->outfit_id;
        $this->timestamp = $row[0]->timestamp;
        $this->user = array(
            "username" => $row[0]->username,
            "image" => image_bin_encode($row[0]->user_image),
        );
        $this->description = $row[0]->outfit_description;
        $this->image = image_bin_encode($row[0]->outfit_image);
        $garments = array();

        foreach ($row as $index => $garment) {
            $garments[$index] = array(
                "garment_id" => $garment->garment_id,
                "garment_name" => $garment->garment_name,
                "description" => $garment->garment_description,
                "tags" => $garment->garment_tags,
                "image" => image_bin_encode($garment->garment_image),
            );
        }
        $this->garments = $garments;

    }

    public function render_outfit($is_profile = false): void
    {
        ?>
        <article dataset-outfit-id="<?= $this->outfit_id ?>">
            <div class="info">
                <time datetime="<?= $this->timestamp ?>"><?= $this->timestamp ?></time>
                <?php if (!$is_profile): ?>
                    <div class="profile">
                        <a href="/user/<?= $this->user['username'] ?>">
                            <span class="username"><?= $this->user['username'] ?></span>
                            <img loading="lazy" src="<?= $this->user['image'] ?>">
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="description">
                <p><?= $this->description ?></p>
            </div>
            <div class="album">
                <figure>
                    <img loading="lazy" src="<?= $this->image ?>" class="primary">
                    <?php foreach ($this->garments as $garment): ?>
                        <a href="/garment/<?= $garment['garment_id'] ?>"
                           title="<?= $garment['garment_name'] ?>"
                           dataset-garment-id="<?= $garment['garment_id'] ?>"
                        >
                            <img loading="lazy"
                                 src="<?= $garment['image'] ?>"
                                 alt="<?= $garment['garment_name'] ?>"
                                 class=""
                            >
                        </a>
                    <?php endforeach; ?>
                </figure>
            </div>
        </article>
        <?php
    }

}