<div class="feed">
    <?php
    $params = null;
    $sql = "SELECT `outfit_num` FROM `outfits` order by timestamp limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    foreach ($row as $outfit) {
        $post = new Post\Outfit($outfit->outfit_num);
        $post->render_outfit();
    }
    ?>
</div>