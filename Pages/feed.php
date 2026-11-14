<?php
title_update("feed");
?>
<div class="feed">
    <?php
    $params = null;
    $sql = "SELECT `outfit_num` FROM `outfits` WHERE " . visibility_where("outfits", "user") . " order by timestamp DESC limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    if ($row[0] != '00000') {
        foreach ($row as $outfit) {
            $post = new Post\Outfit($outfit->outfit_num);
            $post->render_outfit();
        }
    } else {
        ?>
        <p class="empty">Nothing to be found</p>
        <?php
    }
    ?>
</div>