<?php
title_update("feed");
//Following: people you follow, here and elsewhere (and you). Local: everyone here you can see.
//Federated: posts from other servers that people here follow.
$viewer = (int)$_SESSION['user_id'];
$federation = Federation::enabled();
$tabs = $federation ? array("following", "local", "federated") : array("following", "local");
$has_follows = Follow::follows_anyone($viewer) || ($federation && count(RemoteFollow::following($viewer)) > 0);
$tab = $_GET['tab'] ?? ($has_follows ? "following" : "local");
$tab = in_array($tab, $tabs, true) ? $tab : "following";
$limit = 30;
?>
<form action="/search" method="get" class="search feed-search" role="search">
    <label for="feed-search" class="visually-hidden">Find people</label>
    <input type="search" id="feed-search" name="q" placeholder="Find people<?= $federation ? ", here or @name@server" : "" ?>" autocomplete="off">
    <button type="submit" class="secondary">Search</button>
</form>
<nav class="feed-tabs" aria-label="Feed">
    <?php foreach ($tabs as $name): ?>
        <a href="/feed?tab=<?= $name ?>"<?= $tab == $name ? ' aria-current="page"' : "" ?>><?= ucfirst($name) ?></a>
    <?php endforeach; ?>
</nav>
<div class="feed">
    <?php
    //newest first across local outfits and posts from elsewhere
    $items = array();
    if ($tab != "federated") {
        $following = $tab == "following"
            ? " AND (outfits.`user` = " . $viewer . " OR outfits.`user` IN (SELECT `followed_id` FROM `follows` WHERE `follower_id` = " . $viewer . " AND `state` = 'accepted'))"
            : "";
        $sql = "SELECT `outfit_num`, `timestamp` FROM `outfits` WHERE " . visibility_where("outfits", "user") . $following . " order by timestamp DESC limit " . $limit;
        $db = new Mysql();
        $row = $db->Fetch($sql, null);
        foreach ($row[0] != '00000' ? $row : array() as $outfit) {
            $items[] = array("time" => strtotime((string)$outfit->timestamp), "outfit" => $outfit->outfit_num);
        }
    }
    $remote = $tab == "local" ? array() : RemotePost::latest($viewer, $tab == "following" ? "following" : "all", $limit);
    foreach ($remote as $post) {
        $items[] = array("time" => strtotime((string)$post->published), "remote" => $post);
    }
    usort($items, function ($a, $b) {
        return $b['time'] <=> $a['time'];
    });
    $items = array_slice($items, 0, $limit);
    $media = RemotePost::media_for(array_column(array_filter($items, function ($item) {
        return isset($item['remote']);
    }), "remote"));

    foreach ($items as $item) {
        if (isset($item['remote'])) {
            RemotePost::render($item['remote'], $media[(int)$item['remote']->post_num] ?? array());
        } else {
            $post = new Post\Outfit($item['outfit']);
            $post->render_outfit();
        }
    }

    if (count($items) == 0) {
        if ($tab == "following") {
            ?>
            <p class="empty">Nothing from people you follow yet. Find someone on the <a href="/feed?tab=local">Local</a> feed and follow them from their profile<?= $federation ? ", or search for someone elsewhere by their handle" : "" ?>.</p>
            <?php
        } elseif ($tab == "federated") {
            ?>
            <p class="empty">Nothing from other servers yet. Once someone here follows people elsewhere, their posts show up here.</p>
            <?php
        } else {
            ?>
            <p class="empty">Nothing to be found</p>
            <?php
        }
    }
    ?>
</div>
