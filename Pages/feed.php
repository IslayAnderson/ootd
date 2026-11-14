<?php
title_update("feed");
?>
<div class="feed">
    <?php
    $params = null;
    $sql = "SELECT `outfit_num` FROM `outfits` order by timestamp DESC limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    if ($row[0] != '00000') {
        foreach ($row as $outfit) {
            $post = new Post\Outfit($outfit->outfit_num);
            $post->render_outfit();
        }
    } else {
        ?>
        <p>Nothing to be found</p>
        <?php
    }
    ?>
</div>