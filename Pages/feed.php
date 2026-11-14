<?php
title_update("feed");
//Following shows people you follow (and you); Local is everyone on this instance you can see
$has_follows = Follow::follows_anyone($_SESSION['user_id']);
$tab = ($_GET['tab'] ?? ($has_follows ? "following" : "local")) == "local" ? "local" : "following";
?>
<nav class="feed-tabs" aria-label="Feed">
    <a href="/feed?tab=following"<?= $tab == "following" ? ' aria-current="page"' : "" ?>>Following</a>
    <a href="/feed?tab=local"<?= $tab == "local" ? ' aria-current="page"' : "" ?>>Local</a>
</nav>
<div class="feed">
    <?php
    $viewer = (int)$_SESSION['user_id'];
    $following = $tab == "following"
        ? " AND (outfits.`user` = " . $viewer . " OR outfits.`user` IN (SELECT `followed_id` FROM `follows` WHERE `follower_id` = " . $viewer . " AND `state` = 'accepted'))"
        : "";
    $sql = "SELECT `outfit_num` FROM `outfits` WHERE " . visibility_where("outfits", "user") . $following . " order by timestamp DESC limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, null);

    if ($row[0] != '00000') {
        foreach ($row as $outfit) {
            $post = new Post\Outfit($outfit->outfit_num);
            $post->render_outfit();
        }
    } elseif ($tab == "following") {
        ?>
        <p class="empty">Nothing from people you follow yet. Find someone on the <a href="/feed?tab=local">Local</a> feed and follow them from their profile.</p>
        <?php
    } else {
        ?>
        <p class="empty">Nothing to be found</p>
        <?php
    }
    ?>
</div>
