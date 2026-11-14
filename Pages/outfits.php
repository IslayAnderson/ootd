<?php
title_update("outfits");
$user = unserialize($_SESSION['user']);
?>
<section class="outfits">
    <p class="kicker">Outfits</p>
    <h1>Your <em>outfits</em></h1>
    <?php if (!empty($_GET['deleted'])): ?>
        <p class="notice">Your outfit has been deleted.</p>
    <?php endif; ?>
    <?php
    $params = array(":a" => $user->get_user_id());
    $sql = "SELECT `outfit_num` FROM `outfits` where `user` = :a AND `deleted_at` IS NULL order by `timestamp` DESC limit 100";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    if ($row[0] != '00000') {
        foreach ($row as $outfit) {
            $post = new Post\Outfit($outfit->outfit_num);
            $post->render_outfit(true);
        }
    } else {
        ?>
        <p class="empty">Nothing to be found, post your first outfit from your <a href="/account">account</a>.</p>
        <?php
    }
    ?>
</section>
